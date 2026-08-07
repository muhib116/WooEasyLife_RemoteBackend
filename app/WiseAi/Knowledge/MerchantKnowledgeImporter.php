<?php

namespace App\WiseAi\Knowledge;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Public adapter import for Messenger SST → hub knowledge drafts.
 *
 * Upsert key: meta.messenger_key (stable). Never auto-publishes.
 * Offer-scoped FAQs use external_id = WC product id so KnowledgeResolver can bind them.
 */
class MerchantKnowledgeImporter
{
    public const SCHEMA_VERSION = '1.0';

    private const ALLOWED_TYPES = [
        KnowledgeSchema::KIND_FAQ,
        KnowledgeSchema::KIND_POLICY,
        KnowledgeSchema::KIND_FACT,
        KnowledgeSchema::KIND_SCRIPT,
        KnowledgeSchema::KIND_OFFER,
    ];

    private const ALLOWED_SCOPES = [
        KnowledgeSchema::SCOPE_MERCHANT,
        KnowledgeSchema::SCOPE_OFFER,
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     item: WiseKnowledgeItem,
     *     created: bool,
     *     changed: bool,
     *     unpublished: bool
     * }
     */
    public function upsert(WiseApiKey $apiKey, array $payload): array
    {
        $messengerKey = trim((string) ($payload['messenger_key'] ?? $payload['source_key'] ?? ''));
        if ($messengerKey === '') {
            throw new InvalidArgumentException('messenger_key is required.');
        }
        if (strlen($messengerKey) > 191) {
            throw new InvalidArgumentException('messenger_key max length is 191.');
        }

        $type = strtolower(trim((string) ($payload['type'] ?? KnowledgeSchema::KIND_FAQ)));
        if ($type === 'offer') {
            $type = KnowledgeSchema::KIND_OFFER;
        }
        if ($type === KnowledgeSchema::KIND_OTHER) {
            $type = KnowledgeSchema::KIND_FACT;
        }
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid type. Allowed: faq, policy, fact, script, product.');
        }

        $scope = strtolower(trim((string) ($payload['scope'] ?? KnowledgeSchema::SCOPE_MERCHANT)));
        if (! in_array($scope, self::ALLOWED_SCOPES, true)) {
            throw new InvalidArgumentException('Invalid scope. Allowed: merchant, offer.');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('title is required.');
        }

        $answer = trim((string) ($payload['answer'] ?? ''));
        if ($answer === '') {
            throw new InvalidArgumentException('answer is required.');
        }

        $question = isset($payload['question']) ? trim((string) $payload['question']) : null;
        if ($question === '') {
            $question = null;
        }

        $externalId = trim((string) ($payload['external_id'] ?? ''));
        if ($scope === KnowledgeSchema::SCOPE_OFFER && $externalId === '') {
            throw new InvalidArgumentException('offer scope requires external_id (WC product id).');
        }
        if ($type === KnowledgeSchema::KIND_OFFER && $externalId === '') {
            throw new InvalidArgumentException('type=product requires external_id.');
        }
        if (strlen($externalId) > 191) {
            throw new InvalidArgumentException('external_id max length is 191.');
        }

        $keywords = $payload['keywords'] ?? [];
        if (! is_array($keywords)) {
            throw new InvalidArgumentException('keywords must be an array.');
        }
        $keywords = array_values(array_unique(array_filter(array_map(
            static fn ($k) => mb_substr(trim((string) $k), 0, 60),
            $keywords
        ), static fn ($k) => $k !== '')));

        $meta = [
            'messenger_key' => $messengerKey,
            'synced_via' => 'messenger_knowledge',
            'messenger_schema_version' => self::SCHEMA_VERSION,
        ];
        foreach (['platform', 'sku', 'offer_kind', 'chunk', 'wc_product_id'] as $field) {
            if (! empty($payload[$field]) || (isset($payload[$field]) && $payload[$field] === 0)) {
                $meta[$field] = mb_substr(trim((string) $payload[$field]), 0, 64);
            }
        }
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            foreach ($payload['meta'] as $k => $v) {
                if (! is_string($k) || $k === '' || in_array($k, ['messenger_key', 'synced_via', 'messenger_schema_version'], true)) {
                    continue;
                }
                if (is_scalar($v) || $v === null) {
                    $meta[$k] = is_string($v) ? mb_substr($v, 0, 120) : $v;
                }
            }
        }

        return DB::transaction(function () use (
            $apiKey,
            $messengerKey,
            $type,
            $scope,
            $title,
            $answer,
            $question,
            $keywords,
            $externalId,
            $meta,
        ) {
            $existing = WiseKnowledgeItem::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('meta->messenger_key', $messengerKey)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                $item = WiseKnowledgeItem::create([
                    'wise_api_key_id' => $apiKey->id,
                    'external_id' => $externalId !== '' ? $externalId : null,
                    'type' => $type,
                    'scope' => $scope,
                    'title' => mb_substr($title, 0, 191),
                    'question' => $question !== null ? mb_substr($question, 0, 2000) : null,
                    'answer' => mb_substr($answer, 0, 5000),
                    'keywords' => $keywords,
                    'meta' => $meta,
                    'status' => 'draft',
                    'version' => 1,
                ]);

                return [
                    'item' => $item->fresh(),
                    'created' => true,
                    'changed' => true,
                    'unpublished' => false,
                ];
            }

            $wasPublished = $existing->isPublished();
            $prevMeta = is_array($existing->meta) ? $existing->meta : [];
            $sameContent =
                (string) $existing->title === $title
                && (string) $existing->answer === $answer
                && (string) ($existing->question ?? '') === (string) ($question ?? '')
                && (string) $existing->type === $type
                && (string) ($existing->scope ?: '') === $scope
                && (string) ($existing->external_id ?? '') === $externalId
                && $this->keywordsEqual($existing->keywords ?? [], $keywords)
                && (string) ($prevMeta['messenger_key'] ?? '') === $messengerKey;

            if ($sameContent) {
                return [
                    'item' => $existing,
                    'created' => false,
                    'changed' => false,
                    'unpublished' => false,
                ];
            }

            $mergedMeta = $prevMeta;
            foreach (['messenger_key', 'synced_via', 'messenger_schema_version', 'platform', 'sku', 'offer_kind', 'chunk', 'wc_product_id'] as $wipe) {
                unset($mergedMeta[$wipe]);
            }
            $mergedMeta = array_merge($mergedMeta, $meta);

            $existing->fill([
                'external_id' => $externalId !== '' ? $externalId : null,
                'type' => $type,
                'scope' => $scope,
                'title' => mb_substr($title, 0, 191),
                'question' => $question !== null ? mb_substr($question, 0, 2000) : null,
                'answer' => mb_substr($answer, 0, 5000),
                'keywords' => $keywords,
                'meta' => $mergedMeta,
                'version' => (int) $existing->version + 1,
                'status' => 'draft',
            ]);
            $existing->save();

            return [
                'item' => $existing->fresh(),
                'created' => false,
                'changed' => true,
                'unpublished' => $wasPublished,
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{ok: int, created: int, changed: int, unchanged: int, errors: list<array{index:int,message:string}>}
     */
    public function importMany(WiseApiKey $apiKey, array $items): array
    {
        $stats = [
            'ok' => 0,
            'created' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'errors' => [],
        ];

        foreach (array_values($items) as $i => $raw) {
            if (! is_array($raw)) {
                $stats['errors'][] = ['index' => $i, 'message' => 'Item must be an object.'];

                continue;
            }
            try {
                $result = $this->upsert($apiKey, $raw);
                $stats['ok']++;
                if ($result['created']) {
                    $stats['created']++;
                } elseif ($result['changed']) {
                    $stats['changed']++;
                } else {
                    $stats['unchanged']++;
                }
            } catch (InvalidArgumentException $e) {
                $stats['errors'][] = ['index' => $i, 'message' => $e->getMessage()];
            }
        }

        return $stats;
    }

    /**
     * @param  list<mixed>  $a
     * @param  list<string>  $b
     */
    private function keywordsEqual(array $a, array $b): bool
    {
        $norm = static function (array $list): array {
            $out = array_values(array_unique(array_filter(array_map(
                static fn ($k) => trim((string) $k),
                $list
            ), static fn ($k) => $k !== '')));
            sort($out);

            return $out;
        };

        return $norm($a) === $norm($b);
    }
}
