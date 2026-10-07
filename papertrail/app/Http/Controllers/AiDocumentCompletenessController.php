<?php

namespace App\Http\Controllers;

use App\Services\AiDocumentCompletenessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AiDocumentCompletenessController extends Controller
{
    public function check(Request $request, AiDocumentCompletenessService $service): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:80'],
            'document_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $check = $service->checkDocumentCompleteness(
                $request->user(),
                $validated['document_type'],
                (int) $validated['document_id']
            );

            return response()->json([
                'success' => true,
                'check_id' => $check->id,
                'document_type' => $check->document_type,
                'tracking_number' => $check->document_tracking_number,
                'score' => $check->completeness_score,
                'missing_requirements' => $check->missing_requirements ?? [],
                'warnings' => $check->warnings ?? [],
                'recommendation' => $check->recommendations,
                'completed_items' => $check->getAttribute('completed_requirements') ?? [],
                'completed_requirements' => $check->getAttribute('completed_requirements') ?? [],
            ]);
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to check this document.',
            ], 403);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'AI checking is temporarily unavailable.',
            ], 503);
        }
    }
}
