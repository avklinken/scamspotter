<?php
declare(strict_types=1);

$seo ??= ['title' => 'Beheer — ScamSpotter.nl', 'robots' => 'noindex,nofollow'];
$success = flash('success');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo['title'] ?? 'Beheer — ScamSpotter.nl') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('icons/logo-light.svg')) ?>" alt="ScamSpotter.nl" width="180" height="35"></a>
        <nav aria-label="Beheernavigatie">
            <a href="<?= e(url('/admin')) ?>">Dashboard</a>
            <a href="<?= e(url('/admin/business')) ?>">Business</a>
            <a href="<?= e(url('/admin/scams')) ?>">Scams</a>
            <a href="<?= e(url('/admin/alerts')) ?>">Waarschuwingen</a>
            <a href="<?= e(url('/admin/sources')) ?>">Bronnen</a>
            <a href="<?= e(url('/admin/review')) ?>">AI-review</a>
            <a href="<?= e(url('/admin/reports')) ?>">Meldingen</a>
            <a href="<?= e(url('/admin/seo')) ?>">SEO</a>
            <a href="<?= e(url('/admin/cron')) ?>">Cron &amp; logs</a>
            <a href="<?= e(url('/admin/settings')) ?>">Instellingen</a>
        </nav>
        <a class="admin-public-link" href="<?= e(url('/')) ?>">← Naar publieke site</a>
    </aside>
    <section class="admin-main">
        <header class="admin-topbar"><span>ScamSpotter beheer</span><form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="link-button" type="submit">Uitloggen</button></form></header>
        <?php if ($success !== null): ?><div class="flash flash-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?= $content ?>
    </section>
</div>
</body>
</html>
