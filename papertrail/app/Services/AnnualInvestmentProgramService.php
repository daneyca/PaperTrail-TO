<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnnualInvestmentProgramService
{
    public const SLOTS = [
        'prepared' => 'Prepared by',
        'reviewed' => 'Reviewed by',
        'approved' => 'Approved by',
    ];

    public function validateDocument(Request $request): array
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'municipality' => ['required', 'string', 'max:120'],
            'climate_change' => ['nullable', 'boolean'],
            'section_labels' => ['required', 'array'],
            'section_labels.general' => ['required', 'string', 'max:100'],
            'section_labels.other' => ['required', 'string', 'max:100'],
            'section_colors' => ['required', 'array'],
            'section_colors.general' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'section_colors.other' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.section' => ['required', 'in:general,other'],
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
        ]);

        $data['items'] = array_values(array_filter($data['items'], fn (array $item) => $this->hasRowContent($item)));

        if ($data['items'] === []) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one AIP project or activity before saving or submitting.',
            ]);
        }

        return $data;
    }

    public function signerCandidates(): array
    {
        $active = fn () => User::query()->where('status', User::STATUS_ACTIVE);
        $office = fn (Builder $query, string $code) => $query->whereHas('assignedOffice', fn (Builder $officeQuery) => $officeQuery->where('code', $code));

        return [
            'prepared' => $active()->where('role', User::ROLE_HEAD_OFFICE)
                ->where(fn (Builder $query) => $office($query, 'MPDO'))
                ->orderBy('user_id')->get(['id', 'user_id', 'name', 'role', 'office_id']),
            'reviewed' => $active()->where(fn (Builder $query) => $query
                ->where('role', User::ROLE_BUDGET)
                ->orWhere(fn (Builder $headOffice) => $headOffice
                    ->where('role', User::ROLE_HEAD_OFFICE)
                    ->whereHas('assignedOffice', fn (Builder $officeQuery) => $officeQuery->where('code', 'MBO'))))
                ->orderBy('user_id')->get(['id', 'user_id', 'name', 'role', 'office_id']),
            'approved' => $active()->where(fn (Builder $query) => $query
                ->where('role', User::ROLE_APPROVING_AUTHORITY)
                ->orWhere(fn (Builder $headOffice) => $headOffice
                    ->where('role', User::ROLE_HEAD_OFFICE)
                    ->whereHas('assignedOffice', fn (Builder $officeQuery) => $officeQuery->where('code', 'MO'))))
                ->orderBy('user_id')->get(['id', 'user_id', 'name', 'role', 'office_id']),
        ];
    }

    public function resolveSigners(array $ids): array
    {
        $candidates = $this->signerCandidates();
        $resolved = [];

        foreach (self::SLOTS as $slot => $label) {
            $id = filter_var($ids[$slot] ?? null, FILTER_VALIDATE_INT);
            $user = $id ? $candidates[$slot]->firstWhere('id', $id) : null;

            if (! $user) {
                throw ValidationException::withMessages([
                    "signers.{$slot}" => "Select an active {$label} account from the list.",
                ]);
            }

            $resolved[$slot] = $user;
        }

        if (count(array_unique(array_map(fn (User $user) => $user->id, $resolved))) !== count($resolved)) {
            throw ValidationException::withMessages(['signers' => 'Choose a different account for each signatory.']);
        }

        return $resolved;
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
