<?php

namespace App\Services;

use App\Models\AiDocumentMetadata;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class AiMetadataExtractionService
{
    private const SUPPORTED_CATEGORIES = [
        'Purchase Request',
        'BAC Resolution',
        'Purchase Order',
        'Request for Quotation',
        'Abstract of Quotations',
        'Annual Procurement Plan',
        'Supplemental Annual Procurement Plan',
        'Inspection / Acceptance',
        'Supporting Document',
    ];

    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly DocumentResolverService $documents,
    ) {
    }

    public function extractMetadata(User $user, DocumentAttachment $attachment): AiDocumentMetadata
    {
        $startedAt = microtime(true);
        $attachment->loadMissing(['uploadedBy', 'office', 'procurementDocument', 'attachable']);
        $document = $this->resolveDocument($attachment);

        if (! $this->canAccessAttachment($user, $attachment, $document)) {
            AuditLogger::denied('unauthorized_ai_metadata_extraction_attempt', [
                'module' => 'AI Metadata Extraction',
                'document_type' => $attachment->document_type,
                'document_id' => $attachment->document_id,
                'attachment_id' => $attachment->id,
                'user_id' => $user->id,
            ]);

            throw new AuthorizationException('You do not have permission to extract metadata from this attachment.');
        }

        $documentType = $this->canonicalDocumentType($attachment, $document);
        $documentId = $document?->getKey() ?: $attachment->document_id;
        $trackingNumber = $document ? $this->documents->displayTrackingNumber($document) : $attachment->tracking_number;

        AuditLogger::ai('ai_metadata_extraction_started', $document ?: $attachment, [
            'module' => 'AI Metadata Extraction',
            'document_type' => $documentType,
            'document_id' => $documentId,
            'attachment_id' => $attachment->id,
            'tracking_number' => $trackingNumber,
            'user_id' => $user->id,
            'execution_time' => 0,
        ]);

        Log::info('ai_metadata_extraction_started', [
            'user_id' => $user->id,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'attachment_id' => $attachment->id,
            'execution_time' => 0,
        ]);

        try {
            $textResult = $this->extractText($attachment);
            $response = null;
            $parsed = [];
            $usedOpenAi = false;

            if ($this->openAI->isConfigured()) {
                $response = $this->openAI->chat($this->messagesFor($attachment, $document, $documentType, $textResult), [
                    'temperature' => 0,
                    'max_tokens' => 550,
                    'response_format' => ['type' => 'json_object'],
                ]);
                $parsed = $this->parseJsonResponse($response);
                $usedOpenAi = true;
            } else {
                $parsed = $this->localFallbackResult($attachment, $document, $documentType, $textResult);
                $response = json_encode([
                    'mode' => 'rule_based_fallback',
                    'message' => 'OpenAI is not configured; metadata was classified from document context and file details only.',
                ]);
            }

            $classification = $this->classificationFrom($parsed, $attachment, $documentType, $textResult, $usedOpenAi);
            $metadata = $this->metadataFrom($parsed, $attachment, $document, $textResult);
            $confidence = $this->confidenceFrom($parsed, $classification, $textResult, $usedOpenAi);

            $record = AiDocumentMetadata::create([
                'document_type' => $documentType,
                'document_id' => $documentId,
                'attachment_id' => $attachment->id,
                'extracted_metadata' => $metadata,
                'classification_result' => $classification,
                'confidence_score' => $confidence,
                'status' => AiDocumentMetadata::STATUS_PENDING_REVIEW,
                'ai_response' => $response,
                'created_by_user_id' => $user->id,
            ]);

            $attachment->forceFill([
                'ai_analysis_status' => DocumentAttachment::AI_COMPLETED,
            ])->save();

            $executionTime = round(microtime(true) - $startedAt, 3);

            AuditLogger::ai('ai_metadata_extraction_completed', $document ?: $attachment, [
                'module' => 'AI Metadata Extraction',
                'document_type' => $documentType,
                'document_id' => $documentId,
                'attachment_id' => $attachment->id,
                'metadata_id' => $record->id,
                'tracking_number' => $trackingNumber,
                'classification' => $classification['category'] ?? null,
                'confidence_score' => $confidence,
                'used_openai' => $usedOpenAi,
                'user_id' => $user->id,
                'execution_time' => $executionTime,
            ]);

            Log::info('ai_metadata_extraction_completed', [
                'user_id' => $user->id,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'attachment_id' => $attachment->id,
                'metadata_id' => $record->id,
                'classification' => $classification['category'] ?? null,
                'confidence_score' => $confidence,
                'used_openai' => $usedOpenAi,
                'execution_time' => $executionTime,
            ]);

            return $record;
        } catch (Throwable $exception) {
            $executionTime = round(microtime(true) - $startedAt, 3);

            $record = AiDocumentMetadata::create([
                'document_type' => $documentType,
                'document_id' => $documentId,
                'attachment_id' => $attachment->id,
                'extracted_metadata' => [
                    'file_name' => $attachment->displayName(),
                    'extraction_note' => 'Metadata extraction did not complete.',
                ],
                'classification_result' => [
                    'category' => 'Supporting Document',
                    'confidence' => 0,
                    'missing_fields' => ['Metadata extraction is temporarily unavailable.'],
                ],
                'confidence_score' => 0,
                'status' => AiDocumentMetadata::STATUS_FAILED,
                'ai_response' => $exception::class . ': ' . $exception->getMessage(),
                'created_by_user_id' => $user->id,
            ]);

            $attachment->forceFill([
                'ai_analysis_status' => DocumentAttachment::AI_FAILED,
            ])->save();

            Log::error('AI metadata extraction failed.', [
                'user_id' => $user->id,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'attachment_id' => $attachment->id,
                'metadata_id' => $record->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'execution_time' => $executionTime,
            ]);

            AuditLogger::ai('ai_metadata_extraction_failed', $document ?: $attachment, [
                'module' => 'AI Metadata Extraction',
                'document_type' => $documentType,
                'document_id' => $documentId,
                'attachment_id' => $attachment->id,
                'metadata_id' => $record->id,
                'tracking_number' => $trackingNumber,
                'user_id' => $user->id,
                'severity' => 'warning',
                'failure' => $exception::class,
                'execution_time' => $executionTime,
            ]);

            throw new RuntimeException('Metadata extraction is temporarily unavailable.', previous: $exception);
        }
    }

    public function accept(User $user, AiDocumentMetadata $metadata): AiDocumentMetadata
    {
        $metadata->loadMissing('attachment');
        $document = $metadata->attachment ? $this->resolveDocument($metadata->attachment) : null;

        if (! $metadata->attachment || ! $this->canAccessAttachment($user, $metadata->attachment, $document)) {
            throw new AuthorizationException('You do not have permission to review this metadata result.');
        }

        $metadata->update([
            'status' => AiDocumentMetadata::STATUS_ACCEPTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        AuditLogger::ai('ai_metadata_accepted', $document ?: $metadata->attachment, [
            'module' => 'AI Metadata Extraction',
            'document_type' => $metadata->document_type,
            'document_id' => $metadata->document_id,
            'attachment_id' => $metadata->attachment_id,
            'metadata_id' => $metadata->id,
            'classification' => $metadata->classification_result['category'] ?? null,
            'confidence_score' => $metadata->confidence_score,
            'user_id' => $user->id,
        ]);

        return $metadata->fresh();
    }

    public function reject(User $user, AiDocumentMetadata $metadata, ?string $reason = null): AiDocumentMetadata
    {
        $metadata->loadMissing('attachment');
        $document = $metadata->attachment ? $this->resolveDocument($metadata->attachment) : null;

        if (! $metadata->attachment || ! $this->canAccessAttachment($user, $metadata->attachment, $document)) {
            throw new AuthorizationException('You do not have permission to review this metadata result.');
        }

        $classification = $metadata->classification_result ?? [];
        $classification['review_note'] = filled($reason) ? Str::limit((string) $reason, 240, '') : null;

        $metadata->update([
            'status' => AiDocumentMetadata::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'classification_result' => $classification,
        ]);

        AuditLogger::ai('ai_metadata_rejected', $document ?: $metadata->attachment, [
            'module' => 'AI Metadata Extraction',
            'document_type' => $metadata->document_type,
            'document_id' => $metadata->document_id,
            'attachment_id' => $metadata->attachment_id,
            'metadata_id' => $metadata->id,
            'classification' => $metadata->classification_result['category'] ?? null,
            'confidence_score' => $metadata->confidence_score,
            'has_reason' => filled($reason),
            'user_id' => $user->id,
        ]);

        return $metadata->fresh();
    }

    private function resolveDocument(DocumentAttachment $attachment): ?Model
    {
        try {
            return $this->documents->resolveAttachmentDocument($attachment);
        } catch (Throwable) {
            return null;
        }
    }

    private function canAccessAttachment(User $user, DocumentAttachment $attachment, ?Model $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($document && $this->documents->canView($user, $document)) {
            return true;
        }

        if ((int) $attachment->uploaded_by_user_id === (int) $user->id) {
            return true;
        }

        return $attachment->office_id
            && $user->office_id
            && (int) $attachment->office_id === (int) $user->office_id;
    }

    private function canonicalDocumentType(DocumentAttachment $attachment, ?Model $document): ?string
    {
        if ($document) {
            return $this->documents->documentTypeFor($document, $attachment->document_type);
        }

        return $attachment->document_type
            ? $this->documents->normalizeDocumentType($attachment->document_type)
            : null;
    }

    private function extractText(DocumentAttachment $attachment): array
    {
        $extension = Str::lower((string) ($attachment->file_extension ?: pathinfo((string) $attachment->file_path, PATHINFO_EXTENSION)));
        $ocrText = $this->cleanText((string) ($attachment->ocr_text ?? ''));

        if ($ocrText !== '') {
            return [
                'text' => $this->limitText($ocrText),
                'method' => 'stored_ocr_text',
                'note' => null,
            ];
        }

        $path = $this->localPathFor($attachment);

        if (! $path || ! is_file($path)) {
            return [
                'text' => '',
                'method' => 'file_unavailable',
                'note' => 'The uploaded file is not available on local storage.',
            ];
        }

        return match ($extension) {
            'pdf' => [
                'text' => $this->limitText($this->extractTextFromPdf($path)),
                'method' => 'text_based_pdf',
                'note' => 'Phase 1 supports best-effort text extraction from text-based PDFs.',
            ],
            'docx' => [
                'text' => $this->limitText($this->extractTextFromDocx($path)),
                'method' => 'docx_xml',
                'note' => 'Phase 1 supports text extraction from DOCX files.',
            ],
            'txt', 'csv' => [
                'text' => $this->limitText($this->cleanText((string) @file_get_contents($path))),
                'method' => 'plain_text',
                'note' => null,
            ],
            'doc' => [
                'text' => '',
                'method' => 'legacy_doc_placeholder',
                'note' => 'Legacy DOC text extraction is not available in Phase 1.',
            ],
            'jpg', 'jpeg', 'png', 'gif', 'webp' => [
                'text' => '',
                'method' => 'image_ocr_placeholder',
                'note' => 'Image OCR is prepared for future integration and is not available in Phase 1.',
            ],
            default => [
                'text' => '',
                'method' => 'unsupported_file_type',
                'note' => 'This file type is stored for reference; text extraction is not available in Phase 1.',
            ],
        };
    }

    private function localPathFor(DocumentAttachment $attachment): ?string
    {
        if (! filled($attachment->file_path)) {
            return null;
        }

        try {
            $disk = $attachment->readableStorageDisk();

            if (! Storage::disk($disk)->exists($attachment->file_path)) {
                return null;
            }

            return Storage::disk($disk)->path($attachment->file_path);
        } catch (Throwable) {
            return null;
        }
    }

    private function extractTextFromDocx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === '') {
            return '';
        }

        $xml = preg_replace('/<\/w:p>/i', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:tab\s*\/>/i', "\t", $xml) ?? $xml;

        return $this->cleanText(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function extractTextFromPdf(string $path): string
    {
        $content = @file_get_contents($path, false, null, 0, 1200000);

        if (! is_string($content) || $content === '') {
            return '';
        }

        $text = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*Tj/s', $content, $matches)) {
            foreach ($matches[0] as $chunk) {
                if (preg_match('/\((.*)\)\s*Tj/s', $chunk, $stringMatch)) {
                    $text[] = $this->decodePdfString($stringMatch[1]);
                }
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $arrayMatches)) {
            foreach ($arrayMatches[1] as $chunk) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $chunk, $strings)) {
                    foreach ($strings[0] as $string) {
                        $text[] = $this->decodePdfString(trim($string, '()'));
                    }
                }
            }
        }

        if ($text === []) {
            $plain = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]+/', ' ', $content) ?? $content;
            $plain = preg_replace('/[^A-Za-z0-9\.\,\:\;\-\_\(\)\/\s]+/', ' ', $plain) ?? $plain;
            $text[] = $plain;
        }

        return $this->cleanText(implode(' ', $text));
    }

    private function decodePdfString(string $value): string
    {
        $value = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $value);
        $value = preg_replace_callback('/\\\\([nrtbf])/', function (array $match) {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                default => ' ',
            };
        }, $value) ?? $value;

        return $value;
    }

    private function messagesFor(DocumentAttachment $attachment, ?Model $document, ?string $documentType, array $textResult): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'You are PaperTrail AI Metadata Extraction and Document Classification. Analyze only the provided file text and document context. Return only valid JSON. Do not return markdown. Do not invent unavailable information; use null for unknown values. AI output is advisory only and must not approve, reject, route, submit, or modify procurement documents.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'expected_json_format' => [
                        'document_type' => null,
                        'confidence_score' => 0,
                        'classification' => [
                            'category' => null,
                            'confidence' => 0,
                        ],
                        'metadata' => [
                            'document_number' => null,
                            'document_date' => null,
                            'fiscal_year' => null,
                            'office' => null,
                            'supplier' => null,
                            'total_amount' => null,
                            'procurement_mode' => null,
                            'purpose_or_title' => null,
                            'source_document_number' => null,
                        ],
                        'missing_fields' => [],
                    ],
                    'allowed_classification_categories' => self::SUPPORTED_CATEGORIES,
                    'document_context' => [
                        'document_type_hint' => $documentType,
                        'document_id' => $document?->getKey(),
                        'tracking_number' => $document ? $this->documents->displayTrackingNumber($document) : $attachment->tracking_number,
                        'office_name' => $document ? $this->documents->officeNameFor($document) : $attachment->office_name,
                        'document_status' => $document->status ?? null,
                    ],
                    'file_context' => [
                        'file_name' => $attachment->displayName(),
                        'category' => $attachment->attachment_category,
                        'mime_type' => $attachment->mime_type,
                        'extension' => $attachment->file_extension,
                        'text_extraction_method' => $textResult['method'] ?? null,
                        'text_extraction_note' => $textResult['note'] ?? null,
                    ],
                    'extracted_text_excerpt' => $textResult['text'] ?: null,
                    'instruction' => 'Return JSON only. If the text does not contain a value, return null. Prefer Purchase Request, BAC Resolution, and Purchase Order when the text clearly matches those documents; otherwise classify as the closest allowed category or Supporting Document.',
                ], JSON_UNESCAPED_SLASHES),
            ],
        ];
    }

    private function parseJsonResponse(string $response): array
    {
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        if (! str_starts_with($clean, '{')) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $clean = substr($clean, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned an invalid metadata JSON response.');
        }

        return $decoded;
    }

    private function localFallbackResult(DocumentAttachment $attachment, ?Model $document, ?string $documentType, array $textResult): array
    {
        return [
            'document_type' => $this->labelForDocumentType($documentType),
            'confidence_score' => $textResult['text'] ? 45 : 20,
            'classification' => [
                'category' => $this->labelForDocumentType($documentType) ?: $this->classifyFromFilename($attachment),
                'confidence' => $textResult['text'] ? 45 : 20,
            ],
            'metadata' => [
                'document_number' => $document ? $this->documents->displayTrackingNumber($document) : $attachment->tracking_number,
                'document_date' => null,
                'fiscal_year' => $document->fiscal_year ?? null,
                'office' => $document ? $this->documents->officeNameFor($document) : $attachment->office_name,
                'supplier' => null,
                'total_amount' => null,
                'procurement_mode' => null,
                'purpose_or_title' => $document->title ?? $document->purpose ?? null,
                'source_document_number' => null,
            ],
            'missing_fields' => [$textResult['note'] ?: 'OpenAI is not configured; please review metadata manually.'],
        ];
    }

    private function classificationFrom(array $parsed, DocumentAttachment $attachment, ?string $documentType, array $textResult, bool $usedOpenAi): array
    {
        $classification = Arr::get($parsed, 'classification', []);
        $category = Arr::get($classification, 'category')
            ?: Arr::get($parsed, 'document_type')
            ?: $this->labelForDocumentType($documentType)
            ?: $this->classifyFromFilename($attachment);

        $category = $this->normalizeCategory((string) $category);
        $confidence = $this->clampScore(Arr::get($classification, 'confidence', Arr::get($parsed, 'confidence_score')));
        $confidence = $confidence ?: ($usedOpenAi ? 50 : ($textResult['text'] ? 45 : 20));

        return [
            'category' => $category,
            'confidence' => $confidence,
            'missing_fields' => $this->uniqueList(Arr::wrap($parsed['missing_fields'] ?? Arr::get($classification, 'missing_fields', []))),
            'extraction_method' => $textResult['method'] ?? null,
            'extraction_note' => $textResult['note'] ?? null,
            'supported_phase' => in_array($category, ['Purchase Request', 'BAC Resolution', 'Purchase Order'], true)
                ? 'phase_1_supported'
                : 'phase_1_prepared',
        ];
    }

    private function metadataFrom(array $parsed, DocumentAttachment $attachment, ?Model $document, array $textResult): array
    {
        $metadata = Arr::get($parsed, 'metadata', []);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        return array_merge([
            'file_name' => $attachment->displayName(),
            'attachment_category' => $attachment->attachment_category,
            'source_office' => $document ? $this->documents->officeNameFor($document) : $attachment->office_name,
            'source_tracking_number' => $document ? $this->documents->displayTrackingNumber($document) : $attachment->tracking_number,
            'text_extraction_method' => $textResult['method'] ?? null,
            'text_extraction_note' => $textResult['note'] ?? null,
        ], Arr::only($metadata, [
            'document_number',
            'document_date',
            'fiscal_year',
            'office',
            'supplier',
            'total_amount',
            'procurement_mode',
            'purpose_or_title',
            'source_document_number',
        ]));
    }

    private function confidenceFrom(array $parsed, array $classification, array $textResult, bool $usedOpenAi): int
    {
        $score = $this->clampScore($parsed['confidence_score'] ?? $classification['confidence'] ?? null);

        if ($score > 0) {
            return $score;
        }

        if (! $usedOpenAi) {
            return $textResult['text'] ? 45 : 20;
        }

        return $textResult['text'] ? 60 : 35;
    }

    private function classifyFromFilename(DocumentAttachment $attachment): string
    {
        $name = Str::lower($attachment->displayName());

        return match (true) {
            str_contains($name, 'purchase request') || preg_match('/\bpr[-_ ]?\d/i', $name) => 'Purchase Request',
            str_contains($name, 'resolution') || str_contains($name, 'bac res') => 'BAC Resolution',
            str_contains($name, 'purchase order') || preg_match('/\bpo[-_ ]?\d/i', $name) => 'Purchase Order',
            str_contains($name, 'rfq') || str_contains($name, 'quotation') => 'Request for Quotation',
            str_contains($name, 'abstract') => 'Abstract of Quotations',
            str_contains($name, 'supplemental') => 'Supplemental Annual Procurement Plan',
            str_contains($name, 'app') => 'Annual Procurement Plan',
            str_contains($name, 'inspection') || str_contains($name, 'acceptance') => 'Inspection / Acceptance',
            default => 'Supporting Document',
        };
    }

    private function labelForDocumentType(?string $documentType): ?string
    {
        return match ($documentType) {
            'purchase_request' => 'Purchase Request',
            'bac_resolution' => 'BAC Resolution',
            'purchase_order' => 'Purchase Order',
            'rfq' => 'Request for Quotation',
            'abstract' => 'Abstract of Quotations',
            'app' => 'Annual Procurement Plan',
            'supplemental_app' => 'Supplemental Annual Procurement Plan',
            'inspection_acceptance' => 'Inspection / Acceptance',
            'ppmp', 'ppmp_record' => 'Project Procurement Management Plan',
            default => $documentType ? Str::headline($documentType) : null,
        };
    }

    private function normalizeCategory(string $category): string
    {
        $normalized = Str::lower($category);

        foreach (self::SUPPORTED_CATEGORIES as $allowed) {
            if ($normalized === Str::lower($allowed)) {
                return $allowed;
            }
        }

        return match (true) {
            str_contains($normalized, 'purchase request') || $normalized === 'pr' => 'Purchase Request',
            str_contains($normalized, 'resolution') => 'BAC Resolution',
            str_contains($normalized, 'purchase order') || $normalized === 'po' => 'Purchase Order',
            str_contains($normalized, 'rfq') || str_contains($normalized, 'quotation') => 'Request for Quotation',
            str_contains($normalized, 'abstract') => 'Abstract of Quotations',
            str_contains($normalized, 'supplemental') => 'Supplemental Annual Procurement Plan',
            str_contains($normalized, 'annual procurement plan') || $normalized === 'app' => 'Annual Procurement Plan',
            str_contains($normalized, 'inspection') || str_contains($normalized, 'acceptance') => 'Inspection / Acceptance',
            default => 'Supporting Document',
        };
    }

    private function clampScore(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function limitText(string $text): string
    {
        return Str::limit($this->cleanText($text), 8000, '');
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[[:cntrl:]]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function uniqueList(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
