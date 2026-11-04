<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Services\AnnualInvestmentProgramService;
use App\Services\AuditLogger;
use App\Services\SignatureRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnnualInvestmentProgramSubmissionController extends Controller
{
    public function store(Request $request, AnnualInvestmentProgramService $aip, SignatureRequestService $signatureRequests): RedirectResponse
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003', 403);

        $data = $aip->validateDocument($request);
        $signers = $aip->resolveSigners();

        foreach ($signers as $slot => $user) {
            $data[$slot.'_name'] = trim($data[$slot.'_name'] ?? '') ?: $user->name;
            $data[$slot.'_title'] = trim($data[$slot.'_title'] ?? '');
        }

        $document = DB::transaction(function () use ($request, $data, $signers) {
            $signatories = [];

            foreach (AnnualInvestmentProgramService::SLOTS as $slot => $label) {
                $signatories[] = [
                    'slot' => $slot,
                    'label' => $label,
                    'name' => $data[$slot.'_name'],
                    'title' => $data[$slot.'_title'],
                    'recipient_user_id' => $signers[$slot]->id,
                    'recipient_account' => $signers[$slot]->user_id,
                ];
            }

            $document = CompetitiveBiddingSignatureDocument::create([
                'template_key' => 'annual_investment_program',
                'document_title' => 'Annual Investment Program CY '.$data['year'],
                'document_html' => view('bac-secretariat.competitive-bidding.partials.aip-document', compact('data'))->render(),
                'signatories' => $signatories,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
                'status' => CompetitiveBiddingSignatureDocument::STATUS_PENDING_SIGNATURES,
            ]);

            $document->forceFill(['tracking_number' => 'AIP-CY'.$data['year'].'-'.str_pad((string) $document->id, 5, '0', STR_PAD_LEFT)])->save();

            foreach (array_keys(AnnualInvestmentProgramService::SLOTS) as $index => $slot) {
                $user = $signers[$slot];

                SignatureRequest::create([
                    'request_uuid' => (string) Str::uuid(),
                    'document_type' => 'competitive_bidding_document',
                    'document_id' => $document->id,
                    'document_label' => $document->document_title,
                    'tracking_number' => $document->displayNumber(),
                    'signatory_slot' => $slot,
                    'signatory_label' => AnnualInvestmentProgramService::SLOTS[$slot],
                    'signing_order' => $index + 1,
                    'signing_mode' => 'parallel',
                    'is_required' => true,
                    'requested_by_user_id' => $request->user()->id,
                    'requested_to_user_id' => $user->id,
                    'requested_to_role' => $user->role,
                    'requested_to_office_id' => $user->office_id,
                    'status' => SignatureRequest::STATUS_PENDING,
                    'metadata' => [
                        'printed_name' => $data[$slot.'_name'],
                        'display_role_label' => $data[$slot.'_title'],
                        'template_key' => 'annual_investment_program',
                    ],
                ]);
            }

            AuditLogger::signature('aip_submitted_for_signatures', $document, [
                'description' => 'Annual Investment Program routed to the three assigned accounts for signatures.',
                'metadata' => ['recipient_account_ids' => array_map(fn ($user) => $user->user_id, $signers)],
            ]);

            return $document;
        });

        foreach ($document->signatureRequests as $signatureRequest) {
            $signatureRequests->notifySigner($signatureRequest);
        }

        return redirect()
            ->route('bac-secretariat.competitive-bidding.annual-investment-program.show', $document)
            ->with('status', 'AIP submitted to the assigned signatories. Each person can open it under Signatures.');
    }

    public function show(Request $request, CompetitiveBiddingSignatureDocument $document, SignatureRequestService $signatureRequests): View
    {
        abort_unless($document->template_key === 'annual_investment_program'
            && $request->user()?->user_id === 'BACSEC-003'
            && (int) $document->prepared_by_user_id === (int) $request->user()->id, 403);

        $requests = $document->signatureRequests()->with('requestedTo')->orderBy('signing_order')->get();
        $signatureSlots = $signatureRequests->signedSignaturesForDocument($document, 'competitive_bidding_document');

        return view('bac-secretariat.competitive-bidding.annual-investment-program-submitted', compact('document', 'requests', 'signatureSlots'));
    }
}
