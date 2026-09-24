<?php
declare(strict_types=1);
?>
<main class="business-login-page">
    <div class="business-login-card">
        <img src="<?= e(asset('icons/logo.svg')) ?>" alt="ScamSpotter.nl" width="210" height="40">
        <p class="eyebrow">ScamSpotter Business</p>
        <h1>Bescherm de menselijke laag.</h1>
        <p>Controleer verdachte zakelijke communicatie en deel signalen veilig met je organisatie.</p>
        <?php if (!empty($error)): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('/business/login')) ?>">
            <?= csrf_field() ?>
            <div class="form-field"><label class="form-label" for="business-email">E-mailadres</label><input class="form-control" id="business-email" name="email" type="email" autocomplete="username" required></div>
            <div class="form-field"><label class="form-label" for="business-password">Wachtwoord</label><input class="form-control" id="business-password" name="password" type="password" autocomplete="current-password" required></div>
            <button class="button button-orange" type="submit">Inloggen</button>
        </form>
        <p class="form-help" style="margin-top:20px"><a href="<?= e(url('/voor-organisaties')) ?>">Meer over ScamSpotter Business</a> · <a href="<?= e(url('/')) ?>">Naar ScamSpotter.nl voor consumenten</a></p>
    </div>
</main>
