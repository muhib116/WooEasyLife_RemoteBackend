<?php

namespace App\WiseAi\Knowledge;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Knowledge\Search\KnowledgeSearchManager;
use Illuminate\Support\Collection;

/**
 * Related-question growth — suggests existing FAQ questions / sibling gap texts.
 * Never invents answers. Answers are omitted from the response contract.
 */
class RelatedQuestionSuggester
{
    public const VERSION = 'related-q-1.0';

    public function __construct(
        private KnowledgeSearchManager $search,
    ) {}

    /**
     * @return array{
     *     version: string,
     *     items: list<array{
     *         question: string,
     *         title: ?string,
     *         knowledge_id: ?int,
     *         status: ?string,
     *         score: int,
     *         reason: string
     *     }>
     * }
     */
    public function forTurn(WiseTurn $turn, ?WiseApiKey $apiKey = null, int $limit = 8): array
    {
        $apiKey ??= $turn->apiKey;
        $text = trim((string) $turn->text);
        $intent = (string) ($turn->decision['intent'] ?? 'unknown');
        $limit = max(1, min(20, $limit));

        if ($text === '' || $apiKey === null) {
            return ['version' => self::VERSION, 'items' => []];
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();

        foreach ($this->fromPublished($apiKey, $text, $intent, $limit) as $row) {
            $rows->push($row);
        }

        foreach ($this->fromSiblingGaps($apiKey, $turn, $text, $intent, $limit) as $row) {
            $key = mb_strtolower((string) $row['question']);
            if ($rows->contains(fn ($r) => mb_strtolower((string) $r['question']) === $key)) {
                continue;
            }
            $rows->push($row);
        }

        $items = $rows
            ->sortByDesc(fn (array $r) => $r['score'])
            ->unique(fn (array $r) => mb_strtolower((string) $r['question']))
            ->take($limit)
            ->values()
            ->all();

        return [
            'version' => self::VERSION,
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromPublished(WiseApiKey $apiKey, string $text, string $intent, int $limit): array
    {
        $ids = $this->search->search($text, [
            'status' => 'published',
            'types' => KnowledgeSchema::groundableKinds(),
            'wise_api_key_id' => (int) $apiKey->id,
            'exclude_platform' => false,
        ], $limit * 2);

        $query = WiseKnowledgeItem::query()
            ->where('status', 'published')
            ->whereIn('type', KnowledgeSchema::groundableKinds())
            ->where(function ($q) use ($apiKey) {
                $q->where('wise_api_key_id', $apiKey->id)
                    ->orWhere(function ($p) {
                        $p->whereNull('wise_api_key_id')
                            ->where('scope', KnowledgeSchema::SCOPE_PLATFORM);
                    });
            });

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $tokens = $this->tokens($text);
            if ($tokens === []) {
                return [];
            }
            $query->where(function ($q) use ($tokens) {
                foreach (array_slice($tokens, 0, 5) as $token) {
                    $like = '%'.$token.'%';
                    $q->orWhere('match_text', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('question', 'like', $like);
                }
            });
        }

        $out = [];
        foreach ($query->orderByDesc('id')->limit($limit * 3)->get() as $item) {
            $question = trim((string) ($item->question ?: $item->title));
            if ($question === '') {
                continue;
            }
            $score = $this->scoreOverlap($text, $question.' '.$item->title, $intent, (string) ($item->type ?? ''));
            if ($score < 20) {
                continue;
            }
            $out[] = [
                'question' => $question,
                'title' => (string) $item->title,
                'knowledge_id' => (int) $item->id,
                'status' => 'published',
                'score' => $score,
                'reason' => 'published_faq',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromSiblingGaps(WiseApiKey $apiKey, WiseTurn $turn, string $text, string $intent, int $limit): array
    {
        $tokens = $this->tokens($text);
        if ($tokens === []) {
            return [];
        }

        $q = WiseTurn::query()
            ->where('wise_api_key_id', $apiKey->id)
            ->where('gap', true)
            ->where('id', '!=', $turn->id)
            ->whereNull('gap_handled_at')
            ->latest('id')
            ->limit(40);

        $out = [];
        foreach ($q->get(['id', 'text', 'decision']) as $sibling) {
            $qText = trim((string) $sibling->text);
            if ($qText === '' || mb_strtolower($qText) === mb_strtolower($text)) {
                continue;
            }
            $sibIntent = (string) ($sibling->decision['intent'] ?? '');
            $score = $this->scoreOverlap($text, $qText, $intent, $sibIntent);
            if ($intent !== 'unknown' && $sibIntent === $intent) {
                $score += 15;
            }
            if ($score < 25) {
                continue;
            }
            $out[] = [
                'question' => $qText,
                'title' => null,
                'knowledge_id' => null,
                'status' => null,
                'score' => $score,
                'reason' => 'sibling_gap',
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower($text)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p, " \t\n\r\0\x0B?.,!;:\"'");
            if ($p === '' || mb_strlen($p) < 3) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    private function scoreOverlap(string $a, string $b, string $intent, string $hint): int
    {
        $ta = $this->tokens($a);
        $tb = $this->tokens($b);
        if ($ta === [] || $tb === []) {
            return 0;
        }
        $overlap = count(array_intersect($ta, $tb));
        $score = (int) round(100 * $overlap / max(count($ta), 1));
        if ($intent !== '' && $intent !== 'unknown' && str_contains(mb_strtolower($hint.' '.$b), mb_strtolower($intent))) {
            $score += 10;
        }

        return min(100, $score);
    }
}
