<?php
declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/app/bootstrap.php';

$organizationId = (int) ($argv[1] ?? 0);
$tenantId = strtolower(trim((string) ($argv[2] ?? '')));
$displayName = trim((string) ($argv[3] ?? ''));
if ($organizationId < 1 || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenantId) !== 1) {
    fwrite(STDERR, "Gebruik: php bin/link-microsoft-tenant.php organisatie_id tenant_id [naam]\n");
    exit(1);
}

$db = Database::connect();
$organization = $db->prepare("SELECT id FROM organizations WHERE id = :id AND status IN ('trial', 'active') LIMIT 1");
$organization->execute(['id' => $organizationId]);
if ($organization->fetchColumn() === false) {
    fwrite(STDERR, "Actieve organisatie niet gevonden.\n");
    exit(1);
}

$statement = $db->prepare("INSERT INTO microsoft_tenants
        (organization_id, tenant_id, display_name, consent_status, consented_at, revoked_at)
    VALUES (:organization_id, :tenant_id, :display_name, 'active', NOW(), NULL)
    ON DUPLICATE KEY UPDATE organization_id = VALUES(organization_id), display_name = VALUES(display_name),
        consent_status = 'active', consented_at = NOW(), revoked_at = NULL");
$statement->execute([
    'organization_id' => $organizationId,
    'tenant_id' => $tenantId,
    'display_name' => $displayName !== '' ? $displayName : null,
]);
echo "Microsoft tenant gekoppeld aan organisatie {$organizationId}.\n";
