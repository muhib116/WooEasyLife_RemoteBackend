<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguageEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Upsert merchant language overlay from Playground Coach (human-gated).
 * May teach AMBIGUOUS surfaces (e.g. pp) only as merchant + human_approved meta.
 */
final class MerchantLanguageEntryWriter
{
    public const META_SOURCE = 'playground_coach';

    /**
     * @param  array{
     *     type: string,
     *     from: string,
     *     to: string,
     *     publish?: bool,
     *     wise_turn_id?: int|null
     * }  $input
     */
    public function upsert(WiseApiKey $apiKey, array $input): WiseLanguageEntry
    {
        $type = (string) ($input['type'] ?? 'abbrev');
        $from = mb_strtolower(trim((string) ($input['from'] ?? '')));
        $to = trim((string) ($input['to'] ?? ''));
        $publish = (bool) ($input['publish'] ?? false);
        $turnId = isset($input['wise_turn_id']) ? (int) $input['wise_turn_id'] : null;

        if ($from === '') {
            throw new InvalidArgumentException('language.from is required.');
        }
        if ($type === 'filler') {
            throw new InvalidArgumentException('Playground Coach does not teach filler strips.');
        }
        if ($to === '') {
            throw new InvalidArgumentException('language.to is required.');
        }
        if (! in_array($type, ['abbrev', 'banglish', 'sms', 'commerce', 'messenger', 'phonetic'], true)) {
            throw new InvalidArgumentException('Unsupported language type.');
        }

        $entry = DB::transaction(function () use ($apiKey, $type, $from, $to, $publish, $turnId) {
            $locked = WiseLanguageEntry::query()
                ->where('wise_api_key_id', $apiKey->id)
                ->where('type', $type)
                ->where('from_text', $from)
                ->lockForUpdate()
                ->first();

            $meta = [
                'source' => self::META_SOURCE,
                'human_approved' => true,
            ];
            if ($turnId) {
                $meta['wise_turn_id'] = $turnId;
            }

            if ($locked) {
                $meta = array_merge(is_array($locked->meta) ? $locked->meta : [], $meta);
                // Never silently demote a live map on "Save draft" — only publish promotes.
                $nextStatus = $publish
                    ? 'published'
                    : ($locked->status === 'published' ? 'published' : 'draft');
                $locked->fill([
                    'to_text' => $to,
                    'status' => $nextStatus,
                    'enabled' => true,
                    'version' => max(1, ((int) $locked->version) + 1),
                    'meta' => $meta,
                ]);
                $locked->save();

                return $locked;
            }

            return WiseLanguageEntry::create([
                'wise_api_key_id' => $apiKey->id,
                'type' => $type,
                'from_text' => $from,
                'to_text' => $to,
                'status' => $publish ? 'published' : 'draft',
                'enabled' => true,
                'version' => 1,
                'meta' => $meta,
            ]);
        });

        LanguageNormalizer::forgetEntryCache($apiKey->id);

        return $entry->fresh() ?? $entry;
    }
}
