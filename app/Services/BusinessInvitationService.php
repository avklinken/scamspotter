<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class BusinessInvitationService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{id: int, token: string, expires_at: string} */
    public function create(int $organizationId, string $email, string $role = 'member'): array
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 191) {
            throw new \InvalidArgumentException('Vul een geldig e-mailadres in.');
        }
        if (!in_array($role, ['member', 'analyst'], true)) {
            throw new \InvalidArgumentException('Ongeldige organisatierol.');
        }
        $member = $this->db->prepare('SELECT bu.id FROM business_users bu
            INNER JOIN organization_memberships om ON om.user_id = bu.id AND om.organization_id = :organization_id AND om.status = \'active\'
            WHERE bu.email = :email LIMIT 1');
        $member->execute(['organization_id' => $organizationId, 'email' => $email]);
        if ($member->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Dit e-mailadres is al lid van de organisatie.');
        }
        $this->db->prepare('UPDATE organization_invitations SET accepted_at = NOW()
            WHERE organization_id = :organization_id AND email = :email AND accepted_at IS NULL')
            ->execute(['organization_id' => $organizationId, 'email' => $email]);
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');
        $statement = $this->db->prepare('INSERT INTO organization_invitations
            (organization_id, email, role, token_hash, expires_at)
            VALUES (:organization_id, :email, :role, :token_hash, :expires_at)');
        $statement->execute([
            'organization_id' => $organizationId,
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token, 'expires_at' => $expiresAt];
    }

    /** @return array<string, mixed>|null */
    public function find(string $token): ?array
    {
        $statement = $this->db->prepare('SELECT i.id, i.organization_id, i.email, i.role, i.expires_at,
                o.name AS organization_name
            FROM organization_invitations i INNER JOIN organizations o ON o.id = i.organization_id
            WHERE i.token_hash = :token_hash AND i.accepted_at IS NULL AND i.expires_at > NOW()
              AND o.status IN (\'trial\', \'active\') LIMIT 1');
        $statement->execute(['token_hash' => hash('sha256', trim($token))]);
        $invite = $statement->fetch();
        return is_array($invite) ? $invite : null;
    }

    /** @return array{organization_id: int, organization_name: string, email: string} */
    public function accept(string $token, string $name, string $password): array
    {
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new \InvalidArgumentException('Vul je naam in (2 tot 160 tekens).');
        }
        if (mb_strlen($password) < 12) {
            throw new \InvalidArgumentException('Kies een wachtwoord van minimaal 12 tekens.');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT i.id, i.organization_id, i.email, i.role, o.name AS organization_name
                FROM organization_invitations i INNER JOIN organizations o ON o.id = i.organization_id
                WHERE i.token_hash = :token_hash AND i.accepted_at IS NULL AND i.expires_at > NOW()
                  AND o.status IN (\'trial\', \'active\') LIMIT 1 FOR UPDATE');
            $statement->execute(['token_hash' => hash('sha256', trim($token))]);
            $invite = $statement->fetch();
            if (!is_array($invite)) {
                throw new \InvalidArgumentException('Deze uitnodiging is verlopen of al gebruikt.');
            }
            $userQuery = $this->db->prepare('SELECT id, password_hash FROM business_users WHERE email = :email LIMIT 1');
            $userQuery->execute(['email' => $invite['email']]);
            $user = $userQuery->fetch();
            if (!is_array($user)) {
                $insertUser = $this->db->prepare('INSERT INTO business_users (email, name, password_hash, status)
                    VALUES (:email, :name, :password_hash, \'active\')');
                $insertUser->execute([
                    'email' => $invite['email'],
                    'name' => $name,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $this->db->lastInsertId();
            } else {
                $userId = (int) $user['id'];
                if (!is_string($user['password_hash']) || $user['password_hash'] === '') {
                    $this->db->prepare('UPDATE business_users SET name = :name, password_hash = :password_hash, status = \'active\' WHERE id = :id')
                        ->execute(['name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
                }
            }
            $membership = $this->db->prepare('INSERT INTO organization_memberships (organization_id, user_id, role, status)
                VALUES (:organization_id, :user_id, :role, \'active\')
                ON DUPLICATE KEY UPDATE role = VALUES(role), status = \'active\'');
            $membership->execute([
                'organization_id' => (int) $invite['organization_id'],
                'user_id' => $userId,
                'role' => $invite['role'],
            ]);
            $this->db->prepare('UPDATE organization_invitations SET accepted_at = NOW() WHERE id = :id')
                ->execute(['id' => $invite['id']]);
            $this->db->commit();
            return [
                'organization_id' => (int) $invite['organization_id'],
                'organization_name' => (string) $invite['organization_name'],
                'email' => (string) $invite['email'],
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
