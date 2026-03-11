<?php

class Item
{
    public static function getByListId(PDO $db, int $listId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM items WHERE list_id = :list_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['list_id' => $listId]);
        $items = $stmt->fetchAll();

        // Attach links to each item
        $linkStmt = $db->prepare('SELECT * FROM item_links WHERE item_id = :item_id ORDER BY id ASC');
        foreach ($items as &$item) {
            $linkStmt->execute(['item_id' => $item['id']]);
            $item['links'] = $linkStmt->fetchAll();
        }

        return $items;
    }

    public static function getById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function create(
        PDO $db,
        int $listId,
        string $name,
        string $description = '',
        ?float $price = null,
        int $quantityDesired = 1,
        string $imageUrl = '',
        int $sortOrder = 0,
        array $links = []
    ): int {
        $stmt = $db->prepare(
            'INSERT INTO items (list_id, name, description, price, quantity_desired, image_url, sort_order, created_at, updated_at)
             VALUES (:list_id, :name, :description, :price, :quantity_desired, :image_url, :sort_order, :created_at, :updated_at)'
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'list_id'          => $listId,
            'name'             => $name,
            'description'      => $description,
            'price'            => $price,
            'quantity_desired'  => $quantityDesired,
            'image_url'        => $imageUrl,
            'sort_order'       => $sortOrder,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $itemId = (int) $db->lastInsertId();

        self::insertLinks($db, $itemId, $links);

        // Update parent list timestamp
        $db->prepare('UPDATE wish_lists SET updated_at = :updated_at WHERE id = :id')
            ->execute(['updated_at' => $now, 'id' => $listId]);

        return $itemId;
    }

    public static function update(
        PDO $db,
        int $id,
        string $name,
        string $description = '',
        ?float $price = null,
        int $quantityDesired = 1,
        string $imageUrl = '',
        int $sortOrder = 0,
        array $links = []
    ): void {
        $stmt = $db->prepare(
            'UPDATE items SET name = :name, description = :description, price = :price,
             quantity_desired = :quantity_desired, image_url = :image_url,
             sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id'
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'name'             => $name,
            'description'      => $description,
            'price'            => $price,
            'quantity_desired'  => $quantityDesired,
            'image_url'        => $imageUrl,
            'sort_order'       => $sortOrder,
            'updated_at'       => $now,
            'id'               => $id,
        ]);

        // Replace links
        $db->prepare('DELETE FROM item_links WHERE item_id = :item_id')->execute(['item_id' => $id]);
        self::insertLinks($db, $id, $links);

        // Update parent list timestamp
        $item = self::getById($db, $id);
        if ($item) {
            $db->prepare('UPDATE wish_lists SET updated_at = :updated_at WHERE id = :id')
                ->execute(['updated_at' => $now, 'id' => $item['list_id']]);
        }
    }

    public static function delete(PDO $db, int $id): void
    {
        $item = self::getById($db, $id);
        $db->prepare('DELETE FROM item_links WHERE item_id = :item_id')->execute(['item_id' => $id]);
        $db->prepare('DELETE FROM purchases WHERE item_id = :item_id')->execute(['item_id' => $id]);
        $db->prepare('DELETE FROM items WHERE id = :id')->execute(['id' => $id]);

        if ($item) {
            $db->prepare('UPDATE wish_lists SET updated_at = :updated_at WHERE id = :id')
                ->execute(['updated_at' => date('Y-m-d H:i:s'), 'id' => $item['list_id']]);
        }
    }

    public static function reorder(PDO $db, int $listId, array $itemIds): void
    {
        $stmt = $db->prepare('UPDATE items SET sort_order = :sort_order WHERE id = :id AND list_id = :list_id');
        foreach ($itemIds as $order => $itemId) {
            $stmt->execute([
                'sort_order' => $order,
                'id'         => $itemId,
                'list_id'    => $listId,
            ]);
        }
    }

    private static function insertLinks(PDO $db, int $itemId, array $links): void
    {
        if (empty($links)) {
            return;
        }
        $stmt = $db->prepare(
            'INSERT INTO item_links (item_id, url, label) VALUES (:item_id, :url, :label)'
        );
        foreach ($links as $link) {
            $stmt->execute([
                'item_id' => $itemId,
                'url'     => $link['url'] ?? '',
                'label'   => $link['label'] ?? '',
            ]);
        }
    }
}
