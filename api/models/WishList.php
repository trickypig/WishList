<?php

class WishList
{
    public static function getByUserId(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT wl.*,
                    (SELECT COUNT(*) FROM items WHERE list_id = wl.id) AS item_count
             FROM wish_lists wl
             WHERE wl.user_id = :user_id
             ORDER BY wl.updated_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare('SELECT * FROM wish_lists WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function create(PDO $db, int $userId, string $title, string $description = '', string $visibility = 'all_families'): int
    {
        $stmt = $db->prepare(
            'INSERT INTO wish_lists (user_id, title, description, visibility, created_at, updated_at)
             VALUES (:user_id, :title, :description, :visibility, :created_at, :updated_at)'
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'user_id'     => $userId,
            'title'       => $title,
            'description' => $description,
            'visibility'  => $visibility,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(PDO $db, int $id, string $title, string $description): void
    {
        $stmt = $db->prepare(
            'UPDATE wish_lists SET title = :title, description = :description, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'title'       => $title,
            'description' => $description,
            'updated_at'  => date('Y-m-d H:i:s'),
            'id'          => $id,
        ]);
    }

    public static function delete(PDO $db, int $id): void
    {
        // Delete related data first
        $db->prepare('DELETE FROM item_links WHERE item_id IN (SELECT id FROM items WHERE list_id = :list_id)')->execute(['list_id' => $id]);
        $db->prepare('DELETE FROM purchases WHERE item_id IN (SELECT id FROM items WHERE list_id = :list_id)')->execute(['list_id' => $id]);
        $db->prepare('DELETE FROM items WHERE list_id = :list_id')->execute(['list_id' => $id]);
        $db->prepare('DELETE FROM list_family_shares WHERE list_id = :list_id')->execute(['list_id' => $id]);
        $db->prepare('DELETE FROM wish_lists WHERE id = :id')->execute(['id' => $id]);
    }

    public static function updateSharing(PDO $db, int $id, string $visibility, array $familyIds = []): void
    {
        $db->prepare('UPDATE wish_lists SET visibility = :visibility, updated_at = :updated_at WHERE id = :id')
            ->execute([
                'visibility' => $visibility,
                'updated_at' => date('Y-m-d H:i:s'),
                'id'         => $id,
            ]);

        // Replace family shares
        $db->prepare('DELETE FROM list_family_shares WHERE list_id = :list_id')->execute(['list_id' => $id]);

        if (!empty($familyIds)) {
            $stmt = $db->prepare('INSERT INTO list_family_shares (list_id, family_id) VALUES (:list_id, :family_id)');
            foreach ($familyIds as $familyId) {
                $stmt->execute(['list_id' => $id, 'family_id' => $familyId]);
            }
        }
    }

    public static function getByFamilyId(PDO $db, int $familyId, int $requesterId): array
    {
        // Get lists visible to a family, excluding the requester's own lists.
        // Visibility rules:
        //   - 'all_families': visible to all families the owner belongs to
        //   - 'selected_families': visible only if there's a list_family_shares entry for this family
        $stmt = $db->prepare(
            "SELECT wl.*, u.display_name AS owner_name,
                    (SELECT COUNT(*) FROM items WHERE list_id = wl.id) AS item_count
             FROM wish_lists wl
             JOIN users u ON u.id = wl.user_id
             WHERE wl.user_id != :requester_id
               AND wl.user_id IN (SELECT user_id FROM family_members WHERE family_id = :family_id)
               AND (
                   wl.visibility = 'all_families'
                   OR (wl.visibility = 'selected_families' AND wl.id IN (
                       SELECT list_id FROM list_family_shares WHERE family_id = :family_id2
                   ))
               )
             ORDER BY wl.updated_at DESC"
        );
        $stmt->execute([
            'requester_id' => $requesterId,
            'family_id'    => $familyId,
            'family_id2'   => $familyId,
        ]);
        return $stmt->fetchAll();
    }
}
