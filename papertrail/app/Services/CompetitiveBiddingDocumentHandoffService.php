<?php

namespace App\Services;

use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\DocumentAttachment;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompetitiveBiddingDocumentHandoffService
{
    public const SOURCE_MANUAL_UPLOAD = 'manual_upload';

    private const SIGNATURE_REQUIREMENT_MAP = [
        'pre_bid_conference_minutes' => 'pre_bid_conference_minutes',
        'bid_evaluation_report' => 'bid_evaluation_report',
        'post_qualification_evaluation_summary' => 'post_qualification_summary',
        'post_qualification_report_twg' => 'twg_post_qualification_report',
        'notice_of_post_qualification' => 'notice_post_qualification',
        'bac_resolution_on_post_qualification' => 'bac_resolution_post_qualification',
        'notice_of_award' => 'notice_of_award',
        'contract_agreement' => 'contract_agreement',
        'notice_to_proceed' => 'notice_to_proceed',
    ];

    public function submitAttachment(
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item,
        UploadedFile $file,
        User $submittedBy
    ): DocumentAttachment {
        return DB::transaction(function () use ($record, $item, $file, $submittedBy) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $storedFilename = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
            $directory = "document-attachments/competitive-bidding-supporting/{$record->id}/{$item->id}";
            $path = $file->storeAs($directory, $storedFilename, 'local');
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $attachment = DocumentAttachment::create([
                'attachable_type' => $item::class,
                'attachable_id' => $item->id,
                'document_type' => 'competitive_bidding_supporting_item',
                'document_id' => $item->id,
                'tracking_number' => $record->reference_number,
                'office_id' => $submittedBy->office_id,
                'office_name' => $submittedBy->assignedOffice?->name ?? $submittedBy->office,
                'uploaded_by_user_id' => $submittedBy->id,
                'original_name' => $file->getClientOriginalName(),
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $storedFilename,
                'file_path' => $path,
                'disk' => 'local',
                'mime_type' => $mimeType,
                'file_extension' => $extension,
                'size' => $size,
                'file_size' => $size,
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'description' => $item->requirement_name,
                'document_section' => 'competitive_bidding_supporting_document',
                'attachment_category' => DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT,
                'ocr_status' => $this->ocrStatusFor($mimeType, $extension),
                'ai_analysis_status' => DocumentAttachment::AI_PENDING,
                'is_required' => $item->isEffectivelyRequired(),
                'status' => DocumentAttachment::STATUS_ACTIVE,
            ]);

            $item->update([
                'status' => CompetitiveBiddingSupportingItem::STATUS_ATTACHED,
                'remarks' => null,
                'submitted_by_user_id' => $submittedBy->id,
                'submitted_at' => now(),
                'source_type' => self::SOURCE_MANUAL_UPLOAD,
                'source_id' => $attachment->id,
                'source_reference' => $record->reference_number,
                'source_status' => 'submitted',
                'source_version' => ((int) $item->source_version) + 1,
                'checked_by_user_id' => null,
                'checked_at' => null,
            ]);

            return $attachment;
        });
    }

    public function syncSignatureDocument(CompetitiveBiddingSignatureDocument $document): ?CompetitiveBiddingSupportingItem
    {
        $requirementKey = self::SIGNATURE_REQUIREMENT_MAP[$document->template_key] ?? null;

        if (! $requirementKey || blank($document->tracking_number)) {
            return null;
        }

        $record = CompetitiveBiddingRecord::query()
            ->where('reference_number', trim((string) $document->tracking_number))
            ->first();

        if (! $record) {
            return null;
        }

        $item = $record->projectItems()
            ->where('requirement_key', $requirementKey)
            ->first();

        if (! $item) {
            return null;
        }

        if ($document->status === CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED) {
            $isNewVersion = $item->source_type !== $document::class
                || (int) $item->source_id !== (int) $document->id
                || $item->source_status !== CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED;

            if (! $isNewVersion) {
                return $item;
            }

            $item->update([
                'status' => CompetitiveBiddingSupportingItem::STATUS_ATTACHED,
                'remarks' => null,
                'submitted_by_user_id' => $document->prepared_by_user_id,
                'submitted_at' => now(),
                'source_type' => $document::class,
                'source_id' => $document->id,
                'source_reference' => $document->displayNumber(),
                'source_status' => $document->status,
                'source_version' => (int) $item->source_version + 1,
                'checked_by_user_id' => null,
                'checked_at' => null,
            ]);

            $this->notifyBacsec002(
                $record,
                $item,
                'Signed document added for checking',
                "{$document->document_title} was completed and added to {$record->reference_number}."
            );
        } elseif ($document->status === CompetitiveBiddingSignatureDocument::STATUS_RETURNED
            && $item->source_type === $document::class
            && (int) $item->source_id === (int) $document->id) {
            $item->update([
                'status' => CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION,
                'remarks' => 'The linked document was returned during signature routing.',
                'source_status' => $document->status,
                'checked_by_user_id' => null,
                'checked_at' => null,
            ]);
        }

        return $item->fresh();
    }

    public function syncExistingSignatureDocuments(CompetitiveBiddingRecord $record): void
    {
        CompetitiveBiddingSignatureDocument::query()
            ->where('tracking_number', $record->reference_number)
            ->where('status', CompetitiveBiddingSignatureDocument::STATUS_FULLY_SIGNED)
            ->whereIn('template_key', array_keys(self::SIGNATURE_REQUIREMENT_MAP))
            ->each(fn (CompetitiveBiddingSignatureDocument $document) => $this->syncSignatureDocument($document));
    }

    public function notifyBacsec002(
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item,
        string $title,
        string $message
    ): void {
        $user = User::query()
            ->where('user_id', 'BACSEC-001')
            ->where('status', User::STATUS_ACTIVE)
            ->first();

        if (! $user) {
            return;
        }

        SystemNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => SystemNotification::TYPE_INFO,
            'module' => 'Competitive Bidding Supporting Documents',
            'related_type' => $item::class,
            'related_id' => $item->id,
            'action_url' => route('bac-secretariat.competitive-bidding.supporting-documents.show', $record),
        ]);
    }

    public function notifySubmitterOfReview(
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item
    ): void {
        $submitter = $item->submittedBy;

        if (! $submitter || $submitter->user_id === 'BACSEC-001') {
            return;
        }

        $type = $item->status === CompetitiveBiddingSupportingItem::STATUS_VERIFIED
            ? SystemNotification::TYPE_SUCCESS
            : SystemNotification::TYPE_WARNING;

        SystemNotification::create([
            'user_id' => $submitter->id,
            'title' => $item->status === CompetitiveBiddingSupportingItem::STATUS_VERIFIED
                ? 'Supporting document verified'
                : 'Supporting document needs attention',
            'message' => "{$item->requirement_name} for {$record->reference_number} is {$item->statusLabel()}.",
            'type' => $type,
            'module' => 'Competitive Bidding Supporting Documents',
            'related_type' => $item::class,
            'related_id' => $item->id,
            'action_url' => route('bac-secretariat.competitive-bidding.supporting-submissions.show', $record),
        ]);
    }

    private function ocrStatusFor(?string $mimeType, string $extension): string
    {
        return $mimeType === 'application/pdf'
            || str_starts_with((string) $mimeType, 'image/')
            || in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)
                ? DocumentAttachment::OCR_PENDING
                : DocumentAttachment::OCR_NOT_REQUIRED;
    }
}
