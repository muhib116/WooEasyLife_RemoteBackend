<?php

namespace App\WiseAi;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Assist\GroundedAssistEngine;
use App\WiseAi\Context\ContextPackBuilder;
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
use App\WiseAi\Learning\ConversationLearningExtractor;
use App\WiseAi\Learning\GapAutoDrafter;
use App\WiseAi\Memory\SoftMemoryExtractor;
use App\WiseAi\Voice\VoiceContractBuilder;

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
        private VoiceContractBuilder $voice,
        private GapAutoDrafter $gapAutoDraft,
        private ContextPackBuilder $contextPack,
        private GroundedAssistEngine $groundedAssist,
        private SoftMemoryExtractor $softMemory,
        private ConversationLearningExtractor $continuousLearning,
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
            // Ambiguous tokens (e.g. pp) must soft-clarify — never fuzzy-hit "support" via substring.
            $languageAmbiguous = is_array($language['ambiguous'] ?? null) ? $language['ambiguous'] : [];
            if ($languageAmbiguous !== []) {
                $decision['action'] = 'clarify';
                $decision['suggested_reply'] = $this->contracts->clarifyReply('unknown');
                $decision['source'] = 'contract';
                $decision['missing_context'] = 'utterance';
                $decision['gap'] = false;
                $gap = false;
                $trace['P3_ground'] = 'skip_unknown_ambiguous';
                $trace['P7_judge'] = 'fail_unknown_utterance';
            } else {
                // Published FAQ/script hits (Playground coach teach). Soft clarify only on miss.
                $unknownGround = $groundText;
                if ($productSubject) {
                    $unknownGround = trim($productSubject['title'].' '.$unknownGround);
                }
                $match = $this->knowledge->resolve(
                    $apiKey,
                    $unknownGround,
                    $classified['intent'],
                    $turn->context,
                    $productSubject,
                );
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
                    if ($productSubject) {
                        $decision['product_subject'] = $productSubject;
                        $evidence['product_subject'] = $productSubject;
                    }
                    $decision['action'] = 'suggest_reply';
                    $decision['suggested_reply'] = $item->answer;
                    $decision['source'] = 'knowledge';
                    $decision['confidence'] = min(98, max(40, (int) $classified['confidence'] + 10));
                    $decision['gap'] = false;
                    $gap = false;
                    $trace['P3_ground'] = [
                        'result' => 'knowledge_hit',
                        'candidates' => $match['candidates'] ?? null,
                        'match_score' => $match['score'],
                        'via' => 'unknown_utterance',
                    ];
                    $trace['P7_judge'] = 'pass_evidence';
                } else {
                    // Soft clarify — not a knowledge gap, no invented facts.
                    $decision['action'] = 'clarify';
                    $decision['suggested_reply'] = $this->contracts->clarifyReply('unknown');
                    $decision['source'] = 'contract';
                    $decision['missing_context'] = 'utterance';
                    $decision['gap'] = false;
                    $gap = false;
                    $trace['P3_ground'] = 'skip_unknown_soft';
                    $trace['P7_judge'] = 'fail_unknown_utterance';
                }
            }
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
                $offerKind = is_string($turn->context['offer_kind'] ?? null)
                    ? (string) $turn->context['offer_kind']
                    : null;
                $decision['action'] = 'needs_human';
                $decision['suggested_reply'] = $this->contracts->gapAssistReply(
                    $classified['intent'],
                    $offerKind,
                );
                $decision['source'] = 'gap_assist';
                $decision['gap'] = true;
                if ($productSubject) {
                    $decision['product_subject'] = $productSubject;
                }
                $trace['P3_ground'] = 'knowledge_miss';
                $trace['P7_judge'] = 'fail_no_evidence';
                $trace['gap_assist_reply'] = true;
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

        // Grounded conversational assist — miss / unknown soft only; strong knowledge hits skip.
        $assistPack = null;
        $flags = is_array($configSnapshot['feature_flags'] ?? null) ? $configSnapshot['feature_flags'] : [];
        $assistEligible = ! empty($flags['llm_grounded_assist'])
            && (
                ($decision['source'] ?? null) === 'gap_assist'
                || ($trace['P3_ground'] ?? null) === 'skip_unknown_soft'
            );
        if ($assistEligible) {
            $assistPack = $this->contextPack->build(
                $apiKey,
                $groundText,
                $language,
                $classified,
                $productSubject,
                $turn->context,
                $turn->conversationId,
            );
            $trace['P3_context_pack'] = $assistPack['pack_meta'] ?? [];
            $assist = $this->groundedAssist->run($assistPack);
            $trace['P6_grounded_assist'] = [
                'applied' => (bool) ($assist['applied'] ?? false),
                'reason' => $assist['reason'] ?? null,
                'attempts' => $assist['attempts'] ?? 0,
                'score' => $assist['score'] ?? null,
                'confidence' => $assist['confidence'] ?? null,
                'passed_bar' => $assist['passed_bar'] ?? null,
                'used_knowledge_ids' => $assist['used_knowledge_ids'] ?? [],
                'prompt_version' => $assist['prompt_version'] ?? null,
                'latency_ms' => $assist['latency_ms'] ?? 0,
            ];
            if (! empty($assist['applied']) && is_string($assist['reply'] ?? null) && trim((string) $assist['reply']) !== '') {
                $needClarify = ! empty($assist['need_clarify']);
                $passedBar = ! empty($assist['passed_bar']);
                // Below bar: keep legacy gap/contract reply unless it is an explicit clarify ask.
                if (! $passedBar && ! $needClarify) {
                    $trace['P6_grounded_assist']['applied_to_decision'] = false;
                    $trace['P6_grounded_assist']['discard_reason'] = 'below_bar';
                } else {
                    $decision['suggested_reply'] = (string) $assist['reply'];
                    $decision['action'] = $needClarify ? 'clarify' : 'suggest_reply';
                    $decision['source'] = $needClarify ? 'grounded_assist_clarify' : 'grounded_assist';
                    $decision['confidence'] = max(40, min(98, (int) ($assist['confidence'] ?? 70)));
                    $decision['gap'] = false;
                    $gap = false;
                    $decision['grounded_assist'] = [
                        'applied' => true,
                        'score' => $assist['score'] ?? null,
                        'confidence' => $assist['confidence'] ?? null,
                        'attempts' => $assist['attempts'] ?? 0,
                        'plan' => $assist['plan'] ?? [],
                        'need_clarify' => $needClarify,
                        'used_knowledge_ids' => $assist['used_knowledge_ids'] ?? [],
                        'prompt_version' => $assist['prompt_version'] ?? null,
                        'passed_bar' => $passedBar,
                        'conversation_summary' => $assistPack['conversation_summary'] ?? null,
                        'goal' => $assistPack['goal'] ?? null,
                    ];
                    $evidence['answer'] = (string) $assist['reply'];
                    $evidence['answer_hash'] = hash('sha256', (string) $assist['reply']);
                    if (! empty($assist['used_knowledge_ids'])) {
                        $evidence['grounded_assist_ids'] = $assist['used_knowledge_ids'];
                        $evidence['knowledge_id'] = (int) ($assist['used_knowledge_ids'][0] ?? 0) ?: null;
                    } else {
                        $evidence['grounded_assist_ids'] = [];
                        $evidence['grounding'] = $needClarify ? 'clarify_no_fact' : 'assist_without_cited_ids';
                    }
                    $trace['P7_judge'] = $needClarify ? 'assist_clarify' : 'pass_grounded_assist';
                    $trace['P3_ground'] = [
                        'result' => 'grounded_assist',
                        'chunk_count' => count($assistPack['evidence_pack'] ?? []),
                        'passed_bar' => $passedBar,
                    ];
                    $trace['P6_grounded_assist']['applied_to_decision'] = true;
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

        // Optional LLM wording — skip when grounded assist already owns the human reply.
        $assistOwned = in_array((string) ($decision['source'] ?? ''), ['grounded_assist', 'grounded_assist_clarify'], true);
        if (! $assistOwned) {
            $llmResult = $this->llmLanguage->maybeRewrite(
                $decision,
                $configSnapshot,
                is_array($evidence) ? $evidence : null,
            );
            $decision = $llmResult['decision'];
            $decision['language_llm'] = $llmResult['language_llm'];
            $trace['P6_language_llm'] = $llmResult['language_llm'];
        } else {
            $trace['P6_language_llm'] = ['applied' => false, 'reason' => 'grounded_assist_owns_reply'];
        }

        // Voice side-channel only when channel=voice or context.output_profile=voice.
        $decision = $this->voice->attach($decision, $turn->channel, $turn->context);
        if (isset($decision['voice']) && is_array($decision['voice'])) {
            $trace['P8_voice'] = [
                'next_action' => $decision['voice']['next_action'] ?? null,
                'slot_to_ask' => $decision['voice']['slot_to_ask'] ?? null,
                'gap' => (bool) ($decision['voice']['gap'] ?? false),
            ];
        }

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

        // Guided heal: gap → draft only (never publish; inbox stays open).
        if ($gap) {
            $autoDraft = $this->gapAutoDraft->maybeDraft($record, $apiKey, $decision);
            if ($autoDraft) {
                $decision['heal'] = [
                    'auto_draft_id' => (int) $autoDraft->id,
                    'auto_draft_status' => 'draft',
                ];
                $trace['P9_heal'] = [
                    'auto_draft_id' => (int) $autoDraft->id,
                    'published' => false,
                ];
                $record->update([
                    'decision' => $decision,
                    'trace' => $trace,
                ]);
                $record->refresh();
            }
        }

        // Soft memory + continuous learning drafts (never auto-publish).
        if ($assistPack !== null) {
            $this->softMemory->persist($apiKey, $turn->conversationId, $record, $decision, $assistPack);
            $clDraft = $this->continuousLearning->maybeDraft($record, $apiKey, $decision, $assistPack);
            if ($clDraft) {
                $decision['learning'] = [
                    'cl_draft_id' => (int) $clDraft->id,
                    'status' => 'draft',
                ];
                $trace['P9_continuous_learning'] = [
                    'draft_id' => (int) $clDraft->id,
                    'published' => false,
                ];
                $record->update([
                    'decision' => $decision,
                    'trace' => $trace,
                ]);
                $record->refresh();
            }
        }

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
