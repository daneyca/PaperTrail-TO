<?php

namespace Tests\Unit;

use App\Services\DocumentTrackingService;
use App\Services\OpenAIService;
use App\Services\ProcurementChatbotService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProcurementChatbotFallbackTest extends TestCase
{
    public function test_pr_submission_question_has_useful_offline_guidance(): void
    {
        $service = new ProcurementChatbotService(
            $this->createMock(OpenAIService::class),
            $this->createMock(DocumentTrackingService::class),
        );
        $method = new ReflectionMethod($service, 'fallbackResponse');

        $response = $method->invoke($service, 'What happens after submitting a PR?');

        $this->assertStringContainsString('PR Numbering Staff', $response);
        $this->assertStringContainsString('BAC Secretariat', $response);
        $this->assertStringContainsString('Status Tracking', $response);
    }
}
