<?php

/*
 * Needed when the Vue app is served from a different origin to the API — which
 * it is in development (Vite on :5173, Laravel on :8000) and often in
 * production too.
 *
 * Authentication is a bearer token rather than a cookie, so credentials are not
 * required and the allowed origins can stay explicit.
 */

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173',
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];
