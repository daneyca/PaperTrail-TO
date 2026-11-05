@extends('layouts.dashboard')

@section('title', ($isPhilgepsNotice ? 'PhilGEPS Bid Notice Abstract' : 'Submit Project Documents to BACSEC-001') . ' | PaperTrail')

@section('content')
    <section class="cb-support-hero pt-smooth-enter">
        <div>
            <p class="cb-support-eyebrow">BACSEC-003 &middot; Competitive Bidding</p>
            <h1>{{ $isPhilgepsNotice ? 'PhilGEPS Bid Notice Abstract' : 'Submit Project Documents to BACSEC-001' }}</h1>
            <p>{{ $isPhilgepsNotice ? 'Select a bidding file to upload its official PhilGEPS printout.' : 'Final project documents ready for BACSEC-001 checking.' }}</p>
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
                        <th>{{ $isPhilgepsNotice ? 'Printout' : 'Submitted' }}</th>
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
                            <td>
                                @if ($isPhilgepsNotice)
                                    {{ $record->projectItems->first()?->statusLabel() ?? 'Not added' }}
                                @else
                                    {{ $record->submitted_items_count }} / {{ $record->submission_items_count }}
                                @endif
                            </td>
                            <td><span class="cb-support-status cb-support-status--{{ $record->status }}">{{ $record->statusLabel() }}</span></td>
                            <td>
                                <a class="cb-support-icon-button" href="{{ route('bac-secretariat.competitive-bidding.supporting-submissions.show', $isPhilgepsNotice ? [$record, 'document' => 'philgeps_bid_notice_abstract'] : $record) }}" title="{{ $isPhilgepsNotice ? 'Open PhilGEPS printout' : 'Open submission file' }}" aria-label="{{ $isPhilgepsNotice ? 'Open PhilGEPS printout for ' . $record->reference_number : 'Open submission file for ' . $record->reference_number }}">
                                    <x-papertrail.icon name="open" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="cb-support-empty">
                                    <x-papertrail.icon name="document" />
                                    <strong>{{ $isPhilgepsNotice ? 'No bidding file yet. BACSEC-001 must create one before you can upload the PhilGEPS printout.' : 'No competitive bidding files are ready yet.' }}</strong>
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
