<?php
declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/app/bootstrap.php';

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
$name = trim((string) ($argv[2] ?? ''));
$organizationName = trim((string) ($argv[3] ?? ''));
$password = (string) ($argv[4] ?? '');
$slug = trim((string) ($argv[5] ?? preg_replace('/[^a-z0-9]+/i', '-', strtolower($organizationName))), '-');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || $organizationName === '' || strlen($password) < 12 || $slug === '') {
    fwrite(STDERR, "Gebruik: php bin/create-business-org.php email naam organisatie wachtwoord [slug]\nWachtwoord minimaal 12 tekens.\n");
    exit(1);
}

$db = Database::connect();
$db->beginTransaction();
try {
    $account = $db->prepare("INSERT INTO commercial_accounts (name, slug, billing_email, status)
        VALUES (:name, :slug, :billing_email, 'trial')
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name), billing_email = VALUES(billing_email), status = 'trial'");
    $account->execute(['name' => $organizationName, 'slug' => $slug, 'billing_email' => $email]);
    $accountId = (int) $db->lastInsertId();

    $organization = $db->prepare("INSERT INTO organizations (account_id, name, slug, status)
        VALUES (:account_id, :name, :slug, 'trial')
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), account_id = VALUES(account_id), name = VALUES(name), status = 'trial'");
    $organization->execute(['account_id' => $accountId, 'name' => $organizationName, 'slug' => $slug]);
    $organizationId = (int) $db->lastInsertId();

    $user = $db->prepare("INSERT INTO business_users (email, name, password_hash, identity_provider, status)
        VALUES (:email, :name, :password_hash, 'local', 'active')
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name), password_hash = VALUES(password_hash), status = 'active'");
    $user->execute(['email' => $email, 'name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $db->lastInsertId();

    $membership = $db->prepare("INSERT INTO organization_memberships (organization_id, user_id, role, status)
        VALUES (:organization_id, :user_id, 'owner', 'active')
        ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'");
    $membership->execute(['organization_id' => $organizationId, 'user_id' => $userId]);
    $subscriptionExists = $db->prepare("SELECT id FROM account_subscriptions WHERE account_id = :account_id AND status IN ('trialing', 'active') ORDER BY id DESC LIMIT 1");
    $subscriptionExists->execute(['account_id' => $accountId]);
    if ($subscriptionExists->fetchColumn() === false) {
        $subscription = $db->prepare("INSERT INTO account_subscriptions (account_id, plan_code, status) VALUES (:account_id, 'business_trial', 'trialing')");
        $subscription->execute(['account_id' => $accountId]);
    }
    $db->commit();
    echo "Business-organisatie en owner aangemaakt of bijgewerkt.\n";
} catch (Throwable $exception) {
    $db->rollBack();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
