@csrf

<div class="form-grid">
    <div class="form-field">
        <label for="code">Role Code</label>
        <input id="code" name="code" type="text" value="{{ old('code', $role->code) }}" @readonly($role->exists && $role->is_system) required>
        @error('code')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="name">Role Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}" required>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="dashboard_route">Dashboard Route</label>
        <input id="dashboard_route" name="dashboard_route" type="text" value="{{ old('dashboard_route', $role->dashboard_route) }}">
        @error('dashboard_route')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $role->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $role->status) === 'inactive') @disabled($role->code === 'admin')>Inactive</option>
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field">
        <label for="is_system">Role Type</label>
        <select id="is_system" name="is_system" @disabled($role->exists && $role->is_system)>
            <option value="0" @selected(!old('is_system', $role->is_system))>Custom</option>
            <option value="1" @selected(old('is_system', $role->is_system))>System</option>
        </select>
        @error('is_system')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-field form-field-full">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4">{{ old('description', $role->description) }}</textarea>
        @error('description')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit">{{ $mode === 'create' ? 'Create Role' : 'Save Changes' }}</button>
    <a href="{{ route('admin.roles.index') }}">Cancel</a>
</div>
