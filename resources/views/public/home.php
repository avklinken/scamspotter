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
        <div class="hero-visual" aria-label="Voorbeeld van ScamSpotter op een smartphone">
            <div class="hero-stage">
                <div class="hero-stage-label"><span>SCAMSPOTTER CHECK</span><strong>Blijf één stap voor.</strong></div>
                <div class="hero-scan-card">
                    <small>Link check</small>
                    <strong>verdacht-example.com</strong>
                    <div class="scan-card-result"><span aria-hidden="true">!</span><div><b>Waarschijnlijk fraude</b><small>Smishing · herkenbare signalen</small></div></div>
                </div>
                <div class="mock-phone" role="img" aria-label="Mobiele ScamSpotter-check met waarschuwing voor smishing">
                    <div class="phone-button phone-button-volume"></div><div class="phone-button phone-button-silent"></div><div class="phone-button phone-button-power"></div>
                    <div class="mock-screen">
                        <div class="mock-statusbar"><span>8:41</span><span class="mock-island" aria-hidden="true"></span><span class="phone-status-icons" aria-hidden="true"><i class="phone-signal"></i><i class="phone-wifi"></i><i class="phone-battery"></i></span></div>
                        <div class="mock-appbar"><img src="<?= e(asset('icons/logo.svg')) ?>" alt="" width="128" height="24"><span class="phone-menu" aria-hidden="true"><i></i><i></i><i></i></span></div>
                        <div class="mock-screen-copy"><small>SCAMSPOTTER CHECK</small><strong>Iets verdachts<br>ontvangen?</strong><p>Plak een bericht, upload een screenshot of voer een link in. We helpen je direct.</p></div>
                        <div class="mock-input">Plak hier de tekst of link…</div>
                        <div class="mock-input-actions">
                            <span aria-label="Screenshot"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="8" cy="10" r="1.5"></circle><path d="m6 17 4-4 3 3 2-2 3 3"></path></svg></span>
                            <span aria-label="Link"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.5 14.5 5-5"></path><path d="m7.2 17.8-1 .9a3.2 3.2 0 0 1-4.5-4.5l3.5-3.5a3.2 3.2 0 0 1 4.5 0"></path><path d="m16.8 6.2 1-.9a3.2 3.2 0 0 1 4.5 4.5l-3.5 3.5a3.2 3.2 0 0 1-4.5 0"></path></svg></span>
                            <span aria-label="Telefoonnummer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.5 4.8 4.7a2 2 0 0 0-.9 2.5c1.8 5.1 5.8 9.1 10.9 10.9a2 2 0 0 0 2.5-.9l1.2-2.2-3.1-2.1-1.8 1.7a12 12 0 0 1-4.2-4.2l1.7-1.8L9.1 5.5 7 3.5Z"></path></svg></span>
                            <span aria-label="E-mailadres"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg></span>
                        </div>
                        <div class="mock-cta"><span>Controleren</span><b>→</b></div>
                        <p class="mock-footnote"><span aria-hidden="true">⌁</span> Je gegevens worden niet opgeslagen.</p>
                        <div class="mock-alert"><div><small>ACTUELE WAARSCHUWING</small><strong>Valse sms over pakket</strong></div><span class="mock-alert-badge">Actief</span><p>Smishing · 8 maart 2025</p></div>
                    </div>
                </div>
            </div>
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
