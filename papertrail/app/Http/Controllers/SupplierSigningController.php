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

    public function submit(Request $request, string $token): RedirectResponse
    {
        $supplierRequest = $this->supplierRequest($token);
        $this->expireIfNeeded($supplierRequest);

        if (! $supplierRequest->isOpenForSupplier()) {
            return back()->with('error', 'This signing link is no longer accepting submissions.');
        }

        $validated = $request->validate([
            'signatory_name' => ['required', 'string', 'max:255'],
            'signatory_position' => ['nullable', 'string', 'max:255'],
            'supplier_remarks' => ['nullable', 'string', 'max:2000'],
            'signed_document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'signature_consent' => ['accepted'],
        ]);

        $file = $request->file('signed_document');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedFilename = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
        $path = $file->storeAs('supplier-signatures/signed', $storedFilename, 'local');

        $supplierRequest->update([
            'status' => SupplierSignatureRequest::STATUS_SUBMITTED,
            'signatory_name' => $validated['signatory_name'],
            'signatory_position' => $validated['signatory_position'] ?? $supplierRequest->signatory_position,
            'signed_document_path' => $path,
            'signed_document_name' => $file->getClientOriginalName(),
            'signed_document_mime' => $file->getMimeType(),
            'signed_document_size' => $file->getSize(),
            'signed_document_hash' => hash_file('sha256', $file->getRealPath()),
            'signed_document_disk' => 'local',
            'submitted_at' => now(),
            'signer_ip_address' => $request->ip(),
            'signer_user_agent' => $request->userAgent(),
            'supplier_remarks' => $validated['supplier_remarks'] ?? null,
            'consent_text' => 'Supplier representative confirmed the uploaded signed document is complete and authorized for this procurement record.',
        ]);

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
