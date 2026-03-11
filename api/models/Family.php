<?php

class Family
{
    public static function getByUserId(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT f.*,
                    fm.role AS my_role,
                    (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) AS member_count
             FROM families f
             JOIN family_members fm ON fm.family_id = f.id AND fm.user_id = :user_id
             ORDER BY f.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare('SELECT * FROM families WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $family = $stmt->fetch();

        if ($family) {
            $family['members'] = self::getMembers($db, $id);
        }

        return $family;
    }

    public static function create(PDO $db, string $name, int $createdBy): int
    {
        $inviteCode = self::generateInviteCode();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare(
            'INSERT INTO families (name, invite_code, created_by, created_at, updated_at)
             VALUES (:name, :invite_code, :created_by, :created_at, :updated_at)'
        );
        $stmt->execute([
            'name'        => $name,
            'invite_code' => $inviteCode,
            'created_by'  => $createdBy,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $familyId = (int) $db->lastInsertId();

        // Add creator as admin
        self::addMember($db, $familyId, $createdBy, 'admin');

        return $familyId;
    }

    public static function findByInviteCode(PDO $db, string $code): array|false
    {
        $stmt = $db->prepare('SELECT * FROM families WHERE invite_code = :code');
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    public static function addMember(PDO $db, int $familyId, int $userId, string $role = 'member'): void
    {
        $stmt = $db->prepare(
            'INSERT INTO family_members (family_id, user_id, role, joined_at)
             VALUES (:family_id, :user_id, :role, :joined_at)'
        );
        $stmt->execute([
            'family_id' => $familyId,
            'user_id'   => $userId,
            'role'      => $role,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function removeMember(PDO $db, int $familyId, int $userId): void
    {
        $stmt = $db->prepare('DELETE FROM family_members WHERE family_id = :family_id AND user_id = :user_id');
        $stmt->execute(['family_id' => $familyId, 'user_id' => $userId]);
    }

    public static function isMember(PDO $db, int $familyId, int $userId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM family_members WHERE family_id = :family_id AND user_id = :user_id');
        $stmt->execute(['family_id' => $familyId, 'user_id' => $userId]);
        return $stmt->fetch() !== false;
    }

    public static function getMemberRole(PDO $db, int $familyId, int $userId): string|false
    {
        $stmt = $db->prepare('SELECT role FROM family_members WHERE family_id = :family_id AND user_id = :user_id');
        $stmt->execute(['family_id' => $familyId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ? $row['role'] : false;
    }

    public static function getMembers(PDO $db, int $familyId): array
    {
        $stmt = $db->prepare(
            'SELECT u.id, u.email, u.display_name, fm.role, fm.joined_at
             FROM family_members fm
             JOIN users u ON u.id = fm.user_id
             WHERE fm.family_id = :family_id
             ORDER BY fm.joined_at ASC'
        );
        $stmt->execute(['family_id' => $familyId]);
        return $stmt->fetchAll();
    }

    private static function generateInviteCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
