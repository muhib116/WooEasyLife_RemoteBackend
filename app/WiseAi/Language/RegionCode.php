<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;

/**
 * Stable BD region codes for BCLC regional pack assignments (merchant opt-in).
 * Understanding overlays — not translation engines.
 */
final class RegionCode
{
    /** Seeded packs in L3 v1 (more regions later). */
    public const SEEDED = ['chattogram', 'sylhet', 'noakhali'];

    /** @var array<string, string> alias → canonical */
    private const ALIASES = [
        'chattogram' => 'chattogram',
        'chittagong' => 'chattogram',
        'ctg' => 'chattogram',
        'চট্টগ্রাম' => 'chattogram',
        'sylhet' => 'sylhet',
        'সিলেট' => 'sylhet',
        'noakhali' => 'noakhali',
        'নোয়াখালী' => 'noakhali',
        'barisal' => 'barisal',
        'বরিশাল' => 'barisal',
        'rajshahi' => 'rajshahi',
        'রাজশাহী' => 'rajshahi',
        'khulna' => 'khulna',
        'খুলনা' => 'khulna',
        'rangpur' => 'rangpur',
        'রংপুর' => 'rangpur',
        'dhaka' => 'dhaka',
        'ঢাকা' => 'dhaka',
    ];

    /**
     * Resolve region from turn context (wins) then merchant key meta.
     *
     * @param  array<string, mixed>  $context
     */
    public static function resolve(?WiseApiKey $apiKey = null, array $context = []): ?string
    {
        $candidates = [
            $context['region'] ?? null,
            $context['locale_region'] ?? null,
            is_array($apiKey?->meta['language'] ?? null) ? ($apiKey->meta['language']['region'] ?? null) : null,
            $apiKey?->meta['region'] ?? null,
        ];

        foreach ($candidates as $raw) {
            $code = self::normalize((string) ($raw ?? ''));
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    public static function normalize(string $raw): ?string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        return self::ALIASES[$raw] ?? (preg_match('/^[a-z][a-z0-9_-]{1,40}$/', $raw) === 1 ? $raw : null);
    }

    public static function packSlug(string $region): string
    {
        $code = self::normalize($region);
        if ($code === null) {
            throw new \InvalidArgumentException('Invalid region for pack slug.');
        }

        return 'region-'.$code;
    }

    /** @return array<string, array{name: string, banglish: array<string, string>}> */
    public static function seedCatalog(): array
    {
        return [
            'chattogram' => [
                'name' => 'Chattogram Regional',
                'banglish' => [
                    'aitta' => 'eta',
                    'deikkha' => 'dekhe',
                    'zaiba' => 'jabe',
                    'hoitey' => 'hote',
                    'oyre' => 'ore',
                    'aitta dam' => 'eta dam',
                ],
            ],
            'sylhet' => [
                'name' => 'Sylhet Regional',
                'banglish' => [
                    'fura' => 'pura',
                    'oita' => 'ota',
                    'khaisi' => 'kheyechi',
                    'zamu' => 'jabo',
                    'aise nai' => 'ase nai',
                    'oita kito' => 'ota ki',
                ],
            ],
            'noakhali' => [
                'name' => 'Noakhali Regional',
                'banglish' => [
                    'kita' => 'ki',
                    'korba' => 'korbe',
                    'kita korba' => 'ki korbe',
                    'thik ase ni' => 'thik ase',
                    'aiso' => 'eso',
                ],
            ],
        ];
    }
}
