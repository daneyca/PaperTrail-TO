<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasWorkspaces
{
    public function principalAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_user_id');
    }

    public function linkedWorkspaces(): HasMany
    {
        return $this->hasMany(User::class, 'principal_user_id');
    }

    public function loginAccount(): User
    {
        return $this->principal_user_id ? $this->principalAccount : $this;
    }

    public function availableWorkspaces(): Collection
    {
        $account = $this->loginAccount();

        if ($account->isOfficeDocumentPreparer()) {
            return collect([$account])->filter(fn (User $workspace) => $workspace->isActive())->values();
        }

        return collect([$account])->merge($account->linkedWorkspaces()->with('assignedOffice', 'assignedRole')->get())
            ->filter(fn (User $workspace) => $workspace->isActive())->values();
    }

    public function workspaceLabel(): string
    {
        if ($this->user_id === \App\Services\BacsecMpdoWorkspaceService::WORKSPACE) {
            return 'MPDO Procurement Staff';
        }
        if ($this->role === self::ROLE_BAC_CHAIR) {
            return 'BAC Chairperson';
        }

        return $this->role === self::ROLE_HEAD_OFFICE
            ? ($this->assignedOffice?->name ?: $this->office ?: $this->role)
            : ($this->assignedRole?->name ?: $this->role);
    }

    // Identity is shared; IDs, offices and roles remain workspace-specific for routing.
    public function getNameAttribute($value): ?string
    {
        return $this->principal_user_id ? $this->loginAccount()->name : $value;
    }

    public function getEmailAttribute($value): ?string
    {
        return $this->principal_user_id ? $this->loginAccount()->email : $value;
    }

    public function getProfilePhotoPathAttribute($value): ?string
    {
        return $this->principal_user_id ? $this->loginAccount()->profile_photo_path : $value;
    }
}
