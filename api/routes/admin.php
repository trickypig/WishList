<?php

function registerAdminRoutes(Router $router, PDO $db): void
{
    // GET /admin/scrape-logs - list scrape logs (paginated)
    $router->get('/admin/scrape-logs', function (array $params) use ($db) {
        requireAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        // Optional filters
        $where = [];
        $bindings = [];

        if (!empty($_GET['host'])) {
            $where[] = 'host LIKE :host';
            $bindings['host'] = '%' . $_GET['host'] . '%';
        }
        if (!empty($_GET['success'])) {
            $where[] = 'success = :success';
            $bindings['success'] = (int) $_GET['success'];
        }
        if (!empty($_GET['user_id'])) {
            $where[] = 'user_id = :user_id';
            $bindings['user_id'] = (int) $_GET['user_id'];
        }
        if (isset($_GET['has_error']) && $_GET['has_error'] === '1') {
            $where[] = "error_message != ''";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM scrape_logs {$whereClause}");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetch()['total'];

        // Get logs (exclude raw_html from listing for performance)
        $stmt = $db->prepare(
            "SELECT id, user_id, url, host, http_code, html_length,
                    extracted_name, extracted_price, extracted_image_url, extracted_store_name,
                    success, error_message, duration_ms, created_at
             FROM scrape_logs {$whereClause}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($bindings as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        // Enrich with user display names
        $userIds = array_unique(array_filter(array_column($logs, 'user_id')));
        $userNames = [];
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $uStmt = $db->prepare("SELECT id, display_name, email FROM users WHERE id IN ({$placeholders})");
            $uStmt->execute(array_values($userIds));
            foreach ($uStmt->fetchAll() as $u) {
                $userNames[$u['id']] = $u;
            }
        }

        foreach ($logs as &$log) {
            $uid = $log['user_id'];
            $log['user_display_name'] = $userNames[$uid]['display_name'] ?? 'Unknown';
            $log['user_email'] = $userNames[$uid]['email'] ?? '';
        }
        unset($log);

        Response::json([
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    });

    // GET /admin/scrape-logs/{id} - get single log with raw HTML
    $router->get('/admin/scrape-logs/{id}', function (array $params) use ($db) {
        requireAdmin();

        $id = (int) $params['id'];
        $stmt = $db->prepare("SELECT * FROM scrape_logs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $log = $stmt->fetch();

        if (!$log) {
            Response::notFound('Log not found');
        }

        // Get user info
        if ($log['user_id']) {
            $user = User::findById($db, (int) $log['user_id']);
            $log['user_display_name'] = $user ? $user['display_name'] : 'Unknown';
            $log['user_email'] = $user ? $user['email'] : '';
        }

        Response::json(['log' => $log]);
    });

    // GET /admin/stats - basic admin stats
    $router->get('/admin/stats', function (array $params) use ($db) {
        requireAdmin();

        $stats = [];

        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $stats['total_users'] = (int) $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM wish_lists");
        $stats['total_lists'] = (int) $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM items");
        $stats['total_items'] = (int) $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM families");
        $stats['total_families'] = (int) $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM scrape_logs");
        $stats['total_scrapes'] = (int) $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM scrape_logs WHERE success = 1");
        $stats['successful_scrapes'] = (int) $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM scrape_logs WHERE success = 0");
        $stats['failed_scrapes'] = (int) $stmt->fetch()['total'];

        Response::json(['stats' => $stats]);
    });
}
