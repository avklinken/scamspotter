<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Repositories\CheckRepository;
use App\Repositories\ScamRepository;
use App\Services\MatchingService;
use App\Services\OpenAIService;
use App\Services\SeoService;

final class CheckerController extends Controller
{
    public function show(Request $request): void
    {
        $this->render('public/checker', [
            'seo' => (new SeoService())->metadata([
                'title' => 'Iets verdachts checken — ScamSpotter.nl',
                'description' => 'Check een verdacht bericht, website, e-mailadres of telefoonnummer met ScamSpotter.',
            ]),
            'activeType' => (string) $request->query('type', 'message'),
            'landing' => null,
            'errors' => [],
        ]);
    }

    public function landing(Request $request, string $landing): void
    {
        $pages = [
            'verdachte-mail' => ['type' => 'message', 'title' => 'Verdachte e-mail checken', 'intro' => 'Plak de tekst van de e-mail. Verwijder gerust persoonsgegevens voordat je hem deelt.'],
            'verdachte-sms' => ['type' => 'message', 'title' => 'Verdachte sms checken', 'intro' => 'Plak het sms-bericht en let vooral op links, betaalverzoeken en tijdsdruk.'],
            'verdachte-website' => ['type' => 'url', 'title' => 'Verdachte website checken', 'intro' => 'Controleer een link voordat je inlogt, betaalt of gegevens invult.'],
            'telefoonnummer' => ['type' => 'phone', 'title' => 'Telefoonnummer checken', 'intro' => 'Vul een nummer in en beschrijf eventueel wat de beller vroeg.'],
        ];
        $configured = $this->db()->prepare("SELECT slug, heading, intro, input_type, campaign_identifier, seo_title, meta_description, indexable FROM landing_pages WHERE slug = :slug AND status = 'published' LIMIT 1");
        $configured->execute(['slug' => $landing]);
        $databasePage = $configured->fetch();
        $config = is_array($databasePage) ? [
            'type' => (string) $databasePage['input_type'],
            'title' => (string) $databasePage['heading'],
            'intro' => (string) $databasePage['intro'],
            'campaign_identifier' => (string) ($databasePage['campaign_identifier'] ?? $landing),
            'indexable' => (bool) $databasePage['indexable'],
        ] : ($pages[$landing] ?? null);
        if ($config === null) {
            $this->render('public/404', ['seo' => (new SeoService())->metadata(['title' => 'Niet gevonden — ScamSpotter.nl'])], 404);
            return;
        }
        $this->render('public/checker', [
            'seo' => (new SeoService())->metadata([
                'title' => (string) ($databasePage['seo_title'] ?? $config['title'] . ' — ScamSpotter.nl'),
                'description' => (string) ($databasePage['meta_description'] ?? $config['intro']),
                'robots' => !empty($config['indexable']) ? 'index,follow' : 'noindex,follow',
            ]),
            'activeType' => $config['type'],
            'landing' => $config,
            'errors' => [],
        ]);
    }

    public function run(Request $request): void
    {
        if (!$request->isPost() || !verify_csrf($request->post('_csrf'))) {
            http_response_code(419);
            echo 'Ongeldige sessie. Probeer opnieuw.';
            return;
        }

        $inputType = (string) $request->post('input_type', 'message');
        $input = trim((string) $request->post('input', ''));
        $allowed = ['message', 'url', 'email', 'phone'];
        $errors = [];
        if (!in_array($inputType, $allowed, true)) {
            $errors[] = 'Kies een geldig type controle.';
        }
        if ($input === '') {
            $errors[] = 'Vul een bericht, link, e-mailadres of telefoonnummer in.';
        }
        if (mb_strlen($input) > 5000) {
            $errors[] = 'De invoer mag maximaal 5.000 tekens bevatten.';
        }
        if ($inputType === 'url' && filter_var($input, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Vul een volledige link in, bijvoorbeeld https://voorbeeld.nl.';
        }
        if ($inputType === 'email' && filter_var($input, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        }
        if ($errors !== []) {
            $this->render('public/checker', [
                'seo' => (new SeoService())->metadata(['title' => 'Iets verdachts checken — ScamSpotter.nl']),
                'activeType' => $inputType,
                'landing' => null,
                'errors' => $errors,
                'input' => $input,
            ], 422);
            return;
        }

        $ipHash = hash('sha256', $request->ip() . '|' . (string) env('APP_KEY', ''));
        $rate = $this->db()->prepare("SELECT COUNT(*) FROM checks WHERE ip_hash = :ip_hash AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $rate->execute(['ip_hash' => $ipHash]);
        if ((int) $rate->fetchColumn() >= 30) {
            $this->render('public/checker', [
                'seo' => (new SeoService())->metadata(['title' => 'Iets verdachts checken — ScamSpotter.nl']),
                'activeType' => $inputType,
                'landing' => null,
                'errors' => ['Je hebt het maximum aantal controles voor dit uur bereikt. Probeer later opnieuw.'],
                'input' => $input,
            ], 429);
            return;
        }

        $service = new MatchingService(new ScamRepository($this->db()), new CheckRepository($this->db()));
        $attribution = [];
        foreach (['gclid', 'msclkid'] as $key) {
            $value = trim((string) $request->post($key, ''));
            if ($value !== '') {
                $attribution[$key] = mb_substr($value, 0, 250);
            }
        }
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            $value = trim((string) $request->post($key, ''));
            if ($value !== '') {
                $attribution[$key] = mb_substr($value, 0, 250);
            }
        }
        $result = $service->check($inputType, $input, $request->ip(), trim((string) $request->post('campaign_identifier', '')) ?: null, $attribution);
        $ai = (new OpenAIService())->analyzeCheck($inputType, $input, $result['matches']);
        if ($ai !== null && in_array($ai['status'] ?? '', ['strong_match', 'suspicious_signals', 'possible_match', 'no_match', 'insufficient_information'], true)) {
            $statement = $this->db()->prepare("INSERT INTO ai_analyses (check_id, model, prompt_version, input_hash, classification, output_json, status)
                VALUES (:check_id, :model, 'checker-v1', :input_hash, :classification, :output_json, 'suggestion')");
            $statement->execute([
                'check_id' => $result['check_id'],
                'model' => (string) env('OPENAI_MODEL', 'gpt-4o-mini'),
                'input_hash' => hash('sha256', $input),
                'classification' => $ai['status'],
                'output_json' => json_text($ai),
            ]);
            $result['ai_analysis'] = $ai;
        }
        $this->render('public/check-result', [
            'seo' => (new SeoService())->metadata([
                'title' => 'Resultaat van je scamcheck — ScamSpotter.nl',
                'description' => 'Bekijk de signalen en praktische vervolgstappen van je ScamSpotter-check.',
                'robots' => 'noindex,follow',
            ]),
            'result' => $result,
        ]);
    }
}
