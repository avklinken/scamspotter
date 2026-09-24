<?php
declare(strict_types=1);
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow">Betrouwbare informatie. Actuele waarschuwingen. Praktisch advies.</p>
            <h1>Herken <span class="accent">oplichting</span> vóór je erin trapt.</h1>
            <p class="hero-copy">ScamSpotter helpt je verdachte berichten, telefoontjes, websites en e-mails herkennen. Gebaseerd op actuele waarschuwingen en betrouwbare bronnen.</p>
            <div class="hero-actions"><a class="button button-orange" data-analytics-event="scam_check_started" href="<?= e(url('/check')) ?>">⌕ &nbsp;Iets verdachts checken</a><a class="button button-ghost" href="<?= e(url('/waarschuwingen')) ?>">Bekijk actuele scams</a></div>
            <p class="hero-note">Je gegevens worden niet standaard opgeslagen.</p>
        </div>
        <div class="hero-visual" aria-label="Voorbeeld van een scamcheck">
            <div class="hero-signal"><small>Link check</small><strong>https://verdacht-example.com</strong><div class="signal-line"><span>Waarschijnlijk fraude</span><span>›</span></div></div>
            <div class="mock-phone"><div class="mock-notch"></div><small>Bericht ontvangen</small><strong>Uw pakket kan niet worden bezorgd.</strong><div class="mock-message">Betaal €1,99 om opnieuw te plannen:<br><b>post-nl-track.com</b><span class="risk-pill">▲ Smishing</span></div></div>
        </div>
    </div>
</section>
<section class="trust-strip"><div class="container trust-grid">
    <div class="trust-item"><span>⌕</span><div>Controleer<small>wat je niet vertrouwt</small></div></div>
    <div class="trust-item"><span>♢</span><div>Actuele<small>waarschuwingen</small></div></div>
    <div class="trust-item"><span>▤</span><div>Uitleg over<small>alle oplichtingstrucs</small></div></div>
    <div class="trust-item"><span>◎</span><div>Gebaseerd op<small>betrouwbare bronnen</small></div></div>
</div></section>
<section class="section"><div class="container">
    <div class="section-heading"><div><p class="eyebrow">Van phishing tot nepwebshop</p><h2>Weet waar je op moet letten.</h2></div><a class="button button-ghost" href="<?= e(url('/oplichting')) ?>">Alle oplichtingstrucs →</a></div>
    <div class="card-grid">
        <?php foreach (array_slice($families, 0, 6) as $family): ?>
            <article class="card family-card"><div class="card-pad"><p class="card-kicker">Scamfamilie</p><h3><?= e($family['name']) ?></h3><p><?= e($family['summary']) ?></p><a class="card-link" href="<?= e(url('/oplichting/' . $family['slug'])) ?>">Bekijk uitleg</a></div></article>
        <?php endforeach; ?>
    </div>
</div></section>
<section class="section section-muted"><div class="container">
    <div class="section-heading"><div><p class="eyebrow">Actueel</p><h2>Wat speelt er nu?</h2><p>Concrete campagnes en waarschuwingen, gekoppeld aan heldere uitleg over de onderliggende truc.</p></div><a class="button button-ghost" href="<?= e(url('/waarschuwingen')) ?>">Alle waarschuwingen →</a></div>
    <div class="card-grid">
        <?php foreach ($alerts as $alert): ?>
            <article class="card alert-card"><div class="card-pad"><div class="alert-meta"><span class="tag tag-danger">Actief</span><span><?= e(format_date($alert['published_at'] ?? $alert['created_at'])) ?></span></div><h3><?= e($alert['title']) ?></h3><p><?= e($alert['summary']) ?></p></div><div class="card-pad"><a class="card-link" href="<?= e(url('/waarschuwingen/' . $alert['slug'])) ?>">Lees waarschuwing</a></div></article>
        <?php endforeach; ?>
        <?php if ($alerts === []): ?><div class="empty-state"><p>De eerste actuele waarschuwingen worden hier zichtbaar zodra de redactie ze publiceert.</p></div><?php endif; ?>
    </div>
</div></section>
<section class="section"><div class="container"><div class="cta-band"><p class="eyebrow" style="color:#ff9a56">Twijfel je?</p><h2>Een vreemde mail, link of beller?</h2><p>Leg het voor aan de ScamSpotter-check. Je krijgt geen schijnzekerheid, maar wel een begrijpelijke uitleg van de signalen die we herkennen.</p><div class="hero-actions"><a class="button button-orange" href="<?= e(url('/check')) ?>">Start de scamchecker</a><a class="button button-ghost" href="<?= e(url('/melden')) ?>">Meld een scam</a></div></div></div></section>
