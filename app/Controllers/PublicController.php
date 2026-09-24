<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Repositories\AlertRepository;
use App\Repositories\ScamRepository;
use App\Repositories\SourceRepository;
use App\Services\SeoService;

final class PublicController extends Controller
{
    private ScamRepository $scams;
    private AlertRepository $alerts;
    private SourceRepository $sources;

    public function __construct(array $app)
    {
        parent::__construct($app);
        $this->scams = new ScamRepository($this->db());
        $this->alerts = new AlertRepository($this->db());
        $this->sources = new SourceRepository($this->db());
    }

    public function home(Request $request): void
    {
        $this->render('public/home', [
            'seo' => $this->seo([
                'title' => 'ScamSpotter.nl — Herken de truc. Blijf één stap voor.',
                'description' => 'Herken verdachte berichten, websites, telefoontjes en e-mails. ScamSpotter helpt je oplichting begrijpen en voorkomen.',
                'json_ld' => $this->organizationSchema(),
            ]),
            'families' => $this->scams->families(),
            'alerts' => $this->alerts->published([], 3),
            'types' => array_slice($this->scams->types(), 0, 6),
        ]);
    }

    public function encyclopedia(Request $request): void
    {
        $this->render('public/encyclopedia', [
            'seo' => $this->seo([
                'title' => 'Alle oplichtingstrucs — ScamSpotter.nl',
                'description' => 'Een helder overzicht van bekende oplichtingstrucs, scamtypes en herkenbare varianten.',
            ]),
            'families' => $this->scams->families(),
            'types' => $this->scams->types(),
            'variants' => $this->scams->variants(),
        ]);
    }

    public function scam(Request $request, string $slug): void
    {
        $item = $this->scams->findBySlug($slug);
        if ($item === null) {
            $this->render('public/404', ['seo' => $this->seo(['title' => 'Niet gevonden — ScamSpotter.nl'])], 404);
            return;
        }
        $name = (string) $item['name'];
        $this->render('public/scam-detail', [
            'seo' => $this->seo([
                'title' => $name . ' — ScamSpotter.nl',
                'description' => (string) ($item['summary'] ?? 'Lees hoe deze oplichtingstruc werkt en waar je op let.'),
                'json_ld' => $this->articleSchema($name, (string) ($item['summary'] ?? ''), url('/oplichting/' . $slug)),
                'breadcrumbs' => [['name' => 'Home', 'url' => url('/')], ['name' => 'Oplichtingstrucs', 'url' => url('/oplichting')], ['name' => $name, 'url' => url('/oplichting/' . $slug)]],
            ]),
            'item' => $item,
        ]);
    }

    public function alerts(Request $request): void
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'family' => (string) $request->query('family', ''),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
            'date' => (string) $request->query('date', ''),
            'source' => (string) $request->query('source', ''),
        ];
        $this->render('public/alerts', [
            'seo' => $this->seo([
                'title' => 'Actuele scamwaarschuwingen — ScamSpotter.nl',
                'description' => 'Bekijk actuele en recente waarschuwingen over digitale oplichting in Nederland.',
            ]),
            'alerts' => $this->alerts->published($filters),
            'filters' => $filters,
            'families' => $this->scams->families(),
            'types' => $this->scams->types(),
            'sources' => $this->sources->active(),
        ]);
    }

    public function alert(Request $request, string $slug): void
    {
        $alert = $this->alerts->findPublished($slug);
        if ($alert === null) {
            $this->render('public/404', ['seo' => $this->seo(['title' => 'Niet gevonden — ScamSpotter.nl'])], 404);
            return;
        }
        $this->render('public/alert-detail', [
            'seo' => $this->seo([
                'title' => (string) $alert['title'] . ' — ScamSpotter.nl',
                'description' => (string) $alert['summary'],
                'og_type' => 'article',
                'json_ld' => $this->articleSchema((string) $alert['title'], (string) $alert['summary'], url('/waarschuwingen/' . $slug)),
                'breadcrumbs' => [['name' => 'Home', 'url' => url('/')], ['name' => 'Waarschuwingen', 'url' => url('/waarschuwingen')], ['name' => (string) $alert['title'], 'url' => url('/waarschuwingen/' . $slug)]],
            ]),
            'alert' => $alert,
        ]);
    }

    public function search(Request $request): void
    {
        $query = trim((string) $request->query('q', ''));
        $this->render('public/search', [
            'seo' => $this->seo([
                'title' => $query !== '' ? 'Zoekresultaten voor ' . $query . ' — ScamSpotter.nl' : 'Zoeken — ScamSpotter.nl',
                'description' => 'Zoek in scams, varianten, waarschuwingen en uitleg van ScamSpotter.',
                'robots' => $query !== '' ? 'noindex,follow' : 'index,follow',
            ]),
            'query' => $query,
            'results' => $query !== '' ? $this->scams->search($query) : [],
        ]);
    }

    public function sources(Request $request): void
    {
        $this->render('public/sources', [
            'seo' => $this->seo([
                'title' => 'Onze bronnen — ScamSpotter.nl',
                'description' => 'Lees welke betrouwbare bronnen ScamSpotter gebruikt voor waarschuwingen en uitleg.',
            ]),
            'sources' => $this->sources->active(),
        ]);
    }

    public function staticPage(Request $request, string $page): void
    {
        $pages = [
            'over-scamspotter' => ['title' => 'Over ScamSpotter — ScamSpotter.nl', 'view' => 'about', 'description' => 'ScamSpotter helpt je verdachte berichten en digitale oplichting beter begrijpen.'],
            'privacy' => ['title' => 'Privacy — ScamSpotter.nl', 'view' => 'privacy', 'description' => 'Lees hoe ScamSpotter omgaat met persoonsgegevens en checker-input.'],
            'cookies' => ['title' => 'Cookies — ScamSpotter.nl', 'view' => 'cookies', 'description' => 'Informatie over cookies en optionele metingen op ScamSpotter.nl.'],
            'contact' => ['title' => 'Contact — ScamSpotter.nl', 'view' => 'contact', 'description' => 'Neem contact op voor correcties, vragen of redactionele opmerkingen.'],
        ];
        $config = $pages[$page] ?? null;
        if ($config === null) {
            $this->render('public/404', ['seo' => $this->seo(['title' => 'Niet gevonden — ScamSpotter.nl'])], 404);
            return;
        }
        $this->render('public/' . $config['view'], [
            'seo' => $this->seo(['title' => $config['title'], 'description' => $config['description']]),
        ]);
    }

    public function methodology(Request $request): void
    {
        $this->render('public/methodology', [
            'seo' => $this->seo([
                'title' => 'Onze werkwijze — ScamSpotter.nl',
                'description' => 'Lees hoe ScamSpotter bronnen, lokale kennis en AI combineert met menselijke redactie.',
            ]),
        ]);
    }

    public function sitemap(Request $request): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $urls = [url('/'), url('/oplichting/'), url('/waarschuwingen/'), url('/melden/'), url('/bronnen/')];
        foreach ($this->scams->types() as $type) {
            $urls[] = url('/oplichting/' . $type['slug']);
        }
        foreach ($this->scams->variants() as $variant) {
            $urls[] = url('/oplichting/' . $variant['slug']);
        }
        foreach ($this->alerts->published([], 1000) as $alert) {
            $urls[] = url('/waarschuwingen/' . $alert['slug']);
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_unique($urls) as $location) {
            echo '<url><loc>' . e($location) . '</loc></url>';
        }
        echo '</urlset>';
    }

    public function robots(Request $request): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /check/result\nSitemap: " . url('/sitemap.xml') . "\n";
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function seo(array $data): array
    {
        return (new SeoService())->metadata($data);
    }

    /** @return array<string, mixed> */
    private function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'ScamSpotter.nl',
            'url' => url('/'),
            'logo' => asset('icons/logo-icon.svg'),
            'description' => 'Nederlandse informatie over digitale oplichting en scamwaarschuwingen.',
        ];
    }

    /** @return array<string, mixed> */
    private function articleSchema(string $name, string $description, string $canonical): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $name,
            'description' => $description,
            'url' => $canonical,
            'publisher' => ['@type' => 'Organization', 'name' => 'ScamSpotter.nl'],
        ];
    }
}
