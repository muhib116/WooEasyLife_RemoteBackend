<?php

namespace App\WiseAi\Knowledge\Seed;

use App\WiseAi\Knowledge\KnowledgeSchema;

/**
 * Deterministic quality gate for customer-facing seed scripts.
 *
 * This is a release gate, not a claim that language quality is objectively
 * measurable. A 10 means every defined safety + human-copy criterion passed.
 */
final class SeedQualityScorer
{
    /** @var list<string> */
    private const ROBOTIC_TERMS = [
        'হিউম্যান',
        'সিস্টেম',
        'স্টোর পলিসি',
        'ক্যাম্পেইন/নলেজ',
        'অ্যামাউন্ট',
        'কনফার্ম করবে',
    ];

    /**
     * @param  array<string, mixed>  $row
     * @return array{score: int, checks: array<string, bool>, issues: list<string>}
     */
    public function score(array $row, string $scope): array
    {
        $answer = trim((string) ($row['answer'] ?? ''));
        $question = trim((string) ($row['question'] ?? ''));
        $situation = (string) ($row['situation'] ?? '');
        $issues = [];
        $checks = [];

        $checks['complete_schema'] = trim((string) ($row['slug'] ?? '')) !== ''
            && $question !== ''
            && $answer !== ''
            && is_array($row['keywords'] ?? null)
            && in_array((string) ($row['type'] ?? ''), KnowledgeSchema::groundableKinds(), true);
        if (! $checks['complete_schema']) {
            $issues[] = 'missing/invalid customer-facing schema';
        }

        $checks['natural_bangla'] = preg_match_all('/\p{Bengali}/u', $answer) >= 20
            && preg_match('/[।!?]$/u', $answer) === 1;
        if (! $checks['natural_bangla']) {
            $issues[] = 'answer lacks adequate natural Bangla or closing punctuation';
        }

        $robotic = array_values(array_filter(
            self::ROBOTIC_TERMS,
            fn (string $term) => str_contains(mb_strtolower($answer), mb_strtolower($term)),
        ));
        $checks['no_internal_jargon'] = $robotic === [];
        if ($robotic !== []) {
            $issues[] = 'internal/robotic terms: '.implode(', ', $robotic);
        }

        $social = $situation === 'social';
        $checks['conversational_next_step'] = $social
            || preg_match('/বলুন|বলবেন|পাঠান|পাঠাবেন|লিখুন|দিন|জানাই|দেখে/u', $answer) === 1;
        if (! $checks['conversational_next_step']) {
            $issues[] = 'does not give the customer a clear next step';
        }

        $validator = new KnowledgeSeedValidator;
        $checks['evidence_safe'] = $validator->answerFactGuards($answer, (string) ($row['slug'] ?? '')) === [];
        if (! $checks['evidence_safe']) {
            $issues[] = 'contains an unsupported commercial fact';
        }

        $riskSituations = ['S1', 'delivery', 'timeline', 'payment', 'cod', 'stock', 'return', 'refund', 'promo', 'bargain', 'wholesale'];
        $requiresGuard = in_array($situation, $riskSituations, true)
            || in_array((string) ($row['slug'] ?? ''), [
                'price-clarify',
                'area-delivery',
                'delivery-ask-area',
                'order-status',
            ], true);
        $checks['safe_when_risky'] = ! $requiresGuard
            || preg_match('/অনুমান|আন্দাজ|নিয়ম দেখে|দেখে .*জানাই|যাচাই করে জানানো যাবে|দাম বলব না|চার্জ বলব না/u', $answer) === 1;
        if (! $checks['safe_when_risky']) {
            $issues[] = 'risky commerce question lacks a safety/verification guard';
        }

        $checks['scope_correct'] = $scope !== KnowledgeSchema::SCOPE_REGION
            || trim((string) ($row['region'] ?? ($row['meta']['region'] ?? ''))) !== '';
        if (! $checks['scope_correct']) {
            $issues[] = 'regional seed lacks region metadata';
        }

        $score = count(array_filter($checks));

        return compact('score', 'checks', 'issues');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{score: float, perfect: bool, items: int, failures: array<string, list<string>>}
     */
    public function scoreCatalog(array $items, string $scope): array
    {
        $total = 0;
        $failures = [];

        foreach ($items as $row) {
            $result = $this->score($row, $scope);
            $total += $result['score'];
            if ($result['score'] !== 7) {
                $failures[(string) ($row['slug'] ?? 'unknown')] = $result['issues'];
            }
        }

        $count = count($items);
        $score = $count === 0 ? 0.0 : round(($total / ($count * 7)) * 10, 2);

        return [
            'score' => $score,
            'perfect' => $count > 0 && $failures === [],
            'items' => $count,
            'failures' => $failures,
        ];
    }
}
