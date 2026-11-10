@extends('layouts.dashboard')
@section('title', 'Observer Invitations | PaperTrail')
@section('content')
<section class="dashboard-hero admin-users-hero">
    <div><p class="eyebrow">BACSEC-003 · Competitive Bidding</p><h1>Letter of Invitation to Observer</h1><p>Prepare invitation letters and upload copies collected after physical signing or delivery.</p></div>
    <div class="hero-actions"><a href="{{ route('bac-secretariat.competitive-bidding.observers.create') }}" class="dashboard-action" data-action-icon="false">Create Invitation</a></div>
</section>
<section class="table-panel">
    <div class="table-scroll"><table class="user-management-table">
        <thead><tr><th>Recipient</th><th>Letter Date</th><th>Related Bidding File</th><th>Uploaded Copies</th><th>Last Updated</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse ($invitations as $invitation)
                <tr>
                    <td><strong>{{ $invitation->recipient() }}</strong></td>
                    <td>{{ $invitation->payload['letter_date'] ?? 'Not entered' }}</td>
                    <td>{{ $invitation->biddingRecord?->reference_number ?? '—' }}</td>
                    <td><span class="status-pill {{ $invitation->copies_count ? 'status-approved' : 'status-draft' }}">{{ $invitation->copies_count ? $invitation->copies_count.' uploaded' : 'No copies yet' }}</span></td>
                    <td>{{ $invitation->updated_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                    <td><div class="table-actions"><a href="{{ route('bac-secretariat.competitive-bidding.observers.show', $invitation) }}" class="dashboard-action secondary-action" data-action-icon="false">View</a><a href="{{ route('bac-secretariat.competitive-bidding.observers.edit', $invitation) }}" class="dashboard-action secondary-action" data-action-icon="false">Edit</a></div></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>No observer invitations yet.</strong><p>Create an editable letter, print it for signing, then upload the signed or received copies.</p></div></td></tr>
            @endforelse
        </tbody>
    </table></div>
    {{ $invitations->links() }}
</section>
@endsection
