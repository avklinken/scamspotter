<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class RetentionService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, int> */
    public function purge(): array
    {
        $deleted = [];
        $deleted['public_checks'] = $this->delete('DELETE FROM checks WHERE expires_at < NOW()');
        $deleted['business_checks'] = $this->delete('DELETE FROM organization_checks WHERE expires_at < NOW()');
        $deleted['business_runs'] = $this->delete('DELETE FROM organization_analysis_runs WHERE retention_until < NOW()');
        $deleted['business_feedback'] = $this->delete('DELETE FROM organization_check_feedback WHERE created_at < DATE_SUB(NOW(), INTERVAL 395 DAY)');
        $deleted['business_events'] = $this->delete('DELETE FROM organization_events WHERE occurred_at < DATE_SUB(NOW(), INTERVAL 395 DAY)');
        $deleted['usage_events'] = $this->delete('DELETE FROM usage_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 395 DAY)');
        $deleted['rate_limits'] = $this->delete('DELETE FROM business_rate_limits WHERE last_seen_at < UTC_TIMESTAMP() - INTERVAL 2 DAY');
        return $deleted;
    }

    private function delete(string $sql): int
    {
        return $this->db->exec($sql) ?: 0;
    }
}
