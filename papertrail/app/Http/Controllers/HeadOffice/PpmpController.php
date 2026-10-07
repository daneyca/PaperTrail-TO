<?php

namespace App\Http\Controllers\HeadOffice;

use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use App\Models\DocumentRoutingHistory;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentReferenceNumberService;
use App\Services\DocumentDraftService;
use App\Services\HeadOfficePpmpSignatureWorkflowService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PpmpController extends Controller
{
    private const APP_CONSOLIDATION_HANDLER_USER_ID = 'BACSEC-004';
    private const SIGNATURE_REQUIRED_MESSAGE = 'PPMP requires Head of Office e-signature before submission to BAC Secretariat for APP consolidation.';

    private const PLAN_TYPES = [
        'indicative' => 'Indicative',
        'final' => 'Final',
    ];

    private const PROJECT_TYPES = [
        'Goods',
        'Infrastructure',
        'Consulting Services',
    ];

    private const PROCUREMENT_MODES = [
        'Small Value',
        'Small Value Procurement',
        'Competitive Bidding',
        'Negotiated Procurement',
        'Direct Acquisition',
        'Shopping',
        'Other',
    ];

    private const PRE_PROCUREMENT_OPTIONS = [
        'Yes',
        'No',
        'N/A',
    ];

    private const FUND_SOURCES = [
        'General Fund',
        'Trust Fund',
        'Special Purpose Fund',
        'Other',
    ];

    private const MODIFIABLE_STATUSES = [
        ProcurementDocument::STATUS_PPMP_DRAFT,
        ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
        ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('PPMP Submission', 'PPMP List Viewed', 'Head of Office viewed office PPMP records.');

        $query = $this->officePpmpQuery($request->user())
            ->with(['submittingOffice', 'submittedBy', 'currentOffice']);

        $this->applyFilters($query, $request);

        return view('head-office.ppmp.index', [
            'documents' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary($request->user()),
            'recentDrafts' => app(DocumentDraftService::class)->getPpmpDraftsForUser($request->user(), 5),
            'filters' => $request->only(['search', 'document_type', 'fiscal_year', 'status', 'stage', 'date_from', 'date_to']),
            'documentTypes' => $this->officePpmpQuery($request->user())
                ->select('document_type')
                ->whereNotNull('document_type')
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type'),
            'fiscalYears' => $this->officePpmpQuery($request->user())
                ->select('fiscal_year')
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'statuses' => $this->statuses(),
            'stages' => $this->officePpmpQuery($request->user())
                ->select('stage')
                ->whereNotNull('stage')
                ->distinct()
                ->orderBy('stage')
                ->pluck('stage'),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $office = $this->assignedOffice($request->user());

        if (!$office) {
            return redirect()
                ->route('head-office.dashboard')
                ->with('error', 'Your account does not have an assigned office. Please contact the administrator.');
        }

        AuditLogger::log('PPMP Submission', 'PPMP Create Page Viewed', 'Head of Office opened the Submit PPMP form.');

        return view('head-office.ppmp.create', [
            'document' => new ProcurementDocument([
                'fiscal_year' => now()->year,
                'priority' => 'normal',
                'title' => 'Project Procurement Plan',
                'status' => ProcurementDocument::STATUS_PPMP_DRAFT,
                'ppmp_plan_type' => 'indicative',
                'prepared_by_name' => $request->user()->name,
                'submitted_by_name' => $request->user()->name,
            ]),
            'office' => $office,
            'user' => $request->user(),
            'items' => $this->blankRows(),
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $office = $this->assignedOffice($request->user());

        if (!$office) {
            return redirect()
                ->route('head-office.dashboard')
                ->with('error', 'Your account does not have an assigned office. Please contact the administrator.');
        }

        $submit = $request->input('save_action') === 'submit';
        $validated = $this->validatedPayload($request, $submit);

        $document = DB::transaction(function () use ($request, $validated, $office, $submit) {
            $document = ProcurementDocument::create([
                'tracking_number' => null,
                'ppmp_no' => $validated['ppmp_no'] ?? null,
                'ppmp_plan_type' => $validated['ppmp_plan_type'] ?? 'indicative',
                'document_type' => 'PPMP',
                'title' => $validated['title'] ?? 'Project Procurement Plan',
                'description' => $validated['description'] ?? null,
                'fiscal_year' => $validated['fiscal_year'],
                'submitting_office_id' => $office->id,
                'submitted_by_user_id' => $request->user()->id,
                'prepared_by_user_id' => $request->user()->id,
                'prepared_by_name' => $validated['prepared_by_name'] ?? $request->user()->name,
                'submitted_by_name' => $validated['submitted_by_name'] ?? $request->user()->name,
                'current_office_id' => $office->id,
                'assigned_to_user_id' => $request->user()->id,
                'status' => ProcurementDocument::STATUS_PPMP_DRAFT,
                'stage' => ProcurementDocument::STAGE_PPMP_PREPARATION,
                'priority' => $validated['priority'] ?? 'normal',
                'remarks' => $validated['remarks'] ?? null,
                'total_amount' => 0,
            ]);

            $this->syncItems($document, $validated['items'] ?? []);

            AuditLogger::log('PPMP Submission', 'PPMP Draft Created', 'Head of Office created a PPMP draft.', $document);

            if ($submit) {
                $this->startSignatureWorkflow($document->refresh(), $request->user());
            }

            return $document->refresh();
        });

        return redirect()
            ->route('head-office.ppmp.show', $document)
            ->with('status', $submit
                ? 'PPMP created successfully. Please complete the required e-signature before submitting this document to BAC Secretariat for APP consolidation.'
                : 'PPMP saved as draft.');
    }

    public function show(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to access another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        AuditLogger::log('PPMP Submission', 'PPMP Detail Viewed', 'Head of Office viewed PPMP detail.', $document);

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'currentOffice',
            'assignedTo',
            'ppmpItems',
            'attachments.uploadedBy',
            'routingHistories.actionBy',
            'routingHistories.fromOffice',
            'routingHistories.toOffice',
        ]);

        $signatureWorkflow = app(HeadOfficePpmpSignatureWorkflowService::class);

        return view('head-office.ppmp.show', [
            'document' => $document,
            'canModify' => $this->canModify($document),
            'canStartSignatureWorkflow' => $signatureWorkflow->canStartFor($request->user(), $document),
            'canSubmitToAppConsolidation' => $signatureWorkflow->canSubmitToAppConsolidation($request->user(), $document),
            'ppmpSignatureCards' => $signatureWorkflow->signatureStatusCards($document),
            'ppmpSignatureProgress' => $signatureWorkflow->signatureProgress($document),
            'signatureSlots' => $signatureWorkflow->signedSlots($document),
        ]);
    }

    public function edit(Request $request, ProcurementDocument $document): View|RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to edit another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        if (!$this->canModify($document)) {
            return redirect()
                ->route('head-office.ppmp.show', $document)
                ->with('error', 'Only draft or returned PPMP records can be edited.');
        }

        return view('head-office.ppmp.edit', [
            'document' => $document->load(['ppmpItems']),
            'office' => $this->assignedOffice($request->user()),
            'user' => $request->user(),
            'items' => $this->formRows($document),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to update another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        if (!$this->canModify($document)) {
            return redirect()
                ->route('head-office.ppmp.show', $document)
                ->with('error', 'Only draft or returned PPMP records can be updated.');
        }

        $submit = $request->input('save_action') === 'submit';
        $validated = $this->validatedPayload($request, $submit);

        DB::transaction(function () use ($request, $document, $validated, $submit) {
            $oldValues = $document->only(['title', 'fiscal_year', 'status', 'total_amount']);

            $document->update([
                'ppmp_no' => $validated['ppmp_no'] ?? null,
                'ppmp_plan_type' => $validated['ppmp_plan_type'] ?? 'indicative',
                'title' => $validated['title'] ?? 'Project Procurement Plan',
                'description' => $validated['description'] ?? null,
                'fiscal_year' => $validated['fiscal_year'],
                'priority' => $validated['priority'] ?? 'normal',
                'remarks' => $validated['remarks'] ?? null,
                'prepared_by_user_id' => $request->user()->id,
                'prepared_by_name' => $validated['prepared_by_name'] ?? null,
                'submitted_by_name' => $validated['submitted_by_name'] ?? null,
            ]);

            $this->syncItems($document, $validated['items'] ?? []);

            AuditLogger::log('PPMP Submission', 'PPMP Draft Updated', 'Head of Office updated a PPMP draft.', $document, $oldValues, $document->fresh()->only(['title', 'fiscal_year', 'status', 'total_amount']));

            if ($submit) {
                $this->startSignatureWorkflow($document->refresh(), $request->user());
            }
        });

        return redirect()
            ->route('head-office.ppmp.show', $document)
            ->with('status', $submit
                ? 'PPMP updated successfully. Please complete the required e-signature before submitting this document to BAC Secretariat for APP consolidation.'
                : 'PPMP draft updated.');
    }

    public function destroy(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Delete Attempt', 'Head of Office attempted to delete another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        if (!$this->canModify($document)) {
            return redirect()
                ->route('head-office.ppmp.index')
                ->with('error', 'Only draft or returned PPMP records can be deleted.');
        }

        DB::transaction(function () use ($request, $document) {
            $oldValues = $document->only(['tracking_number', 'ppmp_no', 'title', 'fiscal_year', 'status', 'total_amount']);

            SignatureRequest::query()
                ->forDocument('ppmp', $document->id)
                ->delete();

            SystemNotification::query()
                ->where('related_type', $document::class)
                ->where('related_id', $document->id)
                ->delete();

            $document->attachments()->update([
                'status' => DocumentAttachment::STATUS_DELETED,
                'deleted_by_user_id' => $request->user()->id,
            ]);
            $document->attachments()->delete();
            $document->ppmpItems()->delete();

            AuditLogger::log('PPMP Submission', 'PPMP Deleted', 'Head of Office deleted a PPMP draft/returned record.', $document, $oldValues, null, 'warning');

            $document->delete();
        });

        return redirect()
            ->route('head-office.ppmp.index')
            ->with('status', 'PPMP deleted.');
    }

    public function print(Request $request, ProcurementDocument $document): View
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to print another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        $document->load([
            'submittingOffice',
            'submittedBy',
            'preparedBy',
            'ppmpItems',
        ]);

        $signatureWorkflow = app(HeadOfficePpmpSignatureWorkflowService::class);

        AuditLogger::log('PPMP Submission', 'PPMP Print Viewed', 'Head of Office opened PPMP print view.', $document);

        return view('head-office.ppmp.print', [
            'document' => $document,
            'items' => $this->formRows($document),
            'signatureSlots' => $signatureWorkflow->signedSlots($document),
        ]);
    }

    public function submit(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to submit another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        $signatureWorkflow = app(HeadOfficePpmpSignatureWorkflowService::class);

        if ($signatureWorkflow->canSubmitToAppConsolidation($request->user(), $document)) {
            $target = $this->appConsolidationTarget();

            if (!$target['office'] || !$target['user']) {
                return back()->with('error', 'BACSEC-004 APP consolidation routing target is not configured.');
            }

            DB::transaction(function () use ($request, $document, $target) {
                $this->submitDocument($document->refresh(), $request->user(), $target['office'], $target['user']);
            });

            return redirect()
                ->route('head-office.ppmp.show', $document)
                ->with('status', 'PPMP submitted to BACSEC-004 for APP consolidation.');
        }

        if (!$this->canModify($document)) {
            $message = in_array($document->status, [
                ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
                ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            ], true)
                ? self::SIGNATURE_REQUIRED_MESSAGE
                : 'Only draft, returned, or fully signed PPMP records can be submitted.';

            return back()->with('error', $message);
        }

        $error = $this->existingSubmissionError($document);

        if ($error) {
            return back()->with('error', $error);
        }

        DB::transaction(function () use ($request, $document) {
            $this->startSignatureWorkflow($document->refresh(), $request->user());
        });

        return redirect()
            ->route('head-office.ppmp.show', $document)
            ->with('status', 'PPMP signature request generated. Complete the required e-signature before submitting this document to BAC Secretariat for APP consolidation.');
    }

    public function storeAttachment(Request $request, ProcurementDocument $document): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document)) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to upload to another office PPMP.', $document, null, null, 'warning');
            abort(403);
        }

        if (!$this->canModify($document)) {
            return back()->with('error', 'Attachments can only be uploaded while the PPMP is draft or returned.');
        }

        $validated = $request->validate([
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        foreach ($validated['attachments'] as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $storedFilename = (string) Str::uuid() . ($extension ? '.' . $extension : '');
            $directory = 'document-attachments/ppmp/' . $document->id;
            $path = $file->storeAs($directory, $storedFilename, 'local');
            $ocrReady = $file->getMimeType() === 'application/pdf'
                || str_starts_with((string) $file->getMimeType(), 'image/')
                || in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true);

            $attachment = $document->attachments()->create([
                'attachment_uuid' => (string) Str::uuid(),
                'attachable_type' => $document::class,
                'attachable_id' => $document->id,
                'document_type' => 'ppmp',
                'document_id' => $document->id,
                'tracking_number' => $document->tracking_number,
                'office_id' => $document->submitting_office_id,
                'office_name' => $document->submittingOffice?->name,
                'uploaded_by_user_id' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $storedFilename,
                'file_path' => $path,
                'disk' => 'local',
                'mime_type' => $file->getMimeType(),
                'file_extension' => $extension,
                'size' => $file->getSize(),
                'file_size' => $file->getSize(),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'document_section' => 'ppmp',
                'attachment_category' => DocumentAttachment::CATEGORY_SUPPORTING_DOCUMENT,
                'ocr_status' => $ocrReady ? DocumentAttachment::OCR_PENDING : DocumentAttachment::OCR_NOT_REQUIRED,
                'ai_analysis_status' => DocumentAttachment::AI_PENDING,
                'status' => DocumentAttachment::STATUS_ACTIVE,
            ]);

            AuditLogger::log('PPMP Submission', 'PPMP Attachment Uploaded', 'Head of Office uploaded a PPMP attachment.', $attachment, null, [
                'document_id' => $document->id,
                'file_name' => $attachment->original_name,
            ]);
        }

        return back()->with('status', 'Attachment uploaded.');
    }

    public function destroyAttachment(Request $request, ProcurementDocument $document, DocumentAttachment $attachment): RedirectResponse
    {
        if (!$this->canAccess($request->user(), $document) || $attachment->procurement_document_id !== $document->id) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to delete an attachment outside their scope.', $document, null, null, 'warning');
            abort(403);
        }

        if (!$this->canModify($document)) {
            return back()->with('error', 'Attachments can only be deleted while the PPMP is draft or returned.');
        }

        $sameOffice = $attachment->uploadedBy?->office_id && $attachment->uploadedBy->office_id === $request->user()->office_id;

        if ($attachment->uploaded_by_user_id !== $request->user()->id && !$sameOffice) {
            AuditLogger::log('PPMP Submission', 'Unauthorized PPMP Access Attempt', 'Head of Office attempted to delete an attachment uploaded by another office.', $attachment, null, null, 'warning');
            abort(403);
        }

        $oldValues = $attachment->only(['original_name', 'file_path']);
        $attachment->forceFill([
            'status' => DocumentAttachment::STATUS_DELETED,
            'deleted_by_user_id' => $request->user()->id,
        ])->save();
        $attachment->delete();

        AuditLogger::log('PPMP Submission', 'PPMP Attachment Deleted', 'Head of Office deleted a PPMP attachment.', $document, $oldValues, null, 'warning');

        return back()->with('status', 'Attachment deleted.');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        foreach (['document_type', 'fiscal_year', 'stage', 'created_year', 'created_month'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', function (Builder $builder) use ($request) {
            $status = (string) $request->input('status');
            $groups = [
                'draft' => [ProcurementDocument::STATUS_PPMP_DRAFT],
                'signatures' => [ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES],
                'ready' => [ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED],
                'submitted' => [ProcurementDocument::STATUS_PENDING_PPMP_REVIEW],
                'reviewed' => [ProcurementDocument::STATUS_UNDER_PPMP_REVIEW],
                'returned' => [ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT, ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED],
                'accepted' => [ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION],
            ];

            $builder->whereIn('status', $groups[$status] ?? [$status]);
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('updated_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('updated_at', '<=', $request->date('date_to')));
    }

    private function officePpmpQuery(User $user): Builder
    {
        return ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where('submitting_office_id', $user->office_id);
    }

    private function canAccess(User $user, ProcurementDocument $document): bool
    {
        return $document->document_type === 'PPMP'
            && $document->submitting_office_id !== null
            && $document->submitting_office_id === $user->office_id;
    }

    private function canModify(ProcurementDocument $document): bool
    {
        return in_array($document->status, self::MODIFIABLE_STATUSES, true);
    }

    private function assignedOffice(User $user): ?Office
    {
        return $user->assignedOffice ?: ($user->office_id ? Office::find($user->office_id) : null);
    }

    private function validatedPayload(Request $request, bool $submit): array
    {
        $items = collect($request->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->filter(fn (array $item) => $this->hasItemContent($item))
            ->values()
            ->all();

        $request->merge(['items' => $items]);

        $rules = [
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'ppmp_no' => ['nullable', 'string', 'max:255'],
            'ppmp_plan_type' => ['required', 'in:indicative,final'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:normal,high,urgent'],
            'remarks' => ['nullable', 'string'],
            'prepared_by_name' => ['nullable', 'string', 'max:255'],
            'submitted_by_name' => ['nullable', 'string', 'max:255'],
            'items' => [$submit ? 'required' : 'nullable', 'array'],
            'items.*.item_no' => ['nullable', 'string', 'max:50'],
            'items.*.general_description' => ['nullable', 'string'],
            'items.*.project_type' => ['nullable', 'string', 'max:255'],
            'items.*.quantity_size' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:80'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'items.*.procurement_mode' => ['nullable', 'string', 'max:120'],
            'items.*.recommended_mode' => ['nullable', 'string', 'max:120'],
            'items.*.pre_procurement_conference' => ['nullable', 'string', 'max:255'],
            'items.*.start_procurement_activity' => ['nullable', 'string', 'max:255'],
            'items.*.end_procurement_activity' => ['nullable', 'string', 'max:255'],
            'items.*.expected_delivery_period' => ['nullable', 'string', 'max:255'],
            'items.*.source_of_funds' => ['nullable', 'string', 'max:255'],
            'items.*.supporting_documents' => ['nullable', 'string'],
            'items.*.attached_supporting_documents' => ['nullable', 'string'],
            'items.*.schedule_quarter' => ['nullable', 'string', 'max:80'],
            'items.*.category' => ['nullable', 'string', 'max:120'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.is_bold' => ['nullable', 'boolean'],
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $submit) {
            if (!$submit) {
                return;
            }

            $items = $request->input('items', []);
            $total = 0;
            $budgetRows = 0;

            foreach ($items as $index => $item) {
                $description = trim((string) ($item['general_description'] ?? ''));
                $budget = $this->money($item['estimated_budget'] ?? $item['estimated_total_cost'] ?? 0);
                $isSubtotalRow = $this->isSubtotalRow($item);

                if (! $this->hasItemContent($item)) {
                    continue;
                }

                if ($isSubtotalRow) {
                    continue;
                }

                if ($description === '' && $budget > 0) {
                    $validator->errors()->add("items.$index.general_description", 'Enter a description for this PPMP row.');
                }

                if ($budget > 0) {
                    $budgetRows++;
                    $total += $budget;
                }
            }

            if ($budgetRows === 0) {
                $validator->errors()->add('items', 'At least one PPMP row with a budget amount is required before submission.');
            }

            if ($total <= 0) {
                $validator->errors()->add('items', 'Total PPMP amount must be greater than zero before submission.');
            }
        });

        return $validator->validate();
    }

    private function syncItems(ProcurementDocument $document, array $items): void
    {
        $document->ppmpItems()->delete();
        $total = 0;

        foreach ($items as $index => $item) {
            $description = trim((string) ($item['general_description'] ?? ''));

            if (!$this->hasItemContent($item)) {
                continue;
            }

            $isSubtotalRow = $this->isSubtotalRow($item);
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $budget = $this->money($item['estimated_budget'] ?? $item['estimated_total_cost'] ?? null);
            $unitCost = $this->money($item['estimated_unit_cost'] ?? $budget);
            $itemTotal = $budget > 0 ? $budget : round($quantity * $unitCost, 2);
            if (! $isSubtotalRow) {
                $total += $itemTotal;
            }
            $procurementMode = $item['recommended_mode'] ?? $item['procurement_mode'] ?? null;
            $projectType = $item['project_type'] ?? $item['category'] ?? null;
            $startActivity = $item['start_procurement_activity'] ?? null;
            $endActivity = $item['end_procurement_activity'] ?? null;

            $document->ppmpItems()->create([
                'row_order' => $index,
                'item_no' => $item['item_no'] ?? (string) ($index + 1),
                'general_description' => $description,
                'project_type' => $projectType,
                'quantity_size' => $item['quantity_size'] ?? trim(($item['quantity'] ?? '') . ' ' . ($item['unit'] ?? '')),
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $itemTotal,
                'procurement_mode' => $procurementMode,
                'pre_procurement_conference' => $item['pre_procurement_conference'] ?? null,
                'start_procurement_activity' => $startActivity,
                'end_procurement_activity' => $endActivity,
                'expected_delivery_period' => $item['expected_delivery_period'] ?? null,
                'source_of_funds' => $item['source_of_funds'] ?? null,
                'attached_supporting_documents' => $item['supporting_documents'] ?? $item['attached_supporting_documents'] ?? null,
                'schedule_quarter' => $item['schedule_quarter'] ?? trim(($startActivity ?? '') . ' - ' . ($endActivity ?? ''), ' -'),
                'category' => $projectType,
                'remarks' => $item['remarks'] ?? null,
                'is_bold' => $this->truthy($item['is_bold'] ?? false),
            ]);
        }

        $document->update(['total_amount' => $total]);
    }

    private function existingSubmissionError(ProcurementDocument $document): ?string
    {
        $document->loadMissing('ppmpItems');

        if ($document->ppmpItems->isEmpty()) {
            return 'At least one PPMP item is required before submission.';
        }

        foreach ($document->ppmpItems as $item) {
            $budget = (float) $item->estimated_total_cost;
            $isSubtotalRow = $this->isSubtotalRow([
                'general_description' => $item->general_description,
                'project_type' => $item->project_type,
                'quantity_size' => $item->quantity_size,
                'procurement_mode' => $item->procurement_mode,
                'pre_procurement_conference' => $item->pre_procurement_conference,
                'start_procurement_activity' => $item->start_procurement_activity,
                'end_procurement_activity' => $item->end_procurement_activity,
                'expected_delivery_period' => $item->expected_delivery_period,
                'source_of_funds' => $item->source_of_funds,
                'estimated_budget' => $item->estimated_total_cost,
                'supporting_documents' => $item->attached_supporting_documents,
                'remarks' => $item->remarks,
            ]);
            if ($isSubtotalRow) {
                continue;
            }

            if ($budget > 0 && trim((string) $item->general_description) === '') {
                return 'Enter a description for every PPMP row with a budget amount.';
            }
        }

        if ((float) $document->total_amount <= 0) {
            return 'Total PPMP amount must be greater than zero before submission.';
        }

        return null;
    }

    private function submitDocument(ProcurementDocument $document, User $user, Office $appConsolidationOffice, User $appConsolidationUser): void
    {
        $oldStatus = $document->status;
        $fromOffice = $document->current_office_id;

        if (!$document->document_reference_number) {
            app(DocumentReferenceNumberService::class)->assign($document, DocumentReferenceNumberService::TYPE_PPMP, now());
        }

        if (!$document->ppmp_no || $document->ppmp_no !== $document->tracking_number) {
            $document->ppmp_no = $document->tracking_number;
        }

        $document->fill([
            'status' => ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            'stage' => ProcurementDocument::STAGE_PPMP_SUBMITTED_TO_BAC,
            'submitted_at' => now(),
            'current_office_id' => $appConsolidationOffice->id,
            'assigned_to_user_id' => $appConsolidationUser->id,
            'bac_secretariat_status' => 'pending',
        ])->save();

        $this->recordRouting(
            $document,
            $user,
            'PPMP Submitted to BAC',
            $oldStatus,
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            'Signed PPMP routed to BACSEC-004 for APP consolidation.',
            $fromOffice,
            $appConsolidationOffice->id,
        );

        SystemNotificationService::notify(
            $appConsolidationUser,
            'New PPMP for APP Consolidation',
            "Signed PPMP {$document->tracking_number} was submitted by {$document->submittingOffice?->name} for APP consolidation.",
            SystemNotification::TYPE_INFO,
            'PPMP Submission',
            $document,
            route('bac-secretariat.ppmp.show', $document),
        );

        AuditLogger::log('PPMP Submission', 'PPMP Submitted', 'Head of Office submitted a signed PPMP to BAC Secretariat for APP consolidation.', $document, ['status' => $oldStatus], [
            'status' => ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            'tracking_number' => $document->tracking_number,
            'assigned_to' => self::APP_CONSOLIDATION_HANDLER_USER_ID,
        ]);
    }

    private function startSignatureWorkflow(ProcurementDocument $document, User $user): void
    {
        $requests = app(HeadOfficePpmpSignatureWorkflowService::class)->start($document, $user);

        if ($requests->isEmpty()) {
            throw new \RuntimeException('No PPMP signature requests could be generated for this document.');
        }
    }

    private function generateTrackingNumber(int $year, ?Office $office): string
    {
        $officeCode = $this->trackingOfficeCode($office);
        $prefix = "PPMP-{$year}-{$officeCode}-";

        $lastTrackingNumber = ProcurementDocument::query()
            ->where('document_type', 'PPMP')
            ->where('tracking_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('tracking_number')
            ->value('tracking_number');

        $sequence = $lastTrackingNumber ? ((int) substr($lastTrackingNumber, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function trackingOfficeCode(?Office $office): string
    {
        $source = $office?->code ?: $office?->name ?: 'OFFICE';
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $source));

        return $code !== '' ? $code : 'OFFICE';
    }

    private function appConsolidationTarget(): array
    {
        $user = User::where('status', User::STATUS_ACTIVE)
            ->where('user_id', self::APP_CONSOLIDATION_HANDLER_USER_ID)
            ->first();

        $office = $user?->assignedOffice ?: Office::where('code', 'BACSEC')
            ->orWhere('code', 'BAC')
            ->orWhere('name', 'BAC Secretariat')
            ->orWhere('name', 'like', '%Bids and Awards%')
            ->orWhere('name', 'like', '%BAC%')
            ->first();

        return ['office' => $office, 'user' => $user];
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments, ?int $fromOfficeId, ?int $toOfficeId): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function summary(User $user): array
    {
        $base = $this->officePpmpQuery($user);

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', ProcurementDocument::STATUS_PPMP_DRAFT)->count(),
            'submitted' => (clone $base)->where('status', ProcurementDocument::STATUS_PENDING_PPMP_REVIEW)->count(),
            'reviewed' => (clone $base)->where('status', ProcurementDocument::STATUS_UNDER_PPMP_REVIEW)->count(),
            'returned' => (clone $base)->whereIn('status', [ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT, ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED])->count(),
            'accepted' => (clone $base)->where('status', ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION)->count(),
            'pending' => (clone $base)->whereIn('status', [
                ProcurementDocument::STATUS_PPMP_DRAFT,
                ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
                ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
                ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            ])->count(),
            'budget' => (clone $base)->sum('total_amount'),
        ];
    }

    private function statuses(): array
    {
        return [
            ProcurementDocument::STATUS_PPMP_DRAFT,
            ProcurementDocument::STATUS_PPMP_PENDING_SIGNATORIES,
            ProcurementDocument::STATUS_PPMP_SIGNATORIES_COMPLETED,
            ProcurementDocument::STATUS_PPMP_SIGNATURE_RETURNED,
            ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
            ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
            ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
            ProcurementDocument::STATUS_PPMP_CANCELLED,
        ];
    }

    private function blankRows(int $count = 18): \Illuminate\Support\Collection
    {
        return collect(array_fill(0, $count, $this->blankItem()));
    }

    private function formRows(ProcurementDocument $document, int $minimumRows = 0): \Illuminate\Support\Collection
    {
        $rows = $document->ppmpItems->map(function ($item) {
            $generalDescription = trim((string) $item->general_description);
            $projectType = trim((string) $item->project_type);
            $quantitySize = trim((string) $item->quantity_size);
            $isAutoZeroQuantity = $generalDescription === ''
                && is_numeric($quantitySize)
                && (float) $quantitySize === 0.0;

            if ($isAutoZeroQuantity) {
                $quantitySize = '';
            }

            if ($generalDescription === '' && strcasecmp($projectType, 'Goods') === 0 && $quantitySize === '') {
                $projectType = '';
            }

            return [
                'item_no' => $item->item_no,
                'general_description' => $item->general_description,
                'project_type' => $projectType,
                'quantity_size' => $quantitySize,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'estimated_unit_cost' => $item->estimated_unit_cost,
                'estimated_budget' => (float) $item->estimated_total_cost > 0 ? $item->estimated_total_cost : '',
                'procurement_mode' => $item->procurement_mode,
                'pre_procurement_conference' => $item->pre_procurement_conference,
                'start_procurement_activity' => $item->start_procurement_activity,
                'end_procurement_activity' => $item->end_procurement_activity,
                'expected_delivery_period' => $item->expected_delivery_period,
                'source_of_funds' => $item->source_of_funds,
                'supporting_documents' => $item->attached_supporting_documents,
                'remarks' => $item->remarks,
            ];
        });

        while ($rows->count() < $minimumRows) {
            $rows->push($this->blankItem());
        }

        return $rows;
    }

    private function blankItem(): array
    {
        return [
            'item_no' => '',
            'general_description' => '',
            'project_type' => '',
            'quantity_size' => '',
            'quantity' => '',
            'unit' => '',
            'estimated_unit_cost' => '',
            'estimated_budget' => '',
            'procurement_mode' => '',
            'pre_procurement_conference' => '',
            'start_procurement_activity' => '',
            'end_procurement_activity' => '',
            'expected_delivery_period' => '',
            'source_of_funds' => '',
            'supporting_documents' => '',
            'remarks' => '',
        ];
    }

    private function hasItemContent(array $item): bool
    {
        return collect([
            $item['item_no'] ?? null,
            $item['general_description'] ?? null,
            $item['project_type'] ?? null,
            $item['quantity_size'] ?? null,
            $item['quantity'] ?? null,
            $item['unit'] ?? null,
            $item['estimated_unit_cost'] ?? null,
            $item['estimated_budget'] ?? null,
            $item['procurement_mode'] ?? null,
            $item['recommended_mode'] ?? null,
            $item['pre_procurement_conference'] ?? null,
            $item['start_procurement_activity'] ?? null,
            $item['end_procurement_activity'] ?? null,
            $item['expected_delivery_period'] ?? null,
            $item['source_of_funds'] ?? null,
            $item['supporting_documents'] ?? null,
            $item['attached_supporting_documents'] ?? null,
            $item['remarks'] ?? null,
        ])->contains(fn ($value) => filled($value));
    }

    private function hasOfficialItemColumns(array $item): bool
    {
        return collect([
            $item['project_type'] ?? null,
            $item['quantity_size'] ?? null,
            $item['procurement_mode'] ?? null,
            $item['recommended_mode'] ?? null,
            $item['pre_procurement_conference'] ?? null,
            $item['start_procurement_activity'] ?? null,
            $item['end_procurement_activity'] ?? null,
            $item['expected_delivery_period'] ?? null,
            $item['source_of_funds'] ?? null,
            $item['estimated_budget'] ?? null,
        ])->contains(fn ($value) => filled($value));
    }

    private function isSubtotalRow(array $item): bool
    {
        return collect([
            $item['general_description'] ?? null,
            $item['project_type'] ?? null,
            $item['quantity_size'] ?? null,
            $item['procurement_mode'] ?? null,
            $item['recommended_mode'] ?? null,
            $item['pre_procurement_conference'] ?? null,
            $item['start_procurement_activity'] ?? null,
            $item['end_procurement_activity'] ?? null,
            $item['expected_delivery_period'] ?? null,
            $item['source_of_funds'] ?? null,
            $item['supporting_documents'] ?? null,
            $item['attached_supporting_documents'] ?? null,
            $item['remarks'] ?? null,
        ])->contains(fn ($value) => $this->isSubtotalText($value));
    }

    private function isSubtotalText(mixed $value): bool
    {
        $normalized = trim(preg_replace('/\s+/', ' ', str_replace(':', '', (string) $value)));

        return preg_match('/^(tot\.?|total)(\s+(budget|amount))?$/i', $normalized) === 1;
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function money(mixed $value): float
    {
        return round(max((float) str_replace(',', '', (string) $value), 0), 2);
    }

    private function options(): array
    {
        return [
            'planTypes' => self::PLAN_TYPES,
            'projectTypes' => self::PROJECT_TYPES,
            'procurementModes' => self::PROCUREMENT_MODES,
            'preProcurementOptions' => self::PRE_PROCUREMENT_OPTIONS,
            'fundSources' => self::FUND_SOURCES,
        ];
    }
}
