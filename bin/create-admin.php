<?php
declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/app/bootstrap.php';
$email = trim((string) ($argv[1] ?? env('ADMIN_EMAIL', '')));
$name = trim((string) ($argv[2] ?? 'ScamSpotter beheer'));
$password = (string) ($argv[3] ?? env('ADMIN_PASSWORD', ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Gebruik: php bin/create-admin.php email naam wachtwoord (wachtwoord minimaal 12 tekens)\n");
    exit(1);
}
$db = Database::connect();
$statement = $db->prepare("INSERT INTO admin_users (email, name, password_hash, role, is_active)
    VALUES (:email, :name, :password_hash, 'admin', 1)
    ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), is_active = 1");
$statement->execute(['email' => mb_strtolower($email), 'name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
echo "Admin-gebruiker aangemaakt of bijgewerkt.\n";
