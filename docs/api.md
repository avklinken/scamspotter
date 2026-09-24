# ScamSpotter API v1

Zakelijke API-calls gebruiken een organisatiegebonden Bearer-key. Maak een key met:

```bash
php bin/create-business-api-key.php organisatie-slug "Naam client"
```

## Analyse

```http
POST /api/v1/analyse
Authorization: Bearer ss_live_...
Content-Type: application/json

{
  "content_type": "email",
  "subject": "Nieuwe betaalgegevens",
  "sender_email": "leverancier@example.org",
  "content": "Controleer deze factuur..."
}
```

De response bevat een status, scamfamilie/type/variant waar herkenbaar, signalen, organisatiecontext, vervolgstap en aanbevolen actie. Interne matchscores zijn geen publieke kansberekening.

## Business endpoints

- `POST /api/v1/business/check` — Outlook/business check.
- `POST /api/v1/business/report` — expliciet organisatie-rapport.
- `POST /api/v1/business/feedback` — nuttig/niet nuttig of false-positive/false-negative feedback op een check.
- `GET /api/v1/business/usage` — gebruik over de laatste 30 dagen plus planverbruik en resterende maandruimte.
- `GET /api/v1/intelligence/feed` — gepubliceerde actuele waarschuwingen en gekoppelde indicatoren voor API-clients (`since` en `limit` zijn optioneel).

API-checks zijn begrensd via `BUSINESS_CHECKS_PER_HOUR` (standaard 120 per gebruiker of API-client). De API retourneert geen volledige opgeslagen mailbody. Gebruik TLS, roteer keys en bewaar keys alleen in een secret manager of serveromgeving.

Daarnaast bewaakt ScamSpotter de maandlimiet van het organisatieplan. Bij overschrijding retourneert een analyse `429 plan_limit_reached` met de gebruikte en resterende eenheden.

Een organisatiebeheerder kan API-keys voor plannen met API-toegang aanmaken en intrekken via `/business/settings`. De volledige key wordt alleen direct na aanmaken getoond.
