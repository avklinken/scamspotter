<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AnalysisJobService
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $payload */
    public function enqueue(?int $organizationId, string $channel, string $jobType, array $payload, ?\DateTimeImmutable $availableAt = null): int
    {
        $channel = trim($channel);
        $jobType = trim($jobType);
        if ($channel === '' || mb_strlen($channel) > 40 || $jobType === '' || mb_strlen($jobType) > 60) {
            throw new \InvalidArgumentException('Ongeldig analyse-jobtype of kanaal.');
        }
        $statement = $this->db->prepare('INSERT INTO analysis_jobs
            (organization_id, channel, job_type, payload_json, status, available_at)
            VALUES (:organization_id, :channel, :job_type, :payload_json, \'queued\', :available_at)');
        $statement->execute([
            'organization_id' => $organizationId,
            'channel' => $channel,
            'job_type' => $jobType,
            'payload_json' => json_text($payload),
            'available_at' => ($availableAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function claim(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE analysis_jobs SET status = 'queued', locked_at = NULL, available_at = NOW()
                WHERE status = 'processing' AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $jobs = $this->db->query("SELECT * FROM analysis_jobs
                WHERE status = 'queued' AND available_at <= NOW()
                ORDER BY id ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED")->fetchAll();
            $claim = $this->db->prepare("UPDATE analysis_jobs SET status = 'processing', attempts = attempts + 1, locked_at = NOW()
                WHERE id = :id AND status = 'queued'");
            foreach ($jobs as $job) {
                $claim->execute(['id' => $job['id']]);
            }
            $this->db->commit();
            return $jobs;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function complete(int $jobId): bool
    {
        $statement = $this->db->prepare("UPDATE analysis_jobs SET status = 'completed', locked_at = NULL,
                finished_at = NOW(), error_message = NULL
            WHERE id = :id AND status = 'processing'");
        $statement->execute(['id' => $jobId]);
        return $statement->rowCount() > 0;
    }

    public function fail(int $jobId, string $message, bool $retryable = true): bool
    {
        $statement = $this->db->prepare('SELECT attempts FROM analysis_jobs WHERE id = :id AND status = \'processing\' LIMIT 1');
        $statement->execute(['id' => $jobId]);
        $attempts = $statement->fetchColumn();
        if ($attempts === false) {
            return false;
        }
        $message = mb_substr(trim($message), 0, 2000);
        if ($retryable && (int) $attempts < 3) {
            $delay = min(60, 5 * (2 ** max(0, (int) $attempts - 1)));
            $update = $this->db->prepare("UPDATE analysis_jobs SET status = 'queued', locked_at = NULL,
                    available_at = DATE_ADD(NOW(), INTERVAL {$delay} MINUTE), error_message = :error_message
                WHERE id = :id AND status = 'processing'");
        } else {
            $update = $this->db->prepare("UPDATE analysis_jobs SET status = 'failed', locked_at = NULL,
                    finished_at = NOW(), error_message = :error_message
                WHERE id = :id AND status = 'processing'");
        }
        $update->execute(['id' => $jobId, 'error_message' => $message]);
        return $update->rowCount() > 0;
    }

    /** @return array<string, int> */
    public function counts(?int $organizationId = null): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM analysis_jobs';
        $params = [];
        if ($organizationId !== null) {
            $sql .= ' WHERE organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }
        $sql .= ' GROUP BY status';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $counts = ['queued' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($statement->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }
        return $counts;
    }

    public function purge(int $days = 30): int
    {
        $days = max(1, min($days, 3650));
        $statement = $this->db->prepare("DELETE FROM analysis_jobs
            WHERE status IN ('completed', 'failed') AND finished_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)");
        return $statement->execute() ? $statement->rowCount() : 0;
    }
}
