<?php

/**
 * Tests for the password reset rules. Uses an in-memory database.
 * Run from CLI: php api/tests/password-reset-test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PasswordReset.php';

$failures = 0;

function check(string $name, bool $passed): void
{
    global $failures;
    echo ($passed ? '  [OK]   ' : '  [FAIL] ') . $name . "\n";
    if (!$passed) {
        $failures++;
    }
}

function freshDb(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(255) NOT NULL,
        token_version INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    PasswordReset::createTable($db, 'INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT');
    User::create($db, 'Pat@Example.com', 'old-password', 'Pat');
    return $db;
}

function passwordIs(PDO $db, string $password): bool
{
    $hash = $db->query('SELECT password_hash FROM users WHERE id = 1')->fetchColumn();
    return password_verify($password, $hash);
}

function tokenVersion(PDO $db): int
{
    return (int) $db->query('SELECT token_version FROM users WHERE id = 1')->fetchColumn();
}

$now = 1000000;
$ip = '203.0.113.1';

echo "Reset with a code\n";
$db = freshDb();
$r = PasswordReset::start($db, 'pat@example.com', $ip, $now);
check('finds the account regardless of email case', $r['recipient'] === 'Pat@Example.com');
check('code is 6 digits', preg_match('/^\d{6}$/', $r['code']) === 1);
check('code is not stored in plain text', $db->query("SELECT COUNT(*) FROM password_resets WHERE code_hash = '{$r['code']}'")->fetchColumn() == 0);
check('correct code succeeds', PasswordReset::redeemCode($db, 'PAT@example.com', $r['code'], 'new-password', $now + 60));
check('password was changed', passwordIs($db, 'new-password'));
check('older sessions are invalidated', tokenVersion($db) === 1);
check('a code works only once', !PasswordReset::redeemCode($db, 'pat@example.com', $r['code'], 'another', $now + 61));
check('password unchanged by reused code', passwordIs($db, 'new-password'));

echo "Reset with a link\n";
$db = freshDb();
$r = PasswordReset::start($db, 'pat@example.com', $ip, $now);
check('correct token succeeds', PasswordReset::redeemToken($db, $r['token'], 'link-password', $now + 60));
check('password was changed', passwordIs($db, 'link-password'));
check('a token works only once', !PasswordReset::redeemToken($db, $r['token'], 'another', $now + 61));
check('using the link also spends its code', !PasswordReset::redeemCode($db, 'pat@example.com', $r['code'], 'another', $now + 62));
check('a made-up token fails', !PasswordReset::redeemToken($db, 'nope', 'another', $now + 63));

echo "Other outstanding emails\n";
$db = freshDb();
$first = PasswordReset::start($db, 'pat@example.com', $ip, $now);
$second = PasswordReset::start($db, 'pat@example.com', $ip, $now);
check('reset succeeds with newest email', PasswordReset::redeemToken($db, $second['token'], 'newest', $now + 1));
check('earlier email is cancelled by a reset', !PasswordReset::redeemToken($db, $first['token'], 'older', $now + 2));

echo "Expiry\n";
$db = freshDb();
$r = PasswordReset::start($db, 'pat@example.com', $ip, $now);
check('code fails after 15 minutes', !PasswordReset::redeemCode($db, 'pat@example.com', $r['code'], 'late', $now + 901));
check('token fails after 15 minutes', !PasswordReset::redeemToken($db, $r['token'], 'late', $now + 901));
check('password unchanged', passwordIs($db, 'old-password'));
check('still works just before expiry', PasswordReset::redeemToken($db, $r['token'], 'on-time', $now + 899));

echo "Wrong codes\n";
$db = freshDb();
$r = PasswordReset::start($db, 'pat@example.com', $ip, $now);
$wrong = $r['code'] === '000000' ? '000001' : '000000';
for ($i = 0; $i < 4; $i++) {
    PasswordReset::redeemCode($db, 'pat@example.com', $wrong, 'x', $now + 1);
}
check('right code still works after 4 wrong tries', PasswordReset::redeemCode($db, 'pat@example.com', $r['code'], 'fifth', $now + 2));

$db = freshDb();
$r = PasswordReset::start($db, 'pat@example.com', $ip, $now);
$wrong = $r['code'] === '000000' ? '000001' : '000000';
for ($i = 0; $i < 5; $i++) {
    PasswordReset::redeemCode($db, 'pat@example.com', $wrong, 'x', $now + 1);
}
check('5 wrong tries cancel the code', !PasswordReset::redeemCode($db, 'pat@example.com', $r['code'], 'blocked', $now + 2));
check('5 wrong tries cancel the link too', !PasswordReset::redeemToken($db, $r['token'], 'blocked', $now + 2));
check('password unchanged', passwordIs($db, 'old-password'));

echo "Unknown accounts\n";
$db = freshDb();
$r = PasswordReset::start($db, 'nobody@example.com', $ip, $now);
check('no email is sent', $r['recipient'] === null);
check('its code cannot reset anything', !PasswordReset::redeemCode($db, 'nobody@example.com', $r['code'], 'x', $now + 1));
check('its token cannot reset anything', !PasswordReset::redeemToken($db, $r['token'], 'x', $now + 1));

echo "Rate limits\n";
$db = freshDb();
for ($i = 0; $i < 3; $i++) {
    check('email request ' . ($i + 1) . ' allowed', PasswordReset::start($db, 'pat@example.com', $ip, $now) !== null);
}
check('4th email in 15 minutes is refused', PasswordReset::start($db, 'PAT@example.com', $ip, $now) === null);
check('allowed again after the window', PasswordReset::start($db, 'pat@example.com', $ip, $now + 901) !== null);

$db = freshDb();
for ($i = 0; $i < 3; $i++) {
    PasswordReset::start($db, 'nobody@example.com', $ip, $now);
}
check('unknown addresses hit the same limit', PasswordReset::start($db, 'nobody@example.com', $ip, $now) === null);

$db = freshDb();
for ($i = 0; $i < 20; $i++) {
    PasswordReset::start($db, "person{$i}@example.com", $ip, $now);
}
check('21st request from one IP in an hour is refused', PasswordReset::start($db, 'pat@example.com', $ip, $now) === null);
check('other IPs are unaffected', PasswordReset::start($db, 'pat@example.com', '203.0.113.2', $now) !== null);

$db = freshDb();
for ($i = 0; $i < 3; $i++) {
    $r = PasswordReset::start($db, 'pat@example.com', $ip, $now);
    PasswordReset::discard($db, $r['id']);
}
check('failed sends do not count toward the limit', PasswordReset::start($db, 'pat@example.com', $ip, $now) !== null);

echo $failures === 0 ? "\nAll tests passed.\n" : "\n{$failures} test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
