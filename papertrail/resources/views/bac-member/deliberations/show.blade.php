@extends('layouts.dashboard')

@section('title', ($deliberation->deliberation_number ?? 'BAC Deliberation') . ' | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $money = fn ($amount) => 'PHP ' . number_format((float) $amount, 2);
        $isOpen = in_array($deliberation->status, ['scheduled', 'ongoing'], true);
        $aiDocumentType = $document && strtoupper((string) $document->document_type) === 'PPMP' ? 'ppmp' : 'purchase_request';
        $aiDocumentLabel = $document && strtoupper((string) $document->document_type) === 'PPMP' ? 'AI Check PPMP' : 'AI Check PR';
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Deliberation Detail</p>
            <h1>{{ $deliberation->deliberation_number ?? 'Pending Deliberation Number' }}</h1>
            <p>{{ $deliberation->title }}</p>
        </div>

        <div class="hero-actions">
            @if ($document)
                <x-ai.completeness-check-button
                    :document-type="$aiDocumentType"
                    :document-id="$document->id"
                    :tracking-number="$document->tracking_number"
                    :label="$aiDocumentLabel"
                />
            @endif
            <a href="{{ route('bac-member.deliberations.document', $deliberation) }}" class="dashboard-action">View Related Document</a>
            <a href="{{ route('bac-member.deliberations.index') }}" class="dashboard-action secondary-action">Back to Deliberations</a>
        </div>
    </section>

    <section class="budget-review-layout reviewed-detail-layout pt-smooth-enter" style="--pt-delay: 120ms">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Deliberation Record</p>
                    <h2>{{ $deliberation->title }}</h2>
                    <p>{{ $deliberation->agenda ?? 'No agenda provided.' }}</p>
                </div>
                <span class="status-pill status-{{ $deliberation->status }}">{{ $label($deliberation->status) }}</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Deliberation Number</span><strong>{{ $deliberation->deliberation_number ?? 'N/A' }}</strong></div>
                <div><span>Status</span><strong>{{ $label($deliberation->status) }}</strong></div>
                <div><span>Scheduled Date</span><strong>{{ $deliberation->scheduled_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Started Date</span><strong>{{ $deliberation->started_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Completed Date</span><strong>{{ $deliberation->completed_at?->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
                <div><span>Created By</span><strong>{{ $deliberation->createdBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Chair</span><strong>{{ $deliberation->chair?->name ?? 'N/A' }}</strong></div>
                <div><span>Remarks</span><strong>{{ $deliberation->remarks ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel reviewed-summary-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">My Recommendation</p>
                    <h2>Member Input</h2>
                </div>
            </div>

            @if ($isOpen)
                <form method="POST" action="{{ route('bac-member.deliberations.recommendation', $deliberation) }}" class="budget-action-form">
                    @csrf
                    @method('PATCH')

                    <label for="recommendation">Recommendation</label>
                    <select id="recommendation" name="recommendation" required>
                        <option value="">Select recommendation</option>
                        @foreach ($recommendations as $value => $text)
                            <option value="{{ $value }}" @selected(old('recommendation', $participant?->recommendation) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('recommendation')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="recommendation-remarks">Recommendation Remarks</label>
                    <textarea id="recommendation-remarks" name="recommendation_remarks" rows="4" placeholder="Optional notes supporting your recommendation.">{{ old('recommendation_remarks', $participant?->recommendation_remarks) }}</textarea>
                    @error('recommendation_remarks')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <button type="submit">Submit Recommendation</button>
                </form>
            @else
                <div class="empty-state">
                    <strong>This deliberation is closed.</strong>
                    <p>Comments and recommendations are disabled for completed or cancelled deliberations.</p>
                </div>
            @endif
        </aside>
    </section>

    <section class="dashboard-widget-grid pt-smooth-enter" style="--pt-delay: 220ms">
        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Related Document</p><h2>{{ $document?->tracking_number ?? 'N/A' }}</h2></div></div>
            @if ($document)
                <div class="detail-grid budget-detail-grid">
                    <div><span>Document Type</span><strong>{{ $document->document_type }}</strong></div>
                    <div><span>Title</span><strong>{{ $document->title ?? $document->purpose }}</strong></div>
                    <div><span>Requesting Office</span><strong>{{ $document->submittingOffice?->name ?? 'N/A' }}</strong></div>
                    <div><span>Total Amount</span><strong>{{ $money($document->total_amount) }}</strong></div>
                    <div><span>Current Status</span><strong>{{ $label($document->status) }}</strong></div>
                    <div><span>Current Stage</span><strong>{{ $document->stage ?? 'N/A' }}</strong></div>
                    <div><span>Current Office</span><strong>{{ $document->currentOffice?->name ?? 'N/A' }}</strong></div>
                    <div><span>Priority</span><strong>{{ ucfirst($document->priority ?? 'normal') }}</strong></div>
                </div>
            @else
                <div class="empty-state"><strong>No related document</strong><p>This deliberation is missing its procurement document record.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Budget Review</p><h2>Latest Budget Summary</h2></div></div>
            @if ($latestBudgetReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($latestBudgetReview->review_status) }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestBudgetReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Available:</strong> {{ $latestBudgetReview->available_amount !== null ? $money($latestBudgetReview->available_amount) : 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBudgetReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No budget summary</strong><p>Budget review records will appear here when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">Accounting Review</p><h2>Latest Accounting Summary</h2></div></div>
            @if ($latestAccountingReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($latestAccountingReview->review_status) }}</p>
                    <p><strong>Reviewed By:</strong> {{ $latestAccountingReview->reviewedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Reference No.:</strong> {{ $latestAccountingReview->accounting_reference_no ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestAccountingReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No accounting summary</strong><p>Accounting review records will appear here when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">BAC Secretariat</p><h2>Processing Summary</h2></div></div>
            @if ($latestBacSecretariatReview || $document?->bac_secretariat_status)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($document?->bac_secretariat_status ?? $latestBacSecretariatReview?->review_status) }}</p>
                    <p><strong>Received By:</strong> {{ $document?->bacSecretariatReceivedBy?->name ?? 'N/A' }}</p>
                    <p><strong>Route Remarks:</strong> {{ $document?->route_remarks ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBacSecretariatReview?->remarks ?? $document?->bac_secretariat_remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No BAC Secretariat summary</strong><p>BAC Secretariat records will appear here when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget">
            <div class="widget-heading"><div><p class="eyebrow">BAC Member</p><h2>Review Summary</h2></div></div>
            @if ($latestBacMemberReview)
                <div class="budget-review-record">
                    <p><strong>Status:</strong> {{ $label($latestBacMemberReview->review_status) }}</p>
                    <p><strong>Recommendation:</strong> {{ $label($latestBacMemberReview->recommendation) }}</p>
                    <p><strong>Submitted:</strong> {{ $latestBacMemberReview->completed_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                    <p><strong>Remarks:</strong> {{ $latestBacMemberReview->remarks ?? 'N/A' }}</p>
                </div>
            @else
                <div class="empty-state"><strong>No BAC Member review summary</strong><p>BAC Member review actions will appear here when available.</p></div>
            @endif
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Participants</p><h2>Member Inputs</h2></div></div>
            <div class="table-scroll">
                <table class="user-management-table">
                    <thead><tr><th>Participant</th><th>Role</th><th>Attendance</th><th>Recommendation</th><th>Submitted Date</th></tr></thead>
                    <tbody>
                        @forelse ($deliberation->participants as $item)
                            <tr>
                                <td>{{ $item->user?->name ?? 'N/A' }}</td>
                                <td>{{ $item->role_name ?? $item->user?->role ?? 'N/A' }}</td>
                                <td><span class="status-pill status-{{ $item->attendance_status }}">{{ $label($item->attendance_status) }}</span></td>
                                <td>{{ $label($item->recommendation) }}</td>
                                <td>{{ $item->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state"><strong>No participants yet</strong><p>Participants will appear when added to the deliberation.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Comments</p><h2>Deliberation Comments</h2></div></div>
            <ol class="routing-timeline">
                @forelse ($deliberation->comments->sortByDesc('created_at') as $comment)
                    <li>
                        <strong>{{ $comment->user?->name ?? 'N/A' }}</strong>
                        <span>{{ $comment->user?->role ?? 'N/A' }} - {{ $comment->created_at?->format('M d, Y h:i A') }} - {{ $label($comment->visibility) }}</span>
                        <p>{{ $comment->comment }}</p>
                    </li>
                @empty
                    <li><strong>No comments yet</strong><span>Member comments will appear here.</span></li>
                @endforelse
            </ol>

            @if ($isOpen)
                <form method="POST" action="{{ route('bac-member.deliberations.comments.store', $deliberation) }}" class="budget-action-form">
                    @csrf
                    <label for="comment">Add Comment</label>
                    <textarea id="comment" name="comment" rows="4" required placeholder="Add an internal deliberation comment.">{{ old('comment') }}</textarea>
                    @error('comment')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <label for="visibility">Visibility</label>
                    <select id="visibility" name="visibility">
                        @foreach ($visibilities as $value => $text)
                            <option value="{{ $value }}" @selected(old('visibility', 'internal') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('visibility')
                        <span class="field-error">{{ $message }}</span>
                    @enderror

                    <button type="submit">Add Comment</button>
                </form>
            @endif
        </article>

        <article class="dashboard-widget widget-wide">
            <div class="widget-heading"><div><p class="eyebrow">Routing History</p><h2>Document Movement</h2></div></div>
            <ol class="routing-timeline">
                @forelse ($document?->routingHistories ?? [] as $history)
                    <li>
                        <strong>{{ $history->action }}</strong>
                        <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                        <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} - {{ $label($history->status_from ?? 'new') }} to {{ $label($history->status_to) }}</p>
                        @if ($history->comments)
                            <p>{{ $history->comments }}</p>
                        @endif
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>Routing actions will appear here after workflow movement is recorded.</span></li>
                @endforelse
            </ol>
        </article>
    </section>
@endsection
