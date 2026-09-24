<?php
declare(strict_types=1);
?>
<div class="business-page">
    <div class="business-page-header"><div><p class="eyebrow">Organisatie-overzicht</p><h1>Blijf één stap voor.</h1><p>Bekijk hoe medewerkers verdachte communicatie herkennen en melden.</p></div><a class="button button-orange" href="<?= e(url('/business/check')) ?>">Mail controleren</a></div>
    <div class="business-metric-grid">
        <div class="business-metric"><span>Checks · 30 dagen</span><strong><?= e($metrics['checks'] ?? 0) ?></strong></div>
        <div class="business-metric"><span>Verdachte signalen</span><strong><?= e($metrics['suspicious'] ?? 0) ?></strong></div>
        <div class="business-metric"><span>Meldingen · 30 dagen</span><strong><?= e($metrics['reports'] ?? 0) ?></strong></div>
        <div class="business-metric"><span>Openstaande meldingen</span><strong><?= e($metrics['open_reports'] ?? 0) ?></strong></div>
        <div class="business-metric"><span>Actieve leden</span><strong><?= e($metrics['members'] ?? 0) ?></strong></div>
    </div>
    <div class="business-grid-two">
        <section class="business-card"><div class="business-card-header"><div><p class="eyebrow">Meldingen</p><h2>Recente signalen</h2></div><a href="<?= e(url('/business/reports')) ?>">Alles bekijken →</a></div><?php if ($recentReports === []): ?><div class="business-empty">Er zijn nog geen zakelijke meldingen.</div><?php else: ?><div class="business-list"><?php foreach ($recentReports as $report): ?><div class="business-list-row"><div><strong><?= e($report['subject'] ?: 'Verdachte communicatie') ?></strong><span><?= e($report['sender_domain'] ?: 'Afzender onbekend') ?> · <?= e(format_date($report['created_at'], 'j M Y H:i')) ?></span></div><span class="tag <?= in_array($report['status'], ['new', 'review'], true) ? 'tag-danger' : 'tag-green' ?>"><?= e(status_label((string) $report['status'])) ?></span></div><?php endforeach; ?></div><?php endif; ?></section>
        <section class="business-card"><p class="eyebrow">Patronen</p><h2>Meest voorkomende scamtypes</h2><?php if ($topTypes === []): ?><div class="business-empty">Na enkele checks verschijnen hier trends.</div><?php else: ?><div class="business-list"><?php foreach ($topTypes as $type): ?><div class="business-list-row"><strong><?= e($type['type_name'] ?: 'Onbekend') ?></strong><span><?= e($type['total']) ?> checks</span></div><?php endforeach; ?></div><?php endif; ?><div class="business-note">ScamSpotter vervangt geen Microsoft Defender. We leggen de menselijke manipulatie achter verdachte berichten uit.</div></section>
    </div>
</div>
