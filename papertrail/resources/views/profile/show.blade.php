@extends('layouts.dashboard')

@section('title', 'Profile | PaperTrail')

@section('content')
    @php
        $officeName = trim((string) ($user->assignedOffice?->name ?? $user->office ?? ''));
        $roleDisplayLabel = function (?string $value): string {
            $value = trim((string) $value);
            $normalized = strtolower(trim(preg_replace('/[\s_\-]+/', ' ', $value) ?? $value));

            return $normalized === 'bac chair' ? 'BAC Chairman' : $value;
        };
        $displayUserRole = $roleDisplayLabel($user->role);
        $displayOffice = $officeName !== '' ? $officeName : 'No office assigned';
        $hasPendingEmailCode = $user->email
            && ! $user->email_verified_at
            && $user->email_verification_code_hash
            && $user->email_verification_code_expires_at
            && $user->email_verification_code_expires_at->isFuture();
        $passwordModalOpen = request()->boolean('password') || $errors->has('current_password') || $errors->has('password') || $errors->has('password_confirmation');
        $emailRecoveryModalOpen = request()->boolean('email_recovery') || $errors->has('verification_code');
    @endphp

    <section class="dashboard-hero admin-users-hero profile-header">
        <div>
            <p class="eyebrow">Account Profile</p>
            <h1>{{ $user->name }}</h1>
            <p>Manage your account information and profile details.</p>
        </div>
        <div class="hero-actions">
            <a
                href="{{ route('profile.edit') }}"
                class="dashboard-action profile-edit-header-button pt-action-button--labeled"
                data-action-icon="false"
            >
                Edit Profile
            </a>
        </div>
    </section>

    @if (session('warning'))
        <div class="profile-flash warning">{{ session('warning') }}</div>
    @endif

    @if (session('error'))
        <div class="profile-flash error">{{ session('error') }}</div>
    @endif

    @if ($user->email && ! $user->email_verified_at)
        <div class="profile-flash warning">
            Your email is not verified. Verify your official email to enable password recovery.
        </div>
    @elseif (! $user->email)
        <div class="profile-flash warning">
            No official email address is linked to this account. Please update your profile first.
        </div>
    @endif

    <section class="profile-layout profile-layout--details-only">
        <div class="profile-main-column">
            <article class="table-panel profile-info-card">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Account Information</p>
                        <h2>Profile Details</h2>
                        <p>Read-only account identifiers and editable contact details.</p>
                    </div>
                </div>

                <div class="profile-detail-list">
                    <div><span>User ID</span><strong>{{ $user->user_id }}</strong></div>
                    <div><span>Full Name</span><strong>{{ $user->name }}</strong></div>
                    <div><span>Role</span><strong>{{ $displayUserRole }}</strong></div>
                    <div><span>Office</span><strong>{{ $displayOffice }}</strong></div>
                    <div><span>Status</span><strong>{{ ucfirst($user->status) }}</strong></div>
                    <div><span>Official Email</span><strong>{{ $user->email ?? 'Not set' }}</strong></div>
                    <div>
                        <span>Email Verification</span>
                        <strong>
                            @if ($user->email && $user->email_verified_at)
                                Verified
                            @else
                                Not Verified
                            @endif
                        </strong>
                    </div>
                    <div><span>Verified Date</span><strong>{{ $user->email_verified_at?->format('M d, Y h:i A') ?? 'Not verified' }}</strong></div>
                    <div><span>Contact Number</span><strong>{{ $user->contact_number ?? 'Not set' }}</strong></div>
                    <div><span>Position</span><strong>{{ $user->position ?? 'Not set' }}</strong></div>
                    <div><span>Last Updated</span><strong>{{ $user->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                    <div><span>Last Password Changed</span><strong>{{ $user->last_password_changed_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</strong></div>
                </div>
            </article>

            <article class="table-panel profile-activity-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Audit Trail</p>
                        <h2>Recent Activity</h2>
                        <p>Your latest recorded system actions.</p>
                    </div>
                </div>

                <div class="profile-activity-list">
                    @forelse ($recentActivity as $activity)
                        <div class="profile-activity-row">
                            <strong>{{ $activity->action }}</strong>
                            <span>
                                {{ $activity->module }}
                                <b class="severity-badge severity-{{ $activity->severity }}">{{ ucfirst($activity->severity) }}</b>
                                {{ $activity->created_at?->format('M d, Y h:i A') }}
                            </span>
                            @if ($activity->description)
                                <p>{{ $activity->description }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">
                            <strong>No recent activity yet.</strong>
                            <p>Your audit activity will appear here after system actions are recorded.</p>
                        </div>
                    @endforelse
                </div>
            </article>

        </div>

    </section>

    <div
        class="profile-password-modal {{ $passwordModalOpen ? 'is-open' : '' }}"
        data-profile-password-modal
        @unless ($passwordModalOpen) hidden @endunless
    >
        <button type="button" class="profile-password-modal__backdrop" data-profile-password-close aria-label="Close change password dialog"></button>

        <section class="profile-password-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="profile-password-modal-title">
            <header class="profile-password-modal__header">
                <div>
                    <p class="eyebrow">Security</p>
                    <h2 id="profile-password-modal-title">{{ $user->must_change_password ? 'Set Password' : 'Change Password' }}</h2>
                    <p>Use at least 8 characters. Password values are never logged.</p>
                </div>
                <button type="button" class="profile-password-modal__close" data-profile-password-close aria-label="Close modal">
                    <x-papertrail.icon name="close" />
                </button>
            </header>

            <form method="POST" action="{{ route('profile.password.update') }}" class="profile-password-form profile-password-form--modal">
                @csrf
                @method('PATCH')

                <label for="current_password">Current Password</label>
                <input id="current_password" name="current_password" type="password" required>
                @error('current_password')<p class="field-error">{{ $message }}</p>@enderror

                <label for="password">New Password</label>
                <input id="password" name="password" type="password" required>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror

                <label for="password_confirmation">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required>
                @error('password_confirmation')<p class="field-error">{{ $message }}</p>@enderror

                <footer class="profile-password-modal__actions">
                    <button type="button" class="secondary-action" data-profile-password-close>Cancel</button>
                    <button type="submit">Update Password</button>
                </footer>
            </form>
        </section>
    </div>

    <div
        class="profile-password-modal profile-email-recovery-modal {{ $emailRecoveryModalOpen ? 'is-open' : '' }}"
        data-profile-email-recovery-modal
        @unless ($emailRecoveryModalOpen) hidden @endunless
    >
        <button type="button" class="profile-password-modal__backdrop" data-profile-email-recovery-close aria-label="Close email recovery dialog"></button>

        <section class="profile-password-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="profile-email-recovery-title">
            <header class="profile-password-modal__header">
                <div>
                    <p class="eyebrow">Recovery</p>
                    <h2 id="profile-email-recovery-title">Email Recovery</h2>
                    <p>Use your official email for password recovery and verification codes.</p>
                </div>
                <button type="button" class="profile-password-modal__close" data-profile-email-recovery-close aria-label="Close modal">
                    <x-papertrail.icon name="close" />
                </button>
            </header>

            <div class="email-verification-panel">
                <strong>Email Recovery</strong>
                <p>
                    @if ($user->email && $user->email_verified_at)
                        Your official email address is verified.
                    @elseif ($user->email)
                        Enter the 6-digit code sent to your official email. The code expires in 10 minutes.
                    @else
                        No official email address is linked to this account. Please update your profile first.
                    @endif
                </p>

                @if ($user->email && ! $user->email_verified_at)
                    <form method="POST" action="{{ route('verification.code.send') }}">
                        @csrf
                        <button type="submit">Send Verification Code</button>
                    </form>

                    @if ($hasPendingEmailCode)
                        <form method="POST" action="{{ route('verification.code.verify') }}" class="email-verification-code-form">
                            @csrf
                            <label for="verification_code">Verification Code</label>
                            <input
                                id="verification_code"
                                name="verification_code"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autocomplete="one-time-code"
                                value="{{ old('verification_code') }}"
                                placeholder="6-digit code"
                                required
                            >
                            @error('verification_code')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                            <button type="submit">Verify Code</button>
                        </form>
                    @endif
                @endif
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const bindModal = (modalSelector, closeSelector, focusSelector) => {
                const modal = document.querySelector(modalSelector);
                const closeButtons = document.querySelectorAll(closeSelector);
                const firstInput = focusSelector ? modal?.querySelector(focusSelector) : null;

                if (!modal) {
                    return;
                }

                const setOpen = (open) => {
                    modal.hidden = !open;
                    modal.classList.toggle('is-open', open);
                    document.body.classList.toggle('profile-password-modal-open', document.querySelector('.profile-password-modal.is-open') !== null);

                    if (open) {
                        window.setTimeout(() => firstInput?.focus(), 80);
                    }
                };

                closeButtons.forEach((button) => {
                    button.addEventListener('click', () => setOpen(false));
                });

                if (modal.classList.contains('is-open')) {
                    document.body.classList.add('profile-password-modal-open');
                    window.setTimeout(() => firstInput?.focus(), 80);
                }
            };

            bindModal('[data-profile-password-modal]', '[data-profile-password-close]', '#current_password');
            bindModal('[data-profile-email-recovery-modal]', '[data-profile-email-recovery-close]', '#verification_code');

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                document.querySelectorAll('.profile-password-modal.is-open').forEach((modal) => {
                    modal.hidden = true;
                    modal.classList.remove('is-open');
                });

                document.body.classList.remove('profile-password-modal-open');
            });
        })();
    </script>
@endpush
