<?php
declare(strict_types=1);

use App\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$errors = [];
try {
    $db = Database::connect();
    $requiredTables = ['schema_migrations', 'scam_variants', 'checks', 'cron_runs', 'organizations', 'organization_checks', 'business_rate_limits'];
    foreach ($requiredTables as $table) {
        $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $statement->execute(['table' => $table]);
        if ((int) $statement->fetchColumn() !== 1) {
            $errors[] = 'Ontbrekende tabel: ' . $table;
        }
    }
    $migrations = (int) $db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    echo "database:ok migrations:{$migrations}\n";
} catch (Throwable $exception) {
    $errors[] = 'Database: ' . $exception->getMessage();
}

foreach (['storage/cache', 'storage/logs', 'storage/uploads'] as $directory) {
    $path = BASE_PATH . '/' . $directory;
    if (!is_dir($path) || !is_writable($path)) {
        $errors[] = 'Niet schrijfbaar: ' . $directory;
    }
}

echo 'openai:' . ((new App\Services\OpenAIService())->enabled() ? 'enabled' : 'disabled') . "\n";
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}
echo "healthcheck:ok\n";
