<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseLanguageArtifact;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguagePackAssignment;

/**
 * Provision empty regional packs on demand (Discovery growth — not hand-seeded catalogs).
 * Opt-in via region assignment only; never platform-default.
 */
class RegionalPackProvisioner
{
    public function __construct(
        private PackCompiler $compiler,
    ) {}

    public function ensure(string $regionCode): WiseLanguagePack
    {
        $region = RegionCode::normalize($regionCode);
        if ($region === null) {
            throw new \InvalidArgumentException('Invalid region code for pack provision.');
        }

        $slug = RegionCode::packSlug($region);
        $name = ucfirst($region).' Regional';

        $pack = WiseLanguagePack::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'kind' => 'region',
                'name' => $name,
                'semver' => '1.0.0',
                'status' => 'published',
                'locale_scope' => 'bd-'.$region,
                'meta' => [
                    'region' => $region,
                    'provisioned_via' => 'discovery',
                ],
            ]
        );

        if (($pack->meta['region'] ?? null) !== $region) {
            $pack->meta = array_merge($pack->meta ?? [], ['region' => $region]);
            $pack->save();
        }
        if ($pack->status !== 'published') {
            $pack->status = 'published';
            $pack->save();
        }

        WiseLanguagePackAssignment::query()->updateOrCreate(
            [
                'pack_id' => $pack->id,
                'target_type' => 'region',
                'target_id' => $region,
            ],
            [
                'priority' => 15,
                'enabled' => true,
                'meta' => ['role' => 'regional', 'region' => $region],
            ]
        );

        $hasPublished = WiseLanguageArtifact::query()
            ->where('pack_id', $pack->id)
            ->where('status', 'published')
            ->exists();

        if (! $hasPublished) {
            $this->compiler->compileAndPublish($pack->fresh());
        }

        CorpusResolver::forgetCache();

        return $pack->fresh();
    }

    public function ensureFromPackSlug(string $packSlug): ?WiseLanguagePack
    {
        $packSlug = trim($packSlug);
        if (! str_starts_with($packSlug, 'region-')) {
            return null;
        }
        $region = substr($packSlug, strlen('region-'));

        return $this->ensure($region);
    }
}
