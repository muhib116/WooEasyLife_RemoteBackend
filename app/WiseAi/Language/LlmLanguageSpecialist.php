<?php

namespace App\WiseAi\Language;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional post-Judge wording layer — never decides facts.
 */
class LlmLanguageSpecialist
{
    public function __construct(
        private LlmLanguageConfig $config,
        private LlmReplyGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $configSnapshot
     * @param  array<string, mixed>|null  $evidence
     * @return array{
     *     decision: array<string, mixed>,
     *     language_llm: array{applied: bool, model: string|null, latency_ms: int, reason: string}
     * }
     */
    public function maybeRewrite(array $decision, array $configSnapshot, ?array $evidence = null): array
    {
        $meta = [
            'applied' => false,
            'model' => null,
            'latency_ms' => 0,
            'reason' => 'skipped',
        ];

        $flags = is_array($configSnapshot['feature_flags'] ?? null)
            ? $configSnapshot['feature_flags']
            : [];
        if (empty($flags['llm_language'])) {
            $meta['reason'] = 'flag_off';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        if (! $this->config->enabled()) {
            $meta['reason'] = 'platform_off';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        $apiKey = $this->config->apiKey();
        if ($apiKey === null) {
            $meta['reason'] = 'no_key';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        $original = trim((string) ($decision['suggested_reply'] ?? ''));
        if ($original === '') {
            $meta['reason'] = 'no_reply';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        // Knowledge answers stay factual — still allow gentle wording but guard digits.
        $model = $this->config->model();
        $started = microtime(true);

        try {
            $rewrite = $this->callOpenAi($apiKey, $model, $original, $decision);
        } catch (Throwable $e) {
            $meta['reason'] = 'http_error';
            $meta['latency_ms'] = (int) round((microtime(true) - $started) * 1000);
            $meta['model'] = $model;

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        $meta['latency_ms'] = (int) round((microtime(true) - $started) * 1000);
        $meta['model'] = $model;

        if ($rewrite === null || trim($rewrite) === '') {
            $meta['reason'] = 'empty_response';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        $rewrite = trim($rewrite);
        if (! $this->guard->accepts($original, $rewrite, $evidence)) {
            $meta['reason'] = 'fact_guard';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        if ($rewrite === $original) {
            $meta['reason'] = 'unchanged';

            return ['decision' => $decision, 'language_llm' => $meta];
        }

        $decision['suggested_reply'] = $rewrite;
        $decision['language_llm_applied'] = true;
        $meta['applied'] = true;
        $meta['reason'] = 'ok';

        return ['decision' => $decision, 'language_llm' => $meta];
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function callOpenAi(string $apiKey, string $model, string $original, array $decision): ?string
    {
        $style = '';
        if (is_array($decision['psych'] ?? null) && ! empty($decision['psych']['style_hint'])) {
            $style = (string) $decision['psych']['style_hint'];
        }
        if (is_array($decision['experience'] ?? null) && ! empty($decision['experience']['style_bias'])) {
            $style = trim($style.' '.(string) $decision['experience']['style_bias']);
        }

        $system = 'You are the Wise AI Language Specialist for Bangladesh commerce. '
            .'Rewrite the merchant Assist reply for clarity and natural Bangla/Banglish tone. '
            .'Do NOT invent prices, stock, delivery times, policies, or product claims. '
            .'Keep the same meaning and facts. Return ONLY the rewritten reply text.';

        $user = "Original reply:\n".$original;
        if ($style !== '') {
            $user .= "\n\nStyle hint: ".$style;
        }

        // Keep short — adapters (WEL) budget ~25s total for decide including hub work.
        $response = Http::withToken($apiKey)
            ->timeout(5)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.3,
                'max_tokens' => 400,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI HTTP '.$response->status());
        }

        $text = $response->json('choices.0.message.content');

        return is_string($text) ? $text : null;
    }
}
