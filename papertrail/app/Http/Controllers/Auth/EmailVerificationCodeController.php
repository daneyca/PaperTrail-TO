<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailVerificationCodeController extends Controller
{
    private const EXPIRES_IN_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function send(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->email) {
            return back()->with('error', 'Please add an official email address before sending a verification code.');
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'Your official email address is already verified.');
        }

        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_verification_code_hash' => Hash::make($code),
            'email_verification_code_sent_at' => now(),
            'email_verification_code_expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            'email_verification_attempts' => 0,
        ])->save();

        try {
            Mail::raw($this->verificationCodeMessage($user, $code), function ($message) use ($user) {
                $message
                    ->to($user->email, $user->name)
                    ->subject('PaperTrail Email Verification Code');
            });
        } catch (Throwable $exception) {
            $this->clearCode($user);

            Log::error('PaperTrail email verification code send failed.', [
                'user_id' => $user->id,
                'user_identifier' => $user->user_id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            AuditLogger::log(
                'Profile',
                'email_notification_failed',
                'Email verification code could not be sent.',
                $user,
                null,
                null,
                'warning',
                ['email' => $user->email, 'mailer' => config('mail.default')]
            );

            return back()->with('error', 'Unable to send verification code. Please contact the administrator.');
        }

        AuditLogger::log(
            'Profile',
            'email_verification_code_requested',
            'User requested an official email verification code.',
            $user,
            null,
            null,
            'info',
            [
                'email' => $user->email,
                'expires_at' => $user->email_verification_code_expires_at?->toDateTimeString(),
            ]
        );

        return back()->with('status', 'Verification code sent to your official email address.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'verification_code' => ['required', 'digits:6'],
        ]);

        /** @var User $user */
        $user = $request->user()->fresh();

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'Your official email address is already verified.');
        }

        if (! $user->email_verification_code_hash) {
            return back()->with('error', 'Please request a verification code first.');
        }

        if (! $user->email_verification_code_expires_at || $user->email_verification_code_expires_at->isPast()) {
            $this->clearCode($user);

            AuditLogger::log(
                'Profile',
                'email_verification_code_expired',
                'Email verification code expired before it was used.',
                $user,
                null,
                null,
                'warning',
                ['email' => $user->email]
            );

            return back()->with('error', 'Verification code expired. Please request a new code.');
        }

        if ((int) $user->email_verification_attempts >= self::MAX_ATTEMPTS) {
            $this->clearCode($user);

            AuditLogger::log(
                'Profile',
                'email_verification_code_locked',
                'Email verification code was locked after too many invalid attempts.',
                $user,
                null,
                null,
                'warning',
                ['email' => $user->email]
            );

            return back()->with('error', 'Too many invalid attempts. Please request a new verification code.');
        }

        if (! Hash::check($validated['verification_code'], $user->email_verification_code_hash)) {
            $attempts = (int) $user->email_verification_attempts + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->clearCode($user);

                AuditLogger::log(
                    'Profile',
                    'email_verification_code_locked',
                    'Email verification code was locked after too many invalid attempts.',
                    $user,
                    null,
                    null,
                    'warning',
                    ['email' => $user->email]
                );

                return back()->with('error', 'Too many invalid attempts. Please request a new verification code.');
            }

            $user->forceFill(['email_verification_attempts' => $attempts])->save();

            AuditLogger::log(
                'Profile',
                'email_verification_code_invalid_attempt',
                'Invalid email verification code was entered.',
                $user,
                null,
                null,
                'warning',
                ['email' => $user->email, 'attempts' => $attempts]
            );

            return back()
                ->withInput($request->only('verification_code'))
                ->with('error', 'Invalid verification code. Please try again.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            SystemNotificationService::notify(
                $user,
                'Email Verified',
                'Your official email address has been verified.',
                SystemNotification::TYPE_SUCCESS,
                'Profile',
            );

            AuditLogger::log(
                'Profile',
                'email_verified',
                'User verified official email address with a verification code.',
                $user,
                null,
                null,
                'info',
                ['email' => $user->email]
            );
        }

        return back()->with('status', 'Your official email address has been verified successfully.');
    }

    private function clearCode(User $user): void
    {
        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_code_sent_at' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    private function verificationCodeMessage(User $user, string $code): string
    {
        return implode(PHP_EOL . PHP_EOL, [
            'Hello ' . ($user->name ?: 'PaperTrail user') . ',',
            'Your PaperTrail email verification code is:',
            $code,
            'This code will expire in ' . self::EXPIRES_IN_MINUTES . ' minutes.',
            'If you did not request this code, please ignore this email.',
            'PaperTrail - LGU Tomas Oppus',
        ]);
    }
}
