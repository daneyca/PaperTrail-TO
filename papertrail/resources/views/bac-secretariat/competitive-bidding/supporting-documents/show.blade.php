@extends('layouts.dashboard')

@section('title', $record->reference_number . ' Supporting Documents | PaperTrail')

@section('content')
    <section class="cb-support-detail-header pt-smooth-enter">
        <div>
            <p class="cb-support-eyebrow">BACSEC-002 · Supporting Documents</p>
            <h1>{{ $record->reference_number }}</h1>
            <p>{{ $record->project_title }}</p>
        </div>
        <dl class="cb-support-record-meta">
            <div>
                <dt>Approved Budget</dt>
                <dd>{{ $record->approved_budget !== null ? 'PHP ' . number_format((float) $record->approved_budget, 2) : 'Not set' }}</dd>
            </div>
            <div>
                <dt>Bid Opening</dt>
                <dd>{{ $record->bid_opening_date?->format('M d, Y') ?: 'Not set' }}</dd>
            </div>
        </dl>
    </section>

    @if ($errors->any())
        <div class="cb-support-alert cb-support-alert--error" role="alert">
            <strong>Review the highlighted action.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="cb-support-summary" aria-label="Supporting document summary">
        <div>
            <span>Project File</span>
            <strong>{{ $summary['project']['percent'] }}%</strong>
            <small>{{ $summary['project']['verified'] }} of {{ $summary['project']['required'] }} verified</small>
        </div>
        <div>
            <span>Participating Suppliers</span>
            <strong>{{ $record->suppliers->count() }}</strong>
            <small>Separate supplier files</small>
        </div>
        <div>
            <span>Files for Checking</span>
            <strong>{{ $record->supportingItems->where('status', 'attached')->count() }}</strong>
            <small>Attached, not yet verified</small>
        </div>
        <div>
            <span>Recorded Issues</span>
            <strong>{{ $record->supportingItems->whereIn('status', ['missing', 'for_correction'])->count() }}</strong>
            <small>Missing or for correction</small>
        </div>
    </section>

    <section class="cb-support-panel cb-support-guide-note">
        <x-papertrail.icon name="document" />
        <p><strong>Reference numbering:</strong> Item 20 is crossed out as a duplicate in the supplied guide, so the original numbering continues at item 21.</p>
    </section>

    <section class="cb-support-panel" aria-labelledby="project-file-heading">
        <header class="cb-support-panel-header">
            <div>
                <p class="cb-support-eyebrow">One Copy Per Bidding</p>
                <h2 id="project-file-heading">Project and BAC Documents</h2>
            </div>
            <span class="cb-support-progress">{{ $summary['project']['verified'] }} / {{ $summary['project']['required'] }} verified</span>
        </header>

        @include('bac-secretariat.competitive-bidding.supporting-documents._requirements', [
            'sections' => $projectSections,
            'scopeName' => 'Project file',
        ])
    </section>

    <section class="cb-support-panel" aria-labelledby="supplier-files-heading">
        <header class="cb-support-panel-header cb-support-panel-header--supplier">
            <div>
                <p class="cb-support-eyebrow">One File Per Bidder</p>
                <h2 id="supplier-files-heading">Supplier Attachments</h2>
            </div>

            <details class="cb-support-add-supplier" @if ($errors->has('supplier_name')) open @endif>
                <summary>
                    <x-papertrail.icon name="users" />
                    Add Supplier
                </summary>
                <form method="POST" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.suppliers.store', $record) }}">
                    @csrf
                    <label>
                        <span>Supplier Name</span>
                        <input name="supplier_name" value="{{ old('supplier_name') }}" required maxlength="255">
                    </label>
                    <label>
                        <span>Contact Person</span>
                        <input name="contact_person" value="{{ old('contact_person') }}" maxlength="255">
                    </label>
                    <label>
                        <span>Email</span>
                        <input name="email" value="{{ old('email') }}" type="email" maxlength="255">
                    </label>
                    <button class="cb-support-primary" type="submit">
                        <x-papertrail.icon name="check" />
                        Add Supplier File
                    </button>
                </form>
            </details>
        </header>

        <div class="cb-support-suppliers">
            @forelse ($record->suppliers as $supplier)
                @php($supplierSummary = $summary['suppliers'][$supplier->id])
                <details class="cb-support-supplier" @if ($loop->first) open @endif>
                    <summary>
                        <span class="cb-support-supplier-identity">
                            <span class="cb-support-supplier-icon"><x-papertrail.icon name="users" /></span>
                            <span>
                                <strong>{{ $supplier->supplier_name }}</strong>
                                <small>{{ $supplier->contact_person ?: 'No contact person' }}{{ $supplier->email ? ' · ' . $supplier->email : '' }}</small>
                            </span>
                        </span>
                        <span class="cb-support-supplier-summary">
                            <span class="cb-support-status cb-support-status--{{ str_replace('_', '-', $supplier->review_status) }}">{{ $supplier->statusLabel() }}</span>
                            <span class="cb-support-completeness cb-support-completeness--{{ str_replace(' ', '-', strtolower($supplierSummary['review_label'])) }}">{{ $supplierSummary['review_label'] }}</span>
                            <strong>{{ $supplierSummary['verified'] }} / {{ $supplierSummary['required'] }}</strong>
                        </span>
                    </summary>

                    <form method="POST" action="{{ route('bac-secretariat.competitive-bidding.supporting-documents.suppliers.update', [$record, $supplier]) }}" class="cb-support-decision-form">
                        @csrf
                        @method('PATCH')
                        <label>
                            <span>Supplier Review Status</span>
                            <select name="review_status">
                                @foreach ($supplierStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected($supplier->review_status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="cb-support-field-grow">
                            <span>Decision Remarks</span>
                            <input name="decision_remarks" value="{{ $supplier->decision_remarks }}" maxlength="2000" placeholder="Reference the BAC decision when applicable">
                        </label>
                        <button class="cb-support-secondary" type="submit">
                            <x-papertrail.icon name="check" />
                            Save Decision
                        </button>
                    </form>

                    @include('bac-secretariat.competitive-bidding.supporting-documents._requirements', [
                        'sections' => $supplier->supportingItems->groupBy('section'),
                        'scopeName' => $supplier->supplier_name,
                    ])
                </details>
            @empty
                <div class="cb-support-empty cb-support-empty--suppliers">
                    <x-papertrail.icon name="users" />
                    <strong>No supplier files added.</strong>
                </div>
            @endforelse
        </div>
    </section>
@endsection
