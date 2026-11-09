<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable implements MustVerifyEmail
{
    use \App\Models\Concerns\HasWorkspaces;
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'Admin';
    public const ROLE_HEAD_OFFICE = 'Head of Office';
    public const ROLE_OFFICE_PREPARER = 'Procurement Staff';
    public const ROLE_BUDGET = 'Budget Officer';
    public const ROLE_ACCOUNTING = 'Accounting Officer';
    public const ROLE_BAC_SECRETARIAT = 'BAC Secretariat';
    public const ROLE_BAC_MEMBER = 'BAC Member';
    public const ROLE_BAC_CHAIR = 'BAC Chair';
    public const ROLE_BAC_VICE_CHAIRPERSON = 'BAC Vice Chairperson';
    public const ROLE_APPROVING_AUTHORITY = 'Head of the Procuring Entity';
    public const ROLE_PROCUREMENT_CONTROLLER = 'Procurement Controller';
    public const ROLE_PR_NUMBERING = self::ROLE_PROCUREMENT_CONTROLLER;

    public const PROCUREMENT_CONTROLLER_USER_ID = 'PC-001';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const OFFICE_WORKFLOW_ROLES = [
        'MBO-001' => ['name' => self::ROLE_BUDGET, 'code' => 'budget_officer', 'slug' => 'budget'],
        'ACC-001' => ['name' => self::ROLE_ACCOUNTING, 'code' => 'accounting_officer', 'slug' => 'accounting'],
        'MO-001' => ['name' => self::ROLE_APPROVING_AUTHORITY, 'code' => 'approving_authority', 'slug' => 'approving-authority'],
    ];

    public const RETIRED_ACCOUNT_REPLACEMENTS = [
        'BUDGET-001' => 'MBO-001',
        'ACCOUNTING-001' => 'ACC-001',
        'HOPE-001' => 'MO-001',
    ];

    public static function officeWorkflowRoleNames(): array
    {
        return array_column(self::OFFICE_WORKFLOW_ROLES, 'name');
    }

    public static function hiddenLoginRoleNames(): array
    {
        return array_merge(self::officeWorkflowRoleNames(), [
            self::ROLE_BAC_MEMBER,
            self::ROLE_BAC_CHAIR,
            self::ROLE_BAC_VICE_CHAIRPERSON,
        ]);
    }

    public function officeWorkflowRole(): ?array
    {
        return $this->role === self::ROLE_HEAD_OFFICE && $this->isActive()
            ? (self::OFFICE_WORKFLOW_ROLES[$this->user_id] ?? null)
            : null;
    }

    public static function officeAccountForRole(string $role): ?string
    {
        foreach (self::OFFICE_WORKFLOW_ROLES as $account => $duty) {
            if (in_array($role, $duty, true)) {
                return $account;
            }
        }

        return null;
    }

    public function scopeForWorkflowRole(Builder $query, string $role): Builder
    {
        $account = self::officeAccountForRole($role);

        return $query->where(function (Builder $roles) use ($role, $account) {
            $roles->where('role', $role)->orWhereHas('assignedRole', function (Builder $assigned) use ($role) {
                $assigned->where('name', $role)->orWhere('code', $role);
            });
            if ($account) {
                $roles->orWhere(fn (Builder $office) => $office->where('user_id', $account)->where('role', self::ROLE_HEAD_OFFICE));
            }
        });
    }

    public static function officeWorkflowRecipient(string $role): ?self
    {
        $account = self::officeAccountForRole($role);

        return $account ? self::where('user_id', $account)
            ->where('role', self::ROLE_HEAD_OFFICE)->where('status', self::STATUS_ACTIVE)->first() : null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'email_verified_at',
        'email_verification_code_hash',
        'email_verification_code_sent_at',
        'email_verification_code_expires_at',
        'email_verification_attempts',
        'contact_number',
        'contact_verified_at',
        'position',
        'profile_photo_path',
        'last_password_changed_at',
        'typed_signature_name',
        'signer_position',
        'signature_image_path',
        'signature_style',
        'signature_setup_completed_at',
        'office',
        'office_id',
        'role',
        'role_id',
        'password',
        'must_change_password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'email_verification_code_sent_at' => 'datetime',
        'email_verification_code_expires_at' => 'datetime',
        'email_verification_attempts' => 'integer',
        'contact_verified_at' => 'datetime',
        'must_change_password' => 'boolean',
        'last_password_changed_at' => 'datetime',
        'signature_setup_completed_at' => 'datetime',
    ];

    public function hasVerifiedEmail(): bool
    {
        if ($this->principal_user_id) {
            return $this->loginAccount()->hasVerifiedEmail();
        }
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        if ($this->principal_user_id) {
            return $this->loginAccount()->markEmailAsVerified();
        }
        return $this->forceFill([
            'email_verified_at' => now(),
            'email_verification_code_hash' => null,
            'email_verification_code_sent_at' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function assignedOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function systemNotifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class);
    }

    public function chatbotConversations(): HasMany
    {
        return $this->hasMany(ChatbotConversation::class);
    }

    public function notifications(): HasMany
    {
        return $this->systemNotifications();
    }

    public function electronicSignatures(): HasMany
    {
        return $this->hasMany(ElectronicSignature::class, 'signer_user_id');
    }

    public function hasSignatureProfile(): bool
    {
        return filled($this->typed_signature_name) && filled($this->signer_position);
    }

    public function hasRole(string $codeOrName): bool
    {
        return in_array($codeOrName, $this->officeWorkflowRole() ?? [], true)
            || $this->assignedRole?->code === $codeOrName
            || $this->assignedRole?->name === $codeOrName
            || $this->role === $codeOrName;
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->assignedRole?->permissions()
            ->where('key', $permissionKey)
            ->exists()) {
            return true;
        }

        $duty = $this->officeWorkflowRole();

        return $duty && Role::where('code', $duty['code'])
            ->whereHas('permissions', fn (Builder $permissions) => $permissions->where('key', $permissionKey))->exists();
    }

    public function hasBacsec002PurchaseRequestCapability(): bool
    {
        return $this->user_id === 'BACSEC-001'
            && ($this->hasRole('bac_secretariat') || $this->hasRole(self::ROLE_BAC_SECRETARIAT));
    }

    public function hasBacsec002PurchaseRequestPermission(string $permissionKey): bool
    {
        return $this->hasBacsec002PurchaseRequestCapability()
            && in_array($permissionKey, [
                'pr.view',
                'pr.submit',
                'documents.submit',
                'documents.edit.own',
                'documents.track',
                'documents.upload',
            ], true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole(self::ROLE_ADMIN);
    }

    public function isOfficeDocumentPreparer(): bool
    {
        return $this->hasRole('office_preparer') || $this->hasRole(self::ROLE_OFFICE_PREPARER);
    }

    public function isOfficeDocumentActor(): bool
    {
        return $this->isOfficeDocumentPreparer()
            || $this->hasRole('head_office')
            || $this->hasRole(self::ROLE_HEAD_OFFICE);
    }

    public function canManageSvpDocuments(): bool
    {
        if ($this->isOfficeDocumentPreparer()) {
            return true;
        }

        if (! $this->hasRole('head_office') && ! $this->hasRole(self::ROLE_HEAD_OFFICE)) {
            return false;
        }

        // Offices without an active staff account keep their Head-managed workflow.
        return ! $this->office_id || ! self::query()
            ->where('office_id', $this->office_id)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $staff) {
                $staff->where('role', self::ROLE_OFFICE_PREPARER)
                    ->orWhereHas('assignedRole', fn (Builder $role) => $role->where('code', 'office_preparer'));
            })->exists();
    }

    public function dashboardRoute(): string
    {
        if ($this->assignedRole?->dashboard_route) {
            return $this->assignedRole->dashboard_route;
        }

        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_HEAD_OFFICE => 'head-office.dashboard',
            self::ROLE_OFFICE_PREPARER => 'head-office.dashboard',
            self::ROLE_BUDGET => 'budget.dashboard',
            self::ROLE_ACCOUNTING => 'accounting.dashboard',
            self::ROLE_BAC_SECRETARIAT => 'bac-secretariat.dashboard',
            self::ROLE_BAC_MEMBER => 'bac-member.dashboard',
            self::ROLE_BAC_CHAIR => 'bac-chair.dashboard',
            self::ROLE_BAC_VICE_CHAIRPERSON => 'bac-chair.dashboard',
            self::ROLE_APPROVING_AUTHORITY => 'approving-authority.dashboard',
            self::ROLE_PR_NUMBERING => 'pr-numbering.dashboard',
            default => 'dashboard',
        };
    }

    public function roleSlug(): string
    {
        if ($this->assignedRole?->code) {
            return match ($this->assignedRole->code) {
                'head_office' => 'head-office',
                'office_preparer' => 'head-office',
                'budget_officer' => 'budget',
                'accounting_officer' => 'accounting',
                'bac_secretariat' => 'bac-secretariat',
                'bac_member' => 'bac-member',
                'bac_chair' => 'bac-chair',
                'bac_vice_chairperson' => 'bac-chair',
                'approving_authority' => 'approving-authority',
                'pr_numbering_staff' => 'pr-numbering',
                default => str_replace('_', '-', $this->assignedRole->code),
            };
        }

        return match ($this->role) {
            self::ROLE_ADMIN => 'admin',
            self::ROLE_HEAD_OFFICE => 'head-office',
            self::ROLE_OFFICE_PREPARER => 'head-office',
            self::ROLE_BUDGET => 'budget',
            self::ROLE_ACCOUNTING => 'accounting',
            self::ROLE_BAC_SECRETARIAT => 'bac-secretariat',
            self::ROLE_BAC_MEMBER => 'bac-member',
            self::ROLE_BAC_CHAIR => 'bac-chair',
            self::ROLE_BAC_VICE_CHAIRPERSON => 'bac-chair',
            self::ROLE_APPROVING_AUTHORITY => 'approving-authority',
            self::ROLE_PR_NUMBERING => 'pr-numbering',
            default => '',
        };
    }
}
