<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Dialogue\DialoguePatternDetector;
use App\WiseAi\Dialogue\DialogueScripts;
use App\WiseAi\Experience\ExperienceResolver;
use App\WiseAi\Governance\GovernanceSealer;
use App\WiseAi\Language\LanguageNormalizer;
use App\WiseAi\Language\LanguageReviewIngestor;
use App\WiseAi\Language\LlmLanguageSpecialist;
use App\WiseAi\Language\RegionCode;
use App\WiseAi\Psychology\BizOpportunities;
use App\WiseAi\Psychology\PsychSignals;

/**
 * TurnRunner — Admit / Language / Observe / Memory / Contract / Ground / Judge / Deliver.
 */
class TurnRunner
{
    public function __construct(
        private DecideEngine $engine,
        private KnowledgeResolver $knowledge,
        private ConversationMemory $memory,
        private IntentContract $contracts,
        private ProductResolver $products,
        private LanguageNormalizer $language,
        private GovernanceSealer $governance,
        private LanguageReviewIngestor $languageReviews,
        private PsychSignals $psych,
        private BizOpportunities $opportunities,
        private DialoguePatternDetector $dialogue,
        private DialogueScripts $dialogueScripts,
        private ExperienceResolver $experience,
        private LlmLanguageSpecialist $llmLanguage,
    ) {}

    /**
     * @return array{
     *     turn: WiseTurn,
     *     decision: array<string, mixed>,
     *     latency_ms: int
     * }
     */
    public function run(WiseApiKey $apiKey, IncomingTurn $turn): array
    {
        $startedAt = microtime(true);

        $language = $this->language->normalize(
            $turn->text,
            $apiKey,
            $turn->channel,
            RegionCode::resolve($apiKey, $turn->context),
        );
        $observeText = $language['canonical'];

        $configSnapshot = $this->governance->seal(
            $apiKey,
            (string) $language['dict_version'],
            is_array($language['corpus_snapshot'] ?? null) ? $language['corpus_snapshot'] : null,
        );
        $trace = [
            'P1_admit' => 'ok',
            'P1_governance' => [
                'constitution_version' => $configSnapshot['constitution_version'],
                'policy_pack_version' => $configSnapshot['policy_pack_version'],
                'merchant_policy_version' => $configSnapshot['merchant_policy_version'],
                'mode' => $configSnapshot['mode'],
                'sandbox' => $configSnapshot['sandbox'],
            ],
            'P2_language' => [
                'dict_version' => $language['dict_version'],
                'canonical' => $language['canonical'],
                'rules_count' => count($language['rules_applied']),
                'emoji_count' => count($language['emoji_signals']),
                'ambiguous' => $language['ambiguous'],
                'unknown_tokens' => $language['unknown_tokens'],
                'concepts_hit' => $language['concepts_hit'] ?? [],
                'corpus_packs' => count($configSnapshot['language_corpus_snapshot']['packs'] ?? []),
            ],
            'P2_observe' => null,
            'P4_memory' => 'skip_no_conversation',
            'P5_contract' => null,
            'P3_ground' => null,
            'P7_judge' => null,
            'P7_psych' => null,
            'P7_opportunities' => null,
        ];

        $classified = $this->engine->classify($observeText);
        $trace['P2_observe'] = [
            'intent' => $classified['intent'],
            'kind' => $classified['kind'],
            'confidence' => $classified['confidence'],
            'observe_text' => $observeText,
        ];

        $recent = $this->memory->recent($apiKey, $turn->conversationId);
        $prior = $this->memory->priorBusiness($recent);
        $groundText = $observeText;
        $memoryUsed = false;

        if ($turn->conversationId && $recent->isNotEmpty()) {
            $trace['P4_memory'] = [
                'recent_count' => $recent->count(),
                'prior_turn_id' => $prior['turn_id'] ?? null,
                'prior_intent' => $prior['intent'] ?? null,
            ];
        }

        // Only carry on likely follow-ups — not every unknown (gibberish must soft-clarify).
        $followUpProbe = $observeText.' '.$turn->text;
        if (
            $prior
            && $classified['kind'] !== 'social'
            && $this->memory->isLikelyFollowUp($followUpProbe)
        ) {
            $classified['intent'] = $prior['intent'];
            $classified['kind'] = 'business';
            $classified['confidence'] = max(55, min(90, (int) $classified['confidence'] + 25));
            $classified['social_reply'] = null;
            $groundText = trim($prior['text'].' '.$observeText);
            $memoryUsed = true;
            $trace['P4_memory'] = array_merge(
                is_array($trace['P4_memory']) ? $trace['P4_memory'] : [],
                [
                    'applied' => 'intent_carry',
                    'ground_text' => $groundText,
                ],
            );
        } elseif (
            $prior
            && $classified['kind'] === 'business'
            && mb_strlen(trim($observeText)) <= 24
            && $classified['intent'] === $prior['intent']
        ) {
            $groundText = trim($prior['text'].' '.$observeText);
            $memoryUsed = true;
            $trace['P4_memory'] = array_merge(
                is_array($trace['P4_memory']) ? $trace['P4_memory'] : [],
                [
                    'applied' => 'enrich_ground_text',
                    'ground_text' => $groundText,
                ],
            );
        }

        // Product subject: channel context → text mention → conversation memory.
        $productSubject = null;
        if ($classified['kind'] !== 'social') {
            $productSubject = $this->products->fromContext($apiKey, $turn->context)
                ?? $this->products->mention($apiKey, $observeText)
                ?? $this->products->mention($apiKey, $groundText)
                ?? $this->products->mention($apiKey, $turn->text)
                ?? $this->memory->activeProduct($recent);

            if ($productSubject && str_starts_with((string) ($productSubject['source'] ?? ''), 'memory')) {
                $memoryUsed = true;
            }
            if ($productSubject && str_starts_with((string) ($productSubject['source'] ?? ''), 'context')) {
                $trace['P4_memory'] = array_merge(
                    is_array($trace['P4_memory']) ? $trace['P4_memory'] : [],
                    ['context_product' => $productSubject['external_id'] ?? $productSubject['title']],
                );
            }
        }

        $contract = $this->contracts->for($classified['intent']);
        $trace['P5_contract'] = [
            'intent' => $classified['intent'],
            'requires_product' => $contract['requires_product'],
            'product_subject' => $productSubject['title'] ?? null,
            'product_source' => $productSubject['source'] ?? null,
        ];

        $memoryApplied = is_array($trace['P4_memory'] ?? null)
            ? ($trace['P4_memory']['applied'] ?? null)
            : null;
        $productFromMemory = $productSubject !== null
            && str_starts_with((string) ($productSubject['source'] ?? ''), 'memory');
        $dialogue = $this->dialogue->detect([
            'intent' => $classified['intent'],
            'kind' => $classified['kind'],
            'memory_applied' => $memoryApplied,
            'memory_used' => $memoryUsed,
            'product_from_memory' => $productFromMemory,
            'has_product' => $productSubject !== null,
            'prior_intent' => $prior['intent'] ?? null,
            'canonical' => $observeText,
            'unknown_tokens' => $language['unknown_tokens'] ?? [],
            'region' => RegionCode::resolve($apiKey, $turn->context),
        ]);
        $trace['P2_dialogue'] = [
            'id' => $dialogue['id'],
            'family' => $dialogue['family'],
            'version' => $dialogue['version'],
            'memory' => $dialogue['memory'],
        ];

        $evidence = [];
        $gap = false;
        $decision = [
            'intent' => $classified['intent'],
            'confidence' => $classified['confidence'],
            'action' => 'needs_human',
            'suggested_reply' => null,
            'source' => 'pattern',
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'gap' => false,
            'memory_used' => $memoryUsed,
            'product_subject' => $productSubject,
            'missing_context' => null,
            'dialogue' => $dialogue,
            'language' => [
                'raw' => $language['raw'],
                'canonical' => $language['canonical'],
                'dict_version' => $language['dict_version'],
                'concepts_hit' => $language['concepts_hit'] ?? [],
                'rules_applied' => $language['rules_applied'],
                'emoji_signals' => $language['emoji_signals'],
                'commerce_terms' => $language['commerce_terms'],
                'fillers_removed' => $language['fillers_removed'],
                'ambiguous' => $language['ambiguous'],
                'unknown_tokens' => $language['unknown_tokens'],
                'stages' => $language['stages'],
            ],
            'governance' => [
                'mode' => $configSnapshot['mode'],
                'allow_auto' => $configSnapshot['allow_auto'],
                'sandbox' => $configSnapshot['sandbox'],
                'constitution_version' => $configSnapshot['constitution_version'],
                'policy_pack_version' => $configSnapshot['policy_pack_version'],
                'merchant_policy_version' => $configSnapshot['merchant_policy_version'],
            ],
        ];

        if ($classified['kind'] === 'social' && $classified['social_reply'] !== null) {
            $decision['action'] = 'suggest_reply';
            $decision['suggested_reply'] = $classified['social_reply'];
            $decision['source'] = 'pattern';
            $trace['P3_ground'] = 'social_skip_knowledge';
            $trace['P7_judge'] = 'pass_social';
        } elseif ($classified['intent'] === 'unknown') {
            // Soft clarify — not a knowledge gap, no invented facts.
            $decision['action'] = 'clarify';
            $decision['suggested_reply'] = $this->contracts->clarifyReply('unknown');
            $decision['source'] = 'contract';
            $decision['missing_context'] = 'utterance';
            $decision['gap'] = false;
            $gap = false;
            $trace['P3_ground'] = 'skip_unknown_soft';
            $trace['P7_judge'] = 'fail_unknown_utterance';
        } elseif ($this->contracts->requiresProduct($classified['intent']) && $productSubject === null) {
            // S9: merchant-published pricing menu FAQ may answer bare price (SaaS plans).
            // Default stays clarify (S1) when no pricing_menu knowledge exists.
            $menu = $classified['intent'] === 'price'
                ? $this->knowledge->pricingMenu($apiKey)
                : null;

            if ($menu) {
                $item = $menu['item'];
                $evidence = [
                    'knowledge_id' => $item->id,
                    'knowledge_version' => $item->version,
                    'knowledge_type' => $item->type,
                    'knowledge_kind' => $item->type,
                    'knowledge_scope' => $item->scope ?: 'merchant',
                    'title' => $item->title,
                    'answer' => $item->answer,
                    'answer_hash' => hash('sha256', (string) $item->answer),
                    'match_score' => $menu['score'],
                    'pricing_menu' => true,
                ];
                $decision['action'] = 'suggest_reply';
                $decision['suggested_reply'] = $item->answer;
                $decision['source'] = 'knowledge';
                $decision['confidence'] = min(95, $classified['confidence'] + 8);
                $decision['gap'] = false;
                $gap = false;
                $trace['P3_ground'] = 'pricing_menu_hit';
                $trace['P7_judge'] = 'pass_pricing_menu';
            } else {
                // S5: vague token may hit many weak offers — shortlist before bare clarify.
                $probe = $this->knowledge->resolve(
                    $apiKey,
                    $groundText,
                    $classified['intent'],
                    $turn->context,
                    null,
                );
                if ($probe && ! empty($probe['ambiguous']) && ! empty($probe['shortlist'])) {
                    $shortlist = $probe['shortlist'];
                    $decision['action'] = 'clarify';
                    $decision['suggested_reply'] = $this->knowledge->shortlistClarifyReply($shortlist);
                    $decision['source'] = 'shortlist';
                    $decision['missing_context'] = 'offer';
                    $decision['shortlist'] = $shortlist;
                    $decision['gap'] = false;
                    $gap = false;
                    $decision['confidence'] = min(75, max(40, (int) $classified['confidence']));
                    $trace['P3_ground'] = [
                        'result' => 'shortlist',
                        'candidates' => $probe['candidates'] ?? count($shortlist),
                        'best_score' => $probe['score'] ?? null,
                        'shortlist_count' => count($shortlist),
                    ];
                    $trace['P7_judge'] = 'fail_ambiguous_offers';
                } else {
                    // Context miss — clarify (not a knowledge gap). Kind-aware copy when hint present.
                    $offerKind = isset($turn->context['offer_kind'])
                        ? strtolower(trim((string) $turn->context['offer_kind']))
                        : null;
                    if ($offerKind !== null && ! in_array($offerKind, ['physical', 'digital', 'service', 'subscription', 'other'], true)) {
                        $offerKind = null;
                    }
                    $clarify = $this->contracts->clarifyReply($classified['intent'], $offerKind);
                    $decision['action'] = 'clarify';
                    $decision['suggested_reply'] = $clarify;
                    $decision['source'] = 'contract';
                    $decision['missing_context'] = 'offer';
                    $decision['offer_kind_hint'] = $offerKind;
                    $decision['gap'] = false;
                    $gap = false;
                    $trace['P3_ground'] = 'skip_awaiting_offer';
                    $trace['P5_contract'] = array_merge($trace['P5_contract'] ?? [], [
                        'offer_kind_hint' => $offerKind,
                    ]);
                    $trace['P7_judge'] = 'fail_missing_offer';
                }
            }
        } else {
            if ($productSubject) {
                $groundText = trim($productSubject['title'].' '.$groundText);
            }

            // When an offer subject is asserted (context/memory/mention), never fuzzy-match
            // unrelated FAQ — missing published row must be a gap (S6), not a wrong price.
            $match = null;
            $requiresOffer = $this->contracts->requiresProduct($classified['intent']);
            if ($productSubject && $requiresOffer) {
                if (! empty($productSubject['knowledge_id'])) {
                    $direct = WiseKnowledgeItem::query()
                        ->where('wise_api_key_id', $apiKey->id)
                        ->where('id', $productSubject['knowledge_id'])
                        ->where('status', 'published')
                        ->first();
                    if ($direct) {
                        $match = ['item' => $direct, 'score' => 100];
                    }
                }
                if (! $match && ! empty($productSubject['external_id'])) {
                    $byExternal = WiseKnowledgeItem::query()
                        ->where('wise_api_key_id', $apiKey->id)
                        ->where('status', 'published')
                        ->where('type', 'product')
                        ->where('external_id', (string) $productSubject['external_id'])
                        ->first();
                    if ($byExternal) {
                        $match = ['item' => $byExternal, 'score' => 100];
                    }
                }
                $trace['P3_ground'] = $match ? 'offer_direct' : 'offer_asserted_no_knowledge';
            } else {
                $match = $this->knowledge->resolve(
                    $apiKey,
                    $groundText,
                    $classified['intent'],
                    $turn->context,
                    $productSubject,
                );
            }

            if ($match && ! empty($match['ambiguous']) && ! empty($match['shortlist'])) {
                $shortlist = $match['shortlist'];
                $decision['action'] = 'clarify';
                $decision['suggested_reply'] = $this->knowledge->shortlistClarifyReply($shortlist);
                $decision['source'] = 'shortlist';
                $decision['missing_context'] = 'offer';
                $decision['shortlist'] = $shortlist;
                $decision['gap'] = false;
                $gap = false;
                $decision['confidence'] = min(75, max(40, (int) $classified['confidence']));
                $trace['P3_ground'] = [
                    'result' => 'shortlist',
                    'candidates' => $match['candidates'] ?? count($shortlist),
                    'best_score' => $match['score'] ?? null,
                    'shortlist_count' => count($shortlist),
                ];
                $trace['P7_judge'] = 'fail_ambiguous_offers';
            } elseif ($match && ! empty($match['item'])) {
                $item = $match['item'];
                $evidence = [
                    'knowledge_id' => $item->id,
                    'knowledge_version' => $item->version,
                    'knowledge_type' => $item->type,
                    'knowledge_kind' => $item->type,
                    'knowledge_scope' => $item->scope ?: 'merchant',
                    'title' => $item->title,
                    'answer' => $item->answer,
                    'answer_hash' => hash('sha256', (string) $item->answer),
                    'match_score' => $match['score'],
                ];
                if ($memoryUsed && $prior) {
                    $evidence['memory'] = [
                        'prior_turn_id' => $prior['turn_id'],
                        'prior_intent' => $prior['intent'],
                        'ground_text' => $groundText,
                    ];
                }
                if ($item->type === 'product') {
                    $decision['product_subject'] = [
                        'knowledge_id' => (int) $item->id,
                        'title' => (string) $item->title,
                        'source' => 'knowledge',
                        'external_id' => $item->external_id,
                    ];
                } elseif ($productSubject) {
                    $decision['product_subject'] = $productSubject;
                    $evidence['product_subject'] = $productSubject;
                }

                $decision['action'] = 'suggest_reply';
                $decision['suggested_reply'] = $item->answer;
                $decision['source'] = 'knowledge';
                $decision['confidence'] = min(98, $classified['confidence'] + 10);
                $trace['P3_ground'] = [
                    'result' => 'knowledge_hit',
                    'candidates' => $match['candidates'] ?? null,
                    'match_score' => $match['score'],
                ];
                $trace['P7_judge'] = 'pass_evidence';
            } else {
                $gap = true;
                $decision['action'] = 'needs_human';
                $decision['suggested_reply'] = null;
                $decision['source'] = 'pattern';
                $decision['gap'] = true;
                if ($productSubject) {
                    $decision['product_subject'] = $productSubject;
                }
                $trace['P3_ground'] = 'knowledge_miss';
                $trace['P7_judge'] = 'fail_no_evidence';
                if ($memoryUsed && $prior) {
                    $evidence = [
                        'memory' => [
                            'prior_turn_id' => $prior['turn_id'],
                            'prior_intent' => $prior['intent'],
                            'ground_text' => $groundText,
                        ],
                    ];
                }
                if ($productSubject) {
                    $evidence['product_subject'] = $productSubject;
                }
            }
        }

        // Persist subject for later turns even when we only clarified/gapped after a mention.
        if ($productSubject && empty($decision['product_subject'])) {
            $decision['product_subject'] = $productSubject;
        }

        $decision['gap'] = $gap;
        $decision['memory_used'] = $memoryUsed;

        // Experience preview first so preferred_script can influence DialogueScripts.
        $expPreview = $this->experience->preview($apiKey, $decision, $configSnapshot);

        // Dialogue v1 scripts — clarify wording / assist hints; never override knowledge.
        $offerKindForScript = isset($decision['offer_kind_hint'])
            ? (string) $decision['offer_kind_hint']
            : (isset($turn->context['offer_kind']) ? strtolower(trim((string) $turn->context['offer_kind'])) : null);
        $decision = $this->dialogueScripts->enrich($decision, [
            'product_title' => is_array($productSubject) ? ($productSubject['title'] ?? null) : null,
            'offer_kind' => $offerKindForScript,
            'preferred_script' => $expPreview['preferred_script'] ?? null,
        ]);
        if (! empty($decision['dialogue']['script']['id'])) {
            $trace['P2_dialogue']['script_id'] = $decision['dialogue']['script']['id'];
            $trace['P2_dialogue']['script_applied'] = (bool) ($decision['dialogue']['script_applied'] ?? false);
            if (! empty($decision['dialogue']['experience_preferred_script'])) {
                $trace['P2_dialogue']['experience_preferred_script'] = $decision['dialogue']['experience_preferred_script'];
            }
        }

        // Side-channel only — never mutates suggested_reply / evidence facts.
        $psych = $this->psych->tag($turn->text, $language, $classified, $decision);
        $ops = $this->opportunities->suggest($decision, $psych, is_array($evidence) ? $evidence : []);
        $decision['psych'] = $psych;
        $decision['opportunities'] = $ops;
        $trace['P7_psych'] = [
            'emotion' => $psych['emotion'],
            'journey' => $psych['journey'],
            'priority' => $psych['priority'],
            'style_hint' => $psych['style_hint'],
        ];
        $trace['P7_opportunities'] = [
            'count' => count($ops['items']),
            'ids' => array_column($ops['items'], 'id'),
        ];

        // Experience soft hints (what worked) — never overrides knowledge facts / Judge action.
        $expResult = $this->experience->apply($apiKey, $decision, $configSnapshot, $expPreview);
        $decision = $expResult['decision'];
        $trace['P7_experience'] = [
            'applied' => (bool) ($expResult['experience']['applied'] ?? false),
            'net_weight' => $expResult['experience']['net_weight'] ?? 0,
            'confidence_delta' => $expResult['experience']['confidence_delta'] ?? 0,
            'preferred_script' => $expResult['experience']['preferred_script'] ?? null,
        ];

        // Optional LLM wording — fail-open; digit/fact guard; never decides.
        $llmResult = $this->llmLanguage->maybeRewrite(
            $decision,
            $configSnapshot,
            is_array($evidence) ? $evidence : null,
        );
        $decision = $llmResult['decision'];
        $decision['language_llm'] = $llmResult['language_llm'];
        $trace['P6_language_llm'] = $llmResult['language_llm'];

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $record = WiseTurn::create([
            'wise_api_key_id' => $apiKey->id,
            'channel' => $turn->channel,
            'conversation_id' => $turn->conversationId,
            'text' => $turn->text,
            'payload' => [
                'text' => $turn->text,
                'channel' => $turn->channel,
                'conversation_id' => $turn->conversationId,
                'context' => $turn->context,
            ],
            'config_snapshot' => $configSnapshot,
            'decision' => $decision,
            'evidence' => $evidence ?: null,
            'trace' => $trace,
            'status' => 'ok',
            'gap' => $gap,
            'latency_ms' => $latencyMs,
        ]);

        // Learning seed only — humans promote via Language Review (never auto-learn).
        $this->languageReviews->ingest(
            $apiKey,
            $language,
            (int) $record->id,
            $turn->channel,
            RegionCode::resolve($apiKey, $turn->context),
        );
        // Cross-key discovery rank refresh after response (keep decide bounded).
        app(\App\WiseAi\Language\DiscoveryRanker::class)->flushQueuedAfterResponse();

        $apiKey->recordUsage();

        return [
            'turn' => $record,
            'decision' => $decision,
            'latency_ms' => $latencyMs,
        ];
    }
}
