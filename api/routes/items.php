<?php

function registerItemRoutes(Router $router, PDO $db): void
{
    $router->post('/lists/{listId}/suggestions', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['listId'];
        $body = $params['_body'];
        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }
        if ((int) $list['user_id'] === (int) $user['id'] || !WishList::canAccess($db, $list, (int) $user['id'])) {
            Response::error('You can only suggest items on another family member\'s shared list', 403);
        }
        $missing = Validator::required($body, ['name']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }
        $itemId = Item::create(
            $db, $listId, $body['name'], $body['description'] ?? '',
            isset($body['price']) ? (float) $body['price'] : null,
            max(1, (int) ($body['quantity_desired'] ?? 1)), $body['image_url'] ?? '',
            (int) ($body['sort_order'] ?? 0), $body['links'] ?? [], null, (int) $user['id']
        );
        Response::json(['item' => Item::getById($db, $itemId)], 201);
    });

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
            $body['links'] ?? [],
            resolveGroupId($db, $listId, $body['group_id'] ?? null)
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
        $canEdit = $item['suggested_by'] === null
            ? $list && (int) $list['user_id'] === (int) $user['id']
            : (int) $item['suggested_by'] === (int) $user['id'];
        if (!$list || !$canEdit || !WishList::canAccess($db, $list, (int) $user['id'])) {
            Response::error('You cannot edit this item', 403);
        }

        $missing = Validator::required($body, ['name']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        // Optional move: the owner can send another of their own lists as list_id
        $targetListId = isset($body['list_id']) ? (int) $body['list_id'] : (int) $item['list_id'];
        if ($targetListId !== (int) $item['list_id']) {
            if ($item['suggested_by'] !== null) {
                Response::error('Suggestions cannot be moved to another list', 403);
            }
            $target = WishList::getById($db, $targetListId);
            if (!$target) {
                Response::notFound('Target list not found');
            }
            if ((int) $target['user_id'] !== (int) $user['id']) {
                Response::error('You can only move items to your own lists', 403);
            }
        }

        Item::update(
            $db,
            $itemId,
            $body['name'],
            $body['description'] ?? '',
            isset($body['price']) ? (float) $body['price'] : null,
            (int) ($body['quantity_desired'] ?? 1),
            $body['image_url'] ?? '',
            (int) ($body['sort_order'] ?? $item['sort_order']),
            $body['links'] ?? []
        );

        if ($targetListId !== (int) $item['list_id']) {
            Item::moveToList($db, $itemId, $targetListId);
        }

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
        $canDelete = $list && ((int) $list['user_id'] === (int) $user['id']
            || ($item['suggested_by'] !== null && (int) $item['suggested_by'] === (int) $user['id']));
        if (!$canDelete || !WishList::canAccess($db, $list, (int) $user['id'])) {
            Response::error('You cannot delete this item', 403);
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
