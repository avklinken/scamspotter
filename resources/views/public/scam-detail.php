<?php
declare(strict_types=1);
$isVariant = ($item['kind'] ?? '') === 'variant';
$isType = ($item['kind'] ?? '') === 'type';
?>
<section class="page-header"><div class="container"><div class="breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> / <a href="<?= e(url('/oplichting')) ?>">Oplichtingstrucs</a><?php if ($isVariant): ?> / <?= e($item['type_name']) ?><?php endif; ?></div><p class="eyebrow"><?= e($isVariant ? ($item['type_name'] ?? 'Oplichtingstruc') : ($isType ? 'Scamtype' : 'Scamfamilie')) ?></p><h1><?= e($item['name']) ?></h1><p><?= e($item['summary'] ?? '') ?></p></div></section>
<section class="section"><div class="container content-layout"><article class="prose">
    <?php if ($isVariant): ?>
        <h2>Wat is <?= e($item['name']) ?>?</h2><p><?= nl2br(e((string) ($item['content'] ?? $item['summary'] ?? ''))) ?></p>
        <h2>Zo herken je deze werkwijze</h2><ul class="indicator-list"><?php foreach (($item['indicators'] ?? []) as $indicator): ?><li><span><strong><?= e($indicator['value']) ?></strong><?php if (!empty($indicator['explanation'])): ?><br><?= e($indicator['explanation']) ?><?php endif; ?></span></li><?php endforeach; ?></ul>
        <h2>Wat probeert de oplichter daarna te doen?</h2><p>Vaak volgt een verzoek om snel te betalen, gegevens te delen of een link te openen. Controleer de afzender altijd via een kanaal dat je zelf hebt opgezocht.</p>
        <h2>Wat kun je doen?</h2><p>Reageer niet vanuit het bericht. Maak geen geld over, deel geen codes en neem bij twijfel zelf contact op met de organisatie. Heb je al betaald of gegevens gedeeld? Neem dan zo snel mogelijk contact op met je bank en doe zo nodig aangifte.</p>
        <?php if (($item['alerts'] ?? []) !== []): ?><h2>Actuele waarschuwingen</h2><div class="alert-list"><?php foreach ($item['alerts'] as $alert): ?><a class="alert-row" href="<?= e(url('/waarschuwingen/' . $alert['slug'])) ?>"><div><h3><?= e($alert['title']) ?></h3><p><?= e($alert['summary']) ?></p></div><div class="alert-row-right"><span class="tag tag-danger">Actief</span><span class="card-link">Lees</span></div></a><?php endforeach; ?></div><?php endif; ?>
    <?php elseif ($isType): ?>
        <h2>Wat is <?= e($item['name']) ?>?</h2><p><?= nl2br(e((string) ($item['content'] ?? $item['summary'] ?? ''))) ?></p><h2>Bekende varianten</h2><div class="variant-grid"><?php foreach (($item['variants'] ?? []) as $variant): ?><a class="variant-card" href="<?= e(url('/oplichting/' . $variant['slug'])) ?>"><strong><?= e($variant['name']) ?></strong><p><?= e($variant['summary']) ?></p></a><?php endforeach; ?></div>
    <?php else: ?>
        <h2>Over deze scamfamilie</h2><p><?= nl2br(e((string) ($item['description'] ?? $item['summary'] ?? ''))) ?></p><h2>Scamtypes binnen deze familie</h2><div class="variant-grid"><?php foreach (($item['types'] ?? []) as $type): ?><a class="variant-card" href="<?= e(url('/oplichting/' . $type['slug'])) ?>"><strong><?= e($type['name']) ?></strong><p><?= e($type['summary']) ?></p></a><?php endforeach; ?></div>
    <?php endif; ?>
    <p class="form-help" style="margin-top:35px">Laatst gecontroleerd: <?= e(format_date($item['reviewed_at'] ?? $item['updated_at'] ?? null)) ?>. Zie ook onze <a href="<?= e(url('/over-scamspotter/werkwijze')) ?>">werkwijze en bronnen</a>.</p>
</article><aside class="sidebar-card"><h3>Twijfel over een bericht?</h3><p>Laat de checker meekijken naar de signalen. Een uitkomst is geen garantie, maar kan helpen bij je volgende stap.</p><a class="button button-orange" href="<?= e(url('/check')) ?>">Check iets verdachts</a><?php if ($isVariant && !empty($item['related'])): ?><h3 style="margin-top:27px">Gerelateerde scams</h3><?php foreach ($item['related'] as $related): ?><a href="<?= e(url('/oplichting/' . $related['slug'])) ?>"><?= e($related['name']) ?></a><?php endforeach; ?><?php endif; ?></aside></div></section>
