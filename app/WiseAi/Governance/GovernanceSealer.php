<?php

namespace App\WiseAi\Governance;

use App\Models\WiseAi\WiseApiKey;
use App\WiseAi\DecideEngine;
use App\WiseAi\Commerce\CommerceEventTypes;
use App\WiseAi\Dialogue\DialoguePatterns;
use App\WiseAi\Experience\ExperienceRecorder;
use App\WiseAi\Intelligence\MetricDefinitions;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Language\LanguageCorpus;
use App\WiseAi\Language\PlatformLexicon;
use App\WiseAi\Learning\ReasonCodes;
use App\WiseAi\Psychology\BizOpportunities;
use App\WiseAi\Psychology\PsychSignals;

/**
 * Builds the immutable governance slice of config_snapshot for a turn.
 */
class GovernanceSealer
{
    public function __construct(
        private MerchantPolicy $merchantPolicy,
    ) {}

    /**
     * @param  array<string, mixed>|null  $corpusSnapshot  BCLC sealed pack versions + artifact hashes
     * @return array<string, mixed>
     */
    public function seal(
        WiseApiKey $apiKey,
        string $dictVersion = PlatformLexicon::DICT_VERSION,
        ?array $corpusSnapshot = null,
    ): array {
        $merchant = $this->merchantPolicy->resolve($apiKey);
        $pack = PolicyPack::defaults();

        $sealedAt = now()->toIso8601String();
        $snapshot = $corpusSnapshot ?? [
            'protocol_version' => LanguageCorpus::PROTOCOL_VERSION,
            'packs' => [],
            'compiler_version' => LanguageCorpus::COMPILER_VERSION,
            'assignment_ids' => [],
            'assignment_key' => 'unset',
            'from_artifacts' => false,
        ];
        // Always stamp turn time — never trust cached resolver wall-clock.
        $snapshot['sealed_at'] = $sealedAt;

        return [
            'brain_version' => DecideEngine::BRAIN_VERSION,
            'constitution_version' => Constitution::VERSION,
            'policy_pack_version' => PolicyPack::VERSION,
            'merchant_policy_version' => $merchant['policy_version'],
            'mode' => $merchant['mode'],
            'allow_auto' => $merchant['allow_auto'],
            'sandbox' => $merchant['sandbox'],
            'feature_flags' => $merchant['feature_flags'],
            'contracts' => $pack['contracts_version'],
            'dict_version' => $dictVersion,
            'language_corpus_snapshot' => $snapshot,
            'bclc_protocol_version' => LanguageCorpus::PROTOCOL_VERSION,
            'bclc_compiler_version' => LanguageCorpus::COMPILER_VERSION,
            'knowledge_schema_version' => KnowledgeSchema::VERSION,
            'reason_codes_version' => ReasonCodes::VERSION,
            'metrics_version' => MetricDefinitions::VERSION,
            'commerce_events_version' => CommerceEventTypes::VERSION,
            'psych_version' => PsychSignals::VERSION,
            'opportunities_version' => BizOpportunities::VERSION,
            'dialogue_version' => DialoguePatterns::VERSION,
            'experience_version' => ExperienceRecorder::VERSION,
            'api_key_id' => $apiKey->id,
            'sealed_at' => $sealedAt,
        ];
    }
}
