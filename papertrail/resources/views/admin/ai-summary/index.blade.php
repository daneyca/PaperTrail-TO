@extends('layouts.dashboard')

@section('title', 'AI Procurement Insights | PaperTrail')

@section('content')
    @php
        $overviewCards = [
            [
                'label' => 'Total Documents Processed',
                'value' => number_format((int) ($summary['total_documents'] ?? 0)),
                'tone' => 'blue',
                'note' => $summary['filters']['period'] ?? 'All available records',
            ],
            [
                'label' => 'Average Completeness Score',
                'value' => (int) ($summary['completeness_average'] ?? 0) . '%',
                'tone' => 'green',
                'note' => number_format((int) ($summary['completeness_checks'] ?? 0)) . ' AI checks recorded',
            ],
            [
                'label' => 'High Risk Documents',
                'value' => number_format((int) ($summary['high_risk_documents'] ?? 0)),
                'tone' => 'orange',
                'note' => 'High or critical delay risk checks',
            ],
            [
                'label' => 'Average Processing Time',
                'value' => number_format((float) ($summary['average_processing_days'] ?? 0), 1) . ' days',
                'tone' => 'purple',
                'note' => 'Based on available workflow dates',
            ],
        ];

        $riskLevels = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
        $delayLevels = $summary['delay_risk']['levels'] ?? [];
        $delayTotal = max(1, (int) ($summary['delay_risk']['total_checks'] ?? 0));
        $statusTotal = max(1, (int) ($summary['total_documents'] ?? 0));
    @endphp

    <div class="ai-summary-dashboard">
        @if (session('status'))
            <div class="dashboard-alert dashboard-alert-success">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="dashboard-alert dashboard-alert-danger">{{ session('error') }}</div>
        @endif

        <section class="ai-summary-hero">
            <div>
                <p class="eyebrow">AI Reporting</p>
                <h1>AI Procurement Insights</h1>
                <p>
                    Review procurement performance, document health, delay signals, and common issues from existing
                    PaperTrail records.
                </p>
                <div class="ai-summary-filter-pills" aria-label="Active filters">
                    <span>{{ $summary['filters']['period'] ?? 'All available records' }}</span>
                    <span>{{ $summary['filters']['document_type'] ?? 'All document types' }}</span>
                    <span>{{ $summary['filters']['office'] ?? 'All offices' }}</span>
                    <span>{{ $summary['filters']['status'] ?? 'All statuses' }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.ai-summary.generate') }}" class="ai-summary-generate">
                @csrf
                <input type="hidden" name="date_from" value="{{ $filters['date_from_input'] ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $filters['date_to_input'] ?? '' }}">
                <input type="hidden" name="document_type" value="{{ $filters['document_type'] ?? 'all' }}">
                <input type="hidden" name="office_id" value="{{ $filters['office_id'] ?? 'all' }}">
                <input type="hidden" name="status" value="{{ $filters['status'] ?? 'all' }}">
                <button type="submit" class="dashboard-action" @disabled(! $openAiConfigured)>
                    Generate AI Summary
                </button>
                @unless ($openAiConfigured)
                    <span class="ai-summary-config-note">OpenAI API key is not configured.</span>
                @endunless
            </form>
        </section>

        <section class="ai-summary-filter-card" aria-label="AI procurement summary filters">
            <form method="GET" action="{{ route('admin.ai-summary.index') }}" class="ai-summary-filter-grid">
                <label>
                    <span>Date From</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from_input'] ?? '' }}">
                </label>

                <label>
                    <span>Date To</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to_input'] ?? '' }}">
                </label>

                <label>
                    <span>Document Type</span>
                    <select name="document_type">
                        @foreach ($documentTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['document_type'] ?? 'all') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Office</span>
                    <select name="office_id">
                        <option value="all" @selected(($filters['office_id'] ?? 'all') === 'all')>All offices</option>
                        @foreach ($officeOptions as $office)
                            <option value="{{ $office->id }}" @selected((string) ($filters['office_id'] ?? 'all') === (string) $office->id)>
                                {{ $office->name }}{{ $office->code ? ' (' . $office->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Status</span>
                    <select name="status">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === (string) $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="ai-summary-filter-actions">
                    <button type="submit" class="dashboard-action">Apply</button>
                    <a href="{{ route('admin.ai-summary.index') }}" class="btn-secondary">Clear</a>
                </div>
            </form>
        </section>

        <section class="ai-summary-kpis" aria-label="Procurement summary metrics">
            @foreach ($overviewCards as $card)
                <article class="ai-summary-kpi ai-summary-kpi--{{ $card['tone'] }}">
                    <span class="ai-summary-kpi__icon" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $card['value'] }}</strong>
                        <span>{{ $card['label'] }}</span>
                        <small>{{ $card['note'] }}</small>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="ai-summary-main-grid">
            <div class="ai-summary-card-stack">
                <article class="ai-summary-card ai-summary-card-wide">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Document Mix</p>
                            <h2>Procurement Document Summary</h2>
                        </div>
                        <span>{{ number_format((int) ($summary['total_documents'] ?? 0)) }} records</span>
                    </div>

                    <div class="ai-summary-document-grid">
                        @forelse ($summary['document_types'] ?? [] as $type)
                            <div class="ai-summary-doc-type ai-summary-doc-type--{{ $type['tone'] ?? 'navy' }}">
                                <div>
                                    <strong>{{ $type['label'] }}</strong>
                                    <span>{{ number_format((int) $type['count']) }} records</span>
                                </div>
                                <div class="ai-summary-progress">
                                    <span style="width: {{ (int) $type['percentage'] }}%"></span>
                                </div>
                            </div>
                        @empty
                            <p class="ai-summary-empty">No procurement documents match the selected filters.</p>
                        @endforelse
                    </div>
                </article>

                <article class="ai-summary-card">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Status</p>
                            <h2>Workflow Status Breakdown</h2>
                        </div>
                    </div>

                    <div class="ai-summary-status-list">
                        @foreach ($summary['status_breakdown'] ?? [] as $status)
                            <div>
                                <span>{{ $status['label'] }}</span>
                                <strong>{{ number_format((int) $status['count']) }}</strong>
                                <div class="ai-summary-progress">
                                    <span style="width: {{ round(((int) $status['count'] / $statusTotal) * 100) }}%"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ai-summary-card">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Requirements</p>
                            <h2>Common Missing Requirements</h2>
                        </div>
                        <span>{{ number_format((int) ($summary['low_completeness_count'] ?? 0)) }} low-score checks</span>
                    </div>

                    <div class="ai-summary-list">
                        @forelse ($summary['common_missing_requirements'] ?? [] as $requirement)
                            <div>
                                <span>{{ $requirement['label'] }}</span>
                                <strong>{{ number_format((int) $requirement['count']) }}</strong>
                            </div>
                        @empty
                            <p class="ai-summary-empty">No missing required requirement patterns detected.</p>
                        @endforelse
                    </div>
                </article>

                <article class="ai-summary-card">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Route Validation</p>
                            <h2>Routing Guidance Signals</h2>
                        </div>
                    </div>

                    <div class="ai-summary-mini-stats">
                        <div>
                            <span>Total Checks</span>
                            <strong>{{ number_format((int) ($summary['route_validation']['total_checks'] ?? 0)) }}</strong>
                        </div>
                        <div>
                            <span>Warnings</span>
                            <strong>{{ number_format((int) ($summary['route_validation']['warning_count'] ?? 0)) }}</strong>
                        </div>
                        <div>
                            <span>Failed</span>
                            <strong>{{ number_format((int) ($summary['route_validation']['failed_count'] ?? 0)) }}</strong>
                        </div>
                    </div>
                </article>
            </div>

            <div class="ai-summary-card-stack">
                <article class="ai-summary-card">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Delay Risk</p>
                            <h2>Risk Level Summary</h2>
                        </div>
                        <span>{{ number_format((int) ($summary['delay_risk']['total_checks'] ?? 0)) }} checks</span>
                    </div>

                    <div class="ai-summary-risk-list">
                        @foreach ($riskLevels as $level)
                            @php
                                $count = (int) ($delayLevels[$level] ?? 0);
                                $percentage = round(($count / $delayTotal) * 100);
                            @endphp
                            <div class="ai-summary-risk ai-summary-risk--{{ strtolower($level) }}">
                                <div>
                                    <strong>{{ $level }}</strong>
                                    <span>{{ number_format($count) }}</span>
                                </div>
                                <div class="ai-summary-progress">
                                    <span style="width: {{ $percentage }}%"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ai-summary-card">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Classification</p>
                            <h2>AI Metadata Classification</h2>
                        </div>
                        <span>{{ (int) ($summary['metadata']['average_confidence'] ?? 0) }}% avg confidence</span>
                    </div>

                    <div class="ai-summary-list">
                        @forelse ($summary['metadata']['classifications'] ?? [] as $classification)
                            <div>
                                <span>{{ $classification['label'] }}</span>
                                <strong>{{ number_format((int) $classification['count']) }}</strong>
                            </div>
                        @empty
                            <p class="ai-summary-empty">No AI metadata classifications are available yet.</p>
                        @endforelse
                    </div>
                </article>

                <article class="ai-summary-card ai-summary-card-wide">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">GPT-4.1-mini Explanation</p>
                            <h2>AI Generated Procurement Summary</h2>
                        </div>
                        @if ($latestReport)
                            <span>Generated {{ $latestReport->created_at?->format('M d, Y h:i A') }}</span>
                        @endif
                    </div>

                    @if ($latestReport)
                        <div class="ai-summary-generated-text">
                            {!! nl2br(e($latestReport->ai_summary)) !!}
                        </div>
                        <p class="ai-summary-footnote">
                            Generated by {{ $latestReport->generatedBy?->name ?? 'System' }} for
                            {{ $latestReport->report_period }}. AI summarizes data only and does not approve, reject, route,
                            or modify documents.
                        </p>
                    @else
                        <div class="ai-summary-empty-state">
                            <strong>No AI summary generated yet.</strong>
                            <span>Use Generate AI Summary to create a concise explanation from the current dashboard statistics.</span>
                        </div>
                    @endif
                </article>

                <article class="ai-summary-card">
                    <div class="ai-summary-card-heading">
                        <div>
                            <p class="eyebrow">Recent AI Activity</p>
                            <h2>Latest Checks</h2>
                        </div>
                    </div>

                    <div class="ai-summary-activity-list">
                        @forelse ($summary['recent_ai_activity'] ?? [] as $activity)
                            <div class="ai-summary-activity ai-summary-activity--{{ $activity['tone'] ?? 'low' }}">
                                <span>{{ $activity['type'] }}</span>
                                <strong>{{ $activity['title'] }}</strong>
                                <small>{{ $activity['detail'] }} - {{ $activity['date'] }}</small>
                            </div>
                        @empty
                            <p class="ai-summary-empty">No AI activity matches the selected filters.</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </section>
    </div>
@endsection
