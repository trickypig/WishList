<?php

class Purchase
{
    public static function mark(PDO $db, int $itemId, int $userId, int $quantity = 1): int
    {
        $stmt = $db->prepare(
            'INSERT INTO purchases (item_id, user_id, quantity, purchased_at)
             VALUES (:item_id, :user_id, :quantity, :purchased_at)'
        );
        $stmt->execute([
            'item_id'      => $itemId,
            'user_id'      => $userId,
            'quantity'     => $quantity,
            'purchased_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function unmark(PDO $db, int $itemId, int $userId): void
    {
        $stmt = $db->prepare('DELETE FROM purchases WHERE item_id = :item_id AND user_id = :user_id');
        $stmt->execute(['item_id' => $itemId, 'user_id' => $userId]);
    }

    public static function getByItemId(PDO $db, int $itemId): array
    {
        $stmt = $db->prepare(
            'SELECT p.*, u.display_name AS purchaser_name
             FROM purchases p
             JOIN users u ON u.id = p.user_id
             WHERE p.item_id = :item_id'
        );
        $stmt->execute(['item_id' => $itemId]);
        return $stmt->fetchAll();
    }

    public static function getByUserId(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT p.*, i.name AS item_name, i.list_id
             FROM purchases p
             JOIN items i ON i.id = p.item_id
             WHERE p.user_id = :user_id
             ORDER BY p.purchased_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function getPurchaseInfo(PDO $db, int $itemId, int $requesterId): array
    {
        // Total purchased quantity
        $stmt = $db->prepare('SELECT COALESCE(SUM(quantity), 0) AS total_purchased FROM purchases WHERE item_id = :item_id');
        $stmt->execute(['item_id' => $itemId]);
        $total = (int) $stmt->fetchColumn();

        // Whether the requester has purchased this item
        $stmt = $db->prepare('SELECT 1 FROM purchases WHERE item_id = :item_id AND user_id = :user_id');
        $stmt->execute(['item_id' => $itemId, 'user_id' => $requesterId]);
        $purchasedByMe = $stmt->fetch() !== false;

        return [
            'total_purchased' => $total,
            'purchased_by_me' => $purchasedByMe,
        ];
    }
}
