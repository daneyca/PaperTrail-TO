@props([
    'groups' => [],
])

@php
    $rows = collect($groups)->flatMap(function (array $group) {
        return collect($group['documents'] ?? [])->map(function (array $document) use ($group): array {
            return [
                'group' => $group['title'] ?? 'Documents',
                'tone' => $group['tone'] ?? 'blue',
                'icon' => $group['icon'] ?? 'document',
                'document' => $document,
            ];
        });
    })->values();
@endphp

<section {{ $attributes->class(['procurement-status-tables']) }} aria-label="Latest document movement by status">
    <div class="procurement-card-heading procurement-card-heading--split">
        <div>
            <p class="eyebrow">Document Movement</p>
            <h2>Latest created, submitted, approved, and returned records</h2>
            <span>Compact view of the records that need quick checking.</span>
        </div>
    </div>

    <div class="table-responsive procurement-status-table-wrap">
        <table class="table table-sm align-middle mb-0 procurement-status-table procurement-status-table--single">
            <thead>
                <tr>
                    <th>Movement</th>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $document = $row['document'];
                        $viewUrl = $document['viewUrl'] ?? $document['url'] ?? null;
                    @endphp
                    <tr>
                        <td>
                            <span class="procurement-movement-chip procurement-movement-chip--{{ $row['tone'] }}">
                                <x-papertrail.icon :name="$row['icon']" />
                                {{ $row['group'] }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $document['number'] ?? 'Draft' }}</strong>
                            <span>{{ \Illuminate\Support\Str::limit($document['title'] ?? 'Untitled document', 46) }}</span>
                            <small>{{ $document['currentOffice'] ?? $document['currentHolder'] ?? 'Not routed' }}</small>
                        </td>
                        <td>
                            <span class="procurement-status-badge status-{{ $document['statusTone'] ?? 'pending' }}">
                                {{ $document['status'] ?? 'Pending' }}
                            </span>
                        </td>
                        <td>{{ $document['updatedAt'] ?? $document['submittedAt'] ?? 'Not submitted' }}</td>
                        <td class="text-end">
                            @if ($viewUrl)
                                <x-ui.action-button
                                    :href="$viewUrl"
                                    icon="view"
                                    label="View Details"
                                    tooltip="View Details"
                                    variant="view"
                                    icon-only
                                />
                            @else
                                <span class="procurement-table-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="procurement-mini-empty">No recent created, submitted, approved, or returned records yet.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
