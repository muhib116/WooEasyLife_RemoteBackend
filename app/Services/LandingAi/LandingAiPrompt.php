<?php

namespace App\Services\LandingAi;

use Illuminate\Support\Str;

/**
 * Copywriting instructions and the user message for one landing generation.
 * Rules mirror the tuned manual prompt in the WEL widget (Config → AI prompt).
 */
class LandingAiPrompt
{
    public function instructions(string $brand, string $layout): string
    {
        $brand = $brand !== '' ? $brand : 'the merchant\'s store';
        $layout = $layout !== '' ? $layout : 'classic';

        return <<<TXT
You write the sales copy for ONE Bangladeshi cash-on-delivery (COD) product landing page built with the Woo Easy Life Elementor widget. Fill the JSON schema exactly. Every customer-facing string is natural spoken Bengali (বাংলা), short enough to scan on a phone. One idea per block.

Before writing, read COPY BRIEF. Execute CHOSEN ANGLE. You may add at most two supporting points that COPY BRIEF already lists. Do not switch to a different product strategy. Category strategy overrides the price band when they differ. If the price tier is unknown, do not assume one.

PRODUCT IDENTITY (strict order, never switch to another product category)
1. PRODUCT FACTS from WooCommerce, then attached photos for what is visibly true only. Do not infer hidden materials, origin, certificates, or performance from a photo.
2. REFERENCE PAGES: selling angles and objections only. Never their names, prices, sentences, reviews, or policies.
3. MERCHANT NOTES.
If sources disagree, follow product facts and say so in notes. Ignore demo copy from another category.

PRICE & OFFER (follow COPY BRIEF price strategy)
- Use only prices, quantities, and delivery fees written in PRODUCT FACTS or MERCHANT NOTES.
- Bands, only when a real price exists: under ৳500 short and direct; ৳500–1500 benefit plus fair price; ৳1500–4000 specs and risk reduction before the ask; above ৳4000 proof and suitability. Category still leads.
- On sale: mention savings only when discount_percent is present. Never invent a regular price or "free delivery".
- Combo only from listed variations or notes. Unit price = package price / quantity, and savings = single prices minus pack price, only when those numbers are in the facts and the items match. Otherwise sell the product's real value.
- Marquee and pills must match the real offer.

BENGALI CONVERSION
- Hero: one specific desire-led promise, plus one short support line (a real spec, the real price, or COD only if the facts say so). No generic headline. No fake urgency badge.
- Features state what the product has. Benefits say why that matters. Do not repeat the same idea.
- Objections: answer only concerns supported by facts or notes (authenticity, price, delivery, fit, use). Leave unknown answers empty.
- CTA: "এখনই অর্ডার করুন". Use a call or WhatsApp CTA only when MERCHANT NOTES say that channel exists. If stock is out of stock, do not push an order.
- FAQ: real buyer questions for this product. Do not invent the answer. No medical miracles or guaranteed results.
- Urgency only for a real offer, deadline, or stock note. Otherwise invite the order directly. Use "আজকের অফার" or "স্টক সীমিত" only when the notes or facts establish it.

VOICE
- Warm, confident, spoken Bangla. Bangla titles. Latin only for brand, model, SKU, WhatsApp, and COD.
- Intimate products: discreet and factual; one safety line in the FAQ only, and only if relevant.

NEVER IN CUSTOMER TEXT
- Research words: রেফারেন্স, উল্লেখিত, according to, reference page.
- Competitor brands, slogans, or copied sentences.
- Invented prices, delivery times, warranty, phone numbers, certificates, order counts, health guarantees, ১০০% satisfaction, ২৪/৭, or nationwide delivery.
- Missing fact → empty string (keeps the merchant's current value).

REVIEWS & SOCIAL PROOF (follow COPY BRIEF review_mode)
- live: review_items only when grounded in PRODUCT FACTS or MERCHANT NOTES. Otherwise leave review_items empty.
- demo: realistic Bangladeshi names and districts, varied voices, 4–5 stars, plausible praise. Say it is sample copy in notes only, never in customer text.
- Trust stats and badges: only numbers and coverage stated in MERCHANT NOTES or PRODUCT FACTS. If none are given, leave the stat empty.

FIELD RULES
- The store brand is "{$brand}". Keep {n} placeholders exactly.
- Order steps describe only the journey MERCHANT NOTES confirm. Do not promise a confirmation call or pay-on-delivery unless the notes say so. Usage tips go in the FAQ.
- Row counts and character limits in the schema are mandatory.

LAYOUT
Page layout is "{$layout}". Write every schema field anyway.

NOTES FIELD
notes: one to three short English sentences. State the chosen angle, which inputs you used, any reference you could not read, review_mode, and what to verify before publish (price, delivery, phone, stock).
TXT;
    }

    /**
     * Deterministic strategy: score angle candidates, pick one, derive price/review mode.
     *
     * @param  array{brand: string, site_name: string, domain: string, layout: string}  $store
     * @param  list<array<string, mixed>>  $products
     * @param  list<array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    public function buildCopyBrief(array $store, array $products, string $context, array $pages): array
    {
        $primary = $products[0] ?? [];
        $name = (string) ($primary['name'] ?? '');
        $categories = implode(' ', (array) ($primary['categories'] ?? []));
        $tier = (string) ($primary['price_tier'] ?? 'unknown');
        $onSale = ! empty($primary['on_sale']);
        $discount = $primary['discount_percent'] ?? null;
        $multi = count($products) > 1 || count((array) ($primary['variations'] ?? [])) > 1;

        $refAngles = [];
        $refObjections = [];
        foreach ($pages as $page) {
            if (empty($page['ok'])) {
                continue;
            }
            foreach ((array) ($page['selling_angles'] ?? []) as $a) {
                $refAngles[] = $a;
            }
            foreach ((array) ($page['objections'] ?? []) as $o) {
                $refObjections[] = $o;
            }
        }
        $refAngles = array_values(array_unique(array_slice($refAngles, 0, 12)));
        $refObjections = array_values(array_unique(array_slice($refObjections, 0, 8)));

        $candidates = $this->angleCandidates($name, $categories, $tier, $onSale, $multi, $context);
        $scored = [];
        foreach ($candidates as $angle) {
            $score = 1;
            if ($this->contextSupports($angle, $context)) {
                $score += 2;
            }
            foreach ($refAngles as $ref) {
                if ($this->phrasesOverlap($angle, $ref)) {
                    $score += 2;
                }
            }
            if ($onSale && str_contains($angle, 'save')) {
                $score += 2;
            }
            if ($multi && str_contains($angle, 'combo')) {
                $score += 2;
            }
            if (preg_match('/^(Harvest|Visible|Daily wellness|Authenticity|Home statement)/', $angle)) {
                $score += 3;
            }
            $scored[] = ['angle' => $angle, 'score' => $score];
        }
        usort($scored, function (array $a, array $b): int {
            $byScore = $b['score'] <=> $a['score'];

            return $byScore !== 0 ? $byScore : strcmp($a['angle'], $b['angle']);
        });
        $chosen = $scored[0]['angle'] ?? 'Use only the verified product facts. Do not assume a price tier.';

        return [
            'product_identity' => $name !== '' ? $name : '(infer carefully from photos and notes only)',
            'price_strategy' => $this->priceStrategy($tier, $onSale, $discount, $multi, $context),
            'angle_candidates' => array_column($scored, 'angle'),
            'chosen_angle' => $chosen,
            'reference_angles' => $refAngles,
            'reference_objections' => $refObjections,
            'review_mode' => $this->reviewMode($products, $context),
            'delivery_hint' => $this->deliveryHint($context),
            'allowed_price_literals' => $this->allowedPriceLiterals($products),
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array{brand: string, site_name: string, domain: string, layout: string}  $store
     * @param  list<array<string, mixed>>  $products
     * @param  list<array<string, mixed>>  $pages
     */
    public function userMessage(array $store, array $products, string $context, array $pages, int $imageCount, array $brief = []): string
    {
        $lines = ['STORE'];
        $lines[] = 'Brand: '.($store['brand'] !== '' ? $store['brand'] : '(not set)');
        if ($store['site_name'] !== '') {
            $lines[] = 'Site name: '.$store['site_name'];
        }
        if ($store['domain'] !== '') {
            $lines[] = 'Domain: '.$store['domain'];
        }
        $lines[] = 'Page layout: '.($store['layout'] !== '' ? $store['layout'] : 'classic');
        $lines[] = '';

        if ($brief !== []) {
            $lines[] = 'COPY BRIEF (deterministic; follow exactly)';
            $lines[] = 'Product identity: '.($brief['product_identity'] ?? '');
            $lines[] = 'Price strategy: '.($brief['price_strategy'] ?? '');
            $lines[] = 'CHOSEN ANGLE: '.($brief['chosen_angle'] ?? '');
            $lines[] = 'Other scored angles (do not use): '.implode(' | ', array_slice((array) ($brief['angle_candidates'] ?? []), 1, 4));
            if (! empty($brief['reference_angles'])) {
                $lines[] = 'Reference selling angles (ideas only): '.implode(' | ', (array) $brief['reference_angles']);
            }
            if (! empty($brief['reference_objections'])) {
                $lines[] = 'Reference objections to answer if relevant: '.implode(' | ', (array) $brief['reference_objections']);
            }
            if (! empty($brief['delivery_hint'])) {
                $lines[] = 'Delivery note from merchant: '.$brief['delivery_hint'];
            }
            $lines[] = 'review_mode: '.($brief['review_mode'] ?? 'live');
            $lines[] = 'Allowed price text (only these amounts in customer copy): '.implode(', ', (array) ($brief['allowed_price_literals'] ?? []));
            $lines[] = '';
        }

        $lines[] = 'PRODUCT FACTS (from WooCommerce, treat as true)';
        if ($products === []) {
            $lines[] = 'No product is linked to this page yet. Work out the product from the photos, the reference pages and the notes.';
        }
        foreach ($products as $i => $product) {
            $lines[] = ($i + 1).') '.$product['name'].($product['label'] !== '' ? ' (shown as: '.$product['label'].')' : '');
            if (($product['type'] ?? '') !== '') {
                $lines[] = '   Type: '.$product['type'];
            }
            if (($product['sku'] ?? '') !== '') {
                $lines[] = '   SKU: '.$product['sku'];
            }
            if ($product['price'] !== '') {
                $lines[] = '   Price: '.$product['price'];
            }
            if (($product['regular_price'] ?? '') !== '') {
                $lines[] = '   Regular price: '.$product['regular_price'];
            }
            if (($product['sale_price'] ?? '') !== '' && ($product['sale_price'] ?? '') !== ($product['regular_price'] ?? '')) {
                $lines[] = '   Current/sale price: '.$product['sale_price'];
            }
            if (! empty($product['on_sale'])) {
                $lines[] = '   On sale: yes'.(($product['discount_percent'] ?? null) ? ' (~'.$product['discount_percent'].'% off regular)' : '');
            }
            if (($product['price_tier'] ?? '') !== '' && $product['price_tier'] !== 'unknown') {
                $lines[] = '   Price tier: '.$product['price_tier'];
            }
            if ($product['options'] !== []) {
                $lines[] = '   Options: '.implode('; ', $product['options']);
            }
            if (($product['variations'] ?? []) !== []) {
                $lines[] = '   Variations:';
                foreach ((array) $product['variations'] as $var) {
                    if (! is_array($var)) {
                        continue;
                    }
                    $lines[] = '     - '.($var['label'] ?? 'Option').($var['price'] !== '' ? ' @ '.$var['price'] : '')
                        .(! empty($var['in_stock']) ? '' : ' (out of stock)');
                }
            }
            if ($product['categories'] !== []) {
                $lines[] = '   Categories: '.implode(', ', $product['categories']);
            }
            $lines[] = '   Stock: '.($product['in_stock'] ? 'in stock' : 'out of stock');
            if (($product['image_count'] ?? 0) > 0) {
                $lines[] = '   Product images in catalog: '.$product['image_count'];
            }
            if ($product['short_description'] !== '') {
                $lines[] = '   Short description: '.$product['short_description'];
            }
            if ($product['description'] !== '') {
                $lines[] = '   Description: '.$product['description'];
            }
        }
        $lines[] = '';

        $lines[] = 'MERCHANT NOTES';
        $lines[] = $context !== '' ? $context : '(none)';
        $lines[] = '';

        if ($pages !== []) {
            $lines[] = 'REFERENCE PAGES (selling angles only; never copy their text or brand names)';
            foreach ($pages as $i => $page) {
                $host = (string) (parse_url($page['url'], PHP_URL_HOST) ?: $page['url']);
                if (! $page['ok']) {
                    $lines[] = ($i + 1).') '.$host.': could not be read ('.$page['error'].').';

                    continue;
                }
                $lines[] = ($i + 1).') '.$host;
                if (($page['title'] ?? '') !== '') {
                    $lines[] = '   Title: '.$page['title'];
                }
                if (($page['description'] ?? '') !== '') {
                    $lines[] = '   Summary: '.$page['description'];
                }
                if (($page['headings'] ?? []) !== []) {
                    $lines[] = '   Headings: '.implode(' | ', $page['headings']);
                }
                if (($page['selling_angles'] ?? []) !== []) {
                    $lines[] = '   Extracted angles: '.implode(' | ', (array) $page['selling_angles']);
                }
                if (($page['objections'] ?? []) !== []) {
                    $lines[] = '   Extracted objections: '.implode(' | ', (array) $page['objections']);
                }
                if (($page['text'] ?? '') !== '') {
                    $lines[] = '   Page text:';
                    $lines[] = $page['text'];
                }
            }
            $lines[] = '';
        }

        $lines[] = $imageCount > 0
            ? 'PHOTOS: '.$imageCount.' product photo'.($imageCount > 1 ? 's are' : ' is').' attached.'
            : 'PHOTOS: none attached.';

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<string>
     */
    public function allowedPriceLiterals(array $products): array
    {
        $literals = [];
        foreach ($products as $product) {
            foreach (['price', 'regular_price', 'sale_price', 'display'] as $key) {
                $this->collectPriceLiterals((string) ($product[$key] ?? ''), $literals);
            }
            foreach ((array) ($product['variations'] ?? []) as $var) {
                if (is_array($var)) {
                    $this->collectPriceLiterals((string) ($var['price'] ?? ''), $literals);
                }
            }
        }

        return array_values(array_unique($literals));
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return list<array{name: string, label: string, price: string, regular_price: string, sale_price: string, on_sale: bool, discount_percent: ?int, price_tier: string, type: string, sku: string, options: list<string>, variations: list<array<string, mixed>>, categories: list<string>, in_stock: bool, image_count: int, short_description: string, description: string}>
     */
    public function normalizeProducts(array $raw): array
    {
        $products = [];

        foreach (array_slice($raw, 0, 6) as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $this->line($item['name'] ?? '', 200);
            if ($name === '') {
                continue;
            }

            $variations = [];
            foreach (array_slice((array) ($item['variations'] ?? []), 0, 6) as $var) {
                if (! is_array($var)) {
                    continue;
                }
                $variations[] = [
                    'label' => $this->line($var['label'] ?? '', 120),
                    'price' => $this->line($var['price'] ?? '', 80),
                    'in_stock' => filter_var($var['in_stock'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            $discount = isset($item['discount_percent']) && is_numeric($item['discount_percent'])
                ? max(0, min(99, (int) $item['discount_percent'])) : null;

            $products[] = [
                'name' => $name,
                'label' => $this->line($item['label'] ?? '', 120),
                'price' => $this->line($item['price'] ?? '', 160),
                'regular_price' => $this->line($item['regular_price'] ?? '', 80),
                'sale_price' => $this->line($item['sale_price'] ?? '', 80),
                'on_sale' => filter_var($item['on_sale'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'discount_percent' => $discount,
                'price_tier' => $this->line($item['price_tier'] ?? 'unknown', 20),
                'type' => $this->line($item['type'] ?? '', 24),
                'sku' => $this->line($item['sku'] ?? '', 64),
                'options' => $this->lineList($item['options'] ?? [], 8, 160),
                'variations' => $variations,
                'categories' => $this->lineList($item['categories'] ?? [], 8, 60),
                'in_stock' => filter_var($item['in_stock'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'image_count' => max(0, (int) ($item['image_count'] ?? 0)),
                'short_description' => $this->line($item['short_description'] ?? '', 600),
                'description' => $i === 0 ? $this->line($item['description'] ?? '', 1500) : '',
            ];
        }

        return $products;
    }

    public function line(mixed $value, int $max): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', mb_scrub($value, 'UTF-8')) ?? '');

        return Str::limit($value, $max, '…');
    }

    /**
     * @return list<string>
     */
    private function lineList(mixed $values, int $count, int $max): array
    {
        $out = [];
        foreach (array_slice(is_array($values) ? $values : [], 0, $count) as $value) {
            $line = $this->line($value, $max);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function angleCandidates(string $name, string $categories, string $tier, bool $onSale, bool $multi, string $context): array
    {
        $blob = mb_strtolower($name.' '.$categories.' '.$context);
        $angles = [];

        if (preg_match('/seed|বীজ|agri|fertil|crop|চাষ/u', $blob)) {
            $angles[] = 'Harvest confidence: yield, season fit, and easy home or field use.';
        }
        if (preg_match('/cream|skin|beauty|scar|স্কিন|ক্রিম|গোপন/u', $blob)) {
            $angles[] = 'Visible self-care results with discreet daily use and premium feel.';
        }
        if (preg_match('/health|vitamin|supplement|herbal|ঔষধ/u', $blob)) {
            $angles[] = 'Daily wellness habit with honest expectations—no miracle claims.';
        }
        if (preg_match('/clock|decor|wall|ঘর সাজ|ওয়াল/u', $blob)) {
            $angles[] = 'Home statement piece: design, finish, and a premium room upgrade.';
        }
        if ($onSale) {
            $angles[] = 'Limited-time save: stress real discount vs regular price and COD ease.';
        }
        if ($multi) {
            $angles[] = 'Combo value: guide the buyer to the best pack size from listed options.';
        }
        if ($tier === 'budget') {
            $angles[] = 'Smart small spend: quick win, low risk COD, try today.';
        } elseif ($tier === 'mid') {
            $angles[] = 'Everyday value with COD confidence and clear product outcomes.';
        } elseif ($tier === 'upper') {
            $angles[] = 'Justify the price with specs, reliability, and risk reduction before the order.';
        } elseif ($tier === 'premium') {
            $angles[] = 'Premium pick: quality proof, trust, and reassurance before the order.';
        }
        if (preg_match('/original|authentic|নকল|asli|genui/u', $blob)) {
            $angles[] = 'Authenticity first: original product peace of mind before payment on delivery.';
        }

        return array_values(array_unique($angles));
    }

    private function priceStrategy(string $tier, bool $onSale, mixed $discount, bool $multi, string $context): string
    {
        $parts = [];
        $parts[] = match ($tier) {
            'budget' => 'Under ৳500: short copy, immediate use, and only the delivery fee written in the notes.',
            'mid' => '৳500–1500: practical benefit, what the buyer receives, and the real price.',
            'upper' => '৳1500–4000: specs, reliability, and risk reduction before the order.',
            'premium' => 'Above ৳4000: proof, suitability, and support. Do not replace evidence with urgency.',
            default => 'Price unknown: do not assume a tier or invent a price. Sell from category and facts only.',
        };
        if ($onSale && is_int($discount) && $discount > 0) {
            $parts[] = 'Sale active (~'.$discount.'%): marquee/urgency may mention savings, not new numbers.';
        }
        if ($multi) {
            $parts[] = 'Multiple SKUs/packs: steer to the best value row from PRODUCT FACTS.';
        }
        if ($this->deliveryHint($context) !== '') {
            $parts[] = 'Mention delivery charges only as in MERCHANT NOTES.';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function reviewMode(array $products, string $context): string
    {
        if (preg_match('/\b(demo|sample|placeholder)\b/i', $context)) {
            return 'demo';
        }
        foreach ($products as $product) {
            if (($product['name'] ?? '') === '') {
                continue;
            }
            if (($product['price'] ?? '') !== '' || ($product['short_description'] ?? '') !== '' || ($product['description'] ?? '') !== '') {
                return 'live';
            }
        }

        return 'demo';
    }

    private function deliveryHint(string $context): string
    {
        if ($context === '') {
            return '';
        }
        if (preg_match('/(?:delivery|ডেলিভারি|shipping|ঢাকা|ঢাকার\s*বাইরে|charge|চার্জ)[^\n]{0,120}/ui', $context, $m)) {
            return $this->line($m[0], 160);
        }

        return '';
    }

    private function contextSupports(string $angle, string $context): bool
    {
        if ($context === '') {
            return false;
        }

        return $this->phrasesOverlap($angle, $context);
    }

    private function phrasesOverlap(string $a, string $b): bool
    {
        $tokens = array_filter(preg_split('/\s+/u', mb_strtolower($a)) ?: [], fn ($t) => mb_strlen($t) > 4);
        $hay = mb_strtolower($b);
        foreach ($tokens as $token) {
            if (str_contains($hay, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $literals
     */
    private function collectPriceLiterals(string $text, array &$literals): void
    {
        if ($text === '') {
            return;
        }
        $literals[] = trim($text);
        if (preg_match_all('/[\d,]+(?:\.\d+)?/u', $text, $m)) {
            foreach ($m[0] as $num) {
                $literals[] = $num;
                $literals[] = str_replace(',', '', $num);
            }
        }
    }
}
