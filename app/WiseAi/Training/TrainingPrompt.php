<?php

namespace App\WiseAi\Training;

/**
 * Typed professional prompts for Train packs (paste into ChatGPT / Claude / Gemini
 * or send to Wise LLM generate). Still human-reviewed — never auto-publish.
 */
final class TrainingPrompt
{
    public const TYPE_MERCHANT = 'merchant';

    public const TYPE_PLATFORM = 'platform';

    public const TYPE_KNOWLEDGE = 'knowledge';

    public const TYPE_LANGUAGE = 'language';

    public const TYPE_EXPERIENCE = 'experience';

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_MERCHANT,
            self::TYPE_PLATFORM,
            self::TYPE_KNOWLEDGE,
            self::TYPE_LANGUAGE,
            self::TYPE_EXPERIENCE,
        ];
    }

    /**
     * Recommended pack sizes for proper (not toy) training.
     *
     * @return array{min: int, target: int, strong: int, lanes_bn: string, source_bn: string}
     */
    public static function volumeFor(string $type): array
    {
        return match (self::normalizeType($type)) {
            self::TYPE_LANGUAGE => [
                'min' => 15,
                'target' => 25,
                'strong' => 40,
                'lanes_bn' => 'শুধু language · abbrev ৮–১২ + banglish ১২–২০',
                'source_bn' => 'আসল চ্যাট থেকে অন্তত ২৫–৪০টা ইউনিক সারফেস (শব্দ/ফ্রেজ) কপি করুন',
            ],
            self::TYPE_KNOWLEDGE => [
                'min' => 10,
                'target' => 20,
                'strong' => 35,
                'lanes_bn' => 'শুধু knowledge · ডেলিভারি+পেমেন্ট+রিটার্ন বাধ্যতামূলক',
                'source_bn' => 'সঠিক পলিসি টেক্সট + ১৫–২৫টা আসল কাস্টমার প্রশ্ন (Messenger কপি)',
            ],
            self::TYPE_PLATFORM => [
                'min' => 16,
                'target' => 24,
                'strong' => 36,
                'lanes_bn' => 'knowledge ৮–১২ (স্ক্রিপ্ট) + language ১২–২০ — experience নয়',
                'source_bn' => 'কমন BD slang লিস্ট + নিরাপদ স্ক্রিপ্ট আইডিয়া (দাম/ফি নয়)',
            ],
            self::TYPE_EXPERIENCE => [
                'min' => 6,
                'target' => 10,
                'strong' => 16,
                'lanes_bn' => 'শুধু experience soft-hint',
                'source_bn' => '৬–১২টা সিচুয়েশন যেখানে clarify/shortlist সত্যিই কাজ করেছে',
            ],
            default => [ // merchant full
                'min' => 22,
                'target' => 30,
                'strong' => 45,
                'lanes_bn' => 'knowledge ১২–১৮ + language ১০–১৬ + experience ৩–৬',
                'source_bn' => 'পলিসি ৩টা + ২০+ আসল প্রশ্ন + ১৫+ slang + ৪–৬টা “কী কাজ করেছে” নোট',
            ],
        };
    }

    /**
     * Bangla instruction sheet for founders (UI).
     *
     * @return list<string>
     */
    public static function instructionsBn(): array
    {
        return [
            'ভালো ট্রেইনিং = যথেষ্ট আইটেম + আসল সোর্স ডেটা + Publish/Promote। কম আইটেম = খেলনা মোড।',
            'মিনিমাম vs টার্গেট vs শক্তিশালী: নিচের সংখ্যা মানে এক প্যাকের JSON items। সপ্তাহে ২–৩ প্যাক যোগ করে শক্তিশালী করুন।',
            'ভাষা: মিনিমাম ১৫ · টার্গেট ২৫ · শক্তিশালী ৪০+ সারফেস (plz/tnx + Banglish মিশিয়ে)।',
            'নলেজ: মিনিমাম ১০ · টার্গেট ২০ · শক্তিশালী ৩৫+ FAQ/পলিসি — ডেলিভারি·পেমেন্ট·রিটার্ন বাদ যাবে না।',
            'প্ল্যাটফর্ম: মিনিমাম ১৬ · টার্গেট ২৪ (স্ক্রিপ্ট+slang) — স্টোর দাম কখনো নয়।',
            'মার্চেন্ট ফুল: মিনিমাম ২২ · টার্গেট ৩০ · শক্তিশালী ৪৫ (তিন লেন মিলিয়ে)।',
            'এক্সপেরিয়েন্স: মিনিমাম ৬ · টার্গেট ১০ — সত্য নয়, শুধু “কী কাজ করেছে”।',
            'সোর্স: brief-এ Messenger/Instagram থেকে কপি করুন। AI আন্দাজে দাম/পলিসি বানালে বাদ দিন।',
            'Import = ড্রাফট। কাস্টমার দেখবে না যতক্ষণ Knowledge Publish বা Language Promote না হয়।',
        ];
    }

    /**
     * @return list<array{value: string, label: string, hint: string, label_bn: string, hint_bn: string, coach_bn: list<string>, next_bn: string, volume_bn: string}>
     */
    public static function typeOptions(): array
    {
        $opts = [];
        foreach ([
            self::TYPE_PLATFORM,
            self::TYPE_LANGUAGE,
            self::TYPE_KNOWLEDGE,
            self::TYPE_MERCHANT,
            self::TYPE_EXPERIENCE,
        ] as $type) {
            $v = self::volumeFor($type);
            $opts[] = match ($type) {
                self::TYPE_PLATFORM => [
                    'value' => $type,
                    'label' => 'Platform (all keys)',
                    'hint' => 'Shared Knowledge + Language — no Experience',
                    'label_bn' => 'প্ল্যাটফর্ম (সব স্টোর)',
                    'hint_bn' => 'সব API key-এর জন্য সাধারণ স্ক্রিপ্ট + slang — দাম/পলিসি নয়',
                    'volume_bn' => "প্যাক: মিনিমাম {$v['min']} · টার্গেট {$v['target']} · শক্তিশালী {$v['strong']}+",
                    'coach_bn' => [
                        $v['source_bn'].'।',
                        $v['lanes_bn'].'।',
                        'শুধু সব মার্চেন্টের জন্য নিরাপদ জিনিস (সালাম, dam ক্ল্যারিফাই, হ্যান্ডঅফ) — স্টোর ফি নয়।',
                        'Language Approve = Platform scope → BCLC।',
                    ],
                    'next_bn' => 'Import → Language Promote (Platform) · Knowledge Publish',
                ],
                self::TYPE_LANGUAGE => [
                    'value' => $type,
                    'label' => 'Language only',
                    'hint' => 'Abbrev + Banglish Discovery rows',
                    'label_bn' => 'শুধু ভাষা / slang',
                    'hint_bn' => 'plz, tnx, tumar, apnar — কাস্টমার কী টাইপ করে',
                    'volume_bn' => "প্যাক: মিনিমাম {$v['min']} · টার্গেট {$v['target']} · শক্তিশালী {$v['strong']}+",
                    'coach_bn' => [
                        $v['source_bn'].'।',
                        $v['lanes_bn'].' — একই from দুবার নয়।',
                        'ambiguous টোকেন (যেমন শুধু “pp”) বাদ।',
                        'Import → Language Train কিউ → Approve (অটো-পাবলিশ নয়)।',
                    ],
                    'next_bn' => 'Language → Open/Train → Approve',
                ],
                self::TYPE_KNOWLEDGE => [
                    'value' => $type,
                    'label' => 'Knowledge only',
                    'hint' => 'FAQ / policy / script facts for one merchant',
                    'label_bn' => 'শুধু নলেজ / পলিসি',
                    'hint_bn' => 'এক স্টোরের FAQ, ডেলিভারি, পেমেন্ট, রিটার্ন',
                    'volume_bn' => "প্যাক: মিনিমাম {$v['min']} · টার্গেট {$v['target']} · শক্তিশালী {$v['strong']}+",
                    'coach_bn' => [
                        $v['source_bn'].'।',
                        'বাধ্যতামূলক ৩: ডেলিভারি চার্জ · পেমেন্ট · রিটার্ন (সত্য সংখ্যা/নিয়ম)।',
                        'দাম না জানলে clarify — ভুয়া সংখ্যা নয়।',
                        'Import = Draft → Knowledge-এ একট একট Publish।',
                    ],
                    'next_bn' => 'Knowledge Draft → Publish',
                ],
                self::TYPE_MERCHANT => [
                    'value' => $type,
                    'label' => 'Merchant (full pack)',
                    'hint' => 'One store — Knowledge + Language + Experience',
                    'label_bn' => 'মার্চেন্ট ফুল প্যাক',
                    'hint_bn' => 'এক স্টোর: নলেজ + ভাষা + এক্সপেরিয়েন্স একসাথে',
                    'volume_bn' => "প্যাক: মিনিমাম {$v['min']} · টার্গেট {$v['target']} · শক্তিশালী {$v['strong']}+",
                    'coach_bn' => [
                        $v['source_bn'].'।',
                        $v['lanes_bn'].' — কম হলে আলাদা Language/Knowledge প্যাক আবার দিন।',
                        'একটি অ্যাক্টিভ API key লাগবে (Platform নয়)।',
                        'Import এর পর Publish (নলেজ) + Promote (ভাষা) আলাদা।',
                    ],
                    'next_bn' => 'Knowledge Publish + Language Promote',
                ],
                default => [
                    'value' => $type,
                    'label' => 'Experience only',
                    'hint' => 'Merchant soft-hints — not facts',
                    'label_bn' => 'শুধু এক্সপেরিয়েন্স',
                    'hint_bn' => '“কী কাজ করেছে” soft-hint — সত্য/পলিসি নয়',
                    'volume_bn' => "প্যাক: মিনিমাম {$v['min']} · টার্গেট {$v['target']} · শক্তিশালী {$v['strong']}+",
                    'coach_bn' => [
                        $v['source_bn'].'।',
                        'দাম/পলিসি এখানে নয় (সেটা Knowledge)।',
                        'প্রতি সিগন্যালে clear note + pattern_key।',
                        'Publish নেই — merchant key-এ Import হলে soft-hint।',
                    ],
                    'next_bn' => 'Playground-এ একই সিচুয়েশন টেস্ট',
                ],
            };
        }

        return $opts;
    }

    public static function recommendedTargetItems(string $type): int
    {
        return self::volumeFor($type)['target'];
    }

    public static function normalizeType(?string $type): string
    {
        $type = strtolower(trim((string) $type));
        if ($type === '' || $type === 'full' || $type === 'professional') {
            return self::TYPE_MERCHANT;
        }

        return in_array($type, self::types(), true) ? $type : self::TYPE_MERCHANT;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        $out = [];
        foreach (self::types() as $type) {
            $out[$type] = self::for($type);
        }

        return $out;
    }

    /** BC alias — full merchant pack prompt. */
    public static function professional(): string
    {
        return self::for(self::TYPE_MERCHANT);
    }

    public static function for(string $type): string
    {
        return match (self::normalizeType($type)) {
            self::TYPE_PLATFORM => self::platform(),
            self::TYPE_KNOWLEDGE => self::knowledge(),
            self::TYPE_LANGUAGE => self::language(),
            self::TYPE_EXPERIENCE => self::experience(),
            default => self::merchant(),
        };
    }

    /**
     * Lanes allowed for a typed pack (used to strip LLM drift after generate).
     *
     * @return list<string>
     */
    public static function allowedLanes(string $type): array
    {
        return match (self::normalizeType($type)) {
            self::TYPE_PLATFORM => ['knowledge', 'language'],
            self::TYPE_KNOWLEDGE => ['knowledge'],
            self::TYPE_LANGUAGE => ['language'],
            self::TYPE_EXPERIENCE => ['experience'],
            default => ['knowledge', 'language', 'experience'],
        };
    }

    /** Merchant-scoped training types that must import under an API key. */
    public static function requiresMerchantKey(string $type): bool
    {
        return in_array(self::normalizeType($type), [
            self::TYPE_MERCHANT,
            self::TYPE_KNOWLEDGE,
            self::TYPE_EXPERIENCE,
        ], true);
    }

    /**
     * Keep only lanes allowed for $type; coerce platform knowledge scope.
     *
     * @param  array<string, mixed>  $pack
     * @return array{pack: array<string, mixed>, dropped: int}
     */
    public static function filterPack(array $pack, string $type): array
    {
        $type = self::normalizeType($type);
        $allowed = self::allowedLanes($type);
        $items = $pack['items'] ?? [];
        if (! is_array($items)) {
            $items = [];
        }

        $kept = [];
        $dropped = 0;
        foreach ($items as $raw) {
            if (! is_array($raw)) {
                $dropped++;

                continue;
            }
            $lane = strtolower(trim((string) ($raw['lane'] ?? 'knowledge')));
            if ($lane === '') {
                $lane = 'knowledge';
            }
            if (! in_array($lane, $allowed, true)) {
                $dropped++;

                continue;
            }
            if ($type === self::TYPE_PLATFORM && $lane === 'knowledge') {
                $raw['scope'] = 'platform';
            }
            if ($type === self::TYPE_KNOWLEDGE && $lane === 'knowledge') {
                $raw['scope'] = 'merchant';
            }
            $kept[] = $raw;
        }

        $pack['items'] = $kept;
        if (empty($pack['version'])) {
            $pack['version'] = TrainingSchema::VERSION;
        }

        return ['pack' => $pack, 'dropped' => $dropped];
    }

    public static function generatorSystem(string $type = self::TYPE_MERCHANT): string
    {
        $type = self::normalizeType($type);
        $v = self::volumeFor($type);

        $lane = match ($type) {
            self::TYPE_PLATFORM => 'Only knowledge (scope=platform) and language lanes. Never experience.',
            self::TYPE_KNOWLEDGE => 'Only knowledge lane items (merchant scope). No language or experience.',
            self::TYPE_LANGUAGE => 'Only language lane items (abbrev/Banglish). No knowledge or experience.',
            self::TYPE_EXPERIENCE => 'Only experience lane items. No knowledge or language facts.',
            default => 'Include knowledge, language (abbrev + Banglish), and experience lanes.',
        };

        return 'You generate Wise AI training packs as strict JSON only (version '.TrainingSchema::VERSION.'). '
            .$lane.' '
            ."Aim for about {$v['target']} high-quality items (minimum {$v['min']}, do not stop at toy size). "
            .'Never invent prices, stock, or policies. Prefer Bangladesh Messenger Banglish/Bangla. '
            .'Return ONLY JSON.';
    }

    private static function volumeBlock(string $type): string
    {
        $v = self::volumeFor($type);

        return <<<TXT
## Volume for PROPER training (not a toy pack)
- Minimum acceptable: {$v['min']} items
- Target (do this): {$v['target']} items
- Strong pack: {$v['strong']}+ items over multiple imports is fine
- Prefer fewer excellent real rows over many invented ones
- If the brief is thin, still hit the minimum using clarify-style answers / high-frequency slang — never fabricate fees or prices
TXT;
    }

    private static function rootSchema(string $merchantPlaceholder): string
    {
        $version = TrainingSchema::VERSION;

        return <<<JSON
{
  "version": "{$version}",
  "merchant": "{$merchantPlaceholder}",
  "notes": "<short review note>",
  "items": [ ... ]
}
JSON;
    }

    private static function knowledgeShape(string $scopeDefault): string
    {
        return <<<JSON
{
  "lane": "knowledge",
  "type": "faq" | "policy" | "fact" | "script" | "campaign",
  "scope": "{$scopeDefault}",
  "title": "short English label for admins",
  "question": "customer question (Bangla/Banglish)",
  "answer": "approved answer — never invent prices",
  "keywords": ["token1", "token2"],
  "region": "optional e.g. chattogram|sylhet|dhaka"
}
JSON;
    }

    private static function languageShape(): string
    {
        return <<<'JSON'
{
  "lane": "language",
  "category": "abbrev" | "sms" | "banglish" | "phonetic" | "commerce" | "messenger" | "filler",
  "from": "surface customers type (e.g. plz, tnx, tumar, tmr)",
  "to": "canonical expansion (e.g. please, thank you, তোমার, tomorrow)",
  "pack_slug": "core-bd",
  "region": "optional — sets pack_slug to region-<code> when omitted pack_slug"
}
JSON;
    }

    private static function experienceShape(): string
    {
        return <<<'JSON'
{
  "lane": "experience",
  "signal_type": "external",
  "intent": "price|delivery|greeting|complaint|order_status|*",
  "action": "clarify|suggest_reply",
  "weight": 1.0,
  "pattern_key": "script:ask_price_bare.clarify",
  "note": "why this path worked"
}
JSON;
    }

    private static function merchant(): string
    {
        $root = self::rootSchema('<store name>');
        $knowledge = self::knowledgeShape('merchant');
        $language = self::languageShape();
        $experience = self::experienceShape();

        $volume = self::volumeBlock(self::TYPE_MERCHANT);

        return <<<PROMPT
You are a senior Bangladesh e-commerce knowledge engineer helping train **Wise AI** for **one merchant store** (not platform-wide).

## Goal
Produce a **proper JSON training pack** (production-ready volume) bound to a single API key:
1. Store facts (Knowledge) — FAQ, policy, script — about 12–18 rows
2. Chat localization (Language) — about 10–16 rows
3. Soft-hints (Experience) — about 3–6 rows — never facts

{$volume}

Hub Train target: merchant API key. Humans Publish Knowledge and Promote Language — you must NOT invent live prices, stock, or policies the merchant did not give you.

## Output rules (strict)
1. Return **ONLY valid JSON** (no markdown fences, no commentary).
2. Root object MUST match:
{$root}
3. Mix lanes to hit ~30 items when the brief supports it (never pad with fake prices).
4. Prefer Bangla + Banglish Messenger/Instagram questions copied from reality.
5. If a fact is unknown → clarify-style answer — never invent numbers.
6. Required knowledge trio if brief has any policy data: delivery, payment, return.

## Knowledge item shape
{$knowledge}
Use `"scope": "merchant"` only (do not use platform).

## Language item shape (localization — NOT facts)
{$language}
- abbrev/sms: plz, tnx, tmr, msg, asap → English expansion.
- banglish: tumar→তোমার, apnar→আপনার, dam koto→দাম কত.
- Skip ambiguous tokens like bare "pp".
- Rows become Discovery reviews — humans Promote.

## Experience item shape (what worked — NOT facts)
{$experience}

## Source data the human should paste into the brief (for proper training)
- 15–25 real customer questions (Messenger copy)
- Exact delivery / payment / return text
- 15+ slang surfaces they actually type
- 3–6 notes on “what reply path worked”

## First-pack priorities
1. Delivery charge (only real numbers)
2. Payment (COD / bKash / Nagad)
3. Return / exchange
4. Chat abbrev + Banglish
5. “dam koto?” → clarify product
6. Size/color / catalog clarify
7. Angry customer empathy + handoff

## Merchant brief (fill before generating)
- Store name / niche:
- Real customer questions (list):
- Delivery / payment / return (exact):
- Chat slang list:
- What worked (experience notes):
- Forbidden claims:

Generate the JSON pack now at TARGET volume. Omit unknown prices/policies — do not fabricate.
PROMPT;
    }

    private static function platform(): string
    {
        $root = self::rootSchema('Wise Platform');
        $knowledge = self::knowledgeShape('platform');
        $language = self::languageShape();

        $volume = self::volumeBlock(self::TYPE_PLATFORM);

        return <<<PROMPT
You are a senior Bangladesh commerce linguist + knowledge engineer training **Wise AI platform defaults** shared by **all API keys**.

## Goal
Produce a **proper platform training JSON pack**:
1. Shared safe scripts / clarify FAQs (Knowledge, `scope=platform`) — about 8–12 rows
2. Common BD chat localization (Language → Platform / BCLC) — about 12–20 rows

{$volume}

**Do NOT include Experience** (merchant-only). **Do NOT include store-specific prices, SKUs, delivery fees, or brand policies.**

Hub Train target: **Platform (all keys)**. Humans still Publish/Promote.

## Output rules (strict)
1. Return **ONLY valid JSON** (no markdown fences).
2. Root object MUST match:
{$root}
3. Every knowledge item MUST use `"scope": "platform"` and `"lane": "knowledge"`.
4. Every localization item MUST use `"lane": "language"`.
5. Zero `"lane": "experience"` items.
6. Prefer universal BD Messenger patterns — not one shop’s catalog.
7. Hit TARGET ~24 items (≈ half scripts, half language).

## Knowledge item shape (shared only)
{$knowledge}
Good: greetings, “dam koto?” clarify-without-price, negotiation without false discounts, angry empathy + handoff, COD explanation without fees.
Bad: “ঢাকা ডেলিভারি ৬০ টাকা”, product SKUs, store return windows.

## Language item shape
{$language}
Prioritize high-frequency: plz, tnx, tmr, msg, asap, tumar, apnar, amar, koto, ase/aase, dam, hobe, ache, thikase, bhai, apa.
`pack_slug`: usually `core-bd`. Skip bare "pp".

## Source data for proper platform training
- 20+ common BD chat surfaces (list in brief)
- Optional region dialect stems
- Script situations (greeting / price clarify / complaint) — no merchant fees

## Platform brief
- Slang / surfaces list:
- Regions (optional):
- Forbidden claims:

Generate the JSON pack now at TARGET volume — platform-safe only.
PROMPT;
    }

    private static function knowledge(): string
    {
        $root = self::rootSchema('<store name>');
        $knowledge = self::knowledgeShape('merchant');

        $volume = self::volumeBlock(self::TYPE_KNOWLEDGE);

        return <<<PROMPT
You are a senior Bangladesh e-commerce knowledge engineer. Build a **proper Knowledge-only** Wise AI training pack for **one merchant**.

## Goal
Only `"lane": "knowledge"` items (FAQ / policy / fact / script / campaign).  
No language rows. No experience rows.

{$volume}

Hub: merchant API key → drafts → human Publish.

## Output rules (strict)
1. Return **ONLY valid JSON**.
2. Root:
{$root}
3. Every item: `"lane": "knowledge"`, `"scope": "merchant"`.
4. Short Bangla/Banglish questions merchants really get.
5. Never invent prices, stock, or policies — use clarify answers when missing.
6. Hit TARGET ~20 items. Must include delivery + payment + return if brief has any of them.

## Item shape
{$knowledge}

## Source data for proper knowledge training
- Exact delivery / payment / return policy text
- 15–25 real customer questions (copy from Messenger)
- Product/variation questions only with real prices if given

## Priorities
1. Delivery (real numbers only)
2. Payment methods
3. Return / exchange
4. Order status / COD confirm
5. Size/color / product clarify
6. Greeting + complaint handoff scripts

## Merchant brief
- Store name / niche:
- Policies (exact):
- Real customer questions (list):
- Products/prices (only if allowed):
- Forbidden claims:

Generate Knowledge-only JSON now at TARGET volume.
PROMPT;
    }

    private static function language(): string
    {
        $root = self::rootSchema('<store or Wise Platform>');
        $language = self::languageShape();

        $volume = self::volumeBlock(self::TYPE_LANGUAGE);

        return <<<PROMPT
You are a Bangladesh chat-localization specialist training **Wise AI Language** (abbrev + Banglish) for **proper coverage**.

## Goal
Only `"lane": "language"` items.  
No knowledge facts. No experience signals.

{$volume}

Import → Language Discovery (`channel=train`) → human Approve/Promote.  
Merchant target → merchant overlay; Platform target → BCLC (`core-bd` / `region-*`).

## Output rules (strict)
1. Return **ONLY valid JSON**.
2. Root:
{$root}
3. Every item: `"lane": "language"`.
4. `from` and `to` must differ (except filler).
5. Skip ambiguous tokens like bare "pp".
6. Normalize: lowercase Latin abbrev; keep Bengali in `to` when appropriate.
7. Hit TARGET ~25 unique surfaces (≈ 8–12 abbrev/sms + 12–20 banglish/commerce). Deduplicate `from`.

## Item shape
{$language}

Categories:
- abbrev/sms/messenger: plz, tnx, tmr, msg, asap, okk, bro, sis, …
- banglish: tumar, apnar, amar, koto, ase/aase, dam koto, hobe, thikase, bhai, apa, …
- region: only if brief names a dialect → `region-<code>` pack_slug

## Source data for proper language training
Paste 25–40 unique surfaces customers actually type. Do not invent rare dialect maps without evidence.

## Brief
- Surfaces list (required for proper training):
- Region (optional):
- Merchant-specific slang (optional):

Generate Language-only JSON now at TARGET volume.
PROMPT;
    }

    private static function experience(): string
    {
        $root = self::rootSchema('<store name>');
        $experience = self::experienceShape();

        $volume = self::volumeBlock(self::TYPE_EXPERIENCE);

        return <<<PROMPT
You are a commerce dialogue coach training **Wise AI Experience** soft-hints for **one merchant**.

## Goal
Only `"lane": "experience"` items — “what worked” signals.  
These are **not facts** and never Publish as Knowledge.  
Platform Train skips Experience — merchant-key only.

{$volume}

## Output rules (strict)
1. Return **ONLY valid JSON**.
2. Root:
{$root}
3. Every item: `"lane": "experience"`.
4. No knowledge answers, no language from/to maps.
5. Prefer clarify / shortlist patterns that prevent wrong prices.
6. Hit TARGET ~10 signals, each with clear `note` + stable `pattern_key`.

## Item shape
{$experience}

## Good signals
- Bare “dam?” / “দাম কত?” → clarify product first
- Angry tone → empathy + human handoff
- Incomplete address → ask thana/area before COD confirm
- Size missing on fashion → clarify before quote
- “ache?” / stock ask → ask product name/photo first

## Bad signals
- Encoding a specific price as Knowledge
- Inventing policies

## Source data for proper experience training
List 6–12 real situations where a clarify/shortlist path worked better than guessing.

## Merchant brief
- Store niche:
- Situations that worked (list):
- Forbidden claims:

Generate Experience-only JSON now at TARGET volume.
PROMPT;
    }
}
