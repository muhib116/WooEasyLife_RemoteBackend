<?php

namespace App\WiseAi\Knowledge\Search;

use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\KnowledgeSchema;

/**
 * Builds Meili/in-memory documents. Answer is never searchable.
 */
final class KnowledgeSearchDocument
{
    /** @var list<string> */
    public const SEARCHABLE = ['match_text', 'title', 'question', 'keywords'];

    /** @var list<string> */
    public const FILTERABLE = ['status', 'type', 'scope', 'wise_api_key_id', 'external_id'];

    /**
     * @return array<string, mixed>|null  Null when row should not be indexed.
     */
    public static function fromItem(WiseKnowledgeItem $item): ?array
    {
        if ($item->status !== 'published' || ! KnowledgeSchema::isGroundable((string) $item->type)) {
            return null;
        }

        $keywords = $item->keywords ?? [];
        $keywordsFlat = is_array($keywords)
            ? implode(' ', array_map(static fn ($k) => (string) $k, $keywords))
            : (string) $keywords;

        return [
            'id' => (int) $item->id,
            'match_text' => (string) ($item->match_text ?? ''),
            'title' => (string) $item->title,
            'question' => (string) ($item->question ?? ''),
            'keywords' => $keywordsFlat,
            'status' => (string) $item->status,
            'type' => (string) $item->type,
            'scope' => (string) ($item->scope ?: KnowledgeSchema::SCOPE_MERCHANT),
            'wise_api_key_id' => $item->wise_api_key_id !== null ? (int) $item->wise_api_key_id : null,
            'external_id' => $item->external_id !== null ? (string) $item->external_id : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function assertNoSearchableAnswer(array $document): void
    {
        if (array_key_exists('answer', $document)) {
            throw new \InvalidArgumentException('Knowledge search documents must not include answer.');
        }
    }
}
