@extends('layouts.dashboard')

@section('title', 'Submit Bidding Documents | PaperTrail')

@section('content')
    <section class="cb-support-hero pt-smooth-enter">
        <div>
            <p class="cb-support-eyebrow">BACSEC-003 &middot; Competitive Bidding</p>
            <h1>Submit Bidding Documents</h1>
            <p>Final project documents ready for BACSEC-001 checking.</p>
        </div>
    </section>

    <section class="cb-support-panel pt-smooth-enter" aria-labelledby="submission-files-heading">
        <header class="cb-support-panel-header">
            <div>
                <p class="cb-support-eyebrow">Open Files</p>
                <h2 id="submission-files-heading">Competitive Bidding Records</h2>
            </div>
        </header>

        <div class="cb-support-table-scroll">
            <table class="cb-support-record-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Project</th>
                        <th>Bid Opening</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td><strong>{{ $record->reference_number }}</strong></td>
                            <td><strong>{{ $record->project_title }}</strong></td>
                            <td>{{ $record->bid_opening_date?->format('M d, Y') ?: 'Not set' }}</td>
                            <td>{{ $record->submitted_items_count }} / {{ $record->submission_items_count }}</td>
                            <td><span class="cb-support-status cb-support-status--{{ $record->status }}">{{ $record->statusLabel() }}</span></td>
                            <td>
                                <a class="cb-support-icon-button" href="{{ route('bac-secretariat.competitive-bidding.supporting-submissions.show', $record) }}" title="Open submission file" aria-label="Open submission file">
                                    <x-papertrail.icon name="open" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="cb-support-empty">
                                    <x-papertrail.icon name="document" />
                                    <strong>No competitive bidding files are ready yet.</strong>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="cb-support-pagination">
                {{ $records->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
