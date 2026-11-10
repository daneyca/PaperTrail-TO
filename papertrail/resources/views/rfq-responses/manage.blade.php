@php
    $responseWorkflow = app(\App\Services\RfqSupplierWorkflowService::class);
    $canManageResponses = $responseWorkflow->canManage(auth()->user(), $rfq);
@endphp
@if ($canManageResponses)
    @once
        <link rel="stylesheet" href="{{ asset('css/rfq-responses.css') }}?v={{ filemtime(public_path('css/rfq-responses.css')) }}">
        <script src="{{ asset('js/rfq-response.js') }}" defer></script>
    @endonce
    <section class="table-panel rfq-response-management no-print" aria-label="Supplier quotations">
        <div class="section-heading"><div><p class="eyebrow">Digital RFQ</p><h2>Supplier Quotations</h2></div></div>
        @if ($errors->any())<div class="rfq-response-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if (! $rfq->isControlled())
            <p>The Procurement Controller must assign the official quotation number and date before the RFQ can be signed and shared with suppliers.</p>
        @else
            @php
                $officerSignature = $responseWorkflow->officerSignature($rfq);
                $officerRequest = \App\Models\SignatureRequest::forDocument('rfq', $rfq->id)->where('signatory_slot', 'procurement_officer')->latest('id')->first();
                $supplierQuotes = $rfq->supplierQuotations()->latest('id')->get();
            @endphp
            @if (! $officerSignature)
                @if ($officerRequest && $officerRequest->isOpen())
                    <p>Awaiting electronic signature from <strong>{{ $officerRequest->requestedTo?->name }}</strong> (Procurement Officer).</p>
                @else
                    <p>Route the controlled RFQ to its authorized Procurement Officer before creating supplier response links.</p>
                    <form method="POST" action="{{ route('head-office.rfqs.request-officer', $rfq) }}" class="rfq-response-fields">
                        @csrf
                        <label>Procurement Officer<select name="procurement_officer_user_id" required><option value="">Select authorized signatory</option>
                            @foreach (\App\Models\User::where('status', \App\Models\User::STATUS_ACTIVE)->whereNull('principal_user_id')->orderBy('name')->get()->reject(fn ($signer) => $signer->isOfficeDocumentPreparer()) as $signer)
                                <option value="{{ $signer->id }}">{{ $signer->name }} · {{ $signer->user_id }}</option>
                            @endforeach
                        </select></label><div class="rfq-response-form-action"><button type="submit" class="dashboard-action">Request Electronic Signature</button></div>
                    </form>
                @endif
            @elseif (! $responseWorkflow->officerSignatureIsCurrent($rfq))
                <p class="rfq-response-errors">The issued RFQ no longer matches the signed document. Supplier invitations are unavailable until the RFQ is reviewed.</p>
            @else
                <p>Electronically signed by <strong>{{ $officerSignature->signer_name }}</strong>. Create a private link for each supplier. Suppliers enter their prices, contact number, TIN, and signature online.</p>
                <form method="POST" action="{{ route('head-office.rfqs.quotations.invite', $rfq) }}" class="rfq-response-fields">
                    @csrf
                    <label>Supplier name *<input name="supplier_name" maxlength="255" value="{{ old('supplier_name') }}" required></label>
                    <label>Supplier email<input type="email" name="supplier_email" maxlength="255" value="{{ old('supplier_email') }}"></label>
                    <label>Response deadline (PHT) *<input type="date" name="expires_on" value="{{ old('expires_on', now('Asia/Manila')->addDays(7)->toDateString()) }}" min="{{ now('Asia/Manila')->toDateString() }}" max="{{ now('Asia/Manila')->addDays(30)->toDateString() }}" required></label>
                    <div class="rfq-response-form-action"><button type="submit" class="dashboard-action">Create Supplier Link</button></div>
                </form>
            @endif
            @if ($supplierQuotes->isNotEmpty())
                <div class="table-scroll"><table class="user-management-table"><thead><tr><th>Supplier</th><th>Status</th><th>Received / deadline</th><th>Quoted total</th><th>Actions</th></tr></thead><tbody>
                    @foreach ($supplierQuotes as $quote)
                        <tr><td>{{ $quote->supplier_name }}</td><td>{{ in_array($quote->status, ['pending', 'viewed']) && $quote->expires_at->isPast() ? 'Expired' : str($quote->status)->title() }}</td>
                            <td>{{ ($quote->submitted_at ?: $quote->expires_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT</td>
                            <td>{{ $quote->total_amount !== null ? 'PHP '.number_format((float) $quote->total_amount, 2) : 'Awaiting quotation' }}</td>
                            <td><div class="rfq-response-actions">
                                @if ($quote->isOpen())
                                    <button type="button" class="dashboard-action secondary-action" data-copy-rfq-link="{{ route('rfq-response.show', $quote->link_token) }}">Copy Supplier Link</button>
                                    <form method="POST" action="{{ route('head-office.rfqs.quotations.cancel', [$rfq, $quote]) }}">@csrf @method('PATCH')<button type="submit" class="dashboard-action secondary-action">Cancel Link</button></form>
                                @endif
                                @if ($quote->submitted_at)<a class="dashboard-action secondary-action" href="{{ route('head-office.rfqs.quotations.show', [$rfq, $quote]) }}">{{ $quote->status === 'submitted' ? 'Review Quotation' : 'View Quotation' }}</a>@endif
                            </div></td></tr>
                    @endforeach
                </tbody></table></div>
                @if ($supplierQuotes->where('status', 'verified')->isNotEmpty())<p>Verified supplier prices are available when you create the Abstract using this RFQ. The current Abstract form displays up to five suppliers.</p>@endif
            @endif
        @endif
    </section>
@endif
