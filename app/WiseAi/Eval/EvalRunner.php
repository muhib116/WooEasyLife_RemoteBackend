<?php

namespace App\WiseAi\Eval;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\KnowledgeResolver;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Runs situation goldens against TurnRunner with an isolated sandbox API key.
 */
class EvalRunner
{
    public function __construct(
        private TurnRunner $runner,
    ) {}

    /**
     * @param  list<GoldenCase>|null  $cases
     * @return array{
     *     version: string,
     *     brain_version: string,
     *     passed: int,
     *     failed: int,
     *     skipped: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function run(?array $cases = null, ?string $onlyId = null): array
    {
        $cases ??= SituationGoldens::all();
        if ($onlyId !== null && $onlyId !== '') {
            $cases = array_values(array_filter(
                $cases,
                fn (GoldenCase $c) => strcasecmp($c->id, $onlyId) === 0,
            ));
            if ($cases === []) {
                throw new InvalidArgumentException("Unknown golden id: {$onlyId}");
            }
        }

        $generated = WiseApiKey::generate('eval-goldens-'.Str::lower(Str::random(6)));
        /** @var WiseApiKey $apiKey */
        $apiKey = $generated['key'];
        $apiKey->update([
            'meta' => array_merge($apiKey->meta ?? [], [
                'sandbox' => true,
                'governance' => ['sandbox' => true],
            ]),
        ]);
        $apiKey->refresh();

        $results = [];
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        KnowledgeResolver::excludePlatform(true);

        try {
            foreach ($cases as $case) {
                $this->purgeKeyData($apiKey);

                if ($case->skip) {
                    $skipped++;
                    $results[] = [
                        'id' => $case->id,
                        'name' => $case->name,
                        'status' => 'skipped',
                        'reason' => $case->skipReason,
                    ];
                    continue;
                }

                try {
                    foreach ($case->seeds as $seed) {
                        $this->seedItem($apiKey, $seed);
                    }

                    $conversationId = 'eval-'.$case->id.'-'.Str::random(8);
                    foreach ($case->prior as $prior) {
                        $this->runner->run($apiKey, IncomingTurn::fromPayload([
                            'text' => $prior['text'],
                            'channel' => 'eval',
                            'conversation_id' => $conversationId,
                            'context' => $prior['context'] ?? [],
                        ]));
                    }

                    $run = $this->runner->run($apiKey, IncomingTurn::fromPayload([
                        'text' => $case->text,
                        'channel' => 'eval',
                        'conversation_id' => $conversationId,
                        'context' => $case->context,
                    ]));

                    $decision = $run['decision'];
                    $evidence = $run['turn']->evidence ?? [];
                    $errors = $this->assertExpectations($case, $decision, is_array($evidence) ? $evidence : []);

                    if ($errors === []) {
                        $passed++;
                        $results[] = [
                            'id' => $case->id,
                            'name' => $case->name,
                            'status' => 'passed',
                            'action' => $decision['action'] ?? null,
                            'intent' => $decision['intent'] ?? null,
                        ];
                    } else {
                        $failed++;
                        $results[] = [
                            'id' => $case->id,
                            'name' => $case->name,
                            'status' => 'failed',
                            'errors' => $errors,
                            'got' => [
                                'intent' => $decision['intent'] ?? null,
                                'action' => $decision['action'] ?? null,
                                'gap' => $decision['gap'] ?? null,
                                'source' => $decision['source'] ?? null,
                                'missing_context' => $decision['missing_context'] ?? null,
                                'memory_used' => $decision['memory_used'] ?? null,
                                'suggested_reply' => $decision['suggested_reply'] ?? null,
                                'pricing_menu' => $evidence['pricing_menu'] ?? false,
                            ],
                        ];
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $results[] = [
                        'id' => $case->id,
                        'name' => $case->name,
                        'status' => 'error',
                        'errors' => [$e->getMessage()],
                    ];
                }
            }
        } finally {
            KnowledgeResolver::excludePlatform(false);
            $this->purgeKeyData($apiKey);
            $apiKey->delete();
        }

        return [
            'version' => SituationGoldens::VERSION,
            'brain_version' => \App\WiseAi\DecideEngine::BRAIN_VERSION,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function assertExpectations(GoldenCase $case, array $decision, array $evidence): array
    {
        $errors = [];
        $expect = $case->expect;

        if (isset($expect['intent']) && ($decision['intent'] ?? null) !== $expect['intent']) {
            $errors[] = 'intent expected '.$expect['intent'].' got '.($decision['intent'] ?? 'null');
        }

        if (isset($expect['actions_any'])) {
            $action = (string) ($decision['action'] ?? '');
            if (! in_array($action, $expect['actions_any'], true)) {
                $errors[] = 'action expected one of ['.implode(',', $expect['actions_any']).'] got '.$action;
            }
        } elseif (isset($expect['action']) && ($decision['action'] ?? null) !== $expect['action']) {
            $errors[] = 'action expected '.$expect['action'].' got '.($decision['action'] ?? 'null');
        }

        if (array_key_exists('gap', $expect) && (bool) ($decision['gap'] ?? false) !== (bool) $expect['gap']) {
            $errors[] = 'gap expected '.json_encode($expect['gap']).' got '.json_encode((bool) ($decision['gap'] ?? false));
        }

        if (array_key_exists('memory_used', $expect)
            && (bool) ($decision['memory_used'] ?? false) !== (bool) $expect['memory_used']) {
            $errors[] = 'memory_used expected '.json_encode($expect['memory_used']);
        }

        if (array_key_exists('missing_context', $expect)
            && ($decision['missing_context'] ?? null) !== $expect['missing_context']) {
            $errors[] = 'missing_context expected '.json_encode($expect['missing_context'])
                .' got '.json_encode($decision['missing_context'] ?? null);
        }

        if (isset($expect['source']) && ($decision['source'] ?? null) !== $expect['source']) {
            $errors[] = 'source expected '.$expect['source'].' got '.($decision['source'] ?? 'null');
        }

        if (! empty($expect['reply_non_empty'])) {
            $reply = trim((string) ($decision['suggested_reply'] ?? ''));
            if ($reply === '') {
                $errors[] = 'suggested_reply expected non-empty gap assist';
            }
        }

        if (array_key_exists('pricing_menu', $expect)
            && (bool) ($evidence['pricing_menu'] ?? false) !== (bool) $expect['pricing_menu']) {
            $errors[] = 'pricing_menu expected '.json_encode($expect['pricing_menu']);
        }

        if (! empty($expect['reply_contains_all'])) {
            $reply = mb_strtolower((string) ($decision['suggested_reply'] ?? ''));
            foreach ($expect['reply_contains_all'] as $needle) {
                if ($needle === '' || ! str_contains($reply, mb_strtolower((string) $needle))) {
                    $errors[] = 'reply missing required “'.$needle.'”';
                }
            }
        }

        if (! empty($expect['reply_contains'])) {
            $reply = mb_strtolower((string) ($decision['suggested_reply'] ?? ''));
            $hit = false;
            foreach ($expect['reply_contains'] as $needle) {
                if ($needle !== '' && str_contains($reply, mb_strtolower((string) $needle))) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                $errors[] = 'reply_contains none of ['.implode('|', $expect['reply_contains']).']';
            }
        }

        if (! empty($expect['reply_contains_any_group'])) {
            // Each group is OR; every group must hit (AND of ORs).
            $reply = mb_strtolower((string) ($decision['suggested_reply'] ?? ''));
            foreach ($expect['reply_contains_any_group'] as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $groupHit = false;
                foreach ($group as $needle) {
                    if ($needle !== '' && str_contains($reply, mb_strtolower((string) $needle))) {
                        $groupHit = true;
                        break;
                    }
                }
                if (! $groupHit) {
                    $errors[] = 'reply missing one of ['.implode('|', $group).']';
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $seed
     */
    private function seedItem(WiseApiKey $apiKey, array $seed): void
    {
        WiseKnowledgeItem::create([
            'wise_api_key_id' => $apiKey->id,
            'type' => $seed['type'],
            'scope' => $seed['scope'] ?? 'merchant',
            'title' => $seed['title'],
            'question' => $seed['question'] ?? null,
            'answer' => $seed['answer'],
            'keywords' => $seed['keywords'] ?? [],
            'external_id' => $seed['external_id'] ?? null,
            'meta' => $seed['meta'] ?? null,
            'status' => 'published',
            'version' => 1,
        ]);
    }

    private function purgeKeyData(WiseApiKey $apiKey): void
    {
        WiseTurn::query()->where('wise_api_key_id', $apiKey->id)->delete();
        WiseKnowledgeItem::query()->where('wise_api_key_id', $apiKey->id)->delete();
    }
}
