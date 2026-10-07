@csrf
@if ($app->exists)
    @method('PATCH')
@endif

<div class="form-grid">
    <div>
        <label for="fiscal_year">Fiscal Year</label>
        <input id="fiscal_year" name="fiscal_year" type="number" min="2020" max="2100" value="{{ old('fiscal_year', $app->fiscal_year) }}" required>
        @error('fiscal_year')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $app->title) }}" required>
        @error('title')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<label for="description">Description</label>
<textarea id="description" name="description" rows="4">{{ old('description', $app->description) }}</textarea>
@error('description')<p class="field-error">{{ $message }}</p>@enderror

<label for="remarks">Remarks</label>
<textarea id="remarks" name="remarks" rows="3">{{ old('remarks', $app->remarks) }}</textarea>
@error('remarks')<p class="field-error">{{ $message }}</p>@enderror

<div class="form-actions">
    <button type="submit">{{ $app->exists ? 'Save Changes' : 'Create APP Draft' }}</button>
    <a href="{{ $app->exists ? route('bac-secretariat.app.show', $app) : route('bac-secretariat.app.index') }}">Cancel</a>
</div>
