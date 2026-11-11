<?php

namespace App\Http\Controllers;

use App\Models\SupplierSignatureRequest;
use App\Models\PurchaseOrder;
use App\Models\RfqSupplierQuotation;
use App\Services\AuditLogger;
use App\Services\PurchaseOrderSignatureWorkflowService;
use App\Services\PurchaseOrderSupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierSigningController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $supplierRequest = $this->supplierRequest($token);
        $this->expireIfNeeded($supplierRequest);

        if ($supplierRequest->status === SupplierSignatureRequest::STATUS_PENDING) {
            $supplierRequest->update([
                'status' => SupplierSignatureRequest::STATUS_VIEWED,
                'viewed_at' => now(),
            ]);

            AuditLogger::signature('supplier_signature_request_viewed', $supplierRequest, [
                'description' => 'Supplier opened an external signing link.',
                'metadata' => [
                    'ip_address' => $request->ip(),
                ],
            ]);
        }

        return view('supplier-signing.show', [
            'supplierRequest' => $supplierRequest->fresh(['requestedBy']),
        ]);
    }

    public function submit(
        Request $request,
        string $token,
        PurchaseOrderSignatureWorkflowService $purchaseOrderWorkflow
    ): RedirectResponse
    {
        $supplierRequest = $this->supplierRequest($token);
        $this->expireIfNeeded($supplierRequest);

        if (! $supplierRequest->isOpenForSupplier()) {
            return back()->with('error', 'This signing link is no longer accepting submissions.');
        }

        if ($supplierRequest->document_type === 'purchase_order' && data_get($supplierRequest->metadata, 'purchase_order_snapshot')) {
            $order = PurchaseOrder::find(data_get($supplierRequest->metadata, 'purchase_order_id'));
            abort_unless($order && in_array($order->status, [PurchaseOrder::STATUS_FORWARDED_TO_SUPPLIER, PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_FUND_CERTIFIED], true), 409, 'This PO is no longer accepting signatures.');
            abort_unless(hash_equals(
                RfqSupplierQuotation::hashSnapshot(data_get($supplierRequest->metadata, 'purchase_order_snapshot')),
                RfqSupplierQuotation::hashSnapshot(app(PurchaseOrderSupplierService::class)->snapshot($order))
            ), 409, 'This PO has changed. Please request a new signing link.');
        }

        $validated = $request->validate([
            'signatory_name' => ['required', 'string', 'max:255'],
            'signatory_position' => [$supplierRequest->document_type === 'purchase_order' ? 'required' : 'nullable', 'string', 'max:255'],
            'supplier_remarks' => ['nullable', 'string', 'max:2000'],
            'signature_data' => ['nullable', 'string', 'required_without:signed_document'],
            'signed_document' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png', 'required_without:signature_data'],
            'signature_consent' => ['accepted'],
        ]);

        $file = $request->file('signed_document');
        $signatureData = (string) ($validated['signature_data'] ?? '');

        if ($signatureData !== '') {
            abort_unless(str_starts_with($signatureData, 'data:image/png;base64,'), 422, 'The drawn signature format is invalid.');
            $binary = base64_decode(substr($signatureData, strlen('data:image/png;base64,')), true);
            abort_if($binary === false || strlen($binary) > 2 * 1024 * 1024, 422, 'The drawn signature is invalid or too large.');

            $storedFilename = (string) Str::uuid().'.png';
            $path = 'supplier-signatures/signed/'.$storedFilename;
            Storage::disk('local')->put($path, $binary);
            $originalName = 'supplier-conforme-signature.png';
            $mimeType = 'image/png';
            $size = strlen($binary);
            $hash = hash('sha256', $binary);
        } else {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $storedFilename = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
            $path = $file->storeAs('supplier-signatures/signed', $storedFilename, 'local');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();
            $hash = hash_file('sha256', $file->getRealPath());
        }

        $supplierRequest->update([
            'status' => SupplierSignatureRequest::STATUS_SUBMITTED,
            'signatory_name' => $validated['signatory_name'],
            'signatory_position' => $validated['signatory_position'] ?? $supplierRequest->signatory_position,
            'signed_document_path' => $path,
            'signed_document_name' => $originalName,
            'signed_document_mime' => $mimeType,
            'signed_document_size' => $size,
            'signed_document_hash' => $hash,
            'signed_document_disk' => 'local',
            'submitted_at' => now(),
            'signer_ip_address' => $request->ip(),
            'signer_user_agent' => $request->userAgent(),
            'supplier_remarks' => $validated['supplier_remarks'] ?? null,
            'consent_text' => $supplierRequest->document_type === 'purchase_order'
                ? 'I am authorized to sign for the supplier and accept this Purchase Order, its items, prices, delivery requirements, and terms.'
                : 'Supplier representative confirmed the signed document is complete and authorized for this procurement record.',
            'metadata' => [
                ...($supplierRequest->metadata ?: []),
                'supplier_designation' => $validated['signatory_position'] ?? 'Authorized Supplier Representative',
                'signature_method' => $signatureData !== '' ? 'drawn_signature' : 'uploaded_signed_document',
            ],
        ]);

        if ($supplierRequest->document_type === 'purchase_order') {
            $purchaseOrderWorkflow->handleSupplierSigned($supplierRequest->fresh());
        }

        AuditLogger::signature('supplier_signed_document_submitted', $supplierRequest, [
            'description' => 'Supplier uploaded a signed document through the external signing portal.',
            'metadata' => [
                'signed_document_hash' => $supplierRequest->signed_document_hash,
                'supplier_name' => $supplierRequest->supplier_name,
            ],
        ]);

        return redirect()
            ->route('supplier-signing.show', $supplierRequest->public_token)
            ->with('status', 'Signed document submitted. BAC Secretariat will validate the file.');
    }

    public function downloadSource(string $token): StreamedResponse
    {
        $supplierRequest = $this->supplierRequest($token);

        abort_unless($supplierRequest->hasSourceDocument(), 404);
        abort_if($supplierRequest->status === SupplierSignatureRequest::STATUS_CANCELLED, 403);

        $disk = $supplierRequest->source_document_disk ?: 'local';

        abort_if(! Storage::disk($disk)->exists($supplierRequest->source_document_path), 404);

        return Storage::disk($disk)->download($supplierRequest->source_document_path, $supplierRequest->sourceDocumentName());
    }

    public function signedDocument(string $token): StreamedResponse
    {
        $supplierRequest = $this->supplierRequest($token);
        abort_unless($supplierRequest->hasSignedDocument(), 404);

        $disk = $supplierRequest->signed_document_disk ?: 'local';
        abort_if(! Storage::disk($disk)->exists($supplierRequest->signed_document_path), 404);

        return Storage::disk($disk)->response(
            $supplierRequest->signed_document_path,
            $supplierRequest->signedDocumentName(),
            ['Content-Type' => $supplierRequest->signed_document_mime ?: 'application/octet-stream']
        );
    }

    private function supplierRequest(string $token): SupplierSignatureRequest
    {
        return SupplierSignatureRequest::query()
            ->where('public_token', $token)
            ->firstOrFail();
    }

    private function expireIfNeeded(SupplierSignatureRequest $supplierRequest): void
    {
        if (in_array($supplierRequest->status, SupplierSignatureRequest::ACTIVE_STATUSES, true) && $supplierRequest->isExpired()) {
            $supplierRequest->update(['status' => SupplierSignatureRequest::STATUS_EXPIRED]);
        }
    }
}
