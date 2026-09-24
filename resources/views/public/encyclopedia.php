<?php
declare(strict_types=1);
?>
<section class="page-header"><div class="container"><div class="breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> / Oplichtingstrucs</div><p class="eyebrow">De ScamSpotter kennisbank</p><h1>Alle oplichtingstrucs</h1><p>Van phishing en bankhelpdeskfraude tot romantische manipulatie. Leer de werkwijze herkennen, zonder dat je elk verhaal zelf hoeft te ontcijferen.</p></div></section>
<section class="section"><div class="container">
    <div class="taxonomy-list">
        <?php foreach ($families as $family): ?>
            <section class="taxonomy-family" id="<?= e($family['slug']) ?>"><p class="eyebrow">Scamfamilie</p><h2><?= e($family['name']) ?></h2><p><?= e($family['summary']) ?></p><div class="taxonomy-types">
                <?php foreach ($types as $type): if ((int) $type['family_id'] !== (int) $family['id']) continue; ?>
                    <a class="taxonomy-type" href="<?= e(url('/oplichting/' . $type['slug'])) ?>"><strong><?= e($type['name']) ?></strong><small><?= e($type['summary']) ?></small></a>
                <?php endforeach; ?>
            </div></section>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:48px"><div class="section-heading"><div><p class="eyebrow">Herkenbare patronen</p><h2>Scamvarianten</h2></div></div><div class="variant-grid">
        <?php foreach ($variants as $variant): ?><a class="variant-card" href="<?= e(url('/oplichting/' . $variant['slug'])) ?>"><small><?= e($variant['type_name']) ?></small><strong><?= e($variant['name']) ?></strong><p><?= e($variant['summary']) ?></p></a><?php endforeach; ?>
    </div></div>
</div></section>
