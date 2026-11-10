<li class="observer-project" data-observer-project>
    <div class="observer-project__text">
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $path.'.description', 'value' => $project['description'] ?? '', 'label' => 'Project description', 'placeholder' => 'Enter project description', 'fieldClass' => 'observer-project__description'])
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $path.'.budget_prefix', 'value' => $project['budget_prefix'] ?? 'with an ABC of Php', 'label' => 'Budget wording', 'fieldClass' => 'observer-project__prefix'])
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $path.'.budget', 'value' => $project['budget'] ?? '', 'label' => 'Approved budget for the contract', 'numeric' => true, 'fieldClass' => 'observer-project__budget'])
    </div>
    @if ($editable ?? false)
        <button type="button" class="observer-row-remove no-print" data-action-icon="false" data-observer-remove="project" aria-label="Remove project" title="Remove project">Remove</button>
    @endif
</li>
