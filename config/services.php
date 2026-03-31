<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailchimp' => [
        'api_key' => env('MAILCHIMP_API_KEY'),
        'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
        'lists' => [
            'ru' => env('MAILCHIMP_LIST_RU'),
            'en' => env('MAILCHIMP_LIST_EN'),
        ],
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'api_key' => env('RECAPTCHA_API_KEY', env('RECAPTCHA_SECRET_KEY')),
        'project_id' => env('RECAPTCHA_PROJECT_ID'),
        'expected_action' => env('RECAPTCHA_EXPECTED_ACTION', 'newsletter_subscribe'),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

    'frontend_revalidation' => [
        'url' => env('FRONTEND_REVALIDATE_URL', rtrim(env('APP_URL', 'http://localhost'), '/') . '/api/revalidate'),
        'secret' => env('FRONTEND_REVALIDATE_SECRET'),
        'timeout' => env('FRONTEND_REVALIDATE_TIMEOUT', 10),
    ],

    'oilprice' => [
        'api_token' => env('OILPRICE_API_TOKEN'),
    ],

];
