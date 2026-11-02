<?php

/**
 * Password reset requests. Each one holds a 6-digit code and a link token, stored only as hashes.
 *
 * A row is written for every request, including addresses with no account, so the rate limit
 * behaves the same either way and can't be used to discover who has an account.
 */
class PasswordReset
{
    private const EXPIRES_SECONDS = 900;
    private const MAX_ATTEMPTS = 5;
    private const EMAIL_LIMIT = 3;
    private const EMAIL_WINDOW = 900;
    private const IP_LIMIT = 20;
    private const IP_WINDOW = 3600;

    public static function createTable(PDO $db, string $autoIncrement, string $timestampType): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id {$autoIncrement},
            email VARCHAR(255) NOT NULL,
            user_id INTEGER,
            code_hash VARCHAR(64) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            client_ip VARCHAR(45) NOT NULL,
            expires_at {$timestampType} NOT NULL,
            consumed_at {$timestampType},
            created_at {$timestampType} NOT NULL
        )");
    }

    /**
     * Records a reset request.
     * Returns null when rate limited. Otherwise returns id, code, token, and recipient,
     * where recipient is the account's address, or null if there is no account to email.
     */
    public static function start(PDO $db, string $email, string $clientIp, ?int $now = null): ?array
    {
        $now ??= time();
        $email = self::normalizeEmail($email);

        if (self::recentCount($db, 'email', $email, $now - self::EMAIL_WINDOW) >= self::EMAIL_LIMIT
            || self::recentCount($db, 'client_ip', $clientIp, $now - self::IP_WINDOW) >= self::IP_LIMIT) {
            return null;
        }

        $stmt = $db->prepare('SELECT id, email FROM users WHERE LOWER(email) = :email ORDER BY id LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(32));

        $stmt = $db->prepare(
            'INSERT INTO password_resets (email, user_id, code_hash, token_hash, client_ip, expires_at, created_at)
             VALUES (:email, :user_id, :code_hash, :token_hash, :client_ip, :expires_at, :created_at)'
        );
        $stmt->execute([
            'email'      => $email,
            'user_id'    => $user ? $user['id'] : null,
            'code_hash'  => hash('sha256', $code),
            'token_hash' => hash('sha256', $token),
            'client_ip'  => $clientIp,
            'expires_at' => self::time($now + self::EXPIRES_SECONDS),
            'created_at' => self::time($now),
        ]);

        return [
            'id'        => (int) $db->lastInsertId(),
            'code'      => $code,
            'token'     => $token,
            'recipient' => $user ? $user['email'] : null,
        ];
    }

    /** Removes a request whose email failed to send, so it doesn't count toward the rate limit. */
    public static function discard(PDO $db, int $id): void
    {
        $db->prepare('DELETE FROM password_resets WHERE id = :id')->execute(['id' => $id]);
    }

    /** Sets a new password if the code is right. Wrong guesses count against every open request for the email. */
    public static function redeemCode(PDO $db, string $email, string $code, string $newPassword, ?int $now = null): bool
    {
        $now ??= time();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT * FROM password_resets
                 WHERE email = :email AND user_id IS NOT NULL AND consumed_at IS NULL AND expires_at > :now'
            );
            $stmt->execute(['email' => self::normalizeEmail($email), 'now' => self::time($now)]);
            $requests = $stmt->fetchAll();

            $hash = hash('sha256', trim($code));
            foreach ($requests as $request) {
                if (hash_equals($request['code_hash'], $hash)) {
                    $done = self::complete($db, $request, $newPassword, $now);
                    $db->commit();
                    return $done;
                }
            }

            foreach ($requests as $request) {
                $db->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = :id')
                    ->execute(['id' => $request['id']]);
                $db->prepare(
                    'UPDATE password_resets SET consumed_at = :now
                     WHERE id = :id AND attempts >= ' . self::MAX_ATTEMPTS
                )->execute(['now' => self::time($now), 'id' => $request['id']]);
            }
            $db->commit();
            return false;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Sets a new password if the emailed link's token is valid. */
    public static function redeemToken(PDO $db, string $token, string $newPassword, ?int $now = null): bool
    {
        $now ??= time();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT * FROM password_resets
                 WHERE token_hash = :hash AND user_id IS NOT NULL AND consumed_at IS NULL AND expires_at > :now'
            );
            $stmt->execute(['hash' => hash('sha256', trim($token)), 'now' => self::time($now)]);
            $request = $stmt->fetch();

            $done = $request ? self::complete($db, $request, $newPassword, $now) : false;
            $db->commit();
            return $done;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Changes the password, logs out older sessions, and closes every open request for the account. */
    private static function complete(PDO $db, array $request, string $newPassword, int $now): bool
    {
        $consume = $db->prepare('UPDATE password_resets SET consumed_at = :now WHERE id = :id AND consumed_at IS NULL');
        $consume->execute(['now' => self::time($now), 'id' => $request['id']]);
        if ($consume->rowCount() !== 1) {
            return false;
        }

        $userId = (int) $request['user_id'];
        User::updatePassword($db, $userId, $newPassword);
        $db->prepare('UPDATE users SET token_version = token_version + 1 WHERE id = :id')
            ->execute(['id' => $userId]);
        $db->prepare('UPDATE password_resets SET consumed_at = :now WHERE user_id = :user_id AND consumed_at IS NULL')
            ->execute(['now' => self::time($now), 'user_id' => $userId]);

        return true;
    }

    private static function recentCount(PDO $db, string $column, string $value, int $since): int
    {
        $column = $column === 'email' ? 'email' : 'client_ip';
        $stmt = $db->prepare("SELECT COUNT(*) FROM password_resets WHERE {$column} = :value AND created_at >= :since");
        $stmt->execute(['value' => $value, 'since' => self::time($since)]);
        return (int) $stmt->fetchColumn();
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
