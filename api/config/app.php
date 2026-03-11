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
    ];
}
