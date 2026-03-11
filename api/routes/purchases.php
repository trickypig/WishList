<?php

function registerPurchaseRoutes(Router $router, PDO $db): void
{
    // POST /items/{itemId}/purchase - mark as purchased
    $router->post('/items/{itemId}/purchase', function (array $params) use ($db) {
        $user = authenticate();
        $itemId = (int) $params['itemId'];
        $body = $params['_body'];

        $item = Item::getById($db, $itemId);
        if (!$item) {
            Response::notFound('Item not found');
        }

        // Ensure the purchaser is not the list owner
        $list = WishList::getById($db, $item['list_id']);
        if ($list && (int) $list['user_id'] === (int) $user['id']) {
            Response::error('You cannot purchase items from your own list', 403);
        }

        $quantity = (int) ($body['quantity'] ?? 1);

        Purchase::mark($db, $itemId, $user['id'], $quantity);

        $purchaseInfo = Purchase::getPurchaseInfo($db, $itemId, $user['id']);
        Response::json(['purchase' => $purchaseInfo], 201);
    });

    // DELETE /items/{itemId}/purchase - unmark purchase
    $router->delete('/items/{itemId}/purchase', function (array $params) use ($db) {
        $user = authenticate();
        $itemId = (int) $params['itemId'];

        $item = Item::getById($db, $itemId);
        if (!$item) {
            Response::notFound('Item not found');
        }

        Purchase::unmark($db, $itemId, $user['id']);

        $purchaseInfo = Purchase::getPurchaseInfo($db, $itemId, $user['id']);
        Response::json(['purchase' => $purchaseInfo]);
    });
}
