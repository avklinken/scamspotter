<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class BusinessAuthService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function login(string $email, string $password): ?array
    {
        $statement = $this->db->prepare("SELECT bu.id, bu.email, bu.name, bu.password_hash, o.id AS organization_id,
                o.name AS organization_name, om.role
            FROM business_users bu
            INNER JOIN organization_memberships om ON om.user_id = bu.id AND om.status = 'active'
            INNER JOIN organizations o ON o.id = om.organization_id AND o.status IN ('trial', 'active')
            WHERE bu.email = :email AND bu.status = 'active'
            ORDER BY CASE om.role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, o.id
            LIMIT 1");
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        if (!is_array($user) || !is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        $this->db->prepare('UPDATE business_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        unset($user['password_hash']);
        return $user;
    }

    /** @return array<string, mixed>|null */
    public function authenticateApiKey(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $statement = $this->db->prepare("SELECT k.id AS api_key_id, k.organization_id, o.name AS organization_name,
                o.status AS organization_status
            FROM business_api_keys k INNER JOIN organizations o ON o.id = k.organization_id
            WHERE k.key_hash = :key_hash AND k.revoked_at IS NULL
              AND (k.expires_at IS NULL OR k.expires_at > NOW())
              AND o.status IN ('trial', 'active') LIMIT 1");
        $statement->execute(['key_hash' => $hash]);
        $key = $statement->fetch();
        if (!is_array($key)) {
            return null;
        }
        $this->db->prepare('UPDATE business_api_keys SET last_used_at = NOW() WHERE id = :id')->execute(['id' => $key['api_key_id']]);
        return $key;
    }

    /** @return array<string, mixed>|null */
    public function findMembership(int $userId, int $organizationId): ?array
    {
        $statement = $this->db->prepare("SELECT om.organization_id, om.user_id, om.role, o.name AS organization_name,
                o.status AS organization_status
            FROM organization_memberships om INNER JOIN organizations o ON o.id = om.organization_id
            INNER JOIN business_users bu ON bu.id = om.user_id AND bu.status = 'active'
            WHERE om.user_id = :user_id AND om.organization_id = :organization_id
              AND om.status = 'active' AND o.status IN ('trial', 'active') LIMIT 1");
        $statement->execute(['user_id' => $userId, 'organization_id' => $organizationId]);
        $membership = $statement->fetch();
        return is_array($membership) ? $membership : null;
    }
}
