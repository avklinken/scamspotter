<?php
declare(strict_types=1);

use App\Database;
use App\Services\BusinessProvisioningService;

require dirname(__DIR__) . '/app/bootstrap.php';

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
$name = trim((string) ($argv[2] ?? ''));
$organizationName = trim((string) ($argv[3] ?? ''));
$password = (string) ($argv[4] ?? '');
$slug = trim((string) ($argv[5] ?? ''));
if ($email === '' || $name === '' || $organizationName === '' || $password === '') {
    fwrite(STDERR, "Gebruik: php bin/create-business-org.php email naam organisatie wachtwoord [slug]\n");
    exit(1);
}

$db = Database::connect();
try {
    (new BusinessProvisioningService($db))->provision($email, $name, $organizationName, $password, $slug !== '' ? $slug : null, true);
    echo "Business-organisatie en owner aangemaakt of bijgewerkt.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
