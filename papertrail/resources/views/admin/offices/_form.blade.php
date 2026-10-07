@csrf

<div class="form-grid">
    <div class="form-field">
        <label for="code">Office Code</label>
        <input id="code" name="code" type="text" value="{{ old('code', $office->code) }}" required>
        @error('code')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field">
        <label for="name">Office Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $office->name) }}" required>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field">
        <label for="type">Office Type</label>
        <select id="type" name="type" required>
            <option value="">Choose type</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $office->type) === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('type')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $office->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $office->status) === 'inactive')>Inactive</option>
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field form-field-full">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4">{{ old('description', $office->description) }}</textarea>
        @error('description')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field form-field-full">
        <input type="hidden" name="is_requesting_office" value="0">
        <label class="inline-check" for="is_requesting_office">
            <input id="is_requesting_office" name="is_requesting_office" type="checkbox" value="1" @checked(old('is_requesting_office', $office->is_requesting_office ?? false))>
            Eligible as requesting/end-user office
        </label>
        @error('is_requesting_office')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit">{{ $mode === 'create' ? 'Create Office' : 'Save Changes' }}</button>
    <a href="{{ route('admin.offices.index') }}">Cancel</a>
</div>
