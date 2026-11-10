<?php

/**
 * Returns the group id if it belongs to the list, null for "no group", and rejects anything else.
 */
function resolveGroupId(PDO $db, int $listId, mixed $groupId): ?int
{
    if ($groupId === null || $groupId === '' || (int) $groupId === 0) {
        return null;
    }
    $group = ItemGroup::getById($db, (int) $groupId);
    if (!$group || (int) $group['list_id'] !== $listId) {
        Response::error('Group not found in this list', 400);
    }
    return (int) $groupId;
}

/**
 * Loads a list and stops the request unless the current user owns it.
 */
function requireOwnedList(PDO $db, int $listId, array $user): array
{
    $list = WishList::getById($db, $listId);
    if (!$list) {
        Response::notFound('List not found');
    }
    if ((int) $list['user_id'] !== (int) $user['id']) {
        Response::error('You do not own this list', 403);
    }
    return $list;
}

function registerGroupRoutes(Router $router, PDO $db): void
{
    // POST /lists/{listId}/groups - create group
    $router->post('/lists/{listId}/groups', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['listId'];
        $body = $params['_body'];
        requireOwnedList($db, $listId, $user);

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            Response::error('Missing required fields: title');
        }

        $groupId = ItemGroup::create($db, $listId, $title, (string) ($body['description'] ?? ''));
        Response::json(['group' => ItemGroup::getById($db, $groupId)], 201);
    });

    // PUT /groups/{id} - update group title/description
    $router->put('/groups/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $groupId = (int) $params['id'];
        $body = $params['_body'];

        $group = ItemGroup::getById($db, $groupId);
        if (!$group) {
            Response::notFound('Group not found');
        }
        requireOwnedList($db, (int) $group['list_id'], $user);

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            Response::error('Missing required fields: title');
        }

        ItemGroup::update($db, $groupId, $title, (string) ($body['description'] ?? ''));
        Response::json(['group' => ItemGroup::getById($db, $groupId)]);
    });

    // DELETE /groups/{id} - delete group; its items become ungrouped
    $router->delete('/groups/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $groupId = (int) $params['id'];

        $group = ItemGroup::getById($db, $groupId);
        if (!$group) {
            Response::notFound('Group not found');
        }
        requireOwnedList($db, (int) $group['list_id'], $user);

        ItemGroup::delete($db, $groupId);
        Response::json(['message' => 'Group deleted']);
    });

    // PUT /lists/{listId}/items/arrange - set order and group of every item at once
    $router->put('/lists/{listId}/items/arrange', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['listId'];
        $body = $params['_body'];
        requireOwnedList($db, $listId, $user);

        if (!isset($body['items']) || !is_array($body['items'])) {
            Response::error('items array is required');
        }

        $entries = [];
        foreach ($body['items'] as $entry) {
            if (!is_array($entry) || !isset($entry['id'])) {
                Response::error('Each item needs an id');
            }
            $entries[] = [
                'id'       => (int) $entry['id'],
                'group_id' => resolveGroupId($db, $listId, $entry['group_id'] ?? null),
            ];
        }

        Item::arrange($db, $listId, $entries);
        Response::json(['message' => 'Items arranged']);
    });
}
