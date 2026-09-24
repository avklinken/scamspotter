<?php
declare(strict_types=1);

namespace App\Services;

final class SeoService
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function metadata(array $data = []): array
    {
        $title = (string) ($data['title'] ?? 'ScamSpotter.nl — Herken de truc. Blijf één stap voor.');
        $description = (string) ($data['description'] ?? 'Herken verdachte berichten, websites, telefoontjes en e-mails met ScamSpotter.');
        $canonical = (string) ($data['canonical'] ?? url('/'));
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => (string) ($data['robots'] ?? 'index,follow'),
            'og_type' => (string) ($data['og_type'] ?? 'website'),
            'json_ld' => $data['json_ld'] ?? null,
            'share_image' => (string) ($data['share_image'] ?? asset('icons/share-card.svg')),
            'breadcrumbs' => $data['breadcrumbs'] ?? null,
        ];
    }
}
