<?php

namespace App\Services;

use App\Models\ProcurementDocument;
use Illuminate\Support\Str;

class PrStatusTrackingPresenter
{
    public function present(ProcurementDocument $document): array
    {
        $histories = collect($document->routingHistories ?? [])
            ->sortBy(fn ($history) => ($history->action_at ?? $history->created_at)?->getTimestamp() ?? 0)
            ->values();
        $latest = $histories->last();
        $seen = $histories->pluck('status_to')->push($document->status);
        $numbered = filled($document->pr_no) || $document->pr_number_status === ProcurementDocument::PR_NUMBER_STATUS_ASSIGNED;
        $submitted = $seen->intersect([
            'pr_submitted', 'submitted_to_bac_secretariat', 'pr_received_by_bac_secretariat',
            'under_pr_validation', 'no_ppmp_record_found', 'pending_supplemental_app',
            'supplemental_app_created', 'ready_for_bac_resolution', 'bac_resolution_created',
            'bac_resolution_returned_to_end_user', 'svp_posting_required', 'svp_posting_completed',
            'ready_for_rfq', 'pending_budget_review', 'under_budget_review', 'budget_reviewed',
            'pending_accounting_review', 'under_accounting_review', 'accounting_reviewed',
        ])->isNotEmpty() || in_array($document->pr_status, ['submitted', 'received', 'under_validation', 'routed_to_budget'], true);
        $signed = $numbered || $submitted || $seen->intersect(['pr_signatories_completed', 'pending_pr_number_assignment'])->isNotEmpty();
        $requested = $signed || $seen->contains(ProcurementDocument::STATUS_PR_PENDING_SIGNATORIES);
        // A numbered PR being returned to its requesting office is a normal handoff.
        $returned = Str::startsWith((string) $document->status, 'returned_') || Str::contains((string) $document->status, 'signature_returned');
        $cancelled = Str::contains((string) $document->status, 'cancelled');
        $location = $document->currentOffice?->name ?? $latest?->toOffice?->name ?? $document->submittingOffice?->name ?? 'Not assigned';
        $handler = $document->assignedTo?->name;

        $steps = collect([
            ['label' => 'PR Signatory Requests Generated', 'done' => $requested, 'icon' => 'signature', 'next' => 'Generate the required Purchase Request signatory requests.'],
            ['label' => 'PR Signatories Completed and Submitted', 'done' => $signed, 'icon' => 'users', 'next' => 'Complete the required PR signatures and submit for official number assignment.'],
            ['label' => 'Official PR Number Assigned', 'done' => $numbered, 'icon' => 'document', 'next' => 'Assign the official PR number.'],
            ['label' => 'Submitted to BAC Secretariat', 'done' => $submitted, 'icon' => 'check', 'next' => 'Submit the numbered Purchase Request to BAC Secretariat.'],
        ]);
        $active = $steps->search(fn ($step) => ! $step['done']);
        $steps = $steps->map(function ($step, $index) use ($active, $returned, $cancelled) {
            $step['state'] = $step['done'] ? 'completed' : ($index === $active && ! $cancelled ? ($returned ? 'returned' : 'current') : 'pending');
            $step['status'] = match ($step['state']) {
                'completed' => 'Completed', 'current' => 'In progress', 'returned' => 'Needs attention', default => 'Pending',
            };

            return $step;
        });

        $activities = $histories->map(function ($history, $index) use ($histories, $returned, $cancelled) {
            $isLatest = $index === $histories->count() - 1;

            return [
                'title' => $history->action ?: 'Document movement recorded',
                'date' => $history->action_at ?? $history->created_at,
                'actor' => $history->actionBy?->name ?? 'System',
                'from' => $history->fromOffice?->name ?? 'Not recorded',
                'to' => $history->toOffice?->name ?? 'Not recorded',
                'before' => $this->label($history->status_from),
                'after' => $this->label($history->status_to),
                'comments' => $history->comments,
                'state' => $isLatest ? ($cancelled ? 'pending' : ($returned ? 'returned' : 'current')) : 'completed',
                'status' => $isLatest ? 'Latest update' : 'Recorded',
            ];
        });

        // Preserve repeated office visits when the document is routed back.
        $route = collect();
        $appendOffice = function (?string $office) use ($route): void {
            if (filled($office) && $route->last() !== $office) {
                $route->push($office);
            }
        };
        $appendOffice($histories->first()?->fromOffice?->name ?? $document->submittingOffice?->name);
        foreach ($histories as $history) {
            $appendOffice($history->fromOffice?->name);
            $appendOffice($history->toOffice?->name);
        }
        $appendOffice($location);

        $next = $cancelled ? 'This PR is cancelled. No further action is scheduled.'
            : ($returned ? 'Review the latest return remarks and complete the requested corrections.'
                : ($active !== false ? $steps[$active]['next'] : 'PR submission to BAC Secretariat is recorded. Follow the current stage for further processing.'));

        return [
            'number' => $document->displayNumber(),
            'office' => $document->submittingOffice?->name ?? 'Not recorded',
            'created' => $document->created_at,
            'updated' => $latest?->action_at ?? $latest?->created_at ?? $document->updated_at,
            'location' => $location,
            'handler' => $handler,
            'stage' => $document->stage ?? $this->label($document->status),
            'status' => $this->label($document->status),
            'overall' => $cancelled ? 'Cancelled' : ($returned ? 'Needs attention' : ($submitted ? 'Submitted to BAC' : 'In progress')),
            'state' => $cancelled ? 'pending' : ($returned ? 'returned' : ($submitted ? 'completed' : 'current')),
            'steps' => $steps,
            'completed' => $steps->where('done', true)->count(),
            'percent' => (int) round($steps->where('done', true)->count() / $steps->count() * 100),
            'activities' => $activities,
            'route' => $route,
            'next' => $next,
            'has_next' => ! $cancelled && $active !== false,
        ];
    }

    private function label(?string $value): string
    {
        return Str::of($value ?: 'Not recorded')->replace('_', ' ')->title()
            ->replace(['Pr ', 'Bac ', 'Rfq'], ['PR ', 'BAC ', 'RFQ'])->toString();
    }
}
