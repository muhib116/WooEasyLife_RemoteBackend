<?php

namespace App\WiseAi\Memory;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseConversationMemory;
use App\Models\WiseAi\WiseTurn;

/**
 * Soft memory — preferences / interested product / language (not published FAQ).
 */
final class SoftMemoryExtractor
{
    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $pack
     */
    public function persist(
        WiseApiKey $apiKey,
        ?string $conversationId,
        WiseTurn $turn,
        array $decision,
        array $pack,
    ): void {
        if ($conversationId === null || trim($conversationId) === '') {
            return;
        }

        $assist = is_array($decision['grounded_assist'] ?? null) ? $decision['grounded_assist'] : [];
        $summary = is_string($pack['conversation_summary'] ?? null)
            ? $pack['conversation_summary']
            : ($assist['conversation_summary'] ?? null);
        $goal = is_string($pack['goal'] ?? null) ? $pack['goal'] : ($assist['goal'] ?? null);

        $prefs = [];
        if (! empty($pack['customer']['language_pref'])) {
            $prefs['language_pref'] = (string) $pack['customer']['language_pref'];
        }
        if (! empty($pack['product_subject']['title'])) {
            $prefs['interested_product'] = (string) $pack['product_subject']['title'];
        }
        if (! empty($pack['signals']['emotion'])) {
            $prefs['last_emotion'] = (string) $pack['signals']['emotion'];
        }

        $row = WiseConversationMemory::query()->firstOrNew([
            'wise_api_key_id' => $apiKey->id,
            'conversation_id' => $conversationId,
        ]);

        if (is_string($summary) && trim($summary) !== '') {
            $row->summary = mb_substr(trim($summary), 0, (int) config('wise_ai.conversation_memory.summary_max_chars', 800));
        }
        if (is_string($goal) && trim($goal) !== '') {
            $row->goal = mb_substr(trim($goal), 0, 40);
        }

        $existing = is_array($row->preferences) ? $row->preferences : [];
        $row->preferences = array_merge($existing, $prefs);
        $row->last_turn_id = (int) $turn->id;
        $row->save();
    }
}
