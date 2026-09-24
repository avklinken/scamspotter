<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AlertRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function published(array $filters = [], int $limit = 30): array
    {
        $sql = "SELECT a.*, v.name AS variant_name, v.slug AS variant_slug, t.name AS type_name,
                f.name AS family_name, s.organization AS source_organization
                FROM scam_alerts a
                LEFT JOIN scam_variants v ON v.id = a.variant_id
                LEFT JOIN scam_types t ON t.id = v.type_id
                LEFT JOIN scam_families f ON f.id = t.family_id
                LEFT JOIN sources s ON s.id = a.source_id
                WHERE a.status = 'published'";
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (LOWER(a.title) LIKE :q OR LOWER(a.summary) LIKE :q2)';
            $params['q'] = '%' . mb_strtolower((string) $filters['q']) . '%';
            $params['q2'] = $params['q'];
        }
        if (($filters['family'] ?? '') !== '') {
            $sql .= ' AND f.slug = :family';
            $params['family'] = (string) $filters['family'];
        }
        if (($filters['type'] ?? '') !== '') {
            $sql .= ' AND t.slug = :type';
            $params['type'] = (string) $filters['type'];
        }
        if (($filters['source'] ?? '') !== '') {
            $sql .= ' AND s.slug = :source';
            $params['source'] = (string) $filters['source'];
        }
        if (($filters['date'] ?? '') === '30') {
            $sql .= ' AND COALESCE(a.published_at, a.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif (($filters['date'] ?? '') === '90') {
            $sql .= ' AND COALESCE(a.published_at, a.created_at) >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
        }
        $sql .= ' ORDER BY COALESCE(a.published_at, a.created_at) DESC LIMIT ' . max(1, min($limit, 100));
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findPublished(string $slug): ?array
    {
        $statement = $this->db->prepare("SELECT a.*, v.name AS variant_name, v.slug AS variant_slug,
                v.summary AS variant_summary, t.name AS type_name, t.slug AS type_slug,
                f.name AS family_name, f.slug AS family_slug, s.organization AS source_organization,
                s.homepage AS source_homepage
                FROM scam_alerts a
                LEFT JOIN scam_variants v ON v.id = a.variant_id
                LEFT JOIN scam_types t ON t.id = v.type_id
                LEFT JOIN scam_families f ON f.id = t.family_id
                LEFT JOIN sources s ON s.id = a.source_id
                WHERE a.slug = :slug AND a.status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $alert = $statement->fetch();
        return is_array($alert) ? $alert : null;
    }
}
