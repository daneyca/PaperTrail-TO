<?php

namespace Tests\Unit;

use App\Services\OpenAIService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OpenAIServiceTest extends TestCase
{
    #[DataProvider('deploymentErrors')]
    public function test_it_returns_safe_deployment_error_messages(string $rawMessage, string $expected): void
    {
        $service = new OpenAIService();

        $this->assertSame($expected, $service->userFacingError(new RuntimeException($rawMessage)));
    }

    public static function deploymentErrors(): array
    {
        return [
            'missing key' => [
                'OpenAI API key is not configured.',
                'AI service is not configured on this deployment.',
            ],
            'invalid key' => [
                'Incorrect API key provided: sk-hidden',
                'AI service authentication failed on this deployment.',
            ],
            'network failure' => [
                'cURL error 7: Failed to connect to api.openai.com port 443',
                'The deployment server cannot reach the AI service.',
            ],
            'already-safe network failure' => [
                'The deployment server cannot reach the AI service.',
                'The deployment server cannot reach the AI service.',
            ],
            'quota failure' => [
                'insufficient_quota: check your billing details',
                'AI service usage or billing limit has been reached.',
            ],
            'rate limit' => [
                'Rate limit reached',
                'AI service rate limit reached. Please try again later.',
            ],
        ];
    }
}
