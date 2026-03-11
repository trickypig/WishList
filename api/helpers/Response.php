<?php

/**
 * Static helper for sending JSON responses.
 */
class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::json(['error' => $message], 401);
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::json(['error' => $message], 404);
    }
}
