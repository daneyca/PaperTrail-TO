<?php

namespace App\Http\Controllers;

use App\Models\SupplierSignatureRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierSignatureRequestController extends Controller
{
    private const DOCUMENT_TYPES = [
        'notice_of_award' => 'Notice of Award',
        'contract_agreement' => 'Contract Agreement',
        'notice_to_proceed' => 'Notice to Proceed',
        'purchase_order' => 'Purchase Order',
        'other' => 'Other Supplier Document',
    ];

    public function index(Request $request): View
    {
        $query = SupplierSignatureRequest::query()
            ->with(['requestedBy', 'acceptedBy'])
            ->search($request->input('search'));

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->latest('created_at')->paginate(12)->withQueryString();

        return view('bac-secretariat.supplier-signatures.index', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status']),
            'summary' => [
                'total' => SupplierSignatureRequest::query()->count(),
                'pending' => SupplierSignatureRequest::query()->whereIn('status', [SupplierSignatureRequest::STATUS_PENDING, SupplierSignatureRequest::STATUS_VIEWED])->count(),
                'submitted' => SupplierSignatureRequest::query()->where('status', SupplierSignatureRequest::STATUS_SUBMITTED)->count(),
                'accepted' => SupplierSignatureRequest::query()->where('status', SupplierSignatureRequest::STATUS_ACCEPTED)->count(),
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        return view('bac-secretariat.supplier-signatures.create', [
            'documentTypes' => self::DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:80'],
            'document_title' => ['required', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_email' => ['nullable', 'email', 'max:255'],
            'supplier_contact' => ['nullable', 'string', 'max:80'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_position' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'source_document' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        unset($validated['source_document']);

        $supplierRequest = new SupplierSignatureRequest([
            ...$validated,
            'requested_by_user_id' => $request->user()->id,
            'requested_at' => now(),
            'status' => SupplierSignatureRequest::STATUS_PENDING,
        ]);

        if ($request->hasFile('source_document')) {
            $this->storeSourceDocument($supplierRequest, $request->file('source_document'));
        }

        $supplierRequest->save();

        AuditLogger::signature('supplier_signature_request_created', $supplierRequest, [
            'description' => 'BAC created an external supplier signature request.',
            'metadata' => [
                'supplier_name' => $supplierRequest->supplier_name,
                'document_type' => $supplierRequest->document_type,
                'tracking_number' => $supplierRequest->tracking_number,
            ],
        ]);

        return redirect()
            ->route('supplier-signatures.show', $supplierRequest)
            ->with('status', 'Supplier signing link created.');
    }

    public function show(SupplierSignatureRequest $supplierSignatureRequest): View
    {
        $supplierSignatureRequest->loadMissing(['requestedBy', 'acceptedBy']);

        return view('bac-secretariat.supplier-signatures.show', [
            'supplierRequest' => $supplierSignatureRequest,
        ]);
    }

    public function accept(Request $request, SupplierSignatureRequest $supplierSignatureRequest): RedirectResponse
    {
        abort_if(! $supplierSignatureRequest->hasSignedDocument(), 422);

        $validated = $request->validate([
            'bac_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplierSignatureRequest->update([
            'status' => SupplierSignatureRequest::STATUS_ACCEPTED,
            'accepted_by_user_id' => $request->user()->id,
            'accepted_at' => now(),
            'bac_remarks' => $validated['bac_remarks'] ?? $supplierSignatureRequest->bac_remarks,
        ]);

        AuditLogger::signature('supplier_signature_request_accepted', $supplierSignatureRequest, [
            'description' => 'BAC accepted the supplier-signed document.',
        ]);

        return back()->with('status', 'Supplier-signed document accepted.');
    }

    public function reject(Request $request, SupplierSignatureRequest $supplierSignatureRequest): RedirectResponse
    {
        $validated = $request->validate([
            'bac_remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $supplierSignatureRequest->update([
            'status' => SupplierSignatureRequest::STATUS_REJECTED,
            'rejected_at' => now(),
            'bac_remarks' => $validated['bac_remarks'],
        ]);

        AuditLogger::signature('supplier_signature_request_rejected', $supplierSignatureRequest, [
            'description' => 'BAC returned the supplier signature request for correction.',
            'severity' => 'warning',
        ]);

        return back()->with('status', 'Supplier request returned for correction.');
    }

    public function cancel(Request $request, SupplierSignatureRequest $supplierSignatureRequest): RedirectResponse
    {
        $validated = $request->validate([
            'bac_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplierSignatureRequest->update([
            'status' => SupplierSignatureRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'bac_remarks' => $validated['bac_remarks'] ?? $supplierSignatureRequest->bac_remarks,
        ]);

        AuditLogger::signature('supplier_signature_request_cancelled', $supplierSignatureRequest, [
            'description' => 'BAC cancelled an external supplier signature request.',
            'severity' => 'warning',
        ]);

        return back()->with('status', 'Supplier signing request cancelled.');
    }

    public function downloadSource(SupplierSignatureRequest $supplierSignatureRequest): StreamedResponse
    {
        abort_unless($supplierSignatureRequest->hasSourceDocument(), 404);

        return $this->download(
            $supplierSignatureRequest->source_document_disk,
            $supplierSignatureRequest->source_document_path,
            $supplierSignatureRequest->sourceDocumentName(),
        );
    }

    public function downloadSigned(SupplierSignatureRequest $supplierSignatureRequest): StreamedResponse
    {
        abort_unless($supplierSignatureRequest->hasSignedDocument(), 404);

        return $this->download(
            $supplierSignatureRequest->signed_document_disk,
            $supplierSignatureRequest->signed_document_path,
            $supplierSignatureRequest->signedDocumentName(),
        );
    }

    private function storeSourceDocument(SupplierSignatureRequest $supplierRequest, $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedFilename = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
        $path = $file->storeAs('supplier-signatures/source', $storedFilename, 'local');

        $supplierRequest->fill([
            'source_document_path' => $path,
            'source_document_name' => $file->getClientOriginalName(),
            'source_document_mime' => $file->getMimeType(),
            'source_document_size' => $file->getSize(),
            'source_document_hash' => hash_file('sha256', $file->getRealPath()),
            'source_document_disk' => 'local',
        ]);
    }

    private function download(?string $disk, ?string $path, string $name): StreamedResponse
    {
        $disk = $disk ?: 'local';

        abort_if(! $path || ! Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $name);
    }

    private function statuses(): array
    {
        return [
            SupplierSignatureRequest::STATUS_PENDING => 'Pending',
            SupplierSignatureRequest::STATUS_VIEWED => 'Viewed',
            SupplierSignatureRequest::STATUS_SUBMITTED => 'Submitted',
            SupplierSignatureRequest::STATUS_ACCEPTED => 'Accepted',
            SupplierSignatureRequest::STATUS_REJECTED => 'Returned',
            SupplierSignatureRequest::STATUS_CANCELLED => 'Cancelled',
            SupplierSignatureRequest::STATUS_EXPIRED => 'Expired',
        ];
    }
}
