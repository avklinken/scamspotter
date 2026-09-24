<?php
declare(strict_types=1);

$seo ??= [
    'title' => 'ScamSpotter.nl — Herken de truc. Blijf één stap voor.',
    'description' => 'Herken verdachte berichten, websites, telefoontjes en e-mails met ScamSpotter.',
    'canonical' => url('/'),
    'robots' => 'index,follow',
];
$success = flash('success');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo['title'] ?? '') ?></title>
    <meta name="description" content="<?= e($seo['description'] ?? '') ?>">
    <meta name="robots" content="<?= e($seo['robots'] ?? 'index,follow') ?>">
    <link rel="canonical" href="<?= e($seo['canonical'] ?? url('/')) ?>">
    <meta property="og:type" content="<?= e($seo['og_type'] ?? 'website') ?>">
    <meta property="og:site_name" content="ScamSpotter.nl">
    <meta property="og:title" content="<?= e($seo['title'] ?? '') ?>">
    <meta property="og:description" content="<?= e($seo['description'] ?? '') ?>">
    <meta property="og:url" content="<?= e($seo['canonical'] ?? url('/')) ?>">
    <meta property="og:image" content="<?= e($seo['share_image'] ?? asset('icons/share-card.svg')) ?>">
    <meta name="twitter:card" content="summary">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (isset($seo['json_ld']) && is_array($seo['json_ld'])): ?>
        <script type="application/ld+json"><?= json_text($seo['json_ld']) ?></script>
    <?php endif; ?>
    <?php if (isset($seo['breadcrumbs']) && is_array($seo['breadcrumbs'])): ?><script type="application/ld+json"><?= json_text(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(static fn (array $item, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']], $seo['breadcrumbs'], array_keys($seo['breadcrumbs']))]) ?></script><?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Ga naar hoofdinhoud</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="ScamSpotter.nl home">
            <img src="<?= e(asset('icons/logo.svg')) ?>" alt="ScamSpotter.nl" width="195" height="37">
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav id="site-nav" class="site-nav" aria-label="Hoofdnavigatie">
            <a href="<?= e(url('/check')) ?>">Scamchecker</a>
            <a href="<?= e(url('/waarschuwingen')) ?>">Actuele scams</a>
            <a href="<?= e(url('/oplichting')) ?>">Alle oplichtingstrucs</a>
            <a href="<?= e(url('/melden')) ?>">Melden</a>
            <a href="<?= e(url('/over-scamspotter')) ?>">Over ons</a>
            <a class="nav-search" href="<?= e(url('/zoeken')) ?>" aria-label="Zoeken">⌕</a>
            <a class="button button-small button-orange" href="<?= e(url('/check')) ?>">Iets verdachts checken</a>
        </nav>
    </div>
</header>
<?php if ($success !== null): ?><div class="container"><div class="flash flash-success" role="status"><?= e($success) ?></div></div><?php endif; ?>
<main id="main-content">
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <img src="<?= e(asset('icons/logo-light.svg')) ?>" alt="ScamSpotter.nl" width="195" height="37">
            <p>Herken de truc. Blijf één stap voor.</p>
            <p class="footer-muted">Een veiliger internet begint bij bewustwording.</p>
        </div>
        <div><h2>Ontdek</h2><a href="<?= e(url('/oplichting')) ?>">Oplichtingstrucs</a><a href="<?= e(url('/waarschuwingen')) ?>">Actuele waarschuwingen</a><a href="<?= e(url('/check')) ?>">Scam checken</a><a href="<?= e(url('/melden')) ?>">Melden</a></div>
        <div><h2>Over ons</h2><a href="<?= e(url('/bronnen')) ?>">Bronnen</a><a href="<?= e(url('/over-scamspotter/werkwijze')) ?>">Werkwijze</a><a href="<?= e(url('/voor-organisaties')) ?>">Voor organisaties</a><a href="<?= e(url('/contact')) ?>">Contact</a></div>
        <div><h2>Informatie</h2><a href="<?= e(url('/privacy')) ?>">Privacy</a><a href="<?= e(url('/cookies')) ?>">Cookies</a><a href="<?= e(url('/over-scamspotter')) ?>">Disclaimer</a></div>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> ScamSpotter.nl</span><span>Informatie, geen vervanging voor aangifte of persoonlijk advies.</span></div>
</footer>
<div class="cookie-notice" data-cookie-notice hidden>
    <p>We gebruiken standaard geen optionele tracking. Wil je helpen ScamSpotter te verbeteren?</p>
    <div><button class="button button-small button-orange" type="button" data-consent="accept">Accepteren</button><button class="button button-small button-ghost" type="button" data-consent="decline">Alleen noodzakelijk</button></div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script>window.scamSpotterConfig = <?= json_text(['gtmId' => env('GTM_ID', ''), 'ga4Id' => env('ANALYTICS_ID', '')]) ?>;</script>
</body>
</html>
