<?php

namespace App\Http\Controllers;

use App\Services\AiRouteValidationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AiRouteValidationController extends Controller
{
    public function check(Request $request, AiRouteValidationService $service): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:80'],
            'document_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $service->validateRoute(
                $request->user(),
                $validated['document_type'],
                (int) $validated['document_id']
            );

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to validate this document route.',
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
                'message' => 'AI route validation is temporarily unavailable.',
            ], 503);
        }
    }
}
