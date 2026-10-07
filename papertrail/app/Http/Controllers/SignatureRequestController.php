<?php

namespace App\Http\Controllers;

use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SvpProcurementChain;
use App\Services\AuditLogger;
use App\Services\Bacsec002PrSignatoryRoutingService;
use App\Services\EndUserPrSignatoryRoutingService;
use App\Services\ElectronicSignatureService;
use App\Services\HeadOfficePpmpSignatureWorkflowService;
use App\Services\SignatureRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignatureRequestController extends Controller
{
    public function __construct(
        private readonly SignatureRequestService $signatureRequests,
        private readonly ElectronicSignatureService $signatures,
    ) {
    }

    public function index(Request $request): View
    {
        app(Bacsec002PrSignatoryRoutingService::class)->activateParallelRequestsForSigner($request->user());
        app(EndUserPrSignatoryRoutingService::class)->activateParallelRequestsForSigner($request->user());

        $query = $this->signatureRequests->requestsForUser($request->user());
        $this->applyFilters($query, $request);
        $signatureRequests = $query->latest('created_at')->paginate(10)->withQueryString();

        $signatureRequests->getCollection()->transform(function (SignatureRequest $signatureRequest) use ($request) {
            $document = $this->signatures->findDocument($signatureRequest->document_type, $signatureRequest->document_id);

            $signatureRequest->setAttribute('display_tracking_number', $this->signatureTrackingNumber($signatureRequest, $document));
            $signatureRequest->setAttribute('official_pr_number', $document instanceof ProcurementDocument ? $document->pr_no : null);

            $signatureRequest->setAttribute('can_sign_now', $document
                && $signatureRequest->isOpen()
                && $this->signatures->canSign($request->user(), $document, $signatureRequest->document_type, 'confirmed', $signatureRequest));

            if ($signatureRequest->status === SignatureRequest::STATUS_SIGNED) {
                $signatureRequest->setRelation('signedSignatureForList', $this->signatureRequests->signedSignatureForRequest($signatureRequest));
            }

            if ($signatureRequest->document_type === 'purchase_request' && $document instanceof ProcurementDocument) {
                $svpChain = SvpProcurementChain::query()
                    ->where('source_pr_document_id', $document->id)
                    ->first(['chain_number']);

                $signatureRequest->setAttribute('is_svp_signature_request', (bool) $svpChain);
                $signatureRequest->setAttribute('svp_chain_number', $svpChain?->chain_number);
                $signatureRequest->setAttribute('document_context_label', $this->purchaseRequestOfficeLabel($document));
            }

            if ($signatureRequest->document_type === 'ppmp' && $document instanceof ProcurementDocument) {
                $signatureRequest->setAttribute('signatory_person_label', $this->ppmpHeadOfficeLabel($signatureRequest, $document));
                $signatureRequest->setAttribute('document_context_label', $this->ppmpOfficeLabel($signatureRequest, $document));
            }

            return $signatureRequest;
        });

        AuditLogger::signature('signature_requests_index_viewed', null, [
            'description' => 'User viewed documents for signature.',
            'metadata' => ['user_id' => $request->user()->user_id],
        ]);

        $summaryBase = $this->signatureRequests->requestsForUser($request->user());
        $documentTypes = $this->signatureRequests->requestsForUser($request->user())
            ->select('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type')
            ->filter()
            ->values();

        return view('signature-requests.index', [
            'signatureRequests' => $signatureRequests,
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'pending' => (clone $summaryBase)->open()->count(),
                'signed' => (clone $summaryBase)->where('status', SignatureRequest::STATUS_SIGNED)->count(),
                'declined' => (clone $summaryBase)->whereIn('status', [SignatureRequest::STATUS_DECLINED, SignatureRequest::STATUS_RETURNED])->count(),
            ],
            'filters' => $request->only(['search', 'document_type', 'status', 'date_from', 'date_to']),
            'documentTypes' => $documentTypes,
            'statuses' => [
                SignatureRequest::STATUS_PENDING => 'Active',
                SignatureRequest::STATUS_NOTIFIED => 'Notified',
                SignatureRequest::STATUS_VIEWED => 'Viewed',
                SignatureRequest::STATUS_SIGNED => 'Signed',
                'returned_declined' => 'Returned / Declined',
                SignatureRequest::STATUS_RETURNED => 'Returned',
                SignatureRequest::STATUS_DECLINED => 'Declined',
                SignatureRequest::STATUS_WAITING => 'Waiting',
            ],
        ]);
    }

    public function show(Request $request, SignatureRequest $signatureRequest): View
    {
        $this->authorizeRequest($request, $signatureRequest);
        $this->activatePilotParallelRequest($signatureRequest);
        $signatureRequest->refresh();
        $this->signatureRequests->markViewed($signatureRequest, $request->user());
        $signatureRequest->refresh();

        $signatureRequest->loadMissing(['requestedBy', 'requestedTo', 'requestedOffice']);
        $document = $this->documentOrFail($signatureRequest);
        $sourceDocument = $document->sourcePrDocument ?? null;
        $signedSignature = $this->signatureRequests->signedSignatureForRequest($signatureRequest);
        $existingSignature = $signedSignature ?: $this->signatureRequests->signatureForRequest($signatureRequest);
        $signatureSlots = $this->signatureRequests->signedSignaturesForDocument($document, $signatureRequest->document_type);
        $canSubmitPpmpToAppConsolidation = $signatureRequest->document_type === 'ppmp'
            && $document instanceof ProcurementDocument
            && app(HeadOfficePpmpSignatureWorkflowService::class)->canSubmitToAppConsolidation($request->user(), $document);

        return view('signature-requests.show', [
            'signatureRequest' => $signatureRequest->fresh(['requestedBy', 'requestedTo', 'requestedOffice']),
            'document' => $document,
            'sourceDocument' => $sourceDocument,
            'signedSignature' => $signedSignature,
            'existingSignature' => $existingSignature,
            'signatureSlots' => $signatureSlots,
            'canSign' => $this->signatures->canSign($request->user(), $document, $signatureRequest->document_type, 'confirmed', $signatureRequest),
            'signatureProfileComplete' => $this->signatures->ensureSignatureProfile($request->user()),
            'canSubmitPpmpToAppConsolidation' => $canSubmitPpmpToAppConsolidation,
        ]);
    }

    public function sendCode(Request $request, SignatureRequest $signatureRequest): RedirectResponse
    {
        $this->authorizeRequest($request, $signatureRequest);
        $this->activatePilotParallelRequest($signatureRequest);
        $signatureRequest->refresh();
        $document = $this->documentOrFail($signatureRequest);

        $result = $this->signatures->sendSigningCode(
            $request->user(),
            $document,
            $signatureRequest->document_type,
            $request->input('signature_action', 'confirmed'),
            $signatureRequest,
        );

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function sign(Request $request, SignatureRequest $signatureRequest): RedirectResponse
    {
        $this->authorizeRequest($request, $signatureRequest);
        $this->activatePilotParallelRequest($signatureRequest);
        $signatureRequest->refresh();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'signing_code' => ['required', 'digits:6'],
            'signature_consent' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'signature_action' => ['nullable', 'string', 'max:50'],
        ]);

        $document = $this->documentOrFail($signatureRequest);
        $signatureDesignation = $signatureRequest->requested_to_role ?: $signatureRequest->signatory_label ?: 'assigned signer';
        $result = $this->signatures->signDocument(
            $document,
            $request->user(),
            $signatureRequest->document_type,
            $validated['signature_action'] ?? 'confirmed',
            [
                ...$validated,
                'consent_text' => "I confirm that I have reviewed this document as {$signatureDesignation} and I approve/sign it electronically in PaperTrail.",
            ],
            $request,
            $signatureRequest,
        );

        if (! $result['ok']) {
            return back()
                ->with('error', $result['message'])
                ->withInput($request->except(['current_password', 'signing_code']));
        }

        return back()->with('status', $result['message']);
    }

    public function decline(Request $request, SignatureRequest $signatureRequest): RedirectResponse
    {
        $this->authorizeRequest($request, $signatureRequest);
        $this->activatePilotParallelRequest($signatureRequest);
        $signatureRequest->refresh();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'signature_action' => ['nullable', 'string', 'max:50'],
        ]);

        $document = $this->documentOrFail($signatureRequest);
        $result = $this->signatures->declineSignature(
            $document,
            $request->user(),
            $signatureRequest->document_type,
            $validated['signature_action'] ?? 'confirmed',
            $validated['reason'],
            $request,
            $signatureRequest,
        );

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    private function authorizeRequest(Request $request, SignatureRequest $signatureRequest): void
    {
        if ($this->signatureRequests->userCanAccess($signatureRequest, $request->user())) {
            return;
        }

        AuditLogger::signature('unauthorized_signature_request_access', $signatureRequest, [
            'description' => 'Unauthorized signature request access attempt.',
            'severity' => 'warning',
            'metadata' => [
                'signature_request_id' => $signatureRequest->id,
                'user_id' => $request->user()?->user_id,
            ],
        ]);

        abort(403);
    }

    private function documentOrFail(SignatureRequest $signatureRequest)
    {
        $document = $this->signatures->findDocument($signatureRequest->document_type, $signatureRequest->document_id);

        abort_if(! $document, 404);

        return $document;
    }

    private function purchaseRequestOfficeLabel(ProcurementDocument $document): ?string
    {
        $document->loadMissing(['submittingOffice', 'submittedBy.assignedOffice', 'preparedBy.assignedOffice']);

        return collect([
            $document->submittingOffice?->name,
            $document->department_name,
            $document->submittedBy?->assignedOffice?->name,
            $document->submittedBy?->office,
            $document->preparedBy?->assignedOffice?->name,
            $document->preparedBy?->office,
        ])->first(fn ($label) => filled($label));
    }

    private function ppmpHeadOfficeLabel(SignatureRequest $signatureRequest, ProcurementDocument $document): ?string
    {
        $document->loadMissing(['submittedBy', 'preparedBy']);

        return collect([
            $signatureRequest->requestedTo?->name,
            data_get($signatureRequest->metadata, 'printed_name'),
            $document->submittedBy?->name,
            $document->preparedBy?->name,
            $signatureRequest->requested_to_role,
            $signatureRequest->signatory_label,
        ])->first(fn ($label) => filled($label));
    }

    private function ppmpOfficeLabel(SignatureRequest $signatureRequest, ProcurementDocument $document): ?string
    {
        $document->loadMissing(['submittingOffice', 'submittedBy.assignedOffice', 'preparedBy.assignedOffice']);

        $metadataOfficeNames = data_get($signatureRequest->metadata, 'office_names');

        if (is_array($metadataOfficeNames)) {
            $metadataOfficeNames = collect($metadataOfficeNames)->filter(fn ($label) => filled($label))->implode(', ');
        }

        return collect([
            $document->submittingOffice?->name,
            $signatureRequest->requestedOffice?->name,
            $metadataOfficeNames,
            $document->submittedBy?->assignedOffice?->name,
            $document->submittedBy?->office,
            $document->preparedBy?->assignedOffice?->name,
            $document->preparedBy?->office,
            $document->department_name,
        ])->first(fn ($label) => filled($label));
    }

    private function signatureTrackingNumber(SignatureRequest $signatureRequest, ?object $document): string
    {
        if ($document instanceof ProcurementDocument) {
            return $document->displayNumber();
        }

        if ($document && method_exists($document, 'displayNumber')) {
            return $document->displayNumber();
        }

        return $signatureRequest->tracking_number
            ?: $signatureRequest->document_label
            ?: 'N/A';
    }

    private function activatePilotParallelRequest(SignatureRequest $signatureRequest): void
    {
        if ($signatureRequest->document_type !== 'purchase_request') {
            return;
        }

        $document = $this->signatures->findDocument($signatureRequest->document_type, $signatureRequest->document_id);

        if (! ($document instanceof ProcurementDocument)) {
            return;
        }

        app(Bacsec002PrSignatoryRoutingService::class)->ensureParallelWorkflow($document);
        app(EndUserPrSignatoryRoutingService::class)->ensureParallelWorkflow($document);
    }

    private function applyFilters($query, Request $request): void
    {
        $query->when($request->filled('search'), function ($builder) use ($request) {
            $search = $request->input('search');
            $matchingProcurementDocumentIds = ProcurementDocument::query()
                ->where(function ($documents) use ($search) {
                    $documents->where('document_reference_number', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pr_no', 'like', "%{$search}%");
                })
                ->pluck('id')
                ->all();

            $builder->where(function ($nested) use ($search, $matchingProcurementDocumentIds) {
                $nested->where('document_label', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('signatory_label', 'like', "%{$search}%");

                if ($matchingProcurementDocumentIds !== []) {
                    $nested->orWhere(function ($documents) use ($matchingProcurementDocumentIds) {
                        $documents->whereIn('document_type', ['ppmp', 'purchase_request'])
                            ->whereIn('document_id', $matchingProcurementDocumentIds);
                    });
                }
            });
        });

        $query->when($request->filled('document_type'), fn ($builder) => $builder->where('document_type', $request->input('document_type')));

        $status = $request->input('status');

        if (! $request->filled('status')) {
            $query->open();
        } elseif ($status === 'all') {
            // Keep all assigned signature requests visible.
        } elseif (in_array($status, ['active', 'pending'], true)) {
            $query->open();
        } elseif ($status === 'open') {
            $query->open();
        } elseif ($status === 'returned_declined') {
            $query->whereIn('status', [SignatureRequest::STATUS_DECLINED, SignatureRequest::STATUS_RETURNED]);
        } else {
            $query->where('status', $status);
        }

        $query->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('created_at', '<=', $request->date('date_to')));
    }
}
