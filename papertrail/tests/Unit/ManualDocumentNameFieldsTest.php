<?php

namespace Tests\Unit;

use App\Http\Controllers\HeadOffice\PurchaseRequestController;
use App\Models\AbstractQuotation;
use App\Models\InspectionAcceptanceRecord;
use App\Models\ProcurementDocument;
use App\Models\PurchaseOrder;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use ReflectionMethod;
use Tests\TestCase;

class ManualDocumentNameFieldsTest extends TestCase
{
    public function test_new_pr_names_are_blank_and_saved_names_are_kept(): void
    {
        $user = new User(['user_id' => 'MAO-STAFF', 'name' => 'Example Staff', 'role' => User::ROLE_OFFICE_PREPARER]);
        $user->setRelation('assignedRole', null)->setRelation('assignedOffice', null);
        $method = new ReflectionMethod(PurchaseRequestController::class, 'defaultPrSignatories');
        $controller = new PurchaseRequestController();
        $defaults = $method->invoke($controller, null, $user, null);
        foreach ($defaults as $signatory) {
            $this->assertSame('', $signatory['printed_name']);
        }

        $document = new ProcurementDocument([
            'requested_by_name' => 'Saved Requester', 'approved_by_name' => 'Saved Approver',
            'pr_signatories' => ['budget' => ['printed_name' => 'Saved Budget Officer']],
        ]);
        $saved = $method->invoke($controller, $document, $user, null);
        $this->assertSame('Saved Requester', $saved['requested']['printed_name']);
        $this->assertSame('Saved Approver', $saved['approved']['printed_name']);
        $this->assertSame('Saved Budget Officer', $saved['budget']['printed_name']);
    }

    public function test_new_rfq_officer_names_are_not_prepopulated(): void
    {
        foreach ([\App\Http\Controllers\HeadOffice\RfqController::class, \App\Http\Controllers\BacSecretariat\RfqController::class] as $class) {
            $defaults = (new ReflectionMethod($class, 'defaultData'))->invoke(new $class(), null, null, 1);
            $this->assertNull($defaults['procurement_officer_name']);
        }
    }

    public function test_inspection_names_use_placeholders_and_keep_entered_values(): void
    {
        $po = (new PurchaseOrder())->setRelation('items', collect())->setRelation('sourcePrDocument', null);
        foreach ([new InspectionAcceptanceRecord(), new InspectionAcceptanceRecord(['inspector_name' => 'Saved Inspector', 'inspection_chair_name' => 'Saved Assessor', 'acceptance_officer_name' => 'Saved Receiver'])] as $record) {
            $html = view('head-office.inspection.partials.report', ['purchaseOrder' => $po, 'record' => $record, 'editable' => true])->render();
            $xpath = $this->xpath($html);
            foreach (['inspector_name', 'inspection_chair_name', 'acceptance_officer_name'] as $name) {
                $input = $xpath->query('//input[@name="'.$name.'"]')->item(0);
                $this->assertNotNull($input);
                $this->assertSame('Enter Name', $input->getAttribute('placeholder'));
                $this->assertSame($record->{$name} ?? '', $input->getAttribute('value'));
            }
        }
    }

    public function test_abstract_manual_names_start_blank_and_keep_saved_names(): void
    {
        foreach (['head-office', 'bac-secretariat'] as $role) {
            foreach ([null, ['bac_chairperson' => ['name' => 'Saved Chair']]] as $committee) {
                $abstract = (new AbstractQuotation(['committee_json' => $committee]))->setRelation('items', collect());
                $html = view($role.'.abstracts.partials.abstract-excel-form', [
                    'abstract' => $abstract, 'sourceDocument' => null, 'sourceRfq' => null,
                    'sourceResolution' => null, 'mode' => 'create', 'signatoryUsers' => collect(),
                ])->render();
                $name = $this->xpath($html)->query('//span[@data-committee-role="bac_chairperson"]')->item(0);
                $this->assertNotNull($name);
                $this->assertSame($committee ? 'Saved Chair' : '', trim($name->textContent));
                $this->assertSame('Enter Name', $name->getAttribute('data-placeholder'));
            }
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
