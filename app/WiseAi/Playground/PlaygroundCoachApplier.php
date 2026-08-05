<?php

namespace App\WiseAi\Playground;

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Knowledge\KnowledgePublisher;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Language\MerchantLanguageEntryWriter;
use App\WiseAi\Learning\GapAutoDrafter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Human apply of a Playground Coach proposal — draft or publish, never silent.
 */
final class PlaygroundCoachApplier
{
    public function __construct(
        private KnowledgePublisher $publisher,
        private MerchantLanguageEntryWriter $languageWriter,
        private KnowledgeSeedValidator $validator = new KnowledgeSeedValidator,
    ) {}

    /**
     * @param  array{
     *     category: string,
     *     publish_now?: bool,
     *     knowledge?: array{title?: string, question?: string, answer?: string, keywords?: list<string>},
     *     language?: array{type?: string, from?: string, to?: string}
     * }  $input
     * @return array{
     *     category: string,
     *     published: bool,
     *     knowledge_item: WiseKnowledgeItem|null,
     *     language_entry: WiseLanguageEntry|null,
     *     turn: WiseTurn
     * }
     */
    public function apply(WiseTurn $turn, WiseApiKey $apiKey, array $input): array
    {
        if ((int) $turn->wise_api_key_id !== (int) $apiKey->id) {
            throw new InvalidArgumentException('Turn does not belong to this API key.');
        }

        $category = (string) ($input['category'] ?? '');
        if (! in_array($category, PlaygroundCoach::CATEGORIES, true)) {
            throw new InvalidArgumentException('Invalid coach category.');
        }

        $publishNow = (bool) ($input['publish_now'] ?? false);

        if ($category === PlaygroundCoach::CATEGORY_NOOP) {
            return [
                'category' => $category,
                'published' => false,
                'knowledge_item' => null,
                'language_entry' => null,
                'turn' => $turn->fresh() ?? $turn,
            ];
        }

        if ($category === PlaygroundCoach::CATEGORY_LANGUAGE_ABBREV) {
            $lang = is_array($input['language'] ?? null) ? $input['language'] : [];
            $to = trim((string) ($lang['to'] ?? ''));
            $toFeeErrors = $this->validator->answerFactGuards($to, 'coach language.to');
            if ($toFeeErrors !== []) {
                throw new InvalidArgumentException(
                    'Invented fee/phone/percent blocked in language expansion — keep abbrev expansions fact-free.'
                );
            }
            $entry = $this->languageWriter->upsert($apiKey, [
                'type' => (string) ($lang['type'] ?? 'abbrev'),
                'from' => (string) ($lang['from'] ?? ''),
                'to' => $to,
                'publish' => $publishNow,
                'wise_turn_id' => (int) $turn->id,
            ]);

            return [
                'category' => $category,
                'published' => $entry->status === 'published',
                'knowledge_item' => null,
                'language_entry' => $entry,
                'turn' => $turn->fresh() ?? $turn,
            ];
        }

        // knowledge_faq
        $knowledge = is_array($input['knowledge'] ?? null) ? $input['knowledge'] : [];
        $title = trim((string) ($knowledge['title'] ?? ''));
        $question = trim((string) ($knowledge['question'] ?? ''));
        $answer = trim((string) ($knowledge['answer'] ?? ''));
        $keywords = [];
        if (isset($knowledge['keywords']) && is_array($knowledge['keywords'])) {
            foreach ($knowledge['keywords'] as $kw) {
                if (is_string($kw) && trim($kw) !== '') {
                    $keywords[] = mb_substr(trim($kw), 0, 60);
                }
            }
        }

        if ($title === '') {
            throw new InvalidArgumentException('knowledge.title is required.');
        }
        if ($answer === '') {
            throw new InvalidArgumentException('knowledge.answer is required.');
        }

        $feeErrors = $this->validator->answerFactGuards($answer, 'coach FAQ');
        if ($feeErrors !== []) {
            throw new InvalidArgumentException(
                'Invented fee/phone/percent blocked — remove store-specific amounts or use refuse phrasing.'
            );
        }

        if ($question === '') {
            $question = (string) $turn->text;
        }

        [$item, $freshTurn] = DB::transaction(function () use (
            $turn,
            $apiKey,
            $title,
            $question,
            $answer,
            $keywords,
            $publishNow,
        ) {
            $locked = WiseTurn::query()->whereKey($turn->id)->lockForUpdate()->firstOrFail();

            $canCloseGap = $locked->gap
                && $locked->gap_handled_at === null
                && $locked->gap_knowledge_id === null;

            // Reuse linked knowledge when gap already handled — never spawn orphan FAQs.
            $reuse = null;
            if ($locked->gap_knowledge_id) {
                $reuse = WiseKnowledgeItem::query()
                    ->whereKey($locked->gap_knowledge_id)
                    ->where('wise_api_key_id', $apiKey->id)
                    ->lockForUpdate()
                    ->first();
            }
            if ($reuse === null && $canCloseGap && $locked->gap_auto_draft_id) {
                $reuse = WiseKnowledgeItem::query()
                    ->whereKey($locked->gap_auto_draft_id)
                    ->where('wise_api_key_id', $apiKey->id)
                    ->lockForUpdate()
                    ->first();
            }
            if (
                $reuse === null
                && $locked->gap
                && ($locked->gap_handled_at !== null || $locked->gap_knowledge_id !== null)
            ) {
                throw new InvalidArgumentException(
                    'This gap was already handled. Open Knowledge to edit the linked FAQ, or Coach a new turn.'
                );
            }

            $meta = [
                'source' => ($reuse && (($reuse->meta['source'] ?? null) === GapAutoDrafter::META_SOURCE
                    || ($reuse->meta['auto_draft'] ?? false) === true))
                    ? GapAutoDrafter::META_SOURCE
                    : 'playground_coach',
                'wise_turn_id' => (int) $locked->id,
                'human_reviewed' => true,
            ];
            if ($reuse && is_array($reuse->meta)) {
                $meta = array_merge($reuse->meta, $meta);
            }

            $payload = [
                'wise_api_key_id' => $apiKey->id,
                'type' => KnowledgeSchema::KIND_FAQ,
                'scope' => KnowledgeSchema::SCOPE_MERCHANT,
                'title' => mb_substr($title, 0, 191),
                'question' => mb_substr($question, 0, 2000),
                'answer' => mb_substr($answer, 0, 5000),
                'keywords' => $keywords,
                'meta' => $meta,
            ];

            if ($reuse) {
                $reuse->fill(array_merge($payload, [
                    'version' => max(1, ((int) $reuse->version) + 1),
                    // Content change unpublishes until human re-approves (same as updateKnowledge).
                    'status' => 'draft',
                ]));
                $reuse->save();
                $item = $reuse;
            } else {
                $item = WiseKnowledgeItem::create(array_merge($payload, [
                    'version' => 1,
                    'status' => 'draft',
                ]));
            }

            if ($publishNow) {
                $item = $this->publisher->publish($item);
            }

            if ($canCloseGap) {
                $locked->update([
                    'gap_handled_at' => now(),
                    'gap_knowledge_id' => $item->id,
                    'gap_auto_draft_id' => $item->id,
                ]);
            }

            return [$item->fresh(['apiKey:id,name']) ?? $item, $locked->fresh() ?? $locked];
        });

        return [
            'category' => $category,
            'published' => $item->status === 'published',
            'knowledge_item' => $item,
            'language_entry' => null,
            'turn' => $freshTurn,
        ];
    }
}
