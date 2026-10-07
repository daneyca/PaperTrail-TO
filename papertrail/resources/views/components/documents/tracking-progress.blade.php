@props([
    'document' => [],
    'timeline' => [],
    'currentStage' => null,
    'stages' => null,
])

@php
    $document = is_array($document) ? $document : [];
    $timelineItems = collect($timeline ?? [])->values();

    $textFrom = function ($value): string {
        return strtolower(trim((string) ($value ?? '')));
    };

    $hasAny = function (string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    };

    $typeText = $textFrom(($document['document_type'] ?? '') . ' ' . ($document['tracking_number'] ?? ''));
    $currentText = $textFrom(
        ($document['current_status'] ?? '') . ' ' .
        ($document['current_stage'] ?? '') . ' ' .
        ($document['current_holder'] ?? '') . ' ' .
        ($currentStage ?? '')
    );
    $timelineText = $textFrom($timelineItems->map(function ($item): string {
        return trim(
            (string) ($item['label'] ?? '') . ' ' .
            (string) ($item['status'] ?? '') . ' ' .
            (string) ($item['stage'] ?? '') . ' ' .
            (string) ($item['location'] ?? '')
        );
    })->implode(' '));

    $providedStages = is_iterable($stages) ? collect($stages)->filter()->values() : collect();

    if ($providedStages->isNotEmpty()) {
        $computedStages = $providedStages->map(function ($stage): array {
            $state = strtolower((string) ($stage['status'] ?? $stage['state'] ?? 'pending'));

            return [
                'label' => $stage['label'] ?? $stage['stage'] ?? 'Workflow Step',
                'status' => in_array($state, ['completed', 'current', 'pending'], true) ? $state : 'pending',
            ];
        })->values()->all();
    } else {
        if ($hasAny($typeText, ['purchase order', 'po-'])) {
            $labels = ['Draft Created', 'Submitted', 'Supplier Processing', 'Delivery', 'Inspection', 'Completed'];
            $keywords = [
                ['draft', 'created', 'recorded'],
                ['submitted', 'routed'],
                ['supplier', 'processing', 'purchase order', 'po approved', 'issued'],
                ['delivery', 'delivered', 'dispatch'],
                ['inspection', 'acceptance', 'accepted'],
                ['completed', 'closed'],
            ];
        } elseif ($hasAny($typeText, ['bac resolution', 'resolution', 'bac-res', 'bac res'])) {
            $labels = ['Draft Created', 'BAC Review', 'BAC Member Review', 'BAC Chair Signature', 'HOPE Approval', 'Completed'];
            $keywords = [
                ['draft', 'created', 'recorded'],
                ['bac review', 'resolution', 'secretariat'],
                ['member', 'deliberation'],
                ['chair', 'signature', 'confirmed'],
                ['hope', 'approval', 'approved'],
                ['completed', 'closed'],
            ];
        } elseif ($hasAny($typeText, ['project procurement management plan', 'ppmp'])) {
            $labels = ['Draft', 'Pending Signature', 'Signed', 'Submitted to BAC', 'Under APP Consolidation', 'Approved'];
            $keywords = [
                ['draft', 'created', 'recorded'],
                ['pending sign', 'signator', 'signature workflow'],
                ['signed', 'ready for submission', 'signatories completed'],
                ['submitted to bac', 'pending ppmp review'],
                ['under app consolidation', 'under ppmp review', 'app consolidation', 'consolidation'],
                ['accepted', 'approved'],
            ];
        } else {
            $labels = ['Draft Created', 'Submitted', 'PR Number Assigned', 'BAC Review', 'Approved', 'Completed'];
            $keywords = [
                ['draft', 'created', 'recorded'],
                ['submitted', 'pending pr number', 'pending pr number assignment'],
                ['pr number', 'number assigned', 'numbering'],
                ['bac', 'secretariat', 'validation', 'review', 'resolution'],
                ['approved', 'approval', 'hope', 'confirmed'],
                ['completed', 'closed'],
            ];
        }

        $sourceText = $currentText !== '' ? $currentText : $timelineText;
        $currentIndex = 0;

        foreach ($keywords as $index => $stageKeywords) {
            if ($hasAny($sourceText, $stageKeywords)) {
                $currentIndex = $index;
            }
        }

        if ($currentIndex === 0 && $timelineText !== '') {
            foreach ($keywords as $index => $stageKeywords) {
                if ($hasAny($timelineText, $stageKeywords)) {
                    $currentIndex = max($currentIndex, $index);
                }
            }
        }

        $computedStages = collect($labels)->map(function (string $label, int $index) use ($currentIndex): array {
            return [
                'label' => $label,
                'status' => $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'pending'),
            ];
        })->all();
    }
@endphp

<div {{ $attributes->merge(['class' => 'document-tracking-progress-wrap']) }}>
    <div class="document-tracking-progress" aria-label="Major document tracking stages">
        @foreach ($computedStages as $stage)
            @php
                $state = $stage['status'] ?? 'pending';
                $icon = $state === 'completed' ? '&#10003;' : ($state === 'current' ? '&#9679;' : '&#9675;');
                $stageLabel = (string) ($stage['label'] ?? 'Workflow Step');
                $stageSlug = \Illuminate\Support\Str::slug($stageLabel);
            @endphp

            <div class="progress-stage {{ $state }}" data-compact-timeline-stage="{{ $stageSlug }}">
                <div class="progress-circle" aria-hidden="true">{!! $icon !!}</div>
                <div class="progress-label">{{ $stageLabel }}</div>
            </div>
        @endforeach
    </div>
</div>
