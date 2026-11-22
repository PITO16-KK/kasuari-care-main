<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_GATEWAY_URL'),
        'token' => env('WHATSAPP_GATEWAY_TOKEN'),
    ],

    'google_forms' => [
        'url' => env('GOOGLE_FORMS_URL', 'https://forms.gle/RVYZWH2Mwjoi4z688'),
        'token' => env('GOOGLE_FORMS_TOKEN', 'kasuari-care-gform-2026'),
        'webhook_secret' => env('GOOGLE_FORMS_WEBHOOK_SECRET'),
    ],

];
