<?php
declare(strict_types=1);

namespace App\Services;

final class ScamAnalysisOrchestrator
{
    public function __construct(
        private ScamAnalysisService $local,
        private OpenAIService $ai,
    ) {
    }

    /** @return array<string, mixed> */
    public function analyze(string $inputType, string $input, string $channel = 'business'): array
    {
        $local = $this->local->analyze($inputType, $input);
        $ai = null;
        $mode = (string) env('AI_CHECK_MODE', 'all');
        $needsAi = $mode === 'all' || ($mode === 'uncertain' && $local['status']['code'] !== 'strong_match');
        if ($needsAi) {
            $ai = $this->ai->analyzeCheck($inputType, $input, $local['matches']);
        }

        return [
            'channel' => $channel,
            'input_type' => $inputType,
            'status' => $local['status'],
            'top_match' => $local['top_match'],
            'matches' => $local['matches'],
            'has_match' => $local['has_match'],
            'input_excerpt' => $local['input_excerpt'],
            'redacted_excerpt' => $local['redacted_excerpt'],
            'ai_analysis' => $ai,
            'usage' => $this->ai->lastUsage(),
        ];
    }
}
