<?php

/**
 * Application configuration derived from environment variables.
 */

function getAppConfig(): array
{
    return [
        'jwt_secret'  => env('JWT_SECRET', 'change-me'),
        'cors_origin' => env('CORS_ORIGIN', 'http://localhost:5173'),
        'app_env'     => env('APP_ENV', 'development'),
        // Public site address for links in emails. Never derived from the request's Host header,
        // which a caller could forge to send a reset link pointing at their own site.
        'app_url'     => rtrim(env('APP_URL', env('CORS_ORIGIN', 'http://localhost:5173')), '/'),
    ];
}
