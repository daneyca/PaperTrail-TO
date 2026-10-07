<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Templates\OfficialEditableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentTemplateController extends Controller
{
    public const TYPES = [
        'ppmp' => ['label' => 'PPMP', 'format' => 'xlsx'],
        'app' => ['label' => 'APP', 'format' => 'xlsx'],
        'supplemental_app' => ['label' => 'Supplemental APP', 'format' => 'xlsx'],
        'pr' => ['label' => 'Purchase Request', 'format' => 'xlsx'],
        'rfq' => ['label' => 'RFQ', 'format' => 'xlsx'],
        'abstract' => ['label' => 'Abstract of Quotations', 'format' => 'xlsx'],
        'purchase_order' => ['label' => 'Purchase Order', 'format' => 'xlsx'],
        'bac_resolution' => ['label' => 'BAC Resolution', 'format' => 'docx'],
        'received_bac_resolution' => ['label' => 'Received BAC Resolution', 'format' => 'docx'],
        'inspection_acceptance' => ['label' => 'Inspection / Acceptance', 'format' => 'xlsx'],
    ];

    private const MACRO_EXTENSIONS = ['xlsm', 'xltm', 'docm', 'dotm', 'xls', 'doc'];

    public function download(Request $request, string $documentType, OfficialEditableTemplateService $templates): StreamedResponse|BinaryFileResponse|RedirectResponse
    {
        $type = $this->normalizeDocumentType($documentType);
        abort_unless($type && $this->canDownload($request->user(), $type), 403);

        if (! $templates->exists($type)) {
            return back()->with('error', 'Editable template file is missing.');
        }

        $this->audit('Official Editable Template Downloaded', 'User downloaded the stored official editable template.', null, [
            'document_type' => $type,
            'file_path' => $templates->path($type),
        ]);

        return Storage::disk('local')->download(
            $templates->path($type),
            $templates->downloadFilename($type, (int) $request->input('fiscal_year')),
        );
    }

    public function index(Request $request)
    {
        $templates = Schema::hasTable('document_templates')
            ? DocumentTemplate::query()
                ->with('uploader')
                ->orderBy('document_type')
                ->latest()
                ->paginate(12)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 12);

        $this->audit('Template Management Viewed', 'Admin viewed document template management.');

        return view('admin.document-templates.index', [
            'templates' => $templates,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('document_templates')) {
            return back()->with('error', 'Document template table is not ready. Please run php artisan migrate.');
        }

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'template_name' => ['required', 'string', 'max:255'],
            'template_file' => ['required', 'file', 'max:10240'],
            'version' => ['nullable', 'string', 'max:60'],
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = $validated['document_type'];
        $file = $request->file('template_file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! $this->isAllowedExtension($type, $extension)) {
            return back()
                ->withInput()
                ->withErrors(['template_file' => $this->extensionMessage($type)]);
        }

        $templates = app(OfficialEditableTemplateService::class);
        $makeActive = (bool) ($validated['is_active'] ?? true);

        if ($makeActive) {
            DocumentTemplate::query()
                ->where('document_type', $type)
                ->update(['is_active' => false]);
        }

        if (! Storage::disk('local')->exists($templates->directory($type))) {
            Storage::disk('local')->makeDirectory($templates->directory($type));
        }

        if ($makeActive) {
            $path = $templates->path($type);
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            $template = DocumentTemplate::updateOrCreate(
                [
                    'document_type' => $type,
                    'file_path' => $path,
                ],
                [
                    'template_name' => $validated['template_name'],
                    'file_format' => $extension,
                    'version' => $validated['version'] ?? null,
                    'fiscal_year' => $validated['fiscal_year'] ?? null,
                    'is_active' => true,
                    'uploaded_by' => $request->user()?->id,
                    'notes' => $validated['notes'] ?? null,
                ],
            );
        } else {
            $filename = now()->format('YmdHis') . '-' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                . '.' . $extension;

            $path = $file->storeAs($templates->directory($type), $filename, 'local');

            $template = DocumentTemplate::create([
                'document_type' => $type,
                'template_name' => $validated['template_name'],
                'file_path' => $path,
                'file_format' => $extension,
                'version' => $validated['version'] ?? null,
                'fiscal_year' => $validated['fiscal_year'] ?? null,
                'is_active' => false,
                'uploaded_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        $this->audit('Master Template Uploaded', 'Admin uploaded an editable master template.', $template, [
            'document_type' => $type,
            'file_format' => $extension,
        ]);

        return back()->with('status', 'Editable template uploaded.');
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        if (! Schema::hasTable('document_templates')) {
            return back()->with('error', 'Document template table is not ready. Please run php artisan migrate.');
        }

        $validated = $request->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:60'],
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $active = (bool) ($validated['is_active'] ?? false);

        if ($active) {
            DocumentTemplate::query()
                ->where('document_type', $documentTemplate->document_type)
                ->whereKeyNot($documentTemplate->id)
                ->update(['is_active' => false]);
        }

        $documentTemplate->update([
            'template_name' => $validated['template_name'],
            'version' => $validated['version'] ?? null,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $active,
        ]);

        $this->audit('Master Template Updated', 'Admin updated editable template metadata.', $documentTemplate);

        return back()->with('status', 'Template details updated.');
    }

    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        if (! Schema::hasTable('document_templates')) {
            return back()->with('error', 'Document template table is not ready. Please run php artisan migrate.');
        }

        if ($documentTemplate->fileExists()) {
            Storage::disk('local')->delete($documentTemplate->file_path);
        }

        $this->audit('Master Template Deleted', 'Admin deleted an editable template record.', $documentTemplate, [
            'document_type' => $documentTemplate->document_type,
        ], 'warning');

        $documentTemplate->delete();

        return back()->with('status', 'Template deleted.');
    }

    public function normalizeDocumentType(string $documentType): ?string
    {
        $normalized = app(OfficialEditableTemplateService::class)->canonicalType($documentType);

        return $normalized && array_key_exists($normalized, self::TYPES) ? $normalized : null;
    }

    public function expectedFormat(string $documentType): string
    {
        return self::TYPES[$documentType]['format'];
    }

    public function isAllowedExtension(string $documentType, string $extension): bool
    {
        return ! in_array($extension, self::MACRO_EXTENSIONS, true)
            && $extension === $this->expectedFormat($documentType);
    }

    public function extensionMessage(string $documentType): string
    {
        $label = self::TYPES[$documentType]['label'];
        $format = strtoupper($this->expectedFormat($documentType));

        return "{$label} templates must be uploaded as {$format}. Macro-enabled or legacy formats are not allowed.";
    }

    private function canDownload(?User $user, string $documentType): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE)) {
            return in_array($documentType, ['ppmp', 'pr', 'rfq', 'abstract', 'purchase_order', 'inspection_acceptance'], true);
        }

        if ($user->hasRole('bac_secretariat') || $user->hasRole(User::ROLE_BAC_SECRETARIAT)) {
            return in_array($documentType, ['ppmp', 'app', 'supplemental_app', 'rfq', 'abstract', 'bac_resolution', 'received_bac_resolution', 'purchase_order'], true);
        }

        return false;
    }

    private function audit(string $action, string $description, ?DocumentTemplate $template = null, array $metadata = [], string $severity = 'info'): void
    {
        try {
            AuditLogger::log('Document Templates', $action, $description, $template, null, null, $severity, $metadata);
        } catch (Throwable $exception) {
            Log::warning('PaperTrail document template audit failed.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
