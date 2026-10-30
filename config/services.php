<?php

return [
    'guest_access' => ['frontend_url' => env('GUEST_FRONTEND_URL', 'https://stay.insigniacourts.com')],

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

    'opay' => [
        'merchant_id' => env('OPAY_MERCHANT_ID', '256626100809577'),
        'public_key'  => env('OPAY_PUBLIC_KEY'),
        'secret_key'  => env('OPAY_SECRET_KEY'),
        'base_url'    => env('OPAY_BASE_URL', 'https://sandboxapi.opaycheckout.com'),
        'env'         => env('OPAY_ENV', 'sandbox'),
    ],

    'flutterwave' => [
        'web_return_url' => env('FLW_WEB_RETURN_URL'),
        'public_key'     => env('FLW_PUBLIC_KEY'),
        'secret_key'     => env('FLW_SECRET_KEY'),
        'encryption_key' => env('FLW_ENCRYPTION_KEY'),
        'secret_hash'    => env('FLW_SECRET_HASH'),
        'base_url'       => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),
    ],

];
