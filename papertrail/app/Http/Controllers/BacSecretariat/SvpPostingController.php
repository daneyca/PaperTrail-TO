<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use App\Models\ProcurementDocument;
use App\Models\SvpChainEvent;
use App\Models\SvpProcurementChain;
use App\Models\SvpPostingRecord;
use App\Services\AuditLogger;
use App\Services\SvpChainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SvpPostingController extends Controller
{
    private const POSTING_PROCESSOR_USER_ID = 'BACSEC-004';

    public function __construct(private readonly SvpChainService $chains)
    {
    }

    public function pending(Request $request): View
    {
        $this->authorizePostingUser($request);

        $query = $this->pendingPostingQuery()
            ->with(['sourcePrDocument.submittingOffice', 'sourcePrDocument.assignedTo', 'bacResolution', 'latestPostingRecord']);

        $this->applySearch($query, $request);

        AuditLogger::log('SVP Posting', 'Pending Posting Viewed', 'BACSEC-004 viewed pending SVP posting tasks.');

        return view('bac-secretariat.svp-posting.pending', [
            'chains' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request, ?SvpProcurementChain $chain = null): View
    {
        $this->authorizePostingUser($request);

        if ($chain && ! $this->canAccessPendingChain($chain)) {
            abort(403);
        }

        $selectedChain = $chain;

        if (! $selectedChain && $request->filled('chain_id')) {
            $selectedChain = $this->pendingPostingQuery()
                ->with(['sourcePrDocument.submittingOffice', 'bacResolution', 'latestPostingRecord'])
                ->whereKey($request->integer('chain_id'))
                ->first();
        }

        $pendingChains = $this->pendingPostingQuery()
            ->with(['sourcePrDocument.submittingOffice', 'bacResolution', 'latestPostingRecord'])
            ->latest('updated_at')
            ->get();

        AuditLogger::log('SVP Posting', 'Create Posting Viewed', 'BACSEC-004 opened the SVP posting form.', $selectedChain);

        return view('bac-secretariat.svp-posting.create', [
            'chain' => $selectedChain?->loadMissing(['sourcePrDocument.submittingOffice', 'bacResolution', 'latestPostingRecord']),
            'postingRecord' => $selectedChain?->latestPostingRecord,
            'pendingChains' => $pendingChains,
            'statuses' => $this->postingStatuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePostingUser($request);

        $allowedMimes = config('papertrail.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']);
        $maxSize = (int) config('papertrail.attachments.max_size_kb', 10240);

        $validated = $request->validate([
            'svp_procurement_chain_id' => ['required', 'integer'],
            'pr_reference' => ['nullable', 'string', 'max:255'],
            'bac_resolution_reference' => ['nullable', 'string', 'max:255'],
            'procurement_title' => ['nullable', 'string', 'max:255'],
            'requesting_office' => ['nullable', 'string', 'max:255'],
            'procurement_method' => ['nullable', 'string', 'max:100'],
            'posting_platform' => ['required', 'string', 'max:100'],
            'philgeps_reference_number' => ['nullable', 'string', 'max:255'],
            'approved_budget' => ['required', 'numeric', 'min:50000', 'max:200000'],
            'posting_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:posting_date'],
            'status' => ['required', Rule::in(array_keys($this->postingStatuses()))],
            'action' => ['nullable', Rule::in(['save', 'mark_posted'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'posting_notice' => ['nullable', 'file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
            'posting_evidence' => ['nullable', 'file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
            'supporting_documents' => ['nullable', 'array'],
            'supporting_documents.*' => ['file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
        ]);

        $chain = $this->pendingPostingQuery()
            ->with(['sourcePrDocument.submittingOffice', 'bacResolution', 'latestPostingRecord'])
            ->whereKey($validated['svp_procurement_chain_id'])
            ->firstOrFail();

        $sourceDocument = $chain->sourcePrDocument;
        $status = ($validated['action'] ?? null) === 'mark_posted'
            ? SvpPostingRecord::STATUS_POSTED
            : $validated['status'];
        $continuesWorkflow = in_array($status, [SvpPostingRecord::STATUS_POSTED, SvpPostingRecord::STATUS_CLOSED], true);

        $postingRecord = SvpPostingRecord::query()->updateOrCreate(
            ['svp_procurement_chain_id' => $chain->id],
            [
                'source_pr_document_id' => $sourceDocument?->id,
                'created_by_user_id' => $chain->latestPostingRecord?->created_by_user_id ?: $request->user()->id,
                'completed_by_user_id' => $continuesWorkflow ? $request->user()->id : null,
                'pr_reference' => $validated['pr_reference'] ?: ($sourceDocument?->pr_no ?? $sourceDocument?->tracking_number ?? $chain->tracking_number),
                'bac_resolution_reference' => $validated['bac_resolution_reference'] ?: ($chain->bacResolution?->displayNumber() ?? $chain->bacResolution?->resolution_number),
                'procurement_title' => $validated['procurement_title'] ?: ($sourceDocument?->title ?? $sourceDocument?->description ?? 'SVP Posting'),
                'requesting_office' => $validated['requesting_office'] ?: ($sourceDocument?->submittingOffice?->name ?? $chain->office_name),
                'procurement_method' => $validated['procurement_method'] ?: 'SVP',
                'posting_platform' => $validated['posting_platform'],
                'philgeps_reference_number' => $validated['philgeps_reference_number'] ?? null,
                'approved_budget' => $validated['approved_budget'],
                'posting_date' => $validated['posting_date'] ?? null,
                'closing_date' => $validated['closing_date'] ?? null,
                'status' => $status,
                'remarks' => $validated['remarks'] ?? null,
                'posted_at' => in_array($status, [SvpPostingRecord::STATUS_POSTED, SvpPostingRecord::STATUS_CLOSED], true)
                    ? ($chain->latestPostingRecord?->posted_at ?: now())
                    : null,
                'completed_at' => $continuesWorkflow ? now() : null,
            ],
        );

        $this->storePostingAttachments($request, $postingRecord);

        $this->chains->addEvent($chain, [
            'document_type' => 'SVP Posting',
            'document_id' => $postingRecord->id,
            'action' => $continuesWorkflow ? 'Posting marked as posted' : 'Posting record saved',
            'stage' => SvpProcurementChain::STAGE_POSTING,
            'status' => $status,
            'from_role' => 'BAC Secretariat',
            'to_role' => 'BAC Secretariat',
            'from_office_id' => $request->user()->office_id,
            'to_office_id' => $request->user()->office_id,
            'performed_by_user_id' => $request->user()->id,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        if ($continuesWorkflow) {
            $result = $this->chains->completePosting($chain->refresh(), $request->user(), $validated['remarks'] ?? null, $postingRecord);

            return redirect()
                ->route('bac-secretariat.svp-posting.show', $postingRecord)
                ->with($result['ok'] ? 'status' : 'error', $result['message']);
        }

        AuditLogger::log('SVP Posting', 'Posting Record Saved', 'BACSEC-004 saved an SVP posting record.', $postingRecord);

        return redirect()
            ->route('bac-secretariat.svp-posting.show', $postingRecord)
            ->with('status', 'Posting record saved.');
    }

    public function show(Request $request, SvpPostingRecord $postingRecord): View
    {
        $this->authorizePostingUser($request);
        $postingRecord->load(['chain.sourcePrDocument.submittingOffice', 'sourcePrDocument.submittingOffice', 'attachments.uploadedBy', 'createdBy', 'completedBy']);

        AuditLogger::log('SVP Posting', 'Posting Record Viewed', 'BACSEC-004 viewed an SVP posting record.', $postingRecord);

        return view('bac-secretariat.svp-posting.show', [
            'postingRecord' => $postingRecord,
            'chain' => $postingRecord->chain,
            'cards' => $postingRecord->chain ? $this->chains->documentCards($postingRecord->chain, 'bac-secretariat') : [],
            'workflowSteps' => $postingRecord->chain ? $this->chains->workflowSteps($postingRecord->chain) : [],
        ]);
    }

    public function posted(Request $request): View
    {
        $this->authorizePostingUser($request);

        $query = SvpPostingRecord::query()
            ->with(['chain.sourcePrDocument.submittingOffice', 'sourcePrDocument.submittingOffice', 'createdBy', 'completedBy'])
            ->whereIn('status', [SvpPostingRecord::STATUS_POSTED, SvpPostingRecord::STATUS_CLOSED]);

        $this->applyPostingSearch($query, $request);

        AuditLogger::log('SVP Posting', 'Posted Records Viewed', 'BACSEC-004 viewed SVP posted records.');

        return view('bac-secretariat.svp-posting.posted', [
            'postingRecords' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'statuses' => $this->postingStatuses(),
        ]);
    }

    public function history(Request $request): View
    {
        $this->authorizePostingUser($request);

        $query = SvpChainEvent::query()
            ->with(['chain.sourcePrDocument.submittingOffice', 'performedBy', 'fromOffice', 'toOffice'])
            ->where(function (Builder $builder) {
                $builder->where('stage', SvpProcurementChain::STAGE_POSTING)
                    ->orWhere('stage', SvpProcurementChain::STAGE_POSTING_COMPLETED)
                    ->orWhere('action', 'like', '%Posting%');
            });

        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('action', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('chain', fn (Builder $chain) => $chain->where('chain_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('office_name', 'like', "%{$search}%"));
            });
        });

        AuditLogger::log('SVP Posting', 'Posting History Viewed', 'BACSEC-004 viewed SVP posting history.');

        return view('bac-secretariat.svp-posting.history', [
            'events' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    private function pendingPostingQuery(): Builder
    {
        return SvpProcurementChain::query()
            ->where('current_stage', SvpProcurementChain::STAGE_POSTING)
            ->where('current_status', ProcurementDocument::STATUS_SVP_POSTING_REQUIRED)
            ->where('total_amount', '>', SvpChainService::POSTING_MIN_AMOUNT)
            ->where('total_amount', '<', SvpChainService::POSTING_MAX_AMOUNT);
    }

    private function canAccessPendingChain(SvpProcurementChain $chain): bool
    {
        return $this->pendingPostingQuery()->whereKey($chain->id)->exists();
    }

    private function applySearch(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('chain_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('office_name', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', function (Builder $document) use ($search) {
                        $document->where('tracking_number', 'like', "%{$search}%")
                            ->orWhere('pr_no', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        });
    }

    private function applyPostingSearch(Builder $query, Request $request): void
    {
        $query->when($request->filled('status') && $request->input('status') !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('pr_reference', 'like', "%{$search}%")
                    ->orWhere('procurement_title', 'like', "%{$search}%")
                    ->orWhere('requesting_office', 'like', "%{$search}%")
                    ->orWhereHas('chain', fn (Builder $chain) => $chain->where('chain_number', 'like', "%{$search}%"));
            });
        });
    }

    private function authorizePostingUser(Request $request): void
    {
        if ($request->user()?->user_id !== self::POSTING_PROCESSOR_USER_ID) {
            abort(403);
        }
    }

    private function postingStatuses(): array
    {
        return [
            SvpPostingRecord::STATUS_PENDING_POSTING => 'Pending Posting',
            SvpPostingRecord::STATUS_POSTED => 'Posted',
            SvpPostingRecord::STATUS_CLOSED => 'Closed',
        ];
    }

    private function storePostingAttachments(Request $request, SvpPostingRecord $postingRecord): void
    {
        $this->storeSingleAttachment($request, $postingRecord, 'posting_notice', DocumentAttachment::CATEGORY_BAC_DOCUMENT, 'Posting Notice');
        $this->storeSingleAttachment($request, $postingRecord, 'posting_evidence', DocumentAttachment::CATEGORY_OTHER, 'Posting Evidence');

        foreach ($request->file('supporting_documents', []) as $file) {
            $this->createAttachment($request, $postingRecord, $file, DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT, 'Posting Supporting Document');
        }
    }

    private function storeSingleAttachment(Request $request, SvpPostingRecord $postingRecord, string $field, string $category, string $description): void
    {
        if (! $request->hasFile($field)) {
            return;
        }

        $this->createAttachment($request, $postingRecord, $request->file($field), $category, $description);
    }

    private function createAttachment(Request $request, SvpPostingRecord $postingRecord, mixed $file, string $category, string $description): void
    {
        $postingRecord->loadMissing(['sourcePrDocument.submittingOffice']);

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedFilename = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $directory = "document-attachments/svp_posting/{$postingRecord->getKey()}";
        $path = $file->storeAs($directory, $storedFilename, 'local');
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $sourceDocument = $postingRecord->sourcePrDocument;

        DocumentAttachment::create([
            'attachable_type' => $postingRecord::class,
            'attachable_id' => $postingRecord->getKey(),
            'procurement_document_id' => $sourceDocument?->id,
            'document_type' => 'svp_posting',
            'document_id' => $postingRecord->getKey(),
            'tracking_number' => $postingRecord->displayNumber(),
            'office_id' => $sourceDocument?->submitting_office_id,
            'office_name' => $sourceDocument?->submittingOffice?->name ?? $postingRecord->requesting_office,
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
            'description' => $description,
            'document_section' => Str::snake($description),
            'attachment_category' => $category,
            'ocr_status' => $this->ocrStatusFor($mimeType, $extension),
            'ai_analysis_status' => DocumentAttachment::AI_PENDING,
            'status' => DocumentAttachment::STATUS_ACTIVE,
        ]);

        AuditLogger::log('SVP Posting', 'Posting Evidence Uploaded', "{$description} uploaded for SVP posting.", $postingRecord);
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
