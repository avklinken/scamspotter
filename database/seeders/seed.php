<?php
declare(strict_types=1);

use App\Database;
use App\Support\Env;

require dirname(__DIR__, 2) . '/app/bootstrap.php';
$db = Database::connect();
$db->beginTransaction();

try {
    $sourceIds = [];
    $sources = [
        ['Fraudehelpdesk', 'fraudehelpdesk', 'https://www.fraudehelpdesk.nl/', 'https://www.fraudehelpdesk.nl/feed/?post_type=alert', 'rss', 'rss', 'Nederlandse meldingen en waarschuwingen over fraude.'],
        ['Politie', 'politie', 'https://www.politie.nl/onderwerpen/fraude.html', '', 'website', 'manual', 'Officiële informatie en aangifte-informatie.'],
        ['NCSC', 'ncsc', 'https://www.ncsc.nl/actueel', '', 'website', 'rss', 'Informatie van het Nationaal Cyber Security Centrum.'],
        ['Opgelicht?!', 'opgelicht', 'https://opgelicht.avrotros.nl/', '', 'website', 'manual', 'Journalistieke signalering van oplichting en fraude.'],
        ['Booking.com Security', 'booking-security', 'https://partner.booking.com/en-us/help/legal-security/security', '', 'advisory', 'manual', 'Officiële beveiligingsinformatie voor reserveringen.'],
    ];
    $sourceStatement = $db->prepare("INSERT INTO sources (organization, title, slug, homepage, feed_url, source_type, trust_status, active, crawl_method, notes)
        VALUES (:organization, :title, :slug, :homepage, :feed_url, :source_type, 'verified', 1, :crawl_method, :notes)
        ON DUPLICATE KEY UPDATE organization = VALUES(organization), homepage = VALUES(homepage), feed_url = COALESCE(VALUES(feed_url), feed_url), notes = VALUES(notes), trust_status = 'verified', active = 1");
    foreach ($sources as [$organization, $slug, $homepage, $feedUrl, $sourceType, $crawlMethod, $notes]) {
        $sourceStatement->execute([
            'organization' => $organization,
            'title' => $organization,
            'slug' => $slug,
            'homepage' => $homepage,
            'feed_url' => $feedUrl !== '' ? $feedUrl : null,
            'source_type' => $sourceType,
            'crawl_method' => $crawlMethod,
            'notes' => $notes,
        ]);
        $id = $db->prepare('SELECT id FROM sources WHERE slug = :slug');
        $id->execute(['slug' => $slug]);
        $sourceIds[$slug] = (int) $id->fetchColumn();
    }

    $familyIds = [];
    $families = [
        ['phishing-identiteitsfraude', 'Phishing & identiteitsfraude', 'Berichten en websites die je naar gegevens, codes of een valse betaalpagina lokken.', 10],
        ['impersonatiefraude', 'Impersonatiefraude', 'Een oplichter doet zich voor als bank, familielid, werkgever of bekende organisatie.', 20],
        ['advance-fee-fraude', 'Advance-fee fraude', 'Je krijgt iets waardevols beloofd, maar moet eerst betalen voordat het zover is.', 30],
        ['handelsfraude', 'Handelsfraude', 'Fraude rond kopen, verkopen, marktplaatsen en webwinkels.', 40],
        ['beleggingsfraude', 'Beleggingsfraude', 'Verleidelijke investeringen met druk, nepresultaten of moeilijk terug te krijgen geld.', 50],
        ['relatie-en-manipulatiefraude', 'Relatie- en manipulatiefraude', 'Oplichters bouwen vertrouwen op en sturen daarna aan op geld, geheimhouding of beeldmateriaal.', 60],
        ['tech-supportfraude', 'Tech- en supportfraude', 'Valse helpdesks of meldingen die toegang tot je apparaat of rekening proberen te krijgen.', 70],
        ['werk-en-inkomensfraude', 'Werk- en inkomensfraude', 'Nepvacatures, taken of verdiensten waarbij je gegevens of geld moet voorschieten.', 80],
        ['reis-en-reserveringsfraude', 'Reis- en reserveringsfraude', 'Valse boekingen, betaalverzoeken en supportberichten rond reizen en accommodaties.', 90],
    ];
    $familyStatement = $db->prepare("INSERT INTO scam_families (name, slug, summary, description, status, sort_order, published_at, reviewed_at)
        VALUES (:name, :slug, :summary, :description, 'published', :sort_order, NOW(), NOW())
        ON DUPLICATE KEY UPDATE name = VALUES(name), summary = VALUES(summary), description = VALUES(description), status = 'published', sort_order = VALUES(sort_order)");
    foreach ($families as [$slug, $name, $summary, $sortOrder]) {
        $familyStatement->execute(['name' => $name, 'slug' => $slug, 'summary' => $summary, 'description' => $summary, 'sort_order' => $sortOrder]);
        $id = $db->prepare('SELECT id FROM scam_families WHERE slug = :slug');
        $id->execute(['slug' => $slug]);
        $familyIds[$slug] = (int) $id->fetchColumn();
    }

    $typeIds = [];
    $types = [
        ['phishing-identiteitsfraude', 'phishing', 'Phishing', 'Valse e-mails of websites die inloggegevens of betaalgegevens proberen te stelen.', 'Een bericht lijkt afkomstig van een bekende organisatie en stuurt je naar een nagebouwde pagina.'],
        ['phishing-identiteitsfraude', 'smishing', 'Smishing', 'Phishing via sms of chat, vaak met een korte link en een dringend verzoek.', 'De boodschap gebruikt een bezorging, betaling of accountmelding om je snel te laten klikken.'],
        ['phishing-identiteitsfraude', 'quishing', 'Quishing', 'Phishing via een QR-code die naar een valse website of betaalpagina leidt.', 'Een QR-code voelt vertrouwd, maar verbergt de bestemming. Controleer de URL voordat je gegevens invoert.'],
        ['phishing-identiteitsfraude', 'credential-harvesting', 'Credential harvesting', 'Het verzamelen van gebruikersnamen, wachtwoorden of eenmalige codes via een valse inlogflow.', 'De pagina lijkt op de echte dienst en vraagt om meer gegevens dan normaal.'],
        ['impersonatiefraude', 'bankhelpdeskfraude', 'Bankhelpdeskfraude', 'Iemand doet zich voor als bankmedewerker en vraagt je geld veilig te stellen of codes te delen.', 'De beller gebruikt autoriteit en tijdsdruk. Een bank vraagt je niet om geld naar een veilige rekening over te boeken.'],
        ['impersonatiefraude', 'whatsapp-hulpvraagfraude', 'WhatsApp-hulpvraagfraude', 'Een oplichter doet zich voor als familielid met een nieuw nummer en vraagt om snel geld.', 'Het bericht gebruikt herkenbare familie-informatie en zegt dat bellen even niet kan.'],
        ['impersonatiefraude', 'ceo-fraude', 'CEO-fraude', 'Een verzoek lijkt van een directeur of collega te komen en vraagt om een betaling of geheimhouding.', 'De afzender wijkt af van de normale werkwijze en probeert controle door een tweede persoon te omzeilen.'],
        ['advance-fee-fraude', 'fake-giveaway', 'Fake giveaway', 'Een gratis product of prijs blijkt afhankelijk van transportkosten, administratiekosten of een voorschot.', 'Het verhaal is emotioneel en aantrekkelijk; betalen is de voorwaarde om iets “gratis” te ontvangen.'],
        ['advance-fee-fraude', 'recovery-scam', 'Recovery scam', 'Na eerdere fraude belooft iemand je geld terug te halen tegen een nieuwe betaling.', 'De oplichter kent details van een eerdere schade en vraagt om kosten, codes of toegang.'],
        ['handelsfraude', 'nepwebshop', 'Nepwebshop', 'Een webshop biedt producten aan maar levert niet, of verzamelt betaal- en persoonsgegevens.', 'Extreem lage prijzen, recente domeinen, afwijkende betaalmethodes en ontbrekende contactgegevens zijn signalen.'],
        ['handelsfraude', 'marketplacefraude', 'Marketplacefraude', 'Fraude rond kopen of verkopen via een handelsplatform, vaak met een valse betaal- of verzendlink.', 'De andere partij wil buiten het platform communiceren of stuurt een link voor “verificatie”.'],
        ['beleggingsfraude', 'investment-scam', 'Beleggingsfraude', 'Een nepplatform toont hoge rendementen en stuurt aan op steeds grotere stortingen.', 'Er is druk om direct te beslissen en opnemen lukt pas na nieuwe kosten.'],
        ['relatie-en-manipulatiefraude', 'romance-scam', 'Romance scam', 'Een online relatie wordt gebruikt om vertrouwen op te bouwen en daarna geld te vragen.', 'De persoon kan nooit afspreken en heeft steeds een urgente reden voor financiële hulp.'],
        ['relatie-en-manipulatiefraude', 'sextortion', 'Sextortion', 'Een oplichter dreigt intiem beeldmateriaal te verspreiden om geld of meer beelden af te dwingen.', 'De druk loopt snel op. Betalen stopt de dreiging meestal niet.'],
        ['tech-supportfraude', 'tech-support-scam', 'Tech-support scam', 'Een valse beveiligingsmelding of helpdesk probeert toegang tot je computer of rekening te krijgen.', 'De “helpdesk” vraagt om software, meekijken op afstand of betaalgegevens.'],
        ['werk-en-inkomensfraude', 'job-scam', 'Job scam', 'Een nepvacature vraagt om persoonsgegevens, een betaling of het ontvangen en doorsturen van geld.', 'Het aanbod belooft snel geld zonder normaal sollicitatieproces.'],
        ['werk-en-inkomensfraude', 'task-scam', 'Task scam', 'Een online taakplatform laat je eerst kleine bedragen verdienen en vraagt daarna om een voorschot.', 'Je moet betalen om taken vrij te spelen of je saldo op te nemen.'],
        ['reis-en-reserveringsfraude', 'booking-reservation-scam', 'Booking/reserveringsfraude', 'Een valse reserveringsmelding vraagt om betaling via een link buiten het bekende platform.', 'De boodschap gebruikt een bestaande boeking, korte deadline of zogenaamde verificatie.'],
    ];
    $typeStatement = $db->prepare("INSERT INTO scam_types (family_id, name, slug, summary, content, status, published_at, reviewed_at)
        VALUES (:family_id, :name, :slug, :summary, :content, 'published', NOW(), NOW())
        ON DUPLICATE KEY UPDATE family_id = VALUES(family_id), name = VALUES(name), summary = VALUES(summary), content = VALUES(content), status = 'published'");
    foreach ($types as [$familySlug, $slug, $name, $summary, $content]) {
        $typeStatement->execute(['family_id' => $familyIds[$familySlug], 'name' => $name, 'slug' => $slug, 'summary' => $summary, 'content' => $content]);
        $id = $db->prepare('SELECT id FROM scam_types WHERE slug = :slug');
        $id->execute(['slug' => $slug]);
        $typeIds[$slug] = (int) $id->fetchColumn();
    }

    $variantIds = [];
    $variants = [
        ['fake-giveaway', 'gratis-piano-scam', 'Gratis piano scam', 'Een gratis piano wordt aangeboden na een emotioneel verhaal, maar transportkosten moeten vooraf worden betaald.', 'Vaak vertelt de afzender dat een overleden echtgenoot of partner de piano achterlaat. Na interesse volgt een transportbedrijf en een verzoek om transportkosten vooraf te betalen.'],
        ['smishing', 'pakket-opnieuw-plannen-199', 'Pakket opnieuw plannen €1,99', 'Een sms doet zich voor als bezorgdienst en vraagt €1,99 om een pakket opnieuw te plannen.', 'De link leidt naar een nagebouwde betaalpagina. Daarna kunnen betaalgegevens of bankinloggegevens worden gevraagd.'],
        ['whatsapp-hulpvraagfraude', 'nieuw-telefoonnummer-whatsapp', '“Nieuw telefoonnummer” WhatsApp-scam', 'Een bekende lijkt vanaf een nieuw nummer te berichten en vraagt om een snelle betaling.', 'Het gesprek wordt bewust kort gehouden. De oplichter vraagt om een rekening te betalen omdat de bankapp of telefoon nog niet werkt.'],
        ['bankhelpdeskfraude', 'veilige-rekening-bankhelpdeskfraude', 'Veilige rekening bij bankhelpdeskfraude', 'Een nepbankmedewerker zegt dat je geld naar een veilige rekening moet overboeken.', 'De beller gebruikt angst over fraude op je rekening en wil dat je codes deelt of geld overmaakt. Banken vragen dit niet.'],
        ['quishing', 'qr-code-parkeerboete', 'QR-code voor parkeerboete', 'Een QR-code op een brief of bericht leidt naar een valse betaalpagina voor een parkeerboete.', 'De QR-code verbergt de uiteindelijke URL. Controleer boetes via het officiële loket in plaats van de QR-code.'],
        ['booking-reservation-scam', 'booking-reservering-betalingslink', 'Booking reservation payment scam', 'Een bericht over een bestaande reservering vraagt om betaling of verificatie via een nieuwe link.', 'De afzender creëert tijdsdruk en vraagt om buiten het platform te betalen.'],
        ['recovery-scam', 'geld-terug-na-fraude', 'Geld terug na eerdere fraude', 'Een zogenaamde specialist belooft eerder verloren geld terug te halen voor een voorschot.', 'Er wordt vaak verwezen naar een dossiernummer en daarna gevraagd om kosten of remote access.'],
        ['nepwebshop', 'extreem-lage-prijs-webshop', 'Webshop met extreem lage prijs', 'Een professioneel ogende webshop verkoopt populaire producten ver onder de marktprijs.', 'Na betaling wordt niet geleverd of worden aanvullende gegevens gevraagd. Controleer bedrijfsgegevens en onafhankelijke ervaringen.'],
    ];
    $variantStatement = $db->prepare("INSERT INTO scam_variants (type_id, name, slug, summary, content, status, published_at, reviewed_at)
        VALUES (:type_id, :name, :slug, :summary, :content, 'published', NOW(), NOW())
        ON DUPLICATE KEY UPDATE type_id = VALUES(type_id), name = VALUES(name), summary = VALUES(summary), content = VALUES(content), status = 'published'");
    foreach ($variants as [$typeSlug, $slug, $name, $summary, $content]) {
        $variantStatement->execute(['type_id' => $typeIds[$typeSlug], 'name' => $name, 'slug' => $slug, 'summary' => $summary, 'content' => $content]);
        $id = $db->prepare('SELECT id FROM scam_variants WHERE slug = :slug');
        $id->execute(['slug' => $slug]);
        $variantIds[$slug] = (int) $id->fetchColumn();
    }

    $indicatorStatement = $db->prepare("INSERT INTO scam_indicators (indicator_type, value, normalized_value, explanation, weight, status, source_id, first_seen, last_seen)
        VALUES (:indicator_type, :value, :normalized_value, :explanation, :weight, 'active', :source_id, NOW(), NOW())");
    $existingIndicator = $db->prepare('SELECT id FROM scam_indicators WHERE indicator_type = :indicator_type AND normalized_value = :normalized_value LIMIT 1');
    $linkStatement = $db->prepare("INSERT IGNORE INTO scam_variant_indicators (variant_id, indicator_id, weight_override) VALUES (:variant_id, :indicator_id, :weight_override)");
    $indicatorsByVariant = [
        'gratis-piano-scam' => [
            ['story', 'overleden echtgenoot', 'Een emotioneel verhaal over een overleden partner komt vaak terug.', 2.5],
            ['story', 'overleden partner', 'De afzender noemt een overlijden als reden voor het aanbod.', 2.5],
            ['product', 'gratis piano', 'Het product wordt als gratis aangeboden.', 2.2],
            ['product', 'Yamaha', 'Yamaha-modelnamen komen voor in meerdere meldingen.', 2.0],
            ['product', 'Yamaha GC1', 'Een concreet Yamaha GC1-model is een herkenbaar patroon.', 2.8],
            ['audience', 'muziekliefhebber', 'De ontvanger wordt aangesproken als liefhebber, student of docent.', 1.6],
            ['action', 'transportkosten', 'Na interesse wordt een bedrag voor transport of verhuizing gevraagd.', 3.5],
            ['payment', 'vooraf betalen', 'De betaling moet plaatsvinden voordat levering kan gebeuren.', 3.4],
            ['action', 'transportbedrijf', 'Een zogenaamd transportbedrijf wordt als tussenstap opgevoerd.', 2.8],
        ],
        'pakket-opnieuw-plannen-199' => [
            ['payment', '€1,99', 'Een klein bedrag wordt gebruikt om de betaalstap geloofwaardig te maken.', 2.8],
            ['action', 'opnieuw te plannen', 'Het bericht zegt dat een pakket opnieuw moet worden gepland.', 2.2],
            ['channel', 'sms', 'De campagne komt vaak als sms binnen.', 1.4],
            ['action', 'pakket', 'Een bezorging is het excuus voor de link.', 1.6],
        ],
        'nieuw-telefoonnummer-whatsapp' => [
            ['channel', 'nieuw telefoonnummer', 'De afzender zegt vanaf een nieuw nummer te berichten.', 3.0],
            ['channel', 'WhatsApp', 'Het gesprek vindt plaats via WhatsApp.', 1.4],
            ['action', 'kun je dit betalen', 'Er wordt gevraagd om snel een rekening of bedrag voor te schieten.', 2.8],
        ],
        'veilige-rekening-bankhelpdeskfraude' => [
            ['impersonation', 'bankmedewerker', 'De beller gebruikt de identiteit van een bankmedewerker.', 2.8],
            ['payment', 'veilige rekening', 'Het geld moet naar een zogenaamde veilige rekening.', 4.0],
            ['urgency', 'verdachte transactie', 'Angst voor een verdachte transactie creëert tijdsdruk.', 2.0],
            ['action', 'code doorgeven', 'Er wordt om een beveiligingscode gevraagd.', 3.0],
        ],
        'qr-code-parkeerboete' => [
            ['channel', 'QR-code', 'De QR-code verbergt waar de link naartoe gaat.', 2.2],
            ['payment', 'parkeerboete', 'De betaling wordt aan een boete gekoppeld.', 2.4],
            ['action', 'betaal direct', 'De ontvanger krijgt weinig tijd om te handelen.', 1.8],
        ],
        'booking-reservering-betalingslink' => [
            ['brand', 'Booking', 'De afzender leunt op de bekendheid van Booking.', 1.8],
            ['action', 'betaling verifiëren', 'Er wordt om een extra betaling of verificatie gevraagd.', 2.5],
            ['channel', 'reservering', 'De boodschap verwijst naar een bestaande of vermeende reservering.', 1.8],
        ],
        'geld-terug-na-fraude' => [
            ['promise', 'geld terug', 'Er wordt herstel van eerdere schade beloofd.', 2.8],
            ['payment', 'voorschot', 'Voor de hulp moet eerst een voorschot worden betaald.', 3.2],
            ['action', 'dossiernummer', 'Een dossiernummer moet vertrouwen wekken.', 1.8],
        ],
        'extreem-lage-prijs-webshop' => [
            ['price', 'extreem lage prijs', 'De aanbieding wijkt sterk af van de marktprijs.', 2.6],
            ['commerce', 'webshop', 'De campagne gebruikt een online winkel als context.', 1.5],
            ['payment', 'alleen vooraf betalen', 'Er is geen normale veilige betaaloptie beschikbaar.', 2.8],
        ],
    ];
    foreach ($indicatorsByVariant as $variantSlug => $indicators) {
        foreach ($indicators as [$indicatorType, $value, $explanation, $weight]) {
            $normalizedValue = mb_strtolower($value);
            $existingIndicator->execute(['indicator_type' => $indicatorType, 'normalized_value' => $normalizedValue]);
            $indicatorId = (int) ($existingIndicator->fetchColumn() ?: 0);
            if ($indicatorId === 0) {
                $indicatorStatement->execute([
                    'indicator_type' => $indicatorType,
                    'value' => $value,
                    'normalized_value' => $normalizedValue,
                    'explanation' => $explanation,
                    'weight' => $weight,
                    'source_id' => $sourceIds['fraudehelpdesk'],
                ]);
                $indicatorId = (int) $db->lastInsertId();
            }
            $linkStatement->execute(['variant_id' => $variantIds[$variantSlug], 'indicator_id' => $indicatorId, 'weight_override' => null]);
        }
    }

    $aliasStatement = $db->prepare('INSERT IGNORE INTO scam_aliases (entity_type, entity_id, alias, normalized_alias) VALUES (:entity_type, :entity_id, :alias, :normalized_alias)');
    foreach ([['variant', $variantIds['gratis-piano-scam'], 'gratis piano'], ['variant', $variantIds['gratis-piano-scam'], 'piano van overleden echtgenoot'], ['type', $typeIds['bankhelpdeskfraude'], 'veilige rekening fraude'], ['type', $typeIds['quishing'], 'qr phishing']] as [$entityType, $entityId, $alias]) {
        $aliasStatement->execute(['entity_type' => $entityType, 'entity_id' => $entityId, 'alias' => $alias, 'normalized_alias' => mb_strtolower($alias)]);
    }

    $alertStatement = $db->prepare("INSERT INTO scam_alerts (variant_id, source_id, title, slug, summary, body, event_date, status, published_at, last_reviewed_at)
        VALUES (:variant_id, :source_id, :title, :slug, :summary, :body, :event_date, 'published', :published_at, NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), body = VALUES(body), variant_id = VALUES(variant_id), source_id = VALUES(source_id), status = 'published', published_at = VALUES(published_at), last_reviewed_at = NOW()");
    $alerts = [
        ['gratis-piano-scam', 'fraudehelpdesk', 'Gratis Yamaha GC1 na overlijden? Let op transportkosten', 'yamaha-gc1-overleden-echtgenoot', 'Een aanbod voor een gratis Yamaha-piano blijkt te leiden naar een verzoek om transportkosten vooraf te betalen.', 'In deze campagne wordt een Yamaha GC1 aangeboden na een emotioneel verhaal over een overleden echtgenoot. Na interesse volgt een zogenaamd transportbedrijf dat vooraf geld vraagt. Betaal niet en deel geen persoonsgegevens.', '2025-03-12'],
        ['pakket-opnieuw-plannen-199', 'fraudehelpdesk', 'Valse sms over pakket opnieuw plannen voor €1,99', 'pakket-opnieuw-plannen-199', 'Een sms vraagt €1,99 om een pakket opnieuw te laten bezorgen.', 'De link in dit bericht leidt niet naar de officiële bezorgdienst. De betaalpagina kan daarna extra kaart- of inloggegevens vragen.', '2025-03-08'],
        ['qr-code-parkeerboete', 'politie', 'Pas op voor QR-codes bij valse parkeerboetes', 'qr-code-parkeerboete', 'Een QR-code kan naar een nagebouwde betaalpagina voor een parkeerboete leiden.', 'Controleer parkeerboetes via de officiële website van de gemeente of instantie. Scan geen QR-code als je de herkomst niet kunt controleren.', '2025-02-25'],
    ];
    foreach ($alerts as [$variantSlug, $sourceSlug, $title, $slug, $summary, $body, $eventDate]) {
        $alertStatement->execute(['variant_id' => $variantIds[$variantSlug], 'source_id' => $sourceIds[$sourceSlug], 'title' => $title, 'slug' => $slug, 'summary' => $summary, 'body' => $body, 'event_date' => $eventDate, 'published_at' => $eventDate . ' 09:00:00']);
    }
    $alertLookup = $db->prepare('SELECT id FROM scam_alerts WHERE slug = :slug');
    $sourceLink = $db->prepare("INSERT IGNORE INTO source_links (source_id, entity_type, entity_id, label, published_at, checked_at) VALUES (:source_id, 'alert', :entity_id, :label, :published_at, NOW())");
    foreach ($alerts as [$variantSlug, $sourceSlug, $title, $slug, $summary, $body, $eventDate]) {
        $alertLookup->execute(['slug' => $slug]);
        $sourceLink->execute(['source_id' => $sourceIds[$sourceSlug], 'entity_id' => (int) $alertLookup->fetchColumn(), 'label' => $title, 'published_at' => $eventDate . ' 09:00:00']);
    }

    $landingStatement = $db->prepare("INSERT INTO landing_pages (slug, heading, intro, input_type, campaign_identifier, seo_title, meta_description, indexable, status)
        VALUES (:slug, :heading, :intro, :input_type, :campaign_identifier, :seo_title, :meta_description, 0, 'published')
        ON DUPLICATE KEY UPDATE heading = VALUES(heading), intro = VALUES(intro), input_type = VALUES(input_type), status = 'published'");
    foreach ([
        ['verdachte-mail', 'Verdachte e-mail checken', 'Plak de tekst van de e-mail. Verwijder gerust persoonsgegevens voordat je hem deelt.', 'message'],
        ['verdachte-sms', 'Verdachte sms checken', 'Plak het sms-bericht en let vooral op links, betaalverzoeken en tijdsdruk.', 'message'],
        ['verdachte-website', 'Verdachte website checken', 'Controleer een link voordat je inlogt, betaalt of gegevens invult.', 'url'],
        ['telefoonnummer', 'Telefoonnummer checken', 'Vul een nummer in en beschrijf eventueel wat de beller vroeg.', 'phone'],
    ] as [$slug, $heading, $intro, $inputType]) {
        $landingStatement->execute(['slug' => $slug, 'heading' => $heading, 'intro' => $intro, 'input_type' => $inputType, 'campaign_identifier' => $slug, 'seo_title' => $heading . ' — ScamSpotter.nl', 'meta_description' => $intro]);
    }

    $tagStatement = $db->prepare('INSERT IGNORE INTO tags (name, slug) VALUES (:name, :slug)');
    foreach ([['Actueel', 'actueel'], ['Betaalverzoek', 'betaalverzoek'], ['Tijdsdruk', 'tijdsdruk'], ['Impersonatie', 'impersonatie']] as [$name, $slug]) {
        $tagStatement->execute(['name' => $name, 'slug' => $slug]);
    }

    $sourceItem = $db->prepare("INSERT INTO source_items (source_id, external_id, url, title, raw_content, normalized_content, content_hash, published_at, status)
        VALUES (:source_id, :external_id, :url, :title, :raw_content, :normalized_content, :content_hash, NOW(), 'new')
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), title = VALUES(title), raw_content = VALUES(raw_content), normalized_content = VALUES(normalized_content)");
    $sourceItem->execute(['source_id' => $sourceIds['fraudehelpdesk'], 'external_id' => 'seed-qr-parkeerboete', 'url' => 'https://www.fraudehelpdesk.nl/voorbeeld/qr-code-parkeerboete', 'title' => 'Fraudehelpdesk: QR-code voor parkeerboete', 'raw_content' => 'Voorbeeldbron voor de redactionele review-queue.', 'normalized_content' => 'qr code parkeerboete', 'content_hash' => hash('sha256', 'seed-qr-parkeerboete')]);
    $sourceItemId = (int) $db->lastInsertId();
    $aiLookup = $db->prepare('SELECT id FROM ai_analyses WHERE source_item_id = :source_item_id AND prompt_version = \'v1\' LIMIT 1');
    $aiLookup->execute(['source_item_id' => $sourceItemId]);
    $aiId = (int) ($aiLookup->fetchColumn() ?: 0);
    if ($aiId === 0) {
        $ai = $db->prepare("INSERT INTO ai_analyses (source_item_id, model, prompt_version, input_hash, classification, output_json, status)
            VALUES (:source_item_id, :model, 'v1', :input_hash, 'new_alert', :output_json, 'suggestion')");
        $ai->execute(['source_item_id' => $sourceItemId, 'model' => 'seed-demo', 'input_hash' => hash('sha256', 'seed-qr-parkeerboete'), 'output_json' => json_text(['classification' => 'new_alert', 'family' => 'Phishing & identiteitsfraude', 'type' => 'Quishing', 'variant' => 'QR-code voor parkeerboete', 'recognized_signals' => ['parkeerboete', 'QR-code'], 'likely_next_step' => 'Betaalgegevens laten invullen.', 'reasoning_summary' => 'Sluit aan bij een bestaande quishing-variant.', 'recommended_editorial_action' => 'Koppel aan bestaand type en maak alert.', 'duplicate_status' => 'existing_variant', 'suggested_tags' => ['actueel', 'betaalverzoek']])]);
        $aiId = (int) $db->lastInsertId();
    }
    $reviewLookup = $db->prepare("SELECT id FROM review_queue WHERE source_item_id = :source_item_id AND status = 'pending' LIMIT 1");
    $reviewLookup->execute(['source_item_id' => $sourceItemId]);
    if ($reviewLookup->fetchColumn() === false) {
        $review = $db->prepare("INSERT INTO review_queue (source_item_id, ai_analysis_id, item_type, priority, status, suggested_action) VALUES (:source_item_id, :ai_id, 'source_item', 8, 'pending', 'Koppel aan Quishing en beoordeel als nieuwe waarschuwing')");
        $review->execute(['source_item_id' => $sourceItemId, 'ai_id' => $aiId]);
    }

    $db->commit();
    echo "Seed data geladen.\n";
} catch (Throwable $exception) {
    $db->rollBack();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
