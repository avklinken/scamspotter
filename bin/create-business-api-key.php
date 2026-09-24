<?php
declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/app/bootstrap.php';

$slug = trim((string) ($argv[1] ?? ''));
$name = trim((string) ($argv[2] ?? 'API client'));
if ($slug === '') {
    fwrite(STDERR, "Gebruik: php bin/create-business-api-key.php organisatie-slug [naam]\n");
    exit(1);
}
$db = Database::connect();
$organization = $db->prepare("SELECT id FROM organizations WHERE slug = :slug AND status IN ('trial', 'active') LIMIT 1");
$organization->execute(['slug' => $slug]);
$organizationId = (int) $organization->fetchColumn();
if ($organizationId < 1) {
    fwrite(STDERR, "Organisatie niet gevonden.\n");
    exit(1);
}
$token = 'ss_live_' . bin2hex(random_bytes(24));
$statement = $db->prepare('INSERT INTO business_api_keys (organization_id, name, key_prefix, key_hash) VALUES (:organization_id, :name, :key_prefix, :key_hash)');
$statement->execute([
    'organization_id' => $organizationId,
    'name' => $name,
    'key_prefix' => substr($token, 0, 16),
    'key_hash' => hash('sha256', $token),
]);
echo "Bewaar deze key direct; hij wordt daarna niet opnieuw getoond:\n{$token}\n";
