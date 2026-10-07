<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | PaperTrail reads OpenAI credentials from .env only. Never hardcode API
    | keys here or expose them in Blade views, logs, or browser output.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Keep the first connection test responsive and safe for local demo use.
    |
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
];
