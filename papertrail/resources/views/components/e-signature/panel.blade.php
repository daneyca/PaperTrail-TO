@php
    $existingSignature = $existingSignature ?? null;
    $signedSignature = $signedSignature ?? ($existingSignature?->isValid() ? $existingSignature : null);
    $canSign = (bool) ($canSign ?? false);
    $signatureProfileComplete = (bool) ($signatureProfileComplete ?? auth()->user()?->hasSignatureProfile());
    $actionLabel = $actionLabel ?? 'Confirm / Sign';
    $signatureAction = $signatureAction ?? 'confirmed';
    $componentKey = 'esignature_' . substr(md5(($sendCodeRoute ?? '') . ($signRoute ?? '') . ($declineRoute ?? '') . ($panelTitle ?? '')), 0, 10);
    $panelIcon = $panelIcon ?? 'signature';
    $showPanelIcon = (bool) ($showPanelIcon ?? false);
    $panelDescription = $panelDescription ?? 'Electronically sign this document through the verified internal PaperTrail workflow.';
    $openSignLabel = $openSignLabel ?? 'Sign Document';
    $openDeclineLabel = $openDeclineLabel ?? 'Return / Decline';
    $compactHelpText = $compactHelpText ?? 'Open the secure signing window when you are ready to enter your password and signing code.';
    $signModalId = $componentKey . '_sign_modal';
    $returnModalId = $componentKey . '_return_modal';
    $passwordInputId = $componentKey . '_current_password';
    $codeInputId = $componentKey . '_signing_code';
    $remarksInputId = $componentKey . '_signature_remarks';
    $consentInputId = $componentKey . '_signature_consent';
    $declineReasonId = $componentKey . '_decline_reason';
    $hasSignatureErrors = $errors->has('current_password') || $errors->has('signing_code') || $errors->has('signature_consent');
    $hasDeclineErrors = $errors->has('reason');
@endphp

<section class="e-signature-panel" aria-label="Electronic signature panel">
    <div class="e-signature-panel__header">
        @if ($showPanelIcon)
            <span class="e-signature-panel__icon" aria-hidden="true">
                <x-papertrail.icon :name="$panelIcon" />
            </span>
        @endif

        <div class="e-signature-panel__heading">
            <p class="eyebrow">E-Signature</p>
            <h2>{{ $panelTitle ?? 'PaperTrail Signature' }}</h2>
            <p>{{ $panelDescription }}</p>
        </div>

        @if ($signedSignature)
            <span class="signature-status-badge is-signed">Signed</span>
        @elseif ($existingSignature)
            <span class="signature-status-badge is-pending">{{ str($existingSignature->signature_status)->title() }}</span>
        @else
            <span class="signature-status-badge is-pending">Pending</span>
        @endif
    </div>

    <div class="e-signature-panel__body">
        @if (! $signatureProfileComplete)
            <div class="e-signature-warning">
                <strong>Please complete your E-Signature Setup in your Profile before signing.</strong>
                <p>Typed signature name and position/designation are required. Signature image is optional.</p>
            </div>
            <a href="{{ route('profile.signature.edit') }}">Go to Signature Settings</a>
        @elseif ($signedSignature)
            <div class="signature-panel-status">
                <strong>Document signed</strong>
                <dl>
                    <div>
                        <dt>Signed By</dt>
                        <dd>{{ $signedSignature->signer_name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt>Date Signed</dt>
                        <dd>{{ $signedSignature->signed_at?->format('M d, Y h:i A') ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt>Signature ID</dt>
                        <dd>{{ $signedSignature->signature_code ?? 'N/A' }}</dd>
                    </div>
                </dl>
                <a href="{{ route('e-signatures.certificate', $signedSignature) }}">View Signature Certificate</a>
            </div>
        @elseif ($canSign)
            <div class="e-signature-compact-actions">
                <button type="button" class="e-signature-open-button" data-e-signature-open="{{ $signModalId }}">
                    {{ $openSignLabel }}
                </button>

                @if (! empty($declineRoute))
                    <button type="button" class="e-signature-open-button e-signature-open-button--secondary" data-e-signature-open="{{ $returnModalId }}">
                        {{ $openDeclineLabel }}
                    </button>
                @endif

                <p>{{ $compactHelpText }}</p>
            </div>

            <div id="{{ $signModalId }}" class="e-signature-modal" data-e-signature-modal @unless ($hasSignatureErrors) hidden @endunless>
                <div class="e-signature-modal__backdrop" data-e-signature-close></div>
                <section class="e-signature-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $componentKey }}_sign_title">
                    <header class="e-signature-modal__header">
                        <div>
                            <p class="eyebrow">E-Signature</p>
                            <h2 id="{{ $componentKey }}_sign_title">{{ $panelTitle ?? 'Signature Confirmation' }}</h2>
                        </div>
                        <button type="button" class="e-signature-modal__close" data-e-signature-close aria-label="Close signature form">&times;</button>
                    </header>

                    <div class="e-signature-modal__body e-signature-modal__body--sign">
                        <form method="POST" action="{{ $sendCodeRoute }}" class="e-signature-form e-signature-code-form">
                            @csrf
                            <input type="hidden" name="signature_action" value="{{ $signatureAction }}">
                            <div>
                                <strong>1. Get signing code</strong>
                                <span>Send a one-time 6-digit code before confirming.</span>
                            </div>
                            <button type="submit">Send Code</button>
                        </form>

                        <form method="POST" action="{{ $signRoute }}" class="e-signature-form">
                            @csrf
                            <input type="hidden" name="signature_action" value="{{ $signatureAction }}">

                            <div class="e-signature-step">
                                <strong>2. Confirm identity</strong>
                                <div class="e-signature-field-grid">
                                    <div>
                                        <label for="{{ $passwordInputId }}">Password</label>
                                        <div class="e-signature-password-field">
                                            <input id="{{ $passwordInputId }}" name="current_password" type="password" autocomplete="current-password" required data-e-signature-focus>
                                            <button
                                                type="button"
                                                class="e-signature-password-toggle"
                                                data-e-signature-password-toggle="{{ $passwordInputId }}"
                                                data-password-visible="false"
                                                aria-label="Show password"
                                                title="Show password"
                                            >
                                                <svg class="e-signature-password-icon e-signature-password-icon--show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                <svg class="e-signature-password-icon e-signature-password-icon--hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M3 3l18 18" />
                                                    <path d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6" />
                                                    <path d="M7.1 7.1C4.2 8.6 2.5 12 2.5 12s3.5 6 9.5 6c1.4 0 2.7-.3 3.8-.8" />
                                                    <path d="M17.4 14.9C20 13.3 21.5 12 21.5 12s-3.5-6-9.5-6c-.9 0-1.8.1-2.6.4" />
                                                </svg>
                                            </button>
                                        </div>
                                        @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label for="{{ $codeInputId }}">6-Digit Code</label>
                                        <input id="{{ $codeInputId }}" name="signing_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                                        @error('signing_code')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="e-signature-step">
                                <label for="{{ $remarksInputId }}">Remarks</label>
                                <textarea id="{{ $remarksInputId }}" name="remarks" rows="2" placeholder="Optional remarks for this signature.">{{ old('remarks') }}</textarea>
                            </div>

                            <div class="e-signature-modal__footer">
                                <label class="signature-consent" for="{{ $consentInputId }}">
                                    <input id="{{ $consentInputId }}" type="checkbox" name="signature_consent" value="1" required>
                                    <span>I reviewed this document and approve/sign it electronically.</span>
                                </label>
                                @error('signature_consent')<p class="field-error">{{ $message }}</p>@enderror

                                <button type="submit">{{ $actionLabel }}</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>

            @if (! empty($declineRoute))
                <div id="{{ $returnModalId }}" class="e-signature-modal" data-e-signature-modal @unless ($hasDeclineErrors) hidden @endunless>
                    <div class="e-signature-modal__backdrop" data-e-signature-close></div>
                    <section class="e-signature-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $componentKey }}_return_title">
                        <header class="e-signature-modal__header">
                            <div>
                                <p class="eyebrow">Return Document</p>
                                <h2 id="{{ $componentKey }}_return_title">Decline / Return</h2>
                                <span>Explain what must be corrected before this document can be signed.</span>
                            </div>
                            <button type="button" class="e-signature-modal__close" data-e-signature-close aria-label="Close return form">&times;</button>
                        </header>

                        <div class="e-signature-modal__body">
                            <form method="POST" action="{{ $declineRoute }}" class="e-signature-form">
                                @csrf
                                <input type="hidden" name="signature_action" value="{{ $signatureAction }}">
                                <label for="{{ $declineReasonId }}">Decline / Return Reason</label>
                                <textarea id="{{ $declineReasonId }}" name="reason" rows="4" required placeholder="Explain what must be corrected before signing." data-e-signature-focus>{{ old('reason') }}</textarea>
                                @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                                <button type="submit" class="danger-action">Submit Return</button>
                            </form>
                        </div>
                    </section>
                </div>
            @endif
        @else
            <div class="empty-state">
                <strong>No signature action available</strong>
                <p>This document is not currently in a status that can be electronically signed by your account.</p>
            </div>
        @endif
    </div>
</section>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', (event) => {
                const passwordToggle = event.target.closest('[data-e-signature-password-toggle]');
                if (passwordToggle) {
                    const passwordInput = document.getElementById(passwordToggle.dataset.eSignaturePasswordToggle);
                    if (!passwordInput) return;

                    const shouldShow = passwordInput.type === 'password';
                    passwordInput.type = shouldShow ? 'text' : 'password';
                    passwordToggle.dataset.passwordVisible = shouldShow ? 'true' : 'false';
                    passwordToggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
                    passwordToggle.setAttribute('title', shouldShow ? 'Hide password' : 'Show password');
                    passwordInput.focus();
                    return;
                }

                const openButton = event.target.closest('[data-e-signature-open]');
                if (openButton) {
                    const modal = document.getElementById(openButton.dataset.eSignatureOpen);
                    if (!modal) return;

                    modal.hidden = false;
                    window.setTimeout(() => {
                        const focusTarget = modal.querySelector('[data-e-signature-focus], input:not([type="hidden"]), textarea, button');
                        focusTarget?.focus();
                    }, 50);
                    return;
                }

                const closeButton = event.target.closest('[data-e-signature-close]');
                if (closeButton) {
                    const modal = closeButton.closest('[data-e-signature-modal]');
                    if (modal) {
                        modal.hidden = true;
                    }
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                document.querySelectorAll('[data-e-signature-modal]:not([hidden])').forEach((modal) => {
                    modal.hidden = true;
                });
            });
        </script>
    @endpush
@endonce
