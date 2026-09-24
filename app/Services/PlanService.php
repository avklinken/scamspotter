<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class PlanService
{
    /** @var array<string, array{label: string, max_users: int, checks_month: int, api: bool}> */
    private const PLANS = [
        'business_trial' => ['label' => 'Business pilot', 'max_users' => 25, 'checks_month' => 500, 'api' => false],
        'business_starter' => ['label' => 'Business Starter', 'max_users' => 25, 'checks_month' => 1000, 'api' => false],
        'business_team' => ['label' => 'Business Team', 'max_users' => 100, 'checks_month' => 5000, 'api' => true],
        'business' => ['label' => 'Business', 'max_users' => 250, 'checks_month' => 15000, 'api' => true],
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed> */
    public function forOrganization(int $organizationId): array
    {
        $statement = $this->db->prepare("SELECT ca.id AS account_id, ca.name AS account_name, ca.status AS account_status,
                COALESCE(s.plan_code, 'business_trial') AS plan_code, COALESCE(s.status, 'trialing') AS subscription_status,
                s.current_period_end
            FROM organizations o INNER JOIN commercial_accounts ca ON ca.id = o.account_id
            LEFT JOIN account_subscriptions s ON s.id = (
                SELECT s2.id FROM account_subscriptions s2 WHERE s2.account_id = ca.id ORDER BY s2.id DESC LIMIT 1
            )
            WHERE o.id = :organization_id LIMIT 1");
        $statement->execute(['organization_id' => $organizationId]);
        $row = $statement->fetch() ?: [];
        $planCode = (string) ($row['plan_code'] ?? 'business_trial');
        $plan = self::PLANS[$planCode] ?? self::PLANS['business_trial'];
        return $row + ['plan_code' => $planCode, 'plan' => $plan];
    }

    /** @return array{allowed: bool, used: int, limit: int, remaining: int, plan_code: string} */
    public function allowance(int $organizationId): array
    {
        $planData = $this->forOrganization($organizationId);
        $statement = $this->db->prepare("SELECT COUNT(*) FROM organization_analysis_runs
            WHERE organization_id = :organization_id AND created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')");
        $statement->execute(['organization_id' => $organizationId]);
        $used = (int) $statement->fetchColumn();
        $limit = max(0, (int) ($planData['plan']['checks_month'] ?? 0));
        return [
            'allowed' => $limit === 0 || $used < $limit,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === 0 ? 0 : max(0, $limit - $used),
            'plan_code' => (string) ($planData['plan_code'] ?? 'business_trial'),
        ];
    }

    public function assertCheckAllowed(int $organizationId): void
    {
        $allowance = $this->allowance($organizationId);
        if (!$allowance['allowed']) {
            throw new PlanLimitException($allowance);
        }
    }

    /** @return array<string, int> */
    public function usage(int $organizationId): array
    {
        $allowance = $this->allowance($organizationId);
        $statement = $this->db->prepare("SELECT COUNT(*) FROM organization_analysis_runs
            WHERE organization_id = :organization_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $statement->execute(['organization_id' => $organizationId]);
        return [
            'checks_30d' => (int) $statement->fetchColumn(),
            'checks_month' => $allowance['used'],
            'limit' => $allowance['limit'],
            'remaining' => $allowance['remaining'],
        ];
    }
}
