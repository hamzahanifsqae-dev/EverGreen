<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Internal demo provisioning (not shown to visitors)
    |--------------------------------------------------------------------------
    |
    | Visitors click "Try Demo" with no login form. A temporary merchant is
    | created automatically per device. Password is used internally only.
    |
    */

    'email' => env('DEMO_ACCOUNT_EMAIL', 'demo@crmdemo.com'),

    'password' => env('DEMO_ACCOUNT_PASSWORD', 'Demo@123456'),

    'session_timeout_minutes' => (int) env('DEMO_SESSION_TIMEOUT', 30),

    'merchant_name' => env('DEMO_MERCHANT_NAME', 'EverGreen Cold Storage Demo'),

    'temporary_email_domain' => env('DEMO_TEMPORARY_EMAIL_DOMAIN', 'crmdemo.com'),

    'daily_reset_at' => env('DEMO_DAILY_RESET_AT', '17:00'),

    'notices' => [
        'demo_left' => 'You left the demo. Your remaining time is saved — click Try Demo again before it expires to continue.',
        'demo_expired' => 'Your demo time has ended. Your temporary account and all data from this session have been deleted.',
    ],

];
