<?php
declare(strict_types=1);

namespace App\Services;

final class OpenAIService
{
    /** @var array{input_tokens?: int, output_tokens?: int} */
    private array $lastUsage = [];

    public function enabled(): bool
    {
        return (string) env('OPENAI_API_KEY', '') !== '';
    }

    /** @return array{input_tokens?: int, output_tokens?: int} */
    public function lastUsage(): array
    {
        return $this->lastUsage;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<array<string, mixed>> $candidates
     * @return array<string, mixed>|null
     */
    public function classify(array $source, array $candidates = []): ?array
    {
        if (!$this->enabled() || !function_exists('curl_init')) {
            return null;
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'classification' => ['type' => 'string', 'enum' => ['duplicate', 'irrelevant', 'existing_scam', 'new_alert', 'new_variant_candidate', 'new_type_candidate', 'update_existing']],
                'family' => ['type' => ['string', 'null']],
                'type' => ['type' => ['string', 'null']],
                'variant' => ['type' => ['string', 'null']],
                'recognized_signals' => ['type' => 'array', 'items' => ['type' => 'string']],
                'likely_next_step' => ['type' => 'string'],
                'reasoning_summary' => ['type' => 'string'],
                'recommended_editorial_action' => ['type' => 'string'],
                'duplicate_status' => ['type' => 'string'],
                'suggested_tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['classification', 'family', 'type', 'variant', 'recognized_signals', 'likely_next_step', 'reasoning_summary', 'recommended_editorial_action', 'duplicate_status', 'suggested_tags'],
        ];
        $payload = [
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'store' => false,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Je bent een redactionele assistent voor ScamSpotter.nl. Doe geen juridische of absolute uitspraken. Gebruik uitsluitend de aangeleverde bron en kandidaten. Geef JSON terug volgens het schema. AI-output is een voorstel voor menselijke beoordeling en mag niet automatisch worden gepubliceerd.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => json_text(['source' => $source, 'known_candidates' => $candidates]),
                    ]],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'scamspotter_classification',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];
        $ch = curl_init('https://api.openai.com/v1/responses');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(5, (int) env('OPENAI_TIMEOUT', '30')),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . env('OPENAI_API_KEY', ''),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $error !== '' || $status < 200 || $status >= 300) {
            $this->log('OpenAI request failed', ['status' => $status, 'error' => $error]);
            return null;
        }
        $response = json_decode((string) $raw, true);
        $this->recordUsage(is_array($response) ? $response : []);
        $text = $this->extractText(is_array($response) ? $response : []);
        $result = is_string($text) ? json_decode($text, true) : null;
        return is_array($result) && isset($result['classification']) ? $result : null;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return array<string, mixed>|null
     */
    public function analyzeCheck(string $inputType, string $input, array $matches): ?array
    {
        if (!$this->enabled() || !function_exists('curl_init')) {
            return null;
        }
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['strong_match', 'suspicious_signals', 'possible_match', 'no_match', 'insufficient_information']],
                'family' => ['type' => ['string', 'null']],
                'type' => ['type' => ['string', 'null']],
                'variant' => ['type' => ['string', 'null']],
                'recognized_signals' => ['type' => 'array', 'items' => ['type' => 'string']],
                'likely_next_step' => ['type' => 'string'],
                'advice' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['status', 'family', 'type', 'variant', 'recognized_signals', 'likely_next_step', 'advice'],
        ];
        $payload = [
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'store' => false,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Je bent de aanvullende analyse van ScamSpotter.nl. Overdrijf zekerheid nooit. Gebruik de lokale kandidaten als context, maar verzin geen feiten. Geef uitsluitend JSON volgens het schema. Een “no_match” betekent niet veilig.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => json_text(['input_type' => $inputType, 'input' => $input, 'local_matches' => $matches]),
                    ]],
                ],
            ],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'scamspotter_checker_analysis', 'strict' => true, 'schema' => $schema]],
        ];
        $response = $this->request($payload);
        return is_array($response) && isset($response['status']) ? $response : null;
    }

    /** @param array<string, mixed> $response */
    private function extractText(array $response): ?string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }
        foreach (($response['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (isset($content['text']) && is_string($content['text'])) {
                    return $content['text'];
                }
            }
        }
        return null;
    }

    /** @param array<string, mixed> $response */
    private function recordUsage(array $response): void
    {
        $usage = $response['usage'] ?? null;
        $this->lastUsage = is_array($usage) ? [
            'input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0,
            'output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0,
        ] : [];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function request(array $payload): ?array
    {
        $ch = curl_init('https://api.openai.com/v1/responses');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(5, (int) env('OPENAI_TIMEOUT', '30')),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . env('OPENAI_API_KEY', ''), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $error !== '' || $status < 200 || $status >= 300) {
            $this->log('OpenAI checker request failed', ['status' => $status, 'error' => $error]);
            return null;
        }
        $response = json_decode((string) $raw, true);
        $this->recordUsage(is_array($response) ? $response : []);
        $text = $this->extractText(is_array($response) ? $response : []);
        $decoded = is_string($text) ? json_decode($text, true) : null;
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $context */
    private function log(string $message, array $context = []): void
    {
        $line = sprintf("[%s] %s %s\n", date('c'), $message, json_text($context));
        file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
