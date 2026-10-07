<?php

namespace App\Http\Controllers;

use App\Models\AiDocumentMetadata;
use App\Models\DocumentAttachment;
use App\Services\AiMetadataExtractionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AiMetadataExtractionController extends Controller
{
    public function extract(
        Request $request,
        DocumentAttachment $attachment,
        AiMetadataExtractionService $service,
    ): JsonResponse {
        try {
            $metadata = $service->extractMetadata($request->user(), $attachment);

            return response()->json($this->payload($metadata));
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to extract metadata from this attachment.',
            ], 403);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ], 404);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Metadata extraction is temporarily unavailable.',
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Metadata extraction is temporarily unavailable.',
            ], 503);
        }
    }

    public function accept(
        Request $request,
        AiDocumentMetadata $metadata,
        AiMetadataExtractionService $service,
    ): JsonResponse {
        try {
            $metadata = $service->accept($request->user(), $metadata);

            return response()->json($this->payload($metadata, 'Metadata result accepted for manual reference.'));
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to review this metadata result.',
            ], 403);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to accept metadata result right now.',
            ], 503);
        }
    }

    public function reject(
        Request $request,
        AiDocumentMetadata $metadata,
        AiMetadataExtractionService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:240'],
        ]);

        try {
            $metadata = $service->reject($request->user(), $metadata, $validated['reason'] ?? null);

            return response()->json($this->payload($metadata, 'Metadata result rejected.'));
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to review this metadata result.',
            ], 403);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reject metadata result right now.',
            ], 503);
        }
    }

    private function payload(AiDocumentMetadata $metadata, ?string $message = null): array
    {
        $classification = $metadata->classification_result ?? [];

        return [
            'success' => true,
            'message' => $message,
            'metadata_id' => $metadata->id,
            'document_type' => $metadata->document_type,
            'document_id' => $metadata->document_id,
            'attachment_id' => $metadata->attachment_id,
            'status' => $metadata->status,
            'classification' => $classification,
            'classification_category' => $classification['category'] ?? null,
            'classification_confidence' => $classification['confidence'] ?? $metadata->confidence_score,
            'confidence_score' => $metadata->confidence_score,
            'extracted_metadata' => $metadata->extracted_metadata ?? [],
            'missing_fields' => $classification['missing_fields'] ?? [],
            'accept_url' => route('ai.metadata.accept', $metadata),
            'reject_url' => route('ai.metadata.reject', $metadata),
        ];
    }
}
