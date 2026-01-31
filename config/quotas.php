<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upload Quotas
    |--------------------------------------------------------------------------
    |
    | Configure upload quotas per domain and per user.
    | Values are in bytes.
    | Per-user limits are overridden by tier (config/pricing.php) when user has a subscription.
    |
    */

    'upload' => [
        'per_domain' => [
            // 'memora' => 1073741824, // 1GB
        ],
        'per_user' => [
            'default' => 5 * 1024 * 1024 * 1024, // 5GB (Starter-equivalent for unsubscribed users)
        ],
    ],
];
