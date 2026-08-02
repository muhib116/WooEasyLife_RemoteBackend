<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseLanguageArtifact;
use App\Models\WiseAi\WiseLanguageConcept;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguageSurface;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic pack → runtime artifact. Decide path never SQL-scans surfaces.
 */
class PackCompiler
{
    /**
     * Compile published surfaces into a hash-mapped artifact and publish it.
     *
     * @return array{artifact: WiseLanguageArtifact, created: bool, content_hash: string}
     */
    public function compileAndPublish(WiseLanguagePack $pack): array
    {
        $payload = $this->buildPayload($pack);
        // Content-addressed: exclude DB pack_id so export/import hashes stay portable.
        $hash = $this->contentHash($payload);

        $existing = WiseLanguageArtifact::query()
            ->where('pack_id', $pack->id)
            ->where('content_hash', $hash)
            ->where('status', 'published')
            ->first();

        if ($existing) {
            $this->ensurePublished($pack);

            return ['artifact' => $existing, 'created' => false, 'content_hash' => $hash];
        }

        return DB::transaction(function () use ($pack, $payload, $hash) {
            WiseLanguageArtifact::query()
                ->where('pack_id', $pack->id)
                ->where('status', 'published')
                ->update(['status' => 'superseded']);

            $artifact = WiseLanguageArtifact::query()->create([
                'pack_id' => $pack->id,
                'pack_version' => (string) $pack->semver,
                'compiler_version' => LanguageCorpus::COMPILER_VERSION,
                'content_hash' => $hash,
                'artifact_json' => $this->canonicalJson($payload),
                'status' => 'published',
                'published_at' => now(),
            ]);

            $this->ensurePublished($pack);
            CorpusResolver::forgetCache();

            return ['artifact' => $artifact, 'created' => true, 'content_hash' => $hash];
        });
    }

    private function ensurePublished(WiseLanguagePack $pack): void
    {
        if ($pack->status === 'published') {
            return;
        }
        $pack->status = 'published';
        $pack->save();
    }

    /** @param  array<string, mixed>  $payload */
    public function contentHash(array $payload): string
    {
        $forHash = $payload;
        unset($forHash['pack_id']);

        return hash('sha256', $this->canonicalJson($forHash));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(WiseLanguagePack $pack): array
    {
        $maps = [];
        foreach (LanguageCorpus::MAP_CATEGORIES as $cat) {
            $maps[$cat] = [];
        }
        $fillers = [];
        $emoji = [];
        $conceptHits = [];
        $ambiguous = PlatformLexicon::AMBIGUOUS;

        $concepts = WiseLanguageConcept::query()
            ->where('pack_id', $pack->id)
            ->where('status', 'published')
            ->get()
            ->keyBy('id');

        $surfaces = WiseLanguageSurface::query()
            ->where('pack_id', $pack->id)
            ->where('approval_status', 'published')
            ->where('deprecated', false)
            ->orderBy('id')
            ->get();

        foreach ($surfaces as $surface) {
            /** @var WiseLanguageConcept|null $concept */
            $concept = $concepts->get($surface->concept_id);
            if (! $concept) {
                continue;
            }

            $from = mb_strtolower(trim((string) $surface->surface_text));
            if ($from === '' || in_array($from, PlatformLexicon::AMBIGUOUS, true)) {
                continue;
            }

            $category = (string) $concept->category;
            $to = trim((string) ($surface->to_text ?? ''));
            $conceptHits[$from] = (string) $concept->concept_key;

            if ($category === LanguageCorpus::EMOJI_CATEGORY) {
                $meta = is_array($surface->meta) ? $surface->meta : [];
                $emoji[$surface->surface_text] = [
                    'signal' => (string) ($meta['signal'] ?? 'emotion'),
                    'polarity' => (string) ($meta['polarity'] ?? 'neutral'),
                ];
                continue;
            }

            if (in_array($category, LanguageCorpus::LIST_CATEGORIES, true)) {
                $fillers[] = $from;
                continue;
            }

            if (in_array($category, LanguageCorpus::MAP_CATEGORIES, true) && $to !== '') {
                $maps[$category][$from] = $to;
            }
        }

        $fillers = array_values(array_unique($fillers));
        sort($fillers);

        foreach ($maps as $cat => $pairs) {
            ksort($pairs);
            $maps[$cat] = $pairs;
        }
        ksort($emoji);
        ksort($conceptHits);

        return [
            'protocol_version' => LanguageCorpus::PROTOCOL_VERSION,
            'compiler_version' => LanguageCorpus::COMPILER_VERSION,
            'pack_id' => (int) $pack->id,
            'pack_slug' => (string) $pack->slug,
            'pack_kind' => (string) $pack->kind,
            'pack_version' => (string) $pack->semver,
            'maps' => $maps,
            'filler' => $fillers,
            'emoji' => $emoji,
            'ambiguous' => array_values($ambiguous),
            'concept_hits' => $conceptHits,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function canonicalJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
