<?php

namespace App\WiseAi\Explain;

use App\Models\WiseAi\WiseTurn;

/**
 * Explainability over a sealed turn — never re-runs live brain / live dictionaries.
 * Replay-compatible: only reads turn.decision, evidence, trace, config_snapshot.
 */
class ExplainBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(WiseTurn $turn): array
    {
        $decision = is_array($turn->decision) ? $turn->decision : [];
        $evidence = is_array($turn->evidence) ? $turn->evidence : [];
        $trace = is_array($turn->trace) ? $turn->trace : [];
        $config = is_array($turn->config_snapshot) ? $turn->config_snapshot : [];
        $language = is_array($decision['language'] ?? null) ? $decision['language'] : [];
        $payload = is_array($turn->payload) ? $turn->payload : [];

        $timeline = [];

        $timeline[] = [
            'step' => 'admit',
            'title' => 'Turn admitted',
            'detail' => 'Request accepted and sealed for this turn.',
            'status' => 'ok',
        ];

        $govTrace = $trace['P1_governance'] ?? null;
        $timeline[] = [
            'step' => 'governance',
            'title' => 'Governance sealed',
            'detail' => 'Constitution '.($config['constitution_version'] ?? '?')
                .' · Policy pack '.($config['policy_pack_version'] ?? '?')
                .' · Merchant '.($config['merchant_policy_version'] ?? '?')
                .' · Mode '.($config['mode'] ?? ($decision['governance']['mode'] ?? '?'))
                .(! empty($config['sandbox']) ? ' · sandbox' : ''),
            'status' => 'ok',
            'data' => is_array($govTrace) ? $govTrace : [
                'constitution_version' => $config['constitution_version'] ?? null,
                'policy_pack_version' => $config['policy_pack_version'] ?? null,
                'merchant_policy_version' => $config['merchant_policy_version'] ?? null,
                'mode' => $config['mode'] ?? null,
                'feature_flags' => $config['feature_flags'] ?? null,
            ],
        ];

        $raw = (string) ($language['raw'] ?? $turn->text ?? $payload['text'] ?? '');
        $canonical = (string) ($language['canonical'] ?? $raw);
        $rules = is_array($language['rules_applied'] ?? null) ? $language['rules_applied'] : [];
        $ruleSummary = $rules === []
            ? 'No lexicon rules applied.'
            : implode('; ', array_map(
                fn ($r) => ($r['from'] ?? '?').' → '.($r['to'] ?? '?'),
                array_slice($rules, 0, 8)
            ));

        $corpus = is_array($config['language_corpus_snapshot'] ?? null)
            ? $config['language_corpus_snapshot']
            : [];
        $corpusPacks = is_array($corpus['packs'] ?? null) ? $corpus['packs'] : [];
        $overlays = is_array($corpus['overlays'] ?? null) ? $corpus['overlays'] : [];
        $conceptsHit = is_array($language['concepts_hit'] ?? null) ? $language['concepts_hit'] : [];
        $corpusDetail = $corpusPacks === []
            ? 'No BCLC pack artifacts sealed (lexicon fallback or pre-BCLC turn).'
            : count($corpusPacks).' pack artifact(s) sealed'
                .(($overlays['content_hash'] ?? '') !== ''
                    ? '; overlays hash='.substr((string) $overlays['content_hash'], 0, 8)
                    : '; no merchant overlays');

        $timeline[] = [
            'step' => 'language',
            'title' => 'Language normalization',
            'detail' => ($raw === $canonical
                ? 'Canonical text unchanged from raw. '
                : "Raw “{$raw}” → canonical “{$canonical}”. {$ruleSummary} ")
                .$corpusDetail,
            'status' => 'ok',
            'data' => [
                'raw' => $raw,
                'canonical' => $canonical,
                'dict_version' => $language['dict_version'] ?? $config['dict_version'] ?? null,
                'concepts_hit' => $conceptsHit,
                'rules_applied' => $rules,
                'emoji_signals' => $language['emoji_signals'] ?? [],
                'ambiguous' => $language['ambiguous'] ?? [],
                'unknown_tokens' => $language['unknown_tokens'] ?? [],
                'language_corpus_snapshot' => $this->presentCorpusSnapshot($corpus),
            ],
        ];

        $intent = (string) ($decision['intent'] ?? 'unknown');
        $observe = $trace['P2_observe'] ?? [];
        $timeline[] = [
            'step' => 'intent',
            'title' => 'Intent detection',
            'detail' => "Classified as “{$intent}” (".($observe['kind'] ?? 'n/a').') on canonical text'
                .(isset($observe['confidence']) ? " at {$observe['confidence']}% pattern confidence." : '.'),
            'status' => 'ok',
            'data' => [
                'intent' => $intent,
                'kind' => $observe['kind'] ?? null,
                'observe_text' => $observe['observe_text'] ?? $canonical,
                'pattern_confidence' => $observe['confidence'] ?? null,
            ],
        ];

        $dialogue = is_array($decision['dialogue'] ?? null) ? $decision['dialogue'] : [];

        $memory = $trace['P4_memory'] ?? null;
        $memoryUsed = (bool) ($decision['memory_used'] ?? false);
        $timeline[] = [
            'step' => 'memory',
            'title' => 'Conversation memory',
            'detail' => $memoryUsed
                ? 'Prior turn context influenced this decision (intent carry and/or ground-text enrich).'
                : 'No memory influence on this turn.',
            'status' => $memoryUsed ? 'used' : 'skip',
            'data' => is_array($memory) ? $memory : ['state' => $memory],
        ];

        // After memory — detection uses memory/product subject (Replay causality).
        if ($dialogue !== []) {
            $timeline[] = [
                'step' => 'dialogue',
                'title' => 'Dialogue pattern',
                'detail' => ($dialogue['label'] ?? $dialogue['id'] ?? 'pattern')
                    .' · family '.($dialogue['family'] ?? '?')
                    .(! empty($dialogue['memory']) ? ' · memory' : ''),
                'status' => 'ok',
                'data' => $dialogue,
            ];
        }

        $contract = $trace['P5_contract'] ?? [];
        $missing = $decision['missing_context'] ?? null;
        $timeline[] = [
            'step' => 'contract',
            'title' => 'Intent contract',
            'detail' => $missing
                ? "Required context missing: {$missing}."
                : 'Required context satisfied or not required for this intent.',
            'status' => $missing ? 'clarify' : 'ok',
            'data' => [
                'requires_product' => $contract['requires_product'] ?? null,
                'product_subject' => $decision['product_subject'] ?? null,
                'missing_context' => $missing,
            ],
        ];

        $ground = (string) ($trace['P3_ground'] ?? 'n/a');
        $timeline[] = [
            'step' => 'knowledge',
            'title' => 'Knowledge grounding',
            'detail' => $this->knowledgeDetail($ground, $evidence, $decision),
            'status' => match (true) {
                str_contains($ground, 'hit') || str_contains($ground, 'menu') || str_contains($ground, 'direct') => 'hit',
                str_contains($ground, 'miss') || str_contains($ground, 'no_knowledge') => 'miss',
                str_contains($ground, 'skip') || str_contains($ground, 'social') => 'skip',
                default => 'info',
            },
            'data' => [
                'ground' => $ground,
                'knowledge_id' => $evidence['knowledge_id'] ?? null,
                'knowledge_version' => $evidence['knowledge_version'] ?? null,
                'match_score' => $evidence['match_score'] ?? null,
                'pricing_menu' => $evidence['pricing_menu'] ?? false,
            ],
        ];

        $judge = (string) ($trace['P7_judge'] ?? 'n/a');
        $action = (string) ($decision['action'] ?? 'needs_human');
        $timeline[] = [
            'step' => 'judge',
            'title' => 'Judge',
            'detail' => "Action “{$action}” — judge={$judge}. "
                .($decision['gap'] ?? false
                    ? 'Knowledge gap: subject/context known but no published evidence.'
                    : 'No knowledge gap flag.'),
            'status' => ($decision['gap'] ?? false) ? 'gap' : 'ok',
            'data' => [
                'action' => $action,
                'judge' => $judge,
                'gap' => (bool) ($decision['gap'] ?? false),
                'source' => $decision['source'] ?? null,
            ],
        ];

        $psych = is_array($decision['psych'] ?? null) ? $decision['psych'] : [];
        $ops = is_array($decision['opportunities'] ?? null) ? $decision['opportunities'] : [];
        $opItems = is_array($ops['items'] ?? null) ? $ops['items'] : [];
        $timeline[] = [
            'step' => 'psych',
            'title' => 'Psychology + opportunities (Assist side-channel)',
            'detail' => $psych === []
                ? 'No psych side-channel on this sealed turn.'
                : 'Emotion “'.($psych['emotion'] ?? '?').'” · journey “'.($psych['journey'] ?? '?')
                    .'” · Assist priority “'.($psych['priority'] ?? 'normal')
                    .'” · style_hint “'.($psych['style_hint'] ?? 'neutral_clear')
                    .'”. Opportunities: '.count($opItems)
                    .' (never alter sealed facts / customer reply).',
            'status' => $psych === [] ? 'skip' : 'side_channel',
            'data' => [
                'psych' => $psych,
                'opportunities' => $ops,
            ],
        ];

        $timeline[] = [
            'step' => 'wording',
            'title' => 'Suggested wording',
            'detail' => $this->wordingDetail($decision, $evidence),
            'status' => ($decision['suggested_reply'] ?? null) ? 'ok' : 'none',
            'data' => [
                'source' => $decision['source'] ?? null,
                'suggested_reply' => $decision['suggested_reply'] ?? null,
                'answer_hash' => $evidence['answer_hash'] ?? null,
            ],
        ];

        $confidence = (int) ($decision['confidence'] ?? 0);
        $timeline[] = [
            'step' => 'confidence',
            'title' => 'Confidence',
            'detail' => $this->confidenceDetail($confidence, $observe, $evidence, $decision),
            'status' => 'ok',
            'data' => [
                'confidence' => $confidence,
                'pattern_confidence' => $observe['confidence'] ?? null,
                'match_score' => $evidence['match_score'] ?? null,
            ],
        ];

        $answers = [
            'why_language' => $raw === $canonical
                ? 'Input needed little or no lexicon normalization. '.$corpusDetail
                : "Normalized with dict ".($language['dict_version'] ?? $config['dict_version'] ?? '?').": {$ruleSummary} ".$corpusDetail,
            'why_intent' => "Intent “{$intent}” from pattern match on canonical “{$canonical}”.",
            'why_dialogue' => $dialogue === []
                ? 'No dialogue pattern sealed on this turn.'
                : 'Dialogue “'.($dialogue['id'] ?? '?').'” ('.($dialogue['label'] ?? '').') · family '
                    .($dialogue['family'] ?? '?').' · '.($dialogue['version'] ?? '')
                    .(! empty($dialogue['script']['id'])
                        ? ' · script '.$dialogue['script']['id']
                            .(! empty($dialogue['script_applied']) ? ' (applied)' : ' (hint)')
                        : ''),
            'why_knowledge' => $this->knowledgeDetail($ground, $evidence, $decision),
            'why_evidence' => empty($evidence['knowledge_id'])
                ? 'No sealed knowledge evidence on this turn.'
                : 'Evidence sealed: knowledge #'.($evidence['knowledge_id'] ?? '?')
                    .' v'.($evidence['knowledge_version'] ?? '?')
                    .' hash='.substr((string) ($evidence['answer_hash'] ?? ''), 0, 12).'…',
            'why_wording' => $this->wordingDetail($decision, $evidence),
            'why_confidence' => $this->confidenceDetail($confidence, $observe, $evidence, $decision),
            'why_clarification' => $action === 'clarify'
                ? ($missing
                    ? "Clarifying because required context “{$missing}” is missing (not a knowledge gap)."
                    : 'Clarifying because the utterance was unclear (soft unknown / contract).')
                : 'No clarification action on this turn.',
            'why_psych' => $psych === []
                ? 'No psych tags sealed on this turn.'
                : 'Assist cues only — emotion/journey/priority/style_hint never change knowledge facts.',
            'why_corpus' => $corpusPacks === []
                ? 'Replay language packs: none sealed (fallback lexicon or older turn).'
                : 'Replay language packs: '.implode(', ', array_map(
                    static fn ($p) => ((string) ($p['slug'] ?? 'pack')).'@'.((string) ($p['version'] ?? '?'))
                        .':'.substr((string) ($p['artifact_hash'] ?? ''), 0, 8),
                    $corpusPacks
                )),
        ];

        return [
            'turn_id' => $turn->id,
            'replay_safe' => true,
            'summary' => $this->summary($decision, $canonical, $ground),
            'timeline' => $timeline,
            'answers' => $answers,
            'sealed' => [
                'brain_version' => $decision['brain_version'] ?? $config['brain_version'] ?? null,
                'dict_version' => $language['dict_version'] ?? $config['dict_version'] ?? null,
                'bclc_protocol_version' => $config['bclc_protocol_version'] ?? null,
                'bclc_compiler_version' => $config['bclc_compiler_version'] ?? null,
                'language_corpus_snapshot' => $this->presentCorpusSnapshot($corpus),
                'knowledge_schema_version' => $config['knowledge_schema_version'] ?? null,
                'reason_codes_version' => $config['reason_codes_version'] ?? null,
                'metrics_version' => $config['metrics_version'] ?? null,
                'psych_version' => $config['psych_version'] ?? null,
                'opportunities_version' => $config['opportunities_version'] ?? null,
                'dialogue_version' => $config['dialogue_version']
                    ?? ($decision['dialogue']['version'] ?? null),
                'commerce_events_version' => $config['commerce_events_version'] ?? null,
                'constitution_version' => $config['constitution_version'] ?? null,
                'policy_pack_version' => $config['policy_pack_version'] ?? null,
                'merchant_policy_version' => $config['merchant_policy_version'] ?? null,
                'contracts' => $config['contracts'] ?? null,
                'mode' => $config['mode'] ?? null,
                'feature_flags' => $config['feature_flags'] ?? null,
                'sandbox' => $config['sandbox'] ?? false,
                'sealed_at' => $config['sealed_at'] ?? ($corpus['sealed_at'] ?? null),
            ],
            'replay' => [
                'question' => $raw,
                'canonical' => $canonical,
                'channel' => $turn->channel,
                'conversation_id' => $turn->conversation_id,
                'intent' => $intent,
                'action' => $action,
                'confidence' => $confidence,
                'gap' => (bool) ($decision['gap'] ?? false),
                'source' => $decision['source'] ?? null,
                'suggested_reply' => $decision['suggested_reply'] ?? null,
                'evidence' => $evidence,
                'psych' => $psych,
                'opportunities' => $ops,
                'latency_ms' => $turn->latency_ms,
                'created_at' => optional($turn->created_at)?->toIso8601String(),
            ],
        ];
    }

    /**
     * Explain/Replay API view of corpus — packs + overlay hash/count only (not full entry bodies).
     * Turn config_snapshot keeps full overlays for sealed honesty.
     *
     * @param  array<string, mixed>  $corpus
     * @return array<string, mixed>
     */
    private function presentCorpusSnapshot(array $corpus): array
    {
        $overlays = is_array($corpus['overlays'] ?? null) ? $corpus['overlays'] : [];
        $entries = is_array($overlays['entries'] ?? null) ? $overlays['entries'] : [];
        $entryIds = [];
        foreach ($entries as $row) {
            if (is_array($row) && ($row['id'] ?? null) !== null) {
                $entryIds[] = (int) $row['id'];
            }
        }

        $presented = $corpus;
        $presented['overlays'] = [
            'content_hash' => (string) ($overlays['content_hash'] ?? ''),
            'entry_count' => (int) ($overlays['entry_count'] ?? count($entries)),
            'meta_override_count' => (int) ($overlays['meta_override_count'] ?? 0),
            'entry_ids' => $entryIds,
        ];

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $decision
     */
    private function knowledgeDetail(string $ground, array $evidence, array $decision): string
    {
        return match (true) {
            $ground === 'pricing_menu_hit' => 'Bare price answered from published pricing-menu FAQ (S9 opt-in).',
            $ground === 'offer_direct', $ground === 'knowledge_hit' => 'Matched published knowledge #'
                .($evidence['knowledge_id'] ?? '?').' (score '.($evidence['match_score'] ?? '?').').',
            $ground === 'offer_asserted_no_knowledge', $ground === 'knowledge_miss' => 'No published knowledge for the asserted/resolved subject — gap.',
            $ground === 'skip_awaiting_offer' => 'Skipped knowledge: offer subject missing → clarify first.',
            $ground === 'skip_unknown_soft' => 'Skipped knowledge: unknown utterance → soft clarify.',
            $ground === 'social_skip_knowledge' => 'Social intent — knowledge not required.',
            default => 'Grounding path: '.$ground.'.',
        };
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $evidence
     */
    private function wordingDetail(array $decision, array $evidence): string
    {
        $source = (string) ($decision['source'] ?? '');
        $reply = $decision['suggested_reply'] ?? null;

        if ($reply === null || $reply === '') {
            return 'No suggested reply text (needs human / gap).';
        }

        return match ($source) {
            'knowledge' => 'Wording copied from sealed knowledge answer'
                .(isset($evidence['title']) ? ' “'.$evidence['title'].'”.' : '.'),
            'contract' => 'Wording from Intent Contract clarify template (not merchant knowledge).',
            'pattern' => 'Wording from built-in social/pattern reply.',
            default => "Wording source “{$source}”.",
        };
    }

    /**
     * @param  array<string, mixed>  $observe
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $decision
     */
    private function confidenceDetail(int $confidence, array $observe, array $evidence, array $decision): string
    {
        $parts = ["Final confidence {$confidence}%."];
        if (isset($observe['confidence'])) {
            $parts[] = 'Pattern base '.$observe['confidence'].'%.';
        }
        if (isset($evidence['match_score'])) {
            $parts[] = 'Knowledge match score '.$evidence['match_score'].'.';
        }
        if (($decision['source'] ?? '') === 'knowledge') {
            $parts[] = 'Boosted after evidence seal.';
        }
        if (($decision['action'] ?? '') === 'clarify') {
            $parts[] = 'Clarify actions keep pattern-level confidence (no invented facts).';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function summary(array $decision, string $canonical, string $ground): string
    {
        $intent = $decision['intent'] ?? 'unknown';
        $action = $decision['action'] ?? 'needs_human';

        return "On “{$canonical}” → intent {$intent} → action {$action} (ground={$ground}).";
    }
}
