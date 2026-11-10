@php
    $required = (int) ($ppmpSignatureProgress['required'] ?? 0);
    $completed = (int) ($ppmpSignatureProgress['completed'] ?? 0);
    $complete = $required > 0 && $completed >= $required;
    $returned = collect($ppmpSignatureCards)->contains('status', 'rejected');
    $state = $returned ? 'rejected' : ($complete ? 'signed' : 'pending');
    $status = $returned ? 'Returned for correction' : ($complete ? 'Signed' : ($completed > 0 ? 'Partially signed' : 'Awaiting signatures'));
@endphp

<section class="ppmp-signature-strip no-print" aria-label="PPMP e-signatures">
    <details class="ppmp-signature-details">
        <summary>
            <span class="ppmp-signature-status-badge ppmp-signature-status-badge--{{ $state }}">{{ $status }}</span>
            <span class="ppmp-signature-count">{{ $completed }}/{{ $required }} signed</span>
            <span class="ppmp-signature-toggle"><span>View details</span><span>Hide details</span></span>
        </summary>
        <ul class="ppmp-signature-detail-list">
            @foreach ($ppmpSignatureCards as $card)
                <li>
                    <div>
                        <strong>{{ $card['label'] }} &middot; {{ $card['signer_name'] }}</strong>
                        <small>{{ $card['designation'] }}</small>
                    </div>
                    <span class="ppmp-signature-status-badge ppmp-signature-status-badge--{{ $card['status'] }}">{{ $card['status_label'] }}</span>
                    @if ($card['signed_at'])
                        <small>Signed {{ $card['signed_at']->copy()->timezone('Asia/Manila')->format('M d, Y h:i A') }}</small>
                    @endif
                    @if (filled($card['remarks'] ?? null))
                        <div class="ppmp-signature-detail-remarks">
                            <strong>Remarks</strong>
                            <p class="signature-remarks-text">{{ $card['remarks'] }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
    @foreach ($ppmpSignatureCards as $card)
        @if (! $card['signed_at'] && $card['request']?->isOpen() && $card['request']->is_required && (int) $card['request']->requested_to_user_id === (int) auth()->id())
            <a href="{{ route('signature-requests.show', $card['request']) }}" class="dashboard-action" data-action-icon="false">Sign {{ $card['label'] }}</a>
        @endif
    @endforeach
</section>
