<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->fresh(['assignedRole.permissions', 'assignedOffice']) ?? $request->user()->loadMissing('assignedRole.permissions', 'assignedOffice');

        AuditLogger::log('Profile', $this->auditAction($user, 'Profile Viewed'), 'User viewed own profile.');

        return view('profile.show', [
            'user' => $user,
            'permissions' => $user->assignedRole?->permissions?->sortBy('name')->values() ?? collect(),
            'recentActivity' => AuditLog::where('user_id', $user->id)
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function edit(Request $request): View
    {
        $user = $request->user();

        AuditLogger::log('Profile', $this->auditAction($user, 'Profile Edit Page Viewed'), 'User opened own profile edit page.');

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldValues = $user->only(['name', 'email', 'contact_number', 'position', 'profile_photo_path']);
        $emailChanged = array_key_exists('email', $validated) && $validated['email'] !== $user->email;

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $validated['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
            AuditLogger::log('Profile', $this->auditAction($user, 'Profile Photo Uploaded'), 'User uploaded own profile photo.', $user);
        }

        unset($validated['profile_photo']);

        if ($emailChanged) {
            $validated['email_verified_at'] = null;
            $validated['email_verification_code_hash'] = null;
            $validated['email_verification_code_sent_at'] = null;
            $validated['email_verification_code_expires_at'] = null;
            $validated['email_verification_attempts'] = 0;
        }

        $user->update($validated);

        AuditLogger::log('Profile', $this->auditAction($user, 'profile_updated'), 'User updated own profile.', $user, $oldValues, $user->only(['name', 'email', 'contact_number', 'position', 'profile_photo_path']));

        return redirect()
            ->route('profile.show')
            ->with('status', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'The current password is incorrect.'])
                ->onlyInput();
        }

        $updates = [
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ];

        if (Schema::hasColumn('users', 'last_password_changed_at')) {
            $updates['last_password_changed_at'] = now();
        }

        $user->update($updates);

        SystemNotificationService::notify(
            $user,
            'Password Changed',
            'Your PaperTrail account password was updated.',
            SystemNotification::TYPE_SUCCESS,
            'Profile',
        );

        AuditLogger::log('Profile', $this->auditAction($user, 'password_changed'), 'User changed own password.', $user, null, null, 'warning');

        return redirect()
            ->route('profile.show')
            ->with('status', 'Password changed successfully.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
            AuditLogger::log('Profile', $this->auditAction($user, 'Profile Photo Deleted'), 'User removed own profile photo.', $user);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Profile photo removed.');
    }

    private function auditAction(User $user, string $default): string
    {
        if ($user->role !== User::ROLE_BAC_MEMBER) {
            return $default;
        }

        return match ($default) {
            'Profile Viewed' => 'BAC Member Profile Viewed',
            'Profile Edit Page Viewed' => 'BAC Member Profile Edit Page Viewed',
            'profile_updated' => 'profile_updated',
            'password_changed' => 'password_changed',
            'Profile Photo Uploaded' => 'BAC Member Profile Photo Updated',
            'Profile Photo Deleted' => 'BAC Member Profile Photo Updated',
            default => $default,
        };
    }
}
