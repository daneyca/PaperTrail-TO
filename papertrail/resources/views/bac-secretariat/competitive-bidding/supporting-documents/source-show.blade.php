@extends('layouts.dashboard')

@section('title', $record->reference_number . ' Submissions | PaperTrail')

@section('content')
    <section class="cb-support-detail-header pt-smooth-enter">
        <div>
            <p class="cb-support-eyebrow">BACSEC-003 &middot; Submit to BACSEC-001</p>
            <h1>{{ $record->reference_number }}</h1>
            <p>{{ $record->project_title }}</p>
        </div>
        <dl class="cb-support-record-meta">
            <div>
                <dt>Documents Submitted</dt>
                <dd>{{ $submittedCount }} / {{ $itemCount }}</dd>
            </div>
            <div>
                <dt>Bid Opening</dt>
                <dd>{{ $record->bid_opening_date?->format('M d, Y') ?: 'Not set' }}</dd>
            </div>
        </dl>
    </section>

    @if (session('status'))
        <div class="cb-support-alert cb-support-alert--success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="cb-support-alert cb-support-alert--error" role="alert">
            <strong>The document was not submitted.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="cb-support-panel" aria-labelledby="bacsec004-submissions-heading">
        <header class="cb-support-panel-header">
            <div>
                <p class="cb-support-eyebrow">Project Documents</p>
                <h2 id="bacsec003-submissions-heading">BACSEC-003 Submission Queue</h2>
            </div>
            <span class="cb-support-progress">{{ $submittedCount }} / {{ $itemCount }} submitted</span>
        </header>

        <div class="cb-support-requirement-groups">
            @foreach ($sections as $section => $items)
                <details class="cb-support-requirement-group" open>
                    <summary>
                        <span>{{ $section }}</span>
                        <small>{{ $items->whereIn('status', ['attached', 'verified'])->count() }} / {{ $items->count() }} submitted</small>
                    </summary>

                    <div class="cb-support-requirement-list">
                        @foreach ($items as $item)
                            @php($statusClass = str_replace('_', '-', $item->status))
                            <article class="cb-support-requirement cb-support-source-requirement cb-support-requirement--{{ $statusClass }}">
                                <div class="cb-support-requirement-number">{{ $item->guide_number }}</div>

                                <div class="cb-support-requirement-info">
                                    <div class="cb-support-requirement-title">
                                        <strong>{{ $item->requirement_name }}</strong>
                                    </div>
                                    <span class="cb-support-status cb-support-status--{{ $statusClass }}">{{ $item->statusLabel() }}</span>
                                    @if ($item->remarks)
                                        <small class="cb-support-review-note">BACSEC-001: {{ $item->remarks }}</small>
                                    @endif
                                    @if ($item->submitted_at)
                                        <small>Version {{ $item->source_version }} submitted {{ $item->submitted_at->format('M d, Y h:i A') }}</small>
                                    @endif
                                </div>

                                <div class="cb-support-files">
                                    @forelse ($item->attachments as $attachment)
                                        <div class="cb-support-file">
                                            <span title="{{ $attachment->displayName() }}">{{ $attachment->displayName() }}</span>
                                            <small>{{ $attachment->fileSizeHuman() }}</small>
                                            <div class="cb-support-file-actions">
                                                <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.attachments.view', [$record, $item, $attachment]) }}" target="_blank" class="cb-support-icon-button" title="View file" aria-label="View file">
                                                    <x-papertrail.icon name="view" />
                                                </a>
                                                <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.attachments.download', [$record, $item, $attachment]) }}" class="cb-support-icon-button" title="Download file" aria-label="Download file">
                                                    <x-papertrail.icon name="download" />
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="cb-support-no-file">No submitted file</span>
                                    @endforelse
                                </div>

                                <form method="POST" enctype="multipart/form-data" action="{{ route('bac-secretariat.competitive-bidding.supporting-submissions.store', [$record, $item]) }}" class="cb-support-source-form">
                                    @csrf
                                    <input type="file" name="attachment" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" aria-label="Choose final file for {{ $item->requirement_name }}">
                                    <button type="submit" class="cb-support-primary">
                                        <x-papertrail.icon name="upload" />
                                        {{ $item->source_version > 0 ? 'Submit Revision' : 'Submit to BACSEC-001' }}
                                    </button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </section>
@endsection
