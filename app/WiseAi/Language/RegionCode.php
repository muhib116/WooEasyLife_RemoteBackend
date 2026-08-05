<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseApiKey;

/**
 * Stable BD region codes for BCLC regional pack assignments (merchant opt-in).
 * Understanding overlays — not translation engines.
 *
 * Model: a few dialect HUBS (seeded packs) + many PLACE aliases (district/upazila → hub).
 * Kishoreganj / Haluaghat → mymensingh; Bogura is its own Barendri hub (not every village).
 *
 * Lexicon seeds drawn from public dialect notes (Vashantor/arXiv, Wikipedia Mymensinghi/Varendri,
 * community Bogura word lists) — romanized for Messenger Banglish; grow further via Discovery.
 */
final class RegionCode
{
    /**
     * Seeded L3 dialect hubs. Places (kishoreganj, haluaghat, …) alias into these.
     * Dhaka omitted as hub seed — near-standard; still aliasable for opt-in growth.
     */
    public const SEEDED = [
        'chattogram',
        'sylhet',
        'noakhali',
        'barisal',
        'rajshahi',
        'khulna',
        'rangpur',
        'mymensingh',
        'bogura',
    ];

    /**
     * Public sources used for starter banglish / regional knowledge notes.
     *
     * @var list<string>
     */
    public const SOURCES = [
        'https://arxiv.org/abs/2311.11142', // Vashantor — CTG/Noakhali/Sylhet/Barishal/Mymensingh
        'https://en.wikipedia.org/wiki/Mymensinghi_dialect',
        'https://en.wikipedia.org/wiki/Varendri',
        'http://raufur1.blogspot.com/2015/10/blog-post.html', // Bogura community lexicon
        'https://doi.org/10.17632/sx6ybcps2n.1', // BanglaDial district labels
    ];

    /**
     * Alias / place → canonical hub (or bare code).
     * Includes Bengali spellings + common misspellings (kishorgonj, bogra, …).
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        // —— Chattogram hub ——
        'chattogram' => 'chattogram',
        'chittagong' => 'chattogram',
        'ctg' => 'chattogram',
        'চট্টগ্রাম' => 'chattogram',

        // —— Sylhet hub ——
        'sylhet' => 'sylhet',
        'সিলেট' => 'sylhet',

        // —— Noakhali hub ——
        'noakhali' => 'noakhali',
        'নোয়াখালী' => 'noakhali',

        // —— Barisal hub ——
        'barisal' => 'barisal',
        'barishal' => 'barisal',
        'বরিশাল' => 'barisal',

        // —— Rajshahi hub (north-central; Bogura has own pack) ——
        'rajshahi' => 'rajshahi',
        'রাজশাহী' => 'rajshahi',

        // —— Bogura / Barendri hub ——
        'bogura' => 'bogura',
        'bogra' => 'bogura',
        'বগুড়া' => 'bogura',
        'বগুড়া' => 'bogura',

        // —— Khulna hub ——
        'khulna' => 'khulna',
        'খুলনা' => 'khulna',

        // —— Rangpur hub ——
        'rangpur' => 'rangpur',
        'রংপুর' => 'rangpur',

        // —— Mymensingh hub (incl. Kishoreganj, Haluaghat, Tangail…) ——
        'mymensingh' => 'mymensingh',
        'mymensing' => 'mymensingh',
        'ময়মনসিংহ' => 'mymensingh',
        'ময়মনসিংহ' => 'mymensingh',
        'kishoreganj' => 'mymensingh',
        'kishorgonj' => 'mymensingh',
        'kishoregonj' => 'mymensingh',
        'কিশোরগঞ্জ' => 'mymensingh',
        'haluaghat' => 'mymensingh',
        'হালুয়াঘাট' => 'mymensingh',
        'হালুয়াঘাট' => 'mymensingh',
        'tangail' => 'mymensingh',
        'টাঙ্গাইল' => 'mymensingh',

        // —— Dhaka (opt-in code only; no L3 seed pack) ——
        'dhaka' => 'dhaka',
        'ঢাকা' => 'dhaka',
    ];

    /**
     * Places covered by each hub (for UI labels — not separate packs).
     *
     * @return array<string, list<string>>
     */
    public static function placeCoverage(): array
    {
        return [
            'chattogram' => ['Chattogram'],
            'sylhet' => ['Sylhet'],
            'noakhali' => ['Noakhali'],
            'barisal' => ['Barisal'],
            'rajshahi' => ['Rajshahi'],
            'bogura' => ['Bogura'],
            'khulna' => ['Khulna'],
            'rangpur' => ['Rangpur'],
            // Directly supported by cited Mymensinghi sources; do not infer other districts.
            'mymensingh' => ['Mymensingh', 'Kishoreganj', 'Haluaghat', 'Tangail'],
        ];
    }

    /**
     * Dropdown options for hub admin / WEL (hubs only; places resolve via alias).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function uiOptions(): array
    {
        $out = [];
        foreach (self::seedCatalog() as $code => $def) {
            $places = self::placeCoverage()[$code] ?? [];
            $extra = $places !== [] ? ' · '.implode(', ', array_slice($places, 1, 4)) : '';
            $out[] = [
                'value' => $code,
                'label' => ($def['name'] ?? ucfirst($code)).$extra,
            ];
        }

        return $out;
    }

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
        // Spaces / hyphens → underscore for coxs_bazar style keys
        $compact = str_replace([' ', '-'], ['', '_'], $raw);
        if (isset(self::ALIASES[$raw])) {
            return self::ALIASES[$raw];
        }
        if (isset(self::ALIASES[$compact])) {
            return self::ALIASES[$compact];
        }
        // Strip underscores for kishor_gonj-like
        $nospace = str_replace('_', '', $compact);
        if (isset(self::ALIASES[$nospace])) {
            return self::ALIASES[$nospace];
        }

        // Unreviewed region values must not create a new regional pack at runtime.
        return null;
    }

    public static function packSlug(string $region): string
    {
        $code = self::normalize($region);
        if ($code === null) {
            throw new \InvalidArgumentException('Invalid region for pack slug.');
        }

        return 'region-'.$code;
    }

    /**
     * Starter banglish overlays (romanized Messenger → clearer BD Banglish).
     * Prefer distinctive stems so Discovery does not cross-pollute hubs.
     *
     * @return array<string, array{name: string, banglish: array<string, string>, sources?: list<string>}>
     */
    public static function seedCatalog(): array
    {
        return [
            'chattogram' => [
                'name' => 'Chattogram Regional',
                'sources' => ['Vashantor (arXiv:2311.11142)', 'BD commerce chat'],
                'banglish' => [
                    'aitta' => 'eta',
                    'deikkha' => 'dekhe',
                    'zaiba' => 'jabe',
                    'hoitey' => 'hote',
                    'oyre' => 'ore',
                    'aitta dam' => 'eta dam',
                    'ibar' => 'tar',
                    'itar' => 'tar',
                    'deikkha nai' => 'dekhi nai',
                ],
            ],
            'sylhet' => [
                'name' => 'Sylhet Regional',
                'sources' => ['Vashantor (arXiv:2311.11142)'],
                'banglish' => [
                    'fura' => 'pura',
                    'oita' => 'ota',
                    'khaisi' => 'kheyechi',
                    'zamu' => 'jabo',
                    'aise nai' => 'ase nai',
                    'oita kito' => 'ota ki',
                    'tumaer' => 'tomar',
                    'dekhlam' => 'dekhlam',
                ],
            ],
            'noakhali' => [
                'name' => 'Noakhali Regional',
                'sources' => ['Vashantor (arXiv:2311.11142)'],
                'banglish' => [
                    'kita' => 'ki',
                    'korba' => 'korbe',
                    'kita korba' => 'ki korbe',
                    'thik ase ni' => 'thik ase',
                    'aiso' => 'eso',
                    'kita hoise' => 'ki hoise',
                    'edya' => 'dekha',
                ],
            ],
            'barisal' => [
                'name' => 'Barisal Regional',
                'sources' => ['Vashantor (arXiv:2311.11142)', 'BanglaRegionalTextCorpus'],
                'banglish' => [
                    'kothay gili' => 'kothay geli',
                    'hoilo ki' => 'holo ki',
                    'disu to' => 'dao to',
                    'aisos naki' => 'esecho naki',
                    'kemon asos' => 'kemon acho',
                    'nayabhai' => 'boro bhai',
                    'bagda' => 'boka',
                ],
            ],
            'rajshahi' => [
                'name' => 'Rajshahi Regional',
                'sources' => ['Varendri / North-Central Bengali (Wikipedia)'],
                'banglish' => [
                    'kotha koba' => 'kotha kobe',
                    'agelai aso' => 'age eso',
                    'kemne aso' => 'kemon acho',
                    'zaiba ki' => 'jabe ki',
                    'thik ase ne' => 'thik ase',
                    'koria dilam' => 'kore dilam',
                ],
            ],
            'bogura' => [
                'name' => 'Bogura Regional',
                'sources' => [
                    'http://raufur1.blogspot.com/2015/10/blog-post.html',
                    'Varendri / Barendri notes',
                ],
                'banglish' => [
                    'hami' => 'ami',
                    'hamar' => 'amar',
                    'eda' => 'eta',
                    'seda' => 'ota',
                    'kunti' => 'kothay',
                    'oti' => 'okhane',
                    'eti' => 'ekhane',
                    'yabu' => 'jabo',
                    'khabu' => 'khabo',
                    'kyangka achu' => 'kemon acho',
                    'dam koto eda' => 'dam koto eta',
                    'jannya' => 'jani na',
                    'egla' => 'egulo',
                    'ogla' => 'ogulo',
                ],
            ],
            'khulna' => [
                'name' => 'Khulna Regional',
                'sources' => ['BanglaRegionalTextCorpus (Khulna/Narail)'],
                'banglish' => [
                    'korbi to' => 'korbe to',
                    'zabi naki' => 'jabe naki',
                    'disechish' => 'diyecho',
                    'koto hoibo' => 'koto hobe',
                    'thik ase to' => 'thik ase',
                    'aiso ni' => 'esecho',
                    'pathabe' => 'pathabe',
                ],
            ],
            'rangpur' => [
                'name' => 'Rangpur Regional',
                'sources' => ['BanglaRegionalTextCorpus (Rangpur)', 'Varendri cluster'],
                'banglish' => [
                    'ki koria' => 'ki kore',
                    'koriya dilam' => 'kore dilam',
                    'ashim kal' => 'ashi kal',
                    'naik re' => 'nai re',
                    'kemne achen' => 'kemon achen',
                    'hamar ta' => 'amar ta',
                    'gelam ni' => 'gechi',
                ],
            ],
            'mymensingh' => [
                'name' => 'Mymensingh Regional',
                'sources' => [
                    'Wikipedia Mymensinghi dialect',
                    'Vashantor (arXiv:2311.11142)',
                    'covers Kishoreganj / Haluaghat / Tangail aliases',
                ],
                'banglish' => [
                    'kormu' => 'korbo',
                    'koram' => 'korbo',
                    'amare' => 'amake',
                    'tomare' => 'tomake',
                    'jaimu' => 'jabo',
                    'amare pathao' => 'amake pathao',
                    'gulan' => 'gulo',
                    'buka' => 'boka',
                    'dam koto amare' => 'dam koto amake',
                    'kormu ki' => 'korbo ki',
                ],
            ],
        ];
    }
}
