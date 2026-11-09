<?php

namespace App\Services;

use App\Models\Office;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentReferenceNumberService
{
    public const TYPE_PPMP = 'PPMP';
    public const TYPE_APP = 'APP';
    public const TYPE_PR = 'PR';
    public const TYPE_BAC = 'BAC';
    public const TYPE_RFQ = 'RFQ';
    public const TYPE_AOQ = 'AOQ';
    public const TYPE_PO = 'PO';
    public const TYPE_IA = 'IA';

    public function assign(Model $document, string $documentType, ?CarbonInterface $createdAt = null): void
    {
        if (filled($document->getAttribute('document_reference_number'))) {
            return;
        }

        $type = self::normalizeType($documentType);
        $date = $createdAt ?: $this->referenceDate($document);
        $year = (int) $date->format('Y');
        $month = (int) $date->format('m');
        $officeId = (int) ($document->getAttribute('submitting_office_id') ?: $document->getAttribute('office_id'));
        if ($type === self::TYPE_PPMP && $officeId > 0) {
            $sequence = $this->nextPpmpSequence($officeId, $year);
            $code = strtoupper(trim((string) Office::whereKey($officeId)->value('code')));
            $officeCode = preg_match('/^[A-Z0-9_-]+$/', $code) ? $code : 'OFFICE-' . $officeId;
            $referenceNumber = sprintf('%04d-%02d-PPMP-%s-%04d', $year, $month, $officeCode, $sequence);
        } else {
            $sequence = $this->nextSequence($type, $year, $month);
            $referenceNumber = sprintf('%04d-%02d-%s-%04d', $year, $month, $type, $sequence);
        }

        $document->setAttribute('document_reference_number', $referenceNumber);
        $document->setAttribute('sequence_number', $sequence);
        $document->setAttribute('created_year', $year);
        $document->setAttribute('created_month', $month);

        if (blank($document->getAttribute('document_type'))) {
            $document->setAttribute('document_type', $type);
        }

        $attributes = $document->getAttributes();

        if ($document->isFillable('tracking_number') && blank($attributes['tracking_number'] ?? null)) {
            $document->setAttribute('tracking_number', $referenceNumber);
        }
    }

    public static function normalizeType(?string $documentType): string
    {
        $type = Str::of((string) $documentType)
            ->lower()
            ->replace(['-', '/'], ' ')
            ->replace('_', ' ')
            ->squish()
            ->toString();

        return match ($type) {
            'ppmp', 'project procurement management plan' => self::TYPE_PPMP,
            'app', 'annual procurement plan', 'app consolidation' => self::TYPE_APP,
            'pr', 'purchase request' => self::TYPE_PR,
            'bac', 'bac resolution', 'resolution' => self::TYPE_BAC,
            'rfq', 'request for quotation' => self::TYPE_RFQ,
            'aoq', 'abstract', 'abstract quotation', 'abstract of quotation' => self::TYPE_AOQ,
            'po', 'purchase order' => self::TYPE_PO,
            'ia', 'inspection acceptance', 'inspection / acceptance', 'inspection and acceptance' => self::TYPE_IA,
            default => Str::upper(Str::slug($documentType ?: 'DOC', '')),
        };
    }

    public static function labelForType(string $type): string
    {
        return match (self::normalizeType($type)) {
            self::TYPE_PPMP => 'PPMP',
            self::TYPE_APP => 'Annual Procurement Plan',
            self::TYPE_PR => 'Purchase Request',
            self::TYPE_BAC => 'BAC Resolution',
            self::TYPE_RFQ => 'RFQ',
            self::TYPE_AOQ => 'Abstract of Quotation',
            self::TYPE_PO => 'Purchase Order',
            self::TYPE_IA => 'Inspection / Acceptance',
            default => $type,
        };
    }

    protected function nextSequence(string $type, int $year, int $month): int
    {
        return DB::transaction(function () use ($type, $year, $month): int {
            $sequences = DB::table('document_reference_sequences')
                ->where('document_type', $type)
                ->where('created_year', $year)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($sequences->isEmpty()) {
                DB::table('document_reference_sequences')->insert([
                    'document_type' => $type,
                    'created_year' => $year,
                    'created_month' => $month,
                    'last_sequence' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $sequence = $sequences->first();
            $next = ((int) $sequences->max('last_sequence')) + 1;

            if ($sequences->count() > 1) {
                DB::table('document_reference_sequences')
                    ->whereIn('id', $sequences->skip(1)->pluck('id')->all())
                    ->delete();
            }

            DB::table('document_reference_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'created_month' => $month,
                    'last_sequence' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });
    }

    private function nextPpmpSequence(int $officeId, int $year): int
    {
        return DB::transaction(function () use ($officeId, $year): int {
            DB::table('ppmp_reference_sequences')->insertOrIgnore([
                'office_id' => $officeId, 'created_year' => $year, 'last_sequence' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $counter = DB::table('ppmp_reference_sequences')->where('office_id', $officeId)
                ->where('created_year', $year)->lockForUpdate()->first();
            $next = (int) $counter->last_sequence + 1;
            DB::table('ppmp_reference_sequences')->where('id', $counter->id)
                ->update(['last_sequence' => $next, 'updated_at' => now()]);
            return $next;
        });
    }

    protected function referenceDate(Model $document): CarbonInterface
    {
        $createdAt = $document->getAttribute('created_at');

        if ($createdAt instanceof CarbonInterface) {
            return $createdAt;
        }

        if (filled($createdAt)) {
            return Carbon::parse($createdAt);
        }

        return now();
    }
}
