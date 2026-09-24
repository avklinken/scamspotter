<?php
declare(strict_types=1);

use App\Database;
use App\Support\Env;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

require_once BASE_PATH . '/app/Support/helpers.php';
Env::load(BASE_PATH);
date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Europe/Amsterdam'));

if (!Env::bool('APP_DEBUG', false)) {
    ini_set('display_errors', '0');
    set_exception_handler(static function (Throwable $exception): void {
        $directory = BASE_PATH . '/storage/logs';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory . '/app.log', '[' . date('c') . '] ' . $exception->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        http_response_code(500);
        echo 'Er ging iets mis. Probeer het later opnieuw.';
    });
}

return [
    'db' => Database::connect(),
];
