<?php

return [
    'csp_enabled' => (bool) env('SECURITY_CSP_ENABLED', false),
    'hsts_enabled' => (bool) env('SECURITY_HSTS_ENABLED', false),
    'rate_limit_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 60),
];
