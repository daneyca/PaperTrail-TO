<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnnualInvestmentProgramService
{
    public const SLOTS = [
        'prepared' => 'Prepared by',
        'reviewed' => 'Reviewed by',
        'approved' => 'Approved by',
    ];

    public const RECIPIENT_ACCOUNTS = [
        'prepared' => ['user_id' => 'BACCHAIR-001', 'role' => User::ROLE_BAC_CHAIR, 'office' => 'BAC'],
        'reviewed' => ['user_id' => 'MBO-001', 'role' => User::ROLE_HEAD_OFFICE, 'office' => 'MBO'],
        'approved' => ['user_id' => 'MO-001', 'role' => User::ROLE_HEAD_OFFICE, 'office' => 'MO'],
    ];

    public function validateDocument(Request $request): array
    {
        $rules = [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'municipality' => ['required', 'string', 'max:120'],
            'climate_change' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.section' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'items.*.reference' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.office' => ['nullable', 'string', 'max:200'],
            'items.*.start' => ['nullable', 'string', 'max:80'],
            'items.*.completion' => ['nullable', 'string', 'max:80'],
            'items.*.output' => ['nullable', 'string', 'max:500'],
            'items.*.funding' => ['nullable', 'string', 'max:120'],
            'items.*.ps' => ['nullable', 'numeric', 'min:0'],
            'items.*.mooe' => ['nullable', 'numeric', 'min:0'],
            'items.*.co' => ['nullable', 'numeric', 'min:0'],
            'items.*.adaptation' => ['nullable', 'numeric', 'min:0'],
            'items.*.mitigation' => ['nullable', 'numeric', 'min:0'],
            'items.*.typology' => ['nullable', 'string', 'max:100'],
            'prepared_name' => ['nullable', 'string', 'max:120'],
            'prepared_title' => ['nullable', 'string', 'max:120'],
            'prepared_date' => ['nullable', 'string', 'max:80'],
            'reviewed_name' => ['nullable', 'string', 'max:120'],
            'reviewed_title' => ['nullable', 'string', 'max:120'],
            'reviewed_date' => ['nullable', 'string', 'max:80'],
            'approved_name' => ['nullable', 'string', 'max:120'],
            'approved_title' => ['nullable', 'string', 'max:120'],
            'approved_date' => ['nullable', 'string', 'max:80'],
        ];

        if ($request->exists('sections')) {
            $rules += [
                'sections' => ['required', 'array', 'min:1', 'max:40'],
                'sections.*.key' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/', 'distinct'],
                'sections.*.type' => ['required', 'in:section,subheading'],
                'sections.*.label' => ['required', 'string', 'max:100'],
                'sections.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ];
        } else {
            // Accept forms created before sections became editable.
            $rules += [
                'section_labels' => ['required', 'array'],
                'section_labels.general' => ['required', 'string', 'max:100'],
                'section_labels.other' => ['required', 'string', 'max:100'],
                'section_colors' => ['required', 'array'],
                'section_colors.general' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'section_colors.other' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ];
        }

        $data = $request->validate($rules);
        $data['sections'] = isset($data['sections'])
            ? array_values($data['sections'])
            : array_map(fn (string $key) => [
                'key' => $key,
                'type' => 'section',
                'label' => $data['section_labels'][$key],
                'color' => $data['section_colors'][$key],
            ], ['general', 'other']);

        $data['items'] = array_values(array_filter($data['items'], fn (array $item) => $this->hasRowContent($item)));

        $sectionKeys = array_column($data['sections'], 'key');
        $hasMainSection = false;
        foreach ($data['sections'] as $section) {
            if ($section['type'] === 'section') {
                $hasMainSection = true;
            } elseif (! $hasMainSection) {
                throw ValidationException::withMessages(['sections' => 'Place a main section before its subheadings.']);
            }
        }
        foreach ($data['items'] as $item) {
            if (! in_array($item['section'], $sectionKeys, true)) {
                throw ValidationException::withMessages(['items' => 'A project row belongs to a missing section.']);
            }
        }

        if ($data['items'] === []) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one AIP project or activity before saving or submitting.',
            ]);
        }

        return $data;
    }

    public function visibleSections(array $data): array
    {
        $visible = [];
        $mainIndex = null;

        foreach ($data['sections'] as $section) {
            $section['items'] = array_values(array_filter($data['items'],
                fn (array $item) => $item['section'] === $section['key']));
            $section['visible'] = $section['items'] !== [];

            if ($section['type'] === 'section') {
                $mainIndex = count($visible);
            } elseif ($section['visible'] && $mainIndex !== null) {
                $visible[$mainIndex]['visible'] = true;
            }

            $visible[] = $section;
        }

        return array_values(array_filter($visible, fn (array $section) => $section['visible']));
    }

    public function validateDraft(array $input): array
    {
        $rules = [
            'year' => ['nullable', 'string', 'max:4'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'climate_change' => ['nullable', 'boolean'],
            'sections' => ['required', 'array', 'min:1', 'max:40'],
            'sections.*.key' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/', 'distinct'],
            'sections.*.type' => ['required', 'in:section,subheading'],
            'sections.*.label' => ['nullable', 'string', 'max:100'],
            'sections.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'items' => ['present', 'array', 'max:500'],
            'items.*.section' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'signers' => ['nullable', 'array'],
        ];

        foreach (['reference' => 100, 'description' => 500, 'office' => 200, 'start' => 80,
            'completion' => 80, 'output' => 500, 'funding' => 120, 'typology' => 100] as $field => $max) {
            $rules["items.*.{$field}"] = ['nullable', 'string', "max:{$max}"];
        }
        foreach (['ps', 'mooe', 'co', 'adaptation', 'mitigation'] as $field) {
            $rules["items.*.{$field}"] = ['nullable', 'string', 'max:30'];
        }
        foreach (array_keys(self::SLOTS) as $slot) {
            $rules["signers.{$slot}"] = ['nullable', 'integer'];
            $rules["{$slot}_name"] = ['nullable', 'string', 'max:120'];
            $rules["{$slot}_title"] = ['nullable', 'string', 'max:120'];
            $rules["{$slot}_date"] = ['nullable', 'string', 'max:80'];
        }

        $data = Validator::make($input, $rules)->validate();
        $data['sections'] = array_values(array_map(function (array $section): array {
            $section['label'] = $section['label'] ?? '';

            return $section;
        }, $data['sections']));
        $data['items'] = array_values($data['items']);
        $keys = array_column($data['sections'], 'key');
        $hasMainSection = false;
        foreach ($data['sections'] as $section) {
            if ($section['type'] === 'section') $hasMainSection = true;
            elseif (! $hasMainSection) {
                throw ValidationException::withMessages(['sections' => 'Place a main section before its subheadings.']);
            }
        }
        foreach ($data['items'] as $item) {
            if (! in_array($item['section'], $keys, true)) {
                throw ValidationException::withMessages(['items' => 'A project row belongs to a missing section.']);
            }
        }

        return $data;
    }

    public function signerAccounts(): array
    {
        $accounts = User::query()
            ->with('assignedOffice')
            ->whereIn('user_id', array_column(self::RECIPIENT_ACCOUNTS, 'user_id'))
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->keyBy('user_id');

        $signers = [];
        foreach (self::RECIPIENT_ACCOUNTS as $slot => $expected) {
            $account = $accounts->get($expected['user_id']);
            $signers[$slot] = $account?->role === $expected['role']
                && $account->assignedOffice?->code === $expected['office'] ? $account : null;
        }

        return $signers;
    }

    public function resolveSigners(): array
    {
        $signers = $this->signerAccounts();

        foreach (self::SLOTS as $slot => $label) {
            if (! $signers[$slot]) {
                throw ValidationException::withMessages([
                    "signers.{$slot}" => "The {$label} account (".self::RECIPIENT_ACCOUNTS[$slot]['user_id'].') is unavailable. Ask an administrator to check its status, role, and office.',
                ]);
            }
        }

        return $signers;
    }

    private function hasRowContent(array $item): bool
    {
        foreach (['reference', 'description', 'office', 'start', 'completion', 'output', 'funding', 'ps', 'mooe', 'co', 'adaptation', 'mitigation', 'typology'] as $field) {
            if (trim((string) ($item[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }
}
