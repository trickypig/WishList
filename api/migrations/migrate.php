<?php

/**
 * Database migration script.
 * Run from CLI: php migrate.php
 */

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

echo "Starting migration...\n";

try {
    $db = getDatabase();
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$driver = env('DB_DRIVER', 'sqlite');
$isSqlite = ($driver === 'sqlite');

echo "Using driver: {$driver}\n";

// Helper for auto-increment syntax
$autoIncrement = $isSqlite
    ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
    : 'INT AUTO_INCREMENT PRIMARY KEY';

$timestampType = $isSqlite ? 'TEXT' : 'DATETIME';
$textType = 'TEXT';

// ---- users ----
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id {$autoIncrement},
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    is_admin INTEGER NOT NULL DEFAULT 0,
    created_at {$timestampType} NOT NULL,
    updated_at {$timestampType} NOT NULL
)");
echo "  [OK] users\n";

// ---- families ----
$db->exec("CREATE TABLE IF NOT EXISTS families (
    id {$autoIncrement},
    name VARCHAR(255) NOT NULL,
    invite_code VARCHAR(8) NOT NULL UNIQUE,
    created_by INTEGER NOT NULL,
    created_at {$timestampType} NOT NULL,
    updated_at {$timestampType} NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
)");
echo "  [OK] families\n";

// ---- family_members ----
$db->exec("CREATE TABLE IF NOT EXISTS family_members (
    id {$autoIncrement},
    family_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member',
    joined_at {$timestampType} NOT NULL,
    FOREIGN KEY (family_id) REFERENCES families(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(family_id, user_id)
)");
echo "  [OK] family_members\n";

// ---- family_invites ----
$db->exec("CREATE TABLE IF NOT EXISTS family_invites (
    id {$autoIncrement},
    family_id INTEGER NOT NULL,
    email VARCHAR(255),
    invited_by INTEGER NOT NULL,
    created_at {$timestampType} NOT NULL,
    FOREIGN KEY (family_id) REFERENCES families(id),
    FOREIGN KEY (invited_by) REFERENCES users(id)
)");
echo "  [OK] family_invites\n";

// ---- wish_lists ----
$db->exec("CREATE TABLE IF NOT EXISTS wish_lists (
    id {$autoIncrement},
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    visibility VARCHAR(30) NOT NULL DEFAULT 'all_families',
    created_at {$timestampType} NOT NULL,
    updated_at {$timestampType} NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");
echo "  [OK] wish_lists\n";

// ---- list_family_shares ----
$db->exec("CREATE TABLE IF NOT EXISTS list_family_shares (
    id {$autoIncrement},
    list_id INTEGER NOT NULL,
    family_id INTEGER NOT NULL,
    FOREIGN KEY (list_id) REFERENCES wish_lists(id),
    FOREIGN KEY (family_id) REFERENCES families(id),
    UNIQUE(list_id, family_id)
)");
echo "  [OK] list_family_shares\n";

// ---- items ----
$db->exec("CREATE TABLE IF NOT EXISTS items (
    id {$autoIncrement},
    list_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    price DECIMAL(10,2),
    quantity_desired INTEGER NOT NULL DEFAULT 1,
    image_url TEXT DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at {$timestampType} NOT NULL,
    updated_at {$timestampType} NOT NULL,
    FOREIGN KEY (list_id) REFERENCES wish_lists(id)
)");
echo "  [OK] items\n";

// ---- item_links ----
$db->exec("CREATE TABLE IF NOT EXISTS item_links (
    id {$autoIncrement},
    item_id INTEGER NOT NULL,
    url TEXT NOT NULL,
    label VARCHAR(255) DEFAULT '',
    FOREIGN KEY (item_id) REFERENCES items(id)
)");
echo "  [OK] item_links\n";

// ---- purchases ----
$db->exec("CREATE TABLE IF NOT EXISTS purchases (
    id {$autoIncrement},
    item_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    purchased_at {$timestampType} NOT NULL,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)");
echo "  [OK] purchases\n";

// ---- scrape_logs ----
$db->exec("CREATE TABLE IF NOT EXISTS scrape_logs (
    id {$autoIncrement},
    user_id INTEGER,
    url TEXT NOT NULL,
    host VARCHAR(255) DEFAULT '',
    http_code INTEGER,
    raw_html LONGTEXT,
    html_length INTEGER DEFAULT 0,
    extracted_name TEXT DEFAULT '',
    extracted_price DECIMAL(10,2),
    extracted_image_url TEXT DEFAULT '',
    extracted_store_name VARCHAR(255) DEFAULT '',
    success INTEGER NOT NULL DEFAULT 0,
    error_message TEXT DEFAULT '',
    duration_ms INTEGER DEFAULT 0,
    created_at {$timestampType} NOT NULL
)");
echo "  [OK] scrape_logs\n";

// ---- Indexes ----
$indexes = [
    'CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)',
    'CREATE INDEX IF NOT EXISTS idx_family_members_user ON family_members(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_family_members_family ON family_members(family_id)',
    'CREATE INDEX IF NOT EXISTS idx_wish_lists_user ON wish_lists(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_items_list ON items(list_id)',
    'CREATE INDEX IF NOT EXISTS idx_item_links_item ON item_links(item_id)',
    'CREATE INDEX IF NOT EXISTS idx_purchases_item ON purchases(item_id)',
    'CREATE INDEX IF NOT EXISTS idx_purchases_user ON purchases(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_list_family_shares_list ON list_family_shares(list_id)',
    'CREATE INDEX IF NOT EXISTS idx_list_family_shares_family ON list_family_shares(family_id)',
    'CREATE INDEX IF NOT EXISTS idx_families_invite_code ON families(invite_code)',
    'CREATE INDEX IF NOT EXISTS idx_scrape_logs_user ON scrape_logs(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_scrape_logs_created ON scrape_logs(created_at)',
    'CREATE INDEX IF NOT EXISTS idx_scrape_logs_host ON scrape_logs(host)',
];

foreach ($indexes as $idx) {
    $db->exec($idx);
}
echo "  [OK] indexes\n";

// ---- Schema updates (safe to re-run) ----
$alterStatements = [
    "ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0",
];

foreach ($alterStatements as $sql) {
    try {
        $db->exec($sql);
        echo "  [OK] " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        // Column already exists — skip silently
        if (!str_contains($e->getMessage(), 'duplicate') && !str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'Duplicate column')) {
            // For SQLite: "duplicate column name"
            // Just ignore — the column exists
        }
    }
}

echo "\nMigration complete!\n";
