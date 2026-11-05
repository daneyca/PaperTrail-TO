<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompetitiveBiddingAppService
{
    public const SLOTS = [
        'prepared' => 'Prepared by',
        'recommended' => 'Recommending Approval',
        'certified' => 'Certified Funds Available',
        'approved' => 'Approval',
    ];

    public const ACCOUNTS = [
        'prepared' => 'BACSEC-002',
        'recommended' => 'BACCHAIR-001',
        'certified' => 'MBO-001',
        'approved' => 'MO-001',
    ];

    public const FIELDS = ['code', 'project', 'end_user', 'mode', 'ads', 'bids', 'award', 'contract', 'funds', 'budget', 'personnel', 'mooe', 'co', 'remarks'];

    public function validateDraft(array $input): array
    {
        $rules = ['year' => ['nullable', 'string', 'max:4'], 'items' => ['present', 'array', 'max:500']];
        foreach (self::FIELDS as $field) {
            $rules["items.*.{$field}"] = ['nullable', 'string', 'max:500'];
        }
        foreach (array_keys(self::SLOTS) as $slot) {
            $rules["{$slot}_name"] = ['nullable', 'string', 'max:120'];
            $rules["{$slot}_title"] = ['nullable', 'string', 'max:120'];
        }

        $data = Validator::make($input, $rules)->validate();
        $data['items'] = array_values($data['items']);

        return $data;
    }

    public function validateDocument(Request $request): array
    {
        $rules = ['year' => ['required', 'integer', 'between:2000,2100'], 'items' => ['required', 'array', 'min:1', 'max:500']];
        foreach (self::FIELDS as $field) {
            $rules["items.*.{$field}"] = in_array($field, ['budget', 'personnel', 'mooe', 'co'], true)
                ? ['nullable', 'numeric', 'min:0'] : ['nullable', 'string', 'max:500'];
        }
        foreach (array_keys(self::SLOTS) as $slot) {
            $rules["{$slot}_name"] = ['nullable', 'string', 'max:120'];
            $rules["{$slot}_title"] = ['nullable', 'string', 'max:120'];
        }
        $data = $request->validate($rules);
        $data['items'] = array_values(array_filter($data['items'], fn ($item) => $this->hasContent($item)));
        if ($data['items'] === []) {
            throw ValidationException::withMessages(['items' => 'Enter at least one procurement project before submitting.']);
        }
        foreach ($data['items'] as $item) {
            if (trim((string) ($item['project'] ?? '')) === '') {
                throw ValidationException::withMessages(['items' => 'Each procurement row needs a project name.']);
            }
        }

        return $data;
    }

    public function signerAccounts(): array
    {
        $users = User::query()->whereIn('user_id', array_values(self::ACCOUNTS))
            ->where('status', User::STATUS_ACTIVE)->get()->keyBy('user_id');

        return array_map(fn ($id) => $users->get($id), self::ACCOUNTS);
    }

    public function resolveSigners(): array
    {
        $signers = $this->signerAccounts();
        foreach ($signers as $slot => $user) {
            if (! $user) {
                throw ValidationException::withMessages([$slot.'_name' => 'The '.self::ACCOUNTS[$slot].' signing account is unavailable.']);
            }
        }

        return $signers;
    }

    private function hasContent(array $item): bool
    {
        foreach (self::FIELDS as $field) {
            if (trim((string) ($item[$field] ?? '')) !== '') return true;
        }

        return false;
    }
}
