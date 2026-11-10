<?php

namespace App\Services;

use App\Models\ProcurementDocument;
use Illuminate\Support\Carbon;

class OfficialPrNumberService
{
    private const FUND_PATTERNS = [
        '101' => '/\b(?:101|general\s*fund|gf)\b/i',
        '401' => '/\b(?:401|trust\s*fund|tf)\b/i',
        '221' => '/\b(?:221|special\s*(?:education\s*)?fund|school\s*fund|sef)\b/i',
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
