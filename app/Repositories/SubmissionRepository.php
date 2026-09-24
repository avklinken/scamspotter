<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubmissionRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->db->prepare("INSERT INTO submissions
            (type, description, suspicious_text, submitted_url, submitted_email, submitted_phone,
             contact_email, consent, status, redaction_status, ip_hash, user_agent_hash, created_at, updated_at)
            VALUES (:type, :description, :suspicious_text, :submitted_url, :submitted_email, :submitted_phone,
             :contact_email, :consent, 'new', 'pending', :ip_hash, :user_agent_hash, NOW(), NOW())");
        $statement->execute([
            'type' => $data['type'],
            'description' => $data['description'],
            'suspicious_text' => $data['suspicious_text'],
            'submitted_url' => $data['submitted_url'],
            'submitted_email' => $data['submitted_email'],
            'submitted_phone' => $data['submitted_phone'],
            'contact_email' => $data['contact_email'],
            'consent' => $data['consent'] ? 1 : 0,
            'ip_hash' => $data['ip_hash'],
            'user_agent_hash' => $data['user_agent_hash'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 30): array
    {
        $statement = $this->db->query('SELECT * FROM submissions ORDER BY created_at DESC LIMIT ' . max(1, min($limit, 100)));
        return $statement->fetchAll();
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->db->prepare('UPDATE submissions SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    /** @param array<string, mixed> $attachment */
    public function addAttachment(int $submissionId, array $attachment): void
    {
        $statement = $this->db->prepare("INSERT INTO submission_attachments
            (submission_id, original_name, stored_name, mime_type, size_bytes, sha256, created_at)
            VALUES (:submission_id, :original_name, :stored_name, :mime_type, :size_bytes, :sha256, NOW())");
        $statement->execute([
            'submission_id' => $submissionId,
            'original_name' => $attachment['original_name'],
            'stored_name' => $attachment['stored_name'],
            'mime_type' => $attachment['mime_type'],
            'size_bytes' => $attachment['size_bytes'],
            'sha256' => $attachment['sha256'],
        ]);
    }
}
