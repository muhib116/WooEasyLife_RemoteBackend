<?php

namespace App\WiseAi\Knowledge\Search;

/**
 * In-process index for tests (no Meili daemon).
 */
class InMemoryKnowledgeSearchDriver implements KnowledgeSearchDriver
{
    /** @var array<int, array<string, mixed>> */
    private static array $docs = [];

    public static function reset(): void
    {
        self::$docs = [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, array $filters, int $limit): array
    {
        $q = mb_strtolower(trim($query));
        $status = (string) ($filters['status'] ?? 'published');
        $types = $filters['types'] ?? null;
        $keyId = $filters['wise_api_key_id'] ?? null;
        $excludePlatform = (bool) ($filters['exclude_platform'] ?? false);

        $hits = [];
        foreach (self::$docs as $id => $doc) {
            if (($doc['status'] ?? '') !== $status) {
                continue;
            }
            if (is_array($types) && $types !== [] && ! in_array($doc['type'] ?? '', $types, true)) {
                continue;
            }
            $docKey = $doc['wise_api_key_id'] ?? null;
            if ($excludePlatform) {
                if ($docKey === null || (int) $docKey !== (int) $keyId) {
                    continue;
                }
            } elseif ($keyId !== null) {
                $ok = ((int) $docKey === (int) $keyId)
                    || ($docKey === null && in_array($doc['scope'] ?? '', ['platform', 'region'], true));
                if (! $ok) {
                    continue;
                }
            }

            $hay = mb_strtolower(
                ($doc['match_text'] ?? '').' '.($doc['title'] ?? '').' '.($doc['question'] ?? '').' '.($doc['keywords'] ?? '')
            );
            if ($q === '' || str_contains($hay, $q) || $this->tokenHit($hay, $q)) {
                $hits[] = (int) $id;
            }
        }

        return array_slice($hits, 0, max(1, $limit));
    }

    public function upsert(array $document): void
    {
        KnowledgeSearchDocument::assertNoSearchableAnswer($document);
        $id = (int) ($document['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        self::$docs[$id] = $document;
    }

    public function delete(int $id): void
    {
        unset(self::$docs[$id]);
    }

    public function clear(): void
    {
        self::$docs = [];
    }

    private function tokenHit(string $hay, string $q): bool
    {
        foreach (preg_split('/\s+/u', $q) ?: [] as $token) {
            $token = trim($token);
            if (mb_strlen($token) >= 3 && str_contains($hay, $token)) {
                return true;
            }
        }

        return false;
    }
}
