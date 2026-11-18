<?php

// Run: php api/tests/suggestions-test.php. All data stays in memory.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

if (($argv[1] ?? '') === '--migrate') {
    require_once __DIR__ . '/../env.php';
    $_ENV_LOADED = true;
    $_ENV_VALUES = ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $argv[2]];
    include __DIR__ . '/../migrations/migrate.php';
    include __DIR__ . '/../migrations/migrate.php';
    exit;
}

// Capture the response boundary instead of exiting the test process.
class TestResponse extends RuntimeException
{
    public function __construct(public mixed $data, public int $status) { parent::__construct(); }
}
class Response
{
    public static function json($data, int $status = 200): void { throw new TestResponse($data, $status); }
    public static function error(string $message, int $status = 400): void { self::json(['error' => $message], $status); }
    public static function notFound(string $message = 'Not found'): void { self::error($message, 404); }
}
function authenticate(): array { return ['id' => $GLOBALS['requester']]; }

foreach (['helpers/Router', 'helpers/Validator', 'helpers/StoreName', 'models/Item', 'models/ItemGroup',
          'models/WishList', 'models/Purchase', 'routes/lists', 'routes/items', 'routes/groups', 'routes/purchases'] as $file) {
    require_once __DIR__ . '/../' . $file . '.php';
}

$failures = 0;
function check(string $name, bool $passed): void
{
    global $failures;
    echo ($passed ? '[OK] ' : '[FAIL] ') . $name . "\n";
    if (!$passed) $failures++;
}
function callRoute(PDO $db, int $user, string $method, string $path, array $params = [], array $query = []): TestResponse
{
    $GLOBALS['requester'] = $user;
    $_GET = $query;
    $router = new Router();
    registerListRoutes($router, $db);
    registerItemRoutes($router, $db);
    registerGroupRoutes($router, $db);
    registerPurchaseRoutes($router, $db);
    $routes = (new ReflectionProperty(Router::class, 'routes'))->getValue($router);
    foreach ($routes as $route) {
        if ($route['method'] === $method && $route['path'] === $path) {
            try { ($route['handler'])($params + ['_body' => []]); }
            catch (TestResponse $response) { return $response; }
        }
    }
    return new TestResponse(['error' => 'Route not found'], 404);
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, display_name TEXT);
    INSERT INTO users VALUES (1, 'Owner'), (2, 'Alex'), (3, 'Sam'), (4, 'Outsider');
    CREATE TABLE family_members (family_id INTEGER, user_id INTEGER);
    CREATE TABLE families (id INTEGER PRIMARY KEY, name TEXT);
    INSERT INTO families VALUES (1, 'Close family'), (2, 'Extended family');
    INSERT INTO family_members VALUES (1,1), (1,2), (1,3), (2,1), (2,4);
    CREATE TABLE wish_lists (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, description TEXT, visibility TEXT, updated_at TEXT);
    INSERT INTO wish_lists VALUES (1,1,'Birthday','','all_families',''), (2,1,'Private','','private',''), (3,1,'Selected','','selected_families','');
    CREATE TABLE list_family_shares (list_id INTEGER, family_id INTEGER);
    INSERT INTO list_family_shares VALUES (3,1);
    CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, list_id INTEGER, group_id INTEGER, suggested_by INTEGER NULL,
        name TEXT, description TEXT, price DECIMAL(10,2), quantity_desired INTEGER, image_url TEXT,
        sort_order INTEGER, created_at TEXT, updated_at TEXT);
    CREATE TABLE item_links (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, url TEXT, label TEXT);
    CREATE TABLE purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, user_id INTEGER, quantity INTEGER, purchased_at TEXT);");
ItemGroup::createTable($db, 'INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT');
$official = Item::create($db, 1, 'Official wish');

$r = callRoute($db, 2, 'POST', '/lists/{listId}/suggestions', ['listId' => 1, '_body' => [
    'name' => 'Suggested book', 'description' => 'A good read', 'price' => 12.50, 'quantity_desired' => 2,
    'suggested_by' => 3, 'group_id' => 999,
    'links' => [['url' => 'https://example.com/book', 'store_name' => 'Book shop']],
]]);
check('family member can create a suggestion', $r->status === 201);
if ($r->status !== 201) exit(1);
$suggestion = (int) $r->data['item']['id'];
check('server assigns attribution, ignoring spoofed author and official group',
    (int) $r->data['item']['suggested_by'] === 2 && $r->data['item']['group_id'] === null);

$owner = callRoute($db, 1, 'GET', '/lists/{id}', ['id' => 1])->data['list'];
$selected = callRoute($db, 1, 'GET', '/lists/{id}', ['id' => 3])->data['list'];
check('owner receives selected sharing family names and ids',
    ($selected['family_ids'] ?? null) === [1]
    && ($selected['shared_families'] ?? null) === [['id' => 1, 'name' => 'Close family']]);
check('viewers do not receive owner sharing details', !isset(callRoute($db, 2, 'GET', '/lists/{id}', ['id' => 3])->data['list']['shared_families']));
callRoute($db, 1, 'PUT', '/lists/{id}/sharing', ['id' => 3,
    '_body' => ['visibility' => 'specific_families', 'family_ids' => [1]]]);
check('web selected sharing value preserves family access',
    WishList::canAccess($db, WishList::getById($db, 3), 2)
    && !WishList::canAccess($db, WishList::getById($db, 3), 4));
check('owner receives only official items by default', count($owner['items']) === 1 && (int) $owner['items'][0]['id'] === $official
    && empty($owner['suggestions']) && !str_contains(json_encode($owner), 'Suggested book'));
$member = callRoute($db, 3, 'GET', '/lists/{id}', ['id' => 1])->data['list'];
check('members see suggestions separately with attribution and links', count($member['items']) === 1
    && count($member['suggestions']) === 1 && $member['suggestions'][0]['suggested_by_name'] === 'Alex'
    && $member['suggestions'][0]['links'][0]['store_name'] === 'Book shop');
check('suggestions retain normal product fields', (float) $member['suggestions'][0]['price'] === 12.5
    && (int) $member['suggestions'][0]['quantity_desired'] === 2);

check('suggestion purchase works', callRoute($db, 3, 'POST', '/items/{itemId}/purchase', ['itemId' => $suggestion])->status === 201);
$revealed = callRoute($db, 1, 'GET', '/lists/{id}', ['id' => 1], ['include_suggestions' => '1'])->data['list'];
check('explicit owner reveal returns suggestions', count($revealed['suggestions']) === 1);
check('owner reveal never returns purchase details', !isset($revealed['suggestions'][0]['total_purchased'])
    && !isset($revealed['suggestions'][0]['purchased_by_me']));
$member = callRoute($db, 3, 'GET', '/lists/{id}', ['id' => 1])->data['list'];
check('members see purchase counts on suggestions', (int) $member['suggestions'][0]['total_purchased'] === 1
    && (int) $member['suggestions'][0]['purchased_by_me'] === 1);
check('members see who claimed suggestions', ($member['suggestions'][0]['purchaser_names'] ?? null) === ['Sam']);
check('owner reveal excludes claimant names', !isset($revealed['suggestions'][0]['purchaser_names']));
Purchase::mark($db, $official, 2);
$claimedList = callRoute($db, 3, 'GET', '/lists/{id}', ['id' => 1])->data['list'];
check('members see who claimed official wishes', ($claimedList['items'][0]['purchaser_names'] ?? null) === ['Alex']);
$ownerList = callRoute($db, 1, 'GET', '/lists/{id}', ['id' => 1])->data['list'];
check('official claimant names stay hidden from owner', !isset($ownerList['items'][0]['purchaser_names']));
Purchase::unmark($db, $official, 2);
check('owner cannot learn purchase state via undo endpoint', callRoute($db, 1, 'DELETE', '/items/{itemId}/purchase', ['itemId' => $suggestion])->status === 403);

check('author can edit their suggestion', callRoute($db, 2, 'PUT', '/items/{id}', ['id' => $suggestion,
    '_body' => ['name' => 'Updated book', 'suggested_by' => null]])->status === 200);
check('editing cannot remove attribution', (int) Item::getById($db, $suggestion)['suggested_by'] === 2);
check('another member cannot edit suggestion', callRoute($db, 3, 'PUT', '/items/{id}', ['id' => $suggestion, '_body' => ['name' => 'Changed']])->status === 403);
check('owner cannot edit someone else\'s suggestion', callRoute($db, 1, 'PUT', '/items/{id}', ['id' => $suggestion, '_body' => ['name' => 'Changed']])->status === 403);
check('member cannot edit official wishes', callRoute($db, 2, 'PUT', '/items/{id}', ['id' => $official, '_body' => ['name' => 'Changed']])->status === 403);
check('member cannot create official wishes', callRoute($db, 2, 'POST', '/lists/{listId}/items', ['listId' => 1, '_body' => ['name' => 'Changed']])->status === 403);
check('owner cannot suggest on own list', callRoute($db, 1, 'POST', '/lists/{listId}/suggestions', ['listId' => 1, '_body' => ['name' => 'Changed']])->status === 403);
check('another member cannot delete suggestion', callRoute($db, 3, 'DELETE', '/items/{id}', ['id' => $suggestion])->status === 403);

foreach ([2 => 2, 3 => 4] as $listId => $userId) {
    check("unshared list $listId cannot be read by known ID", callRoute($db, $userId, 'GET', '/lists/{id}', ['id' => $listId], ['include_suggestions' => '1'])->status === 403);
    check("unshared list $listId rejects suggestions", callRoute($db, $userId, 'POST', '/lists/{listId}/suggestions', ['listId' => $listId, '_body' => ['name' => 'Hidden']])->status === 403);
}
check('selected-family member can suggest', callRoute($db, 2, 'POST', '/lists/{listId}/suggestions', ['listId' => 3, '_body' => ['name' => 'Selected gift']])->status === 201);
$privateItem = Item::create($db, 2, 'Private gift');
check('unshared item cannot be purchased', callRoute($db, 2, 'POST', '/items/{itemId}/purchase', ['itemId' => $privateItem])->status === 403);
check('unshared item purchase cannot be inspected via undo', callRoute($db, 2, 'DELETE', '/items/{itemId}/purchase', ['itemId' => $privateItem])->status === 403);

$group = ItemGroup::create($db, 1, 'Official group', '');
callRoute($db, 1, 'PUT', '/lists/{listId}/items/arrange', ['listId' => 1, '_body' => ['items' => [['id' => $official, 'group_id' => $group], ['id' => $suggestion, 'group_id' => $group]]]]);
check('official arrangement does not group suggestions', Item::getById($db, $suggestion)['group_id'] === null
    && (int) Item::getById($db, $official)['group_id'] === $group);
$before = Item::getById($db, $suggestion)['sort_order'];
callRoute($db, 1, 'PUT', '/lists/{listId}/items/reorder', ['listId' => 1, '_body' => ['item_ids' => [$official, $suggestion]]]);
check('official reorder leaves suggestion order alone', Item::getById($db, $suggestion)['sort_order'] === $before);
check('official item counts exclude suggestions', (int) WishList::getByUserId($db, 1)[0]['item_count'] >= 0
    && (int) array_values(array_filter(WishList::getByUserId($db, 1), fn($l) => (int) $l['id'] === 1))[0]['item_count'] === 1
    && (int) array_values(array_filter(WishList::getByFamilyId($db, 1, 2), fn($l) => (int) $l['id'] === 1))[0]['item_count'] === 1);

$db->exec('DELETE FROM family_members WHERE user_id = 2');
check('former member loses viewing access', callRoute($db, 2, 'GET', '/lists/{id}', ['id' => 1])->status === 403);
check('former member cannot edit own suggestion', callRoute($db, 2, 'PUT', '/items/{id}', ['id' => $suggestion, '_body' => ['name' => 'Changed']])->status === 403);
check('former member cannot delete own suggestion', callRoute($db, 2, 'DELETE', '/items/{id}', ['id' => $suggestion])->status === 403);
check('former member cannot purchase suggestion', callRoute($db, 2, 'POST', '/items/{itemId}/purchase', ['itemId' => $suggestion])->status === 403);
check('owner can remove revealed suggestion', callRoute($db, 1, 'DELETE', '/items/{id}', ['id' => $suggestion])->status === 200);
check('deleting suggestion cleans its links and purchases', Item::getById($db, $suggestion) === false
    && (int) $db->query("SELECT COUNT(*) FROM item_links WHERE item_id = $suggestion")->fetchColumn() === 0
    && (int) $db->query("SELECT COUNT(*) FROM purchases WHERE item_id = $suggestion")->fetchColumn() === 0);

// Exercise the real migration on a legacy item table and rerun it for idempotence.
$migrationPath = tempnam(sys_get_temp_dir(), 'wish-suggestions-');
$legacy = new PDO('sqlite:' . $migrationPath);
$legacy->exec("CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, list_id INTEGER, name TEXT, description TEXT,
    price DECIMAL(10,2), quantity_desired INTEGER DEFAULT 1, image_url TEXT, sort_order INTEGER DEFAULT 0,
    created_at TEXT, updated_at TEXT);
    INSERT INTO items (list_id, name) VALUES (1, 'Legacy official wish')");
$legacy = null;
// A subprocess releases the migration's cached PDO connection before Windows cleanup.
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --migrate ' . escapeshellarg($migrationPath), $migrationLines, $migrationStatus);
$migrationOutput = implode("\n", $migrationLines);
$legacy = new PDO('sqlite:' . $migrationPath);
$old = $legacy->query('SELECT * FROM items WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
check('migration preserves legacy wishes as official items', $old['name'] === 'Legacy official wish' && $old['suggested_by'] === null);
check('migration safely reruns', $migrationStatus === 0 && !str_contains($migrationOutput, '[ERROR]'));
$legacy = null;
$db = null;
unlink($migrationPath);

echo "$failures failures\n";
exit($failures ? 1 : 0);
