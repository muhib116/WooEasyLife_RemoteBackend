<?php

namespace App\WiseAi\Language;

/**
 * Platform language pack v0 — deterministic, versioned for Replay.
 * Merchant overrides merge on top (sparse); never silently rewrite ambiguous tokens (e.g. pp).
 */
class PlatformLexicon
{
    public const DICT_VERSION = 'platform-0.4.0';

    /**
     * Tokens that must NOT auto-expand (guess-confirm later).
     *
     * @var list<string>
     */
    public const AMBIGUOUS = ['pp', 'p.p'];

    /**
     * Longer keys first when applying maps.
     *
     * @return array{
     *     abbrev: array<string, string>,
     *     sms: array<string, string>,
     *     banglish: array<string, string>,
     *     phonetic: array<string, string>,
     *     commerce: array<string, string>,
     *     filler: list<string>,
     *     emoji: array<string, array{signal: string, polarity: string}>
     * }
     */
    public function pack(): array
    {
        return [
            'abbrev' => [
                'tnx' => 'thank you',
                'thx' => 'thank you',
                'ty' => 'thank you',
                'tysm' => 'thank you',
                'thanku' => 'thank you',
                'thankuu' => 'thank you',
                'plz' => 'please',
                'pls' => 'please',
                'plzz' => 'please',
                'plzzz' => 'please',
                'msg' => 'message',
                'gm' => 'good morning',
                'gn' => 'good night',
                'ok' => 'okay',
                'okk' => 'okay',
                'okkk' => 'okay',
                'okayy' => 'okay',
                'k' => 'okay',
                'kk' => 'okay',
            ],
            'sms' => [
                'u' => 'you',
                'ur' => 'your',
                'b4' => 'before',
                '2day' => 'today',
                '4u' => 'for you',
                'r' => 'are',
                'y' => 'why',
                'btw' => 'by the way',
                'idk' => 'i do not know',
            ],
            'banglish' => [
                'dam koto' => 'দাম কত',
                'price koto' => 'দাম কত',
                'koto taka' => 'কত টাকা',
                'etar dam' => 'এটার দাম',
                'stock ase' => 'স্টক আছে',
                'ase' => 'আছে',
                'pamu' => 'পাবো',
                'lagbe' => 'লাগবে',
                'hobe' => 'হবে',
                'kobe' => 'কবে',
                'koto' => 'কত',
                'dam' => 'দাম',
                'delivery hobe' => 'ডেলিভারি হবে',
                'order confirm' => 'অর্ডার কনফার্ম',
                'eta available' => 'এটা অ্যাভেইলেবল',
            ],
            'phonetic' => [
                'assalamu alaikum' => 'আসসালামু আলাইকুম',
                'as salamu alaikum' => 'আসসালামু আলাইকুম',
                'as salam' => 'আসসালামু আলাইকুম',
                'assalamualaikum' => 'আসসালামু আলাইকুম',
                'aslm' => 'আসসালামু আলাইকুম',
                'slm' => 'আসসালামু আলাইকুম',
                'salam' => 'সালাম',
            ],
            'commerce' => [
                'cod' => 'cash on delivery',
                'cash on delivery' => 'cash on delivery',
                'bkash' => 'বিকাশ',
                'bikash' => 'বিকাশ',
                'nagad' => 'নগদ',
                'rocket' => 'রকেট',
                'last price' => 'last price',
                'fixed' => 'fixed price',
            ],
            'filler' => [
                'vai', 'bhai', 'bro', 'apu', 'apa', 'sir', 'boss', 'dear',
            ],
            'emoji' => [
                '👍' => ['signal' => 'acknowledgement', 'polarity' => 'positive'],
                '👎' => ['signal' => 'acknowledgement', 'polarity' => 'negative'],
                '❤️' => ['signal' => 'emotion', 'polarity' => 'positive'],
                '❤' => ['signal' => 'emotion', 'polarity' => 'positive'],
                '😍' => ['signal' => 'emotion', 'polarity' => 'positive'],
                '😊' => ['signal' => 'emotion', 'polarity' => 'positive'],
                '😢' => ['signal' => 'emotion', 'polarity' => 'sad'],
                '😭' => ['signal' => 'emotion', 'polarity' => 'sad'],
                '😡' => ['signal' => 'emotion', 'polarity' => 'angry'],
                '🔥' => ['signal' => 'excitement', 'polarity' => 'positive'],
                '🙏' => ['signal' => 'thanks', 'polarity' => 'positive'],
                '✅' => ['signal' => 'acknowledgement', 'polarity' => 'positive'],
                '❌' => ['signal' => 'acknowledgement', 'polarity' => 'negative'],
            ],
        ];
    }

    /** @return list<array{type: string, from: string, to: string}> */
    public function flatEntries(): array
    {
        $pack = $this->pack();
        $rows = [];
        foreach (['abbrev', 'sms', 'banglish', 'phonetic', 'commerce'] as $type) {
            foreach ($pack[$type] as $from => $to) {
                $rows[] = ['type' => $type, 'from' => $from, 'to' => $to];
            }
        }
        foreach ($pack['filler'] as $from) {
            $rows[] = ['type' => 'filler', 'from' => $from, 'to' => '(strip)'];
        }
        foreach ($pack['emoji'] as $from => $meta) {
            $rows[] = [
                'type' => 'emoji',
                'from' => $from,
                'to' => $meta['signal'].'/'.$meta['polarity'],
            ];
        }

        return $rows;
    }
}
