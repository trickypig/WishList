<?php

/**
 * Wish List API - Front Controller
 * Made by - TrickyPig
 */

// Environment
require_once __DIR__ . '/env.php';

// Config
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

// Helpers
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/Router.php';

// Middleware
require_once __DIR__ . '/middleware/Cors.php';
require_once __DIR__ . '/middleware/Auth.php';

// Models
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/WishList.php';
require_once __DIR__ . '/models/Item.php';
require_once __DIR__ . '/models/Family.php';
require_once __DIR__ . '/models/Purchase.php';

// Routes
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/lists.php';
require_once __DIR__ . '/routes/items.php';
require_once __DIR__ . '/routes/families.php';
require_once __DIR__ . '/routes/purchases.php';
require_once __DIR__ . '/routes/scrape.php';
require_once __DIR__ . '/routes/admin.php';

// Handle CORS
handleCors();

// Database connection
try {
    $db = getDatabase();
} catch (PDOException $e) {
    Response::error('Database connection failed: ' . $e->getMessage(), 500);
}

// Router
$router = new Router();

// Register all routes
registerAuthRoutes($router, $db);
registerListRoutes($router, $db);
registerItemRoutes($router, $db);
registerFamilyRoutes($router, $db);
registerPurchaseRoutes($router, $db);
registerScrapeRoutes($router, $db);
registerAdminRoutes($router, $db);

// Dispatch
$router->dispatch();
