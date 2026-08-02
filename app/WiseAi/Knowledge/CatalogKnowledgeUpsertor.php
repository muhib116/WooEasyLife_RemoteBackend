<?php

namespace App\WiseAi\Knowledge;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Public catalog sync — adapters upsert offer rows; never auto-publish.
 *
 * Upsert key: (wise_api_key_id, meta.platform, external_id) for type=product.
 * Content or identity change → draft + version bump (same rule as admin update).
 */
class CatalogKnowledgeUpsertor
{
    public const SCHEMA_VERSION = '1.0';

    private const OFFER_KINDS = ['physical', 'digital', 'service', 'subscription', 'other'];

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
        $externalId = trim((string) ($payload['external_id'] ?? ''));
        if ($externalId === '') {
            throw new InvalidArgumentException('external_id is required.');
        }
        if (strlen($externalId) > 191) {
            throw new InvalidArgumentException('external_id max length is 191.');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('title is required.');
        }

        $answer = trim((string) ($payload['answer'] ?? ''));
        if ($answer === '') {
            throw new InvalidArgumentException('answer is required.');
        }

        $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
        if (strlen($platform) > 40) {
            throw new InvalidArgumentException('platform max length is 40.');
        }

        $offerKind = strtolower(trim((string) ($payload['offer_kind'] ?? 'physical')));
        if ($offerKind === '') {
            $offerKind = 'physical';
        }
        if (! in_array($offerKind, self::OFFER_KINDS, true)) {
            throw new InvalidArgumentException('Invalid offer_kind.');
        }

        $sku = trim((string) ($payload['sku'] ?? ''));
        $question = isset($payload['question']) ? trim((string) $payload['question']) : null;
        if ($question === '') {
            $question = null;
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
            'offer_kind' => $offerKind,
            'synced_via' => 'catalog_api',
            'catalog_schema_version' => self::SCHEMA_VERSION,
        ];
        if ($platform !== '') {
            $meta['platform'] = $platform;
        }
        if ($sku !== '') {
            $meta['sku'] = mb_substr($sku, 0, 64);
        }

        return DB::transaction(function () use (
            $apiKey,
            $externalId,
            $platform,
            $title,
            $answer,
            $question,
            $keywords,
            $meta,
            $sku,
            $offerKind,
        ) {
            $existing = $this->findExisting($apiKey->id, $externalId, $platform);

            if (! $existing) {
                $item = WiseKnowledgeItem::create([
                    'wise_api_key_id' => $apiKey->id,
                    'external_id' => $externalId,
                    'type' => KnowledgeSchema::KIND_OFFER,
                    'scope' => KnowledgeSchema::SCOPE_MERCHANT,
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
            $prevPlatform = strtolower(trim((string) ($prevMeta['platform'] ?? '')));
            $prevSku = trim((string) ($prevMeta['sku'] ?? ''));
            $prevKind = strtolower(trim((string) ($prevMeta['offer_kind'] ?? '')));

            $sameContent =
                (string) $existing->title === $title
                && (string) $existing->answer === $answer
                && (string) ($existing->question ?? '') === (string) ($question ?? '')
                && $this->keywordsEqual($existing->keywords ?? [], $keywords)
                && $prevPlatform === $platform
                && $prevSku === $sku
                && $prevKind === $offerKind
                && (string) $existing->external_id === $externalId
                && (string) $existing->type === KnowledgeSchema::KIND_OFFER;

            if ($sameContent) {
                return [
                    'item' => $existing,
                    'created' => false,
                    'changed' => false,
                    'unpublished' => false,
                ];
            }

            // Preserve merchant/admin meta keys; overwrite catalog identity fields only.
            $mergedMeta = $prevMeta;
            foreach (['platform', 'sku', 'offer_kind', 'synced_via', 'catalog_schema_version'] as $wipe) {
                unset($mergedMeta[$wipe]);
            }
            $mergedMeta = array_merge($mergedMeta, $meta);

            $existing->fill([
                'external_id' => $externalId,
                'type' => KnowledgeSchema::KIND_OFFER,
                'scope' => KnowledgeSchema::SCOPE_MERCHANT,
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

    private function findExisting(int $apiKeyId, string $externalId, string $platform): ?WiseKnowledgeItem
    {
        $query = WiseKnowledgeItem::query()
            ->where('wise_api_key_id', $apiKeyId)
            ->where('type', KnowledgeSchema::KIND_OFFER)
            ->where('external_id', $externalId)
            ->lockForUpdate();

        if ($platform !== '') {
            $query->where('meta->platform', $platform);
        } else {
            $query->where(function ($q) {
                $q->whereNull('meta->platform')
                    ->orWhere('meta->platform', '');
            });
        }

        return $query->first();
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
