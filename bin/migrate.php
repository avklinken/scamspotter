<?php
declare(strict_types=1);

use App\Database;

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$db = Database::connect();
$db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(191) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    $statement = $db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :migration');
    $statement->execute(['migration' => $name]);
    if ((int) $statement->fetchColumn() > 0) {
        continue;
    }
    try {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Kan migratie niet lezen: ' . $name);
        }
        $db->exec($sql);
        $insert = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $insert->execute(['migration' => $name]);
        echo "Applied {$name}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "Migration {$name} failed: {$exception->getMessage()}\n");
        exit(1);
    }
}
echo "Migrations complete.\n";
