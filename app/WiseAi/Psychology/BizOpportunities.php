<?php

namespace App\WiseAi\Psychology;

/**
 * Business opportunities / coach seeds (Wave C3).
 * Assist side-channel recommendations only — never Auto, never mutates knowledge, never customer reply text.
 */
class BizOpportunities
{
    public const VERSION = '1.0';

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>|null  $psych
     * @param  array<string, mixed>  $evidence
     * @return array{
     *     version: string,
     *     items: list<array{
     *         id: string,
     *         title: string,
     *         reason: string,
     *         priority: string,
     *         href_hint: string
     *     }>,
     *     side_channel: true
     * }
     */
    public function suggest(array $decision, ?array $psych, array $evidence = []): array
    {
        $items = [];
        $action = (string) ($decision['action'] ?? '');
        $intent = (string) ($decision['intent'] ?? '');
        $priority = (string) ($psych['priority'] ?? 'normal');

        if (($decision['gap'] ?? false) === true) {
            $items[] = [
                'id' => 'draft_knowledge',
                'title' => 'Draft knowledge from this gap',
                'reason' => 'Customer asked about “'.$intent.'” but no published evidence grounded a reply.',
                'priority' => 'high',
                'href_hint' => 'learning_gap',
            ];
        }

        if ($action === 'clarify' && ($decision['missing_context'] ?? null) === 'offer') {
            $items[] = [
                'id' => 'publish_offer_or_menu',
                'title' => 'Publish offer catalog or pricing-menu FAQ',
                'reason' => 'Bare price ask needed an offer subject — catalog/menu reduces clarify loops.',
                'priority' => 'high',
                'href_hint' => 'knowledge',
            ];
        }

        if ($action === 'clarify' && $intent === 'unknown') {
            $items[] = [
                'id' => 'language_or_script',
                'title' => 'Add language entry or soft-clarify script',
                'reason' => 'Soft unknown — lexicon/script coverage may reduce confusion turns.',
                'priority' => 'normal',
                'href_hint' => 'language',
            ];
        }

        if (in_array($priority, ['high', 'critical'], true) && in_array($action, ['suggest_reply', 'clarify'], true)) {
            $items[] = [
                'id' => 'assist_priority',
                'title' => 'Review Assist sooner ('.$priority.' psych priority)',
                'reason' => 'Emotion/journey signals suggest faster human review — still human-send only.',
                'priority' => $priority,
                'href_hint' => 'assist',
            ];
        }

        if (($psych['emotion'] ?? '') === 'price_sensitive' && $intent === 'price' && $action === 'suggest_reply') {
            $items[] = [
                'id' => 'value_script',
                'title' => 'Consider a value/offer script (voice kind)',
                'reason' => 'Price-sensitive tone — voice/script knowledge can guide Assist edits (facts stay sealed).',
                'priority' => 'normal',
                'href_hint' => 'knowledge',
            ];
        }

        if ($action === 'needs_human' && empty($decision['gap'])) {
            $items[] = [
                'id' => 'policy_or_human',
                'title' => 'Check policy pack or hand off',
                'reason' => 'needs_human without gap — policy/safety or missing capability may apply.',
                'priority' => 'high',
                'href_hint' => 'config',
            ];
        }

        // Deduplicate by id, keep first.
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            if (isset($seen[$item['id']])) {
                continue;
            }
            $seen[$item['id']] = true;
            $unique[] = $item;
        }

        return [
            'version' => self::VERSION,
            'items' => $unique,
            'side_channel' => true,
            'evidence_note' => empty($evidence['knowledge_id'])
                ? null
                : 'Opportunities do not override sealed knowledge #'.$evidence['knowledge_id'],
        ];
    }
}
