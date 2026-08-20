<?php

return [

    'ml' => [
        'driver' => env('ML_DRIVER', 'http'),
        'url' => env('ML_BASE_URL', 'https://akazelll-face-skin-predict.hf.space'),
        'timeout' => (int) env('ML_TIMEOUT', 30),
        'retries' => (int) env('ML_RETRIES', 2),
        'free_scan_limit' => (int) env('FREE_SCAN_LIMIT', 3),
        'confidence_threshold' => (float) env('ML_CONFIDENCE_THRESHOLD', 0.50),
        'disclaimer' => 'Hasil scan hanya sebagai referensi awal dan bukan diagnosis medis. Konsultasikan dengan dokter kulit untuk penanganan yang tepat.',
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'allowed_client_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('GOOGLE_ALLOWED_CLIENT_IDS', env('GOOGLE_CLIENT_ID', '')))
        ))),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'expiry_duration' => (int) env('MIDTRANS_EXPIRY_DURATION', 1440),
    ],

    'ip_location' => [
        'driver' => env('IP_LOCATION_DRIVER', 'ip-api'),
        'cache_ttl' => (int) env('IP_LOCATION_CACHE_TTL', 86400),
        'limit_per_minute' => (int) env('IP_LOCATION_LIMIT_PER_MINUTE', 45),
    ],

    'fcm' => [
        'enabled' => (bool) env('FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_json' => env('FCM_CREDENTIALS_JSON'),
        'api_url' => env('FCM_API_URL', 'https://fcm.googleapis.com/v1/projects/%s/messages:send'),
    ],

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

];
