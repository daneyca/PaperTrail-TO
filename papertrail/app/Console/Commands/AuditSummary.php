<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditSummary extends Command
{
    protected $signature = 'papertrail:audit-summary';

    protected $description = 'Show safe PaperTrail audit trail summary counts.';

    public function handle(): int
    {
        $this->components->info('PaperTrail audit summary');

        $this->table(['Metric', 'Count'], [
            ['Total logs', AuditLog::count()],
            ['Logs today', AuditLog::whereDate('created_at', today())->count()],
            ['Failed logs', AuditLog::where('status', 'failed')->count()],
            ['Denied logs', AuditLog::where('status', 'denied')->count()],
            ['Critical logs', AuditLog::where('severity', 'critical')->count()],
        ]);

        return self::SUCCESS;
    }
}
