<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplateImport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Templates\OfficialEditableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentTemplateImportController extends Controller
{
    public function uploadForm(Request $request, string $documentType)
    {
        $type = app(DocumentTemplateController::class)->normalizeDocumentType($documentType);
        abort_unless($type && $this->canUpload($request->user(), $type), 403);

        $recentImports = Schema::hasTable('document_template_imports')
            ? DocumentTemplateImport::query()
                ->where('document_type', $type)
                ->where('uploaded_by', $request->user()?->id)
                ->latest()
                ->limit(8)
                ->get()
            : collect();

        return view('document-template-imports.upload', [
            'type' => $type,
            'typeMeta' => DocumentTemplateController::TYPES[$type],
            'recentImports' => $recentImports,
        ]);
    }

    public function upload(Request $request, string $documentType): RedirectResponse
    {
        $templateController = app(DocumentTemplateController::class);
        $type = $templateController->normalizeDocumentType($documentType);
        abort_unless($type && $this->canUpload($request->user(), $type), 403);

        if (! Schema::hasTable('document_template_imports')) {
            return back()->with('error', 'Template import table is not ready. Please run php artisan migrate.');
        }

        $validated = $request->validate([
            'filled_template' => ['required', 'file', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $request->file('filled_template');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! $templateController->isAllowedExtension($type, $extension)) {
            throw ValidationException::withMessages([
                'filled_template' => $templateController->extensionMessage($type),
            ]);
        }

        // TODO: Add antivirus scanning before production deployment.
        $routeType = app(OfficialEditableTemplateService::class)->routeKey($type);
        $filename = $routeType . '_' . ($request->user()?->id ?? 'guest') . '_' . now()->format('YmdHis')
            . '.' . $extension;

        $path = $file->storeAs("template-imports/{$routeType}", $filename, 'local');

        $import = DocumentTemplateImport::create([
            'document_type' => $type,
            'uploaded_by' => $request->user()?->id,
            'office_id' => $request->user()?->office_id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $filename,
            'file_path' => $path,
            'file_format' => $extension,
            'file_size' => $file->getSize(),
            'import_status' => DocumentTemplateImport::STATUS_MANUAL_REVIEW,
            'validation_errors' => [
                'parser' => 'No XLSX/DOCX parser is configured yet. The uploaded file was stored safely for manual review.',
            ],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->audit('Filled Template Uploaded', 'User uploaded a filled editable template for manual review.', $import, [
            'document_type' => $type,
            'file_format' => $extension,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return redirect()
            ->route('document-template-imports.upload-form', ['documentType' => str_replace('_', '-', $type)])
            ->with('status', 'Template uploaded successfully. Manual review or import mapping is required before creating a draft.');
    }

    private function canUpload(?User $user, string $documentType): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE)) {
            return in_array($documentType, ['ppmp', 'pr', 'rfq', 'abstract', 'purchase_order', 'inspection_acceptance'], true);
        }

        if ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT)) {
            return in_array($documentType, ['ppmp', 'app', 'supplemental_app', 'rfq', 'abstract', 'bac_resolution', 'purchase_order'], true);
        }

        return false;
    }

    private function audit(string $action, string $description, DocumentTemplateImport $import, array $metadata = []): void
    {
        try {
            AuditLogger::log('Document Template Imports', $action, $description, $import, null, null, 'info', $metadata);
        } catch (Throwable $exception) {
            Log::warning('PaperTrail template import audit failed.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
