<div class="business-page">
    <section class="business-card">
        <div class="business-card-header"><div><p class="eyebrow">Organisatiecontext</p><h2>Eigen signalen</h2></div></div>
        <p>Voeg alleen context toe die medewerkers nodig hebben, zoals een eigen domein, merknaam of vaste leverancier. Dit maakt een match niet automatisch fraude.</p>
        <form method="post" action="<?= e(url('/business/settings/rule')) ?>" class="form-row">
            <?= csrf_field() ?>
            <div class="form-field"><label class="form-label" for="business-rule-type">Type</label><select class="form-select" id="business-rule-type" name="rule_type"><option value="domain">Bedrijfsdomein</option><option value="brand">Merknaam</option><option value="supplier">Leverancier</option><option value="person">Naam persoon</option><option value="finance_contact">Financieel contact</option></select></div>
            <div class="form-field"><label class="form-label" for="business-rule-value">Waarde</label><input class="form-control" id="business-rule-value" name="value" maxlength="255" required placeholder="bijv. bedrijf.nl"></div>
            <div class="form-field"><label class="form-label" for="business-rule-label">Label</label><input class="form-control" id="business-rule-label" name="label" maxlength="190" placeholder="bijv. Officieel bedrijfsdomein"></div>
            <div class="form-field"><label class="form-label" for="business-rule-explanation">Toelichting</label><input class="form-control" id="business-rule-explanation" name="explanation" maxlength="500" placeholder="Waarom is dit relevant?"></div>
            <div class="form-field" style="align-self:end"><button class="button button-orange" type="submit">Context opslaan</button></div>
        </form>
        <?php if ($organizationRules !== []): ?>
            <div class="business-table-wrap" style="margin-top:20px"><table class="business-table"><thead><tr><th>Type</th><th>Waarde</th><th>Label</th><th></th></tr></thead><tbody>
            <?php foreach ($organizationRules as $rule): ?><tr><td><?= e($rule['rule_type']) ?></td><td><strong><?= e($rule['value']) ?></strong></td><td><?= e($rule['label'] ?: '—') ?></td><td><form method="post" action="<?= e(url('/business/settings/rule/delete')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($rule['id']) ?>"><button class="button button-small button-ghost" type="submit">Verwijderen</button></form></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <div class="business-note">ScamSpotter gebruikt deze signalen als organisatiecontext bij zakelijke checks; ze worden niet toegevoegd aan de publieke kennisbank.</div>
    </section>
</div>
