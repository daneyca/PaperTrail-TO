<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLinkMail;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        AuditLogger::log('Authentication', 'Forgot Password Page Viewed', 'Forgot password page viewed.');

        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'security_code' => ['required', 'string'],
        ]);

        if (! CaptchaController::matches($request, $validated['security_code'])) {
            AuditLogger::log('Authentication', 'Unauthorized Password Reset Attempt', 'Security code mismatch on forgot password.', null, null, null, 'warning', [
                'attempted_user_id' => strtoupper($validated['user_id']),
            ]);

            return back()
                ->withErrors(['security_code' => 'Invalid security code. Please try again.'])
                ->withInput($request->except('security_code'));
        }

        $user = User::where('user_id', strtoupper($validated['user_id']))->first();

        if (! $user || ! $user->email || strcasecmp($user->email, $validated['email']) !== 0) {
            AuditLogger::log('Authentication', 'Unauthorized Password Reset Attempt', 'Password reset requested with mismatched account details.', null, null, null, 'warning', [
                'attempted_user_id' => strtoupper($validated['user_id']),
            ]);

            return back()
                ->withErrors(['email' => 'The User ID and email address do not match our records.'])
                ->withInput($request->except('security_code'));
        }

        if (! $user->hasVerifiedEmail()) {
            AuditLogger::log('Authentication', 'Password Reset Failed - Email Not Verified', 'Password reset blocked because email is not verified.', $user, null, null, 'warning');

            return back()
                ->withErrors(['email' => 'Please verify your official email before using password recovery.'])
                ->withInput($request->except('security_code'));
        }

        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        Mail::to($user->email)->send(new PasswordResetLinkMail($user, $url));

        AuditLogger::log('Authentication', 'Password Reset Link Requested', 'User requested a password reset link.', $user);

        return back()->with('status', 'A password reset link has been sent to your verified email address.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Password::broker()->tokenExists($user, $validated['token'])) {
            AuditLogger::log('Authentication', 'Password Reset Failed', 'Invalid or expired reset link used.', $user, null, null, 'warning');

            return back()
                ->withErrors(['email' => 'Invalid or expired reset link. Please request a new one.'])
                ->withInput($request->only('email'));
        }

        $updates = [
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ];

        if (Schema::hasColumn('users', 'last_password_changed_at')) {
            $updates['last_password_changed_at'] = now();
        }

        $user->update($updates);
        Password::broker()->deleteToken($user);

        SystemNotificationService::notify(
            $user,
            'Password Reset',
            'Your password was changed successfully.',
            SystemNotification::TYPE_SUCCESS,
            'Security',
        );

        AuditLogger::log('Authentication', 'Password Reset Completed', 'User completed password reset.', $user, null, null, 'warning');

        return redirect()
            ->route('login')
            ->with('status', 'Password reset successful. You may now sign in.');
    }
}
