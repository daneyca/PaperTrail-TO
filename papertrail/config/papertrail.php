<?php

return [
    'email_notifications_enabled' => env('PAPERTRAIL_EMAIL_NOTIFICATIONS', true),
    'ai_summary_cache_seconds' => env('PAPERTRAIL_AI_SUMMARY_CACHE_SECONDS', 300),
    'attachments' => [
        'max_size_kb' => env('PAPERTRAIL_ATTACHMENT_MAX_SIZE_KB', 10240),
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
    ],
];
