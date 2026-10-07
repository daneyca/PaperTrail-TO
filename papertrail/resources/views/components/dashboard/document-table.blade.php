@props([
    'documents' => [],
    'title' => 'Latest procurement movement',
    'subtitle' => 'Important records across your accessible workflow.',
])

<article {{ $attributes->class(['procurement-recent-card']) }}>
    <div class="procurement-card-heading procurement-card-heading--split">
        <div>
            <p class="eyebrow">Recent Documents</p>
            <h2>{{ $title }}</h2>
            <span>{{ $subtitle }}</span>
        </div>
    </div>

    <div class="table-responsive procurement-table-wrap">
        <table class="table table-sm align-middle mb-0 procurement-dashboard-table procurement-dashboard-table--minimal">
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
                @forelse ($documents as $document)
                    @php
                        $viewUrl = $document['viewUrl'] ?? $document['url'] ?? null;
                        $editUrl = $document['editUrl'] ?? $document['edit_url'] ?? null;
                        $printUrl = $document['printUrl'] ?? $document['print_url'] ?? null;
                        $downloadUrl = $document['downloadUrl'] ?? $document['download_url'] ?? null;
                        $deleteUrl = $document['deleteUrl'] ?? $document['delete_url'] ?? null;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $document['number'] ?? 'Draft' }}</strong>
                        </td>
                        <td><span class="procurement-type-badge type-{{ strtolower($document['type'] ?? 'doc') }}">{{ $document['type'] ?? 'DOC' }}</span></td>
                        <td><span class="procurement-status-badge status-{{ $document['statusTone'] ?? 'pending' }}">{{ $document['status'] ?? 'Pending' }}</span></td>
                        <td>{{ $document['currentOffice'] ?? $document['currentHolder'] ?? 'Not routed' }}</td>
                        <td>{{ $document['updatedAt'] ?? $document['submittedAt'] ?? 'Not submitted' }}</td>
                        <td class="text-end">
                            <div class="procurement-icon-actions">
                                @if ($viewUrl)
                                    <x-ui.action-button
                                        :href="$viewUrl"
                                        icon="view"
                                        label="View Details"
                                        tooltip="View Details"
                                        variant="view"
                                        icon-only
                                    />
                                @endif

                                @if ($editUrl)
                                    <x-ui.action-button
                                        :href="$editUrl"
                                        icon="edit"
                                        label="Edit Record"
                                        tooltip="Edit Record"
                                        variant="edit"
                                        icon-only
                                    />
                                @endif

                                @if ($printUrl)
                                    <x-ui.action-button
                                        :href="$printUrl"
                                        icon="print"
                                        label="Print"
                                        tooltip="Print"
                                        variant="print"
                                        target="_blank"
                                        rel="noopener"
                                        icon-only
                                    />
                                @endif

                                @if ($downloadUrl)
                                    <x-ui.action-button
                                        :href="$downloadUrl"
                                        icon="download"
                                        label="Download Document"
                                        tooltip="Download Document"
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

                                @if ($deleteUrl)
                                    <form method="POST" action="{{ $deleteUrl }}" data-confirm="Delete this record?" data-confirm-title="Delete Record?" data-confirm-label="Delete" data-confirm-type="danger">
                                        @csrf
                                        @method('DELETE')
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

                                @if (! $viewUrl && ! $editUrl && ! $printUrl && ! $downloadUrl && ! $deleteUrl)
                                    <span class="procurement-table-muted">N/A</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="procurement-empty-table">
                                <strong>No recent documents yet</strong>
                                <span>New procurement records will appear here after activity is recorded.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>
