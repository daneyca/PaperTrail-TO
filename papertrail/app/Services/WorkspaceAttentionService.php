<?php

namespace App\Services;

use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class WorkspaceAttentionService
{
    public function __construct(private readonly SignatureRequestService $signatureRequests)
    {
    }

    /**
     * @param  iterable<int, User>  $workspaces
     * @return Collection<int, array{total: int, pending_signatures: int, unread_notifications: int, other_notifications: int}>
     */
    public function summaries(iterable $workspaces): Collection
    {
        return collect($workspaces)->mapWithKeys(fn (User $workspace) => [
            $workspace->id => $this->summaryFor($workspace),
        ]);
    }

    /**
     * @return array{total: int, pending_signatures: int, unread_notifications: int, other_notifications: int}
     */
    public function summaryFor(User $workspace): array
    {
        $pendingSignatureIds = $this->signatureRequests
            ->getPendingRequestsForUser($workspace)
            ->pluck('signature_requests.id');

        $unreadNotifications = $workspace->systemNotifications()
            ->whereNull('read_at');
        $unreadCount = (clone $unreadNotifications)->count();

        $coveredSignatureNotifications = $pendingSignatureIds->isEmpty()
            ? 0
            : (clone $unreadNotifications)
                ->where('related_type', SignatureRequest::class)
                ->whereIn('related_id', $pendingSignatureIds)
                ->count();

        $pendingSignatureCount = $pendingSignatureIds->count();
        $otherNotificationCount = max(0, $unreadCount - $coveredSignatureNotifications);

        return [
            'total' => $pendingSignatureCount + $otherNotificationCount,
            'pending_signatures' => $pendingSignatureCount,
            'unread_notifications' => $unreadCount,
            'other_notifications' => $otherNotificationCount,
        ];
    }
}
