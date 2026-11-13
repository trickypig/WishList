<?php

/**
 * Emailed family invite links. Each link holds a random token, stored only as a hash.
 *
 * A link is a key: anyone holding a valid one may join the family under any account.
 * Links stay valid until they expire or an admin revokes them; using one does not use it up.
 */
class FamilyInvite
{
    public const EXPIRES_SECONDS = 1209600; // 14 days
    public const FAMILY_LIMIT = 20;
    public const FAMILY_WINDOW = 3600;

    public static function createTable(PDO $db, string $autoIncrement, string $timestampType): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS family_invites (
            id {$autoIncrement},
            family_id INTEGER NOT NULL,
            email VARCHAR(255),
            invited_by INTEGER NOT NULL,
            token_hash VARCHAR(64),
            expires_at {$timestampType} NULL,
            revoked_at {$timestampType} NULL,
            accepted_at {$timestampType} NULL,
            accepted_by INTEGER NULL,
            created_at {$timestampType} NOT NULL,
            FOREIGN KEY (family_id) REFERENCES families(id),
            FOREIGN KEY (invited_by) REFERENCES users(id)
        )");
    }

    /**
     * Records a new invite link.
     * Returns null when the family has sent too many invites in the last hour,
     * otherwise the new invite's id and its raw token (which is never stored).
     */
    public static function create(PDO $db, int $familyId, string $email, int $invitedBy, ?int $now = null): ?array
    {
        $now ??= time();

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM family_invites
             WHERE family_id = :family_id AND token_hash IS NOT NULL AND created_at >= :since'
        );
        $stmt->execute(['family_id' => $familyId, 'since' => self::time($now - self::FAMILY_WINDOW)]);
        if ((int) $stmt->fetchColumn() >= self::FAMILY_LIMIT) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare(
            'INSERT INTO family_invites (family_id, email, invited_by, token_hash, expires_at, created_at)
             VALUES (:family_id, :email, :invited_by, :token_hash, :expires_at, :created_at)'
        );
        $stmt->execute([
            'family_id'  => $familyId,
            'email'      => self::normalizeEmail($email),
            'invited_by' => $invitedBy,
            'token_hash' => hash('sha256', $token),
            'expires_at' => self::time($now + self::EXPIRES_SECONDS),
            'created_at' => self::time($now),
        ]);

        return ['id' => (int) $db->lastInsertId(), 'token' => $token];
    }

    /** Removes an invite whose email failed to send, so it doesn't count toward the rate limit. */
    public static function discard(PDO $db, int $id): void
    {
        $db->prepare('DELETE FROM family_invites WHERE id = :id')->execute(['id' => $id]);
    }

    /** The live invite for a token, with family_name and inviter_name, or false. */
    public static function findValid(PDO $db, string $token, ?int $now = null): array|false
    {
        if ($token === '') {
            return false;
        }
        $now ??= time();

        $stmt = $db->prepare(
            'SELECT fi.*, f.name AS family_name, u.display_name AS inviter_name
             FROM family_invites fi
             JOIN families f ON f.id = fi.family_id
             JOIN users u ON u.id = fi.invited_by
             WHERE fi.token_hash = :token_hash AND fi.revoked_at IS NULL AND fi.expires_at > :now'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token), 'now' => self::time($now)]);
        return $stmt->fetch();
    }

    /**
     * Adds the user to the invite's family. Returns true if they were already a member.
     * The first successful use is recorded for the admin's list.
     */
    public static function accept(PDO $db, array $invite, int $userId, ?int $now = null): bool
    {
        $now ??= time();
        $familyId = (int) $invite['family_id'];

        if (Family::isMember($db, $familyId, $userId)) {
            return true;
        }

        Family::addMember($db, $familyId, $userId, 'member');
        $db->prepare(
            'UPDATE family_invites SET accepted_at = :now, accepted_by = :user_id
             WHERE id = :id AND accepted_at IS NULL'
        )->execute(['now' => self::time($now), 'user_id' => $userId, 'id' => $invite['id']]);

        return false;
    }

    /** Live invites for a family, newest first, one per email address (the most recent). */
    public static function listLive(PDO $db, int $familyId, ?int $now = null): array
    {
        $now ??= time();

        $stmt = $db->prepare(
            'SELECT fi.id, fi.email, inviter.display_name AS invited_by_name, fi.created_at, fi.expires_at,
                    fi.accepted_at, used.display_name AS accepted_by_name
             FROM family_invites fi
             JOIN users inviter ON inviter.id = fi.invited_by
             LEFT JOIN users used ON used.id = fi.accepted_by
             WHERE fi.family_id = :family_id AND fi.token_hash IS NOT NULL
               AND fi.revoked_at IS NULL AND fi.expires_at > :now
             ORDER BY fi.created_at DESC, fi.id DESC'
        );
        $stmt->execute(['family_id' => $familyId, 'now' => self::time($now)]);

        // Keep the newest row per email. If an older live link to that email was used, show that use,
        // since the older link still works and the admin may want to revoke it.
        $byEmail = [];
        foreach ($stmt->fetchAll() as $row) {
            $email = $row['email'];
            if (!isset($byEmail[$email])) {
                $row['id'] = (int) $row['id'];
                $byEmail[$email] = $row;
            } elseif ($byEmail[$email]['accepted_at'] === null && $row['accepted_at'] !== null) {
                $byEmail[$email]['accepted_at'] = $row['accepted_at'];
                $byEmail[$email]['accepted_by_name'] = $row['accepted_by_name'];
            }
        }
        return array_values($byEmail);
    }

    public static function findInFamily(PDO $db, int $familyId, int $inviteId): array|false
    {
        $stmt = $db->prepare(
            'SELECT * FROM family_invites WHERE id = :id AND family_id = :family_id AND token_hash IS NOT NULL'
        );
        $stmt->execute(['id' => $inviteId, 'family_id' => $familyId]);
        return $stmt->fetch();
    }

    /** Revokes every live invite to an email address in a family. Returns how many were revoked. */
    public static function revokeForEmail(PDO $db, int $familyId, string $email, ?int $now = null): int
    {
        $now ??= time();

        $stmt = $db->prepare(
            'UPDATE family_invites SET revoked_at = :now
             WHERE family_id = :family_id AND email = :email AND token_hash IS NOT NULL
               AND revoked_at IS NULL AND expires_at > :now2'
        );
        $stmt->execute([
            'now'       => self::time($now),
            'now2'      => self::time($now),
            'family_id' => $familyId,
            'email'     => self::normalizeEmail($email),
        ]);
        return $stmt->rowCount();
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function time(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
