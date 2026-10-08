@extends('layouts.dashboard')

@section('title', 'Supplier Signatures | PaperTrail')

@section('content')
    <style>
        .supplier-signature-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .supplier-signature-link {
            width: min(100%, 360px);
            border: 1px solid #d8e2ef;
            border-radius: 8px;
            padding: 8px 10px;
            color: #52627a;
            font-size: 0.78rem;
        }

        .supplier-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .supplier-status--pending,
        .supplier-status--viewed {
            background: #fff4cc;
            color: #8a5a00;
        }

        .supplier-status--submitted {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .supplier-status--accepted {
            background: #d1fae5;
            color: #047857;
        }

        .supplier-status--rejected,
        .supplier-status--cancelled,
        .supplier-status--expired {
            background: #fee2e2;
            color: #b91c1c;
        }
    </style>

    <section class="dashboard-hero admin-users-hero pt-smooth-enter">
        <div>
            <p class="eyebrow">Competitive Bidding</p>
            <h1>Supplier Signatures</h1>
            <p>Create secure supplier links, receive signed copies, and keep an auditable external-signing trail.</p>
        </div>

        <div class="hero-actions">
            <a href="{{ route('bac-secretariat.competitive-bidding.menu') }}" class="dashboard-action secondary-action">Back to Competitive Bidding</a>
            <a href="{{ route('supplier-signatures.create') }}" class="dashboard-action">New Supplier Link</a>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" aria-label="Supplier signature summary">
        <x-dashboard.stat-card href="{{ route('supplier-signatures.index') }}" value="{{ $summary['total'] }}" label="All Requests" accent="navy" icon="signature" />
        <x-dashboard.stat-card href="{{ route('supplier-signatures.index', ['status' => 'pending']) }}" value="{{ $summary['pending'] }}" label="Pending Supplier" accent="gold" icon="clock" />
        <x-dashboard.stat-card href="{{ route('supplier-signatures.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="For BAC Validation" accent="blue" icon="upload" />
        <x-dashboard.stat-card href="{{ route('supplier-signatures.index', ['status' => 'accepted']) }}" value="{{ $summary['accepted'] }}" label="Accepted" accent="green" icon="check" />
    </section>

    <section class="table-panel pt-smooth-enter" aria-label="Supplier signature requests">
        <form method="GET" action="{{ route('supplier-signatures.index') }}" class="user-table-toolbar">
            <div class="user-search">
                <label for="search">Search</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Supplier, document, tracking number">
            </div>

            <div class="user-filter">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('supplier-signatures.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Supplier Link</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $supplierRequest)
                        <tr>
                            <td>{{ $supplierRequest->created_at?->format('M d, Y') }}</td>
                            <td>
                                <strong>{{ $supplierRequest->document_title }}</strong>
                                <br>
                                <span class="muted-text">{{ $supplierRequest->tracking_number ?: 'No tracking number' }}</span>
                            </td>
                            <td>
                                {{ $supplierRequest->supplier_name }}
                                @if ($supplierRequest->supplier_email)
                                    <br><span class="muted-text">{{ $supplierRequest->supplier_email }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="supplier-status supplier-status--{{ $supplierRequest->status }}">
                                    {{ $supplierRequest->statusLabel() }}
                                </span>
                            </td>
                            <td>
                                <input class="supplier-signature-link" type="text" value="{{ $supplierRequest->publicUrl() }}" readonly onclick="this.select()">
                            </td>
                            <td>
                                <div class="supplier-signature-actions">
                                    <a class="dashboard-action secondary-action" href="{{ route('supplier-signatures.show', $supplierRequest) }}">Open</a>
                                    @if ($supplierRequest->hasSignedDocument())
                                        <a class="dashboard-action secondary-action" href="{{ route('supplier-signatures.signed-document', $supplierRequest) }}">Signed File</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>No supplier signature requests yet.</strong>
                                    <p>Create a supplier link when a competitive bidding document needs supplier acknowledgment or signature.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="pagination-wrap">
                {{ $requests->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
