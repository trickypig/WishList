<?php

/**
 * Tests for the family invite link rules. Uses an in-memory database.
 * Run from CLI: php api/tests/family-invite-test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Family.php';
require_once __DIR__ . '/../models/FamilyInvite.php';

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
    $db->exec("CREATE TABLE families (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        invite_code VARCHAR(20) NOT NULL,
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $db->exec("CREATE TABLE family_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        family_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'member',
        joined_at TEXT NOT NULL,
        UNIQUE(family_id, user_id)
    )");
    FamilyInvite::createTable($db, 'INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT');
    User::create($db, 'admin@example.com', 'password', 'Alex Admin');
    User::create($db, 'two@example.com', 'password', 'Two');
    User::create($db, 'three@example.com', 'password', 'Three');
    $db->exec("INSERT INTO families (name, invite_code, created_by, created_at, updated_at)
               VALUES ('Smith', 'ABC123', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
    $db->exec("INSERT INTO families (name, invite_code, created_by, created_at, updated_at)
               VALUES ('Jones', 'DEF456', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
    Family::addMember($db, 1, 1, 'admin');
    return $db;
}

function memberCount(PDO $db, int $familyId, int $userId): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM family_members WHERE family_id = :f AND user_id = :u');
    $stmt->execute(['f' => $familyId, 'u' => $userId]);
    return (int) $stmt->fetchColumn();
}

$now = 1000000;

echo "Creating invites\n";
$db = freshDb();
$r = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
check('create returns a token', is_array($r) && strlen($r['token']) === 64);
$row = $db->query('SELECT * FROM family_invites WHERE id = ' . (int) $r['id'])->fetch();
check('only the token hash is stored', $row['token_hash'] === hash('sha256', $r['token'])
    && !in_array($r['token'], array_map('strval', $row), true));

$db = freshDb();
FamilyInvite::create($db, 1, ' Mom@Example.com ', 1, $now);
FamilyInvite::create($db, 1, 'MOM@example.com', 1, $now + 10);
$emails = $db->query('SELECT DISTINCT email FROM family_invites')->fetchAll(PDO::FETCH_COLUMN);
check('email is normalised', $emails === ['mom@example.com'] && count(FamilyInvite::listLive($db, 1, $now + 20)) === 1);

echo "Finding by token\n";
$db = freshDb();
$r = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
$found = FamilyInvite::findValid($db, $r['token'], $now);
check('findValid returns family and inviter names', $found !== false
    && $found['family_name'] === 'Smith' && $found['inviter_name'] === 'Alex Admin');
check('expired token is not found', FamilyInvite::findValid($db, $r['token'], $now + 1209600) === false
    && FamilyInvite::findValid($db, $r['token'], $now + 1209599) !== false);
check('unknown token is not found', FamilyInvite::findValid($db, str_repeat('a', 64), $now) === false);

echo "Revoking\n";
$db = freshDb();
$a = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
$b = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now + 5);
$c = FamilyInvite::create($db, 1, 'dad@example.com', 1, $now + 5);
$count = FamilyInvite::revokeForEmail($db, 1, 'Mom@example.com', $now + 10);
check('revoked token is not found', $count >= 1 && FamilyInvite::findValid($db, $a['token'], $now + 20) === false);
check('revoke covers every invite to that email', $count === 2
    && FamilyInvite::findValid($db, $b['token'], $now + 20) === false
    && FamilyInvite::findValid($db, $c['token'], $now + 20) !== false);

echo "Accepting\n";
$db = freshDb();
$r = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
$invite = FamilyInvite::findValid($db, $r['token'], $now);
$first = FamilyInvite::accept($db, $invite, 2, $now + 1);
$second = FamilyInvite::accept($db, FamilyInvite::findValid($db, $r['token'], $now + 2), 3, $now + 2);
check('link is reusable by different accounts', $first === false && $second === false
    && memberCount($db, 1, 2) === 1 && memberCount($db, 1, 3) === 1);
$again = FamilyInvite::accept($db, FamilyInvite::findValid($db, $r['token'], $now + 3), 2, $now + 3);
check('accept is idempotent', $again === true && memberCount($db, 1, 2) === 1);
$row = $db->query('SELECT accepted_by, accepted_at FROM family_invites WHERE id = ' . (int) $r['id'])->fetch();
check('first use is recorded once', (int) $row['accepted_by'] === 2 && $row['accepted_at'] === gmdate('Y-m-d H:i:s', $now + 1));
check('accepting only joins the invite family', memberCount($db, 2, 2) === 0);

echo "Resending\n";
$db = freshDb();
$old = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
$new = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now + 60);
$live = FamilyInvite::listLive($db, 1, $now + 120);
check('resend leaves the old token valid', FamilyInvite::findValid($db, $old['token'], $now + 120) !== false
    && FamilyInvite::findValid($db, $new['token'], $now + 120) !== false
    && count($live) === 1 && (int) $live[0]['id'] === $new['id']);

echo "Listing\n";
$db = freshDb();
$r = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
FamilyInvite::accept($db, FamilyInvite::findValid($db, $r['token'], $now), 2, $now + 1);
FamilyInvite::create($db, 1, 'dad@example.com', 1, $now + 10);
$live = FamilyInvite::listLive($db, 1, $now + 20);
check('list shows used invites with who used them', count($live) === 2
    && $live[0]['email'] === 'dad@example.com' && $live[1]['accepted_by_name'] === 'Two'
    && $live[1]['invited_by_name'] === 'Alex Admin' && $live[0]['accepted_by_name'] === null);
check('list never includes tokens', !array_key_exists('token_hash', $live[0]) && !array_key_exists('token', $live[0]));
check('list excludes expired invites', count(FamilyInvite::listLive($db, 1, $now + 1209610)) === 0);
check('list is per family', count(FamilyInvite::listLive($db, 2, $now + 20)) === 0);

echo "Rate limit\n";
$db = freshDb();
$ok = true;
for ($i = 0; $i < 20; $i++) {
    $ok = $ok && FamilyInvite::create($db, 1, "p{$i}@example.com", 1, $now) !== null;
}
check('20 invites an hour are allowed', $ok);
check('21st invite in an hour is refused', FamilyInvite::create($db, 1, 'extra@example.com', 1, $now + 100) === null);
check('other families are unaffected', FamilyInvite::create($db, 2, 'extra@example.com', 1, $now + 100) !== null);
check('allowed again after the hour', FamilyInvite::create($db, 1, 'extra@example.com', 1, $now + 3601) !== null);

$db = freshDb();
for ($i = 0; $i < 20; $i++) {
    $x = FamilyInvite::create($db, 1, "p{$i}@example.com", 1, $now);
    FamilyInvite::discard($db, $x['id']);
}
check('discarded invites do not count', FamilyInvite::create($db, 1, 'extra@example.com', 1, $now) !== null);

echo "Legacy rows and scoping\n";
$db = freshDb();
$db->exec("INSERT INTO family_invites (family_id, email, invited_by, created_at)
           VALUES (1, 'old@example.com', 1, '" . gmdate('Y-m-d H:i:s', $now) . "')");
check('legacy rows never match or list', FamilyInvite::findValid($db, '', $now) === false
    && count(FamilyInvite::listLive($db, 1, $now)) === 0);
$r = FamilyInvite::create($db, 1, 'mom@example.com', 1, $now);
check('findInFamily is scoped', FamilyInvite::findInFamily($db, 1, $r['id']) !== false
    && FamilyInvite::findInFamily($db, 2, $r['id']) === false);

echo $failures === 0 ? "\nAll tests passed.\n" : "\n{$failures} test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
