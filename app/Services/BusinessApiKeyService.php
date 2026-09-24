<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class BusinessApiKeyService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $organizationId): array
    {
        $statement = $this->db->prepare('SELECT id, name, key_prefix, last_used_at, expires_at, revoked_at, created_at
            FROM business_api_keys WHERE organization_id = :organization_id ORDER BY id DESC');
        $statement->execute(['organization_id' => $organizationId]);
        return $statement->fetchAll();
    }

    /** @return array{id: int, token: string} */
    public function create(int $organizationId, string $name): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new \InvalidArgumentException('Geef een duidelijke API-keynaam van maximaal 120 tekens op.');
        }
        $token = 'ss_live_' . bin2hex(random_bytes(24));
        $statement = $this->db->prepare('INSERT INTO business_api_keys (organization_id, name, key_prefix, key_hash)
            VALUES (:organization_id, :name, :key_prefix, :key_hash)');
        $statement->execute([
            'organization_id' => $organizationId,
            'name' => $name,
            'key_prefix' => substr($token, 0, 16),
            'key_hash' => hash('sha256', $token),
        ]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
    }

    public function revoke(int $organizationId, int $keyId): bool
    {
        $statement = $this->db->prepare('UPDATE business_api_keys SET revoked_at = NOW()
            WHERE id = :id AND organization_id = :organization_id AND revoked_at IS NULL');
        $statement->execute(['id' => $keyId, 'organization_id' => $organizationId]);
        return $statement->rowCount() > 0;
    }
}
