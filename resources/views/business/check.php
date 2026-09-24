<?php
declare(strict_types=1);
?>
<div class="business-page">
    <div class="business-page-header"><div><p class="eyebrow">Handmatige controle</p><h1>Controleer zakelijke mail.</h1><p>Gebruik dit formulier voor een test of plak de inhoud van een verdachte mail. De volledige inhoud wordt niet blijvend opgeslagen.</p></div></div>
    <section class="business-card business-check-card">
        <?php if (!empty($errors)): ?><div class="form-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="<?= e(url('/business/check')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="input_type" value="email">
            <div class="form-row"><div class="form-field"><label class="form-label" for="business-subject">Onderwerp</label><input class="form-control" id="business-subject" name="subject" maxlength="500"></div><div class="form-field"><label class="form-label" for="business-sender">Afzender</label><input class="form-control" id="business-sender" name="sender_email" type="email" maxlength="255" placeholder="afzender@voorbeeld.nl"></div></div>
            <div class="form-field"><label class="form-label" for="business-body">Berichtinhoud</label><textarea class="form-textarea" id="business-body" name="body" rows="14" maxlength="12000" required placeholder="Plak hier de verdachte e-mailtekst..."></textarea><p class="form-help">Verwijder persoonsgegevens die niet nodig zijn voor de beoordeling.</p></div>
            <button class="button button-orange" type="submit">Controleer met ScamSpotter →</button>
        </form>
    </section>
</div>
