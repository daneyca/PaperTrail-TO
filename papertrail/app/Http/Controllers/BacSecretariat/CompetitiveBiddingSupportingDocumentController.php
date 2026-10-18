<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\CompetitiveBiddingSupplier;
use App\Models\CompetitiveBiddingSupportingItem;
use App\Models\DocumentAttachment;
use App\Services\AuditLogger;
use App\Services\CompetitiveBiddingDocumentHandoffService;
use App\Services\CompetitiveBiddingSupportingDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitiveBiddingSupportingDocumentController extends Controller
{
    private const BACSEC_002_USER_ID = 'BACSEC-001';
    private const BACSEC_004_USER_ID = 'BACSEC-003';

    public function __construct(
        private readonly CompetitiveBiddingSupportingDocumentService $supportingDocuments,
        private readonly CompetitiveBiddingDocumentHandoffService $handoffs,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeUser($request);

        $search = trim($request->string('search')->toString());
        $records = CompetitiveBiddingRecord::query()
            ->withCount('suppliers')
            ->withCount([
                'supportingItems as supporting_items_count',
                'supportingItems as verified_items_count' => fn ($query) => $query->where('status', CompetitiveBiddingSupportingItem::STATUS_VERIFIED),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('project_title', 'like', "%{$search}%")
                        ->orWhereHas('suppliers', fn ($supplier) => $supplier->where('supplier_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('bac-secretariat.competitive-bidding.supporting-documents.index', [
            'records' => $records,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUser($request);

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:120', 'unique:competitive_bidding_records,reference_number'],
            'project_title' => ['required', 'string', 'max:255'],
            'approved_budget' => ['nullable', 'numeric', 'min:0'],
            'bid_opening_date' => ['nullable', 'date'],
        ]);

        $record = $this->supportingDocuments->createRecord(array_merge($validated, [
            'approved_budget' => $validated['approved_budget'] ?? null,
            'bid_opening_date' => $validated['bid_opening_date'] ?? null,
            'status' => CompetitiveBiddingRecord::STATUS_OPEN,
            'created_by_user_id' => $request->user()->id,
        ]));

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supporting File Created',
            'BACSEC-001 created a competitive bidding supporting-document file.',
            $record
        );

        return redirect()
            ->route('bac-secretariat.competitive-bidding.supporting-documents.show', $record)
            ->with('status', 'Bidding supporting-document file created. Add every participating supplier next.');
    }

    public function show(Request $request, CompetitiveBiddingRecord $record): View
    {
        $this->authorizeUser($request);
        $this->handoffs->syncExistingSignatureDocuments($record);

        $record->load([
            'supportingItems.attachments.uploadedBy',
            'supportingItems.checkedBy',
            'supportingItems.submittedBy',
            'supportingItems.supplier',
            'suppliers.supportingItems.attachments.uploadedBy',
            'suppliers.supportingItems.checkedBy',
            'suppliers.supportingItems.submittedBy',
            'suppliers.supportingItems.supplier',
        ]);

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supporting File Viewed',
            'BACSEC-001 viewed a competitive bidding supporting-document file.',
            $record
        );

        return view('bac-secretariat.competitive-bidding.supporting-documents.show', [
            'record' => $record,
            'projectSections' => $record->supportingItems
                ->whereNull('competitive_bidding_supplier_id')
                ->groupBy('section'),
            'summary' => $this->supportingDocuments->recordSummary($record),
            'itemStatuses' => CompetitiveBiddingSupportingItem::statusOptions(),
            'supplierStatuses' => CompetitiveBiddingSupplier::statusOptions(),
        ]);
    }

    public function storeSupplier(
        Request $request,
        CompetitiveBiddingRecord $record
    ): RedirectResponse {
        $this->authorizeUser($request);

        $validated = $request->validate([
            'supplier_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('competitive_bidding_suppliers', 'supplier_name')
                    ->where(fn ($query) => $query->where('competitive_bidding_record_id', $record->id)),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $supplier = $this->supportingDocuments->addSupplier($record, array_merge($validated, [
            'review_status' => CompetitiveBiddingSupplier::STATUS_UNDER_REVIEW,
            'created_by_user_id' => $request->user()->id,
        ]));

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supplier File Added',
            'BACSEC-001 added a supplier file to the competitive bidding record.',
            $supplier,
            null,
            ['supplier_name' => $supplier->supplier_name]
        );

        return back()->with('status', "Supplier file added for {$supplier->supplier_name}.");
    }

    public function updateSupplier(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupplier $supplier
    ): RedirectResponse {
        $this->authorizeUser($request);
        $this->ensureSupplierBelongsToRecord($record, $supplier);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in(array_keys(CompetitiveBiddingSupplier::statusOptions()))],
            'decision_remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $oldValues = $supplier->only(['review_status', 'decision_remarks']);

        $supplier->update($validated);

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supplier Review Updated',
            'BACSEC-001 updated the supplier review decision.',
            $supplier,
            $oldValues,
            $supplier->only(['review_status', 'decision_remarks'])
        );

        return back()->with('status', "Review status updated for {$supplier->supplier_name}.");
    }

    public function updateItem(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item
    ): RedirectResponse {
        $this->authorizeUser($request);
        $this->ensureItemBelongsToRecord($record, $item);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(CompetitiveBiddingSupportingItem::statusOptions()))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === CompetitiveBiddingSupportingItem::STATUS_VERIFIED
            && ! $item->hasSubmittedEvidence()) {
            throw ValidationException::withMessages([
                'status' => 'Attach or link a completed system document before marking this requirement as verified.',
            ]);
        }

        if (in_array($validated['status'], [
            CompetitiveBiddingSupportingItem::STATUS_MISSING,
            CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION,
            CompetitiveBiddingSupportingItem::STATUS_NOT_APPLICABLE,
        ], true) && blank($validated['remarks'] ?? null)) {
            throw ValidationException::withMessages([
                'remarks' => 'Enter a remark explaining this document status.',
            ]);
        }

        $oldValues = $item->only(['status', 'remarks', 'checked_by_user_id', 'checked_at']);
        $reviewed = $validated['status'] !== CompetitiveBiddingSupportingItem::STATUS_PENDING;

        $item->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'checked_by_user_id' => $reviewed ? $request->user()->id : null,
            'checked_at' => $reviewed ? now() : null,
        ]);

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supporting Requirement Reviewed',
            "BACSEC-001 reviewed guide item {$item->guide_number}.",
            $item,
            $oldValues,
            $item->only(['status', 'remarks', 'checked_by_user_id', 'checked_at']),
            in_array($item->status, [
                CompetitiveBiddingSupportingItem::STATUS_MISSING,
                CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION,
            ], true) ? 'warning' : 'info'
        );

        if (in_array($item->status, [
            CompetitiveBiddingSupportingItem::STATUS_VERIFIED,
            CompetitiveBiddingSupportingItem::STATUS_FOR_CORRECTION,
            CompetitiveBiddingSupportingItem::STATUS_MISSING,
        ], true)) {
            $this->handoffs->notifySubmitterOfReview($record, $item->fresh('submittedBy'));
        }

        return back()->with('status', "Guide item {$item->guide_number} updated.");
    }

    public function storeAttachment(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item
    ): RedirectResponse {
        $this->authorizeUser($request);
        $this->ensureItemBelongsToRecord($record, $item);

        $allowedMimes = config('papertrail.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']);
        $maxSize = (int) config('papertrail.attachments.max_size_kb', 10240);
        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
        ]);

        $attachment = $this->handoffs->submitAttachment(
            $record,
            $item,
            $validated['attachment'],
            $request->user()
        );

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supporting Document Attached',
            "BACSEC-001 attached a file to guide item {$item->guide_number}.",
            $attachment,
            null,
            null,
            'info',
            ['record_id' => $record->id, 'item_id' => $item->id, 'file_name' => $attachment->displayName()]
        );

        return back()->with('status', "File attached to guide item {$item->guide_number}. Review it before marking the item verified.");
    }

    public function viewAttachment(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item,
        DocumentAttachment $attachment
    ): StreamedResponse {
        $this->authorizeAttachment($request, $record, $item, $attachment);

        if (! $attachment->isPdf() && ! $attachment->isImage()) {
            return $this->downloadAttachment($request, $record, $item, $attachment);
        }

        $disk = $attachment->readableStorageDisk();
        abort_unless($attachment->file_path && Storage::disk($disk)->exists($attachment->file_path), 404);

        return Storage::disk($disk)->response($attachment->file_path, $attachment->displayName(), [
            'Content-Disposition' => 'inline; filename="' . addslashes($attachment->displayName()) . '"',
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function downloadAttachment(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item,
        DocumentAttachment $attachment
    ): StreamedResponse {
        $this->authorizeAttachment($request, $record, $item, $attachment);

        $disk = $attachment->readableStorageDisk();
        abort_unless($attachment->file_path && Storage::disk($disk)->exists($attachment->file_path), 404);

        return Storage::disk($disk)->download($attachment->file_path, $attachment->displayName());
    }

    public function destroyAttachment(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item,
        DocumentAttachment $attachment
    ): RedirectResponse {
        $this->authorizeUser($request);
        $this->authorizeAttachment($request, $record, $item, $attachment);

        $attachment->forceFill([
            'status' => DocumentAttachment::STATUS_DELETED,
            'deleted_by_user_id' => $request->user()->id,
        ])->save();
        $attachment->delete();

        if (! $item->attachments()->exists()) {
            $item->update([
                'status' => $item->hasSystemDocument()
                    ? CompetitiveBiddingSupportingItem::STATUS_ATTACHED
                    : CompetitiveBiddingSupportingItem::STATUS_PENDING,
                'submitted_by_user_id' => $item->hasSystemDocument() ? $item->submitted_by_user_id : null,
                'submitted_at' => $item->hasSystemDocument() ? $item->submitted_at : null,
                'source_type' => $item->hasSystemDocument() ? $item->source_type : null,
                'source_id' => $item->hasSystemDocument() ? $item->source_id : null,
                'source_reference' => $item->hasSystemDocument() ? $item->source_reference : null,
                'source_status' => $item->hasSystemDocument() ? $item->source_status : null,
                'checked_by_user_id' => null,
                'checked_at' => null,
            ]);
        }

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Supporting Attachment Removed',
            "BACSEC-001 removed an attachment from guide item {$item->guide_number}.",
            $item,
            null,
            null,
            'warning'
        );

        return back()->with('status', 'Supporting attachment removed.');
    }

    public function sourceIndex(Request $request): View
    {
        $this->authorizeSourceUser($request);

        $records = CompetitiveBiddingRecord::query()
            ->withCount([
                'projectItems as submission_items_count' => fn ($query) => $query->where('document_source', self::BACSEC_004_USER_ID),
                'projectItems as submitted_items_count' => fn ($query) => $query
                    ->where('document_source', self::BACSEC_004_USER_ID)
                    ->whereIn('status', [
                        CompetitiveBiddingSupportingItem::STATUS_ATTACHED,
                        CompetitiveBiddingSupportingItem::STATUS_VERIFIED,
                    ]),
            ])
            ->latest()
            ->paginate(12);

        return view('bac-secretariat.competitive-bidding.supporting-documents.source-index', compact('records'));
    }

    public function sourceShow(Request $request, CompetitiveBiddingRecord $record): View
    {
        $this->authorizeSourceUser($request);

        $items = $record->projectItems()
            ->where('document_source', self::BACSEC_004_USER_ID)
            ->with(['attachments.uploadedBy', 'checkedBy', 'submittedBy'])
            ->get();

        return view('bac-secretariat.competitive-bidding.supporting-documents.source-show', [
            'record' => $record,
            'sections' => $items->groupBy('section'),
            'submittedCount' => $items->whereIn('status', [
                CompetitiveBiddingSupportingItem::STATUS_ATTACHED,
                CompetitiveBiddingSupportingItem::STATUS_VERIFIED,
            ])->count(),
            'itemCount' => $items->count(),
        ]);
    }

    public function storeSourceAttachment(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item
    ): RedirectResponse {
        $this->authorizeSourceUser($request);
        $this->ensureItemBelongsToRecord($record, $item);
        abort_unless($this->isBacsec004ProjectItem($item), 403);

        $allowedMimes = config('papertrail.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']);
        $maxSize = (int) config('papertrail.attachments.max_size_kb', 10240);
        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:' . $maxSize, 'mimes:' . implode(',', $allowedMimes)],
        ]);

        $attachment = $this->handoffs->submitAttachment(
            $record,
            $item,
            $validated['attachment'],
            $request->user()
        );

        $this->handoffs->notifyBacsec002(
            $record,
            $item,
            'BACSEC-003 submitted a bidding document',
            "{$item->requirement_name} was submitted for {$record->reference_number}."
        );

        AuditLogger::log(
            'Competitive Bidding Supporting Documents',
            'Document Submitted to BACSEC-001',
            "BACSEC-003 submitted guide item {$item->guide_number} to BACSEC-001.",
            $attachment,
            null,
            null,
            'info',
            ['record_id' => $record->id, 'item_id' => $item->id, 'version' => $item->fresh()->source_version]
        );

        return back()->with('status', "{$item->requirement_name} submitted to BACSEC-001 for checking.");
    }

    public function viewSystemDocument(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item
    ): Response {
        $this->authorizeUser($request);
        $this->ensureItemBelongsToRecord($record, $item);
        abort_unless($item->hasSystemDocument(), 404);

        $document = CompetitiveBiddingSignatureDocument::findOrFail($item->source_id);
        $title = e($document->document_title);

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . "<title>{$title}</title></head><body>{$document->document_html}</body></html>"
        );
    }

    private function authorizeUser(Request $request): void
    {
        abort_unless($request->user()?->user_id === self::BACSEC_002_USER_ID, 403);
    }

    private function authorizeSourceUser(Request $request): void
    {
        abort_unless($request->user()?->user_id === self::BACSEC_004_USER_ID, 403);
    }

    private function ensureSupplierBelongsToRecord(
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupplier $supplier
    ): void {
        abort_unless($supplier->competitive_bidding_record_id === $record->id, 404);
    }

    private function ensureItemBelongsToRecord(
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item
    ): void {
        abort_unless($item->competitive_bidding_record_id === $record->id, 404);
    }

    private function authorizeAttachment(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSupportingItem $item,
        DocumentAttachment $attachment
    ): void {
        $this->ensureItemBelongsToRecord($record, $item);
        $canView = $request->user()?->user_id === self::BACSEC_002_USER_ID
            || ($request->user()?->user_id === self::BACSEC_004_USER_ID && $this->isBacsec004ProjectItem($item));
        abort_unless($canView, 403);
        abort_unless(
            $attachment->attachable_type === $item::class
            && (int) $attachment->attachable_id === $item->id
            && $attachment->status === DocumentAttachment::STATUS_ACTIVE,
            404
        );
    }

    private function isBacsec004ProjectItem(CompetitiveBiddingSupportingItem $item): bool
    {
        return $item->competitive_bidding_supplier_id === null
            && $item->document_source === self::BACSEC_004_USER_ID;
    }
}
