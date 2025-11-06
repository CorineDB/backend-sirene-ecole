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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    // Services pour Sirène d'École
    'token' => [
        'encryption_key' => env('TOKEN_ENCRYPTION_KEY'),
        'encryption_method' => env('TOKEN_ENCRYPTION_METHOD', 'AES-256-CBC'),
    ],

    'otp' => [
        'expiration_minutes' => env('OTP_EXPIRATION_MINUTES', 5),
        'max_attempts' => env('OTP_MAX_ATTEMPTS', 3),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'custom_api'),
        'url' => env('SMS_PROVIDER_URL'),
        'account_id' => env('SMS_PROVIDER_API_ACCOUNT_ID'),
        'account_password' => env('SMS_PROVIDER_API_ACCOUNT_PASSWORD'),
        'api_key' => env('SMS_PROVIDER_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),
        'from_number' => env('SMS_FROM_NUMBER', 'SIRENE'),
        'username' => env('SMS_USERNAME'),

        // Alertes de seuil
        'alert_threshold' => env('SMS_ALERT_THRESHOLD', 1000),
        'alert_emails' => env('ALERT_EMAIL', ''),
        'alert_phones' => env('ALERT_SMS', ''),
    ],

    'subscription' => [
        'price_per_year' => env('SUBSCRIPTION_PRICE_PER_YEAR', 50000),
        'currency' => env('SUBSCRIPTION_CURRENCY', 'XOF'),
    ],

    'cinetpay' => [
        'api_key' => env('CINETPAY_API_KEY'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'api_url' => env('CINETPAY_API_URL', 'https://api-checkout.cinetpay.com/v2/payment'),
        'mode' => env('CINETPAY_MODE', 'test'), // test ou production
        'check' => env('CINETPAY_API_CHECK', 'https://api-checkout.cinetpay.com/v2/payment/check'),
        'demo' => env('CINETPAY_DEMO', 'https://cinetpay.com/demo'),
    ],

];
