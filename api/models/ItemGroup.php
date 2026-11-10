<?php

/**
 * A titled section inside a wish list that items can be dragged into.
 */
class ItemGroup
{
    public static function createTable(PDO $db, string $autoIncrement, string $timestampType): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS item_groups (
            id {$autoIncrement},
            list_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at {$timestampType} NOT NULL,
            updated_at {$timestampType} NOT NULL,
            FOREIGN KEY (list_id) REFERENCES wish_lists(id)
        )");
    }

    public static function getByListId(PDO $db, int $listId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM item_groups WHERE list_id = :list_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['list_id' => $listId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare('SELECT * FROM item_groups WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function create(PDO $db, int $listId, string $title, string $description = ''): int
    {
        $orderStmt = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM item_groups WHERE list_id = :list_id');
        $orderStmt->execute(['list_id' => $listId]);
        $sortOrder = (int) $orderStmt->fetchColumn();

        $now = date('Y-m-d H:i:s');
        $db->prepare(
            'INSERT INTO item_groups (list_id, title, description, sort_order, created_at, updated_at)
             VALUES (:list_id, :title, :description, :sort_order, :created_at, :updated_at)'
        )->execute([
            'list_id'     => $listId,
            'title'       => $title,
            'description' => $description,
            'sort_order'  => $sortOrder,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $id = (int) $db->lastInsertId();

        self::touchList($db, $listId, $now);
        return $id;
    }

    public static function update(PDO $db, int $id, string $title, string $description): void
    {
        $now = date('Y-m-d H:i:s');
        $db->prepare(
            'UPDATE item_groups SET title = :title, description = :description, updated_at = :updated_at WHERE id = :id'
        )->execute([
            'title'       => $title,
            'description' => $description,
            'updated_at'  => $now,
            'id'          => $id,
        ]);

        $group = self::getById($db, $id);
        if ($group) {
            self::touchList($db, (int) $group['list_id'], $now);
        }
    }

    /**
     * Deletes the group but keeps its items: they drop back to the ungrouped section.
     */
    public static function delete(PDO $db, int $id): void
    {
        $group = self::getById($db, $id);
        $db->prepare('UPDATE items SET group_id = NULL WHERE group_id = :group_id')->execute(['group_id' => $id]);
        $db->prepare('DELETE FROM item_groups WHERE id = :id')->execute(['id' => $id]);

        if ($group) {
            self::touchList($db, (int) $group['list_id'], date('Y-m-d H:i:s'));
        }
    }

    public static function deleteByListId(PDO $db, int $listId): void
    {
        $db->prepare('DELETE FROM item_groups WHERE list_id = :list_id')->execute(['list_id' => $listId]);
    }

    private static function touchList(PDO $db, int $listId, string $now): void
    {
        $db->prepare('UPDATE wish_lists SET updated_at = :updated_at WHERE id = :id')
            ->execute(['updated_at' => $now, 'id' => $listId]);
    }
}
