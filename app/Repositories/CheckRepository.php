<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CheckRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . max(1, (int) ($data['retention_days'] ?? 7)) . ' days')->format('Y-m-d H:i:s');
        $statement = $this->db->prepare("INSERT INTO checks
            (input_type, campaign_identifier, redacted_input, input_hash, status, ip_hash, attribution_json, created_at, expires_at)
            VALUES (:input_type, :campaign_identifier, :redacted_input, :input_hash, :status, :ip_hash, :attribution_json, NOW(), :expires_at)");
        $statement->execute([
            'input_type' => $data['input_type'],
            'campaign_identifier' => $data['campaign_identifier'] ?? null,
            'redacted_input' => $data['redacted_input'],
            'input_hash' => $data['input_hash'],
            'status' => $data['status'],
            'ip_hash' => $data['ip_hash'],
            'attribution_json' => json_text($data['attribution'] ?? []),
            'expires_at' => $expiresAt,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param list<array<string, mixed>> $matches */
    public function addMatches(int $checkId, array $matches): void
    {
        $statement = $this->db->prepare("INSERT INTO check_matches
            (check_id, variant_id, match_score, match_status, matched_indicators, created_at)
            VALUES (:check_id, :variant_id, :score, :status, :indicators, NOW())");
        foreach ($matches as $match) {
            $statement->execute([
                'check_id' => $checkId,
                'variant_id' => $match['variant_id'],
                'score' => $match['score'],
                'status' => $match['status'],
                'indicators' => json_text($match['indicators']),
            ]);
        }
    }
}
