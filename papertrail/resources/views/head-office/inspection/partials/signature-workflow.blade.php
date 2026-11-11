@if ($record->exists)
    @php
        $iarWorkflow = app(\App\Services\InspectionSignatureWorkflowService::class);
        $iarRequests = $iarWorkflow->currentRequests($record)->with('requestedTo')->get()->keyBy('signatory_slot');
        $iarLocked = $iarWorkflow->isLocked($record);
    @endphp
    <section class="dashboard-widget no-print" aria-label="IAR signature routing">
        <div class="widget-heading"><div><p class="eyebrow">Required Signatures</p><h2>Inspection / Acceptance Signatories</h2>
            <p>All three accounts must sign the current report before acceptance or completion.</p></div></div>
        <ul>
            @foreach (\App\Services\InspectionSignatureWorkflowService::SIGNERS as $slot => [$account, $field, $designation])
                @php $iarRequest = $iarRequests->get($slot); @endphp
                <li>{{ $record->{$field.'_designation'} ?: $designation }} → <strong>{{ $account }}</strong>:
                    {{ $iarRequest ? str($iarRequest->status)->replace('_', ' ')->title() : 'Not yet requested' }}
                    @if ($iarRequest && (int) $iarRequest->requested_to_user_id === (int) auth()->id())
                        <a href="{{ route('signature-requests.show', $iarRequest) }}">Open signature request</a>
                    @endif
                </li>
            @endforeach
        </ul>
        @if (! $iarLocked)
            <form method="POST" action="{{ route('head-office.inspection.submit-for-signatures', $purchaseOrder) }}">
                @csrf @method('PATCH')<button type="submit" class="dashboard-action">Submit for Signatures</button>
            </form>
        @elseif ($record->status !== \App\Models\InspectionAcceptanceRecord::STATUS_COMPLETED)
            <p>The submitted report is locked. Revising it requires new signatures from all three accounts.</p>
            <form method="POST" action="{{ route('head-office.inspection.revise', $purchaseOrder) }}">
                @csrf @method('PATCH')<button type="submit" class="dashboard-action secondary-action">Revise Report</button>
            </form>
        @endif
    </section>
@endif
