# ScamSpotter Outlook add-in

## Pilot

1. Host `public/integrations/outlook/taskpane.html` en `taskpane.js` op HTTPS.
2. Configureer de publieke ScamSpotter-URL in `manifest.xml` wanneer de omgeving anders is.
3. Maak een Business-organisatie met `bin/create-business-org.php`.
4. Sideload het manifest voor ontwikkeling of deploy het via Microsoft 365 Admin Center → Integrated apps.
5. Test in Outlook on the web en New Outlook voordat de add-in breed wordt uitgerold.

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

De Outlook-pilot gebruikt de Business-sessie en CSRF-token. Server-to-server clients kunnen een organisatiegebonden Bearer-key gebruiken; de key wordt alleen als hash opgeslagen.
