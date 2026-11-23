<?php

// Run: php api/tests/move-item-test.php. All data stays in memory.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

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
    INSERT INTO wish_lists VALUES (1,1,'Birthday','','all_families',''), (2,1,'Holiday','','private',''), (4,2,'Alex list','','all_families','');
    CREATE TABLE list_family_shares (list_id INTEGER, family_id INTEGER);
    CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, list_id INTEGER, group_id INTEGER, suggested_by INTEGER NULL,
        name TEXT, description TEXT, price DECIMAL(10,2), quantity_desired INTEGER, image_url TEXT,
        sort_order INTEGER, created_at TEXT, updated_at TEXT);
    CREATE TABLE item_links (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, url TEXT, label TEXT);
    CREATE TABLE purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, user_id INTEGER, quantity INTEGER, purchased_at TEXT);");
ItemGroup::createTable($db, 'INTEGER PRIMARY KEY AUTOINCREMENT', 'TEXT');

$wish = Item::create($db, 1, 'Movable wish', '', null, 1, '', 0, [['url' => 'https://example.com/a', 'store_name' => 'Shop']]);
$group = ItemGroup::create($db, 1, 'Birthday group', '');
Item::arrange($db, 1, [['id' => $wish, 'group_id' => $group]]);
Item::create($db, 2, 'Already there', '', null, 1, '', 5);
Purchase::mark($db, $wish, 2);

$r = callRoute($db, 1, 'PUT', '/items/{id}', ['id' => $wish, '_body' => ['name' => 'Moved wish', 'list_id' => 2,
    'links' => [['url' => 'https://example.com/a', 'store_name' => 'Shop']]]]);
$moved = Item::getById($db, $wish);
check('owner can move an item to another of their lists', $r->status === 200 && (int) $moved['list_id'] === 2);
check('move saves the edited fields too', $moved['name'] === 'Moved wish');
check('moved item leaves its old group and goes to the end', $moved['group_id'] === null && (int) $moved['sort_order'] === 6);
check('moved item keeps links and purchases',
    (int) $db->query("SELECT COUNT(*) FROM item_links WHERE item_id = $wish")->fetchColumn() === 1
    && (int) $db->query("SELECT COUNT(*) FROM purchases WHERE item_id = $wish")->fetchColumn() === 1);
check('response reports the new list', (int) $r->data['item']['list_id'] === 2);

check('same list_id is a plain edit', callRoute($db, 1, 'PUT', '/items/{id}', ['id' => $wish,
    '_body' => ['name' => 'Moved wish', 'list_id' => 2]])->status === 200 && (int) Item::getById($db, $wish)['list_id'] === 2);
check('owner cannot move to someone else\'s list', callRoute($db, 1, 'PUT', '/items/{id}', ['id' => $wish,
    '_body' => ['name' => 'x', 'list_id' => 4]])->status === 403 && (int) Item::getById($db, $wish)['list_id'] === 2);
check('missing target list is rejected', callRoute($db, 1, 'PUT', '/items/{id}', ['id' => $wish,
    '_body' => ['name' => 'x', 'list_id' => 999]])->status === 404 && Item::getById($db, $wish)['name'] === 'Moved wish');

$suggestion = Item::create($db, 1, 'Suggested', '', null, 1, '', 0, [], null, 2);
check('suggestions cannot be moved', callRoute($db, 2, 'PUT', '/items/{id}', ['id' => $suggestion,
    '_body' => ['name' => 'Suggested', 'list_id' => 4]])->status === 403 && (int) Item::getById($db, $suggestion)['list_id'] === 1);

echo "$failures failures\n";
exit($failures ? 1 : 0);
