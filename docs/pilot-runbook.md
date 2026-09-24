# ScamSpotter Business pilot-runbook

## 1. Pilot aanmaken

1. Log in op `/admin` en open **Business**.
2. Maak één organisatie, owner en tijdelijk sterk wachtwoord aan.
3. Laat de owner inloggen op `/business/login`.
4. Nodig vanuit **Organisatie** teamleden uit; deel de tijdelijke link alleen met de bedoelde medewerker.
5. Noteer de organisatie, pilotperiode en afgesproken bewaartermijn; deel geen checker-inhoud via losse e-mail.
6. Pauzeer of sluit een pilot na afloop via **Admin → Business**; beide statussen blokkeren nieuwe Business-logins zonder organisatiegegevens direct te verwijderen.

## 2. Outlook installeren

1. Download of open `/integrations/outlook/manifest.xml` vanaf de HTTPS-productieomgeving.
2. Sideload het manifest tijdens ontwikkeling, of deploy het als geïntegreerde app via het Microsoft 365 Admin Center.
3. Test eerst in Outlook on the web en New Outlook met een klein testpubliek.
4. De add-in vraagt alleen `ReadItem`: er is geen automatische mailboxscan en geen brede Microsoft Graph-applicatierechten.

## 3. Functionele tests

Gebruik testberichten zonder echte persoonsgegevens:

- een bekende pakket- of betaalfraude met een herkenbare indicator;
- een bericht met meerdere sociale-engineeringssignalen;
- een legitieme interne testmail;
- een bericht waarvoor onvoldoende informatie beschikbaar is.

Controleer per test de uitleg, signalen, vervolgstap, feedbackknoppen en de optionele melding. Controleer daarna in het Business-dashboard of de check en melding alleen binnen de juiste organisatie zichtbaar zijn.

## 4. Pilotmeting

Meet minimaal:

- actieve medewerkers en herhaalgebruik;
- handmatige checks en expliciete meldingen;
- nuttige matches, foutpositieven en feedback;
- tijd tussen check en melding-opvolging;
- checks met lokale match versus aanvullende AI-analyse;
- OpenAI-verbruik en totale kosten per organisatie.

De pilot is geslaagd wanneer medewerkers de uitleg begrijpen, beheerders er opvolging aan kunnen koppelen en de gebruikskosten voorspelbaar blijven. ScamSpotter vervangt geen Defender of bestaande mailbeveiliging.

## 5. Stoppen of verwijderen

1. Trek de add-in-deployment in Microsoft 365 in.
2. Deactiveer de organisatie of revoke eventuele API-keys.
3. Verwijder organisatiegegevens na afloop van de afgesproken retentie- en verwijderprocedure.
4. Leg feedback en false positives vast als productverbetering, niet als publieke scamclaim zonder redactionele beoordeling.
