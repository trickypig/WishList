<?php

/**
 * Promote a user to admin.
 * Usage: php make-admin.php youremail@example.com
 */

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($argv[1])) {
    echo "Usage: php make-admin.php <email>\n";
    exit(1);
}

$email = $argv[1];

try {
    $db = getDatabase();
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$stmt = $db->prepare('SELECT id, email, display_name, is_admin FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "No user found with email: {$email}\n";
    exit(1);
}

if ($user['is_admin']) {
    echo "{$user['display_name']} ({$email}) is already an admin.\n";
    exit(0);
}

$stmt = $db->prepare('UPDATE users SET is_admin = 1 WHERE id = ?');
$stmt->execute([$user['id']]);

echo "Done! {$user['display_name']} ({$email}) is now an admin.\n";
echo "Log out and back in for it to take effect.\n";
