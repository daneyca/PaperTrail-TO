<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        if (Auth::check()) {
            return redirect()
                ->route($request->user()->dashboardRoute())
                ->withHeaders($this->noCacheHeaders());
        }

        return view('auth.login', [
            'roles' => $this->roles(),
            'offices' => $this->endUserOffices(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string'],
            'office' => ['nullable', 'string'],
            'user_id' => ['required', 'string'],
            'password' => ['required', 'string'],
            'security_code' => ['required', 'string'],
        ]);

        if (! CaptchaController::matches($request, $validated['security_code'])) {
            AuditLogger::failed('user_login_failed', [
                'module' => 'Authentication',
                'description' => 'Security code mismatch.',
                'metadata' => [
                'attempted_user_id' => strtoupper($validated['user_id']),
                ],
            ]);

            return back()
                ->withErrors(['security_code' => 'Invalid security code. Please try again.'])
                ->withInput($request->except('password', 'security_code'));
        }

        $user = User::where('user_id', strtoupper($validated['user_id']))->first();

        if (!$user) {
            AuditLogger::failed('user_login_failed', [
                'module' => 'Authentication',
                'description' => 'Unknown User ID login attempt.',
                'metadata' => [
                'attempted_user_id' => strtoupper($validated['user_id']),
                ],
            ]);

            return back()
                ->withErrors(['user_id' => 'The account does not exist.'])
                ->withInput($request->except('password', 'security_code'));
        }

        if (!$user->isActive()) {
            AuditLogger::failed('user_login_failed', [
                'module' => 'Authentication',
                'description' => 'Inactive account login rejected.',
                'auditable' => $user,
                'metadata' => [
                'attempted_user_id' => $user->user_id,
                    'reason' => 'inactive_account',
                ],
            ]);

            return back()
                ->withErrors(['user_id' => 'This account is inactive. Please contact the administrator.'])
                ->withInput($request->except('password', 'security_code'));
        }

        if ($user->role !== $validated['role']) {
            AuditLogger::failed('user_login_failed', [
                'module' => 'Authentication',
                'description' => 'Selected role did not match account.',
                'auditable' => $user,
                'metadata' => [
                'attempted_role' => $validated['role'],
                ],
            ]);

            return back()
                ->withErrors(['role' => 'The selected login role does not match this account.'])
                ->withInput($request->except('password', 'security_code'));
        }

        if ($user->role === User::ROLE_HEAD_OFFICE && !$this->officeMatchesUser($user, $validated['office'])) {
            AuditLogger::failed('user_login_failed', [
                'module' => 'Authentication',
                'description' => 'Selected office did not match account.',
                'auditable' => $user,
                'metadata' => [
                'attempted_office' => $validated['office'],
                ],
            ]);

            return back()
                ->withErrors(['office' => 'The selected office does not match this end-user account.'])
                ->withInput($request->except('password', 'security_code'));
        }

        if (!Auth::attempt(['user_id' => $user->user_id, 'password' => $validated['password']])) {
            AuditLogger::failed('user_login_failed', [
                'module' => 'Authentication',
                'description' => 'Incorrect password login attempt.',
                'auditable' => $user,
                'metadata' => [
                'attempted_user_id' => $user->user_id,
                ],
            ]);

            return back()
                ->withErrors(['password' => 'The provided credentials are incorrect.'])
                ->withInput($request->except('password', 'security_code'));
        }

        $request->session()->regenerate();
        CaptchaController::forget($request);
        AuditLogger::log('Authentication', 'user_login_success', 'User logged in successfully.', $user);

        if ($user->must_change_password) {
            return redirect()
                ->route('profile.show')
                ->with('warning', 'You must change your temporary password before continuing.');
        }

        return redirect()->route($user->dashboardRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLogger::log('Authentication', 'user_logout', 'User logged out successfully.', $request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withHeaders($this->noCacheHeaders());
    }

    public function timeout(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'Your session has expired due to inactivity. Please log in again.';

        AuditLogger::log('Authentication', 'session_timeout', 'User session expired due to inactivity.', $request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('warning', $message);

        if ($request->expectsJson()) {
            return response()
                ->json(['redirect' => route('login')])
                ->withHeaders($this->noCacheHeaders());
        }

        return redirect()
            ->route('login')
            ->withHeaders($this->noCacheHeaders());
    }

    public function switchAccount(Request $request): RedirectResponse
    {
        AuditLogger::log('Authentication', 'user_logout', 'User switched account.', $request->user(), null, null, 'notice', [
            'reason' => 'switch_account',
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withHeaders($this->noCacheHeaders());
    }

    private function noCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    private function roles(): array
    {
        $roleOrder = [
            User::ROLE_ADMIN,
            User::ROLE_HEAD_OFFICE,
            User::ROLE_BAC_SECRETARIAT,
            User::ROLE_BAC_MEMBER,
            User::ROLE_BAC_CHAIR,
            User::ROLE_BAC_VICE_CHAIRPERSON,
            User::ROLE_APPROVING_AUTHORITY,
            User::ROLE_PR_NUMBERING,
        ];
        $hiddenLoginRoles = [
            User::ROLE_BUDGET,
            User::ROLE_ACCOUNTING,
        ];

        return Role::where('status', Role::STATUS_ACTIVE)
            ->where('is_system', true)
            ->where('code', '!=', '099')
            ->where('name', '!=', 'President of the Philippines')
            ->whereNotIn('name', $hiddenLoginRoles)
            ->orderBy('id')
            ->pluck('name')
            ->sortBy(fn (string $role) => array_search($role, $roleOrder, true) === false
                ? PHP_INT_MAX
                : array_search($role, $roleOrder, true))
            ->values()
            ->all();
    }

    private function endUserOffices(): array
    {
        return Office::requesting()
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office) => [
                'value' => (string) $office->id,
                'label' => $office->name,
            ])
            ->all();
    }

    private function officeMatchesUser(User $user, ?string $selectedOffice): bool
    {
        if (!$selectedOffice) {
            return false;
        }

        $office = Office::find($selectedOffice);

        if (!$office) {
            return false;
        }

        return $office->isRequestingOffice()
            && ((int) $user->office_id === $office->id || $user->office === $office->name);
    }
}
