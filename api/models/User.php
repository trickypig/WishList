<?php

class User
{
    public static function findByEmail(PDO $db, string $email): array|false
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public static function findById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare('SELECT id, email, display_name, is_admin, created_at, updated_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function create(PDO $db, string $email, string $password, string $displayName): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            'INSERT INTO users (email, password_hash, display_name, created_at, updated_at)
             VALUES (:email, :password_hash, :display_name, :created_at, :updated_at)'
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'email'         => $email,
            'password_hash' => $hash,
            'display_name'  => $displayName,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function updateProfile(PDO $db, int $id, string $displayName): void
    {
        $stmt = $db->prepare(
            'UPDATE users SET display_name = :display_name, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'display_name' => $displayName,
            'updated_at'   => date('Y-m-d H:i:s'),
            'id'           => $id,
        ]);
    }
}
