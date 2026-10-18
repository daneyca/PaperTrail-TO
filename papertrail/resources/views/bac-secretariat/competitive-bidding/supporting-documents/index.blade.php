@extends('layouts.dashboard')

@section('title', 'Supporting Documents | PaperTrail')

@section('content')
    <section class="cb-support-hero pt-smooth-enter">
        <div>
            <p class="cb-support-eyebrow">BACSEC-001 · Competitive Bidding</p>
            <h1>Supporting Documents</h1>
            <p>Complete bidding files and supplier-submitted attachments.</p>
        </div>

        <details class="cb-support-create" @if ($errors->any()) open @endif>
            <summary>
                <x-papertrail.icon name="document" />
                New Bidding File
            </summary>
            <form method="POST" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.store') }}" class="cb-support-create-form">
                @csrf
                <label>
                    <span>Reference Number</span>
                    <input name="reference_number" value="{{ old('reference_number') }}" required maxlength="120" placeholder="CB-2026-001">
                </label>
                <label class="cb-support-field-wide">
                    <span>Project Title</span>
                    <input name="project_title" value="{{ old('project_title') }}" required maxlength="255" placeholder="Procurement title">
                </label>
                <label>
                    <span>Approved Budget</span>
                    <input name="approved_budget" value="{{ old('approved_budget') }}" type="number" min="0" step="0.01" placeholder="0.00">
                </label>
                <label>
                    <span>Bid Opening Date</span>
                    <input name="bid_opening_date" value="{{ old('bid_opening_date') }}" type="date">
                </label>
                <button class="cb-support-primary" type="submit">
                    <x-papertrail.icon name="check" />
                    Create File
                </button>
            </form>
        </details>
    </section>

    @if ($errors->any())
        <div class="cb-support-alert cb-support-alert--error" role="alert">
            <strong>The bidding file could not be created.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="cb-support-panel pt-smooth-enter" aria-labelledby="bidding-files-heading">
        <header class="cb-support-panel-header">
            <div>
                <p class="cb-support-eyebrow">Records</p>
                <h2 id="bidding-files-heading">Bidding Files</h2>
            </div>

            <form method="GET" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.index') }}" class="cb-support-search">
                <label for="supporting-search">Search</label>
                <div>
                    <input id="supporting-search" name="search" type="search" value="{{ $search }}" placeholder="Reference, project, or supplier">
                    <button type="submit" title="Search" aria-label="Search">
                        <x-papertrail.icon name="search" />
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.index') }}">Clear</a>
                    @endif
                </div>
            </form>
        </header>

        <div class="cb-support-table-scroll">
            <table class="cb-support-record-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Project</th>
                        <th>Bid Opening</th>
                        <th>Suppliers</th>
                        <th>Attached Guide Items</th>
                        <th>Status</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td><strong>{{ $record->reference_number }}</strong></td>
                            <td>
                                <strong>{{ $record->project_title }}</strong>
                                @if ($record->approved_budget !== null)
                                    <span>PHP {{ number_format((float) $record->approved_budget, 2) }}</span>
                                @endif
                            </td>
                            <td>{{ $record->bid_opening_date?->format('M d, Y') ?: 'Not set' }}</td>
                            <td>{{ $record->suppliers_count }}</td>
                            <td>{{ $record->verified_items_count }} / {{ $record->supporting_items_count }}</td>
                            <td>
                                <span class="cb-support-status cb-support-status--{{ $record->status }}">{{ $record->statusLabel() }}</span>
                            </td>
                            <td>
                                <a class="cb-support-icon-button" href="{{ route('bac-secretariat.competitive-bidding.supporting-documents.show', $record) }}" title="Open bidding file" aria-label="Open bidding file">
                                    <x-papertrail.icon name="open" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="cb-support-empty">
                                    <x-papertrail.icon name="document" />
                                    <strong>No supporting-document files found.</strong>
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
