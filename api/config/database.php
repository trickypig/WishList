<?php

/**
 * PDO database factory.
 */

function getDatabase(): PDO
{
    $driver = env('DB_DRIVER', 'sqlite');

    if ($driver === 'sqlite') {
        $dbPath = env('DB_PATH', '../data/wish.db');

        // Resolve relative path from the api/ directory
        if (!str_starts_with($dbPath, '/') && !preg_match('/^[A-Za-z]:/', $dbPath)) {
            $dbPath = __DIR__ . '/../' . $dbPath;
        }

        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
    } else {
        $host = env('DB_HOST', '127.0.0.1');
        $name = env('DB_NAME', 'wish');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}
