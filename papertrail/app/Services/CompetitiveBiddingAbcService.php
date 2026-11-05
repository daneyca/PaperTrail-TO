<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompetitiveBiddingAbcService
{
    public const SLOTS = [
        'prepared' => 'Prepared / Submitted by',
        'checked' => 'Checked by',
        'recommended' => 'Recommending Approval',
        'approved' => 'Approved',
    ];

    public const RECIPIENT_ACCOUNTS = [
        'checked' => 'BACSEC-002',
        'recommended' => 'BACCHAIR-001',
        'approved' => 'MO-001',
    ];

    public const FIELDS = [
        'item_no', 'description', 'quantity', 'unit', 'direct_cost',
        'ocm_percent', 'profit_percent', 'mob_demob_percent', 'markup_percent',
        'markup_value', 'vat', 'total_indirect', 'unit_cost', 'total_cost',
    ];

    private const MONEY_FIELDS = ['direct_cost', 'markup_value', 'vat', 'total_indirect', 'unit_cost', 'total_cost'];
    private const PERCENT_FIELDS = ['ocm_percent', 'profit_percent', 'mob_demob_percent', 'markup_percent'];

    public function validateDraft(array $input): array
    {
        $rules = [
            'project' => ['nullable', 'string', 'max:300'],
            'location' => ['nullable', 'string', 'max:200'],
            'items' => ['present', 'array', 'max:500'],
        ];
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
        $rules = [
            'project' => ['required', 'string', 'max:300'],
            'location' => ['required', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
        ];
        foreach (self::FIELDS as $field) {
            $rules["items.*.{$field}"] = in_array($field, [...self::MONEY_FIELDS, ...self::PERCENT_FIELDS, 'quantity'], true)
                ? ['nullable', 'numeric', 'min:0'] : ['nullable', 'string', 'max:500'];
        }
        foreach (array_keys(self::SLOTS) as $slot) {
            $rules["{$slot}_name"] = [$slot === 'prepared' ? 'required' : 'nullable', 'string', 'max:120'];
            $rules["{$slot}_title"] = [$slot === 'prepared' ? 'required' : 'nullable', 'string', 'max:120'];
        }
        $data = $request->validate($rules);
        $data['items'] = array_values(array_filter($data['items'], fn ($item) => $this->hasContent($item)));
        if ($data['items'] === []) {
            throw ValidationException::withMessages(['items' => 'Enter at least one ABC item before submitting.']);
        }
        foreach ($data['items'] as $index => &$item) {
            if (blank($item['description'] ?? null) || blank($item['unit'] ?? null)
                || ! isset($item['quantity']) || ! isset($item['unit_cost'])) {
                throw ValidationException::withMessages(['items' => 'Every item needs a description, quantity, unit, and unit cost.']);
            }
            $item['item_no'] = trim((string) ($item['item_no'] ?? '')) ?: (string) ($index + 1);
            $item['total_cost'] = round((float) $item['quantity'] * (float) $item['unit_cost'], 2);
        }
        unset($item);

        return $data;
    }

    public function signerAccounts(): array
    {
        $users = User::query()->where('status', User::STATUS_ACTIVE)
            ->whereIn('user_id', array_values(self::RECIPIENT_ACCOUNTS))->get();
        $byCode = $users->keyBy('user_id');
        $signers = [];
        foreach (self::RECIPIENT_ACCOUNTS as $slot => $code) $signers[$slot] = $byCode->get($code);

        return $signers;
    }

    public function resolveSigners(): array
    {
        $signers = $this->signerAccounts();
        foreach (self::RECIPIENT_ACCOUNTS as $slot => $code) {
            if (! $signers[$slot]) throw ValidationException::withMessages([$slot.'_name' => "The {$code} signing account is unavailable."]);
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
