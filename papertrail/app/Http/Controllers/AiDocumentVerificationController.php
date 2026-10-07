<?php

namespace App\Http\Controllers;

use App\Models\DocumentAttachment;
use App\Services\AiDocumentVerificationDashboardService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AiDocumentVerificationController extends Controller
{
    public function __construct(private readonly AiDocumentVerificationDashboardService $dashboardService)
    {
    }

    public function index(Request $request): View
    {
        $data = $this->dashboardService->indexData($request->user(), $request->query());

        $this->audit('ai_verification_index_viewed', 'User opened the AI Document Verification repository.');

        return view('ai-document-verification.index', $data);
    }

    public function upload(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->dashboardService->canUpload($user)) {
            $this->audit('unauthorized_ai_verification_upload_attempt', 'User attempted to upload an AI verification file without permission.', 'warning');
            abort(403);
        }

        $allowedMimes = config('papertrail.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']);
        $maxSize = (int) config('papertrail.attachments.max_size_kb', 10240);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
            'attachment_category' => ['nullable', Rule::in($this->categoryKeys())],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $file = $validated['attachment'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedFilename = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $directory = 'ai-document-verification/' . $user->id;
        $path = $file->storeAs($directory, $storedFilename, 'local');
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $category = $validated['attachment_category'] ?? DocumentAttachment::CATEGORY_SCANNED_DOCUMENT;

        $attachment = DocumentAttachment::create([
            'document_type' => 'ai_document_verification',
            'office_id' => $user->office_id,
            'office_name' => $user->assignedOffice?->name ?? $user->office,
            'uploaded_by_user_id' => $user->id,
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

        $this->audit('ai_verification_upload_created', 'User uploaded a scanned file for future AI verification.', 'info', [
            'attachment_id' => $attachment->id,
            'file_name' => $attachment->displayName(),
            'attachment_category' => $attachment->attachment_category,
            'ocr_status' => $attachment->ocr_status,
            'ai_analysis_status' => $attachment->ai_analysis_status,
            'is_confidential' => $attachment->is_confidential,
        ], $attachment);

        return redirect()
            ->route('ai-document-verification.index', ['scope' => $request->input('scope', 'mine')])
            ->with('status', 'Document uploaded for AI verification. Status is Pending AI Review.');
    }

    private function ocrStatusFor(?string $mimeType, string $extension): string
    {
        return $mimeType === 'application/pdf'
            || str_starts_with((string) $mimeType, 'image/')
            || in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)
                ? DocumentAttachment::OCR_PENDING
                : DocumentAttachment::OCR_NOT_REQUIRED;
    }

    private function categoryKeys(): array
    {
        return [
            DocumentAttachment::CATEGORY_SCANNED_DOCUMENT,
            DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT,
            DocumentAttachment::CATEGORY_BAC_DOCUMENT,
            DocumentAttachment::CATEGORY_QUOTATION,
            DocumentAttachment::CATEGORY_ELIGIBILITY_DOCUMENT,
            DocumentAttachment::CATEGORY_SIGNED_DOCUMENT,
            DocumentAttachment::CATEGORY_OTHER,
        ];
    }

    private function audit(
        string $action,
        string $description,
        string $severity = 'info',
        array $metadata = [],
        ?DocumentAttachment $attachment = null,
    ): void {
        try {
            AuditLogger::log('AI Document Verification', $action, $description, $attachment, null, null, $severity, $metadata);
        } catch (Throwable $exception) {
            Log::error('PaperTrail AI verification audit failed.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
