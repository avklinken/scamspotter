<?php
declare(strict_types=1);

use App\Database;
use App\Services\AnalysisJobService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
$jobs = new AnalysisJobService(Database::connect());
foreach ($jobs->counts() as $status => $count) {
    echo $status . ': ' . $count . PHP_EOL;
}
