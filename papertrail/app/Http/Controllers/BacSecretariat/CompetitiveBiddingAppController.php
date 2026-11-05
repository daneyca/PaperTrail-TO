<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingAppDraft;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Services\CompetitiveBiddingAppService;
use App\Services\SignatureRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompetitiveBiddingAppController extends Controller
{
    public function create(Request $request, CompetitiveBiddingAppService $service): View
    {
        $this->authorizeEditor($request);

        return view('bac-secretariat.competitive-bidding.annual-procurement-plan', [
            'draft' => CompetitiveBiddingAppDraft::where('user_id', $request->user()->id)->first(),
            'signerAccounts' => $service->signerAccounts(),
        ]);
    }

    public function saveDraft(Request $request, CompetitiveBiddingAppService $service): JsonResponse
    {
        $this->authorizeEditor($request);
        $request->validate(['revision' => ['required', 'integer', 'min:0'], 'draft' => ['required', 'array']]);
        $payload = $service->validateDraft($request->input('draft'));

        $draft = DB::transaction(function () use ($request, $payload) {
            $draft = CompetitiveBiddingAppDraft::where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (($draft?->revision ?? 0) !== (int) $request->input('revision')) return null;
            if ($draft) {
                $draft->update(['payload' => $payload, 'revision' => $draft->revision + 1]);
            } else {
                $draft = CompetitiveBiddingAppDraft::create(['user_id' => $request->user()->id, 'payload' => $payload, 'revision' => 1]);
            }

            return $draft;
        });

        if (! $draft) return response()->json(['message' => 'This APP draft changed in another tab. Reload to see the newer version.'], 409);

        return response()->json(['revision' => $draft->revision, 'saved_at' => $draft->updated_at->toIso8601String()]);
    }

    public function submit(Request $request, CompetitiveBiddingAppService $service, SignatureRequestService $notifications): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $service->validateDocument($request);
        $signers = $service->resolveSigners();
        foreach ($signers as $slot => $user) {
            $data[$slot.'_name'] = trim($data[$slot.'_name'] ?? '') ?: $user->name;
            $data[$slot.'_title'] = trim($data[$slot.'_title'] ?? '');
        }

        $document = DB::transaction(function () use ($request, $data, $signers) {
            $signatories = [];
            foreach (CompetitiveBiddingAppService::SLOTS as $slot => $label) {
                $signatories[] = [
                    'slot' => $slot, 'label' => $label, 'name' => $data[$slot.'_name'],
                    'title' => $data[$slot.'_title'], 'recipient_user_id' => $signers[$slot]->id,
                    'recipient_account' => $signers[$slot]->user_id,
                ];
            }
            $document = CompetitiveBiddingSignatureDocument::create([
                'template_key' => 'annual_procurement_plan',
                'document_title' => 'Annual Procurement Plan CY '.$data['year'],
                'document_html' => view('bac-secretariat.competitive-bidding.partials.app-document', compact('data'))->render(),
                'signatories' => $signatories,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
                'status' => CompetitiveBiddingSignatureDocument::STATUS_PENDING_SIGNATURES,
            ]);
            $document->forceFill(['tracking_number' => 'APP-CY'.$data['year'].'-'.str_pad((string) $document->id, 5, '0', STR_PAD_LEFT)])->save();
            foreach (array_keys(CompetitiveBiddingAppService::SLOTS) as $index => $slot) {
                $user = $signers[$slot];
                SignatureRequest::create([
                    'request_uuid' => (string) Str::uuid(),
                    'document_type' => 'competitive_bidding_document', 'document_id' => $document->id,
                    'document_label' => $document->document_title, 'tracking_number' => $document->displayNumber(),
                    'signatory_slot' => $slot, 'signatory_label' => CompetitiveBiddingAppService::SLOTS[$slot],
                    'signing_order' => $index + 1, 'signing_mode' => 'parallel', 'is_required' => true,
                    'requested_by_user_id' => $request->user()->id, 'requested_to_user_id' => $user->id,
                    'requested_to_role' => $user->role, 'requested_to_office_id' => $user->office_id,
                    'status' => SignatureRequest::STATUS_PENDING,
                    'metadata' => ['printed_name' => $data[$slot.'_name'], 'display_role_label' => $data[$slot.'_title'], 'template_key' => 'annual_procurement_plan'],
                ]);
            }

            return $document;
        });

        foreach ($document->signatureRequests as $signatureRequest) $notifications->notifySigner($signatureRequest);

        return redirect()->route('bac-secretariat.competitive-bidding.annual-procurement-plan.show', $document)
            ->with('status', 'APP submitted to the four assigned signing accounts.');
    }

    public function show(Request $request, CompetitiveBiddingSignatureDocument $document, SignatureRequestService $service): View
    {
        abort_unless($document->template_key === 'annual_procurement_plan'
            && $request->user()?->user_id === 'BACSEC-003'
            && (int) $document->prepared_by_user_id === (int) $request->user()->id, 403);

        return view('bac-secretariat.competitive-bidding.annual-procurement-plan-submitted', [
            'document' => $document,
            'requests' => $document->signatureRequests()->with('requestedTo')->orderBy('signing_order')->get(),
            'signatureSlots' => $service->signedSignaturesForDocument($document, 'competitive_bidding_document'),
        ]);
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003', 403);
    }
}
