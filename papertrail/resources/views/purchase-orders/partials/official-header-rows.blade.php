@php
    $colspan = $colspan ?? 7;
@endphp

<tr class="po-official-header-row">
    <td colspan="{{ $colspan }}" class="po-no-border po-official-header-cell">
        @include('purchase-orders.partials.official-header')
    </td>
</tr>
