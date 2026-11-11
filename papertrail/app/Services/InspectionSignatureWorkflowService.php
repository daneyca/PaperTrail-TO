<?php

namespace App\Services;

use App\Models\{ElectronicSignature, InspectionAcceptanceRecord, RfqSupplierQuotation, SignatureRequest, SystemNotification, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionSignatureWorkflowService
{
    public const TYPE = 'inspection_acceptance';
    public const SIGNERS = [
        'inspection_officer' => ['MO-STAFF', 'inspector', 'Inspection Officer - Des.'],
        'inspection_chair' => ['MCR-001', 'inspection_chair', 'MCR'],
        'acceptance_officer' => [User::PROCUREMENT_CONTROLLER_USER_ID, 'acceptance_officer', 'Admin Aide I'],
    ];

    public function submit(InspectionAcceptanceRecord $record, User $requester): void
    {
        DB::transaction(function () use ($record, $requester) {
            $record = InspectionAcceptanceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($this->isLocked($record)) {
                throw ValidationException::withMessages(['signatures' => 'This report is already routed or signed. Use Revise Report before making changes.']);
            }
            if (! $record->inspection_date || ! $record->acceptance_date) {
                throw ValidationException::withMessages(['acceptance_date' => 'Enter the inspection date and date received before submitting for signatures.']);
            }
            $signers = [];
            foreach (self::SIGNERS as $slot => [$account, $field, $designation]) {
                $recipient = User::where('user_id', $account)->where('status', User::STATUS_ACTIVE)->first();
                if (! $recipient || ! $recipient->loginAccount()->isActive()) {
                    throw ValidationException::withMessages(['signatures' => "The assigned {$account} account must be active before routing this report."]);
                }
                $record->{$field.'_name'} = $record->{$field.'_name'} ?: $recipient->name;
                $record->{$field.'_designation'} = $record->{$field.'_designation'} ?: $designation;
                $signers[] = ['slot' => $slot, 'account_code' => $account, 'label' => $designation,
                    'designation' => $record->{$field.'_designation'}, 'role_name' => $record->{$field.'_designation'},
                    'printed_name' => $record->{$field.'_name'}, 'required' => true, 'new_cycle' => true];
            }
            $record->forceFill(['status' => InspectionAcceptanceRecord::STATUS_PENDING_SIGNATURES,
                'signature_revision' => $record->signature_revision + 1])->save();
            $record->forceFill(['signature_document_hash' => $this->hash($record)])->save();
            foreach (app(SignatureRequestService::class)->createRequestsForDocument($record, self::TYPE, $signers, $requester) as $request) {
                $expected = self::SIGNERS[$request->signatory_slot][0];
                if ($request->requestedTo?->user_id !== $expected) {
                    throw ValidationException::withMessages(['signatures' => 'An assigned signatory account could not be resolved.']);
                }
                $request->update(['metadata' => [...($request->metadata ?: []),
                    'inspection_revision' => $record->signature_revision, 'document_hash' => $record->signature_document_hash]]);
            }
            app(SvpChainService::class)->linkInspection($record, $requester, 'IAR submitted for three required signatures');
        });
    }

    public function isLocked(InspectionAcceptanceRecord $record): bool
    {
        return in_array($record->status, [InspectionAcceptanceRecord::STATUS_PENDING_SIGNATURES,
            InspectionAcceptanceRecord::STATUS_FULLY_SIGNED, InspectionAcceptanceRecord::STATUS_ACCEPTED,
            InspectionAcceptanceRecord::STATUS_COMPLETED], true);
    }

    public function currentRequests(InspectionAcceptanceRecord $record)
    {
        return SignatureRequest::forDocument(self::TYPE, $record->id)->where('is_required', true)
            ->where('metadata->inspection_revision', $record->signature_revision);
    }

    public function isFullySigned(InspectionAcceptanceRecord $record): bool
    {
        if (! $record->signature_document_hash || ! hash_equals($record->signature_document_hash, $this->hash($record))) {
            return false;
        }
        $requests = $this->currentRequests($record)->get();
        $slots = $this->slots($record);
        return $requests->count() === 3 && $requests->pluck('signatory_slot')->unique()->count() === 3
            && $requests->every(fn ($request) => $request->status === SignatureRequest::STATUS_SIGNED
                && in_array($request->signatory_slot, array_keys(self::SIGNERS), true)
                && $slots->has($request->signatory_slot));
    }

    public function slots(InspectionAcceptanceRecord $record): Collection
    {
        if (! $record->exists || ! $record->signature_document_hash
            || ! hash_equals($record->signature_document_hash, $this->hash($record))) {
            return collect();
        }
        return ElectronicSignature::forDocument(self::TYPE, $record->id)
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
            ->where('document_hash_before', $record->signature_document_hash)
            ->whereIn('signature_request_id', $this->currentRequests($record)->select('id'))
            ->get()->keyBy('signatory_slot');
    }

    public function canSign(InspectionAcceptanceRecord $record, User $user, ?SignatureRequest $request): bool
    {
        return $record->status === InspectionAcceptanceRecord::STATUS_PENDING_SIGNATURES
            && $request && $request->isOpen() && (int) $request->requested_to_user_id === (int) $user->id
            && $request->is_required && $request->document_type === self::TYPE && (int) $request->document_id === (int) $record->id
            && (int) data_get($request->metadata, 'inspection_revision') === (int) $record->signature_revision
            && isset(self::SIGNERS[$request->signatory_slot]) && $user->user_id === self::SIGNERS[$request->signatory_slot][0]
            && $record->signature_document_hash && hash_equals($record->signature_document_hash, $this->hash($record))
            && hash_equals($record->signature_document_hash, (string) data_get($request->metadata, 'document_hash', ''))
            && ! app(SignatureRequestService::class)->signedSignatureForRequest($request);
    }

    public function handleSigned(InspectionAcceptanceRecord $record, User $signer): void
    {
        if ($this->isFullySigned($record)) {
            $record->update(['status' => InspectionAcceptanceRecord::STATUS_FULLY_SIGNED]);
            $this->notifyOwner($record, 'IAR signatures completed', 'All three required Inspection / Acceptance signatures are complete.');
        }
        app(SvpChainService::class)->linkInspection($record, $signer, 'IAR electronic signature recorded');
    }

    public function revise(InspectionAcceptanceRecord $record, User $actor, string $reason = 'Report reopened for correction', bool $declined = false): void
    {
        DB::transaction(function () use ($record, $actor, $reason, $declined) {
            $record = InspectionAcceptanceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->status === InspectionAcceptanceRecord::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['signatures' => 'A completed report cannot be reopened.']);
            }
            $requests = $this->currentRequests($record)->get();
            foreach ($requests as $request) {
                $request->update(['is_required' => false, 'status' => $request->status === SignatureRequest::STATUS_DECLINED
                    ? SignatureRequest::STATUS_DECLINED : SignatureRequest::STATUS_CANCELLED, 'cancelled_at' => now()]);
            }
            ElectronicSignature::whereIn('signature_request_id', $requests->pluck('id'))
                ->where('signature_status', ElectronicSignature::STATUS_SIGNED)->update(['signature_status' => ElectronicSignature::STATUS_INVALIDATED]);
            $record->update(['status' => $declined ? InspectionAcceptanceRecord::STATUS_RETURNED : InspectionAcceptanceRecord::STATUS_DRAFT,
                'signature_document_hash' => null]);
            AuditLogger::signature('iar_reopened_for_signatures', $record, ['description' => $reason,
                'metadata' => ['signature_revision' => $record->signature_revision, 'actor_user_id' => $actor->id]]);
            app(SvpChainService::class)->linkInspection($record, $actor, $reason);
            if ($declined) {
                $this->notifyOwner($record, 'IAR returned for correction', $reason);
            }
        });
    }

    public function snapshot(InspectionAcceptanceRecord $record): array
    {
        $order = $record->purchaseOrder()->with(['items', 'sourcePrDocument.submittingOffice'])->firstOrFail();
        return ['report' => $record->only(['id', 'document_reference_number', 'signature_revision', 'invoice_number',
            'delivery_receipt_number', 'inspector_name', 'inspection_chair_name', 'acceptance_officer_name',
            'inspector_designation', 'inspection_chair_designation', 'acceptance_officer_designation',
            'quantity_condition', 'quality_condition', 'findings', 'remarks']),
            'dates' => collect(['report_date', 'invoice_date', 'inspection_date', 'acceptance_date'])
                ->mapWithKeys(fn ($key) => [$key => $record->{$key}?->toDateString()])->all(),
            'purchase_order' => $order->only(['id', 'po_number', 'supplier_name']), 'po_date' => $order->po_date?->toDateString(),
            'office' => $order->sourcePrDocument?->submittingOffice?->name,
            'items' => $order->items->map(fn ($item) => $item->only(['item_no', 'unit', 'description', 'item_description', 'quantity']))->all()];
    }

    public function hash(InspectionAcceptanceRecord $record): string
    {
        return RfqSupplierQuotation::hashSnapshot($this->snapshot($record));
    }

    private function notifyOwner(InspectionAcceptanceRecord $record, string $title, string $message): void
    {
        SystemNotificationService::notify($record->inspectedBy ?: $record->purchaseOrder->preparedBy, $title, $message,
            SystemNotification::TYPE_INFO, 'Inspection / Acceptance', $record, route('head-office.inspection.show', $record->purchase_order_id));
    }
}
