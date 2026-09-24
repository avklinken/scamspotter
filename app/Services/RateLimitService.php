<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class RateLimitService
{
    public function __construct(private PDO $db)
    {
    }

    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        $limit = max(1, $limit);
        $windowSeconds = max(60, $windowSeconds);
        $bucket = (int) floor(time() / $windowSeconds) * $windowSeconds;
        $bucketStart = gmdate('Y-m-d H:i:s', $bucket);
        $statement = $this->db->prepare("INSERT INTO business_rate_limits
            (bucket_key, bucket_start, request_count, last_seen_at)
            VALUES (:bucket_key, :bucket_start, 1, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE request_count = request_count + 1, last_seen_at = UTC_TIMESTAMP()");
        $statement->execute(['bucket_key' => $key . ':' . $windowSeconds, 'bucket_start' => $bucketStart]);

        $current = $this->db->prepare('SELECT request_count FROM business_rate_limits WHERE bucket_key = :bucket_key AND bucket_start = :bucket_start LIMIT 1');
        $current->execute(['bucket_key' => $key . ':' . $windowSeconds, 'bucket_start' => $bucketStart]);
        return (int) $current->fetchColumn() <= $limit;
    }

    public function cleanup(): void
    {
        $this->db->exec('DELETE FROM business_rate_limits WHERE last_seen_at < UTC_TIMESTAMP() - INTERVAL 2 DAY');
    }
}
