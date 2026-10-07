<?php

namespace App\Http\Controllers\BacChair;

use App\Http\Controllers\Controller;
use App\Models\BacResolution;
use App\Models\DocumentRoutingHistory;
use App\Models\ElectronicSignature;
use App\Models\Office;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ElectronicSignatureService;
use App\Services\SignatureRequestService;
use App\Services\SvpChainService;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class BacResolutionController extends Controller
{
    private const VISIBLE_STATUSES = [
        BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR,
        BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
        BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR,
        BacResolution::STATUS_FORWARDED_TO_HOPE,
        BacResolution::STATUS_APPROVED_BY_HOPE,
        BacResolution::STATUS_RETURNED_BY_HOPE,
    ];

    public function index(Request $request): View
    {
        AuditLogger::log('BAC Chair Resolutions', 'BAC Chair Viewed BAC Resolutions List', 'BAC Chair viewed BAC Resolutions submitted for review.');

        $query = $this->baseQuery()
            ->with(['sourcePrDocument.submittingOffice', 'submittedBy', 'preparedBy', 'bacChairConfirmedBy', 'forwardedToHopeBy']);

        $this->applyFilters($query, $request);

        return view('bac-chair.resolutions.index', [
            'resolutions' => $query->latest('updated_at')->paginate(10)->withQueryString(),
            'summary' => $this->summary(),
            'filters' => $request->only(['search', 'fiscal_year', 'status', 'date_from', 'date_to']),
            'fiscalYears' => BacResolution::query()
                ->whereIn('status', self::VISIBLE_STATUSES)
                ->select('fiscal_year')
                ->whereNotNull('fiscal_year')
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'statuses' => self::VISIBLE_STATUSES,
        ]);
    }

    public function show(Request $request, BacResolution $resolution): View|RedirectResponse
    {
        if (! $this->canAccess($resolution)) {
            AuditLogger::log('BAC Chair Resolutions', 'Unauthorized Access Attempt', 'BAC Chair attempted to access a BAC Resolution outside the review queue.', $resolution, null, null, 'warning');

            return redirect()
                ->route('bac-chair.resolutions.index')
                ->with('error', 'You are not authorized to view this BAC Resolution.');
        }

        AuditLogger::log('BAC Chair Resolutions', 'BAC Chair Viewed BAC Resolution', 'BAC Chair viewed a BAC Resolution detail page.', $resolution);

        $resolution->load($this->resolutionRelations());
        $signatureService = app(ElectronicSignatureService::class);
        $signatureRequestService = app(SignatureRequestService::class);
        $signatureRequest = $signatureRequestService->openRequestForUser($resolution, 'bac-resolution', $request->user());
        $signedSignature = $signatureService->signedSignatureFor($resolution, 'bac-resolution', 'confirmed');

        return view('bac-chair.resolutions.show', [
            'resolution' => $resolution,
            'sourceDocument' => $resolution->sourcePrDocument,
            'existingSignature' => $signatureRequest
                ? ($signatureRequestService->signedSignatureForRequest($signatureRequest) ?: $signatureRequestService->signatureForRequest($signatureRequest))
                : ($signedSignature ?: $signatureService->latestSignatureFor($resolution, 'bac-resolution', 'confirmed')),
            'signedSignature' => $signedSignature,
            'signatureSlots' => $signatureRequestService->signedSignaturesForDocument($resolution, 'bac-resolution'),
            'signatureRequests' => $this->signatureRequestsFor($resolution),
            'canSignResolution' => $signatureService->canSign($request->user(), $resolution, 'bac-resolution', 'confirmed', $signatureRequest),
            'signatureProfileComplete' => $signatureService->ensureSignatureProfile($request->user()),
        ]);
    }

    public function print(Request $request, BacResolution $resolution): View|RedirectResponse
    {
        if (! $this->canAccess($resolution)) {
            AuditLogger::log('BAC Chair Resolutions', 'Unauthorized Access Attempt', 'BAC Chair attempted to print a BAC Resolution outside the review queue.', $resolution, null, null, 'warning');

            return redirect()
                ->route('bac-chair.resolutions.index')
                ->with('error', 'You are not authorized to print this BAC Resolution.');
        }

        AuditLogger::log('BAC Chair Resolutions', 'BAC Chair Opened BAC Resolution Print View', 'BAC Chair opened a BAC Resolution print view.', $resolution);

        $resolution->load($this->resolutionRelations());
        $signedSignature = app(ElectronicSignatureService::class)->signedSignatureFor($resolution, 'bac-resolution', 'confirmed');

        return view('bac-chair.resolutions.print', [
            'resolution' => $resolution,
            'sourceDocument' => $resolution->sourcePrDocument,
            'signedSignature' => $signedSignature,
            'signatureSlots' => app(SignatureRequestService::class)->signedSignaturesForDocument($resolution, 'bac-resolution'),
        ]);
    }

    public function confirm(Request $request, BacResolution $resolution): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'signing_code' => ['required', 'digits:6'],
            'signature_consent' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->canAccess($resolution)) {
            AuditLogger::log('BAC Chair Resolutions', 'Unauthorized Access Attempt', 'Invalid BAC Resolution confirmation attempt.', $resolution, null, null, 'warning');

            return back()->with('error', 'You are not authorized to confirm this BAC Resolution.');
        }

        $result = app(ElectronicSignatureService::class)->signDocument(
            $resolution,
            $request->user(),
            'bac-resolution',
            'confirmed',
            [
                ...$validated,
                'consent_text' => 'I confirm that I have reviewed this document and I approve/sign it electronically in PaperTrail.',
            ],
            $request,
        );

        return back()
            ->with($result['ok'] ? 'status' : 'error', $result['message'])
            ->withInput($request->except(['current_password', 'signing_code']));
    }

    public function return(Request $request, BacResolution $resolution): RedirectResponse
    {
        if (app(ElectronicSignatureService::class)->signedSignatureFor($resolution, 'bac-resolution', 'confirmed')) {
            return back()->with('error', 'This BAC Resolution has already been electronically signed and cannot be returned through the normal workflow.');
        }

        if (! $this->canAccess($resolution)
            || ! in_array($resolution->status, [BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR, BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR], true)) {
            AuditLogger::log('BAC Chair Resolutions', 'Unauthorized Access Attempt', 'Invalid BAC Resolution return attempt.', $resolution, null, null, 'warning');

            return back()->with('error', 'This BAC Resolution cannot be returned from its current status.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $target = $this->bacSecretariatTarget();

        if (! $target['user'] && ! $target['office']) {
            return back()->with('error', 'BAC Secretariat routing target is not configured.')->withInput();
        }

        DB::transaction(function () use ($request, $resolution, $validated, $target) {
            $resolution->loadMissing('sourcePrDocument');
            $oldStatus = $resolution->status;
            $remarks = $validated['remarks'];

            $resolution->update([
                'status' => BacResolution::STATUS_RETURNED_BY_BAC_CHAIR,
                'remarks' => $remarks,
                'bac_chair_remarks' => $remarks,
                'returned_at' => now(),
            ]);

            $this->updateSourceDocumentForReturn($resolution, $request->user(), $target, $oldStatus, $remarks);

            SystemNotificationService::notify(
                $resolution->submittedBy ?: $target['user'],
                'BAC Resolution Returned',
                'A BAC Resolution was returned by the BAC Chair and requires correction.',
                SystemNotification::TYPE_WARNING,
                'BAC Resolution',
                $resolution,
                route('bac-secretariat.resolutions.show', $resolution),
            );

            AuditLogger::log('BAC Chair Resolutions', 'BAC Chair Returned BAC Resolution', 'BAC Chair returned a BAC Resolution to BAC Secretariat.', $resolution, ['status' => $oldStatus], ['status' => BacResolution::STATUS_RETURNED_BY_BAC_CHAIR], 'warning');

            app(SvpChainService::class)->linkBacResolution($resolution->refresh(), $request->user(), 'BAC Chair returned BAC Resolution', $remarks);
        });

        return redirect()
            ->route('bac-chair.resolutions.index')
            ->with('status', 'BAC Resolution returned to BAC Secretariat.');
    }

    public function forwardToHope(Request $request, BacResolution $resolution): RedirectResponse
    {
        if (app(ElectronicSignatureService::class)->signedSignatureFor($resolution, 'bac-resolution', 'confirmed')) {
            return back()->with('error', 'This BAC Resolution has already been electronically signed and returned to the requesting office.');
        }

        if (! $this->canAccess($resolution) || $resolution->status !== BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR) {
            AuditLogger::log('BAC Chair Resolutions', 'Unauthorized Access Attempt', 'Invalid BAC Resolution forward-to-HOPE attempt.', $resolution, null, null, 'warning');

            return back()->with('error', 'Only confirmed BAC Resolutions can be forwarded to HOPE.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $hope = $this->hopeUser();

        if (! $hope) {
            return back()->with('error', 'Head of the Procuring Entity account is not configured.')->withInput();
        }

        DB::transaction(function () use ($request, $resolution, $validated, $hope) {
            $resolution->loadMissing('sourcePrDocument');
            $oldStatus = $resolution->status;
            $remarks = $validated['remarks'] ?? null;

            $resolution->update([
                'status' => BacResolution::STATUS_FORWARDED_TO_HOPE,
                'forwarded_to_hope_by_user_id' => $request->user()->id,
                'forwarded_to_hope_at' => now(),
            ]);

            $this->updateSourceDocumentForHopeForwarding($resolution, $request->user(), $hope, $oldStatus, $remarks);

            SystemNotificationService::notify(
                $hope,
                'BAC Resolution Forwarded for Final Approval',
                "BAC Resolution {$resolution->displayNumber()} was forwarded for HOPE review.",
                SystemNotification::TYPE_INFO,
                'BAC Resolution',
                $resolution,
                $resolution->sourcePrDocument && Route::has('approving-authority.pending.show')
                    ? route('approving-authority.pending.show', $resolution->sourcePrDocument)
                    : route('approving-authority.dashboard'),
            );

            AuditLogger::log('BAC Chair Resolutions', 'BAC Chair Forwarded BAC Resolution to HOPE', 'BAC Chair forwarded a BAC Resolution to the Head of the Procuring Entity.', $resolution, ['status' => $oldStatus], ['status' => BacResolution::STATUS_FORWARDED_TO_HOPE]);

            app(SvpChainService::class)->linkBacResolution($resolution->refresh(), $request->user(), 'BAC Resolution forwarded to HOPE', $remarks);
        });

        return back()->with('status', 'BAC Resolution forwarded to HOPE.');
    }

    private function baseQuery(): Builder
    {
        return BacResolution::query()->whereIn('status', self::VISIBLE_STATUSES);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('resolution_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('project_title', 'like', "%{$search}%")
                    ->orWhere('requesting_office_name', 'like', "%{$search}%")
                    ->orWhere('pr_number', 'like', "%{$search}%")
                    ->orWhereHas('sourcePrDocument', function (Builder $document) use ($search) {
                        $document->where('tracking_number', 'like', "%{$search}%")
                            ->orWhere('pr_no', 'like', "%{$search}%")
                            ->orWhereHas('submittingOffice', fn (Builder $office) => $office->where('name', 'like', "%{$search}%"));
                    });
            });
        });

        $query->when($request->filled('fiscal_year'), fn (Builder $builder) => $builder->where('fiscal_year', $request->integer('fiscal_year')));
        $query->when($request->filled('status') && strtolower((string) $request->input('status')) !== 'all', fn (Builder $builder) => $builder->where('status', $request->input('status')));
        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('submitted_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('submitted_at', '<=', $request->date('date_to')));
    }

    private function canAccess(BacResolution $resolution): bool
    {
        return in_array($resolution->status, self::VISIBLE_STATUSES, true);
    }

    private function summary(): array
    {
        return [
            'pending' => BacResolution::where('status', BacResolution::STATUS_SUBMITTED_TO_BAC_CHAIR)->count(),
            'confirmed' => BacResolution::where('status', BacResolution::STATUS_CONFIRMED_BY_BAC_CHAIR)->count(),
            'forwarded' => BacResolution::where('status', BacResolution::STATUS_FORWARDED_TO_HOPE)->count(),
            'returned' => BacResolution::where('status', BacResolution::STATUS_RETURNED_BY_BAC_CHAIR)->count(),
        ];
    }

    private function resolutionRelations(): array
    {
        return [
            'sourcePrDocument.submittingOffice',
            'sourcePrDocument.currentOffice',
            'sourcePrDocument.submittedBy',
            'sourcePrDocument.assignedTo',
            'sourcePrDocument.routingHistories.actionBy',
            'sourcePrDocument.routingHistories.fromOffice',
            'sourcePrDocument.routingHistories.toOffice',
            'preparedBy',
            'submittedBy',
            'approvedBy',
            'bacChairConfirmedBy',
            'forwardedToHopeBy',
            'items',
        ];
    }

    private function updateSourceDocumentForConfirmation(BacResolution $resolution, User $user, string $resolutionOldStatus, ?string $remarks): void
    {
        $document = $resolution->sourcePrDocument;

        if (! $document) {
            return;
        }

        $oldStatus = $document->status;
        $fromOffice = $document->current_office_id;
        $toOffice = $user->office_id ?? $fromOffice;

        $document->update([
            'status' => ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
            'stage' => ProcurementDocument::STAGE_BAC_CHAIR_CONFIRMATION,
            'current_office_id' => $toOffice,
            'assigned_to_user_id' => $user->id,
            'bac_chair_status' => 'confirmed',
            'bac_chair_confirmation_status' => 'confirmed',
            'bac_chair_reviewed_by_user_id' => $user->id,
            'bac_chair_reviewed_at' => now(),
            'bac_chair_confirmed_at' => now(),
            'bac_chair_remarks' => $remarks,
            'bac_chair_confirmation_remarks' => $remarks,
        ]);

        $this->recordRouting(
            $document,
            $user,
            'BAC Chair Confirmed BAC Resolution',
            $oldStatus,
            ProcurementDocument::STATUS_CONFIRMED_BY_BAC_CHAIR,
            $remarks ?: "BAC Resolution {$resolution->displayNumber()} was confirmed by the BAC Chair.",
            $fromOffice,
            $toOffice,
        );
    }

    private function signatureRequestsFor(BacResolution $resolution)
    {
        return SignatureRequest::query()
            ->with(['requestedTo', 'requestedOffice', 'electronicSignature'])
            ->forDocument('bac_resolution', $resolution->id)
            ->orderBy('signing_order')
            ->orderBy('id')
            ->get();
    }

    private function updateSourceDocumentForReturn(BacResolution $resolution, User $user, array $target, string $resolutionOldStatus, string $remarks): void
    {
        $document = $resolution->sourcePrDocument;

        if (! $document) {
            return;
        }

        $oldStatus = $document->status;
        $fromOffice = $document->current_office_id;
        $toOffice = $target['office']?->id ?? $target['user']?->office_id;

        $document->update([
            'status' => ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            'stage' => ProcurementDocument::STAGE_RETURNED_TO_BAC_SECRETARIAT,
            'current_office_id' => $toOffice,
            'assigned_to_user_id' => $target['user']?->id,
            'bac_chair_status' => 'returned',
            'bac_chair_confirmation_status' => 'returned',
            'bac_chair_reviewed_by_user_id' => $user->id,
            'bac_chair_reviewed_at' => now(),
            'bac_chair_remarks' => $remarks,
            'bac_chair_confirmation_remarks' => $remarks,
            'remarks' => $remarks,
            'route_destination_role' => User::ROLE_BAC_SECRETARIAT,
            'route_destination_office_id' => $toOffice,
            'route_remarks' => $remarks,
        ]);

        $this->recordRouting(
            $document,
            $user,
            'BAC Resolution Returned by BAC Chair',
            $oldStatus,
            ProcurementDocument::STATUS_RETURNED_BY_BAC_CHAIR,
            $remarks,
            $fromOffice,
            $toOffice,
        );
    }

    private function updateSourceDocumentForHopeForwarding(BacResolution $resolution, User $user, User $hope, string $resolutionOldStatus, ?string $remarks): void
    {
        $document = $resolution->sourcePrDocument;

        if (! $document) {
            return;
        }

        $oldStatus = $document->status;
        $fromOffice = $document->current_office_id;
        $toOffice = $hope->office_id ?? $this->officeByCodeOrNames('MO', ['Office of the Municipal Mayor'])?->id;

        $document->update([
            'status' => ProcurementDocument::STATUS_PENDING_APPROVAL,
            'stage' => ProcurementDocument::STAGE_APPROVING_AUTHORITY_REVIEW,
            'current_office_id' => $toOffice,
            'assigned_to_user_id' => $hope->id,
            'route_destination_role' => User::ROLE_APPROVING_AUTHORITY,
            'route_destination_office_id' => $toOffice,
            'routed_by_user_id' => $user->id,
            'routed_at' => now(),
            'route_remarks' => $remarks ?: "BAC Resolution {$resolution->displayNumber()} forwarded to HOPE.",
        ]);

        $this->recordRouting(
            $document,
            $user,
            'BAC Resolution Forwarded to HOPE',
            $oldStatus,
            ProcurementDocument::STATUS_PENDING_APPROVAL,
            $remarks ?: "BAC Resolution {$resolution->displayNumber()} was forwarded to the Head of the Procuring Entity.",
            $fromOffice,
            $toOffice,
        );
    }

    private function recordRouting(ProcurementDocument $document, User $user, string $action, ?string $fromStatus, string $toStatus, ?string $comments = null, ?int $fromOfficeId = null, ?int $toOfficeId = null): void
    {
        DocumentRoutingHistory::create([
            'procurement_document_id' => $document->id,
            'action_by_user_id' => $user->id,
            'from_office_id' => $fromOfficeId ?? $document->getOriginal('current_office_id'),
            'to_office_id' => $toOfficeId ?? $document->current_office_id,
            'action' => $action,
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'comments' => $comments,
            'action_at' => now(),
        ]);
    }

    private function bacSecretariatTarget(): array
    {
        $user = $this->activeUserByRoleCodeOrId(User::ROLE_BAC_SECRETARIAT, 'bac_secretariat', 'BACSEC-001');

        return [
            'user' => $user,
            'office' => $user?->assignedOffice ?? $this->officeByCodeOrNames('BACSEC', ['BAC Secretariat']),
        ];
    }

    private function hopeUser(): ?User
    {
        return $this->activeUserByRoleName(User::ROLE_APPROVING_AUTHORITY)
            ?? $this->activeUserByRoleCode('hope')
            ?? $this->activeUserByRoleCode('approving_authority')
            ?? User::where('status', User::STATUS_ACTIVE)->where('user_id', 'HOPE-001')->first();
    }

    private function activeUserByRoleCodeOrId(string $roleName, string $roleCode, string $fallbackUserId): ?User
    {
        return $this->activeUserByRoleName($roleName)
            ?? $this->activeUserByRoleCode($roleCode)
            ?? User::where('status', User::STATUS_ACTIVE)->where('user_id', $fallbackUserId)->first();
    }

    private function activeUserByRoleName(string $roleName): ?User
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($roleName) {
                $query->where('role', $roleName)
                    ->orWhereHas('assignedRole', fn (Builder $role) => $role->where('name', $roleName));
            })
            ->first();
    }

    private function activeUserByRoleCode(string $roleCode): ?User
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('assignedRole', fn (Builder $role) => $role->where('code', $roleCode))
            ->first();
    }

    private function officeByCodeOrNames(string $code, array $names): ?Office
    {
        return Office::query()
            ->where('code', $code)
            ->orWhereIn('name', $names)
            ->first();
    }
}
