@extends('layouts.dashboard')

@section('title', 'E-Signature Setup | PaperTrail')

@section('content')
    @php
        $roleDisplayLabel = function (?string $value): string {
            $value = trim((string) $value);
            $normalized = strtolower(trim(preg_replace('/[\s_\-]+/', ' ', $value) ?? $value));

            return $normalized === 'bac chair' ? 'BAC Chairman' : $value;
        };
        $typedName = old('typed_signature_name', $user->typed_signature_name ?: $user->name);
        $position = old('signer_position', $roleDisplayLabel($user->signer_position ?: ($user->position ?: ($user->assignedRole?->name ?? $user->role))));
        $style = old('signature_style', $user->signature_style ?: 'typed');
        $isComplete = filled($user->typed_signature_name) && filled($user->signer_position);
        $signatureImageVersion = $user->signature_image_path
            ? sha1($user->signature_image_path . '|' . ($user->signature_setup_completed_at?->getTimestamp() ?? $user->updated_at?->getTimestamp() ?? ''))
            : null;
        $signatureImageUrl = $user->signature_image_path
            ? route('profile.signature.image.show', ['user' => $user, 'v' => $signatureImageVersion])
            : null;
    @endphp

    <section class="dashboard-hero admin-users-hero profile-header">
        <div>
            <p class="eyebrow">Account Profile</p>
            <h1>E-Signature Setup</h1>
            <p>Set up the signature you will use when signing documents electronically in PaperTrail.</p>
        </div>

        <a href="{{ route('profile.show') }}" class="dashboard-action secondary-action">Back to Profile</a>
    </section>

    @if ($errors->any())
        <div class="profile-flash error">Please review the highlighted fields and try again.</div>
    @endif

    <section class="signature-settings-card table-panel">
        <div class="signature-settings-header">
            <div>
                <p class="eyebrow">Verified Internal Signature</p>
                <h2>Signature Profile</h2>
                <p>Draw a signature, upload an image, or use your typed name as the fallback signature.</p>
            </div>

            <span class="signature-status-badge {{ $isComplete ? 'is-complete' : 'is-incomplete' }}">
                {{ $isComplete ? 'Complete' : 'Incomplete' }}
            </span>
        </div>

        <form id="signatureSetupForm" method="POST" action="{{ route('profile.signature.update') }}" enctype="multipart/form-data" class="signature-setup-form">
            @csrf

            <div class="signature-form-section">
                <div class="signature-profile-grid">
                    <div class="form-field">
                        <label for="typed_signature_name">Signature Name</label>
                        <input id="typed_signature_name" name="typed_signature_name" type="text" value="{{ $typedName }}" required maxlength="150">
                        @error('typed_signature_name')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-field">
                        <label for="signer_position">Position / Designation</label>
                        <input id="signer_position" name="signer_position" type="text" value="{{ $position }}" required maxlength="150">
                        @error('signer_position')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <input id="signatureStyleInput" type="hidden" name="signature_style" value="{{ $style }}">
            <input id="drawnSignatureDataInput" type="hidden" name="drawn_signature_data" value="">

            <div class="signature-form-section signature-method-section">
                <div class="signature-method-heading">
                    <span>Signature Method</span>
                    <p>Choose how your PaperTrail signature should appear.</p>
                </div>

                <div class="signature-tabs" role="tablist" aria-label="Signature method">
                    <button type="button" class="signature-tab-button {{ $style === 'drawn' ? 'is-active' : '' }}" data-signature-tab="drawn" role="tab" aria-selected="{{ $style === 'drawn' ? 'true' : 'false' }}">Draw Signature</button>
                    <button type="button" class="signature-tab-button {{ $style === 'uploaded' ? 'is-active' : '' }}" data-signature-tab="uploaded" role="tab" aria-selected="{{ $style === 'uploaded' ? 'true' : 'false' }}">Upload PNG / JPG</button>
                    <button type="button" class="signature-tab-button {{ $style === 'typed' ? 'is-active' : '' }}" data-signature-tab="typed" role="tab" aria-selected="{{ $style === 'typed' ? 'true' : 'false' }}">Use Typed Name</button>
                </div>

                <div class="signature-tab-panel {{ $style === 'drawn' ? 'is-active' : '' }}" data-signature-panel="drawn">
                    <p class="signature-method-note">Use your mouse, touchpad, or touchscreen. Clear and redraw until the signature looks correct.</p>
                    <div class="signature-canvas-wrap">
                        <canvas
                            id="signatureCanvas"
                            class="signature-canvas"
                            width="820"
                            height="220"
                            aria-label="Draw signature"
                            data-existing-signature-src="{{ $style === 'drawn' ? $signatureImageUrl : '' }}"
                        ></canvas>
                    </div>
                    <div class="signature-button-row">
                        <button id="clearSignatureCanvasBtn" type="button" class="secondary-action">Clear Drawing</button>
                        <button id="saveSignatureCanvasBtn" type="button">Save Drawing</button>
                        <span class="signature-canvas-status" data-signature-canvas-status aria-live="polite"></span>
                    </div>
                    @error('drawn_signature_data')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="signature-tab-panel {{ $style === 'uploaded' ? 'is-active' : '' }}" data-signature-panel="uploaded">
                    <p class="signature-method-note">Upload a PNG, JPG, or JPEG image up to 2MB. A transparent PNG is preferred.</p>
                    <input id="signatureImageInput" name="signature_image" type="file" accept="image/png,image/jpeg">
                    @error('signature_image')<p class="field-error">{{ $message }}</p>@enderror
                    <div class="signature-preview" aria-live="polite">
                        @if ($signatureImageUrl)
                            <img src="{{ $signatureImageUrl }}" alt="Current signature image">
                        @else
                            <span>No signature image saved yet.</span>
                        @endif
                    </div>
                </div>

                <div class="signature-tab-panel {{ $style === 'typed' ? 'is-active' : '' }}" data-signature-panel="typed">
                    <p class="signature-method-note">Use your typed name when no drawn or uploaded signature image is available.</p>
                    <div class="signature-preview typed-preview" data-typed-signature-preview>{{ $typedName }}</div>
                </div>
            </div>

            <div class="form-actions signature-form-actions">
                <button type="submit">Save Signature Profile</button>
                <a href="{{ route('profile.show') }}">Cancel</a>
            </div>
        </form>

        @if ($user->signature_image_path)
            <form method="POST" action="{{ route('profile.signature.image.destroy') }}" class="signature-remove-form" onsubmit="return confirm('Remove your saved signature image? Typed signature will remain available.');">
                @csrf
                @method('DELETE')
                <button type="submit">Remove Signature Image</button>
            </form>
        @endif
    </section>
@endsection
