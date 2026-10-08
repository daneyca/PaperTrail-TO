@extends('layouts.dashboard')

@section('title', 'Create Supplier Signing Link | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero pt-smooth-enter">
        <div>
            <p class="eyebrow">Supplier Signature</p>
            <h1>Create Supplier Signing Link</h1>
            <p>Generate a secure external link for supplier-signed document upload and audit tracking.</p>
        </div>

        <a href="{{ route('supplier-signatures.index') }}" class="dashboard-action secondary-action">Back</a>
    </section>

    <section class="table-panel pt-smooth-enter" aria-label="Create supplier signature request">
        @if ($errors->any())
            <div class="session-alert session-alert-error" role="alert">
                Please review the highlighted fields and try again.
            </div>
        @endif

        <form method="POST" action="{{ route('supplier-signatures.store') }}" enctype="multipart/form-data" class="admin-form-grid">
            @csrf

            <div class="admin-form-card">
                <h2>Document Details</h2>
                <div class="admin-form-fields">
                    <label>
                        <span>Document Type</span>
                        <select name="document_type" required>
                            @foreach ($documentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Document Title</span>
                        <input name="document_title" type="text" value="{{ old('document_title', 'Notice of Award') }}" required>
                        @error('document_title')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Tracking / Reference Number</span>
                        <input name="tracking_number" type="text" value="{{ old('tracking_number') }}" placeholder="Optional">
                        @error('tracking_number')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Source Document</span>
                        <input name="source_document" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small>Optional. Upload the document the supplier should sign or acknowledge.</small>
                        @error('source_document')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
            </div>

            <div class="admin-form-card">
                <h2>Supplier Details</h2>
                <div class="admin-form-fields">
                    <label>
                        <span>Supplier / Company Name</span>
                        <input name="supplier_name" type="text" value="{{ old('supplier_name') }}" required>
                        @error('supplier_name')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Supplier Email</span>
                        <input name="supplier_email" type="email" value="{{ old('supplier_email') }}" placeholder="Optional">
                        @error('supplier_email')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Contact Number</span>
                        <input name="supplier_contact" type="text" value="{{ old('supplier_contact') }}" placeholder="Optional">
                        @error('supplier_contact')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Authorized Representative</span>
                        <input name="signatory_name" type="text" value="{{ old('signatory_name') }}" placeholder="Optional">
                        @error('signatory_name')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Representative Position</span>
                        <input name="signatory_position" type="text" value="{{ old('signatory_position') }}" placeholder="Owner / Authorized Representative">
                        @error('signatory_position')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
            </div>

            <div class="admin-form-card admin-form-card--wide">
                <h2>Instructions</h2>
                <div class="admin-form-fields">
                    <label>
                        <span>Link Expiration</span>
                        <input name="expires_at" type="date" value="{{ old('expires_at') }}">
                        @error('expires_at')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Supplier Instructions</span>
                        <textarea name="instructions" rows="5" placeholder="Example: Please upload the signed Notice of Award and make sure the authorized representative signs the conforme section.">{{ old('instructions') }}</textarea>
                        @error('instructions')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
            </div>

            <div class="filter-actions admin-form-actions">
                <button type="submit">Create Supplier Link</button>
                <a href="{{ route('supplier-signatures.index') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
