<?php

return [

    'name' => 'EverGreen Cold Storage',

    'logo' => 'images/evergreen-logo.svg',

    'logo_dark' => 'images/evergreen-logo-dark.svg',

    'icon' => 'images/evergreen-icon.svg',

    'favicon' => 'favicon.svg',

    'login_visual' => 'images/evergreen-login-visual.svg',

    'primary_merchant_email' => env('PRIMARY_MERCHANT_EMAIL', 'info@evergreen.com'),

    'primary_merchant_website' => env('PRIMARY_MERCHANT_WEBSITE', 'https://flowdesk.app'),

    'legacy_merchant_email' => 'info@zgngreenpvt.com',

    'colors' => [
        'primary' => '#0f766e',
        'secondary' => '#64748b',
        'accent' => '#10b981',
        'sidebar_dark' => '#0a0a0a',
        'shell_bg' => '#000000',
        'card_bg' => '#0b0f14',
        'success' => '#22c55e',
        'danger' => '#dc2626',
        'warning' => '#f59e0b',
        'default' => '#1e293b',
    ],

    'sidebar' => [
        'background' => '#0a0a0a',
        'surface' => '#111827',
        'text' => '#e2e8f0',
        'muted' => '#94a3b8',
        'active' => '#14b8a6',
        'gradient_start' => '#10b981',
        'gradient_mid' => '#0f766e',
        'gradient_end' => '#14b8a6',
        'icon' => '#94a3b8',
        'header' => '#64748b',
    ],

];
