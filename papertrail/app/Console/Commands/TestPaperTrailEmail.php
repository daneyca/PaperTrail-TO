<?php

namespace App\Console\Commands;

use App\Support\MailConfigurationDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestPaperTrailEmail extends Command
{
    protected $signature = 'papertrail:test-email {email}';

    protected $description = 'Send a PaperTrail SMTP test email to verify mail configuration.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid recipient email address.');

            return self::FAILURE;
        }

        $config = MailConfigurationDiagnostics::safeConfig();

        $this->components->info('PaperTrail mail configuration');
        $this->line('Mailer: ' . $config['mailer']);
        $this->line('Host: ' . $config['host']);
        $this->line('Port: ' . $config['port']);
        $this->line('Encryption: ' . ($config['encryption'] ?: 'none'));
        $this->line('From Address: ' . $config['from_address']);
        $this->line('Username: ' . $config['username_masked']);

        foreach (MailConfigurationDiagnostics::warnings() as $warning) {
            $this->warn($warning);
        }

        try {
            Mail::raw(
                'PaperTrail Gmail SMTP is working.',
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('PaperTrail SMTP Test Email');
                }
            );

            $this->info("SMTP accepted the test email for {$email}.");
            $this->warn('If it does not arrive, check the recipient inbox, spam folder, Gmail app password, and Google account security settings.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('PaperTrail SMTP test email failed.', [
                'recipient' => $email,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'username_masked' => $config['username_masked'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->error('Test email failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
