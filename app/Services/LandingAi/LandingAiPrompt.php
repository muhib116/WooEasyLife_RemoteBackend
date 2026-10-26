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
You write the sales copy for ONE Bangladeshi cash-on-delivery (COD) product landing page built with the Woo Easy Life Elementor widget. Fill the JSON schema exactly. Every customer-facing string is natural spoken Bengali (বাংলা).

PRODUCT IDENTITY (strict order, never switch to another product category)
1. PRODUCT FACTS from the merchant's WooCommerce store, and the attached product photos.
2. REFERENCE PAGES the merchant gave. They sell the same or a similar product. Use them for selling angles, objections, offer structure and FAQ ideas.
3. MERCHANT NOTES.
If the reference pages disagree with the product facts, follow the product facts and say so in notes. Ignore any demo seed or vegetable copy.

VOICE
- Short, warm, confident sentences, like a strong Bangladeshi COD page that wants the order now. Not a research summary.
- Bangla section titles. Latin letters only for brand names, model or SKU names, and tiny badges like COD.
- Hero: one bold, desire-led promise plus one short support line.
- Benefits and features sell outcomes in the merchant's voice (quality, feel, privacy, delivery, COD, pack offers).
- Urgency lines use আজকের অফার / স্টক সীমিত / আগে অর্ডার style. No fake miracles.
- Buttons use action verbs: "এখনই অর্ডার করুন", "কল করে অর্ডার করুন", "WhatsApp-এ অর্ডার".
- Intimate or sensitive products: confident, discreet, premium. One soft safety line in the FAQ only.
- Sample voice (adapt, never copy claims): "হাতে পেয়ে টাকা দিন—সারাদেশে ক্যাশ অন ডেলিভারি", "নাম-মোবাইল-ঠিকানা দিয়ে অর্ডার কনফার্ম করুন".

NEVER IN CUSTOMER TEXT
- Research words: রেফারেন্স, রেফারেন্স পেজ, উল্লেখিত, উল্লেখ রয়েছে, according to, reference page, as mentioned.
- Competitor brand names, shop names, slogans, or sentences copied from reference pages. Write everything fresh.
- Invented facts: prices, discounts, free delivery, delivery times, warranty or return terms, phone numbers, links, certificates, customer or order counts, or medical and health guarantees. Use such facts only when they appear in PRODUCT FACTS or MERCHANT NOTES.
- When a field needs a fact you do not have (a price, a phone number), return an empty string for it. Empty keeps the merchant's current value.

FIELD RULES
- The store brand is "{$brand}". Use it wherever a brand name is needed.
- Keep placeholders such as {n} exactly where a field asks for them.
- Order steps describe the ORDER journey only (ফর্ম পূরণ → কনফার্ম কল → হাতে পেয়ে টাকা দিন). Product usage tips belong in the FAQ.
- Reviews read like real Bangladeshi buyers: varied names and districts, a unique voice in each, 4 or 5 stars, and no claims beyond the product facts.
- Trust and stat badges: use numbers from MERCHANT NOTES when given; otherwise pick values that stay true for any honest shop (১০০%, ২৪/৭, সারাদেশে).
- Follow the row counts and character limits in each schema description. Fill every field you honestly can.

LAYOUT
The page layout is "{$layout}". Write every field anyway; the merchant may switch layouts later.

NOTES FIELD
notes: one to three short English sentences for the merchant. Mention what you used, any reference page you could not use, and what to check before publishing (for example prices or phone numbers).
TXT;
    }

    /**
     * @param  array<string, mixed>  $store
     * @param  list<array<string, mixed>>  $products
     * @param  list<array<string, mixed>>  $pages
     */
    public function userMessage(array $store, array $products, string $context, array $pages, int $imageCount): string
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

        $lines[] = 'PRODUCT FACTS (from WooCommerce, treat as true)';
        if ($products === []) {
            $lines[] = 'No product is linked to this page yet. Work out the product from the photos, the reference pages and the notes.';
        }
        foreach ($products as $i => $product) {
            $lines[] = ($i + 1).') '.$product['name'].($product['label'] !== '' ? ' (shown as: '.$product['label'].')' : '');
            if ($product['price'] !== '') {
                $lines[] = '   Price: '.$product['price'];
            }
            if ($product['options'] !== []) {
                $lines[] = '   Options: '.implode('; ', $product['options']);
            }
            if ($product['categories'] !== []) {
                $lines[] = '   Categories: '.implode(', ', $product['categories']);
            }
            $lines[] = '   Stock: '.($product['in_stock'] ? 'in stock' : 'out of stock');
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
     * @param  array<int, mixed>  $raw
     * @return list<array{name: string, label: string, price: string, options: list<string>, categories: list<string>, in_stock: bool, short_description: string, description: string}>
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

            $products[] = [
                'name' => $name,
                'label' => $this->line($item['label'] ?? '', 120),
                'price' => $this->line($item['price'] ?? '', 120),
                'options' => $this->lineList($item['options'] ?? [], 8, 160),
                'categories' => $this->lineList($item['categories'] ?? [], 8, 60),
                'in_stock' => filter_var($item['in_stock'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'short_description' => $this->line($item['short_description'] ?? '', 600),
                // Long description only for the main product, to keep the prompt small.
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
}
