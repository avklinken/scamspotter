<?php
declare(strict_types=1);

use App\Services\ReviewPublicationService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/app/bootstrap.php';
$publishedAlertIds = (new ReviewPublicationService($config['db']))->publishApprovedBatch();
echo 'Goedgekeurde reviews verwerkt: ' . count($publishedAlertIds) . "\n";
echo 'Alerts gepubliceerd of gekoppeld: ' . count($publishedAlertIds) . "\n";
