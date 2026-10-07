<?php

namespace App\Console\Commands;

use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Throwable;

class OpenAIStatus extends Command
{
    protected $signature = 'papertrail:openai-status';

    protected $description = 'Show safe PaperTrail OpenAI configuration and connection status.';

    public function handle(OpenAIService $openAI): int
    {
        $this->line('OpenAI Configuration');
        $this->line('');
        $this->line('API Key:');
        $this->line($openAI->isConfigured() ? 'Configured' : 'Missing');
        $this->line('');
        $this->line('Model:');
        $this->line($openAI->model());
        $this->line('');
        $this->line('Connection:');

        if (! $openAI->isConfigured()) {
            $this->line('Failed');

            return self::FAILURE;
        }

        try {
            $openAI->ask('Respond with exactly: Ready');
            $this->line('Ready');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line('Failed');
            $this->line('');
            $this->line('Reason:');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
