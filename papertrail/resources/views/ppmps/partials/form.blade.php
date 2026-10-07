@csrf
@if ($ppmp->exists)
    @method('PATCH')
@endif

@php
    $mode = $ppmp->exists ? 'edit' : 'create';
    $status = old('status', $ppmp->status ?: 'draft');
    $statusLabel = $status === 'draft' ? 'PPMP Draft' : str($status)->replace('_', ' ')->title()->replace('Ppmp', 'PPMP') . ' PPMP';
@endphp

<input type="hidden" name="status" value="{{ $status }}">

<div class="ppmp-sticky-actions no-print" aria-label="PPMP form controls">
    <span class="ppmp-draft-status">{{ $statusLabel }}</span>
    <a href="{{ $ppmp->exists ? route('ppmps.show', $ppmp) : route('ppmps.index') }}" class="ppmp-action-secondary">Back</a>
    <button type="submit" name="save_action" value="draft">Save Draft</button>
    <button type="submit" name="save_action" value="submit" data-confirm="PPMP requires Head of Office e-signature before submission to BAC Secretariat for APP consolidation. Continue?">Submit</button>
    <button type="button" data-add-ppmp-row>Add Row</button>
    <button type="button" data-clear-empty-ppmp-rows>Remove Empty Rows</button>
    @if ($ppmp->exists)
        <a href="{{ route('ppmps.print', $ppmp) }}" target="_blank" rel="noopener">Print</a>
    @endif
</div>

@include('ppmps.partials.official-form', [
    'ppmp' => $ppmp,
    'items' => $items,
    'options' => $options,
    'mode' => $mode,
])
