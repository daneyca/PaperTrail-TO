<?php

namespace App\Services;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\ProcurementDocument;
use App\Models\SignatureRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BacsecMpdoWorkspaceService
{
    public const ACCOUNT = 'BACSEC-003';
    public const WORKSPACE = 'BACSEC-003-MPDO';

    public const DESTINATIONS = [
        'dashboard' => 'head-office.dashboard',
        'ppmp' => 'head-office.ppmp.index',
        'pr' => 'head-office.pr.create',
        'resolutions' => 'head-office.resolutions.index',
        'rfq' => 'head-office.rfqs.create',
        'abstract' => 'head-office.abstracts.create',
        'purchase-order' => 'head-office.purchase-orders.create',
        'inspection' => 'head-office.inspection.index',
        'documents' => 'head-office.documents.index',
    ];

    public function allows(User $account): bool
    {
        return $account->user_id === self::ACCOUNT && ! $account->principal_user_id
            && $account->isActive() && $account->hasRole(User::ROLE_BAC_SECRETARIAT);
    }

    public function existing(User $account): ?User
    {
        if (! $this->allows($account)) {
            return null;
        }
        $actor = User::with('assignedOffice', 'assignedRole')->where('user_id', self::WORKSPACE)
            ->where('principal_user_id', $account->id)->where('role', User::ROLE_OFFICE_PREPARER)
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('assignedOffice', fn ($office) => $office->where('code', 'MPDO')->where('status', Office::STATUS_ACTIVE))->first();

        return $actor?->setRelation('principalAccount', $account);
    }

    // Keep the existing preparation ownership for saved documents without changing the login session.
    public function forRequest(User $account, Request $request): ?User
    {
        if (! $this->allows($account)) {
            return null;
        }
        if ($request->routeIs('head-office.*')) {
            return $this->ensure($account);
        }
        $type = strtolower(str_replace(['-', '_', ' '], '', (string) $request->route('documentType')));
        if ($request->routeIs('document-templates.*', 'document-template-imports.*')
            && in_array($type, ['ppmp', 'pr', 'purchaserequest', 'rfq', 'abstract', 'purchaseorder', 'inspectionacceptance'], true)) {
            return $this->ensure($account);
        }
        $actor = $this->existing($account);
        $signatureRequest = $request->route('signatureRequest');
        if ($request->routeIs('signature-requests.show', 'signature-requests.sign', 'signature-requests.decline')
            && is_scalar($signatureRequest) && ctype_digit((string) $signatureRequest)) {
            $signatureRequest = SignatureRequest::find($signatureRequest);
        }
        if ($actor && $signatureRequest instanceof SignatureRequest
            && $signatureRequest->document_type === 'ppmp' && $signatureRequest->signatory_slot === 'prepared_by'
            && (int) $signatureRequest->requested_to_user_id === (int) $actor->id) {
            return $actor;
        }
        if ($actor && $request->routeIs('e-signatures.sign', 'e-signatures.decline') && $type === 'ppmp'
            && ProcurementDocument::whereKey($request->route('documentId'))->where('document_type', 'PPMP')
                ->where('prepared_by_user_id', $actor->id)->exists()) {
            return $actor;
        }

        return null;
    }

    public function preparationSigner(User $user, mixed $document, string $type): User
    {
        if ($type === 'ppmp' && $document instanceof ProcurementDocument && $this->allows($user)) {
            $actor = $this->existing($user);
            if ($actor && (int) $document->prepared_by_user_id === (int) $actor->id) {
                return $actor;
            }
        }
        return $user;
    }

    public function notificationOwnerIds(User $user): array
    {
        $account = $user->loginAccount();
        if (! $this->allows($account) || ($user->id !== $account->id && $user->user_id !== self::WORKSPACE)) {
            return [$user->id];
        }
        return array_values(array_filter([$account->id, $this->existing($account)?->id]));
    }

    public function ensure(User $account): User
    {
        abort_unless($this->allows($account), 403);

        return DB::transaction(function () use ($account) {
            User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $office = Office::where('code', 'MPDO')->where('status', Office::STATUS_ACTIVE)->first();
            $role = Role::where('code', 'office_preparer')->first();
            if (! $office || ! $office->isRequestingOffice() || ! $role) {
                throw ValidationException::withMessages(['workspace' => 'Configure the active MPDO requesting office and Procurement Staff role before opening MPDO SVP.']);
            }

            $workspace = User::where('user_id', self::WORKSPACE)->first();
            if ($workspace) {
                if ((int) $workspace->principal_user_id !== (int) $account->id
                    || (int) $workspace->office_id !== (int) $office->id
                    || $workspace->role !== User::ROLE_OFFICE_PREPARER || ! $workspace->isActive()) {
                    throw ValidationException::withMessages(['workspace' => 'The MPDO preparation assignment is inactive or assigned differently. Ask the administrator to review it.']);
                }
            } else {
                $workspace = new User();
                $workspace->forceFill([
                    'user_id' => self::WORKSPACE, 'principal_user_id' => $account->id,
                    'name' => $account->name, 'role' => User::ROLE_OFFICE_PREPARER, 'role_id' => $role->id,
                    'office_id' => $office->id, 'office' => $office->name, 'position' => 'MPDO Procurement Staff',
                    'status' => User::STATUS_ACTIVE, 'must_change_password' => false,
                    'password' => Hash::make(Str::random(64)),
                ])->save();
                AuditLogger::log('Accounts', 'mpdo_svp_workspace_assigned', 'BACSEC-003 was assigned MPDO SVP document preparation.', $workspace, null, null, 'notice', [
                    'login_account_id' => $account->id, 'office_id' => $office->id,
                ]);
            }

            return $workspace->setRelation('principalAccount', $account)->loadMissing('assignedOffice', 'assignedRole');
        });
    }
}
