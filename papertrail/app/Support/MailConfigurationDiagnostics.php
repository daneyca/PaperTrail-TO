<?php

namespace App\Support;

class MailConfigurationDiagnostics
{
    public static function safeConfig(): array
    {
        return [
            'mailer' => (string) config('mail.default'),
            'driver' => (string) config('mail.default'),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'encryption' => (string) config('mail.mailers.smtp.encryption'),
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'username_masked' => self::mask((string) config('mail.mailers.smtp.username')),
        ];
    }

    public static function warnings(): array
    {
        $config = self::safeConfig();
        $warnings = [];

        if ($config['mailer'] !== 'smtp') {
            $warnings[] = 'MAIL_MAILER is not smtp, so Gmail SMTP is not the active transport.';
        }

        if ($config['host'] !== 'smtp.gmail.com') {
            $warnings[] = 'MAIL_HOST is not smtp.gmail.com. Confirm the loaded config matches your Gmail .env settings.';
        }

        if ((string) $config['port'] !== '587') {
            $warnings[] = 'MAIL_PORT is not 587. Gmail SMTP commonly uses port 587 with TLS.';
        }

        if (strtolower((string) $config['encryption']) !== 'tls') {
            $warnings[] = 'MAIL_ENCRYPTION is not tls. Gmail SMTP on port 587 should use TLS.';
        }

        if ($config['from_address'] && config('mail.mailers.smtp.username') && $config['from_address'] !== config('mail.mailers.smtp.username')) {
            $warnings[] = 'For Gmail SMTP, MAIL_FROM_ADDRESS should usually match MAIL_USERNAME.';
        }

        return $warnings;
    }

    public static function mask(?string $value): string
    {
        if (! $value) {
            return 'not configured';
        }

        $visible = min(4, strlen($value));

        return substr($value, 0, $visible) . str_repeat('*', max(4, strlen($value) - $visible));
    }

}
