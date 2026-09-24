<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class OrganizationRuleService
{
    private const TYPES = ['brand', 'domain', 'supplier', 'person', 'finance_contact'];

    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $organizationId): array
    {
        $statement = $this->db->prepare('SELECT id, rule_type, value, normalized_value, label, explanation, status, created_at
            FROM organization_rules WHERE organization_id = :organization_id ORDER BY status DESC, rule_type, value');
        $statement->execute(['organization_id' => $organizationId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function active(int $organizationId): array
    {
        $statement = $this->db->prepare('SELECT id, rule_type, value, normalized_value, label, explanation
            FROM organization_rules WHERE organization_id = :organization_id AND status = \'active\' ORDER BY id');
        $statement->execute(['organization_id' => $organizationId]);
        return $statement->fetchAll();
    }

    /** @return array{id: int} */
    public function create(int $organizationId, string $type, string $value, string $label, string $explanation, ?int $createdBy): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Ongeldig type organisatiecontext.');
        }
        $value = trim($value);
        $label = trim($label);
        $explanation = trim($explanation);
        if (mb_strlen($value) < 3 || mb_strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException('De contextwaarde moet 3 tot 255 tekens bevatten.');
        }
        if (mb_strlen($label) > 190 || mb_strlen($explanation) > 500) {
            throw new \InvalidArgumentException('Label of toelichting is te lang.');
        }
        $normalized = $this->normalize($value);
        try {
            $statement = $this->db->prepare('INSERT INTO organization_rules
                (organization_id, rule_type, value, normalized_value, label, explanation, status, created_by)
                VALUES (:organization_id, :rule_type, :value, :normalized_value, :label, :explanation, \'active\', :created_by)');
            $statement->execute([
                'organization_id' => $organizationId,
                'rule_type' => $type,
                'value' => $value,
                'normalized_value' => $normalized,
                'label' => $label !== '' ? mb_substr($label, 0, 190) : null,
                'explanation' => $explanation !== '' ? mb_substr($explanation, 0, 500) : null,
                'created_by' => $createdBy,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new \InvalidArgumentException('Deze organisatiecontext bestaat al.');
            }
            throw $exception;
        }
        return ['id' => (int) $this->db->lastInsertId()];
    }

    public function delete(int $organizationId, int $ruleId): bool
    {
        $statement = $this->db->prepare('DELETE FROM organization_rules WHERE id = :id AND organization_id = :organization_id');
        $statement->execute(['id' => $ruleId, 'organization_id' => $organizationId]);
        return $statement->rowCount() > 0;
    }

    public function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        return trim($value, " \t\n\r\0\x0B/");
    }
}
