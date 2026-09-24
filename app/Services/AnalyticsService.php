<?php
declare(strict_types=1);

namespace App\Services;

final class AnalyticsService
{
    public function enabled(): bool
    {
        return (string) env('ANALYTICS_ID', '') !== '' || (string) env('GTM_ID', '') !== '';
    }

    public function providerScript(): string
    {
        if ((string) env('GTM_ID', '') !== '') {
            return '<!-- Analytics is intentionally loaded only after consent. -->';
        }
        return '';
    }
}
