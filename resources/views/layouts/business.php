<?php
declare(strict_types=1);

$seo ??= ['title' => 'ScamSpotter Business', 'robots' => 'noindex,nofollow'];
$success = flash('success');
$error = flash('error');
$apiKey = flash('api_key');
$inviteLink = flash('invite_link');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo['title'] ?? 'ScamSpotter Business') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="business-body">
<div class="business-shell">
    <aside class="business-sidebar">
        <a class="admin-brand" href="<?= e(url('/business')) ?>"><img src="<?= e(asset('icons/logo-light.svg')) ?>" alt="ScamSpotter.nl" width="180" height="35"></a>
        <p class="business-label">Voor organisaties</p>
        <?php if (business_user() !== null): ?>
            <nav aria-label="Businessnavigatie">
                <a href="<?= e(url('/business')) ?>">Overzicht</a>
                <a href="<?= e(url('/business/check')) ?>">Mail controleren</a>
                <a href="<?= e(url('/business/reports')) ?>">Meldingen</a>
                <?php if (business_role_allows('admin')): ?><a href="<?= e(url('/business/settings')) ?>">Organisatie</a><?php endif; ?>
            </nav>
            <a class="business-public-link" href="<?= e(url('/')) ?>">← Publieke site</a>
        <?php endif; ?>
    </aside>
    <section class="business-main">
        <header class="business-topbar">
            <span><?= e((string) (business_user()['organization_name'] ?? 'ScamSpotter Business')) ?></span>
            <?php if (business_user() !== null): ?><form method="post" action="<?= e(url('/business/logout')) ?>"><?= csrf_field() ?><button class="link-button" type="submit">Uitloggen</button></form><?php endif; ?>
        </header>
        <?php if ($success !== null): ?><div class="flash flash-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error !== null): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($apiKey !== null): ?><div class="flash flash-key" role="status"><strong>Nieuwe API-key — kopieer deze nu:</strong><code><?= e($apiKey) ?></code><span>Om veiligheidsredenen wordt deze key niet opnieuw getoond.</span></div><?php endif; ?>
        <?php if ($inviteLink !== null): ?><div class="flash flash-key" role="status"><strong>Uitnodigingslink — deel deze veilig:</strong><code><?= e($inviteLink) ?></code><span>De link is zeven dagen geldig en wordt daarna niet meer getoond.</span></div><?php endif; ?>
        <?= $content ?>
    </section>
</div>
</body>
</html>
