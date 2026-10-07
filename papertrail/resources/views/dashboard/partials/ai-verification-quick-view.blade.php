@php
    $variant = $aiVerification['variant'] ?? 'end_user';
    $stats = collect($aiVerification['stats'] ?? []);
    $recentUploads = collect($aiVerification['recentUploads'] ?? []);
    $documentRows = collect($aiVerification['documentRows'] ?? []);
    $showUploadTable = in_array($variant, ['admin', 'end_user'], true);
@endphp

<section class="ai-verification-panel pt-smooth-enter" style="--pt-delay: 170ms" aria-label="AI Document Verification quick view">
    <div class="ai-verification-heading">
        <div>
            <p class="eyebrow">AI Document Verification</p>
            <h2>{{ $aiVerification['title'] ?? 'AI Document Verification' }}</h2>
            <p>{{ $aiVerification['description'] ?? 'Upload and review scanned procurement files for OCR, metadata extraction, and completeness checking.' }}</p>
        </div>

        <div class="ai-verification-actions">
            @if (! empty($aiVerification['indexUrl']))
                <a class="dashboard-action secondary-action" href="{{ $aiVerification['indexUrl'] }}">
                    {{ $aiVerification['secondaryActionLabel'] ?? 'View Verification' }}
                </a>
            @endif

            @if (! empty($aiVerification['uploadUrl']))
                <a class="dashboard-action" href="{{ $aiVerification['uploadUrl'] }}">
                    {{ $aiVerification['primaryActionLabel'] ?? 'Upload Document' }}
                </a>
            @endif

            @if (! empty($aiVerification['rulesUrl']))
                <a class="dashboard-action secondary-action" href="{{ $aiVerification['rulesUrl'] }}">Manage AI Rules</a>
            @endif
        </div>
    </div>

    <div class="pt-ai-insight-grid" aria-label="AI verification status overview">
        <article class="pt-ai-insight-card is-success">
            <span class="pt-ai-insight-icon" aria-hidden="true">
                <x-papertrail.icon name="check" />
            </span>
            <div>
                <strong>Completeness Score</strong>
                <span>Pending AI review until scanned files are processed.</span>
            </div>
        </article>

        <article class="pt-ai-insight-card is-violet">
            <span class="pt-ai-insight-icon" aria-hidden="true">
                <x-papertrail.icon name="spark" />
            </span>
            <div>
                <strong>Classification</strong>
                <span>No extracted classification is shown without real OCR data.</span>
            </div>
        </article>

        <article class="pt-ai-insight-card is-warning">
            <span class="pt-ai-insight-icon" aria-hidden="true">
                <x-papertrail.icon name="clock" />
            </span>
            <div>
                <strong>Delay Risk</strong>
                <span>Risk prediction will appear after workflow data is available.</span>
            </div>
        </article>

        <article class="pt-ai-insight-card is-danger">
            <span class="pt-ai-insight-icon" aria-hidden="true">
                <x-papertrail.icon name="document" />
            </span>
            <div>
                <strong>Anomaly Check</strong>
                <span>Not yet processed. No sample anomalies are generated.</span>
            </div>
        </article>
    </div>

    @if ($stats->isNotEmpty())
        <div class="ai-verification-stats">
            @foreach ($stats as $stat)
                <x-dashboard.stat-card
                    :href="$stat['href'] ?? null"
                    :value="$stat['value'] ?? '0'"
                    :label="$stat['label'] ?? ''"
                    :accent="$stat['accent'] ?? 'navy'"
                />
            @endforeach
        </div>
    @endif

    <div class="ai-verification-list">
        <div class="ai-verification-list-header">
            <strong>{{ $aiVerification['listTitle'] ?? 'Recent AI Document Uploads' }}</strong>
            <span>No OCR or AI results are shown until real processing data exists.</span>
        </div>

        <div class="table-scroll">
            @if ($showUploadTable)
                <table class="data-table ai-verification-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Uploaded By</th>
                            <th>Category</th>
                            <th>AI Status</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentUploads as $upload)
                            <tr>
                                <td>
                                    <strong>{{ $upload['fileName'] }}</strong>
                                    <span>{{ $upload['documentType'] }} &middot; {{ $upload['trackingNumber'] }}</span>
                                    @if ($upload['isConfidential'])
                                        <span class="ai-confidential-badge">Confidential</span>
                                    @endif
                                </td>
                                <td>{{ $upload['uploadedBy'] }}</td>
                                <td>{{ $upload['category'] }}</td>
                                <td><span class="ai-status-badge status-{{ $upload['aiStatusKey'] }}">{{ $upload['aiStatus'] }}</span></td>
                                <td>{{ $upload['uploadedAt'] }}</td>
                                <td>
                                    <div class="table-actions">
                                        @if ($upload['viewUrl'])
                                            <a href="{{ $upload['viewUrl'] }}" target="_blank" rel="noopener">View File</a>
                                        @endif
                                        @if ($upload['downloadUrl'])
                                            <a href="{{ $upload['downloadUrl'] }}">Download</a>
                                        @endif
                                        @if (! $upload['viewUrl'] && ! $upload['downloadUrl'])
                                            <span>Restricted</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state compact">
                                        <strong>No AI verification uploads yet</strong>
                                        <p>Scanned procurement files will appear here after they are uploaded.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="data-table ai-verification-table">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Document Type</th>
                            <th>Requesting Office</th>
                            @if ($variant === 'approver')
                                <th>Amount</th>
                            @endif
                            <th>AI Status</th>
                            <th>Current Stage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documentRows as $row)
                            <tr>
                                <td><strong>{{ $row['trackingNumber'] }}</strong></td>
                                <td>{{ $row['documentType'] }}</td>
                                <td>{{ $row['requestingOffice'] }}</td>
                                @if ($variant === 'approver')
                                    <td>{{ $row['amount'] }}</td>
                                @endif
                                <td><span class="ai-status-badge status-{{ $row['aiStatusKey'] }}">{{ $row['aiStatus'] }}</span></td>
                                <td>{{ $row['currentStage'] }}</td>
                                <td>
                                    <div class="table-actions">
                                        @if (! empty($row['viewUrl']))
                                            <a href="{{ $row['viewUrl'] }}">View Document</a>
                                        @endif
                                        @if (! empty($row['verificationUrl']))
                                            <a href="{{ $row['verificationUrl'] }}">View Verification</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $variant === 'approver' ? 7 : 6 }}">
                                    <div class="empty-state compact">
                                        <strong>No documents needing AI verification</strong>
                                        <p>Assigned documents with pending verification uploads will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</section>
