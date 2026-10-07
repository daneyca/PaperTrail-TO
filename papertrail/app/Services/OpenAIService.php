<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class OpenAIService
{
    public function chat(array $messages, array $options = []): string
    {
        if ($messages === []) {
            throw new RuntimeException('OpenAI messages cannot be empty.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        try {
            $response = OpenAI::chat()->create(array_merge([
                'model' => $this->model(),
                'messages' => $messages,
                'temperature' => 0,
                'max_tokens' => 900,
            ], $options));

            $data = $response->toArray();
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if ($content === '') {
                throw new RuntimeException('OpenAI returned an empty response.');
            }

            return $content;
        } catch (RuntimeException $exception) {
            Log::warning('PaperTrail OpenAI request failed safely.', [
                'model' => $this->model(),
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('PaperTrail OpenAI request failed.', [
                'model' => $this->model(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($this->safeErrorMessage($exception), previous: $exception);
        }
    }

    public function ask(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            throw new RuntimeException('OpenAI prompt cannot be empty.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a connection test assistant for PaperTrail. Follow the user instruction exactly.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
                'temperature' => 0,
                'max_tokens' => 60,
            ]);

            $data = $response->toArray();
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if ($content === '') {
                throw new RuntimeException('OpenAI returned an empty response.');
            }

            return $content;
        } catch (RuntimeException $exception) {
            Log::warning('PaperTrail OpenAI request failed safely.', [
                'model' => $this->model(),
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('PaperTrail OpenAI request failed.', [
                'model' => $this->model(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($this->safeErrorMessage($exception), previous: $exception);
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function model(): string
    {
        return (string) config('services.openai.model', 'gpt-4.1-mini');
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'api key is missing')) {
            return 'OpenAI API key is not configured.';
        }

        if (str_contains($message, 'incorrect api key')
            || str_contains($message, 'invalid api key')
            || str_contains($message, 'unauthorized')
            || str_contains($message, '401')) {
            return 'OpenAI authentication failed. Please check API configuration.';
        }

        if (str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'connection')) {
            return 'Unable to connect to OpenAI service.';
        }

        if (str_contains($message, 'rate limit') || str_contains($message, '429')) {
            return 'OpenAI rate limit reached. Please try again later.';
        }

        return 'OpenAI service returned an error. Please check API configuration.';
    }
}
