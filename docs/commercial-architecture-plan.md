# ScamSpotter Commercial Architecture Plan

## 1. Executive summary

ScamSpotter blijft een gratis, neutrale consumentenbron. Het betaalde product verkoopt geen angst en vervangt geen Microsoft Defender; het voegt een menselijke uitleglaag toe rond social engineering, scamcampagnes en vervolgstappen.

De aanbevolen volgorde is:

1. valideer de Outlook-pilot met handmatige, expliciete checks;
2. meet gebruik, feedback, retentie en AI-kosten;
3. koppel pas daarna Entra ID, facturatie en automatische Protect-verwerking.

De huidige applicatie deelt één `ScamAnalysisOrchestrator` tussen publieke checks, Business en toekomstige API-kanalen. Businessdata is tenantgebonden en wordt niet automatisch publiek.

## 2. Productfamilie

| Product | Doel | Kernwaarde |
|---|---|---|
| ScamSpotter Free | consumenten | checker, kennisbank, alerts en meldingen |
| ScamSpotter Business | organisaties | medewerkers laten begrijpen welke truc wordt gebruikt |
| ScamSpotter for Microsoft 365 | Business-kanaal | handmatige Outlook-check en expliciete melding |
| ScamSpotter Protect | later | multi-stage analyse van een verdachte subset van inkomende mail |
| ScamSpotter API | later uitbreiden | machine-to-machine analyse voor MSP’s en software |
| Intelligence Feed | later uitbreiden | gestructureerde campagnes, varianten en indicatoren |
| ScamSpotter MSP | later | één dienstverlener boven meerdere klantorganisaties |

## 3. Free versus betaald

| Mogelijkheid | Free | Business |
|---|---:|---:|
| Publieke checker en uitleg | ✓ | ✓ |
| Scam-encyclopedie en actuele alerts | ✓ | ✓ |
| Vrijwillige publieke melding | ✓ | — |
| Outlook add-in | — | ✓ |
| Organisatiegebonden checks | — | ✓ |
| Teamleden en rollen | — | ✓ |
| Centrale meldingen en opvolging | — | ✓ |
| Tenantgebonden contextregels | — | ✓ |
| Feedback- en pilotmetrics | — | ✓ |
| API-key en intelligence feed | — | afhankelijk van plan |
| Automatische mailboxscan | — | Protect, later |

De gratis checker wordt niet kunstmatig verzwakt. De betaalde waarde zit in organisatiebeheer, workflow, context, rapportage en integraties.

## 4. Aanbevolen eerste commerciële MVP

De MVP is een handmatige Outlook add-in met:

- `ReadItem` en geen brede mailboxrechten;
- knop **Controleer met ScamSpotter**;
- uitleg, scamtype, signalen en aanbevolen vervolgstap;
- knop **Meld als verdacht**;
- feedback **nuttig / nog niet**;
- Business-login en tenantgebonden sessie;
- dashboard met checks, meldingen, feedback, AI-usage en retentie;
- geredigeerde CSV-export voor analisten.

Er worden geen inkomende mailboxen automatisch gescand. Dat beperkt privacyrisico, Microsoft-consent, operationele complexiteit en kosten.

## 5. Microsoft 365-integratie

### Pilot

De add-in leest uitsluitend het geopende bericht via Office.js. `ReadItem` is voldoende voor de handmatige flow. De add-in schrijft niets terug naar het bericht en gebruikt geen Microsoft Graph application permission.

### Later: Entra ID

Entra ID kan Business-login vervangen of aanvullen. De token moet de tenant-id en subject bevatten; de server koppelt die waarden aan `microsoft_tenants` en een organisatie. Een organisatie-id uit een URL is nooit een autorisatiebron.

### Later: Protect

Automatische verwerking vereist een afzonderlijk consent- en privacytraject, Graph change notifications of een vergelijkbare gecontroleerde bron, queueing, policy-validatie en een cost guard. De pipeline wordt:

```text
Microsoft 365 → technische prefilter → lokale indicatoren
→ bekende fingerprints → verdachte subset → optionele AI
→ uitleg, rapportage of admin-alert
```

Nooit: iedere e-mail rechtstreeks naar een duur model sturen.

## 6. Technische architectuur

```text
public website ─┐
Outlook add-in ─┼→ OrganizationCheckService
Business web ──┤       ↓
API client ────┘  ScamAnalysisOrchestrator
                  ├─ lokale knowledge-base matching
                  ├─ organization context
                  └─ optionele structured AI suggestion
```

Kanaaladapters leveren dezelfde genormaliseerde input aan. Controllers bevatten geen duplicatie van scamlogica. AI-output blijft een gevalideerde suggestie.

De aanwezige `analysis_jobs`-queue is alleen een toekomstcontract. Er is nog geen automatische producer of Protect-worker actief.

## 7. Wat nu al is aangepast

- tenantgebonden organisaties, leden, rollen, checks, runs, meldingen en events;
- planlimieten, rate limits en AI-usage;
- Outlook manifest/task pane;
- Business API, API-key hashing en intelligence feed;
- uitnodigingen en tenantgebonden contextregels;
- retentie-instelling, CSV-export en feedbackmetrics;
- admin lifecycle-statussen `trial`, `active`, `suspended` en `closed`;
- inert queue-contract met retry/cleanup-ondersteuning;
- productie-healthcheck, linting en cPanel deploypad.

Niet nodig vóór de eerste design-partnerpilot:

- Stripe of andere betaalprovider;
- brede Graph permissions;
- automatische mailboxanalyse;
- MSP-portaal;
- screenshot/OCR;
- publieke reputation pages voor e-mail, telefoon of domein.

## 8. Multi-tenant model

De veilige hiërarchie is:

```text
commercial_account
└── organization
    ├── organization_memberships → business_users
    ├── microsoft_tenants
    ├── organization_rules
    ├── organization_checks / analysis_runs
    ├── organization_reports / feedback
    └── organization_events / usage_events
```

Elke query filtert op de organisatie uit een gevalideerde sessie, API-key of Entra-token. Publieke scamfamilies, types, varianten en alerts zijn globaal; organisatiecontext en bedrijfsdata zijn dat niet.

MSP-relaties horen op accountniveau (`msp_relationships`) en mogen nooit tenantdata door alleen een klant-id zichtbaar maken. Een MSP-rol krijgt later expliciete customer-scopes en audit logging.

## 9. Prijsmodel

Advies: organisatiebundel met actieve gebruikersband, inbegrepen fair-use checks en later een aparte API/Protect-meter. Puur per gebruiker straft een pilotorganisatie met veel lezers maar weinig checks; puur per check maakt omzet onvoorspelbaar.

De voorgestelde €39 / €79 / €149 zijn bruikbare design-partnerhypotheses, geen definitieve marktprijs. Herprijs na pilots op basis van:

- geactiveerde organisaties en gebruikers;
- supporttijd per organisatie;
- checks per actieve gebruiker;
- matchkwaliteit en retentie;
- AI-kosten per betaalde organisatie.

Enterprise en MSP krijgen maatwerk met minimumcommitment, onboarding en duidelijke data-/supportgrenzen.

## 10. Pilotplan

### Fase A — publiek

Meet echte checker-activiteit, lokale matches, AI-aanvullingen, kosten, meldingen en feedback.

### Fase B — eigen organisatie

Gebruik de Outlook add-in met synthetische of toegestane testmails. Meet activatie, nuttige uitleg, foutpositieven, herhaalgebruik en beheerlast.

### Fase C — 5–10 design partners

Start klein, met expliciete bewaartermijn, DPA-proces, testgroep en één organisatiebeheerder. Bied geen automatische mailboxscan aan als voorwaarde voor deelname.

### Fase D — betaald

Converteer alleen organisaties die aantoonbaar terugkomen, rapportages gebruiken en bereid zijn te betalen voor workflowwaarde.

## 11. Privacy en security

- standaard geen volledige mailbody in rapportages;
- hashes, domeinen, redacted excerpts en afgeleide signalen als standaard;
- configureerbare retentie, standaard 30 dagen in de pilot;
- TLS, HttpOnly/SameSite-sessies, CSRF, prepared statements en output escaping;
- login-, check- en feedback-rate-limits;
- API-keys alleen gehasht opslaan en één keer tonen;
- geen secrets in browser, logs of analytics;
- AI als subprocessor expliciet documenteren in DPA/privacyinformatie;
- tenantisolatie testen met negatieve autorisatiecases;
- audit events voor status, exports, contextregels, keys en uitnodigingen;
- uploads en toekomstige maildata buiten publiek uitvoerbare paden;
- verwijdering en export per organisatie kunnen uitvoeren zonder publieke content te wijzigen.

## 12. Kosten en marge

Kostenbeheersing:

1. normaliseer en redacteer vóór opslag;
2. match eerst lokaal;
3. vraag Business-AI standaard alleen bij onzekere lokale uitkomst;
4. begrens maandchecks en uur-rate-limits;
5. sla tokens/modelmetadata op voor factuurcontrole;
6. laat Protect pas na technische prefilteren dure analyse doen;
7. gebruik retentie en geen onnodige attachments;
8. maak API- en Protect-usage later afzonderlijk factureerbaar.

Bij 10, 100 en 1.000 organisaties moeten de belangrijkste operationele cijfers zijn: checks per organisatie, AI-aanvullingspercentage, tokens per check, storage, supporttijd en foutpercentages. Geen modelkosten worden publiek als schijnpreciese europrijs gepresenteerd.

## 13. Metrics

Pilot-kernmetrics:

- geactiveerde organisaties;
- actieve medewerkers en herhaalgebruik;
- checks, meldingen en feedback;
- nuttige matches versus `not_useful`;
- false-positive/false-negative feedback;
- lokale match versus AI-aanvulling;
- tijd tot opvolging van een melding;
- AI-tokens en kosten per organisatie;
- pilot-to-paid conversie.

Later: MRR, churn, ARPA, campaign clustering, MSP expansion en API-retentie.

## 14. Roadmap

### Nu

Stabiliseer publieke content, Business pilot, tenantisolatie, retentie, feedback, exports, logging en kostenmeting.

### Next

Verbeter Outlook onboarding, voeg Entra ID met minimale scopes toe, introduceer betaalbare planadministratie en test de add-in bij design partners.

### Later

Protect prefilter/queue-worker, Graph notifications, admin alerts, API quotas, intelligence feed deliveries en MSP customer-scoping.

### Veel later

OCR/screenshotanalyse, browser extension, e-mail forwarding, mobiele apps, WhatsApp-integratie en meertaligheid.

## 15. Risico’s

| Risico | Maatregel |
|---|---|
| ScamSpotter wordt als spamfilter gezien | positioneer uitleg en human-layer protection |
| Te veel AI-kosten | local-first, uncertain-only, quota en tokenmeting |
| Privacybezwaar bij mailanalyse | expliciete user action, korte retentie en redactie |
| Tenantlek | server-side scope uit auth-context en negatieve tests |
| Lage employee-adoptie | uitleg in Outlook, één klik en meetbare feedback |
| Te vroege overbouw | geen Protect/Graph/MSP vóór pilotbewijs |
| Brandvertrouwen daalt | geen pay-to-remove, sponsored classificaties of fear upsell |

## 16. Duidelijke aanbeveling

Bouw en valideer nu de handmatige Outlook Business-pilot, inclusief feedback, retentie, exports en kostenmeting. Gebruik de bestaande gedeelde analyse-service en laat de publieke checker gratis en onafhankelijk.

Bouw nu niet: automatische mailboxscan, brede Graph-permissions, Stripe, MSP-portaal, OCR of een publieke reputatiedatabase. Activeer die pas na aantoonbare pilotvraag, privacybesluit, Microsoft-consent en een onderbouwd kostenmodel.
