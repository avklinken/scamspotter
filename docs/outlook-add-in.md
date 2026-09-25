# ScamSpotter Outlook add-in

## Pilot

1. Host `public/integrations/outlook/taskpane.html` en `taskpane.js` op HTTPS.
2. Configureer de publieke ScamSpotter-URL in `manifest.xml` wanneer de omgeving anders is.
3. Maak een Business-organisatie via `/admin/business` of met `bin/create-business-org.php`.
4. Download `/integrations/outlook/manifest.xml`; dit XML-bestand is de registratiebeschrijving voor Outlook, niet een uitvoerbaar programma.
5. Test handmatig via Outlook → **My add-ins** → **Add a custom add-in** → **Add from File**, of deploy centraal via Microsoft 365 admin center → **Settings → Integrated apps → Deploy Add-in**.
6. Test in Outlook on the web en New Outlook met een klein testpubliek voordat je de add-in breed uitrolt.

Microsoft-handleidingen: [sideloaden voor tests](https://learn.microsoft.com/en-us/office/dev/add-ins/outlook/sideload-outlook-add-ins-for-testing) en [centrale deployment](https://learn.microsoft.com/en-us/microsoft-365/admin/manage/manage-deployment-of-add-ins?view=o365-worldwide).

## Rechten

Het manifest vraagt `ReadItem`. De add-in leest onderwerp, afzender en body van het huidige bericht, stuurt de inhoud naar de ScamSpotter Business API en toont de analyse. Er is geen automatische mailboxscan, Graph application permission of mailwijziging.

## Data

Een check slaat standaard geen volledige body op. Er worden een hash, redacted excerpt, sender domain, classificatie, matchinformatie en retentiedatum opgeslagen. `Meld als verdacht` maakt expliciet een organisatie-rapport aan.

## API

- `GET /api/v1/business/session`
- `POST /api/v1/business/login`
- `POST /api/v1/business/check`
- `POST /api/v1/business/report`
- `POST /api/v1/business/feedback`

De Outlook-pilot gebruikt de Business-sessie en CSRF-token. Server-to-server clients kunnen een organisatiegebonden Bearer-key gebruiken; de key wordt alleen als hash opgeslagen. Optionele organisatiecontext wordt tenantgebonden als aanvullend signaal meegestuurd.
