<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Mail\AdminPasswordResetNoticeMail;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->whereNotNull('user_id');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('user_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('office', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->filled('office')) {
            $query->where('office', $request->string('office')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('email_verification')) {
            $request->string('email_verification')->toString() === 'verified'
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }

        return view('admin.users.index', [
            'users' => $query
                ->orderBy('role')
                ->orderBy('office')
                ->orderBy('user_id')
                ->paginate(10)
                ->withQueryString(),
            'roles' => Role::orderBy('name')->pluck('name')->all(),
            'offices' => Office::orderBy('name')->pluck('name')->all(),
            'filters' => $request->only(['search', 'role', 'office', 'status', 'email_verification']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['status' => User::STATUS_ACTIVE]),
            'roles' => Role::where('status', Role::STATUS_ACTIVE)->orderBy('name')->get(),
            'offices' => Office::where('status', Office::STATUS_ACTIVE)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $validated['user_id'] = strtoupper($validated['user_id']);
        $office = Office::findOrFail($validated['office_id']);
        $role = Role::findOrFail($validated['role_id']);

        if ($error = $this->headOfficeAssignmentError($office, $role)) {
            return back()->withErrors(['office_id' => $error])->withInput();
        }

        $validated['office'] = $office->name;
        $validated['role'] = $role->name;
        $validated['password'] = Hash::make($validated['password']);
        $validated['email_verified_at'] = null;
        $validated['must_change_password'] = false;

        $user = User::create($validated);
        AuditLogger::log('User Management', 'user_created', 'User account created.', $user, null, $user->only(['user_id', 'name', 'office', 'role', 'status']));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Account created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::where('status', Role::STATUS_ACTIVE)
                ->orWhere('id', $user->role_id)
                ->orderBy('name')
                ->get(),
            'offices' => Office::where('status', Office::STATUS_ACTIVE)
                ->orWhere('id', $user->office_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->rules($user));
        $validated['user_id'] = strtoupper($validated['user_id']);
        $office = Office::findOrFail($validated['office_id']);
        $role = Role::findOrFail($validated['role_id']);

        if ($error = $this->headOfficeAssignmentError($office, $role)) {
            return back()->withErrors(['office_id' => $error])->withInput();
        }

        $validated['office'] = $office->name;
        $validated['role'] = $role->name;

        unset($validated['password']);

        if (($validated['email'] ?? null) !== $user->email) {
            $validated['email_verified_at'] = null;
            $validated['email_verification_code_hash'] = null;
            $validated['email_verification_code_sent_at'] = null;
            $validated['email_verification_code_expires_at'] = null;
            $validated['email_verification_attempts'] = 0;
        }

        $oldValues = $user->only(['user_id', 'name', 'office', 'role', 'status', 'email', 'email_verified_at']);
        $user->update($validated);
        AuditLogger::log('User Management', 'user_updated', 'User account updated.', $user, $oldValues, $user->only(['user_id', 'name', 'office', 'role', 'status', 'email', 'email_verified_at']));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Account updated successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot deactivate your own admin account.');
        }

        $oldValues = $user->only(['status']);
        $user->update([
            'status' => $user->status === User::STATUS_ACTIVE
                ? User::STATUS_INACTIVE
                : User::STATUS_ACTIVE,
        ]);
        AuditLogger::log('User Management', $user->status === User::STATUS_ACTIVE ? 'user_activated' : 'user_deactivated', 'User account status changed.', $user, $oldValues, $user->only(['status']), 'warning');

        return back()->with(
            'status',
            $user->status === User::STATUS_ACTIVE
                ? 'Account activated successfully.'
                : 'Account deactivated successfully.'
        );
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $user->update([
            'password' => Hash::make('password123'),
            'must_change_password' => true,
        ]);

        if ($user->hasVerifiedEmail()) {
            Mail::to($user->email)->send(new AdminPasswordResetNoticeMail($user));
        }

        AuditLogger::log('User Management', 'Admin Password Reset', 'Password reset performed by administrator.', $user, null, null, 'warning', [
            'target_user_id' => $user->user_id,
        ]);

        return back()->with('status', 'Temporary password assigned. User must change password after login.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'user_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'user_id')->ignore($user),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'office_id' => ['required', Rule::exists('offices', 'id')],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }

    private function headOfficeAssignmentError(Office $office, Role $role): ?string
    {
        if ($role->name !== User::ROLE_HEAD_OFFICE) {
            return null;
        }

        return $office->isRequestingOffice()
            ? null
            : 'Head of Office / End User accounts can only be assigned to offices marked as requesting/end-user offices.';
    }
}
