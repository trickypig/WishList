<?php

/**
 * Simple .env file parser.
 * Reads key=value pairs from ../.env relative to this file.
 */

$_ENV_LOADED = false;
$_ENV_VALUES = [];

function loadEnv(): void
{
    global $_ENV_LOADED, $_ENV_VALUES;

    if ($_ENV_LOADED) {
        return;
    }

    $envFile = __DIR__ . '/../.env';

    if (!file_exists($envFile)) {
        $_ENV_LOADED = true;
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if (str_starts_with($line, '#')) {
            continue;
        }

        // Must contain =
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Remove surrounding quotes if present
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV_VALUES[$key] = $value;
    }

    $_ENV_LOADED = true;
}

/**
 * Get an environment variable value.
 */
function env(string $key, string $default = ''): string
{
    global $_ENV_VALUES;

    loadEnv();

    return $_ENV_VALUES[$key] ?? $default;
}
