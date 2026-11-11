<?php

namespace Tests\Feature;

use App\Models\{AbstractQuotation, BacResolution, ElectronicSignature, Office, ProcurementDocument, Rfq, RfqSupplierQuotation, Role, SignatureRequest, User};
use App\Services\RfqSupplierWorkflowService;
use App\Models\PurchaseOrder;
use App\Models\SupplierSignatureRequest;
use App\Services\PurchaseOrderSupplierService;
use App\Services\PurchaseOrderSignatureWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RfqSupplierResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $officer;
    private Rfq $rfq;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'papertrail_testing');
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling([\Illuminate\Validation\ValidationException::class, \Symfony\Component\HttpKernel\Exception\HttpException::class, \Illuminate\Auth\Access\AuthorizationException::class, \Illuminate\Database\Eloquent\ModelNotFoundException::class]);
        $this->seed();
        $office = Office::create(['code' => 'RFQ-DIGITAL', 'name' => 'RFQ Test Office', 'type' => Office::TYPE_END_USER,
            'is_requesting_office' => true, 'status' => Office::STATUS_ACTIVE]);
        $this->staff = User::factory()->create(['user_id' => 'RFQ-DIGITAL-STAFF', 'role' => User::ROLE_OFFICE_PREPARER,
            'role_id' => Role::where('code', 'office_preparer')->value('id'), 'office_id' => $office->id,
            'must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $this->officer = User::where('user_id', User::PROCUREMENT_CONTROLLER_USER_ID)->firstOrFail();
        $this->officer->update(['must_change_password' => false, 'password' => Hash::make('password'),
            'typed_signature_name' => 'Test Procurement Officer', 'signer_position' => 'Procurement Officer',
            'signature_style' => 'typed', 'signature_setup_completed_at' => now()]);
        $pr = ProcurementDocument::create(['document_type' => 'PR', 'title' => 'Supplies for RFQ digital test', 'fiscal_year' => 2026,
            'status' => ProcurementDocument::STATUS_READY_FOR_RFQ, 'submitting_office_id' => $office->id,
            'current_office_id' => $office->id, 'prepared_by_user_id' => $this->staff->id, 'submitted_by_user_id' => $this->staff->id]);
        $resolution = BacResolution::create(['source_pr_document_id' => $pr->id, 'status' => BacResolution::STATUS_RETURNED_TO_END_USER,
            'fiscal_year' => 2026, 'resolution_number' => 'RFQ-DIGITAL-RES', 'prepared_by_user_id' => $this->staff->id]);
        $this->rfq = Rfq::create(['source_pr_document_id' => $pr->id, 'source_bac_resolution_id' => $resolution->id,
            'rfq_number' => 'RFQ-DIGITAL-001', 'rfq_date' => now()->toDateString(), 'status' => Rfq::STATUS_SUBMITTED,
            'prepared_by_user_id' => $this->staff->id, 'submitted_by_user_id' => $this->staff->id,
            'control_assigned_by_user_id' => $this->officer->id, 'control_assigned_at' => now(),
            'supplier_response_enabled' => true, 'purpose' => 'Digital office supplies', 'abc_amount' => 5000,
            'warranty_text' => 'Six months warranty.', 'price_validity_text' => 'Valid for sixty days.']);
        $this->rfq->items()->create(['item_no' => '1', 'description' => 'Bond paper', 'quantity' => 2.50,
            'unit_of_issue' => 'Reams', 'unit_price' => 99, 'total' => 247.50, 'sort_order' => 0]);
    }

    public function test_po_conforme_uses_selected_verified_supplier_and_requires_a_separate_po_signature(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->signOfficer();
        $other = $this->invite('Other Supplier');
        $winner = $this->invite('Winning Supplier');
        foreach ([$other, $winner] as $quote) {
            $this->post(route('rfq-response.submit', $quote->link_token), $this->payload())->assertSessionHasNoErrors();
            $quote->refresh()->update(['status' => 'verified']);
        }
        $abstract = AbstractQuotation::create(['source_rfq_id' => $this->rfq->id,
            'source_pr_document_id' => $this->rfq->source_pr_document_id,
            'lowest_supplier_name' => 'Winning Supplier', 'status' => AbstractQuotation::STATUS_READY_FOR_PO,
            'prepared_by_user_id' => $this->staff->id]);
        $this->actingAs($this->staff)->get(route('head-office.purchase-orders.create', ['source_abstract_id' => $abstract->id]))
            ->assertOk()->assertSee('Winning Supplier')->assertSee('Authorized Supplier Representative')
            ->assertSee('001-234-567-000')->assertSee('09171234567')
            ->assertViewHas('po', fn ($po) => $po->source_rfq_supplier_quotation_id === $winner->id && ! $po->supplier_conforme_date);
        $this->post(route('head-office.purchase-orders.store'), ['source_abstract_id' => $abstract->id, 'save_action' => 'draft',
            'source_rfq_supplier_quotation_id' => $other->id, // Never trust a posted source quotation ID.
            'items' => [['item_no' => '1', 'description' => 'PO accepted supplies', 'quantity' => 2, 'unit' => 'Reams', 'unit_cost' => 12.34]]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $order = PurchaseOrder::where('source_abstract_id', $abstract->id)->firstOrFail();
        $this->assertSame($winner->id, $order->source_rfq_supplier_quotation_id);
        $this->assertSame('001-234-567-000', $order->supplier_tin);
        $this->assertSame('09171234567', $order->supplier_contact);
        $this->assertSame('Owner', $order->supplier_representative_designation);
        $order->forceFill(['po_number' => 'PO-DIGITAL-CONFORME', 'po_date' => now()->toDateString(), 'status' => PurchaseOrder::STATUS_SUBMITTED])->save();
        $workflow = app(PurchaseOrderSignatureWorkflowService::class);
        $workflow->submit($order, $this->staff);
        $request = $workflow->supplierRequestFor($order);
        $this->assertSame($winner->id, data_get($request->metadata, 'source_rfq_supplier_quotation_id'));
        $this->assertSame('Authorized Supplier Representative', $request->signatory_name);
        $this->assertSame('Owner', $request->signatory_position);
        $this->assertSame('09171234567', $request->supplier_contact);
        $this->assertNotSame($winner->link_token, $request->public_token);
        $this->assertFalse($request->hasSignedDocument());
        $this->assertNull($order->fresh()->supplier_conforme_date);
        $this->get(route('head-office.purchase-orders.show', $order))->assertOk()->assertSee('Copy this private PO signing link');
        auth()->logout();
        $this->get($request->publicUrl())->assertOk()->assertSee('PO accepted supplies')->assertSee('001-234-567-000')
            ->assertSee('Authorized Supplier Representative')->assertDontSee('Other Supplier');
        $signature = ['signatory_name' => 'PO Representative', 'signatory_position' => 'Authorized Manager',
            'signature_data' => $this->payload()['signature_data'], 'signature_consent' => true];
        $order->update(['payment_term' => 'Changed after invitation']);
        $this->post(route('supplier-signing.submit', $request->public_token), $signature)->assertStatus(409);
        $this->assertFalse($request->fresh()->hasSignedDocument());
        $order->update(['payment_term' => null]);
        $this->post(route('supplier-signing.submit', $request->public_token), [
            'signatory_name' => 'PO Representative', 'signatory_position' => 'Authorized Manager',
            'signature_data' => $this->payload()['signature_data'], 'signature_consent' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(SupplierSignatureRequest::STATUS_SUBMITTED, $request->fresh()->status);
        $this->assertSame('PO Representative', $order->refresh()->supplier_representative_name);
        $this->assertSame('Authorized Manager', $order->supplier_representative_designation);
        $this->assertNotNull($order->supplier_conforme_date);
        $this->assertSame('Authorized Supplier Representative', $winner->fresh()->signatory_name);
        $this->assertTrue($winner->responseIsIntact());
        $this->assertDatabaseMissing('audit_logs', ['request_url' => $request->publicUrl()]);
        $order->update(['status' => PurchaseOrder::STATUS_SUBMITTED]);
        $workflow->submit($order, $this->staff);
        $replacement = $workflow->supplierRequestFor($order);
        $this->assertNotSame($request->public_token, $replacement->public_token);
        $this->assertFalse($replacement->hasSignedDocument());
        $this->assertTrue($request->fresh()->hasSignedDocument());
    }

    public function test_po_never_prefills_an_unverified_tampered_or_unrelated_supplier_response(): void
    {
        $this->signOfficer();
        $quote = $this->invite();
        $this->post(route('rfq-response.submit', $quote->link_token), $this->payload())->assertSessionHasNoErrors();
        $abstract = new AbstractQuotation(['source_rfq_id' => $this->rfq->id, 'lowest_supplier_name' => 'Supplier One']);
        $service = app(PurchaseOrderSupplierService::class);
        $this->assertSame([], $service->defaults($abstract));
        $quote->refresh()->update(['status' => 'verified']);
        $this->assertSame($quote->id, $service->defaults($abstract)['source_rfq_supplier_quotation_id']);
        $this->assertSame([], $service->defaults($abstract, 'Different Supplier'));
        $this->assertSame([], $service->defaults(null, 'Supplier One'));
        $quote->update(['response_snapshot' => [...$quote->response_snapshot, 'supplier_tin' => 'TAMPERED']]);
        $this->assertSame([], $service->defaults($abstract));
    }

    private function signOfficer(): void
    {
        $this->actingAs($this->staff)->post(route('head-office.rfqs.request-officer', $this->rfq),
            ['procurement_officer_user_id' => $this->officer->id])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();
        $this->assertDatabaseHas('signature_requests', ['document_type' => 'rfq', 'document_id' => $this->rfq->id]);
        $signatureRequest = SignatureRequest::forDocument('rfq', $this->rfq->id)->firstOrFail();
        $this->actingAs($this->officer)->get(route('signature-requests.show', $signatureRequest))
            ->assertOk()->assertSee('Digital office supplies')->assertSee('Bond paper');
        $this->post(route('signature-requests.sign', $signatureRequest), ['current_password' => 'password', 'signature_consent' => true])
            ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();
        $this->assertSame(Rfq::STATUS_ISSUED, $this->rfq->fresh()->status);
    }

    private function invite(string $name = 'Supplier One'): RfqSupplierQuotation
    {
        $this->actingAs($this->staff)->post(route('head-office.rfqs.quotations.invite', $this->rfq),
            ['supplier_name' => $name, 'expires_on' => now('Asia/Manila')->addDays(7)->toDateString()])
            ->assertSessionHasNoErrors()->assertRedirect();
        return $this->rfq->supplierQuotations()->latest('id')->firstOrFail();
    }

    private function payload(string $price = '12.34'): array
    {
        $image = imagecreatetruecolor(180, 60);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        $ink = imagecolorallocate($image, 10, 30, 70);
        imagesetthickness($image, 3);
        imageline($image, 10, 40, 160, 15, $ink);
        ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
        return ['supplier_contact' => '09171234567', 'supplier_tin' => '001-234-567-000', 'supplier_address' => 'Tomas Oppus, Southern Leyte',
            'signatory_name' => 'Authorized Supplier Representative', 'signatory_position' => 'Owner', 'delivery_days' => 7,
            'philgeps_registration_number' => 'PHILGEPS-1234', 'philgeps_registration_type' => 'Registered supplier',
            'items' => [['unit_price' => $price]], 'signature_data' => 'data:image/png;base64,'.base64_encode($png), 'signature_consent' => true];
    }

    public function test_complete_digital_rfq_flow_preserves_supplier_details_and_imports_only_verified_prices(): void
    {
        $this->signOfficer();
        $quote = $this->invite();
        $other = $this->invite('Private Supplier Two');
        $this->assertNotSame($quote->link_token, $quote->getRawOriginal('link_token'));
        $url = route('rfq-response.show', $quote->link_token);
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Supplier One')->assertSee('Bond paper')
            ->assertSee('supplier_contact', false)->assertSee('supplier_tin', false)->assertDontSee('Private Supplier Two')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $before = $this->rfq->items()->first()->getAttributes();
        $payload = $this->payload();
        $payload['items'][0]['quantity'] = 999999;
        $payload['rfq_number'] = 'FORGED';
        $payload['supplier_name'] = 'FORGED SUPPLIER';
        $this->post(route('rfq-response.submit', $quote->link_token), $payload)->assertSessionHasNoErrors()->assertRedirect($url);
        $quote->refresh();
        $this->assertSame('submitted', $quote->status);
        $this->assertSame('30.85', $quote->total_amount);
        $this->assertSame('001-234-567-000', $quote->response_snapshot['supplier_tin']);
        $this->assertSame('09171234567', $quote->response_snapshot['supplier_contact']);
        $this->assertSame('Supplier One', $quote->response_snapshot['supplier_name']);
        $this->assertTrue($quote->responseIsIntact());
        $this->assertSame('RFQ-DIGITAL-001', $this->rfq->fresh()->rfq_number);
        $this->assertSame($before, $this->rfq->items()->first()->getAttributes());
        $this->assertSame('pending', $other->fresh()->status);
        $this->get($url)->assertOk()->assertSee('001-234-567-000')->assertSee('09171234567')->assertDontSee('data-rfq-response-form', false);
        $this->post(route('rfq-response.submit', $quote->link_token), $this->payload('1.00'))->assertStatus(409);
        $this->assertCount(0, app(RfqSupplierWorkflowService::class)->verified($this->rfq));
        $this->actingAs($this->staff)->get(route('head-office.abstracts.create', ['source_rfq_id' => $this->rfq->id]))
            ->assertOk()->assertViewHas('sourceRfq', null);
        $this->get(route('head-office.rfqs.quotations.show', [$this->rfq, $quote]))->assertOk()->assertSee('Verify Quotation')->assertSee('001-234-567-000');
        $this->patch(route('head-office.rfqs.quotations.review', [$this->rfq, $quote]), ['decision' => 'verified'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Rfq::STATUS_QUOTED, $this->rfq->fresh()->status);
        auth()->logout();
        $this->post(route('rfq-response.submit', $other->link_token), $this->payload('20.00'))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->staff)->patch(route('head-office.rfqs.quotations.review', [$this->rfq, $other]), ['decision' => 'verified'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('head-office.abstracts.create', ['source_rfq_id' => $this->rfq->id]))->assertOk()
            ->assertViewHas('sourceRfq', fn ($source) => $source?->id === $this->rfq->id)
            ->assertViewHas('abstract', fn ($abstract) => $abstract->supplier_1_name === 'Supplier One'
                && $abstract->items->first()->supplier_1_amount === '12.34' && $abstract->supplier_2_name === 'Private Supplier Two'
                && $abstract->items->first()->supplier_2_amount === '20.00');
        $this->assertDatabaseHas('system_notifications', ['user_id' => $this->staff->id, 'title' => 'Signed supplier quotation received']);
        $this->assertDatabaseHas('svp_chain_events', ['document_type' => 'RFQ', 'document_id' => $this->rfq->id, 'action' => 'Signed supplier quotation received']);
        $this->assertSame(0, \App\Models\AuditLog::where('request_url', 'like', '%'.$quote->link_token.'%')->count());
    }

    public function test_contact_tin_all_prices_consent_and_a_real_signature_are_required(): void
    {
        $this->signOfficer(); $quote = $this->invite(); auth()->logout();
        $payload = $this->payload();
        unset($payload['supplier_contact'], $payload['supplier_tin'], $payload['signature_consent']);
        $payload['items'] = [];
        $this->post(route('rfq-response.submit', $quote->link_token), $payload)
            ->assertSessionHasErrors(['supplier_contact', 'supplier_tin', 'signature_consent', 'items']);
        $payload = $this->payload(); $payload['signature_data'] = 'data:image/png;base64,'.base64_encode('not an image');
        $this->post(route('rfq-response.submit', $quote->link_token), $payload)->assertSessionHasErrors('signature_data');
        $payload = $this->payload('-2.00');
        $this->post(route('rfq-response.submit', $quote->link_token), $payload)->assertSessionHasErrors('items.0.unit_price');
        $this->assertNull($quote->fresh()->submitted_at);
    }

    public function test_private_tokens_expiry_and_cancellation_prevent_supplier_access(): void
    {
        $this->signOfficer(); $quote = $this->invite();
        $this->get(route('rfq-response.show', str_repeat('a', 64)))->assertNotFound();
        $quote->update(['expires_at' => now()->subMinute()]); auth()->logout();
        $this->get(route('rfq-response.show', $quote->link_token))->assertGone();
        $this->post(route('rfq-response.submit', $quote->link_token), $this->payload())->assertGone();
        $next = $this->invite('Supplier With Cancelled Link');
        $this->patch(route('head-office.rfqs.quotations.cancel', [$this->rfq, $next]))->assertRedirect(); auth()->logout();
        $this->get(route('rfq-response.show', $next->link_token))->assertGone();
        $this->post(route('rfq-response.submit', $next->link_token), $this->payload())->assertGone();
    }

    public function test_other_staff_cannot_manage_supplier_links_or_read_private_quotations(): void
    {
        $this->signOfficer(); $quote = $this->invite();
        $other = User::factory()->create(['user_id' => 'RFQ-UNASSIGNED', 'role' => User::ROLE_OFFICE_PREPARER,
            'role_id' => $this->staff->role_id, 'office_id' => $this->staff->office_id, 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]);
        $this->actingAs($other)->get(route('head-office.rfqs.show', $this->rfq))->assertOk()->assertDontSee($quote->link_token);
        $this->get(route('head-office.rfqs.quotations.show', [$this->rfq, $quote]))->assertForbidden();
        $this->post(route('head-office.rfqs.quotations.invite', $this->rfq), ['supplier_name' => 'Intruder'])->assertForbidden();
        $this->patch(route('head-office.rfqs.quotations.review', [$this->rfq, $quote]), ['decision' => 'verified'])->assertForbidden();
        $this->patch(route('head-office.rfqs.quotations.cancel', [$this->rfq, $quote]))->assertForbidden();
        $this->post(route('signature-requests.sign', SignatureRequest::forDocument('rfq', $this->rfq->id)->first()), ['current_password' => 'password', 'signature_consent' => true])->assertForbidden();
    }

    public function test_control_and_officer_signature_are_required_and_changed_issued_content_is_blocked(): void
    {
        $this->actingAs($this->staff)->post(route('head-office.rfqs.quotations.invite', $this->rfq),
            ['supplier_name' => 'Supplier', 'expires_on' => now()->addDays(3)->toDateString()])->assertSessionHasErrors('supplier_name');
        $this->rfq->update(['status' => Rfq::STATUS_PENDING_CONTROL, 'control_assigned_at' => null]);
        $this->post(route('head-office.rfqs.request-officer', $this->rfq), ['procurement_officer_user_id' => $this->officer->id])->assertSessionHasErrors('procurement_officer_user_id');
        $this->rfq->update(['status' => Rfq::STATUS_SUBMITTED, 'control_assigned_at' => now()]);
        $this->signOfficer(); $quote = $this->invite();
        $this->rfq->items()->first()->update(['description' => 'Changed after signature']); auth()->logout();
        $this->post(route('rfq-response.submit', $quote->link_token), $this->payload())->assertGone();
        $this->assertNull($quote->fresh()->submitted_at);
    }

    public function test_tampered_response_cannot_be_verified_or_prefilled(): void
    {
        $this->signOfficer(); $quote = $this->invite(); auth()->logout();
        $this->post(route('rfq-response.submit', $quote->link_token), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $quote->refresh(); $snapshot = $quote->response_snapshot; $snapshot['items'][0]['unit_price'] = '0.01';
        $quote->update(['response_snapshot' => $snapshot]);
        $this->actingAs($this->staff)->patch(route('head-office.rfqs.quotations.review', [$this->rfq, $quote]), ['decision' => 'verified'])->assertStatus(409);
        $this->assertSame('submitted', $quote->fresh()->status);
        $this->assertCount(0, app(RfqSupplierWorkflowService::class)->verified($this->rfq));
    }

    public function test_procurement_officer_can_return_an_rfq_for_correction(): void
    {
        $this->actingAs($this->staff)->post(route('head-office.rfqs.request-officer', $this->rfq),
            ['procurement_officer_user_id' => $this->officer->id])->assertSessionHasNoErrors()->assertRedirect();
        $signatureRequest = SignatureRequest::forDocument('rfq', $this->rfq->id)->firstOrFail();
        $this->actingAs($this->officer)->post(route('signature-requests.decline', $signatureRequest), ['reason' => 'Please correct the specifications.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Rfq::STATUS_RETURNED, $this->rfq->fresh()->status);
        $this->assertFalse($this->rfq->fresh()->isControlled());
        $this->assertDatabaseHas('system_notifications', ['user_id' => $this->staff->id, 'title' => 'RFQ returned by Procurement Officer']);
    }
}
