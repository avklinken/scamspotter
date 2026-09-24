<?php
declare(strict_types=1);
$status = $result['status'] ?? ['label' => 'Resultaat', 'explanation' => ''];
$top = $result['top_match'] ?? null;
?>
<div class="business-page">
    <div class="business-page-header"><div><p class="eyebrow">ScamSpotter-analyse</p><h1><?= e($status['label'] ?? 'Resultaat') ?></h1><p><?= e($status['explanation'] ?? '') ?></p></div><a class="button button-ghost" href="<?= e(url('/business/check')) ?>">Nieuwe check</a></div>
    <section class="business-result-grid">
        <article class="business-card"><p class="eyebrow">Sterkste overeenkomst</p><?php if (is_array($top)): ?><h2><?= e($top['variant']['name'] ?? 'Bekende scam') ?></h2><p><?= e($top['variant']['summary'] ?? '') ?></p><p><span class="tag"><?= e($top['variant']['type_name'] ?? '') ?></span> <span class="tag"><?= e($top['variant']['family_name'] ?? '') ?></span></p><h3>Herkenbare signalen</h3><ul class="signal-list"><?php foreach (($top['indicators'] ?? []) as $indicator): ?><li><strong><?= e($indicator['value'] ?? '') ?></strong><?php if (!empty($indicator['explanation'])): ?> — <?= e($indicator['explanation']) ?><?php endif; ?></li><?php endforeach; ?></ul><?php else: ?><div class="status-note">Er is geen sterke overeenkomst met een bekende scam gevonden. Dat betekent niet automatisch dat het bericht betrouwbaar is.</div><?php endif; ?></article>
        <aside class="business-card"><h2>Volgende stap</h2><p>Deel geen codes, wachtwoorden of betaalgegevens. Controleer de afzender via een onafhankelijk kanaal.</p><form method="post" action="<?= e(url('/api/v1/business/report')) ?>" data-business-report><input type="hidden" name="check_id" value="<?= e($checkId) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label class="form-label" for="business-report-description">Waarom meld je dit?</label><textarea class="form-textarea" id="business-report-description" name="description" rows="4" placeholder="Bijvoorbeeld: de afzender wijkt af van onze normale leverancier."></textarea><button class="button button-orange" type="submit">Meld als verdacht</button></form><p class="form-help" data-business-report-status></p></aside>
    </section>
</div>
