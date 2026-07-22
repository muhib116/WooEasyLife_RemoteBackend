<?php

namespace App\Services\BlogAi;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * Build our-post snapshots and measure competitor gap checklist coverage in drafts.
 */
class BlogCompetitorGapService
{
    /**
     * Find a matching WooEasyLife post for the keyword.
     * Exact focus_keyword only — cluster-wide fallback skews gap coverage.
     */
    public function findOurPost(string $keyword, ?string $cluster = null): ?BlogPost
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }

        $key = mb_strtolower($keyword);

        $query = BlogPost::query()
            ->whereRaw('LOWER(focus_keyword) = ?', [$key])
            ->orderByRaw("CASE WHEN status = 'published' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        // Prefer same cluster when multiple posts share the focus keyword.
        if (filled($cluster)) {
            $sameCluster = (clone $query)->where('cluster', $cluster)->first();
            if ($sameCluster) {
                return $sameCluster;
            }
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ourSnapshot(?BlogPost $post): ?array
    {
        if (! $post) {
            return null;
        }

        $body = (string) ($post->body_html ?? '');
        $plain = $this->htmlToPlain($body);
        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $headings = [];
        if (preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h\1>/is', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $text = $this->cleanText(strip_tags($match[2]));
                if ($text !== '') {
                    $headings[] = $text;
                }
                if (count($headings) >= 30) {
                    break;
                }
            }
        }

        $faqs = collect(is_array($post->faqs_json) ? $post->faqs_json : [])
            ->map(fn ($row) => is_array($row) ? trim((string) ($row['q'] ?? '')) : '')
            ->filter()
            ->take(12)
            ->values()
            ->all();

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'focus_keyword' => $post->focus_keyword,
            'cluster' => $post->cluster,
            'headings' => $headings,
            'faqs' => $faqs,
            'word_count' => count($words),
            'excerpt' => $plain !== '' ? Str::limit($plain, 2500, '…') : null,
        ];
    }

    /**
     * Normalize LLM gap_checklist rows.
     *
     * @param  mixed  $raw
     * @return list<array{id: string, gap: string, why: string|null, status: string, evidence: string|null}>
     */
    public function normalizeGapChecklist(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $i = 1;
        foreach ($raw as $row) {
            if (is_string($row)) {
                $gap = trim($row);
                if ($gap === '') {
                    continue;
                }
                $out[] = [
                    'id' => 'g'.$i,
                    'gap' => Str::limit($gap, 220, ''),
                    'why' => null,
                    'status' => 'open',
                    'evidence' => null,
                ];
                $i++;
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $gap = trim((string) ($row['gap'] ?? $row['item'] ?? $row['text'] ?? ''));
            if ($gap === '') {
                continue;
            }
            $status = strtolower(trim((string) ($row['status'] ?? 'open')));
            if (! in_array($status, ['open', 'covered', 'partial'], true)) {
                $status = 'open';
            }
            $out[] = [
                'id' => filled($row['id'] ?? null) ? (string) $row['id'] : 'g'.$i,
                'gap' => Str::limit($gap, 220, ''),
                'why' => filled($row['why'] ?? null) ? Str::limit((string) $row['why'], 220, '') : null,
                'status' => $status,
                'evidence' => filled($row['evidence'] ?? null) ? Str::limit((string) $row['evidence'], 220, '') : null,
            ];
            $i++;
            if (count($out) >= 16) {
                break;
            }
        }

        return $out;
    }

    /**
     * Build open-only checklist strings for prompts.
     *
     * @param  list<array{id?: string, gap?: string, status?: string}>  $checklist
     * @return list<string>
     */
    public function openGapTexts(array $checklist): array
    {
        $out = [];
        foreach ($checklist as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = strtolower((string) ($row['status'] ?? 'open'));
            if ($status === 'covered') {
                continue;
            }
            $gap = trim((string) ($row['gap'] ?? ''));
            if ($gap !== '') {
                $out[] = $gap;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Soft coverage of checklist items in a draft body / FAQs.
     *
     * @param  list<array{gap?: string, status?: string}|string>  $checklist
     * @param  list<array{q?: string, a?: string}>  $faqs
     * @return array{
     *     total: int,
     *     covered: int,
     *     pct: int|null,
     *     items: list<array{gap: string, hit: bool}>
     * }
     */
    public function measureCoverage(array $checklist, string $bodyHtml, array $faqs = [], ?string $title = null): array
    {
        $haystackParts = [
            (string) $title,
            $this->htmlToPlain($bodyHtml),
        ];
        foreach ($faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $haystackParts[] = (string) ($faq['q'] ?? '');
            $haystackParts[] = (string) ($faq['a'] ?? '');
        }
        $haystack = mb_strtolower(implode(' ', $haystackParts));

        $items = [];
        $covered = 0;
        foreach ($checklist as $row) {
            $gap = is_array($row)
                ? trim((string) ($row['gap'] ?? ''))
                : trim((string) $row);
            if ($gap === '') {
                continue;
            }
            $hit = $this->textLikelyCovers($haystack, $gap);
            if ($hit) {
                $covered++;
            }
            $items[] = ['gap' => $gap, 'hit' => $hit];
        }

        $total = count($items);

        return [
            'total' => $total,
            'covered' => $covered,
            'pct' => $total > 0 ? (int) round(($covered / $total) * 100) : null,
            'items' => $items,
        ];
    }

    private function textLikelyCovers(string $haystackLower, string $gap): bool
    {
        $gapLower = mb_strtolower(trim($gap));
        if ($gapLower === '' || $haystackLower === '') {
            return false;
        }

        if (mb_strpos($haystackLower, $gapLower) !== false) {
            return true;
        }

        // Token overlap for Bangla/English hybrid phrases (short tokens skipped).
        $tokens = preg_split('/[\s,.;:|\/\-]+/u', $gapLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= 4));
        if ($tokens === []) {
            return false;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (mb_strpos($haystackLower, $token) !== false) {
                $hits++;
            }
        }

        return ($hits / count($tokens)) >= 0.6;
    }

    private function htmlToPlain(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|iframe)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->cleanText($text);
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
