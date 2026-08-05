<?php

namespace App\WiseAi\Learning;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Knowledge\KnowledgeSchema;
use Illuminate\Support\Facades\DB;

/**
 * Guided self-heal: gap → knowledge draft (never publish).
 * Answer is only the sealed gap_assist / suggested_reply — no invented facts.
 */
class GapAutoDrafter
{
    public const META_SOURCE = 'gap_auto_draft';

    /**
     * @param  array<string, mixed>  $decision
     */
    public function maybeDraft(WiseTurn $turn, WiseApiKey $apiKey, array $decision): ?WiseKnowledgeItem
    {
        if (! $turn->gap) {
            return null;
        }

        if ($turn->gap_auto_draft_id !== null || $turn->gap_knowledge_id !== null) {
            return $turn->gapAutoDraft;
        }

        $question = trim((string) $turn->text);
        if ($question === '') {
            return null;
        }

        $answer = trim((string) ($decision['suggested_reply'] ?? ''));
        if ($answer === '') {
            return null;
        }

        // Soft templates only — never invent fee digits into a new draft.
        if (preg_match('/\d+\s*(?:tk|taka|bdt|টাকা)/iu', $answer)) {
            return null;
        }

        $intent = (string) ($decision['intent'] ?? 'unknown');
        $title = $this->titleFrom($question, $intent);

        return DB::transaction(function () use ($turn, $apiKey, $question, $answer, $intent, $title) {
            $locked = WiseTurn::query()->whereKey($turn->id)->lockForUpdate()->firstOrFail();
            if ($locked->gap_auto_draft_id !== null || $locked->gap_knowledge_id !== null) {
                return $locked->gapAutoDraft;
            }

            $item = WiseKnowledgeItem::create([
                'wise_api_key_id' => $apiKey->id,
                'type' => KnowledgeSchema::KIND_FAQ,
                'scope' => KnowledgeSchema::SCOPE_MERCHANT,
                'title' => $title,
                'question' => $question,
                'answer' => $answer,
                'keywords' => $this->keywordsFrom($question, $intent),
                'meta' => [
                    'source' => self::META_SOURCE,
                    'wise_turn_id' => (int) $locked->id,
                    'intent' => $intent,
                    'auto_draft' => true,
                ],
                'status' => 'draft',
                'version' => 1,
            ]);

            $locked->update(['gap_auto_draft_id' => $item->id]);
            $turn->gap_auto_draft_id = $item->id;

            return $item;
        });
    }

    private function titleFrom(string $question, string $intent): string
    {
        $clip = mb_strlen($question) > 72 ? mb_substr($question, 0, 71).'…' : $question;

        return 'Gap · '.$intent.' · '.$clip;
    }

    /**
     * @return list<string>
     */
    private function keywordsFrom(string $question, string $intent): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower($question)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p, " \t\n\r\0\x0B?.,!;:\"'");
            if ($p === '' || mb_strlen($p) < 3) {
                continue;
            }
            $out[] = $p;
            if (count($out) >= 8) {
                break;
            }
        }
        if ($intent !== '' && $intent !== 'unknown') {
            array_unshift($out, $intent);
        }

        return array_values(array_unique($out));
    }
}
