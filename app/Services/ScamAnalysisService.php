<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ScamRepository;

final class ScamAnalysisService
{
    public function __construct(private ScamRepository $scams)
    {
    }

    /** @return array<string, mixed> */
    public function analyze(string $inputType, string $input): array
    {
        $normalized = $this->normalize($input);
        $candidates = $this->scams->matchCandidates($normalized);
        $matches = [];
        foreach ($candidates as $candidate) {
            $indicators = $candidate['indicators'] ?? [];
            $score = array_sum(array_map(static fn (array $indicator): float => (float) ($indicator['weight'] ?? 0), $indicators));
            $matches[] = [
                'variant_id' => $candidate['variant']['id'],
                'variant' => $candidate['variant'],
                'score' => round($score, 2),
                'status' => $this->scoreStatus($score),
                'indicators' => $indicators,
            ];
        }
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $top = $matches[0] ?? null;

        return [
            'input_type' => $inputType,
            'normalized' => $normalized,
            'status' => $this->overallStatus($top, count($matches)),
            'top_match' => $top,
            'matches' => array_slice($matches, 0, 5),
            'has_match' => $top !== null,
            'input_excerpt' => $this->excerpt($input),
            'redacted_excerpt' => $this->redact($input),
        ];
    }

    public function normalize(string $input): string
    {
        $input = trim(strip_tags($input));
        $input = preg_replace('/\s+/u', ' ', $input) ?: $input;
        return mb_strtolower($input);
    }

    public function redact(string $input): string
    {
        $input = preg_replace('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', '[e-mailadres verwijderd]', $input) ?: $input;
        $input = preg_replace('#https?://\S+#iu', '[link verwijderd]', $input) ?: $input;
        return mb_substr(trim($input), 0, 1200);
    }

    private function excerpt(string $input): string
    {
        $input = trim(preg_replace('/\s+/u', ' ', strip_tags($input)) ?: $input);
        return mb_strlen($input) > 160 ? mb_substr($input, 0, 157) . '…' : $input;
    }

    /** @return array{code: string, label: string, explanation: string} */
    private function overallStatus(?array $top, int $count): array
    {
        if ($top === null) {
            return [
                'code' => 'no_match',
                'label' => 'Geen bekende scam-match gevonden',
                'explanation' => 'We herkennen op dit moment geen sterke overeenkomst met een bekende scam. Dat betekent niet automatisch dat het bericht betrouwbaar is.',
            ];
        }
        $score = (float) $top['score'];
        if ($score >= 8) {
            return [
                'code' => 'strong_match',
                'label' => 'Sterke match met bekende scam',
                'explanation' => 'Deze inhoud vertoont meerdere overeenkomsten met een bekende werkwijze. Stop en controleer de afzender via een officieel kanaal.',
            ];
        }
        if ($count > 1 || $score >= 4) {
            return [
                'code' => 'suspicious_signals',
                'label' => 'Meerdere verdachte signalen',
                'explanation' => 'We zien verschillende signalen die vaak voorkomen bij digitale oplichting. Deel geen gegevens en betaal niet voordat je onafhankelijk hebt gecontroleerd.',
            ];
        }
        return [
            'code' => 'possible_match',
            'label' => 'Mogelijk verdacht',
            'explanation' => 'Er is een beperkte overeenkomst met een bekende scam. Kijk vooral naar de context en de gevraagde vervolgstap.',
        ];
    }

    private function scoreStatus(float $score): string
    {
        return $score >= 8 ? 'strong_match' : ($score >= 4 ? 'suspicious_signals' : 'possible_match');
    }
}
