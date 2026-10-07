<?php

namespace App\Console\Commands;

use App\Support\MailConfigurationDiagnostics;
use Illuminate\Console\Command;

class MailDebug extends Command
{
    protected $signature = 'papertrail:mail-debug';

    protected $description = 'Print safe PaperTrail mail configuration diagnostics.';

    public function handle(): int
    {
        $config = MailConfigurationDiagnostics::safeConfig();

        $this->components->info('PaperTrail mail diagnostics');
        $this->line('MAIL_MAILER / default: ' . $config['mailer']);
        $this->line('SMTP host: ' . $config['host']);
        $this->line('SMTP port: ' . $config['port']);
        $this->line('SMTP encryption: ' . ($config['encryption'] ?: 'none'));
        $this->line('SMTP username: ' . $config['username_masked']);
        $this->line('MAIL_FROM_ADDRESS: ' . $config['from_address']);
        $this->line('MAIL_FROM_NAME: ' . $config['from_name']);

        foreach (MailConfigurationDiagnostics::warnings() as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
