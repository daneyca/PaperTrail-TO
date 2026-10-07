<?php

namespace App\Console\Commands;

use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Throwable;

class TestOpenAIConnection extends Command
{
    protected $signature = 'papertrail:test-openai';

    protected $description = 'Test PaperTrail OpenAI API connectivity.';

    public function handle(OpenAIService $openAI): int
    {
        $this->line('================================');
        $this->line('');
        $this->line('PaperTrail OpenAI Connection Test');
        $this->line('');
        $this->line('Model:');
        $this->line($openAI->model());
        $this->line('');

        if (! $openAI->isConfigured()) {
            $this->line('Status:');
            $this->line('FAILED');
            $this->line('');
            $this->line('Reason:');
            $this->line('OpenAI API key is not configured.');
            $this->line('');
            $this->line('================================');

            return self::FAILURE;
        }

        try {
            $response = $openAI->ask('Respond with exactly: OpenAI connection successful.');

            $this->line('Status:');
            $this->line('SUCCESS');
            $this->line('');
            $this->line('Response:');
            $this->line($response);
            $this->line('');
            $this->line('================================');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line('Status:');
            $this->line('FAILED');
            $this->line('');
            $this->line('Reason:');
            $this->line($exception->getMessage());
            $this->line('');
            $this->line('================================');

            return self::FAILURE;
        }
    }
}
