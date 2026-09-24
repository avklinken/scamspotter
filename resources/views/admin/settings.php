<?php
declare(strict_types=1);
?>
<div class="admin-page"><div class="admin-page-header"><div><p class="eyebrow">Runtime-configuratie</p><h1>Instellingen</h1><p>Gevoelige waarden blijven in de omgeving en worden niet in de database of browser opgeslagen.</p></div></div><section class="admin-card"><table class="admin-table"><thead><tr><th>Instelling</th><th>Status</th></tr></thead><tbody><?php foreach ($settings as $setting): ?><tr><td><strong><?= e($setting['label']) ?></strong></td><td><?= e($setting['value']) ?></td></tr><?php endforeach; ?></tbody></table></section></div>
