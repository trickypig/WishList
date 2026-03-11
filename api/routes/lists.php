<?php

function registerListRoutes(Router $router, PDO $db): void
{
    // GET /lists - user's own lists
    $router->get('/lists', function (array $params) use ($db) {
        $user = authenticate();
        $lists = WishList::getByUserId($db, $user['id']);
        Response::json(['lists' => $lists]);
    });

    // POST /lists - create a new list
    $router->post('/lists', function (array $params) use ($db) {
        $user = authenticate();
        $body = $params['_body'];

        $missing = Validator::required($body, ['title']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $listId = WishList::create(
            $db,
            $user['id'],
            $body['title'],
            $body['description'] ?? '',
            $body['visibility'] ?? 'all_families'
        );

        $list = WishList::getById($db, $listId);
        Response::json(['list' => $list], 201);
    });

    // GET /lists/family/{familyId} - lists visible to a family
    // NOTE: This must be registered BEFORE /lists/{id} to avoid {id} matching "family"
    $router->get('/lists/family/{familyId}', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['familyId'];

        if (!Family::isMember($db, $familyId, $user['id'])) {
            Response::error('You are not a member of this family', 403);
        }

        $lists = WishList::getByFamilyId($db, $familyId, $user['id']);
        Response::json(['lists' => $lists]);
    });

    // GET /lists/{id} - single list with items
    $router->get('/lists/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['id'];

        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }

        $items = Item::getByListId($db, $listId);
        $isOwner = (int) $list['user_id'] === (int) $user['id'];

        if (!$isOwner) {
            // Add purchase info to each item, but only for non-owners
            foreach ($items as &$item) {
                $purchaseInfo = Purchase::getPurchaseInfo($db, $item['id'], $user['id']);
                $item['total_purchased'] = $purchaseInfo['total_purchased'];
                $item['purchased_by_me'] = $purchaseInfo['purchased_by_me'];
            }
            unset($item);
        }

        $list['items'] = $items;
        $list['is_owner'] = $isOwner;

        Response::json(['list' => $list]);
    });

    // PUT /lists/{id} - update list
    $router->put('/lists/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['id'];
        $body = $params['_body'];

        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }

        if ((int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        $missing = Validator::required($body, ['title']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        WishList::update($db, $listId, $body['title'], $body['description'] ?? '');

        $updated = WishList::getById($db, $listId);
        Response::json(['list' => $updated]);
    });

    // DELETE /lists/{id} - delete list
    $router->delete('/lists/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['id'];

        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }

        if ((int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        WishList::delete($db, $listId);
        Response::json(['message' => 'List deleted']);
    });

    // PUT /lists/{id}/sharing - update sharing settings
    $router->put('/lists/{id}/sharing', function (array $params) use ($db) {
        $user = authenticate();
        $listId = (int) $params['id'];
        $body = $params['_body'];

        $list = WishList::getById($db, $listId);
        if (!$list) {
            Response::notFound('List not found');
        }

        if ((int) $list['user_id'] !== (int) $user['id']) {
            Response::error('You do not own this list', 403);
        }

        $visibility = $body['visibility'] ?? 'all_families';
        $familyIds = $body['family_ids'] ?? [];

        WishList::updateSharing($db, $listId, $visibility, $familyIds);

        $updated = WishList::getById($db, $listId);
        Response::json(['list' => $updated]);
    });
}
