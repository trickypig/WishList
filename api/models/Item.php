<?php

class Item
{
    public static function getByListId(PDO $db, int $listId, bool $suggestions = false): array
    {
        $stmt = $db->prepare(
            'SELECT i.*, u.display_name AS suggested_by_name FROM items i
             LEFT JOIN users u ON u.id = i.suggested_by
             WHERE i.list_id = :list_id AND i.suggested_by IS ' . ($suggestions ? 'NOT NULL' : 'NULL') . '
             ORDER BY i.sort_order ASC, i.id ASC'
        );
        $stmt->execute(['list_id' => $listId]);
        $items = $stmt->fetchAll();

        // Attach links to each item
        $linkStmt = $db->prepare('SELECT * FROM item_links WHERE item_id = :item_id ORDER BY id ASC');
        foreach ($items as &$item) {
            $linkStmt->execute(['item_id' => $item['id']]);
            $item['links'] = array_map([self::class, 'withStoreName'], $linkStmt->fetchAll());
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
        array $links = [],
        ?int $groupId = null,
        ?int $suggestedBy = null
    ): int {
        $stmt = $db->prepare(
            'INSERT INTO items (list_id, group_id, suggested_by, name, description, price, quantity_desired, image_url, sort_order, created_at, updated_at)
             VALUES (:list_id, :group_id, :suggested_by, :name, :description, :price, :quantity_desired, :image_url, :sort_order, :created_at, :updated_at)'
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'list_id'          => $listId,
            'group_id'         => $groupId,
            'suggested_by'     => $suggestedBy,
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
        $stmt = $db->prepare('UPDATE items SET sort_order = :sort_order WHERE id = :id AND list_id = :list_id AND suggested_by IS NULL');
        foreach ($itemIds as $order => $itemId) {
            $stmt->execute([
                'sort_order' => $order,
                'id'         => $itemId,
                'list_id'    => $listId,
            ]);
        }
    }

    /**
     * Saves the order and group of items in one pass, so a drag into a group lands at the dropped position.
     * Each entry is ['id' => int, 'group_id' => int|null]; ids from other lists are ignored.
     */
    public static function arrange(PDO $db, int $listId, array $entries): void
    {
        $stmt = $db->prepare(
            'UPDATE items SET sort_order = :sort_order, group_id = :group_id WHERE id = :id AND list_id = :list_id AND suggested_by IS NULL'
        );
        foreach (array_values($entries) as $order => $entry) {
            $stmt->execute([
                'sort_order' => $order,
                'group_id'   => $entry['group_id'],
                'id'         => $entry['id'],
                'list_id'    => $listId,
            ]);
        }
        $db->prepare('UPDATE wish_lists SET updated_at = :updated_at WHERE id = :id')
            ->execute(['updated_at' => date('Y-m-d H:i:s'), 'id' => $listId]);
    }

    /**
     * Clients read store_name: the saved label, or one worked out from the URL for links saved without it.
     */
    private static function withStoreName(array $link): array
    {
        $label = trim((string) ($link['label'] ?? ''));
        $link['store_name'] = $label !== '' ? $label : extractStoreName((string) $link['url']);
        return $link;
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
                // Clients send store_name; label is the column it is saved in
                'label'   => trim((string) ($link['store_name'] ?? $link['label'] ?? '')),
            ]);
        }
    }
}
