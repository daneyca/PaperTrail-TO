@extends('layouts.dashboard')

@section('title', 'Edit Purchase Order | PaperTrail')

@section('content')
    <form method="POST" action="{{ route('head-office.purchase-orders.update', $po) }}" class="po-workspace po-editor-form">
        @csrf
        @method('PATCH')

        <section class="po-toolbar no-print">
            <div>
                <strong>Edit Purchase Order</strong>
                <span>Edit directly inside the official LGU Purchase Order form.</span>
            </div>
            <a href="{{ route('head-office.purchase-orders.show', $po) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Update</button>
            <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Purchase Order?');">Submit</button>
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
