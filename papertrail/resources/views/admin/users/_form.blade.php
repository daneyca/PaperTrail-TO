@csrf

<div class="form-grid">
    <div class="form-field">
        <label for="user_id">User ID</label>
        <input id="user_id" name="user_id" type="text" value="{{ old('user_id', $user->user_id) }}" required>
        @error('user_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field">
        <label for="name">Full Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" placeholder="official.email@lgu.gov.ph">
        @error('email')<p class="field-error">{{ $message }}</p>@enderror
        @if ($mode === 'edit' && $user->email && ! $user->email_verified_at)
            <small>Changing email keeps the account unverified until the user verifies it.</small>
        @endif
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

    <div class="form-field">
        <label for="office_id">Office</label>
        <select id="office_id" name="office_id" required>
            <option value="">Choose office</option>
            @foreach ($offices as $office)
                <option value="{{ $office->id }}" data-requesting-office="{{ $office->is_requesting_office ? '1' : '0' }}" @selected((int) old('office_id', $user->office_id) === $office->id)>{{ $office->name }} @if($office->status === 'inactive') (Inactive) @endif @unless($office->is_requesting_office) (Not Requesting) @endunless</option>
            @endforeach
        </select>
        @error('office_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field">
        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required>
            <option value="">Choose role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" data-role-name="{{ $role->name }}" @selected((int) old('role_id', $user->role_id) === $role->id)>{{ $role->name }} @if($role->status === 'inactive') (Inactive) @endif</option>
            @endforeach
        </select>
        @error('role_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    @if ($mode === 'create')
        <div class="form-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="form-field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit">{{ $mode === 'create' ? 'Create Account' : 'Save Changes' }}</button>
    <a href="{{ route('admin.users.index') }}">Cancel</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.getElementById('role_id');
    const officeSelect = document.getElementById('office_id');

    if (!roleSelect || !officeSelect) {
        return;
    }

    const syncOfficeEligibility = () => {
        const selectedRole = roleSelect.options[roleSelect.selectedIndex]?.dataset.roleName || '';
        const requiresRequestingOffice = selectedRole === 'Head of Office / End User';

        Array.from(officeSelect.options).forEach((option) => {
            if (!option.value) {
                return;
            }

            const isRequestingOffice = option.dataset.requestingOffice === '1';
            option.disabled = requiresRequestingOffice && !isRequestingOffice;
        });

        const selectedOffice = officeSelect.options[officeSelect.selectedIndex];

        if (selectedOffice?.disabled) {
            officeSelect.value = '';
        }
    };

    roleSelect.addEventListener('change', syncOfficeEligibility);
    syncOfficeEligibility();
});
</script>
