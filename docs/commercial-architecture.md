# ScamSpotter Commercial Architecture

## Productgrenzen

- **Free:** publieke checker, kennisbank, waarschuwingen en vrijwillige meldingen.
- **Business:** handmatige Outlook-checks, expliciete employee reports, organisatie-dashboard en tenantgebonden trends.
- **Protect:** toekomstige multi-stage automatische mailanalyse; niet actief in deze versie.
- **API/Intelligence Feed/MSP:** toekomstige kanalen bovenop dezelfde analyse-engine.

## Analysepad

```text
channel adapter → OrganizationContext → ScamAnalysisOrchestrator
→ lokale knowledge-base matching → optionele AI-suggestie → channel result
```

De publieke `checks` en `submissions` blijven consumententabellen. Zakelijke data gebruikt `organizations`, `organization_memberships`, `microsoft_tenants`, `organization_analysis_runs`, `organization_checks`, `organization_reports`, `organization_events` en `usage_events`.

## Microsoft 365

De eerste Outlook-add-in leest alleen het huidige item via Office.js met `ReadItem`. Er is geen brede Graph-mailboxpermission nodig voor het handmatige pilotpad. De add-in kan later worden gekoppeld aan Microsoft Entra ID en tenantconsent. Automatische monitoring is bewust een aparte fase met Graph change notifications, queueing, privacybeleid en strengere permissions.

## Tenantisolatie

Elke zakelijke query moet de organisatiecontext uit een geauthenticeerde sessie, API-key of Entra-token halen. Een organisatie-ID uit een URL is nooit voldoende. Publieke scamrecords zijn globaal; klantregels en klantdata blijven tenantgebonden.

## Privacy-defaults

- Geen volledige mailbody in standaardrapportage.
- Redacted excerpt, hashes, afzenderdomein en afgeleide indicators zijn de standaardopslag.
- Volledige inhoud alleen na expliciete melding en met configureerbare retentie.
- Geen message body in logs, analytics of publieke knowledge-base records.
- AI-output is een voorstel en geen publicatiebeslissing.
- Zakelijke checks, loginpogingen en feedback zijn rate-limited; de dagelijkse retention-job ruimt verlopen checks, runs en oude operationele data op.

## Pricing hypothesis

Start met organisatiebundels met actieve gebruikersbanden en fair-use checks. Gebruik €39/€79/€149 eventueel als design-partnerprijzen; herprijs na pilots op basis van supporttijd, activatie en retentie. API- en Protect-usage kunnen later apart worden gemeten.

De applicatie heeft hiervoor al een plan-/usage-laag (`account_subscriptions`, `PlanService`, `usage_events`) zonder betaalprovider. Daarmee kan een pilotplan zichtbaar en meetbaar worden gemaakt voordat facturatie wordt gekoppeld.

Checks worden per organisatieplan begrensd op maandbasis; de rate limiter blijft daarnaast de operationele bescherming per uur. Een bereikt planlimiet blokkeert alleen nieuwe zakelijke analyses en heeft geen effect op de gratis publieke checker.

De Business-pijplijn gebruikt standaard eerst lokale indicatoren en vraagt alleen bij onzekere uitkomsten aanvullende AI (`BUSINESS_AI_CHECK_MODE=uncertain`). Daarmee blijft een Outlook-pilot voorspelbaar in kosten; `AI_CHECK_MODE` voor de publieke checker blijft hiervan losgekoppeld.
