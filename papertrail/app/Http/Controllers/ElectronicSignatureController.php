<?php

namespace App\Http\Controllers;

use App\Models\ElectronicSignature;
use App\Services\AuditLogger;
use App\Services\ElectronicSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ElectronicSignatureController extends Controller
{
    public function __construct(private readonly ElectronicSignatureService $signatures)
    {
    }

    public function sendCode(Request $request, string $documentType, int|string $documentId): RedirectResponse
    {
        $document = $this->documentOrFail($documentType, $documentId);
        $result = $this->signatures->sendSigningCode(
            $request->user(),
            $document,
            $documentType,
            $request->input('signature_action', 'confirmed'),
        );

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function sign(Request $request, string $documentType, int|string $documentId): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'signing_code' => ['required', 'digits:6'],
            'signature_consent' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'signature_action' => ['nullable', 'string', 'max:50'],
        ]);

        $document = $this->documentOrFail($documentType, $documentId);
        $result = $this->signatures->signDocument(
            $document,
            $request->user(),
            $documentType,
            $validated['signature_action'] ?? 'confirmed',
            [
                ...$validated,
                'consent_text' => 'I confirm that I have reviewed this document and I approve/sign it electronically in PaperTrail.',
            ],
            $request,
        );

        if (! $result['ok']) {
            return back()
                ->with('error', $result['message'])
                ->withInput($request->except(['current_password', 'signing_code']));
        }

        return back()->with('status', $result['message']);
    }

    public function decline(Request $request, string $documentType, int|string $documentId): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'signature_action' => ['nullable', 'string', 'max:50'],
        ]);

        $document = $this->documentOrFail($documentType, $documentId);
        $result = $this->signatures->declineSignature(
            $document,
            $request->user(),
            $documentType,
            $validated['signature_action'] ?? 'confirmed',
            $validated['reason'],
            $request,
        );

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function certificate(Request $request, ElectronicSignature $signature): View
    {
        if (! $this->signatures->canViewSignature($request->user(), $signature)) {
            AuditLogger::signature('unauthorized_signature_attempt', $signature, [
                'description' => 'Unauthorized signature certificate access attempt.',
                'severity' => 'warning',
                'metadata' => ['signature_id' => $signature->signature_code],
            ]);

            abort(403);
        }

        $signature->loadMissing(['signer.assignedOffice', 'signer.assignedRole', 'snapshot']);

        AuditLogger::signature('signature_certificate_viewed', $signature, [
            'description' => 'Signature certificate viewed.',
            'metadata' => ['signature_id' => $signature->signature_code],
        ]);

        return view('e-signatures.certificate', [
            'signature' => $signature,
        ]);
    }

    public function image(Request $request, ElectronicSignature $signature): BinaryFileResponse
    {
        if (! $this->signatures->canViewSignature($request->user(), $signature)) {
            abort(403);
        }

        if (! $signature->signature_image_path || ! Storage::disk('local')->exists($signature->signature_image_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($signature->signature_image_path), [
            'Content-Type' => $this->contentTypeFor($signature->signature_image_path),
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function documentOrFail(string $documentType, int|string $documentId)
    {
        $document = $this->signatures->findDocument($documentType, $documentId);

        abort_if(! $document, 404);

        return $document;
    }

    private function contentTypeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
    }
}
