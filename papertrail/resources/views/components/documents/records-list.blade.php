@props([
    'title' => 'Recent Records',
    'subtitle' => 'View and continue your latest document records.',
    'records' => [],
    'documentType' => null,
    'emptyMessage' => 'No document records found.',
    'columns' => ['DOCUMENT NO.', 'DOCUMENT TITLE', 'STATUS', 'LAST UPDATED', 'ACTIONS'],
    'showStatus' => true,
    'showUpdatedDate' => true,
    'actions' => [],
])

@php
    $records = collect($records);

    $statusLabel = function (?string $status): string {
        return match ($status) {
            'ppmp_draft', 'draft' => 'Draft',
            'ppmp_pending_signatories' => 'Pending Signature',
            'ppmp_signatories_completed' => 'Signed',
            'pending_ppmp_review' => 'Submitted to BAC',
            'submitted' => 'Submitted',
            'under_ppmp_review' => 'Under APP Consolidation',
            'under_review' => 'Under Review',
            'returned_by_bac_secretariat', 'returned' => 'Returned',
            'accepted_for_app_consolidation', 'accepted' => 'Approved',
            'approved', 'app_approved', 'approved_by_hope' => 'Approved',
            'signed', 'confirmed_by_bac_chair' => 'Signed',
            'completed', 'po_completed' => 'Completed',
            default => \Illuminate\Support\Str::of((string) ($status ?: 'record'))->replace('_', ' ')->title()->replace('Ppmp', 'PPMP')->toString(),
        };
    };

    $statusClass = function (?string $status): string {
        $status = strtolower((string) $status);

        if ($status === 'ppmp_signatories_completed') {
            return 'document-status-signed';
        }

        if (str_contains($status, 'draft')) {
            return 'document-status-draft';
        }

        if (str_contains($status, 'accepted') || str_contains($status, 'approved')) {
            return 'document-status-accepted';
        }

        if (str_contains($status, 'signed') || str_contains($status, 'confirmed')) {
            return 'document-status-signed';
        }

        if (str_contains($status, 'completed')) {
            return 'document-status-completed';
        }

        if (str_contains($status, 'returned')) {
            return 'document-status-returned';
        }

        if (str_contains($status, 'review')) {
            return str_contains($status, 'pending') ? 'document-status-submitted' : 'document-status-review';
        }

        if (str_contains($status, 'submitted')) {
            return 'document-status-submitted';
        }

        return 'document-status-neutral';
    };
@endphp

<section class="document-records-card document-records-card--compact-list">
    <header>
        <div>
            <span class="section-label">RECENT RECORDS</span>
            <h2>{{ $title }}</h2>
            <p>{{ $subtitle }}</p>
            @if ($documentType)
                <span class="document-records-type">{{ $documentType }}</span>
            @endif
        </div>
    </header>

    @if ($records->isNotEmpty())
        <div class="table-responsive document-records-table-wrap">
            <table class="table table-sm align-middle mb-0 document-records-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Holder</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        @php
                            $recordStatus = strtolower((string) ($record['status'] ?? ''));
                            $recordType = $record['type'] ?? $documentType ?? 'DOC';
                            $openLabel = str_contains($recordStatus, 'draft')
                                || str_contains($recordStatus, 'approved')
                                || str_contains($recordStatus, 'accepted')
                                || str_contains($recordStatus, 'signed')
                                || str_contains($recordStatus, 'confirmed')
                                || str_contains($recordStatus, 'completed')
                                    ? 'View'
                                    : 'Open';
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $record['number'] ?? 'Draft #' . ($record['id'] ?? '') }}</strong>
                            </td>
                            <td>
                                <span class="procurement-type-badge type-{{ \Illuminate\Support\Str::slug($recordType) ?: 'doc' }}">
                                    {{ $recordType }}
                                </span>
                            </td>
                            <td>
                                <span class="document-status-badge {{ $statusClass($record['status'] ?? null) }}">
                                    {{ $statusLabel($record['status'] ?? null) }}
                                </span>
                            </td>
                            <td>{{ $record['holder'] ?? $record['current_holder'] ?? $record['currentOffice'] ?? 'Not routed' }}</td>
                            <td>{{ $record['date_label'] ?? 'N/A' }}</td>
                            <td>
                                <div class="document-record-actions">
                                    @if (! empty($record['edit_url']))
                                        <x-ui.action-button
                                            :href="$record['edit_url']"
                                            icon="edit"
                                            label="Edit Record"
                                            tooltip="Edit Record"
                                            variant="edit"
                                            icon-only
                                        />
                                    @endif

                                    @if (! empty($record['url']))
                                        <x-ui.action-button
                                            :href="$record['url']"
                                            icon="view"
                                            :label="$openLabel"
                                            :tooltip="$openLabel"
                                            variant="view"
                                            icon-only
                                        />
                                    @endif

                                    @if (! empty($record['print_url']))
                                        <x-ui.action-button
                                            :href="$record['print_url']"
                                            icon="print"
                                            label="Print"
                                            tooltip="Print"
                                            variant="print"
                                            target="_blank"
                                            rel="noopener"
                                            icon-only
                                        />
                                    @endif

                                    @if (! empty($record['download_url']))
                                        <x-ui.action-button
                                            :href="$record['download_url']"
                                            icon="download"
                                            label="Download"
                                            tooltip="Download"
                                            variant="download"
                                            target="_blank"
                                            rel="noopener"
                                            data-pt-download-confirm
                                            data-confirm-title="Download Document?"
                                            data-confirm="This file contains official procurement records."
                                            data-confirm-label="Download"
                                            data-confirm-type="download"
                                            icon-only
                                        />
                                    @endif

                                    @if (! empty($record['delete_url']))
                                        <form method="POST" action="{{ $record['delete_url'] }}" data-confirm="Delete this record?" data-confirm-title="Delete Record?" data-confirm-label="Delete" data-confirm-type="danger">
                                            @csrf
                                            @method($record['delete_method'] ?? 'DELETE')
                                            <x-ui.action-button
                                                type="submit"
                                                icon="delete"
                                                label="Delete Record"
                                                tooltip="Delete Record"
                                                variant="danger"
                                                icon-only
                                            />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="document-records-empty">
            <strong>{{ $emptyMessage }}</strong>
            <p>Documents created, submitted, or routed through this module will appear here.</p>
        </div>
    @endif
</section>
