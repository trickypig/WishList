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

    /**
     * Sends the response to the client but keeps the script running, for slow work
     * (such as sending email) whose duration the client must not be able to observe.
     */
    public static function jsonThenContinue($data, int $status = 200): void
    {
        ignore_user_abort(true);
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
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
