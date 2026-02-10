<?php

return [
    'health_webhook_url' => env('ADMIN_HEALTH_WEBHOOK_URL'),
    'quick_links' => [
        ['label' => 'Docs', 'url' => env('ADMIN_QUICK_LINK_DOCS', '#')],
        ['label' => 'Status', 'url' => env('ADMIN_QUICK_LINK_STATUS', '#')],
    ],
];
