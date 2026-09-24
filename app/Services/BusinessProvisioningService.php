<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class BusinessProvisioningService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{account_id: int, organization_id: int, user_id: int} */
    public function provision(string $email, string $name, string $organizationName, string $password, ?string $slug = null, bool $updateExisting = false): array
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        $organizationName = trim($organizationName);
        $slug = trim($slug ?? '', '-');
        $slug = $slug !== '' ? $slug : trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($organizationName)), '-');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || $organizationName === '' || strlen($password) < 12 || $slug === '') {
            throw new \InvalidArgumentException('Vul geldige gegevens in. Het wachtwoord moet minimaal 12 tekens bevatten.');
        }
        if (!$updateExisting && ($this->exists('commercial_accounts', 'slug', $slug) || $this->exists('organizations', 'slug', $slug) || $this->exists('business_users', 'email', $email))) {
            throw new \InvalidArgumentException('De organisatie-slug of het e-mailadres bestaat al.');
        }

        $this->db->beginTransaction();
        try {
            $account = $this->db->prepare("INSERT INTO commercial_accounts (name, slug, billing_email, status)
                VALUES (:name, :slug, :billing_email, 'trial')
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name), billing_email = VALUES(billing_email), status = 'trial'");
            $account->execute(['name' => $organizationName, 'slug' => $slug, 'billing_email' => $email]);
            $accountId = (int) $this->db->lastInsertId();

            $organization = $this->db->prepare("INSERT INTO organizations (account_id, name, slug, status)
                VALUES (:account_id, :name, :slug, 'trial')
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), account_id = VALUES(account_id), name = VALUES(name), status = 'trial'");
            $organization->execute(['account_id' => $accountId, 'name' => $organizationName, 'slug' => $slug]);
            $organizationId = (int) $this->db->lastInsertId();

            $user = $this->db->prepare("INSERT INTO business_users (email, name, password_hash, identity_provider, status)
                VALUES (:email, :name, :password_hash, 'local', 'active')
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name), password_hash = VALUES(password_hash), status = 'active'");
            $user->execute(['email' => $email, 'name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $this->db->lastInsertId();

            $membership = $this->db->prepare("INSERT INTO organization_memberships (organization_id, user_id, role, status)
                VALUES (:organization_id, :user_id, 'owner', 'active')
                ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'");
            $membership->execute(['organization_id' => $organizationId, 'user_id' => $userId]);
            $subscriptionExists = $this->db->prepare("SELECT id FROM account_subscriptions WHERE account_id = :account_id AND status IN ('trialing', 'active') ORDER BY id DESC LIMIT 1");
            $subscriptionExists->execute(['account_id' => $accountId]);
            if ($subscriptionExists->fetchColumn() === false) {
                $subscription = $this->db->prepare("INSERT INTO account_subscriptions (account_id, plan_code, status) VALUES (:account_id, 'business_trial', 'trialing')");
                $subscription->execute(['account_id' => $accountId]);
            }
            $this->db->commit();
            return ['account_id' => $accountId, 'organization_id' => $organizationId, 'user_id' => $userId];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function exists(string $table, string $column, string $value): bool
    {
        $statement = $this->db->prepare("SELECT 1 FROM {$table} WHERE {$column} = :value LIMIT 1");
        $statement->execute(['value' => $value]);
        return $statement->fetchColumn() !== false;
    }
}
