# ScamSpotter.nl

ScamSpotter.nl is een server-rendered PHP/MySQL platform voor het herkennen, uitleggen en redactioneel volgen van digitale oplichting.

## Stack

- PHP 8.2+ (getest met PHP 8.5)
- MySQL 8+ of MariaDB 10.6+
- PDO met native prepared statements
- HTML5, CSS en bescheiden vanilla JavaScript
- Geen Node.js nodig in runtime

## Architectuur

```text
app/
  Controllers/       HTTP flows voor publiek, checker, meldingen en beheer
  Repositories/      PDO data access
  Services/          lokale matching, OpenAI, SEO en analytics-hooks
  Http/              request/router/response
  Support/           .env en veilige template helpers
database/
  migrations/        genormaliseerd MySQL-schema
  seeders/           realistische Nederlandse demo-/startdata
public/              document root, front controller en assets
resources/views/     server-rendered templates
bin/                 migration, seed en admin CLI
cron/                dagelijkse bronimport met lock en review queue
storage/             niet-publieke uploads, cache en logs
```

De inhoudshiërarchie is: **scamfamilie → scamtype → scamvariant → actuele waarschuwing**. Een check wordt apart opgeslagen van een expliciete gebruikersmelding. Nieuwe bron- en AI-items gaan altijd naar menselijke review.

## Lokale installatie

1. Maak een database en databasegebruiker aan.
2. Kopieer configuratie:

   ```bash
   cp .env.example .env
   ```

3. Vul minimaal `DB_*` en een unieke `APP_KEY` in.
4. Installeer optioneel Composer voor IDE/autoload-integratie:

   ```bash
   composer install
   ```

5. Voer schema en startdata uit:

   ```bash
   php bin/migrate.php
   php bin/seed.php
   ```

6. Maak een admin aan:

   ```bash
   php bin/create-admin.php admin@example.com "ScamSpotter beheer" 'gebruik-een-lang-uniek-wachtwoord'
   ```

7. Start lokaal met de ingebouwde server:

   ```bash
   php -S 127.0.0.1:8080 -t public public/index.php
   ```

   Open daarna `http://127.0.0.1:8080`.

## Productie

- Stel de webserver-documentroot in op `public/`; routeer niet-bestaande bestanden naar `public/index.php`.
- Gebruik HTTPS en zet `APP_URL` op de publieke URL.
- Geef PHP schrijfrechten op `storage/cache`, `storage/logs` en `storage/uploads`; laat deze directories niet publiek uitvoeren.
- Gebruik een aparte productie-database en een productie-`APP_KEY`.
- Zet `APP_DEBUG=false`.
- Maak periodieke database- en storage-backups.
- Zet admin-authenticatie achter sterke wachtwoorden en aanvullende hostingbeveiliging waar beschikbaar.

## Dagelijkse cron

Plan bijvoorbeeld:

```cron
15 3 * * * cd /var/www/scamspotter && /usr/bin/php cron/daily.php >> storage/logs/cron-cli.log 2>&1
```

De job gebruikt een filesystem-lock, registreert `cron_runs`, verwerkt alleen actieve geverifieerde bronnen, voorkomt duplicaten op URL/hash/externe ID en maakt review-items. Bronfeeds moeten als vertrouwde HTTPS-URL in de database staan. De eerste seedbronnen hebben bewust geen live feed-URL; voeg die per omgeving gecontroleerd toe.

## OpenAI

OpenAI is optioneel. Configureer in `.env`:

```dotenv
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=30
AI_CHECK_MODE=all
```

De integratie gebruikt de Responses API met Structured Outputs en `store=false`. De API-key komt nooit in de browser. Output wordt server-side gevalideerd en opgeslagen als voorstel; publicatie gebeurt niet automatisch.

Zonder API-key blijven de lokale indicator-matching, checker-resultaten en cron-review-architectuur werken.

## ScamSpotter Business

De eerste Business-pilot gebruikt handmatige Outlook-controles. De publieke site en Business-omgeving delen de ScamSpotter-kennisbank, maar zakelijke checks en meldingen staan in tenantgebonden tabellen en worden niet automatisch publiek gemaakt.

Maak voor een lokale pilot een organisatie en Business-owner aan:

```bash
php bin/create-business-org.php medewerker@example.com "Naam medewerker" "Voorbeeld BV" 'gebruik-een-lang-uniek-wachtwoord' voorbeeld-bv
```

Business-login: `/business/login`. De interne Outlook-add-in staat in `public/integrations/outlook/manifest.xml` en gebruikt `ReadItem`; automatische mailboxanalyse is niet ingeschakeld.

Maak alleen voor een vertrouwde machine-to-machine-client een API-key aan. De volledige key wordt één keer getoond:

```bash
php bin/create-business-api-key.php voorbeeld-bv "Pilot API"
```

Zie `docs/commercial-architecture.md` en `docs/outlook-add-in.md` voor de commerciële grenzen, dataminimalisatie, tenantisolatie en Microsoft 365-onboarding.

## Publieke routes

- `/` — homepage
- `/check` en `/check/verdachte-mail` — checker en campagneklare landingsroutes
- `/oplichting/` — encyclopedie
- `/oplichting/{slug}` — familie, type of variant
- `/waarschuwingen/` en `/waarschuwingen/{slug}` — actuele alerts
- `/zoeken` — site search
- `/melden/` — expliciete gebruikersmelding
- `/over-scamspotter/`, `/over-scamspotter/werkwijze/`, `/bronnen/`
- `/sitemap.xml`, `/robots.txt`

## Beheer

Login op `/admin/login`. Het dashboard toont taxonomie, alerts, bronstatus, review queue, meldingen en cronruns. Review-acties en meldingsstatussen worden in `audit_log` vastgelegd.

## Security- en privacy-basics

- PDO prepared statements en escaping in templates.
- CSRF-token op state-changing formulieren.
- Secure, HttpOnly, SameSite-sessies.
- `password_hash()`/`password_verify()` voor adminaccounts.
- Checker rate limiting per gehashte IP en tijdelijke retentie.
- Uploads buiten `public/`, MIME- en groottelimieten, willekeurige bestandsnamen.
- Geen automatische fetch van door gebruikers aangeleverde URL's.
- OpenAI-output is onbetrouwbare suggestie; bronfeit, redactie, melding en AI blijven onderscheiden.

## Ontwikkelnotities

De visuele richting volgt de ScamSpotter-referentie: diep navy `#0F2D4A`, oranje `#FF6B00`, koele lichte achtergronden, Inter/system sans-serif, afgeronde maar zakelijke componenten en rustige editorial spacing. Logo's staan als originele SVG's in `public/assets/icons/`.

Voor een productie-lancering moeten nog per omgeving worden ingevuld: juridische contactgegevens, bewaartermijnen, live bronfeeds, inhoudelijke redactiecontrole, externe consentprovider en eventuele CDN/image pipeline.
