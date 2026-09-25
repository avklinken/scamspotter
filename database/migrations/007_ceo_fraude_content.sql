SET NAMES utf8mb4;

UPDATE scam_types
SET summary = 'Een oplichter doet zich voor als directeur, bestuurder, collega of zakenpartner en stuurt aan op een ongebruikelijke betaling, wijziging of geheimhouding.',
    content = 'Bij CEO-fraude doet een oplichter zich voor als iemand met gezag binnen of rond een organisatie. Het verzoek komt vaak via e-mail, WhatsApp of telefoon en wijkt af van de normale werkwijze.\n\nDe afzender gebruikt tijdsdruk, vertrouwelijkheid en details uit de organisatie om een betaling, wijziging van rekeningnummer of aankoop los te krijgen. De naam en handtekening kunnen kloppen terwijl het e-mailadres, telefoonnummer of betaalverzoek vals is.',
    status = 'published',
    reviewed_at = NOW()
WHERE slug = 'ceo-fraude';

INSERT INTO scam_variants (type_id, name, slug, summary, content, status, published_at, reviewed_at)
SELECT t.id, 'Spoedbetaling namens de directeur', 'spoedbetaling-namens-directeur', 'Een bericht lijkt van een directeur of bestuurder te komen en vraagt om snel een betaling uit te voeren.', 'De afzender benadrukt dat het vertrouwelijk is en dat de normale goedkeuringsroute niet kan worden gevolgd.', 'published', NOW(), NOW()
FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE type_id = VALUES(type_id), name = VALUES(name), summary = VALUES(summary), content = VALUES(content), status = 'published', reviewed_at = NOW();

INSERT INTO scam_variants (type_id, name, slug, summary, content, status, published_at, reviewed_at)
SELECT t.id, 'Gewijzigd rekeningnummer van een leverancier', 'gewijzigd-rekeningnummer-leverancier', 'Een bekende leverancier of directeur meldt dat een factuur voortaan naar een ander rekeningnummer moet worden betaald.', 'De wijziging komt onverwacht en het nieuwe rekeningnummer wordt alleen in het bericht bevestigd.', 'published', NOW(), NOW()
FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE type_id = VALUES(type_id), name = VALUES(name), summary = VALUES(summary), content = VALUES(content), status = 'published', reviewed_at = NOW();

INSERT INTO scam_variants (type_id, name, slug, summary, content, status, published_at, reviewed_at)
SELECT t.id, 'Cadeaubonnen voor de directeur', 'cadeaubonnen-voor-directeur', 'Een vermeende leidinggevende vraagt om cadeaubonnen te kopen en de codes door te sturen.', 'Het verzoek wordt als een snelle, geheime attentie gepresenteerd en controle door collega’s wordt ontmoedigd.', 'published', NOW(), NOW()
FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE type_id = VALUES(type_id), name = VALUES(name), summary = VALUES(summary), content = VALUES(content), status = 'published', reviewed_at = NOW();

INSERT INTO scam_variants (type_id, name, slug, summary, content, status, published_at, reviewed_at)
SELECT t.id, 'WhatsApp-bericht namens de directeur', 'whatsapp-namens-directeur', 'Een oplichter gebruikt WhatsApp of een nieuw nummer om zich voor te doen als een directeur of manager.', 'De afzender houdt het gesprek kort en vraagt om direct te handelen omdat bellen of overleg niet uitkomt.', 'published', NOW(), NOW()
FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE type_id = VALUES(type_id), name = VALUES(name), summary = VALUES(summary), content = VALUES(content), status = 'published', reviewed_at = NOW();

INSERT INTO scam_aliases (entity_type, entity_id, alias, normalized_alias)
SELECT 'type', t.id, 'business email compromise', 'business email compromise' FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE alias = VALUES(alias);
INSERT INTO scam_aliases (entity_type, entity_id, alias, normalized_alias)
SELECT 'type', t.id, 'BEC', 'bec' FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE alias = VALUES(alias);
INSERT INTO scam_aliases (entity_type, entity_id, alias, normalized_alias)
SELECT 'type', t.id, 'directiefraude', 'directiefraude' FROM scam_types t WHERE t.slug = 'ceo-fraude'
ON DUPLICATE KEY UPDATE alias = VALUES(alias);

INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'impersonation', 'directeur', 'directeur', 'De afzender gebruikt de naam of rol van een directeur of bestuurder.', 2.8, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'impersonation' AND normalized_value = 'directeur');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'urgency', 'met spoed', 'met spoed', 'Tijdsdruk moet voorkomen dat iemand het verzoek controleert.', 2.6, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'urgency' AND normalized_value = 'met spoed');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'payment', 'betaling uitvoeren', 'betaling uitvoeren', 'Het uiteindelijke doel is een ongebruikelijke overboeking.', 3.2, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'payment' AND normalized_value = 'betaling uitvoeren');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'secrecy', 'vertrouwelijk', 'vertrouwelijk', 'Geheimhouding maakt controle door een collega moeilijker.', 2.8, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'secrecy' AND normalized_value = 'vertrouwelijk');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'impersonation', 'leverancier', 'leverancier', 'Een bekende leverancier of zakenpartner wordt nagebootst.', 2.2, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'impersonation' AND normalized_value = 'leverancier');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'payment', 'rekeningnummer gewijzigd', 'rekeningnummer gewijzigd', 'De betaalgegevens wijken onverwacht af van eerdere facturen.', 3.5, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'payment' AND normalized_value = 'rekeningnummer gewijzigd');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'action', 'factuur betalen', 'factuur betalen', 'Een bestaande factuur wordt gebruikt om de betaling geloofwaardig te maken.', 2.4, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'action' AND normalized_value = 'factuur betalen');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'action', 'bevestig per e-mail', 'bevestig per e-mail', 'De afzender probeert onafhankelijke verificatie te vermijden.', 2.4, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'action' AND normalized_value = 'bevestig per e-mail');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'impersonation', 'manager', 'manager', 'De afzender verwijst naar een manager of directeur.', 2.4, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'impersonation' AND normalized_value = 'manager');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'payment', 'cadeaubonnen', 'cadeaubonnen', 'Cadeaubonnen zijn na aankoop moeilijk terug te halen.', 3.2, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'payment' AND normalized_value = 'cadeaubonnen');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'action', 'codes doorsturen', 'codes doorsturen', 'De oplichter wil de codes of foto’s direct ontvangen.', 3.0, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'action' AND normalized_value = 'codes doorsturen');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'secrecy', 'geheim houden', 'geheim houden', 'Geheimhouding moet collega’s buiten het verzoek houden.', 2.5, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'secrecy' AND normalized_value = 'geheim houden');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'channel', 'WhatsApp', 'whatsapp', 'De oplichter gebruikt een chatkanaal dat informeel en snel voelt.', 1.8, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'channel' AND normalized_value = 'whatsapp');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'channel', 'nieuw nummer', 'nieuw nummer', 'Een nieuw nummer maakt controle van de identiteit lastiger.', 2.8, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'channel' AND normalized_value = 'nieuw nummer');
INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
SELECT 'urgency', 'kun je dit regelen', 'kun je dit regelen', 'Een korte opdracht stuurt aan op direct handelen.', 2.2, 'active', (SELECT id FROM sources WHERE slug = 'fraudehelpdesk' LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM scam_indicators WHERE indicator_type = 'urgency' AND normalized_value = 'kun je dit regelen');

INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'spoedbetaling-namens-directeur' AND i.indicator_type = 'impersonation' AND i.normalized_value = 'directeur';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'spoedbetaling-namens-directeur' AND i.indicator_type = 'urgency' AND i.normalized_value = 'met spoed';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'spoedbetaling-namens-directeur' AND i.indicator_type = 'payment' AND i.normalized_value = 'betaling uitvoeren';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'spoedbetaling-namens-directeur' AND i.indicator_type = 'secrecy' AND i.normalized_value = 'vertrouwelijk';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'gewijzigd-rekeningnummer-leverancier' AND i.indicator_type = 'impersonation' AND i.normalized_value = 'leverancier';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'gewijzigd-rekeningnummer-leverancier' AND i.indicator_type = 'payment' AND i.normalized_value = 'rekeningnummer gewijzigd';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'gewijzigd-rekeningnummer-leverancier' AND i.indicator_type = 'action' AND i.normalized_value = 'factuur betalen';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'gewijzigd-rekeningnummer-leverancier' AND i.indicator_type = 'action' AND i.normalized_value = 'bevestig per e-mail';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'cadeaubonnen-voor-directeur' AND i.indicator_type = 'impersonation' AND i.normalized_value = 'directeur';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'cadeaubonnen-voor-directeur' AND i.indicator_type = 'payment' AND i.normalized_value = 'cadeaubonnen';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'cadeaubonnen-voor-directeur' AND i.indicator_type = 'action' AND i.normalized_value = 'codes doorsturen';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'cadeaubonnen-voor-directeur' AND i.indicator_type = 'secrecy' AND i.normalized_value = 'geheim houden';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'whatsapp-namens-directeur' AND i.indicator_type = 'channel' AND i.normalized_value = 'whatsapp';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'whatsapp-namens-directeur' AND i.indicator_type = 'channel' AND i.normalized_value = 'nieuw nummer';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'whatsapp-namens-directeur' AND i.indicator_type = 'impersonation' AND i.normalized_value = 'manager';
INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id)
SELECT v.id, i.id FROM scam_variants v CROSS JOIN scam_indicators i
WHERE v.slug = 'whatsapp-namens-directeur' AND i.indicator_type = 'urgency' AND i.normalized_value = 'kun je dit regelen';
