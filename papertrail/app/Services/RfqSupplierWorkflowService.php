<?php

namespace App\Services;

use App\Models\{AbstractQuotation, AbstractQuotationItem, ElectronicSignature, Rfq, RfqSupplierQuotation, SignatureRequest, SystemNotification, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RfqSupplierWorkflowService
{
    public const CONSENT = 'I confirm that I am authorized to submit this quotation for the supplier, have reviewed the RFQ terms and all quoted prices, and agree to sign this complete quotation electronically.';

    public function canManage(User $user, Rfq $rfq): bool
    {
        $ids = [(int) $user->id];
        if ($actor = app(BacsecMpdoWorkspaceService::class)->existing($user)) {
            $ids[] = (int) $actor->id;
        }
        return $user->isActive() && array_intersect($ids, [(int) $rfq->prepared_by_user_id, (int) $rfq->submitted_by_user_id])
            && ($user->canManageSvpDocuments() || $user->hasRole(User::ROLE_BAC_SECRETARIAT));
    }

    public function officerSignature(Rfq $rfq): ?ElectronicSignature
    {
        return ElectronicSignature::forDocument('rfq', $rfq->id)->where('signatory_slot', 'procurement_officer')
            ->where('signer_user_id', $rfq->procurement_officer_user_id)
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)->whereNotNull('signed_at')
            ->whereNull('revoked_at')->latest('id')->first();
    }

    public function officerSignatureIsCurrent(Rfq $rfq): bool
    {
        $signature = $this->officerSignature($rfq);
        return $signature && hash_equals($signature->document_hash_before ?? '', $this->documentHash($rfq));
    }

    public function documentHash(Rfq $rfq): string
    {
        return RfqSupplierQuotation::hashSnapshot($this->issuedSnapshot($rfq, false));
    }

    public function issuedSnapshot(Rfq $rfq, bool $includeSignature = true): array
    {
        $rfq->load('items');
        $snapshot = $rfq->only(['rfq_number', 'purpose', 'abc_amount', 'delivery_period', 'warranty_text',
            'price_validity_text', 'philgeps_requirement_text', 'mayors_permit_requirement_text', 'procurement_officer_name',
            'procurement_officer_designation', 'procurement_officer_user_id']);
        $snapshot['rfq_date'] = $rfq->rfq_date?->format('Y-m-d');
        $snapshot['items'] = $rfq->items->map(fn ($item) => $item->only(['id', 'item_no', 'description', 'quantity', 'unit_of_issue']))->values()->all();
        if ($includeSignature && ($signature = $this->officerSignature($rfq))) {
            $image = null;
            if ($signature->signature_image_path && Storage::disk('local')->exists($signature->signature_image_path)) {
                $mime = Storage::disk('local')->mimeType($signature->signature_image_path);
                if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                    $image = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($signature->signature_image_path));
                }
            }
            $snapshot['officer_signature'] = ['name' => $signature->typed_signature_name ?: $signature->signer_name,
                'position' => $signature->signer_position, 'signed_at' => $signature->signed_at->toIso8601String(),
                'code' => $signature->signature_code, 'image' => $image];
        }
        return $snapshot;
    }

    public function requestOfficer(Rfq $rfq, User $signer, User $requester): SignatureRequest
    {
        if (! $rfq->isControlled() || $signer->isOfficeDocumentPreparer() || $signer->principal_user_id || ! $signer->isActive()) {
            throw ValidationException::withMessages(['procurement_officer_user_id' => 'Select an active authorized signatory after the RFQ number and date have been assigned.']);
        }
        if ($this->officerSignature($rfq) || $rfq->supplierQuotations()->exists()) {
            throw ValidationException::withMessages(['procurement_officer_user_id' => 'This RFQ already has a signature or supplier invitations. Its signatory cannot be changed.']);
        }
        SignatureRequest::forDocument('rfq', $rfq->id)->whereIn('status', SignatureRequest::OPEN_STATUSES)->update(['status' => SignatureRequest::STATUS_CANCELLED]);
        $rfq->update(['supplier_response_enabled' => true, 'procurement_officer_user_id' => $signer->id,
            'procurement_officer_name' => $signer->name]);
        return app(SignatureRequestService::class)->createOrUpdateRequest($rfq, 'rfq', [
            'slot' => 'procurement_officer', 'label' => 'Procurement Officer', 'user_id' => $signer->id,
            'account_code' => $signer->user_id, 'role_name' => 'Procurement Officer', 'required' => true,
        ], $requester);
    }

    public function handleOfficerSigned(Rfq $rfq, User $signer): void
    {
        $rfq->update(['status' => Rfq::STATUS_ISSUED]);
        app(SvpChainService::class)->linkRfq($rfq, $signer, 'Procurement Officer signed RFQ');
        $this->notifyOwner($rfq, 'RFQ signed and ready for suppliers', 'The Procurement Officer signed RFQ '.$rfq->rfq_number.'. You can now create private supplier response links.');
    }

    public function notifyOwner(Rfq $rfq, string $title, string $message): void
    {
        SystemNotificationService::notify($rfq->preparedBy ?: $rfq->submittedBy, $title, $message,
            SystemNotification::TYPE_INFO, 'RFQ', $rfq, route('head-office.rfqs.show', $rfq));
    }

    public function verified(Rfq $rfq): Collection
    {
        return $rfq->supplierQuotations()->where('status', 'verified')->orderBy('submitted_at')->orderBy('id')->get()
            ->filter(fn ($quotation) => $quotation->responseIsIntact());
    }

    public function prefillAbstract(AbstractQuotation $abstract, ?Rfq $rfq): void
    {
        if (! $rfq || ! $rfq->supplier_response_enabled) {
            return;
        }
        $quotes = $this->verified($rfq)->take(5)->values();
        if ($quotes->isEmpty()) {
            return;
        }
        $summary = [];
        foreach ($quotes as $index => $quote) {
            $key = 'supplier_'.($index + 1);
            $abstract->{$key.'_name'} = $quote->response_snapshot['supplier_name'];
            $summary[$key.'_name'] = $quote->response_snapshot['supplier_name'];
            $summary[$key.'_total'] = $quote->response_snapshot['total_amount'];
        }
        $abstract->suppliers_json = $summary;
        $abstract->setRelation('items', collect($quotes->first()->issued_snapshot['items'])->map(function ($item, $index) use ($quotes) {
            $values = ['item_no' => $item['item_no'] ?: (string) ($index + 1), 'name_of_goods_services' => $item['description'],
                'quantity' => $item['quantity'], 'unit_of_measure' => $item['unit_of_issue'], 'sort_order' => $index];
            foreach ($quotes as $supplierIndex => $quote) {
                $line = collect($quote->response_snapshot['items'])->firstWhere('rfq_item_id', $item['id']);
                $values['supplier_'.($supplierIndex + 1).'_amount'] = $line['unit_price'] ?? null;
            }
            return new AbstractQuotationItem($values);
        }));
    }
}
