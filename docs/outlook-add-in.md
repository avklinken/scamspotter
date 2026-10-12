# ScamSpotter Outlook add-in

## Pilot

1. Host `public/integrations/outlook/taskpane.html` en `taskpane.js` op HTTPS.
2. Configureer Microsoft Entra SSO zoals hieronder; het manifest wordt daarna dynamisch gegenereerd.
3. Maak een Business-organisatie via `/admin/business` of met `bin/create-business-org.php`.
4. Download `/integrations/outlook/manifest.xml`; dit XML-bestand is de registratiebeschrijving voor Outlook, niet een uitvoerbaar programma.
5. Test handmatig via Outlook → **My add-ins** → **Add a custom add-in** → **Add from File**, of deploy centraal via Microsoft 365 admin center → **Settings → Integrated apps → Deploy Add-in**.
6. Test vanuit de primaire Microsoft 365/Exchange-mailbox. POP/IMAP-mailboxen en sommige gedeelde mailbox-contexten activeren Outlook-add-ins niet.
7. Zet de leesvensterweergave op rechts of onder, open daarna een e-mail. De knop **Controleer met ScamSpotter** staat op de berichtwerkbalk of onder **Apps**.
8. Test in Outlook on the web en New Outlook met een klein testpubliek voordat je de add-in breed uitrolt.

Microsoft-handleidingen: [sideloaden voor tests](https://learn.microsoft.com/en-us/office/dev/add-ins/outlook/sideload-outlook-add-ins-for-testing) en [centrale deployment](https://learn.microsoft.com/en-us/microsoft-365/admin/manage/manage-deployment-of-add-ins?view=o365-worldwide).

## New Outlook op Mac

Handmatig sideloaden is een testmethode. In sommige New Outlook-builds kan een handmatig toegevoegde custom add-in na het sluiten van het add-invenster of Outlook opnieuw uit de lijst verdwijnen. Voor een blijvende installatie moet een Microsoft 365-beheerder het XML-manifest centraal uitrollen naar de gebruiker of een groep.

Als de add-in wel onder **My add-ins** staat maar geen knop in een geopende e-mail toont, is meestal de oude manifestversie gecachet of is het bericht geopend vanuit een niet-ondersteunde mailboxcontext. Verwijder de oude ScamSpotter-entry, sluit Outlook volledig af, download het actuele manifest opnieuw, open [Outlook-add-ins testen](https://aka.ms/olksideload), voeg het bestand opnieuw toe en open een bericht in de primaire Microsoft 365-mailbox. Bij centrale deployment moet de beheerder de nieuwe manifestversie opnieuw uploaden.

De huidige add-in gebruikt een add-in-only XML-manifest, omdat het Microsoft 365 unified manifest niet wordt ondersteund in Outlook op Mac. De `VersionOverrides`-sectie definieert de knop in Message Read; de manifestversie is `1.0.7.0` en gebruikt PNG-iconen die door Outlook worden ondersteund. Het manifest bevat de SSO-configuratie uit de serveromgeving en bevat geen vaste client-id in Git.

## Microsoft 365 SSO

De add-in gebruikt de Microsoft 365-identiteit uit Outlook en toont bij ingeschakelde SSO geen ScamSpotter-wachtwoordformulier. De server valideert de Entra-token, controleert de gekoppelde tenant en bindt de Microsoft-identiteit eenmalig aan het bestaande Business-account op basis van het werk-mailadres. Gebruik daarom voor iedere medewerker een eigen Business-lid en een eigen Microsoft 365-account; gedeelde Microsoft 365-accounts blijven niet individueel herleidbaar.

1. Registreer een Entra-app voor de add-in, configureer de Application ID URI als `api://scamspotter.nl/<client-id>` en richt de add-in-SSO/API-scope `access_as_user` in.
2. Zet op de server `MICROSOFT_ENTRA_ENABLED=true`, `MICROSOFT_ENTRA_CLIENT_ID=<client-id>`, `MICROSOFT_ENTRA_RESOURCE=api://scamspotter.nl/<client-id>` en optioneel `MICROSOFT_ENTRA_TENANT_ID=<tenant-id>`.
3. Koppel de tenant aan de Business-organisatie: `php bin/link-microsoft-tenant.php <organisatie-id> <tenant-id> "Naam tenant"`.
4. Download het manifest opnieuw en rol het centraal uit. Outlook gebruikt daarna de Microsoft 365-aanmelding; de add-in gebruikt niet het e-mailadres van de geopende mail als identiteit.

Microsoft beschrijft de [WebApplicationInfo/SSO-configuratie](https://learn.microsoft.com/en-us/javascript/api/manifest/webapplicationinfo?view=word-js-preview) en de [Office SSO-tokenflow](https://learn.microsoft.com/en-us/office/dev/add-ins/develop/use-sso-to-get-office-signed-in-user-token). Zet moderne authenticatie aan voor de Microsoft 365-tenant.

## Rechten

Het manifest vraagt `ReadItem`. De add-in leest onderwerp, afzender en body van het huidige bericht, stuurt de inhoud naar de ScamSpotter Business API en toont de analyse. Er is geen automatische mailboxscan, Graph application permission of mailwijziging.

## Data

Een check slaat standaard geen volledige body op. Er worden een hash, redacted excerpt, sender domain, classificatie, matchinformatie en retentiedatum opgeslagen. `Meld als verdacht` maakt expliciet een organisatie-rapport aan.

## API

- `GET /api/v1/business/session`
- `POST /api/v1/business/login`
- `POST /api/v1/business/sso/microsoft` — Microsoft 365 SSO-token uit Outlook.
- `POST /api/v1/business/check`
- `POST /api/v1/business/report`
- `POST /api/v1/business/feedback`

De Outlook-pilot gebruikt de Business-sessie en CSRF-token. Server-to-server clients kunnen een organisatiegebonden Bearer-key gebruiken; de key wordt alleen als hash opgeslagen. Optionele organisatiecontext wordt tenantgebonden als aanvullend signaal meegestuurd.
