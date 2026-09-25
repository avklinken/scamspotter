# ScamSpotter Outlook add-in

## Pilot

1. Host `public/integrations/outlook/taskpane.html` en `taskpane.js` op HTTPS.
2. Configureer de publieke ScamSpotter-URL in `manifest.xml` wanneer de omgeving anders is.
3. Maak een Business-organisatie via `/admin/business` of met `bin/create-business-org.php`.
4. Download `/integrations/outlook/manifest.xml`; dit XML-bestand is de registratiebeschrijving voor Outlook, niet een uitvoerbaar programma.
5. Test handmatig via Outlook → **My add-ins** → **Add a custom add-in** → **Add from File**, of deploy centraal via Microsoft 365 admin center → **Settings → Integrated apps → Deploy Add-in**.
6. Open daarna een e-mail in de leesweergave. De knop **Controleer met ScamSpotter** staat op de berichtwerkbalk of onder **Apps**.
7. Test in Outlook on the web en New Outlook met een klein testpubliek voordat je de add-in breed uitrolt.

Microsoft-handleidingen: [sideloaden voor tests](https://learn.microsoft.com/en-us/office/dev/add-ins/outlook/sideload-outlook-add-ins-for-testing) en [centrale deployment](https://learn.microsoft.com/en-us/microsoft-365/admin/manage/manage-deployment-of-add-ins?view=o365-worldwide).

## New Outlook op Mac

Handmatig sideloaden is een testmethode. In sommige New Outlook-builds kan een handmatig toegevoegde custom add-in na het sluiten van het add-invenster of Outlook opnieuw uit de lijst verdwijnen. Voor een blijvende installatie moet een Microsoft 365-beheerder het XML-manifest centraal uitrollen naar de gebruiker of een groep.

Als de add-in wel onder **My add-ins** staat maar geen knop in een geopende e-mail toont, is meestal de oude manifestversie gecachet. Verwijder de oude ScamSpotter-entry, sluit Outlook volledig af, open [Outlook-add-ins testen](https://aka.ms/olksideload), voeg het actuele bestand opnieuw toe en open een bericht. Bij centrale deployment moet de beheerder de nieuwe manifestversie opnieuw uploaden.

De huidige add-in gebruikt een add-in-only XML-manifest, omdat het Microsoft 365 unified manifest niet wordt ondersteund in Outlook op Mac. De `VersionOverrides`-sectie definieert de knop in Message Read; de manifestversie is verhoogd naar `1.0.1.0` en gebruikt PNG-iconen die door Outlook worden ondersteund.

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
