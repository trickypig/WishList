<?php

/**
 * Router script for PHP's built-in development server.
 * Usage: php -S localhost:8080 router.php
 *
 * This replicates the .htaccess rewrite rules that route
 * /api/* requests to index.php.
 */

$uri = $_SERVER['REQUEST_URI'];
$path = strtok($uri, '?');

// Serve existing files directly (e.g. static assets)
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false;
}

// Route everything else through the front controller
require __DIR__ . '/index.php';
