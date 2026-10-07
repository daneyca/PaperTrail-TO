<?php

namespace App\Http\Controllers;

use App\Models\AiDelayRiskCheck;
use App\Models\SystemNotification;
use App\Services\AiDelayRiskService;
use App\Services\NotificationDispatchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AiDelayRiskController extends Controller
{
    public function analyze(
        Request $request,
        AiDelayRiskService $service,
        NotificationDispatchService $notifications,
        string $documentType,
        int $documentId
    ): JsonResponse {
        try {
            $result = $service->analyzeDelayRisk(
                $request->user(),
                $documentType,
                $documentId
            );

            $this->notifyIfDocumentIsAtRisk($request, $notifications, $result);

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to analyze this document.',
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
                'message' => 'AI delay risk analysis is temporarily unavailable.',
            ], 503);
        }
    }

    private function notifyIfDocumentIsAtRisk(
        Request $request,
        NotificationDispatchService $notifications,
        array $result
    ): void {
        $riskLevel = strtoupper((string) ($result['risk_level'] ?? ''));

        if (! in_array($riskLevel, [AiDelayRiskCheck::RISK_HIGH, AiDelayRiskCheck::RISK_CRITICAL], true)) {
            return;
        }

        $check = isset($result['check_id'])
            ? AiDelayRiskCheck::find($result['check_id'])
            : null;
        $trackingNumber = $result['tracking_number'] ?? 'Document';
        $riskLabel = $result['risk_label'] ?? "{$riskLevel} Delay Risk";
        $daysWaiting = (int) ($result['days_waiting'] ?? 0);
        $currentHolder = $result['current_holder'] ?? 'Not recorded';
        $currentStage = $result['current_stage'] ?? 'Not recorded';
        $recommendation = $result['recommendation'] ?? 'Review this document and coordinate the next workflow action.';

        $notifications->notifyUser(
            user: $request->user(),
            subject: "{$riskLabel}: {$trackingNumber}",
            message: "This document is at {$riskLevel} risk after {$daysWaiting} day(s) in {$currentStage}. Current holder: {$currentHolder}. {$recommendation}",
            url: $request->headers->get('referer'),
            metadata: [
                'channel' => 'in_app',
                'event' => 'ai_delay_risk_alert',
                'document_type' => $result['document_type'] ?? null,
                'tracking_number' => $trackingNumber,
                'status' => $result['current_status_label'] ?? $result['current_status'] ?? null,
            ],
            type: $riskLevel === AiDelayRiskCheck::RISK_CRITICAL
                ? SystemNotification::TYPE_ERROR
                : SystemNotification::TYPE_WARNING,
            module: 'AI Delay Risk',
            related: $check,
            actionText: 'View Risk Check',
        );
    }
}
