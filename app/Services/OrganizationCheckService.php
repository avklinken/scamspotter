<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class OrganizationCheckService
{
    public function __construct(
        private PDO $db,
        private ScamAnalysisOrchestrator $analysis,
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function run(int $organizationId, ?int $userId, array $data): array
    {
        $inputType = trim((string) ($data['input_type'] ?? 'email'));
        $subject = trim((string) ($data['subject'] ?? ''));
        $sender = trim((string) ($data['sender_email'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $maxChars = max(1000, (int) env('BUSINESS_MAX_MESSAGE_CHARS', '12000'));
        $body = mb_substr(strip_tags($body), 0, $maxChars);
        $analysisInput = trim($subject . "\n" . $sender . "\n" . $body);
        if ($analysisInput === '') {
            throw new \InvalidArgumentException('Voer minimaal een onderwerp, afzender of bericht in.');
        }

        $result = $this->analysis->analyze($inputType, $analysisInput, 'business');
        $top = is_array($result['top_match'] ?? null) ? $result['top_match'] : null;
        $retentionDays = $this->retentionDays($organizationId);
        $expiresAt = (new \DateTimeImmutable())->modify('+' . $retentionDays . ' days')->format('Y-m-d H:i:s');
        $hash = hash('sha256', $analysisInput);
        $resultForStorage = [
            'status' => $result['status'],
            'top_match' => $result['top_match'],
            'matches' => $result['matches'],
            'ai_analysis' => $result['ai_analysis'],
        ];

        $this->db->beginTransaction();
        try {
            $run = $this->db->prepare("INSERT INTO organization_analysis_runs
                (organization_id, user_id, channel, input_type, input_hash, status, variant_id, result_json, model, prompt_version, retention_until)
                VALUES (:organization_id, :user_id, 'outlook_addin', :input_type, :input_hash, :status, :variant_id, :result_json, :model, 'checker-v1', :retention_until)");
            $run->execute([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'input_type' => $inputType,
                'input_hash' => $hash,
                'status' => (string) ($result['status']['code'] ?? 'insufficient_information'),
                'variant_id' => $top['variant_id'] ?? null,
                'result_json' => json_text($resultForStorage),
                'model' => (string) env('OPENAI_MODEL', 'gpt-4o-mini'),
                'input_tokens' => $result['usage']['input_tokens'] ?? null,
                'output_tokens' => $result['usage']['output_tokens'] ?? null,
                'retention_until' => $expiresAt,
            ]);
            $runId = (int) $this->db->lastInsertId();

            $check = $this->db->prepare("INSERT INTO organization_checks
                (organization_id, user_id, analysis_run_id, input_type, subject, sender_email, sender_domain, redacted_excerpt, message_hash, status, expires_at)
                VALUES (:organization_id, :user_id, :run_id, :input_type, :subject, :sender_email, :sender_domain, :redacted_excerpt, :message_hash, :status, :expires_at)");
            $check->execute([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'run_id' => $runId,
                'input_type' => $inputType,
                'subject' => mb_substr($subject, 0, 500) ?: null,
                'sender_email' => mb_substr($sender, 0, 255) ?: null,
                'sender_domain' => $this->senderDomain($sender),
                'redacted_excerpt' => mb_substr((string) ($result['redacted_excerpt'] ?? ''), 0, 1200) ?: null,
                'message_hash' => $hash,
                'status' => (string) ($result['status']['code'] ?? 'insufficient_information'),
                'expires_at' => $expiresAt,
            ]);
            $checkId = (int) $this->db->lastInsertId();

            $this->event($organizationId, $userId, 'check_completed', 'organization_check', $checkId, ['status' => $result['status']['code'] ?? null]);
            if ($result['ai_analysis'] !== null) {
                $this->usage($organizationId, $userId, 'ai_analysis', (string) env('OPENAI_MODEL', 'gpt-4o-mini'), $result['usage'] ?? []);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $result + ['check_id' => $checkId, 'analysis_run_id' => $runId];
    }

    public function report(int $organizationId, ?int $userId, int $checkId, string $description): int
    {
        $check = $this->db->prepare('SELECT id, redacted_excerpt FROM organization_checks WHERE id = :id AND organization_id = :organization_id LIMIT 1');
        $check->execute(['id' => $checkId, 'organization_id' => $organizationId]);
        $row = $check->fetch();
        if (!is_array($row)) {
            throw new \InvalidArgumentException('Deze zakelijke check bestaat niet binnen de organisatie.');
        }
        $statement = $this->db->prepare("INSERT INTO organization_reports
            (organization_id, user_id, check_id, report_type, description, redacted_excerpt, status)
            VALUES (:organization_id, :user_id, :check_id, 'suspicious_email', :description, :redacted_excerpt, 'new')");
        $statement->execute([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'check_id' => $checkId,
            'description' => mb_substr(trim($description), 0, 3000) ?: null,
            'redacted_excerpt' => $row['redacted_excerpt'],
        ]);
        $reportId = (int) $this->db->lastInsertId();
        $this->event($organizationId, $userId, 'report_submitted', 'organization_report', $reportId, []);
        return $reportId;
    }

    private function retentionDays(int $organizationId): int
    {
        $statement = $this->db->prepare('SELECT retention_days FROM organizations WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $organizationId]);
        return max(1, min(365, (int) ($statement->fetchColumn() ?: 30)));
    }

    private function senderDomain(string $sender): ?string
    {
        $sender = trim($sender);
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        $parts = explode('@', $sender);
        return isset($parts[1]) ? mb_strtolower(mb_substr($parts[1], 0, 255)) : null;
    }

    /** @param array<string, mixed> $metadata */
    private function event(int $organizationId, ?int $userId, string $type, string $entityType, int $entityId, array $metadata): void
    {
        $statement = $this->db->prepare('INSERT INTO organization_events (organization_id, user_id, event_type, entity_type, entity_id, metadata_json) VALUES (:organization_id, :user_id, :event_type, :entity_type, :entity_id, :metadata_json)');
        $statement->execute([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'event_type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => json_text($metadata),
        ]);
    }

    /** @param array<string, mixed> $usage */
    private function usage(int $organizationId, ?int $userId, string $type, string $model, array $usage): void
    {
        $statement = $this->db->prepare('INSERT INTO usage_events (organization_id, user_id, event_type, units, model, input_tokens, output_tokens) VALUES (:organization_id, :user_id, :event_type, 1, :model, :input_tokens, :output_tokens)');
        $statement->execute(['organization_id' => $organizationId, 'user_id' => $userId, 'event_type' => $type, 'model' => $model, 'input_tokens' => $usage['input_tokens'] ?? null, 'output_tokens' => $usage['output_tokens'] ?? null]);
    }
}
