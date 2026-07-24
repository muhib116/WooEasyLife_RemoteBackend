<?php

namespace App\Support;

/**
 * Soft-clean mashed cluster copy for #seo-prerender (crawlers).
 * Keeps meaning; strips LaTeX delimiters and box-drawing noise.
 * Does not mutate Inertia props used by the Vue block parser.
 */
class SeoPrerenderText
{
    /** @var array<string, array{bn: string, en: string}> */
    private const PATH_LABELS = [
        '/' => ['bn' => 'হোম', 'en' => 'Bangla home'],
        '/en' => ['bn' => 'ইংরেজি হোম', 'en' => 'English home'],
        '/bd-fraud-checker' => ['bn' => 'ফ্রড চেকার', 'en' => 'BD Fraud Checker'],
        '/en/bd-fraud-checker' => ['bn' => 'ফ্রড চেকার (EN)', 'en' => 'BD Fraud Checker'],
        '/fake-order-protection' => ['bn' => 'ফেক অর্ডার প্রোটেকশন', 'en' => 'Fake Order Protection'],
        '/en/fake-order-protection' => ['bn' => 'ফেক অর্ডার প্রোটেকশন (EN)', 'en' => 'Fake Order Protection'],
        '/courier-auto-entry' => ['bn' => 'কুরিয়ার অটো এন্ট্রি', 'en' => 'Courier Auto Entry'],
        '/en/courier-auto-entry' => ['bn' => 'কুরিয়ার অটো এন্ট্রি (EN)', 'en' => 'Courier Auto Entry'],
        '/return-loss-calculator' => ['bn' => 'রিটার্ন লস ক্যালকুলেটর', 'en' => 'Return Loss Calculator'],
        '/en/return-loss-calculator' => ['bn' => 'রিটার্ন লস ক্যালকুলেটর (EN)', 'en' => 'Return Loss Calculator'],
        '/courier-charge-calculator' => ['bn' => 'কুরিয়ার চার্জ ক্যালকুলেটর', 'en' => 'Courier Charge Calculator'],
        '/en/courier-charge-calculator' => ['bn' => 'কুরিয়ার চার্জ ক্যালকুলেটর (EN)', 'en' => 'Courier Charge Calculator'],
        '/ads-roas-calculator' => ['bn' => 'Ads ROAS ক্যালকুলেটর', 'en' => 'Ads ROAS Calculator'],
        '/en/ads-roas-calculator' => ['bn' => 'Ads ROAS Calculator', 'en' => 'Ads ROAS Calculator'],
        '/woocommerce-bangladesh' => ['bn' => 'WooCommerce Bangladesh গাইড', 'en' => 'WooCommerce Bangladesh guide'],
        '/en/woocommerce-bangladesh' => ['bn' => 'ইংরেজি গাইড', 'en' => 'English WooCommerce guide'],
        '/pricing' => ['bn' => 'প্রাইসিং', 'en' => 'Pricing'],
        '/fraudbd-alternative' => ['bn' => 'FraudBD Alternative', 'en' => 'FraudBD Alternative'],
        '/en/fraudbd-alternative' => ['bn' => 'FraudBD Alternative (EN)', 'en' => 'FraudBD Alternative'],
        '/steadfast-integration' => ['bn' => 'Steadfast ইন্টিগ্রেশন', 'en' => 'Steadfast integration'],
        '/en/steadfast-integration' => ['bn' => 'Steadfast (EN)', 'en' => 'Steadfast integration'],
        '/pathao-courier-guide' => ['bn' => 'Pathao কুরিয়ার গাইড', 'en' => 'Pathao courier guide'],
        '/en/pathao-courier-guide' => ['bn' => 'Pathao (EN)', 'en' => 'Pathao courier guide'],
        '/redx-courier-guide' => ['bn' => 'RedX কুরিয়ার গাইড', 'en' => 'RedX courier guide'],
        '/en/redx-courier-guide' => ['bn' => 'RedX (EN)', 'en' => 'RedX courier guide'],
        '/woocommerce-mobile-app' => ['bn' => 'মোবাইল অ্যাপ গাইড', 'en' => 'Mobile app guide'],
        '/en/woocommerce-mobile-app' => ['bn' => 'Mobile app (EN)', 'en' => 'Mobile app guide'],
        '/customer-verification' => ['bn' => 'কাস্টমার ভেরিফিকেশন', 'en' => 'Customer verification'],
        '/en/customer-verification' => ['bn' => 'Customer verification (EN)', 'en' => 'Customer verification'],
        '/cod-return-reduction' => ['bn' => 'COD রিটার্ন কমান', 'en' => 'COD return reduction'],
        '/en/cod-return-reduction' => ['bn' => 'COD returns (EN)', 'en' => 'COD return reduction'],
        '/woocommerce-notifications' => ['bn' => 'নোটিফিকেশন অটোমেশন', 'en' => 'Notifications automation'],
        '/en/woocommerce-notifications' => ['bn' => 'Notifications (EN)', 'en' => 'Notifications automation'],
        '/facebook-ads-for-woocommerce' => ['bn' => 'Facebook Ads গাইড', 'en' => 'Facebook Ads guide'],
        '/en/facebook-ads-for-woocommerce' => ['bn' => 'Facebook Ads (EN)', 'en' => 'Facebook Ads guide'],
        '/facebook-page-cod-management' => ['bn' => 'Facebook Page COD ম্যানেজমেন্ট', 'en' => 'Facebook Page COD management'],
        '/en/facebook-page-cod-management' => ['bn' => 'Facebook Page COD (EN)', 'en' => 'Facebook Page COD management'],
        '/about' => ['bn' => 'About / Founder Muhibbullah', 'en' => 'About / Founder Muhibbullah'],
        '/en/about' => ['bn' => 'About (EN)', 'en' => 'About / Founder Muhibbullah'],
        '/ki-vabe-fake-order-atkabo' => ['bn' => 'কিভাবে ফেক অর্ডার আটকাবো', 'en' => 'How to stop fake orders (BN)'],
        '/en/ki-vabe-fake-order-atkabo' => ['bn' => 'How to stop fake orders (EN)', 'en' => 'How to stop fake orders'],
        '/fake-customer-check' => ['bn' => 'Fake Customer Check', 'en' => 'Fake Customer Check'],
        '/en/fake-customer-check' => ['bn' => 'Fake Customer Check (EN)', 'en' => 'Fake Customer Check'],
        '/pathao-fraud-check' => ['bn' => 'Pathao ফ্রড চেক', 'en' => 'Pathao fraud check'],
        '/steadfast-fraud-check' => ['bn' => 'Steadfast ফ্রড চেক', 'en' => 'Steadfast fraud check'],
        '/redx-fraud-check' => ['bn' => 'RedX ফ্রড চেক', 'en' => 'RedX fraud check'],
        '/bd-courier-ratio-checker' => ['bn' => 'BD Courier Ratio Checker', 'en' => 'BD Courier Ratio Checker'],
        '/fake-order-check' => ['bn' => 'Fake Order Check', 'en' => 'Fake Order Check'],
        '/courier-checker' => ['bn' => 'Courier Checker', 'en' => 'Courier Checker'],
        '/blog' => ['bn' => 'ব্লগ', 'en' => 'Blog (BN)'],
        '/en/blog' => ['bn' => 'ব্লগ (EN)', 'en' => 'Blog'],
        '/wooeasylife/app/privacy-policy' => ['bn' => 'প্রাইভেসি পলিসি', 'en' => 'Privacy Policy'],
        '/wooeasylife/app/terms-of-service' => ['bn' => 'টার্মস অফ সার্ভিস', 'en' => 'Terms of Service'],
    ];

    /**
     * Every sitemap marketing URL as internal links (Ahrefs: avoid orphaned sitemap pages).
     *
     * @return list<array{href: string, label: string}>
     */
    public static function sitemapNavLinks(bool $isEn = false): array
    {
        $lang = $isEn ? 'en' : 'bn';
        $links = [];

        foreach (config('seo.sitemap.paths', []) as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '' || $path === '/') {
                continue;
            }

            $links[] = [
                'href' => $path,
                'label' => self::PATH_LABELS[$path][$lang] ?? ltrim($path, '/'),
            ];
        }

        return $links;
    }

    public static function plain(string $text): string
    {
        $out = $text;

        $out = preg_replace_callback('/\$\$(.+?)\$\$/s', static function (array $m): string {
            return self::latexToPlain($m[1]);
        }, $out) ?? $out;

        $replacements = [
            '──┬──>' => ' → ',
            '──┼──>' => ' → ',
            '┼──>' => ' → ',
            '┌──>' => ' → ',
            '├──>' => ' → ',
            '└──>' => ' → ',
            '──┐' => ' → ',
            '──┘' => ' → ',
            '──>' => ' → ',
            '➔' => ' → ',
            '→' => ' → ',
        ];
        $out = str_replace(array_keys($replacements), array_values($replacements), $out);

        $out = preg_replace('/\s{2,}/u', ' ', $out) ?? $out;

        return trim($out);
    }

    /**
     * Escape text then wrap known marketing paths in <a> for crawler prerender HTML.
     */
    public static function linkifyHtml(string $text, bool $isEn = false): string
    {
        $plain = self::plain($text);
        if ($plain === '') {
            return '';
        }

        $paths = array_keys(self::PATH_LABELS);
        usort($paths, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $parts = [];
        $cursor = 0;
        $len = mb_strlen($plain);

        while ($cursor < $len) {
            $slice = mb_substr($plain, $cursor);
            $best = null;
            $bestPos = null;
            foreach ($paths as $path) {
                $pos = mb_strpos($slice, $path);
                if ($pos === false) {
                    continue;
                }
                $after = mb_substr($slice, $pos + mb_strlen($path), 1);
                if ($after !== '' && preg_match('/[a-z0-9]/i', $after)) {
                    continue;
                }
                if ($bestPos === null || $pos < $bestPos || ($pos === $bestPos && mb_strlen($path) > mb_strlen((string) $best))) {
                    $best = $path;
                    $bestPos = $pos;
                }
            }

            if ($best === null || $bestPos === null) {
                $parts[] = e(mb_substr($plain, $cursor));
                break;
            }

            if ($bestPos > 0) {
                $parts[] = e(mb_substr($plain, $cursor, $bestPos));
            }

            $label = self::PATH_LABELS[$best][$isEn ? 'en' : 'bn'] ?? $best;
            $parts[] = '<a href="'.e($best).'">'.e($label).'</a>';

            $cursor += $bestPos + mb_strlen($best);
            $rest = mb_substr($plain, $cursor);
            if (preg_match('/^(?:-এ|-তে|-র|-য়|-য়ে|-য়ের|-কে|-ও)/u', $rest, $m)) {
                $cursor += mb_strlen($m[0]);
            }
        }

        return implode('', $parts);
    }

    private static function latexToPlain(string $expr): string
    {
        $s = trim($expr);

        if (preg_match('/^(.+?)\s*=\s*\\\\frac\{(.+)\}\{(.+)\}\s*$/s', $s, $m)) {
            return trim(self::unwrapLatexText($m[1]).' = '.self::unwrapLatexText($m[2]).' / '.self::unwrapLatexText($m[3]));
        }

        if (preg_match('/^(.+?)\s*=\s*(.+)$/s', $s, $m)) {
            return trim(self::unwrapLatexText($m[1]).' = '.self::unwrapLatexText($m[2]));
        }

        return self::unwrapLatexText($s);
    }

    private static function unwrapLatexText(string $expr): string
    {
        $s = $expr;
        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace('/\\\\text\{([^{}]*)\}/', '$1', $s) ?? $s;
            $next = preg_replace('/\\\\mathrm\{([^{}]*)\}/', '$1', $next) ?? $next;
            if ($next === $s) {
                break;
            }
            $s = $next;
        }

        $s = str_replace(['\\&', '\\times', '\\cdot'], ['&', '×', '·'], $s);
        $s = preg_replace('/\\\\left|\\\\right/', '', $s) ?? $s;
        $s = str_replace(['{', '}'], '', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }
}
