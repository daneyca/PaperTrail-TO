<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'Admin';
    public const ROLE_HEAD_OFFICE = 'Head of Office / End User';
    public const ROLE_BUDGET = 'Budget Officer';
    public const ROLE_ACCOUNTING = 'Accounting Officer';
    public const ROLE_BAC_SECRETARIAT = 'BAC Secretariat';
    public const ROLE_BAC_MEMBER = 'BAC Member';
    public const ROLE_BAC_CHAIR = 'BAC Chair';
    public const ROLE_BAC_VICE_CHAIRPERSON = 'BAC Vice Chairperson';
    public const ROLE_APPROVING_AUTHORITY = 'Head of the Procuring Entity';
    public const ROLE_PR_NUMBERING = 'PR Numbering Staff';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

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
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
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
        return $this->assignedRole?->code === $codeOrName
            || $this->assignedRole?->name === $codeOrName
            || $this->role === $codeOrName;
    }

    public function hasPermission(string $permissionKey): bool
    {
        return $this->assignedRole?->permissions()
            ->where('key', $permissionKey)
            ->exists() ?? false;
    }

    public function hasBacsec002PurchaseRequestCapability(): bool
    {
        return $this->user_id === 'BACSEC-002'
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

    public function dashboardRoute(): string
    {
        if ($this->assignedRole?->dashboard_route) {
            return $this->assignedRole->dashboard_route;
        }

        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_HEAD_OFFICE => 'head-office.dashboard',
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
