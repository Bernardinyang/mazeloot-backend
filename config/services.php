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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/api/v1/auth/oauth/google/callback'),
    ],

    'dropbox' => [
        'client_id' => env('DROPBOX_CLIENT_ID'),
        'client_secret' => env('DROPBOX_CLIENT_SECRET'),
        'redirect' => env('DROPBOX_REDIRECT_URI', env('APP_URL').'/api/v1/cloud-storage/oauth/dropbox/callback'),
    ],

    'onedrive' => [
        'client_id' => env('ONEDRIVE_CLIENT_ID'),
        'client_secret' => env('ONEDRIVE_CLIENT_SECRET'),
        'redirect' => env('ONEDRIVE_REDIRECT_URI', env('APP_URL').'/api/v1/cloud-storage/oauth/onedrive/callback'),
    ],

    'box' => [
        'client_id' => env('BOX_CLIENT_ID'),
        'client_secret' => env('BOX_CLIENT_SECRET'),
        'redirect' => env('BOX_REDIRECT_URI', env('APP_URL').'/api/v1/cloud-storage/oauth/box/callback'),
    ],

    'adobe' => [
        'client_id' => env('ADOBE_CLIENT_ID'),
        'client_secret' => env('ADOBE_CLIENT_SECRET'),
        'redirect' => env('ADOBE_REDIRECT_URI', env('APP_URL').'/api/v1/cloud-storage/oauth/adobe/callback'),
    ],

];
