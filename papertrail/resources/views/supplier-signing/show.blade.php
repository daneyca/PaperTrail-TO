<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Supplier Signing | PaperTrail</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f7fb;
            color: #0b2341;
            font-family: "Plus Jakarta Sans", Arial, sans-serif;
        }

        .supplier-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 30px 18px 48px;
        }

        .supplier-card {
            border: 1px solid #d8e2ef;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 18px 46px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }

        .supplier-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 24px;
            border-bottom: 1px solid #e5edf6;
        }

        .supplier-header img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .supplier-header p {
            margin: 0;
            color: #52627a;
            font-weight: 700;
        }

        .supplier-header h1 {
            margin: 3px 0 0;
            font-size: clamp(1.35rem, 4vw, 2rem);
        }

        .supplier-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 0.8fr);
            gap: 20px;
            padding: 24px;
        }

        .supplier-section {
            display: grid;
            gap: 14px;
        }

        .supplier-info {
            display: grid;
            gap: 9px;
            margin: 0;
        }

        .supplier-info div {
            border: 1px solid #e5edf6;
            border-radius: 8px;
            padding: 11px 12px;
            background: #f8fbff;
        }

        .supplier-info dt {
            color: #60708a;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .supplier-info dd {
            margin: 2px 0 0;
            font-weight: 800;
        }

        .supplier-alert {
            border-radius: 8px;
            padding: 12px 14px;
            background: #e8f7ee;
            color: #057046;
            font-weight: 800;
        }

        .supplier-alert--error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .supplier-form {
            display: grid;
            gap: 13px;
        }

        .supplier-form label {
            display: grid;
            gap: 6px;
            color: #0b2341;
            font-weight: 800;
        }

        .supplier-form input,
        .supplier-form textarea {
            width: 100%;
            border: 1px solid #cbd7e6;
            border-radius: 8px;
            padding: 10px 12px;
            font: inherit;
        }

        .supplier-form small {
            color: #64748b;
            font-weight: 600;
        }

        .supplier-check {
            display: grid;
            grid-template-columns: 18px minmax(0, 1fr);
            gap: 9px;
            align-items: start;
            font-weight: 700;
            line-height: 1.35;
        }

        .supplier-check input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
        }

        .supplier-button,
        .supplier-link {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            border: 1px solid #0b2341;
            border-radius: 8px;
            padding: 0 15px;
            background: #0b2341;
            color: #fff;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
        }

        .supplier-link {
            width: max-content;
            border-color: #cbd7e6;
            background: #fff;
            color: #0b2341;
        }

        @media (max-width: 820px) {
            .supplier-body {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="supplier-shell">
        <section class="supplier-card">
            <header class="supplier-header">
                <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Tomas Oppus seal">
                <div>
                    <p>PaperTrail Supplier Signing Portal</p>
                    <h1>{{ $supplierRequest->document_title }}</h1>
                </div>
            </header>

            <div class="supplier-body">
                <section class="supplier-section">
                    @if (session('status'))
                        <div class="supplier-alert">{{ session('status') }}</div>
                    @endif

                    @if (session('error'))
                        <div class="supplier-alert supplier-alert--error">{{ session('error') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="supplier-alert supplier-alert--error">Please review the form and try again.</div>
                    @endif

                    <dl class="supplier-info">
                        <div>
                            <dt>Supplier</dt>
                            <dd>{{ $supplierRequest->supplier_name }}</dd>
                        </div>
                        <div>
                            <dt>Reference</dt>
                            <dd>{{ $supplierRequest->tracking_number ?: 'No reference number provided' }}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>{{ $supplierRequest->statusLabel() }}</dd>
                        </div>
                        <div>
                            <dt>Instructions</dt>
                            <dd>{{ $supplierRequest->instructions ?: 'Upload the signed copy of the document for BAC Secretariat validation.' }}</dd>
                        </div>
                    </dl>

                    @if ($supplierRequest->hasSourceDocument())
                        <a class="supplier-link" href="{{ route('supplier-signing.source-document', $supplierRequest->public_token) }}">Download Source Document</a>
                    @endif
                </section>

                <section class="supplier-section">
                    @if ($supplierRequest->status === \App\Models\SupplierSignatureRequest::STATUS_SUBMITTED)
                        <div class="supplier-alert">Your signed document has been submitted and is waiting for BAC validation.</div>
                    @elseif ($supplierRequest->status === \App\Models\SupplierSignatureRequest::STATUS_ACCEPTED)
                        <div class="supplier-alert">This signed document has been accepted by BAC Secretariat.</div>
                    @elseif (! $supplierRequest->isOpenForSupplier())
                        <div class="supplier-alert supplier-alert--error">This signing link is closed. Please contact BAC Secretariat for assistance.</div>
                    @else
                        <form method="POST" action="{{ route('supplier-signing.submit', $supplierRequest->public_token) }}" enctype="multipart/form-data" class="supplier-form">
                            @csrf

                            <label>
                                <span>Authorized Representative</span>
                                <input name="signatory_name" type="text" value="{{ old('signatory_name', $supplierRequest->signatory_name) }}" required>
                                @error('signatory_name')<small>{{ $message }}</small>@enderror
                            </label>

                            <label>
                                <span>Position</span>
                                <input name="signatory_position" type="text" value="{{ old('signatory_position', $supplierRequest->signatory_position) }}" placeholder="Owner / Authorized Representative">
                                @error('signatory_position')<small>{{ $message }}</small>@enderror
                            </label>

                            <label>
                                <span>Signed Document</span>
                                <input name="signed_document" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small>Accepted files: PDF, JPG, JPEG, PNG. Maximum 10 MB.</small>
                                @error('signed_document')<small>{{ $message }}</small>@enderror
                            </label>

                            <label>
                                <span>Remarks</span>
                                <textarea name="supplier_remarks" rows="4">{{ old('supplier_remarks') }}</textarea>
                                @error('supplier_remarks')<small>{{ $message }}</small>@enderror
                            </label>

                            <label class="supplier-check">
                                <input name="signature_consent" type="checkbox" value="1" required>
                                <span>I confirm that I am authorized to submit this signed document for {{ $supplierRequest->supplier_name }} and that the uploaded file is complete and accurate.</span>
                            </label>
                            @error('signature_consent')<small>{{ $message }}</small>@enderror

                            <button type="submit" class="supplier-button">Submit Signed Document</button>
                        </form>
                    @endif
                </section>
            </div>
        </section>
    </main>
</body>
</html>
