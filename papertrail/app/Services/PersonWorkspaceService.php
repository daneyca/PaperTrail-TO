<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonWorkspaceService
{
    public const INITIAL_ASSIGNMENTS = [
        ['MPDO-001', 'BACCHAIR-001', 'Edmar Peter T. Tambis', User::ROLE_BAC_CHAIR],
        ['MEO-001', 'BACVICE-001', 'Roel J. Bano', User::ROLE_BAC_VICE_CHAIRPERSON],
        ['MAO-001', 'BACMEM-001', 'Perpetua D. Salan', User::ROLE_BAC_MEMBER],
        ['HRMO-001', 'BACMEM-002', 'Rogelio A. Layo', User::ROLE_BAC_MEMBER],
        ['MSDWO-001', 'BACMEM-003', 'Jessica G. Resus', User::ROLE_BAC_MEMBER],
        ['MCR-001', 'BACSEC-002', 'Meagan C. Matutes', User::ROLE_BAC_SECRETARIAT],
    ];

    public function link(User $account, User $workspace): void
    {
        DB::transaction(function () use ($account, $workspace) {
            // Lock in a stable order, including the ownership check, to prevent competing assignments.
            $users = User::whereIn('id', [$account->id, $workspace->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $account = $users->get($account->id);
            $workspace = $users->get($workspace->id);
            if (! $account || ! $workspace || $account->is($workspace)
                || $account->principal_user_id || $workspace->principal_user_id
                || ! $account->isActive() || ! $workspace->isActive()
                || $account->isAdmin() || $workspace->isAdmin()
                || $workspace->linkedWorkspaces()->exists()
                || isset(User::RETIRED_ACCOUNT_REPLACEMENTS[$workspace->user_id])) {
                throw ValidationException::withMessages(['workspace_id' => 'Choose an active, unlinked account belonging to the same person. Admin accounts cannot be linked.']);
            }

            // Move the verified email only when the main login has none. Never overwrite it.
            if (! $account->email && $workspace->email) {
                $email = $workspace->email;
                $verifiedAt = $workspace->email_verified_at;
                $workspace->forceFill(['email' => null, 'email_verified_at' => null])->save();
                $account->forceFill(['email' => $email, 'email_verified_at' => $verifiedAt]);
            }
            if (! $account->hasSignatureProfile() && $workspace->hasSignatureProfile()) {
                $account->forceFill($workspace->only([
                    'typed_signature_name', 'signer_position', 'signature_image_path',
                    'signature_style', 'signature_setup_completed_at',
                ]));
            }
            $account->save();

            $workspace->forceFill([
                'principal_user_id' => $account->id,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'email_verification_code_hash' => null,
                'email_verification_code_expires_at' => null,
            ])->save();
            AuditLogger::log('Accounts', 'workspace_linked', 'Existing role workspace linked to one personal login.', $workspace, null, null, 'notice', [
                'login_account_id' => $account->id, 'workspace_id' => $workspace->id,
            ]);
        });
    }

    public function linkInitialAccounts(): void
    {
        foreach (self::INITIAL_ASSIGNMENTS as [$officeCode, $bacCode, $name, $role]) {
            $account = User::where('user_id', $officeCode)->first();
            $workspace = User::where('user_id', $bacCode)->first();
            if (! $account || ! $workspace) {
                continue;
            }
            if ($workspace->principal_user_id) {
                if ((int) $workspace->principal_user_id !== (int) $account->id) {
                    throw new \RuntimeException("{$bacCode} is already linked to another login. Review this assignment before continuing.");
                }
                continue;
            }
            if ($account->role !== User::ROLE_HEAD_OFFICE || $workspace->role !== $role) {
                throw new \RuntimeException("Unexpected role for {$officeCode} / {$bacCode}. No automatic reassignment was made.");
            }
            DB::transaction(function () use ($account, $workspace, $name) {
                $this->link($account, $workspace);
                $account->forceFill(['name' => $name])->save();
            });
        }
    }
}
