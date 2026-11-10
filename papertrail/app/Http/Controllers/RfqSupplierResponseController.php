<?php

namespace App\Http\Controllers;

use App\Models\{Rfq, RfqSupplierQuotation, User};
use App\Services\{AuditLogger, RfqSupplierWorkflowService, SvpChainService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RfqSupplierResponseController extends Controller
{
    public function __construct(private readonly RfqSupplierWorkflowService $workflow)
    {
    }

    private function authorizeRfq(Request $request, Rfq $rfq): void
    {
        abort_unless($this->workflow->canManage($request->user(), $rfq), 403);
    }

    public function requestOfficer(Request $request, Rfq $rfq)
    {
        $this->authorizeRfq($request, $rfq);
        $data = $request->validate(['procurement_officer_user_id' => ['required', 'integer', 'exists:users,id']]);
        DB::transaction(function () use ($request, $rfq, $data) {
            $locked = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            $this->workflow->requestOfficer($locked, User::findOrFail($data['procurement_officer_user_id']), $request->user());
            app(SvpChainService::class)->linkRfq($locked, $request->user(), 'RFQ routed for Procurement Officer signature');
        });
        return back()->with('status', 'RFQ routed to the selected Procurement Officer for electronic signature.');
    }

    public function invite(Request $request, Rfq $rfq)
    {
        $this->authorizeRfq($request, $rfq);
        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'], 'supplier_email' => ['nullable', 'email:rfc', 'max:255'],
            'expires_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Manila')->toDateString(), 'before_or_equal:'.now('Asia/Manila')->addDays(30)->toDateString()],
        ]);
        DB::transaction(function () use ($request, $rfq, $data) {
            $locked = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isControlled() || ! $this->workflow->officerSignatureIsCurrent($locked)) {
                throw ValidationException::withMessages(['supplier_name' => 'The controlled RFQ must have its current Procurement Officer signature before inviting suppliers.']);
            }
            $snapshot = $this->workflow->issuedSnapshot($locked);
            if (empty($snapshot['items']) || collect($snapshot['items'])->contains(fn ($item) => ! filled($item['description']) || (float) $item['quantity'] <= 0 || (float) $item['quantity'] > 1000000)) {
                throw ValidationException::withMessages(['supplier_name' => 'The issued RFQ must have complete descriptions and positive quantities before inviting suppliers.']);
            }
            $name = trim($data['supplier_name']);
            if ($locked->supplierQuotations()->whereRaw('LOWER(supplier_name) = ?', [mb_strtolower($name)])
                ->whereNotIn('status', ['rejected', 'cancelled'])->where(fn ($active) => $active->whereIn('status', ['submitted', 'verified'])->orWhere('expires_at', '>', now()))->exists()) {
                throw ValidationException::withMessages(['supplier_name' => 'This supplier already has an active or received quotation for this RFQ.']);
            }
            $token = Str::random(64);
            $locked->supplierQuotations()->create([
                'quotation_uuid' => (string) Str::uuid(), 'requested_by_user_id' => $request->user()->id,
                'token_hash' => hash('sha256', $token), 'link_token' => $token, 'supplier_name' => $name,
                'supplier_email' => $data['supplier_email'] ?? null, 'issued_snapshot' => $snapshot,
                'expires_at' => Carbon::parse($data['expires_on'], 'Asia/Manila')->endOfDay()->utc(), 'status' => 'pending',
            ]);
            app(SvpChainService::class)->linkRfq($locked, $request->user(), 'Private supplier quotation link created', 'Supplier: '.$name);
            AuditLogger::log('RFQ', 'Supplier Invited', 'A private supplier response link was created.', $locked);
        });
        return back()->with('status', 'Private response link created. Copy the supplier’s link below and share it with their authorized representative.');
    }

    public function show(Request $request, Rfq $rfq, RfqSupplierQuotation $quotation)
    {
        $this->authorizeRfq($request, $rfq);
        abort_unless((int) $quotation->rfq_id === (int) $rfq->id, 404);
        return view('rfq-responses.review', ['rfq' => $rfq, 'quotation' => $quotation]);
    }

    public function review(Request $request, Rfq $rfq, RfqSupplierQuotation $quotation)
    {
        $this->authorizeRfq($request, $rfq);
        abort_unless((int) $quotation->rfq_id === (int) $rfq->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:verified,rejected'],
            'review_remarks' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $rfq, $quotation, $data) {
            $lockedRfq = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            $locked = RfqSupplierQuotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'submitted', 409, 'This response is not awaiting verification.');
            abort_unless($lockedRfq->isControlled() && $this->workflow->officerSignatureIsCurrent($lockedRfq), 409, 'The issued RFQ is no longer current.');
            abort_unless($locked->responseIsIntact(), 409, 'The saved quotation could not be verified.');
            $locked->update(['status' => $data['decision'], 'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(), 'review_remarks' => $data['review_remarks'] ?? null]);
            if ($data['decision'] === 'verified') {
                $lockedRfq->update(['status' => Rfq::STATUS_QUOTED]);
            }
            app(SvpChainService::class)->linkRfq($lockedRfq, $request->user(), 'Supplier quotation '.$data['decision'], 'Supplier: '.$locked->supplier_name);
            AuditLogger::log('RFQ', 'Supplier Quotation Reviewed', 'Supplier response '.$data['decision'].'.', $lockedRfq);
        });
        return back()->with('status', $data['decision'] === 'verified' ? 'Quotation verified. Its prices are now available for the Abstract.' : 'Quotation rejected. Its prices will not be used for the Abstract.');
    }

    public function cancel(Request $request, Rfq $rfq, RfqSupplierQuotation $quotation)
    {
        $this->authorizeRfq($request, $rfq);
        abort_unless((int) $quotation->rfq_id === (int) $rfq->id, 404);
        DB::transaction(function () use ($request, $rfq, $quotation) {
            $lockedRfq = Rfq::whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            $locked = RfqSupplierQuotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['pending', 'viewed'], true), 409, 'A submitted quotation cannot be cancelled.');
            $locked->update(['status' => 'cancelled']);
            app(SvpChainService::class)->linkRfq($lockedRfq, $request->user(), 'Supplier quotation link cancelled', 'Supplier: '.$locked->supplier_name);
        });
        return back()->with('status', 'Supplier response link cancelled.');
    }
}
