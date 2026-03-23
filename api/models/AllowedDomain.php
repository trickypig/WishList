<?php

class AllowedDomain
{
    /**
     * Get all global default domains.
     */
    public static function getDefaults(PDO $db): array
    {
        $stmt = $db->query('SELECT * FROM default_allowed_domains ORDER BY domain ASC');
        return $stmt->fetchAll();
    }

    /**
     * Add a global default domain.
     */
    public static function addDefault(PDO $db, string $domain, string $displayName, string $iconUrl, int $addedBy): int
    {
        $stmt = $db->prepare(
            'INSERT INTO default_allowed_domains (domain, display_name, icon_url, added_by, created_at)
             VALUES (:domain, :display_name, :icon_url, :added_by, :created_at)'
        );
        $stmt->execute([
            'domain'       => strtolower(trim($domain)),
            'display_name' => $displayName,
            'icon_url'     => $iconUrl,
            'added_by'     => $addedBy,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Remove a global default domain by ID.
     */
    public static function removeDefault(PDO $db, int $id): bool
    {
        $stmt = $db->prepare('DELETE FROM default_allowed_domains WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get a global default domain by ID.
     */
    public static function getDefaultById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare('SELECT * FROM default_allowed_domains WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get effective domains for a family (defaults merged with overrides).
     */
    public static function getEffectiveForFamily(PDO $db, int $familyId): array
    {
        // Get all defaults
        $defaults = self::getDefaults($db);

        // Get family overrides
        $stmt = $db->prepare('SELECT * FROM family_domain_overrides WHERE family_id = :family_id');
        $stmt->execute(['family_id' => $familyId]);
        $overrides = $stmt->fetchAll();

        // Build override lookup by domain
        $overrideMap = [];
        foreach ($overrides as $override) {
            $overrideMap[$override['domain']] = $override;
        }

        // Start with defaults, applying removals
        $effective = [];
        foreach ($defaults as $default) {
            if (isset($overrideMap[$default['domain']]) && $overrideMap[$default['domain']]['action'] === 'remove') {
                continue; // removed by family override
            }
            $effective[] = [
                'domain'       => $default['domain'],
                'display_name' => $default['display_name'],
                'icon_url'     => $default['icon_url'],
                'source'       => 'default',
            ];
        }

        // Add family-specific domains
        foreach ($overrides as $override) {
            if ($override['action'] === 'add') {
                $effective[] = [
                    'domain'       => $override['domain'],
                    'display_name' => $override['display_name'],
                    'icon_url'     => $override['icon_url'],
                    'source'       => 'family',
                ];
            }
        }

        // Sort by domain
        usort($effective, fn($a, $b) => strcmp($a['domain'], $b['domain']));

        return $effective;
    }

    /**
     * Add a family domain override (add a new domain for this family).
     */
    public static function addFamilyDomain(PDO $db, int $familyId, string $domain, string $displayName, string $iconUrl, int $addedBy): int
    {
        $stmt = $db->prepare(
            'INSERT INTO family_domain_overrides (family_id, domain, display_name, icon_url, action, added_by, created_at)
             VALUES (:family_id, :domain, :display_name, :icon_url, :action, :added_by, :created_at)'
        );
        $stmt->execute([
            'family_id'    => $familyId,
            'domain'       => strtolower(trim($domain)),
            'display_name' => $displayName,
            'icon_url'     => $iconUrl,
            'action'       => 'add',
            'added_by'     => $addedBy,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Remove a domain from a family's effective list.
     * If it's a default domain, creates a 'remove' override.
     * If it's a family-added domain, deletes the override.
     */
    public static function removeFamilyDomain(PDO $db, int $familyId, string $domain): bool
    {
        $domain = strtolower(trim($domain));

        // Check if it's a family override
        $stmt = $db->prepare(
            'SELECT * FROM family_domain_overrides WHERE family_id = :family_id AND domain = :domain'
        );
        $stmt->execute(['family_id' => $familyId, 'domain' => $domain]);
        $override = $stmt->fetch();

        if ($override && $override['action'] === 'add') {
            // It's a family-added domain — just delete the override
            $stmt = $db->prepare('DELETE FROM family_domain_overrides WHERE id = :id');
            $stmt->execute(['id' => $override['id']]);
            return true;
        }

        // Check if it's a default domain
        $stmt = $db->prepare('SELECT 1 FROM default_allowed_domains WHERE domain = :domain');
        $stmt->execute(['domain' => $domain]);
        if (!$stmt->fetch()) {
            return false; // Domain not found in defaults or overrides
        }

        // It's a default domain — create a 'remove' override
        if ($override) {
            // Override already exists (shouldn't happen but update it)
            $stmt = $db->prepare(
                'UPDATE family_domain_overrides SET action = :action WHERE id = :id'
            );
            $stmt->execute(['action' => 'remove', 'id' => $override['id']]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO family_domain_overrides (family_id, domain, display_name, icon_url, action, added_by, created_at)
                 VALUES (:family_id, :domain, :display_name, :icon_url, :action, :added_by, :created_at)'
            );
            $stmt->execute([
                'family_id'    => $familyId,
                'domain'       => $domain,
                'display_name' => '',
                'icon_url'     => '',
                'action'       => 'remove',
                'added_by'     => 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }
}
