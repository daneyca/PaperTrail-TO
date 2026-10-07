@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Edit Profile | PaperTrail')

@section('content')
    @php
        $roleDisplayLabel = function (?string $value): string {
            $value = trim((string) $value);
            $normalized = strtolower(trim(preg_replace('/[\s_\-]+/', ' ', $value) ?? $value));

            return $normalized === 'bac chair' ? 'BAC Chairman' : $value;
        };
        $displayOffice = $user->assignedOffice?->name ?? $user->office ?? 'N/A';
        $displayUserRole = $roleDisplayLabel($user->role);
    @endphp

    @unless (request()->boolean('modal'))
        <section class="dashboard-hero admin-users-hero profile-header">
            <div>
                <p class="eyebrow">Account Profile</p>
                <h1>Edit Profile</h1>
                <p>Update your personal details. User ID, role, office, and status are managed by the administrator.</p>
            </div>

            <a href="{{ route('profile.show') }}" class="dashboard-action secondary-action">Cancel</a>
        </section>
    @endunless

    @if ($errors->any())
        <div class="profile-flash error">Please review the highlighted fields and try again.</div>
    @endif

    <section class="form-panel profile-edit-panel profile-edit-panel--compact">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="profile-edit-form profile-edit-form--compact">
            @csrf
            @method('PATCH')

            <div class="profile-readonly-strip">
                <div><span>User ID</span><strong>{{ $user->user_id }}</strong></div>
                <div><span>Role</span><strong>{{ $displayUserRole }}</strong></div>
                <div><span>Office</span><strong>{{ $displayOffice }}</strong></div>
                <div><span>Status</span><strong>{{ ucfirst($user->status) }}</strong></div>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="name">Full Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="contact_number">Contact Number</label>
                    <input id="contact_number" name="contact_number" type="text" value="{{ old('contact_number', $user->contact_number) }}">
                    @error('contact_number')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="position">Position</label>
                    <input id="position" name="position" type="text" value="{{ old('position', $user->position) }}">
                    @error('position')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field profile-photo-field">
                    <label for="profile_photo">Profile Photo</label>
                    @if ($user->profile_photo_path)
                        <div class="profile-photo-current">
                            <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="{{ $user->name }}">
                            <span>Current profile photo</span>
                        </div>
                    @endif
                    <input id="profile_photo" name="profile_photo" type="file" accept="image/*">
                    <small>JPG, JPEG, PNG, or WebP. Maximum 2MB.</small>
                    @error('profile_photo')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Save Changes</button>
                <a href="{{ route('profile.show') }}">Cancel</a>
            </div>
        </form>

        @if ($user->profile_photo_path && ! request()->boolean('modal'))
            <form method="POST" action="{{ route('profile.photo.destroy') }}" class="profile-photo-remove-form" onsubmit="return confirm('Remove your profile photo?');">
                @csrf
                @method('DELETE')
                <button type="submit">Remove Profile Photo</button>
            </form>
        @endif
    </section>
@endsection
