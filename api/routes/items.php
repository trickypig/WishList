<?php

function registerItemRoutes(Router $router, PDO $db): void
{
    // POST /lists/{listId}/items - create item
    $router->post('/lists/{listId}/items', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['listId'];
        $body = $params['_body'];

        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }

        if ((int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        $missing = Validator::required($body, ['name']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $itemId = Item::create(
            $db,
            $listId,
            $body['name'],
            $body['description'] ?? '',
            isset($body['price']) ? (float) $body['price'] : null,
            (int) ($body['quantity_desired'] ?? 1),
            $body['image_url'] ?? '',
            (int) ($body['sort_order'] ?? 0),
            $body['links'] ?? []
        );

        $item = Item::getById($db, $itemId);
        Response::json(['item' => $item], 201);
    });

    // PUT /items/{id} - update item
    $router->put('/items/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $itemId = (int) $params['id'];
        $body = $params['_body'];

        $item = Item::getById($db, $itemId);
        if (!$item) {
            Response::notFound('Item not found');
        }

        $list = WishList::getById($db, $item['list_id']);
        if (!$list || (int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        $missing = Validator::required($body, ['name']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        Item::update(
            $db,
            $itemId,
            $body['name'],
            $body['description'] ?? '',
            isset($body['price']) ? (float) $body['price'] : null,
            (int) ($body['quantity_desired'] ?? 1),
            $body['image_url'] ?? '',
            (int) ($body['sort_order'] ?? 0),
            $body['links'] ?? []
        );

        $updated = Item::getById($db, $itemId);
        Response::json(['item' => $updated]);
    });

    // DELETE /items/{id} - delete item
    $router->delete('/items/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $itemId = (int) $params['id'];

        $item = Item::getById($db, $itemId);
        if (!$item) {
            Response::notFound('Item not found');
        }

        $list = WishList::getById($db, $item['list_id']);
        if (!$list || (int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        Item::delete($db, $itemId);
        Response::json(['message' => 'Item deleted']);
    });

    // PUT /lists/{listId}/items/reorder - reorder items
    $router->put('/lists/{listId}/items/reorder', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['listId'];
        $body = $params['_body'];

        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }

        if ((int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        if (!isset($body['item_ids']) || !is_array($body['item_ids'])) {
            Response::error('item_ids array is required');
        }

        Item::reorder($db, $listId, $body['item_ids']);
        Response::json(['message' => 'Items reordered']);
    });
}
