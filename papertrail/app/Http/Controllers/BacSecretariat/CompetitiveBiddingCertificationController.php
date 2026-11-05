<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\CompetitiveBiddingCertificationDraft;
use App\Models\CompetitiveBiddingRecord;
use App\Models\CompetitiveBiddingSignatureDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SignatureRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompetitiveBiddingCertificationController extends Controller
{
    private const SIGNERS = [
        'head' => ['account' => 'BACSEC-002', 'label' => 'Certified by'],
        'chair' => ['account' => 'BACCHAIR-001', 'label' => 'Noted by'],
    ];

    public function create(Request $request): View
    {
        $this->authorizeEditor($request);

        $records = CompetitiveBiddingRecord::query()->latest()->get();
        $recordOptions = $records->mapWithKeys(fn (CompetitiveBiddingRecord $record) => [
            $record->id => [
                'project' => $record->project_title,
                'budget' => number_format((float) $record->approved_budget, 2),
                'budget_raw' => $record->approved_budget,
                'budget_words' => $this->amountInWords((float) $record->approved_budget),
                'closing_on' => $record->bid_opening_date?->toDateString(),
            ],
        ]);

        return view('bac-secretariat.competitive-bidding.head-certification', [
            'records' => $records,
            'recordOptions' => $recordOptions,
            'signers' => $this->signerAccounts(),
            'draft' => CompetitiveBiddingCertificationDraft::where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'draft' => ['required', 'array'],
            'draft.record_id' => ['nullable', 'integer', 'exists:competitive_bidding_records,id'],
            'draft.project' => ['nullable', 'string', 'max:255'],
            'draft.budget' => ['nullable', 'numeric', 'min:0'],
            'draft.published_on' => ['nullable', 'date_format:Y-m-d'],
            'draft.closing_on' => ['nullable', 'date_format:Y-m-d'],
            'draft.certified_on' => ['nullable', 'date_format:Y-m-d'],
            'draft.budget_words' => ['nullable', 'string', 'max:500'],
            'draft.head_name' => ['nullable', 'string', 'max:120'],
            'draft.head_title' => ['nullable', 'string', 'max:120'],
            'draft.chair_name' => ['nullable', 'string', 'max:120'],
            'draft.chair_title' => ['nullable', 'string', 'max:120'],
        ]);

        $draft = DB::transaction(function () use ($request, $validated) {
            $draft = CompetitiveBiddingCertificationDraft::where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (($draft?->revision ?? 0) !== (int) $validated['revision']) {
                return null;
            }

            if ($draft) {
                $draft->update(['payload' => $validated['draft'], 'revision' => $draft->revision + 1]);
            } else {
                $draft = CompetitiveBiddingCertificationDraft::create([
                    'user_id' => $request->user()->id,
                    'payload' => $validated['draft'],
                    'revision' => 1,
                ]);
            }

            return $draft;
        });

        if (! $draft) {
            return response()->json(['message' => 'This draft changed in another tab. Reload to see the newer version.'], 409);
        }

        return response()->json(['revision' => $draft->revision, 'saved_at' => $draft->updated_at->toIso8601String()]);
    }

    public function submit(Request $request, SignatureRequestService $signatureRequests): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'record_id' => ['required', 'integer', 'exists:competitive_bidding_records,id'],
            'project' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'gt:0'],
            'published_on' => ['required', 'date_format:Y-m-d'],
            'closing_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:published_on'],
            'certified_on' => ['required', 'date_format:Y-m-d'],
            'budget_words' => ['required', 'string', 'max:500'],
            'head_name' => ['required', 'string', 'max:120'],
            'head_title' => ['required', 'string', 'max:120'],
            'chair_name' => ['required', 'string', 'max:120'],
            'chair_title' => ['required', 'string', 'max:120'],
        ]);

        $record = CompetitiveBiddingRecord::findOrFail($data['record_id']);
        if ($record->approved_budget === null || (float) $record->approved_budget <= 0) {
            throw ValidationException::withMessages(['record_id' => 'The selected bidding file needs an approved budget before certification.']);
        }
        $signers = $this->resolveSigners();
        $data['budget'] = number_format((float) $data['budget'], 2);

        $document = DB::transaction(function () use ($request, $record, $data, $signers) {
            $signatories = [];
            foreach (self::SIGNERS as $slot => $details) {
                $user = $signers[$slot];
                $signatories[] = [
                    'slot' => $slot,
                    'label' => $details['label'],
                    'name' => $data[$slot.'_name'],
                    'title' => $data[$slot.'_title'],
                    'recipient_user_id' => $user->id,
                    'recipient_account' => $user->user_id,
                ];
            }

            $document = CompetitiveBiddingSignatureDocument::create([
                'template_key' => 'bac_secretariat_head_certification',
                'document_title' => 'Certification of the Head of the BAC Secretariat - '.$record->project_title,
                'tracking_number' => $record->reference_number,
                'document_html' => view('bac-secretariat.competitive-bidding.partials.head-certification-document', compact('data'))->render(),
                'signatories' => $signatories,
                'prepared_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
                'status' => CompetitiveBiddingSignatureDocument::STATUS_PENDING_SIGNATURES,
            ]);

            foreach (self::SIGNERS as $slot => $details) {
                $user = $signers[$slot];
                SignatureRequest::create([
                    'request_uuid' => (string) Str::uuid(),
                    'document_type' => 'competitive_bidding_document',
                    'document_id' => $document->id,
                    'document_label' => $document->document_title,
                    'tracking_number' => $record->reference_number,
                    'signatory_slot' => $slot,
                    'signatory_label' => $details['label'],
                    'signing_order' => $slot === 'head' ? 1 : 2,
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
                        'template_key' => 'bac_secretariat_head_certification',
                    ],
                ]);
            }

            CompetitiveBiddingCertificationDraft::where('user_id', $request->user()->id)->delete();

            AuditLogger::signature('bac_secretariat_head_certification_submitted', $document, [
                'description' => 'Posting certification routed to the BAC Secretariat head and BAC Chairperson.',
                'metadata' => ['bidding_record_id' => $record->id],
            ]);

            return $document;
        });

        foreach ($document->signatureRequests as $signatureRequest) {
            $signatureRequests->notifySigner($signatureRequest);
        }

        return redirect()->route('bac-secretariat.competitive-bidding.certification.show', $document)
            ->with('status', 'Certification sent to BACSEC-002 and the BAC Chairperson for signatures.');
    }

    public function show(Request $request, CompetitiveBiddingSignatureDocument $document, SignatureRequestService $signatureRequests): View
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003'
            && $document->template_key === 'bac_secretariat_head_certification'
            && (int) $document->prepared_by_user_id === (int) $request->user()->id, 403);

        return view('bac-secretariat.competitive-bidding.head-certification-submitted', [
            'document' => $document,
            'requests' => $document->signatureRequests()->with('requestedTo')->orderBy('signing_order')->get(),
            'signatureSlots' => $signatureRequests->signedSignaturesForDocument($document, 'competitive_bidding_document'),
        ]);
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->user_id === 'BACSEC-003', 403);
    }

    private function signerAccounts(): array
    {
        $users = User::query()->where('status', User::STATUS_ACTIVE)
            ->whereIn('user_id', array_column(self::SIGNERS, 'account'))->get()->keyBy('user_id');

        return [
            'head' => $users->get(self::SIGNERS['head']['account']),
            'chair' => $users->get(self::SIGNERS['chair']['account']),
        ];
    }

    private function resolveSigners(): array
    {
        $signers = $this->signerAccounts();
        foreach (self::SIGNERS as $slot => $details) {
            if (! $signers[$slot]) {
                throw ValidationException::withMessages([$slot.'_name' => "The {$details['account']} signing account is unavailable."]);
            }
        }

        return $signers;
    }

    private function amountInWords(float $amount): string
    {
        if (! class_exists(\NumberFormatter::class)) {
            return '';
        }

        $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
        $whole = (int) floor($amount);
        $centavos = (int) round(($amount - $whole) * 100);
        $words = ucfirst((string) $formatter->format($whole)).' Pesos';

        return $centavos > 0 ? $words.' and '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100' : $words;
    }
}
