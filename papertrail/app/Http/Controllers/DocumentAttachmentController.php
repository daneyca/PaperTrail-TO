<?php

namespace App\Http\Controllers;

use App\Models\DocumentAttachment;
use App\Services\AiDocumentVerificationDashboardService;
use App\Services\AuditLogger;
use App\Services\DocumentResolverService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentAttachmentController extends Controller
{
    private const CATEGORIES = [
        DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT,
        DocumentAttachment::CATEGORY_SCANNED_DOCUMENT,
        DocumentAttachment::CATEGORY_QUOTATION,
        DocumentAttachment::CATEGORY_ELIGIBILITY_DOCUMENT,
        DocumentAttachment::CATEGORY_BAC_DOCUMENT,
        DocumentAttachment::CATEGORY_SIGNED_DOCUMENT,
        DocumentAttachment::CATEGORY_OTHER,
    ];

    public function __construct(
        private readonly DocumentResolverService $resolver,
        private readonly AiDocumentVerificationDashboardService $aiVerificationDashboard,
    )
    {
    }

    public function store(Request $request, string $documentType, int $documentId): RedirectResponse
    {
        $document = $this->resolver->resolve($documentType, $documentId);

        if (! $this->resolver->canUpload($request->user(), $document)) {
            $this->auditDenied($document, 'unauthorized_attachment_upload_attempt', 'User attempted to upload an attachment outside their scope.');
            abort(403);
        }

        $allowedMimes = config('papertrail.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']);
        $maxSize = (int) config('papertrail.attachments.max_size_kb', 10240);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
            'attachment_category' => ['nullable', Rule::in(self::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $file = $validated['attachment'];
        $normalizedType = $this->resolver->documentTypeFor($document, $documentType);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedFilename = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $directory = "document-attachments/{$normalizedType}/{$document->getKey()}";
        $path = $file->storeAs($directory, $storedFilename, 'local');
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $category = $validated['attachment_category'] ?? DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT;

        $attachment = DocumentAttachment::create([
            'attachable_type' => $document::class,
            'attachable_id' => $document->getKey(),
            'procurement_document_id' => $this->resolver->procurementDocumentIdFor($document),
            'document_type' => $normalizedType,
            'document_id' => $document->getKey(),
            'tracking_number' => $this->resolver->displayTrackingNumber($document),
            'office_id' => $this->resolver->officeIdFor($document),
            'office_name' => $this->resolver->officeNameFor($document),
            'uploaded_by_user_id' => $request->user()->id,
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
            'description' => $validated['description'] ?? null,
            'document_section' => $category,
            'attachment_category' => $category,
            'ocr_status' => $this->ocrStatusFor($mimeType, $extension),
            'ai_analysis_status' => DocumentAttachment::AI_PENDING,
            'is_confidential' => (bool) ($validated['is_confidential'] ?? false),
            'status' => DocumentAttachment::STATUS_ACTIVE,
        ]);

        $this->audit($attachment, 'attachment_uploaded', 'User uploaded a supporting document.', [
            'document_type' => $normalizedType,
            'document_id' => $document->getKey(),
            'file_name' => $attachment->displayName(),
            'file_size' => $attachment->file_size,
            'ocr_status' => $attachment->ocr_status,
        ]);

        if ($attachment->isOcrReady()) {
            $this->audit($attachment, 'ocr_ready_attachment_added', 'OCR-ready attachment was added for future AI extraction.', [
                'document_type' => $normalizedType,
                'document_id' => $document->getKey(),
                'file_name' => $attachment->displayName(),
            ]);
        }

        return back()->with('status', 'Supporting document uploaded.');
    }

    public function update(Request $request, DocumentAttachment $attachment): RedirectResponse
    {
        $document = $this->resolver->resolveAttachmentDocument($attachment);

        if (! $this->resolver->canUpload($request->user(), $document)) {
            $this->auditDenied($document, 'unauthorized_attachment_update_attempt', 'User attempted to update attachment metadata outside their scope.');
            abort(403);
        }

        $validated = $request->validate([
            'attachment_category' => ['nullable', Rule::in(self::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $oldValues = $attachment->only(['attachment_category', 'description', 'is_confidential']);

        $attachment->update([
            'attachment_category' => $validated['attachment_category'] ?? $attachment->attachment_category,
            'document_section' => $validated['attachment_category'] ?? $attachment->document_section,
            'description' => $validated['description'] ?? null,
            'is_confidential' => (bool) ($validated['is_confidential'] ?? false),
        ]);

        $this->audit($attachment, 'attachment_updated', 'User updated attachment metadata.', [
            'old_values' => $oldValues,
            'new_values' => $attachment->only(['attachment_category', 'description', 'is_confidential']),
        ]);

        return back()->with('status', 'Attachment details updated.');
    }

    public function view(Request $request, DocumentAttachment $attachment): StreamedResponse
    {
        $document = $this->resolver->resolveAttachmentDocument($attachment);

        if (! $this->canViewAttachment($request, $attachment, $document)) {
            $this->auditDenied($document, 'unauthorized_attachment_access_attempt', 'User attempted to view an attachment outside their scope.');
            abort(403);
        }

        if (! $attachment->isPdf() && ! $attachment->isImage()) {
            return $this->download($request, $attachment);
        }

        $disk = $attachment->readableStorageDisk();

        if (! $attachment->file_path || ! Storage::disk($disk)->exists($attachment->file_path)) {
            abort(404);
        }

        $this->audit($attachment, 'attachment_viewed', 'User viewed an attachment.', [
            'file_name' => $attachment->displayName(),
        ]);

        return Storage::disk($disk)->response($attachment->file_path, $attachment->displayName(), [
            'Content-Disposition' => 'inline; filename="' . addslashes($attachment->displayName()) . '"',
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function download(Request $request, DocumentAttachment $attachment): StreamedResponse
    {
        $document = $this->resolver->resolveAttachmentDocument($attachment);

        if (! $this->canViewAttachment($request, $attachment, $document)) {
            $this->auditDenied($document, 'unauthorized_attachment_access_attempt', 'User attempted to download an attachment outside their scope.');
            abort(403);
        }

        $disk = $attachment->readableStorageDisk();

        if (! $attachment->file_path || ! Storage::disk($disk)->exists($attachment->file_path)) {
            abort(404);
        }

        $this->audit($attachment, 'attachment_downloaded', 'User downloaded an attachment.', [
            'file_name' => $attachment->displayName(),
        ]);

        return Storage::disk($disk)->download($attachment->file_path, $attachment->displayName());
    }

    public function destroy(Request $request, DocumentAttachment $attachment): RedirectResponse
    {
        $document = $this->resolver->resolveAttachmentDocument($attachment);

        if (! $this->resolver->canDeleteAttachment($request->user(), $document, $attachment)) {
            $this->auditDenied($document, 'unauthorized_attachment_delete_attempt', 'User attempted to delete an attachment outside their scope.');
            abort(403);
        }

        $oldValues = $attachment->only(['original_filename', 'original_name', 'file_path', 'attachment_category']);

        $attachment->forceFill([
            'status' => DocumentAttachment::STATUS_DELETED,
            'deleted_by_user_id' => $request->user()->id,
        ])->save();

        $attachment->delete();

        $this->audit($attachment, 'attachment_deleted', 'User deleted an attachment record.', [
            'old_values' => $oldValues,
        ], 'warning');

        return back()->with('status', 'Attachment deleted.');
    }

    private function ocrStatusFor(?string $mimeType, string $extension): string
    {
        return $mimeType === 'application/pdf'
            || str_starts_with((string) $mimeType, 'image/')
            || in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)
                ? DocumentAttachment::OCR_PENDING
                : DocumentAttachment::OCR_NOT_REQUIRED;
    }

    private function canViewAttachment(Request $request, DocumentAttachment $attachment, ?Model $document): bool
    {
        if ($document) {
            return $this->resolver->canView($request->user(), $document);
        }

        return $this->aiVerificationDashboard->canViewAttachment($request->user(), $attachment);
    }

    private function audit(DocumentAttachment $attachment, string $action, string $description, array $metadata = [], string $severity = 'info'): void
    {
        try {
            AuditLogger::log('Document Attachments', $action, $description, $attachment, null, null, $severity, $metadata);
        } catch (Throwable $exception) {
            Log::error('PaperTrail attachment audit failed.', [
                'action' => $action,
                'attachment_id' => $attachment->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function auditDenied(?Model $document, string $action, string $description): void
    {
        try {
            AuditLogger::log('Document Attachments', $action, $description, $document, null, null, 'warning');
        } catch (Throwable $exception) {
            Log::error('PaperTrail attachment denial audit failed.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
