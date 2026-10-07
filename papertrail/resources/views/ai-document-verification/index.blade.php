@extends('layouts.dashboard')

@section('title', 'AI Document Verification | PaperTrail')

@section('content')
    @php
        $statusCards = [
            [
                'label' => 'Completeness Score',
                'status' => 'Pending Review',
                'tone' => 'blue',
                'icon' => 'checklist',
            ],
            [
                'label' => 'Classification',
                'status' => 'Not Processed',
                'tone' => 'green',
                'icon' => 'layers',
            ],
            [
                'label' => 'Delay Risk',
                'status' => 'Awaiting Data',
                'tone' => 'amber',
                'icon' => 'clock',
            ],
            [
                'label' => 'Anomaly Check',
                'status' => 'Preview Mode',
                'tone' => 'violet',
                'icon' => 'scan',
            ],
        ];
    @endphp

    <div class="ai-verification-workspace">
        <section class="ai-verification-hero" aria-labelledby="ai-verification-title">
            <div>
                <p class="eyebrow">AI Document Verification</p>
                <h1 id="ai-verification-title">AI Document Verification</h1>
                <p>Upload and review scanned procurement documents for OCR extraction, classification, completeness checking, and AI-assisted validation.</p>
            </div>

            @if ($canUpload)
                <a href="#upload-document" class="dashboard-action ai-verification-hero-action">Upload Document</a>
            @endif
        </section>

        <section class="ai-verification-status-grid" aria-label="AI verification status summary">
            @foreach ($statusCards as $card)
                <article class="ai-verification-status-card ai-verification-status-card--{{ $card['tone'] }}">
                    <span class="ai-verification-status-icon" aria-hidden="true">
                        @switch($card['icon'])
                            @case('checklist')
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M9 11l2 2 4-5" />
                                    <path d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                                </svg>
                                @break
                            @case('layers')
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="m12 3 8 4-8 4-8-4 8-4Z" />
                                    <path d="m4 12 8 4 8-4" />
                                    <path d="m4 17 8 4 8-4" />
                                </svg>
                                @break
                            @case('clock')
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 7v5l3 2" />
                                    <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                @break
                            @default
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M8 3H5a2 2 0 0 0-2 2v3" />
                                    <path d="M16 3h3a2 2 0 0 1 2 2v3" />
                                    <path d="M8 21H5a2 2 0 0 1-2-2v-3" />
                                    <path d="M16 21h3a2 2 0 0 0 2-2v-3" />
                                    <path d="M7 12h10" />
                                </svg>
                        @endswitch
                    </span>
                    <div>
                        <span>Signal</span>
                        <strong>{{ $card['label'] }}</strong>
                    </div>
                    <span class="ai-verification-status-pill">{{ $card['status'] }}</span>
                </article>
            @endforeach
        </section>

        @if ($canUpload)
            <section class="ai-verification-card ai-verification-upload-card" id="upload-document">
                <header class="ai-verification-card-header">
                    <div>
                        <p class="eyebrow">Upload</p>
                        <h2>Upload Scanned Procurement File</h2>
                    </div>
                </header>

                <form method="POST" action="{{ route('ai-document-verification.upload') }}" enctype="multipart/form-data" class="ai-verification-upload-form">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $filters['scope'] ?? 'mine' }}">

                    <label class="ai-verification-field ai-verification-field--file">
                        <span>File Upload</span>
                        <input type="file" name="attachment" required>
                        @error('attachment')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="ai-verification-field">
                        <span>Category</span>
                        <select name="attachment_category">
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('attachment_category', 'scanned_document') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('attachment_category')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="ai-verification-field ai-verification-field--description">
                        <span>Description</span>
                        <input type="text" name="description" value="{{ old('description') }}" placeholder="Optional short note">
                        @error('description')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <div class="ai-verification-upload-actions">
                        <label class="ai-verification-check">
                            <input type="checkbox" name="is_confidential" value="1" @checked(old('is_confidential'))>
                            <span>Confidential file</span>
                        </label>

                        <button type="submit" class="dashboard-action">Upload Document</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="ai-verification-card ai-verification-repository">
            <header class="ai-verification-card-header">
                <div>
                    <p class="eyebrow">Document Repository</p>
                    <h2>AI Document Uploads</h2>
                </div>
            </header>

            <form method="GET" action="{{ route('ai-document-verification.index') }}" class="ai-verification-filter-toolbar">
                <label class="ai-verification-field">
                    <span>Search</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="File name, tracking number, or office">
                </label>

                <label class="ai-verification-field">
                    <span>AI Status</span>
                    <select name="ai_status">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['ai_status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ai-verification-field">
                    <span>Category</span>
                    <select name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <input type="hidden" name="scope" value="{{ $filters['scope'] }}">

                <div class="filter-actions ai-verification-filter-actions">
                    <button type="submit">Apply</button>
                    <a href="{{ route('ai-document-verification.index', ['scope' => $filters['scope']]) }}">Clear</a>
                </div>
            </form>

            <div class="ai-verification-table-wrap">
                <table class="data-table ai-verification-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Category</th>
                            <th>AI Status</th>
                            <th>Uploaded Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($uploads as $upload)
                            <tr>
                                <td>
                                    <div class="ai-verification-file">
                                        <strong>{{ $upload['fileName'] }}</strong>
                                        <span>{{ $upload['documentType'] }} &middot; {{ $upload['trackingNumber'] }}</span>
                                        @if ($upload['isConfidential'])
                                            <span class="ai-verification-badge ai-verification-badge--confidential">Confidential</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="ai-verification-badge">{{ $upload['category'] }}</span>
                                </td>
                                <td>
                                    <div class="ai-verification-status-stack">
                                        <span class="ai-verification-badge ai-verification-badge--{{ $upload['aiStatusKey'] }}">{{ $upload['aiStatus'] }}</span>
                                        <small>{{ $upload['ocrStatus'] }}</small>
                                    </div>
                                </td>
                                <td>{{ $upload['uploadedAt'] }}</td>
                                <td>
                                    <div class="table-actions ai-verification-actions">
                                        @if ($upload['viewUrl'])
                                            <a href="{{ $upload['viewUrl'] }}" target="_blank" rel="noopener" class="ai-verification-action" aria-label="View {{ $upload['fileName'] }}" title="View">
                                                <x-papertrail.icon name="view" />
                                            </a>
                                        @endif
                                        @if ($upload['downloadUrl'])
                                            <a href="{{ $upload['downloadUrl'] }}"
                                                class="ai-verification-action"
                                                data-pt-download-confirm
                                                data-confirm-title="Download Document?"
                                                data-confirm="This file contains official procurement records."
                                                data-confirm-label="Download"
                                                data-confirm-type="download"
                                                aria-label="Download {{ $upload['fileName'] }}"
                                                title="Download"
                                            >
                                                <x-papertrail.icon name="download" />
                                            </a>
                                        @endif
                                        @if (! $upload['viewUrl'] && ! $upload['downloadUrl'])
                                            <span class="ai-verification-restricted">Restricted</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="ai-verification-empty">
                                        <strong>No AI verification uploads yet</strong>
                                        <p>Scanned procurement files will appear here once uploaded by an authorized user.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($uploads->hasPages())
                <div class="pagination-wrap ai-verification-pagination">
                    {{ $uploads->links('vendor.pagination.papertrail') }}
                </div>
            @endif
        </section>
    </div>
@endsection
