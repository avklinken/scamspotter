<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ScamRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function families(): array
    {
        $statement = $this->db->query("SELECT * FROM scam_families WHERE status = 'published' ORDER BY sort_order, name");
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function types(?int $familyId = null): array
    {
        $sql = "SELECT t.*, f.name AS family_name, f.slug AS family_slug
                FROM scam_types t
                INNER JOIN scam_families f ON f.id = t.family_id
                WHERE t.status = 'published' AND f.status = 'published'";
        $params = [];
        if ($familyId !== null) {
            $sql .= ' AND t.family_id = :family_id';
            $params['family_id'] = $familyId;
        }
        $sql .= ' ORDER BY f.sort_order, t.name';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function variants(?int $typeId = null): array
    {
        $sql = "SELECT v.*, t.name AS type_name, t.slug AS type_slug, f.name AS family_name
                FROM scam_variants v
                INNER JOIN scam_types t ON t.id = v.type_id
                INNER JOIN scam_families f ON f.id = t.family_id
                WHERE v.status = 'published' AND t.status = 'published' AND f.status = 'published'";
        $params = [];
        if ($typeId !== null) {
            $sql .= ' AND v.type_id = :type_id';
            $params['type_id'] = $typeId;
        }
        $sql .= ' ORDER BY v.name';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->db->prepare("SELECT v.*, t.name AS type_name, t.slug AS type_slug, t.summary AS type_summary,
                f.name AS family_name, f.slug AS family_slug
                FROM scam_variants v
                INNER JOIN scam_types t ON t.id = v.type_id
                INNER JOIN scam_families f ON f.id = t.family_id
                WHERE v.slug = :slug AND v.status = 'published' AND t.status = 'published' AND f.status = 'published'
                LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $variant = $statement->fetch();
        if (is_array($variant)) {
            $variant['kind'] = 'variant';
            $variant['indicators'] = $this->indicatorsForVariant((int) $variant['id']);
            $variant['alerts'] = $this->alertsForVariant((int) $variant['id']);
            $variant['related'] = $this->relatedVariants((int) $variant['type_id'], (int) $variant['id']);
            return $variant;
        }

        $statement = $this->db->prepare("SELECT t.*, f.name AS family_name, f.slug AS family_slug
                FROM scam_types t INNER JOIN scam_families f ON f.id = t.family_id
                WHERE t.slug = :slug AND t.status = 'published' AND f.status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $type = $statement->fetch();
        if (is_array($type)) {
            $type['kind'] = 'type';
            $type['variants'] = $this->variants((int) $type['id']);
            return $type;
        }

        $statement = $this->db->prepare("SELECT * FROM scam_families WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $family = $statement->fetch();
        if (is_array($family)) {
            $family['kind'] = 'family';
            $family['types'] = $this->types((int) $family['id']);
            return $family;
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function indicatorsForVariant(int $variantId): array
    {
        $statement = $this->db->prepare("SELECT i.*, vi.weight_override
                FROM scam_indicators i INNER JOIN scam_variant_indicators vi ON vi.indicator_id = i.id
                WHERE vi.variant_id = :variant_id AND i.status = 'active'
                ORDER BY COALESCE(vi.weight_override, i.weight) DESC, i.value");
        $statement->execute(['variant_id' => $variantId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private function alertsForVariant(int $variantId): array
    {
        $statement = $this->db->prepare("SELECT a.*, s.organization AS source_organization
                FROM scam_alerts a LEFT JOIN sources s ON s.id = a.source_id
                WHERE a.variant_id = :variant_id AND a.status = 'published'
                ORDER BY COALESCE(a.published_at, a.created_at) DESC LIMIT 6");
        $statement->execute(['variant_id' => $variantId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private function relatedVariants(int $typeId, int $variantId): array
    {
        $statement = $this->db->prepare("SELECT id, name, slug, summary FROM scam_variants
                WHERE type_id = :type_id AND id <> :variant_id AND status = 'published' ORDER BY name LIMIT 6");
        $statement->execute(['type_id' => $typeId, 'variant_id' => $variantId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 24): array
    {
        $like = '%' . mb_strtolower(trim($query)) . '%';
        $sql = "SELECT * FROM (
                SELECT 'variant' AS result_type, v.name, v.slug, v.summary, t.name AS parent_name, 'Oplichtingstruc' AS label
                FROM scam_variants v INNER JOIN scam_types t ON t.id = v.type_id
                WHERE v.status = 'published' AND (LOWER(v.name) LIKE :q1 OR LOWER(v.summary) LIKE :q2 OR LOWER(v.slug) LIKE :q3)
                UNION ALL
                SELECT 'type', t.name, t.slug, t.summary, f.name, 'Scamtype'
                FROM scam_types t INNER JOIN scam_families f ON f.id = t.family_id
                WHERE t.status = 'published' AND (LOWER(t.name) LIKE :q4 OR LOWER(t.summary) LIKE :q5 OR LOWER(t.slug) LIKE :q6)
                UNION ALL
                SELECT 'alert', a.title, a.slug, a.summary, t.name, 'Waarschuwing'
                FROM scam_alerts a LEFT JOIN scam_variants v ON v.id = a.variant_id LEFT JOIN scam_types t ON t.id = v.type_id
                WHERE a.status = 'published' AND (LOWER(a.title) LIKE :q7 OR LOWER(a.summary) LIKE :q8 OR LOWER(a.slug) LIKE :q9)
            ) results ORDER BY name LIMIT " . max(1, min($limit, 100));
        $statement = $this->db->prepare($sql);
        $statement->execute([
            'q1' => $like, 'q2' => $like, 'q3' => $like,
            'q4' => $like, 'q5' => $like, 'q6' => $like,
            'q7' => $like, 'q8' => $like, 'q9' => $like,
        ]);
        $results = $statement->fetchAll();
        $aliasStatement = $this->db->prepare("SELECT 'variant' AS result_type, v.name, v.slug, v.summary, t.name AS parent_name, 'Alias' AS label
                FROM scam_aliases sa INNER JOIN scam_variants v ON sa.entity_type = 'variant' AND sa.entity_id = v.id
                INNER JOIN scam_types t ON t.id = v.type_id
                WHERE v.status = 'published' AND LOWER(sa.alias) LIKE :q
                UNION ALL
                SELECT 'type', t.name, t.slug, t.summary, f.name, 'Alias'
                FROM scam_aliases sa INNER JOIN scam_types t ON sa.entity_type = 'type' AND sa.entity_id = t.id
                INNER JOIN scam_families f ON f.id = t.family_id
                WHERE t.status = 'published' AND LOWER(sa.alias) LIKE :q2");
        $aliasStatement->execute(['q' => $like, 'q2' => $like]);
        $results = array_merge($results, $aliasStatement->fetchAll());
        $indicatorStatement = $this->db->prepare("SELECT DISTINCT 'variant' AS result_type, v.name, v.slug, v.summary, t.name AS parent_name, 'Signaal' AS label
                FROM scam_indicators i INNER JOIN scam_variant_indicators vi ON vi.indicator_id = i.id
                INNER JOIN scam_variants v ON v.id = vi.variant_id INNER JOIN scam_types t ON t.id = v.type_id
                WHERE v.status = 'published' AND i.status = 'active' AND (LOWER(i.value) LIKE :q OR LOWER(i.normalized_value) LIKE :q2)");
        $indicatorStatement->execute(['q' => $like, 'q2' => $like]);
        $results = array_merge($results, $indicatorStatement->fetchAll());
        $unique = [];
        foreach ($results as $result) {
            $key = ($result['result_type'] ?? '') . ':' . ($result['slug'] ?? '');
            $unique[$key] ??= $result;
        }
        return array_values($unique);
    }

    /** @return list<array<string, mixed>> */
    public function matchCandidates(string $text): array
    {
        $statement = $this->db->query("SELECT v.id, v.type_id, v.name, v.slug, v.summary, t.family_id,
                t.name AS type_name, t.slug AS type_slug, f.name AS family_name, f.slug AS family_slug,
                i.id AS indicator_id, i.indicator_type, i.value,
                i.normalized_value, i.explanation, COALESCE(vi.weight_override, i.weight) AS weight
                FROM scam_variants v
                INNER JOIN scam_types t ON t.id = v.type_id
                INNER JOIN scam_families f ON f.id = t.family_id
                INNER JOIN scam_variant_indicators vi ON vi.variant_id = v.id
                INNER JOIN scam_indicators i ON i.id = vi.indicator_id
                WHERE v.status = 'published' AND t.status = 'published' AND f.status = 'published' AND i.status = 'active'");
        $needle = mb_strtolower($text);
        $matches = [];
        foreach ($statement->fetchAll() as $row) {
            $value = mb_strtolower((string) ($row['normalized_value'] ?: $row['value']));
            if ($value === '' || !str_contains($needle, $value)) {
                continue;
            }
            $id = (int) $row['id'];
            $matches[$id]['variant'] = [
                'id' => $id,
                'type_id' => (int) $row['type_id'],
                'family_id' => (int) $row['family_id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'summary' => $row['summary'],
                'type_name' => $row['type_name'],
                'type_slug' => $row['type_slug'],
                'family_name' => $row['family_name'],
                'family_slug' => $row['family_slug'],
            ];
            $matches[$id]['indicators'][] = [
                'type' => $row['indicator_type'],
                'value' => $row['value'],
                'explanation' => $row['explanation'],
                'weight' => (float) $row['weight'],
            ];
        }
        $aliasStatement = $this->db->query("SELECT entity_id, alias, normalized_alias FROM scam_aliases WHERE entity_type = 'variant'");
        foreach ($aliasStatement->fetchAll() as $alias) {
            $value = mb_strtolower((string) $alias['normalized_alias']);
            $id = (int) $alias['entity_id'];
            if ($value !== '' && str_contains($needle, $value) && isset($matches[$id])) {
                $matches[$id]['indicators'][] = [
                    'type' => 'alias',
                    'value' => $alias['alias'],
                    'explanation' => 'Deze term is als alias aan de variant gekoppeld.',
                    'weight' => 1.5,
                ];
            }
        }
        return array_values($matches);
    }
}
