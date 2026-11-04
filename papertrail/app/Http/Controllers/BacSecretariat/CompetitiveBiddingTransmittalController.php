<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Services\AuditLogger;
use App\Services\CompetitiveBiddingDocumentHandoffService;
use App\Services\CompetitiveBiddingTransmittalService;
use App\Services\SignatureRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompetitiveBiddingTransmittalController extends Controller
{
    public function create(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingTransmittalService $transmittal,
        CompetitiveBiddingDocumentHandoffService $handoffs,
    ): View {
        $this->authorizePreparer($request);
        $handoffs->syncExistingSignatureDocuments($record);

        return view('bac-secretariat.competitive-bidding.transmittal.create', [
            'record' => $record,
            'checklist' => $transmittal->checklist($record),
            'mayors' => $transmittal->mayorCandidates(),
            'letters' => $this->letters($record),
        ]);
    }

    public function store(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingTransmittalService $transmittal,
        CompetitiveBiddingDocumentHandoffService $handoffs,
        SignatureRequestService $signatureRequests,
    ): RedirectResponse {
        $this->authorizePreparer($request);
        $handoffs->syncExistingSignatureDocuments($record);

        $validated = $request->validate([
            'letter_date' => ['required', 'date'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_title' => ['required', 'string', 'max:150'],
            'recipient_office' => ['required', 'string', 'max:250'],
            'recipient_address' => ['nullable', 'string', 'max:250'],
            'salutation' => ['required', 'string', 'max:150'],
            'project_title' => ['required', 'string', 'max:255'],
            'opening' => ['required', 'string', 'max:1200'],
            'closing' => ['required', 'string', 'max:500'],
            'additional_attachments' => ['nullable', 'string', 'max:8000'],
            'confirm_additional' => ['sometimes', 'accepted'],
            'confirm_packet' => ['accepted'],
            'mayor_user_id' => ['required', 'integer'],
        ]);

        $mayor = $transmittal->mayorCandidates()->firstWhere('id', (int) $validated['mayor_user_id']);
        if (! $mayor) {
            throw ValidationException::withMessages(['mayor_user_id' => 'Select an active Municipal Mayor / HOPE account.']);
        }

        $ready = $transmittal->checklist($record)->where('ready', true)->values();
        $additional = collect(preg_split('/\R/u', $validated['additional_attachments'] ?? '') ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($additional->count() > 50 || $additional->contains(fn (string $line) => mb_strlen($line) > 220)) {
            throw ValidationException::withMessages(['additional_attachments' => 'Enter at most 50 additional attachments, each under 220 characters.']);
        }
        if ($additional->isNotEmpty() && empty($validated['confirm_additional'])) {
            throw ValidationException::withMessages(['confirm_additional' => 'Confirm that the additional attachments are physically in the packet.']);
        }
        if ($ready->isEmpty() && $additional->isEmpty()) {
            throw ValidationException::withMessages(['confirm_packet' => 'Verify or complete an attachment before preparing the cover letter.']);
        }

        $data = [
            ...$validated,
            'reference_number' => $record->reference_number,
            'mayor_name' => $mayor->name,
            'attachments' => $ready->map(fn (array $entry) => [
                'name' => $entry['name'],
                'supplier_name' => $entry['supplier_name'],
            ])->concat($additional->map(fn (string $name) => ['name' => $name, 'supplier_name' => null]))->all(),
        ];

        [$document, $signatureRequest] = DB::transaction(function () use ($data, $record, $mayor, $request) {
            $document = CompetitiveBiddingSignatureDocument::create([
                'template_key' => CompetitiveBiddingTransmittalService::TEMPLATE_KEY,
                'document_title' => 'COA Transmittal - '.$record->reference_number,
                'tracking_number' => $record->reference_number,
                'document_html' => view('bac-secretariat.competitive-bidding.transmittal.document', compact('data'))->render(),
                'signatories' => [[
                    'slot' => 'mayor',
                    'label' => 'Municipal Mayor / HOPE',
                    'name' => $mayor->name,
                    'recipient_user_id' => $mayor->id,
                    'recipient_account' => $mayor->user_id,
                ]],
                'status' => CompetitiveBiddingSignatureDocument::STATUS_PENDING_SIGNATURES,
                'prepared_by_user_id' => $request->user()->id,
            ]);

            $signatureRequest = SignatureRequest::create([
                'request_uuid' => (string) Str::uuid(),
                'document_type' => 'competitive_bidding_document',
                'document_id' => $document->id,
                'document_label' => $document->document_title,
                'tracking_number' => $record->reference_number,
                'signatory_slot' => 'mayor',
                'signatory_label' => 'Municipal Mayor / HOPE',
                'signing_order' => 1,
                'signing_mode' => 'parallel',
                'is_required' => true,
                'requested_by_user_id' => $request->user()->id,
                'requested_to_user_id' => $mayor->id,
                'requested_to_role' => $mayor->role,
                'requested_to_office_id' => $mayor->office_id,
                'status' => SignatureRequest::STATUS_PENDING,
                'metadata' => [
                    'printed_name' => $mayor->name,
                    'display_role_label' => 'Municipal Mayor / Head of the Procuring Entity',
                    'template_key' => CompetitiveBiddingTransmittalService::TEMPLATE_KEY,
                ],
            ]);

            AuditLogger::signature('coa_transmittal_submitted', $document, [
                'description' => 'BACSEC-001 sent the COA attachment cover letter to the selected mayor account.',
                'metadata' => ['record_id' => $record->id, 'attachment_count' => count($data['attachments'])],
            ]);

            return [$document, $signatureRequest];
        });

        $signatureRequests->notifySigner($signatureRequest);

        return redirect()
            ->route('bac-secretariat.competitive-bidding.transmittal.show', [$record, $document])
            ->with('status', 'Cover letter sent to the Municipal Mayor / HOPE for signature.');
    }

    public function show(
        Request $request,
        CompetitiveBiddingRecord $record,
        CompetitiveBiddingSignatureDocument $document,
        SignatureRequestService $signatureRequests,
    ): View {
        $this->authorizePreparer($request);
        abort_unless($document->template_key === CompetitiveBiddingTransmittalService::TEMPLATE_KEY
            && $document->tracking_number === $record->reference_number, 404);

        return view('bac-secretariat.competitive-bidding.transmittal.show', [
            'record' => $record,
            'document' => $document,
            'signatureRequest' => $document->signatureRequests()->with('requestedTo')->first(),
            'signatureSlots' => $signatureRequests->signedSignaturesForDocument($document, 'competitive_bidding_document'),
        ]);
    }

    private function letters(CompetitiveBiddingRecord $record)
    {
        return CompetitiveBiddingSignatureDocument::query()
            ->where('template_key', CompetitiveBiddingTransmittalService::TEMPLATE_KEY)
            ->where('tracking_number', $record->reference_number)
            ->latest()
            ->get(['id', 'document_title', 'status', 'created_at']);
    }

    private function authorizePreparer(Request $request): void
    {
        abort_unless($request->user()?->user_id === 'BACSEC-001', 403);
    }
}
