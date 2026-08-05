<?php

namespace App\WiseAi\Playground;

use App\Models\WiseAi\WiseTurn;
use App\WiseAi\DecideEngine;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Language\LlmLanguageConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * On-demand Playground coach — proposes category-wise brain updates.
 * Never persists / never publishes. Fail-closed if LLM unavailable.
 */
final class PlaygroundCoach
{
    public const CATEGORY_KNOWLEDGE_FAQ = 'knowledge_faq';

    public const CATEGORY_LANGUAGE_ABBREV = 'language_abbrev';

    public const CATEGORY_NOOP = 'noop';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_KNOWLEDGE_FAQ,
        self::CATEGORY_LANGUAGE_ABBREV,
        self::CATEGORY_NOOP,
    ];

    /** @var list<string> */
    public const LANGUAGE_TYPES = ['abbrev', 'banglish', 'sms', 'commerce', 'messenger'];

    public function __construct(
        private LlmLanguageConfig $llm,
        private KnowledgeSeedValidator $validator = new KnowledgeSeedValidator,
    ) {}

    /**
     * @param  list<array{role?: string, text?: string}>  $messages
     * @return array{
     *     category: string,
     *     confidence: int,
     *     rationale: string,
     *     knowledge: array{title: string, question: string, answer: string, keywords: list<string>},
     *     language: array{type: string, from: string, to: string},
     *     warnings: list<string>,
     *     model: string,
     *     latency_ms: int,
     *     hint: string
     * }
     */
    public function propose(WiseTurn $turn, array $messages = []): array
    {
        $apiKey = $this->llm->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Wise LLM key missing. Save a key in Config → LLM Language, or set WISE_OPENAI_API_KEY.');
        }
        if (! $this->llm->enabled()) {
            throw new RuntimeException('LLM Language is disabled in Config. Enable it to run Playground Coach.');
        }

        $hint = $this->hintCategory($turn);
        $context = $this->buildContext($turn, $messages, $hint);
        $model = $this->llm->model();
        $started = microtime(true);

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'max_tokens' => 900,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => (string) json_encode($context, JSON_UNESCAPED_UNICODE)],
                ],
            ]);

        $latency = (int) round((microtime(true) - $started) * 1000);
        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200)
            );
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Empty coach model response.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Coach response was not valid JSON.');
        }

        $proposal = $this->normalizeProposal($decoded, $turn, $hint);
        $proposal['model'] = $model;
        $proposal['latency_ms'] = $latency;
        $proposal['hint'] = $hint;

        return $proposal;
    }

    /**
     * Normalize / clamp a raw proposal (also used by tests without HTTP).
     *
     * @param  array<string, mixed>  $raw
     * @return array{
     *     category: string,
     *     confidence: int,
     *     rationale: string,
     *     knowledge: array{title: string, question: string, answer: string, keywords: list<string>},
     *     language: array{type: string, from: string, to: string},
     *     warnings: list<string>
     * }
     */
    public function normalizeProposal(array $raw, WiseTurn $turn, ?string $hint = null): array
    {
        $hint = $hint ?? $this->hintCategory($turn);
        $rawCategory = (string) ($raw['category'] ?? $hint);
        if (! in_array($rawCategory, self::CATEGORIES, true)) {
            $rawCategory = $hint;
        }

        $warnings = [];
        if (isset($raw['warnings']) && is_array($raw['warnings'])) {
            foreach ($raw['warnings'] as $w) {
                if (is_string($w) && trim($w) !== '') {
                    $warnings[] = mb_substr(trim($w), 0, 200);
                }
            }
        }

        [$category, $overrideNote] = $this->resolveCategory($rawCategory, $hint, $turn);
        if ($overrideNote !== null) {
            $warnings[] = $overrideNote;
        }

        $confidence = (int) ($raw['confidence'] ?? 50);
        $confidence = max(0, min(100, $confidence));
        $rationale = mb_substr(trim((string) ($raw['rationale'] ?? '')), 0, 500);

        $decision = is_array($turn->decision) ? $turn->decision : [];
        $lang = is_array($decision['language'] ?? null) ? $decision['language'] : [];
        $canonical = trim((string) ($lang['canonical'] ?? $turn->text ?? ''));
        $customerText = trim((string) ($turn->text ?? ''));
        $assist = trim((string) ($decision['suggested_reply'] ?? ''));

        $knowledgeRaw = is_array($raw['knowledge'] ?? null) ? $raw['knowledge'] : [];
        $title = trim((string) ($knowledgeRaw['title'] ?? ''));
        $question = trim((string) ($knowledgeRaw['question'] ?? ''));
        $answer = trim((string) ($knowledgeRaw['answer'] ?? ''));

        // FAQ proposals always anchor to the customer utterance so Publish has a real Q→A pair.
        if ($category === self::CATEGORY_KNOWLEDGE_FAQ) {
            if ($customerText !== '') {
                $question = $customerText;
                if ($title === '' || mb_strlen($title) < 3) {
                    $title = mb_substr($customerText, 0, 80);
                }
            } else {
                if ($title === '') {
                    $title = mb_substr($canonical !== '' ? $canonical : 'Playground FAQ', 0, 80);
                }
                if ($question === '') {
                    $question = $canonical;
                }
            }
            if ($answer === '') {
                $answer = $assist !== ''
                    ? $assist
                    : 'কোন প্রোডাক্ট/সার্ভিসের বিস্তারিত জানতে চান? নাম বা ছবি পাঠালে দেখে বলছি — আন্দাজ করে দাম/চার্জ বলব না।';
            }
        } else {
            if ($title === '') {
                $title = mb_substr($canonical !== '' ? $canonical : ($customerText !== '' ? $customerText : 'Playground FAQ'), 0, 80);
            }
            if ($question === '') {
                $question = $canonical !== '' ? $canonical : $customerText;
            }
        }

        $keywords = [];
        if (isset($knowledgeRaw['keywords']) && is_array($knowledgeRaw['keywords'])) {
            foreach ($knowledgeRaw['keywords'] as $kw) {
                if (is_string($kw) && trim($kw) !== '') {
                    $keywords[] = mb_substr(trim($kw), 0, 60);
                }
            }
        }
        $intent = (string) ($decision['intent'] ?? '');
        if ($intent !== '' && $intent !== 'unknown' && ! in_array($intent, $keywords, true)) {
            $keywords[] = $intent;
        }
        $keywords = array_values(array_unique(array_slice($keywords, 0, 8)));

        if ($answer !== '') {
            $feeErrors = $this->validator->answerFactGuards($answer, 'coach FAQ');
            if ($feeErrors !== []) {
                $warnings[] = 'Invented fee/phone/percent stripped from proposed answer — write refuse/clarify phrasing.';
                $answer = $assist !== '' ? $assist : 'এলাকা বা প্রোডাক্ট বললে স্টোর পলিসি দেখে জানাই — আন্দাজ করে চার্জ/দাম বলব না।';
                if ($this->validator->answerFactGuards($answer, 'coach FAQ') !== []) {
                    $answer = 'এলাকা বা প্রোডাক্ট বললে স্টোর পলিসি দেখে জানাই — আন্দাজ করে চার্জ/দাম বলব না।';
                }
            }
        }

        $languageRaw = is_array($raw['language'] ?? null) ? $raw['language'] : [];
        $langType = (string) ($languageRaw['type'] ?? 'abbrev');
        if (! in_array($langType, self::LANGUAGE_TYPES, true)) {
            $langType = 'abbrev';
        }
        $from = mb_strtolower(trim((string) ($languageRaw['from'] ?? '')));
        $to = trim((string) ($languageRaw['to'] ?? ''));

        if ($category === self::CATEGORY_LANGUAGE_ABBREV) {
            if ($from === '') {
                $ambiguous = is_array($lang['ambiguous'] ?? null) ? $lang['ambiguous'] : [];
                $unknown = is_array($lang['unknown_tokens'] ?? null) ? $lang['unknown_tokens'] : [];
                $from = mb_strtolower(trim((string) ($ambiguous[0] ?? $unknown[0] ?? $customerText)));
            }
            if ($to === '') {
                $to = 'দাম কত';
                $warnings[] = 'Abbrev expansion was empty — defaulted to দাম কত; edit before publish.';
            }
            if ($to !== '' && $this->validator->answerFactGuards($to, 'coach language.to') !== []) {
                $warnings[] = 'Invented fee/phone/percent stripped from language.to — edit before publish.';
                $to = 'দাম কত';
            }
        }

        return [
            'category' => $category,
            'confidence' => $confidence,
            'rationale' => $rationale !== '' ? $rationale : 'Coach proposal',
            'knowledge' => [
                'title' => mb_substr($title, 0, 191),
                'question' => mb_substr($question, 0, 2000),
                'answer' => mb_substr($answer, 0, 5000),
                'keywords' => $keywords,
            ],
            'language' => [
                'type' => $langType,
                'from' => mb_substr($from, 0, 80),
                'to' => mb_substr($to, 0, 200),
            ],
            'warnings' => array_values(array_unique(array_slice($warnings, 0, 8))),
        ];
    }

    public function hintCategory(WiseTurn $turn): string
    {
        $decision = is_array($turn->decision) ? $turn->decision : [];
        $lang = is_array($decision['language'] ?? null) ? $decision['language'] : [];
        $ambiguous = is_array($lang['ambiguous'] ?? null) ? $lang['ambiguous'] : [];
        $unknown = is_array($lang['unknown_tokens'] ?? null) ? $lang['unknown_tokens'] : [];
        $intent = (string) ($decision['intent'] ?? 'unknown');
        $source = (string) ($decision['source'] ?? '');
        $action = (string) ($decision['action'] ?? '');
        $missing = (string) ($decision['missing_context'] ?? '');
        $text = trim((string) ($turn->text ?? ''));
        $canonical = trim((string) ($lang['canonical'] ?? $text));
        $blob = mb_strtolower($canonical.' '.$text);

        // 1) Language surface first — short / ambiguous tokens.
        if ($ambiguous !== []) {
            return self::CATEGORY_LANGUAGE_ABBREV;
        }
        if ($unknown !== [] && mb_strlen($text) <= 16) {
            return self::CATEGORY_LANGUAGE_ABBREV;
        }
        if ($this->looksLikeAbbrevOnly($text)) {
            return self::CATEGORY_LANGUAGE_ABBREV;
        }

        // 2) Knowledge / gap / business clarify → FAQ teachable.
        if ($turn->gap || $source === 'gap_assist') {
            return self::CATEGORY_KNOWLEDGE_FAQ;
        }
        if (in_array($action, ['needs_human', 'clarify'], true)) {
            return self::CATEGORY_KNOWLEDGE_FAQ;
        }
        if ($missing !== '') {
            return self::CATEGORY_KNOWLEDGE_FAQ;
        }
        if (in_array($intent, DecideEngine::BUSINESS_INTENTS, true)) {
            return self::CATEGORY_KNOWLEDGE_FAQ;
        }
        if ($this->looksLikeProductOrPolicyAsk($blob)) {
            return self::CATEGORY_KNOWLEDGE_FAQ;
        }

        // 3) Social / already answered greeting → noop.
        if (in_array($intent, ['greeting', 'ack', 'thanks'], true)) {
            return self::CATEGORY_NOOP;
        }
        if ($source === 'knowledge' && $action === 'suggest_reply') {
            return self::CATEGORY_NOOP;
        }

        // Unknown + longer text still often a teachable FAQ (effectiveness, works?, etc.).
        if ($intent === 'unknown' || $source === 'contract') {
            if (mb_strlen($text) <= 8) {
                return self::CATEGORY_LANGUAGE_ABBREV;
            }

            return self::CATEGORY_KNOWLEDGE_FAQ;
        }

        return self::CATEGORY_NOOP;
    }

    /**
     * Prefer deterministic hint over LLM noop when brain still needs a teachable update.
     *
     * @return array{0: string, 1: string|null}
     */
    public function resolveCategory(string $llmCategory, string $hint, WiseTurn $turn): array
    {
        if ($llmCategory === $hint) {
            return [$llmCategory, null];
        }

        // Never let LLM demote a strong language/FAQ hint to noop.
        if (
            $llmCategory === self::CATEGORY_NOOP
            && in_array($hint, [self::CATEGORY_KNOWLEDGE_FAQ, self::CATEGORY_LANGUAGE_ABBREV], true)
        ) {
            return [$hint, "Auto category: LLM said noop → kept {$hint} (hint)."];
        }

        // Ambiguous / short abbrev always wins over FAQ.
        if ($hint === self::CATEGORY_LANGUAGE_ABBREV && $llmCategory === self::CATEGORY_KNOWLEDGE_FAQ) {
            return [self::CATEGORY_LANGUAGE_ABBREV, 'Auto category: ambiguous/short token → language_abbrev.'];
        }

        // Gap / business clarify always wins over abbrev unless text is abbrev-only.
        $text = trim((string) ($turn->text ?? ''));
        if (
            $hint === self::CATEGORY_KNOWLEDGE_FAQ
            && $llmCategory === self::CATEGORY_LANGUAGE_ABBREV
            && ! $this->looksLikeAbbrevOnly($text)
        ) {
            return [self::CATEGORY_KNOWLEDGE_FAQ, 'Auto category: business/gap turn → knowledge_faq.'];
        }

        return [$llmCategory, null];
    }

    private function looksLikeAbbrevOnly(string $text): bool
    {
        $t = trim($text);
        if ($t === '') {
            return false;
        }
        if (mb_strlen($t) <= 4 && preg_match('/^[\p{L}\p{N}.]+$/u', $t) === 1) {
            return true;
        }

        return (bool) preg_match('/^(pp|p\.p|tnx|thx|plz|pls|asap|msg|ok+|okk+)$/iu', $t);
    }

    private function looksLikeProductOrPolicyAsk(string $blob): bool
    {
        return (bool) preg_match(
            '/কাজ করে|কাম করে|effective|work\b|works\b|genuine|আসল|নকল|side effect|সাইড|expire|মেয়াদ|warranty|ওয়ারেন্টি|ingredients|উপাদান|how to use|কিভাবে ব্যবহার|stock|স্টক|available|আছে কি|কত দাম|দাম|delivery|ডেলিভারি|charge|চার্জ|cod|ক্যাশ|payment|পেমেন্ট|bkash|nagad/ui',
            $blob,
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Wise AI Playground Coach for Bangladesh ecommerce Messenger chat.
Classify ONE customer turn and propose a brain update. Return JSON only:

{
  "category": "knowledge_faq" | "language_abbrev" | "noop",
  "confidence": 0-100,
  "rationale": "short why",
  "knowledge": {"title":"","question":"","answer":"","keywords":[]},
  "language": {"type":"abbrev|banglish|sms|commerce","from":"","to":""},
  "warnings": []
}

Rules:
- Prefer server hint_category unless clearly wrong.
- knowledge_faq: product/policy/business ask, gap, clarify, "does it work?", effectiveness, delivery/price/payment — even if more context is needed. Write a Bangla clarify/handoff FAQ answer. NEVER invent prices/fees/phones/percents.
- language_abbrev: short/ambiguous token only (pp, tnx, plz, unknown slang). Set language.from + language.to.
- noop: ONLY pure social already fine (hi/thanks/ok) OR knowledge already hit with a good sealed answer. Do NOT use noop just because "more context needed".
- Human will edit + approve — you only propose.
PROMPT;
    }

    /**
     * @param  list<array{role?: string, text?: string}>  $messages
     * @return array<string, mixed>
     */
    private function buildContext(WiseTurn $turn, array $messages, string $hint): array
    {
        $decision = is_array($turn->decision) ? $turn->decision : [];
        $lang = is_array($decision['language'] ?? null) ? $decision['language'] : [];
        $evidence = is_array($turn->evidence) ? $turn->evidence : [];

        $window = [];
        foreach (array_slice($messages, -12) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $role = (string) ($row['role'] ?? '');
            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '' || ! in_array($role, ['customer', 'brain', 'user', 'assistant'], true)) {
                continue;
            }
            $window[] = [
                'role' => $role === 'user' ? 'customer' : ($role === 'assistant' ? 'brain' : $role),
                'text' => mb_substr($text, 0, 500),
            ];
        }

        return [
            'hint_category' => $hint,
            'customer_text' => $turn->text,
            'canonical' => $lang['canonical'] ?? null,
            'intent' => $decision['intent'] ?? null,
            'confidence' => $decision['confidence'] ?? null,
            'action' => $decision['action'] ?? null,
            'source' => $decision['source'] ?? null,
            'gap' => (bool) $turn->gap,
            'ambiguous' => $lang['ambiguous'] ?? [],
            'unknown_tokens' => $lang['unknown_tokens'] ?? [],
            'suggested_reply' => $decision['suggested_reply'] ?? null,
            'evidence_title' => $evidence['title'] ?? null,
            'chat_window' => $window,
        ];
    }
}
