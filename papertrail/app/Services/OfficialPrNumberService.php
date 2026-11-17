<?php

namespace App\Services;

use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficialPrNumberService
{
    private const FUND_PATTERNS = [
        '101' => '/\b(?:101|general\s*funds?|gf)\b/i',
        '401' => '/\b(?:401|trust\s*funds?|tf)\b/i',
        '221' => '/\b(?:221|special\s*(?:education\s*)?funds?|school\s*funds?|sef)\b/i',
    ];

    public function fundSource(ProcurementDocument $document): string
    {
        return trim((string) (filled($document->fund_cluster) ? $document->fund_cluster : $document->fund_source));
    }

    public function fundCode(ProcurementDocument $document): ?string
    {
        $source = preg_replace('/[_-]+/', ' ', $this->fundSource($document));
        $matches = [];
        foreach (self::FUND_PATTERNS as $code => $pattern) {
            if (preg_match($pattern, $source)) {
                $matches[] = (string) $code;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    public function example(ProcurementDocument $document, ?string $date = null): ?string
    {
        $code = $this->fundCode($document);
        if (! $code) {
            return null;
        }
        try {
            $period = ($date ? Carbon::parse($date) : now('Asia/Manila'))->format('Y-m');
        } catch (\Throwable) {
            $period = now('Asia/Manila')->format('Y-m');
        }

        return $code.'-'.$period.'-001';
    }

    public function preview(ProcurementDocument $document, string $date): ?string
    {
        $code = $this->fundCode($document);
        if (! $code) {
            return null;
        }

        $period = Carbon::parse($date);
        $last = (int) DB::table('pr_number_sequences')
            ->where('fiscal_year', $period->year)
            ->where('prefix', $code.'-'.$period->year.'-')
            ->max('last_sequence');

        return $this->formatNumber($code, $period, max($last, $this->highestExistingSequence($code, $period->year)) + 1);
    }

    /** Reserve a number inside the same transaction that saves the PR. */
    public function nextNumber(ProcurementDocument $document, string $date, User $user): string
    {
        $code = $this->fundCode($document);
        if (! $code) {
            throw ValidationException::withMessages([
                'pr_no' => 'Return this PR for correction of its fund source: General Fund (101), Trust Fund (401), or Special Fund / SEF (221).',
            ]);
        }

        $period = Carbon::parse($date);
        $prefix = $code.'-'.$period->year.'-';

        return DB::transaction(function () use ($code, $period, $prefix, $user): string {
            // The unique fund/year key makes the first allocation safe too.
            DB::table('pr_number_sequences')->insertOrIgnore([
                'fiscal_year' => $period->year,
                'office_id' => null,
                'prefix' => $prefix,
                'last_sequence' => 0,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('pr_number_sequences')
                ->where('fiscal_year', $period->year)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            // Older manually numbered PRs may predate the saved counter.
            $next = max((int) $counter->last_sequence, $this->highestExistingSequence($code, $period->year)) + 1;
            $number = $this->formatNumber($code, $period, $next);

            DB::table('pr_number_sequences')->where('id', $counter->id)->update([
                'last_sequence' => $next,
                'updated_by_user_id' => $user->id,
                'updated_at' => now(),
            ]);

            return $number;
        });
    }

    private function highestExistingSequence(string $code, int $year): int
    {
        $highest = 0;

        DB::table('procurement_documents')->where('pr_no', 'like', $code.'-'.$year.'-%')
            ->select(['id', 'pr_no'])
            ->chunkById(500, function ($documents) use (&$highest): void {
                foreach ($documents as $document) {
                    $parts = $this->parts(trim($document->pr_no));
                    if ($parts) {
                        $highest = max($highest, $parts['sequence']);
                    }
                }
            });

        return $highest;
    }

    private function formatNumber(string $code, Carbon $date, int $sequence): string
    {
        if ($sequence > 999999999) {
            throw ValidationException::withMessages(['pr_no' => 'The PR numbering sequence for this fund and year is full.']);
        }

        return sprintf('%s-%s-%03d', $code, $date->format('Y-m'), $sequence);
    }

    public function parts(string $number): ?array
    {
        if (! preg_match('/\A(101|401|221)-(\d{4})-(0[1-9]|1[0-2])-(\d{3,9})\z/', $number, $matches)
            || (int) $matches[4] < 1) {
            return null;
        }

        return ['fund' => $matches[1], 'year' => (int) $matches[2], 'month' => (int) $matches[3], 'sequence' => (int) $matches[4]];
    }

    public function validationError(ProcurementDocument $document, string $number, mixed $date): ?string
    {
        $code = $this->fundCode($document);
        if (! $code) {
            return 'The PR must specify General Fund, Trust Fund, or Special Fund before an official PR number can be assigned.';
        }
        $parts = $this->parts($number);
        if (! $parts) {
            return 'Use '.$code.'-YYYY-MM-### with a valid month and a positive sequence of at least three digits.';
        }
        if ($parts['fund'] !== $code) {
            return 'This PR uses '.$this->fundSource($document).'. Its official PR number must start with '.$code.'-.';
        }
        // The date has its own required/date rules; avoid obscuring those errors.
        if (! is_string($date) || trim($date) === '') {
            return null;
        }
        try {
            $officialDate = Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
        if ($parts['year'] !== $officialDate->year || $parts['month'] !== $officialDate->month) {
            return 'The year and month in the PR number must match the Official PR Date ('.$officialDate->format('Y-m').').';
        }

        return null;
    }
}
