@php
    $budgetInfo = $appBudgetInfo ?? null;
    $approvedBudget = (float) ($budgetInfo['approved'] ?? 0);
    $usedBudget = (float) ($budgetInfo['used'] ?? 0);
    $remainingBudget = (float) ($budgetInfo['remaining'] ?? 0);
@endphp

@if ($budgetInfo && $approvedBudget > 0)
    <section
        class="pr-budget-guard no-print"
        data-pr-budget-guard
        data-approved-budget="{{ number_format($approvedBudget, 2, '.', '') }}"
        data-used-budget="{{ number_format($usedBudget, 2, '.', '') }}"
        data-remaining-budget="{{ number_format($remainingBudget, 2, '.', '') }}"
        aria-label="APP budget control"
    >
        <div class="pr-budget-guard-heading">
            <div>
                <p class="pr-page-kicker">APP Budget Control</p>
                <h2>Budget limit for this PR</h2>
            </div>
            <strong data-pr-budget-status>Within budget</strong>
        </div>
        <div class="pr-budget-guard-grid">
            <div>
                <span>Approved APP/PPMP budget</span>
                <strong>PHP {{ number_format($approvedBudget, 2) }}</strong>
            </div>
            <div>
                <span>Already used by submitted PRs</span>
                <strong>PHP {{ number_format($usedBudget, 2) }}</strong>
            </div>
            <div>
                <span>Remaining before this PR</span>
                <strong>PHP {{ number_format($remainingBudget, 2) }}</strong>
            </div>
            <div>
                <span>This PR total</span>
                <strong>PHP <b data-pr-budget-current>0.00</b></strong>
            </div>
            <div>
                <span>Remaining after this PR</span>
                <strong>PHP <b data-pr-budget-after>{{ number_format($remainingBudget, 2) }}</b></strong>
            </div>
        </div>
        <p class="pr-budget-guard-message" data-pr-budget-message>
            Unit costs may be encoded manually, but the submitted PR total must stay within the remaining APP/PPMP budget.
        </p>
    </section>
@endif
