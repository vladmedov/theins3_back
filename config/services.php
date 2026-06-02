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
            'ru' => [
                [
                    'list_id' => 'digest.ru.daily',
                    'name' => 'Утренний бриф',
                    'mailchimp_id' => env('MAILCHIMP_LIST_DIGEST_RU_DAILY', '2fdb95d056'),
                ],
                [
                    'list_id' => 'digest.ru.weekly',
                    'name' => 'Еженедельный дайджест',
                    'mailchimp_id' => env('MAILCHIMP_LIST_DIGEST_RU_WEEKLY', '122bc4ada7'),
                ],
            ],
            'en' => [
                [
                    'list_id' => 'digest.en.weekly',
                    'name' => 'Weekly Digest',
                    'mailchimp_id' => env('MAILCHIMP_LIST_DIGEST_EN_WEEKLY', '2afe810bd4'),
                ],
            ],
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

    'google_news' => [
        'publication_name' => env('GOOGLE_NEWS_PUBLICATION_NAME', 'The Insider'),
    ],

];
