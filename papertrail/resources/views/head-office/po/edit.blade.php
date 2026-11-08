@extends('layouts.dashboard')

@section('title', 'Edit Purchase Order | PaperTrail')

@section('content')
    <form method="POST" action="{{ route('head-office.purchase-orders.update', $po) }}" class="po-workspace po-editor-form">
        @csrf
        @method('PATCH')

        @if ($errors->any())
            <div class="session-alert session-alert-error po-validation-alert no-print" role="alert">
                <strong>Purchase Order was not submitted.</strong>
                <span>Complete the following required information, then click Submit again:</span>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="po-toolbar no-print">
            <div>
                <strong>Edit Purchase Order</strong>
                <span>Edit directly inside the official LGU Purchase Order form.</span>
            </div>
            <a href="{{ route('head-office.purchase-orders.show', $po) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Update</button>
            <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Purchase Order to the Procurement Controller?');">Submit</button>
            <button type="button" onclick="addPurchaseOrderRow()">Add Row</button>
            <button type="button" id="removeEmptyPoRowsBtn">Remove Empty Rows</button>
            <button type="button" onclick="printPurchaseOrderDocument()">Print</button>
        </section>

        @include('head-office.purchase-orders.partials.purchase-order-excel-form', [
            'po' => $po,
            'sourceDocument' => $sourceDocument,
            'sourceAbstract' => $sourceAbstract,
            'mode' => 'edit',
        ])
    </form>
@endsection
