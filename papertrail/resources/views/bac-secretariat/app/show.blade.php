@extends('layouts.dashboard')

@section('title', ($app->app_number ?? 'APP Draft') . ' | PaperTrail')

@section('content')
    @php
        $canFinalizeWithExistingSignatures = (bool) ($appSignatureWorkflowComplete ?? false);
    @endphp

    <section class="app-toolbar no-print">
        <div>
            <strong>{{ $app->app_number ?? 'APP Draft' }}</strong>
            <span>Tracking Number {{ $app->document_reference_number ?? 'Pending' }} &middot; {{ str($app->status)->replace('_', ' ')->title() }}</span>
        </div>
        <a href="{{ route('bac-secretariat.app.index') }}">Back</a>
        @if ($canConsolidate && $app->isEditable())
            <a href="{{ route('bac-secretariat.app.edit', $app) }}">Edit</a>
        @endif
        <a href="{{ route('bac-secretariat.app.print', $app) }}" target="_blank">Print</a>
        @if ($canConsolidate && $app->status === \App\Models\AnnualProcurementPlan::STATUS_DRAFT)
            <form
                method="POST"
                action="{{ route('bac-secretariat.app.destroy', $app) }}"
                data-confirm-title="Delete APP Draft?"
                data-confirm="This will remove this APP draft and its draft APP items only. Source PPMP records will remain available for consolidation."
                data-confirm-label="Delete Draft"
                data-confirm-type="danger"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="danger-action">Delete Draft</button>
            </form>
        @endif
        <x-ai.completeness-check-button
            document-type="app"
            :document-id="$app->id"
            :tracking-number="$app->displayNumber()"
            label="AI Check APP"
        />
        @if ($canConsolidate && $app->canSubmit())
            <form
                method="POST"
                action="{{ route('bac-secretariat.app.submit', $app) }}"
                data-confirm="{{ $canFinalizeWithExistingSignatures ? 'Finalize this APP update using the existing completed signatures?' : 'Submit this APP for approval?' }}"
            >
                @csrf
                @method('PATCH')
                <button type="submit">{{ $canFinalizeWithExistingSignatures ? 'Finalize Update' : 'Submit' }}</button>
            </form>
        @endif
        @if ($canApprove && $app->canApprove() && (int) ($signatureRequestCount ?? 0) === 0)
            <form method="POST" action="{{ route('bac-secretariat.app.approve', $app) }}" data-confirm="Approve this APP?">
                @csrf
                @method('PATCH')
                <button type="submit">Approve</button>
            </form>
        @endif
    </section>

    @if ($app->status === \App\Models\AnnualProcurementPlan::STATUS_RETURNED && filled($app->return_reason))
        <section class="app-return-note no-print">
            <strong>Return Reason</strong>
            <p>{{ $app->return_reason }}</p>
        </section>
    @endif

    @if ($canApprove && $app->canReturn() && (int) ($signatureRequestCount ?? 0) === 0)
        <section class="app-return-note no-print">
            <form method="POST" action="{{ route('bac-secretariat.app.return', $app) }}" data-confirm="Return this APP for correction?">
                @csrf
                @method('PATCH')
                <label for="return-reason">Return Remarks</label>
                <textarea id="return-reason" name="return_reason" rows="3" placeholder="Explain what needs correction." required></textarea>
                <button type="submit">Return APP</button>
            </form>
        </section>
    @endif

    @if (($appVersionHistory ?? collect())->isNotEmpty())
        <section class="table-panel no-print" aria-label="APP version history">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Version History</p>
                    <h2>{{ $app->app_number ?? $app->displayNumber() }}</h2>
                    <p>Previous approved versions are preserved when new PPMP rows start a revision.</p>
                </div>
            </div>
            <div class="table-scroll">
                <table class="user-management-table">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Total Budget</th>
                            <th>Updated</th>
                            <th>Updated By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appVersionHistory as $version)
                            <tr>
                                <td class="nowrap">
                                    <strong>v{{ $version['version_no'] }}</strong>
                                    @if ($version['current'] ?? false)
                                        <span class="app-version-chip">Current</span>
                                    @endif
                                </td>
                                <td><span class="status-pill status-{{ $version['status'] }}">{{ str($version['status'])->replace('_', ' ')->title() }}</span></td>
                                <td class="nowrap">PHP {{ number_format((float) $version['total_estimated_budget'], 2) }}</td>
                                <td class="nowrap">{{ $version['changed_at']?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                                <td>{{ $version['changed_by'] ?? 'System' }}</td>
                                <td>{{ $version['change_summary'] ?? 'Recorded APP version' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @include('bac-secretariat.app.partials.app-landscape-form', [
        'app' => $app,
        'mode' => 'show',
        'signatureSlots' => $signatureSlots ?? collect(),
    ])

    <x-documents.attachments-panel
        :document="$app"
        document-type="app"
        :can-upload="$canConsolidate && $app->isEditable()"
        title="APP Supporting Documents"
    />
@endsection
