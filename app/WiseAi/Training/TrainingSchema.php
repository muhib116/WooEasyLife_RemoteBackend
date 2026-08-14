<?php

namespace App\WiseAi\Training;

/**
 * Manual training pack JSON contract (human import → drafts / experience / language reviews only).
 */
final class TrainingSchema
{
    public const VERSION = 'wise-train-1.0';

    /**
     * Example pack shown in Train UI + prompt (merchant key target).
     *
     * @return array<string, mixed>
     */
    public static function examplePack(): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => 'Example Fashion BD',
            'notes' => 'Review every row before Publish/Promote. Never invent prices/fees — use clarify or store-owned facts only.',
            'items' => [
                [
                    'lane' => 'knowledge',
                    'type' => 'faq',
                    'title' => 'Delivery charge ask',
                    'question' => 'ঢাকায় ডেলিভারি চার্জ কত?',
                    'answer' => 'ডেলিভারি চার্জ এলাকা অনুযায়ী। জেলা/এলাকা লিখলে স্টোর পলিসি দেখে জানাব — আন্দাজ করে টাকা বলব না।',
                    'keywords' => ['delivery', 'ডেলিভারি', 'charge', 'ঢাকা'],
                    'scope' => 'merchant',
                ],
                [
                    'lane' => 'knowledge',
                    'type' => 'policy',
                    'title' => 'Return policy ask',
                    'question' => 'রিটার্ন করা যাবে?',
                    'answer' => 'রিটার্ন/এক্সচেঞ্জ স্টোর নীতিমালা অনুযায়ী। অর্ডার নম্বর দিলে হিউম্যান নিশ্চিত করে জানাবে — দিন সংখ্যা আন্দাজ করব না।',
                    'keywords' => ['return', 'রিটার্ন', 'ফেরত'],
                    'scope' => 'merchant',
                ],
                [
                    'lane' => 'knowledge',
                    'type' => 'faq',
                    'title' => 'Payment methods',
                    'question' => 'পেমেন্ট কীভাবে করব?',
                    'answer' => 'কোন মাধ্যমে পেমেন্ট করতে চান বলুন (বিকাশ/নগদ/COD ইত্যাদি)। স্টোর যেগুলো এক্সেপ্ট করে সেটা নিশ্চিত করে জানাব।',
                    'keywords' => ['payment', 'বিকাশ', 'ক্যাশ', 'নগদ'],
                    'scope' => 'merchant',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'plz',
                    'to' => 'please',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'tnx',
                    'to' => 'thank you',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'tmr',
                    'to' => 'tomorrow',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'tumar',
                    'to' => 'তোমার',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'apnar',
                    'to' => 'আপনার',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'experience',
                    'signal_type' => 'external',
                    'intent' => 'price',
                    'action' => 'clarify',
                    'weight' => 1.5,
                    'pattern_key' => 'script:ask_price_bare.clarify',
                    'note' => 'When customer says only “dam?”, clarify product name first.',
                ],
            ],
        ];
    }

    /**
     * Platform-global example — shared Knowledge drafts + Language reviews (no Experience).
     *
     * @return array<string, mixed>
     */
    public static function examplePlatformPack(): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => 'Wise Platform',
            'notes' => 'Platform target: shared across all API keys. Publish/Promote carefully. No experience lane.',
            'items' => [
                [
                    'lane' => 'knowledge',
                    'type' => 'faq',
                    'scope' => 'platform',
                    'title' => 'Ask product before quoting price',
                    'question' => 'দাম কত?',
                    'answer' => 'কোন প্রোডাক্টের দাম জানতে চান? নাম বা ছবি পাঠালে নির্দিষ্ট দাম বলতে পারব।',
                    'keywords' => ['দাম', 'price', 'dam'],
                ],
                [
                    'lane' => 'knowledge',
                    'type' => 'script',
                    'scope' => 'platform',
                    'title' => 'Greeting tone BD commerce',
                    'question' => 'আসসালামু আলাইকুম',
                    'answer' => 'ওয়ালাইকুম সালাম! কীভাবে সাহায্য করতে পারি?',
                    'keywords' => ['salam', 'hello', 'হাই'],
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'plz',
                    'to' => 'please',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'abbrev',
                    'from' => 'tnx',
                    'to' => 'thank you',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'tumar',
                    'to' => 'তোমার',
                    'pack_slug' => 'core-bd',
                ],
                [
                    'lane' => 'language',
                    'category' => 'banglish',
                    'from' => 'apnar',
                    'to' => 'আপনার',
                    'pack_slug' => 'core-bd',
                ],
            ],
        ];
    }

    /**
     * Train UI “Load starter” packs sized to proper-training TARGET (not toy examples).
     * Compact {@see examplePack()} / {@see examplePlatformPack()} stay for docs + Pest.
     *
     * @return array<string, mixed>
     */
    public static function starterPack(string $type): array
    {
        $type = TrainingPrompt::normalizeType($type);
        $target = TrainingPrompt::recommendedTargetItems($type);

        return match ($type) {
            TrainingPrompt::TYPE_PLATFORM => self::buildPlatformStarter($target),
            TrainingPrompt::TYPE_LANGUAGE => self::buildLanguageStarter($target, 'platform'),
            TrainingPrompt::TYPE_KNOWLEDGE => self::buildKnowledgeStarter($target, 'merchant'),
            TrainingPrompt::TYPE_EXPERIENCE => self::buildExperienceStarter($target),
            default => self::buildMerchantStarter($target),
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function starterPacks(): array
    {
        $out = [];
        foreach (TrainingPrompt::types() as $type) {
            $out[$type] = self::starterPack($type);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildMerchantStarter(int $target): array
    {
        // ~ knowledge 14 + language 12 + experience 4 ≈ 30
        $knowledgeN = max(12, (int) round($target * 0.47));
        $languageN = max(10, (int) round($target * 0.40));
        $experienceN = max(3, $target - $knowledgeN - $languageN);

        return [
            'version' => self::VERSION,
            'merchant' => 'Example Fashion BD',
            'notes' => 'Proper-training starter (~TARGET). Edit facts before Publish/Promote. Never invent prices.',
            'items' => array_merge(
                self::knowledgeItems($knowledgeN, 'merchant'),
                self::languageItems($languageN),
                self::experienceItems($experienceN),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPlatformStarter(int $target): array
    {
        $knowledgeN = max(8, (int) round($target * 0.42));
        $languageN = max($target - $knowledgeN, 12);

        return [
            'version' => self::VERSION,
            'merchant' => 'Wise Platform',
            'notes' => 'Platform proper-training starter — shared scripts + slang. No experience. No store prices.',
            'items' => array_merge(
                self::knowledgeItems($knowledgeN, 'platform'),
                self::languageItems($languageN),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildKnowledgeStarter(int $target, string $scope): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => $scope === 'platform' ? 'Wise Platform' : 'Example Fashion BD',
            'notes' => 'Knowledge-only proper-training starter — must Publish drafts after Import.',
            'items' => self::knowledgeItems($target, $scope),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildLanguageStarter(int $target, string $scopeLabel): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => $scopeLabel === 'platform' ? 'Wise Platform' : 'Example Fashion BD',
            'notes' => 'Language-only proper-training starter — Promote in Language tab after Import.',
            'items' => self::languageItems($target),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildExperienceStarter(int $target): array
    {
        return [
            'version' => self::VERSION,
            'merchant' => 'Example Fashion BD',
            'notes' => 'Experience-only starter — soft-hints, not facts. Merchant key required.',
            'items' => self::experienceItems($target),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function knowledgeItems(int $count, string $scope): array
    {
        $templates = $scope === 'platform'
            ? [
                ['script', 'Ask product before quoting price', 'দাম কত?', 'কোন প্রোডাক্টের দাম জানতে চান? নাম বা ছবি পাঠালে নির্দিষ্ট দাম বলতে পারব।', ['দাম', 'price', 'dam']],
                ['script', 'Greeting tone BD commerce', 'আসসালামু আলাইকুম', 'ওয়ালাইকুম সালাম! কীভাবে সাহায্য করতে পারি?', ['salam', 'hello', 'হাই']],
                ['script', 'Handoff when angry', 'খারাপ সার্ভিস', 'দুঃখিত — এখনই একজন হিউম্যান এজেন্টকে কানেক্ট করছি।', ['angry', 'কমপ্লেইন', 'agent']],
                ['script', 'Clarify size/color', 'কোনটা নিব?', 'সাইজ/কালার বলুন, অথবা ছবি পাঠান — শর্টলিস্ট করে দিই।', ['সাইজ', 'কালার', 'shortlist']],
                ['script', 'Out of stock honesty', 'স্টক আছে?', 'স্টক নিশ্চিত করে বলব — একটু অপেক্ষা করুন বা হিউম্যানকে জিজ্ঞাসা করুন।', ['stock', 'স্টক']],
                ['script', 'No invented COD fee', 'ক্যাশ অন ডেলিভারি?', 'COD চলে কিনা স্টোর পলিসি দেখে বলতে হবে — অনুমান করে চার্জ বলব না।', ['cod', 'ক্যাশ']],
                ['script', 'Order status ask', 'অর্ডার কোথায়?', 'অর্ডার আইডি বা ফোন নম্বর দিলে স্ট্যাটাস চেক করে বলতে পারি।', ['order', 'ট্র্যাক']],
                ['script', 'Polite close', 'ঠিক আছে', 'ধন্যবাদ! আর কিছু লাগলে লিখবেন।', ['thanks', 'ok']],
                ['script', 'Photo request', 'দেখতে চাই', 'ছবি বা লিংক পাঠালে মিলিয়ে বলতে পারি।', ['photo', 'ছবি']],
                ['script', 'Address confirm', 'ঠিকানা', 'ডেলিভারির পূর্ণ ঠিকানা + ফোন একবার নিশ্চিত করুন।', ['address', 'ঠিকানা']],
                ['script', 'Refund escalate', 'টাকা ফেরত', 'রিফান্ড কেস হিউম্যান টিমে পাঠাচ্ছি — নীতিমালা অনুযায়ী সমাধান হবে।', ['refund', 'রিফান্ড']],
                ['script', 'Language slow down', 'বুঝি না', 'আরেকটু সহজ করে বলুন — প্রোডাক্ট নাম বা সমস্যাটা কী?', ['clarify']],
            ]
            : [
                ['faq', 'Delivery charge ask', 'ঢাকায় ডেলিভারি চার্জ কত?', 'ডেলিভারি চার্জ এলাকা অনুযায়ী। জেলা/এলাকা লিখলে স্টোর পলিসি দেখে জানাব — আন্দাজ করে টাকা বলব না।', ['delivery', 'ডেলিভারি', 'charge', 'ঢাকা']],
                ['policy', 'Return policy ask', 'রিটার্ন করা যাবে?', 'রিটার্ন/এক্সচেঞ্জ স্টোর নীতিমালা অনুযায়ী। অর্ডার নম্বর দিলে হিউম্যান নিশ্চিত করে জানাবে — দিন সংখ্যা আন্দাজ করব না।', ['return', 'রিটার্ন', 'ফেরত']],
                ['faq', 'Payment methods', 'পেমেন্ট কীভাবে করব?', 'কোন মাধ্যমে পেমেন্ট করতে চান বলুন। স্টোর যেগুলো এক্সেপ্ট করে সেটা নিশ্চিত করে জানাব।', ['payment', 'বিকাশ', 'ক্যাশ', 'নগদ']],
                ['faq', 'Outside Dhaka delivery', 'ঢাকার বাইরে ডেলিভারি?', 'ঢাকার বাইরে চার্জ এলাকাভেদে — জেলা/এলাকা লিখলে স্টোর পলিসি দেখে বলব।', ['outside', 'জেলা']],
                ['faq', 'Delivery time', 'কতদিনে পৌঁছাবে?', 'ডেলিভারি সময় এলাকা ও স্টক অনুযায়ী। এলাকা বললে নিশ্চিত করে জানাব — আন্দাজ করব না।', ['time', 'দিন']],
                ['faq', 'Exchange policy', 'এক্সচেঞ্জ হয়?', 'সাইজ/কালার এক্সচেঞ্জ স্টোর নীতি ও স্টক অনুযায়ী — হিউম্যান নিশ্চিত করে জানাবে।', ['exchange', 'এক্সচেঞ্জ']],
                ['faq', 'Bkash number', 'বিকাশ নাম্বার?', 'অর্ডার কনফার্মের পর অফিসিয়াল নম্বর মেসেজে যায় — এখানে আন্দাজ করে নম্বর দিব না।', ['বিকাশ']],
                ['faq', 'Order cancel', 'অর্ডার ক্যান্সেল?', 'পার্সেল পাঠানোর আগে ক্যান্সেল সম্ভব কিনা অর্ডার আইডি দিলে হিউম্যান নিশ্চিত করবে।', ['cancel', 'ক্যান্সেল']],
                ['faq', 'Stock check', 'স্টক আছে?', 'কোন প্রোডাক্ট/সাইজ বলুন — স্টক চেক করে বলব।', ['stock']],
                ['faq', 'Wholesale', 'হোলসেল?', 'হোলসেল রেট পরিমাণ ও স্টোর পলিসি অনুযায়ী — পরিমাণ লিখলে হিউম্যান কনফার্ম করবে।', ['wholesale']],
                ['faq', 'Gift wrap', 'গিফট র‍্যাপ?', 'উপহার র‍্যাপিং অপশন আছে কিনা স্টোর দেখে বলব — অর্ডারে নোট দিন।', ['gift']],
                ['faq', 'Color options', 'কোন কালার আছে?', 'প্রোডাক্ট নাম বলুন — উপলব্ধ কালার স্টক দেখে দিই।', ['color', 'কালার']],
                ['faq', 'Size chart', 'সাইজ চার্ট?', 'সাইজ চার্ট ছবি/লিংক চাইলে পাঠাব — অথবা হিউম্যান।', ['size', 'সাইজ']],
                ['faq', 'Damaged item', 'প্রোডাক্ট নষ্ট?', 'আনবক্সিং ভিডিও রাখুন — অর্ডার নম্বর দিলে রিপ্লেসমেন্ট নীতি দেখে জানাব।', ['damage']],
                ['faq', 'Hotline', 'ফোন নম্বর?', 'সাপোর্ট হটলাইন অর্ডার কনফার্ম মেসেজে থাকে — এখানে অনুমান করে দিব না।', ['phone']],
                ['faq', 'Advance payment', 'অ্যাডভান্স?', 'কিছু এলাকায় অ্যাডভান্স লাগতে পারে — এলাকা বললে স্টোর পলিসি দেখে নিশ্চিত করি।', ['advance']],
                ['faq', 'Track parcel', 'পার্সেল ট্র্যাক?', 'কুরিয়ার ট্র্যাকিং নম্বর পার্সেল পাঠানোর পর মেসেজে যায়।', ['track']],
                ['faq', 'Same day', 'সেম ডে ডেলিভারি?', 'সিলেক্টেড এলাকায় সম্ভব কিনা এলাকা + অর্ডার সময় বললে নিশ্চিত করি।', ['same day']],
                ['faq', 'Product authenticity', 'অরিজিনাল?', 'প্রোডাক্ট বর্ণনা পেজে যা লেখা আছে সেটাই বলি — ব্র্যান্ড ক্লেইম না থাকলে বলব না।', ['original']],
                ['faq', 'Invoice', 'ইনভয়েস?', 'ডেলিভারির সাথে ইনভয়েস যায়; না পেলে অর্ডার আইডি দিন।', ['invoice']],
            ];

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $t = $templates[$i % count($templates)];
            $suffix = $i < count($templates) ? '' : ' ('.((int) floor($i / count($templates)) + 1).')';
            $items[] = [
                'lane' => 'knowledge',
                'type' => $t[0],
                'scope' => $scope,
                'title' => $t[1].$suffix,
                'question' => $t[2],
                'answer' => $t[3],
                'keywords' => $t[4],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function languageItems(int $count): array
    {
        $pairs = [
            ['abbrev', 'plz', 'please'],
            ['abbrev', 'tnx', 'thank you'],
            ['abbrev', 'tmr', 'tomorrow'],
            ['abbrev', 'asap', 'as soon as possible'],
            ['abbrev', 'msg', 'message'],
            ['abbrev', 'pic', 'picture'],
            ['abbrev', 'qty', 'quantity'],
            ['abbrev', 'addr', 'address'],
            ['abbrev', 'phn', 'phone'],
            ['abbrev', 'ordr', 'order'],
            ['abbrev', 'dlvry', 'delivery'],
            ['abbrev', 'pymnt', 'payment'],
            ['banglish', 'tumar', 'তোমার'],
            ['banglish', 'apnar', 'আপনার'],
            ['banglish', 'dam koto', 'দাম কত'],
            ['banglish', 'ase', 'আছে'],
            ['banglish', 'nai', 'নাই'],
            ['banglish', 'koto', 'কত'],
            ['banglish', 'kinbo', 'কিনব'],
            ['banglish', 'pathao', 'পাঠাও'],
            ['banglish', 'bhai', 'ভাই'],
            ['banglish', 'apa', 'আপা'],
            ['banglish', 'thik ase', 'ঠিক আছে'],
            ['banglish', 'kotodin', 'কতদিন'],
            ['banglish', 'ferot', 'ফেরত'],
            ['banglish', 'stok', 'স্টক'],
            ['banglish', 'rong', 'রং'],
            ['banglish', 'size ta', 'সাইজটা'],
            ['banglish', 'bkash', 'বিকাশ'],
            ['banglish', 'nagad', 'নগদ'],
            ['banglish', 'cod', 'cash on delivery'],
            ['banglish', 'home delivery', 'হোম ডেলিভারি'],
            ['banglish', 'discount', 'ছাড়'],
            ['banglish', 'offer', 'অফার'],
            ['banglish', 'last price', 'শেষ দাম'],
            ['banglish', 'confirm', 'কনফার্ম'],
            ['banglish', 'cancel', 'ক্যান্সেল'],
            ['banglish', 'track', 'ট্র্যাক'],
            ['banglish', 'invoice', 'ইনভয়েস'],
            ['banglish', 'warranty', 'ওয়ারেন্টি'],
        ];

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $p = $pairs[$i % count($pairs)];
            $from = $p[1];
            if ($i >= count($pairs)) {
                $from = $p[1].' '.((int) floor($i / count($pairs)) + 1);
            }
            $items[] = [
                'lane' => 'language',
                'category' => $p[0],
                'from' => $from,
                'to' => $p[2],
                'pack_slug' => 'core-bd',
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function experienceItems(int $count): array
    {
        $templates = [
            ['price', 'clarify', 'script:ask_price_bare.clarify', 'When customer says only “dam?”, clarify product name first.'],
            ['price', 'shortlist', 'script:ask_price_ambiguous.shortlist', 'Ambiguous product mention → shortlist 2–3 options, do not invent price.'],
            ['stock', 'clarify', 'script:stock_bare.clarify', 'Bare “stock?” → ask which SKU/size before answering.'],
            ['delivery', 'clarify', 'script:delivery_area.clarify', 'Delivery fee questions need area/district first.'],
            ['return', 'handoff', 'script:return_dispute.handoff', 'Angry return disputes → handoff human; do not invent policy.'],
            ['payment', 'clarify', 'script:payment_method.clarify', 'Payment how? → list only confirmed methods from knowledge.'],
            ['order_status', 'clarify', 'script:order_status.clarify', 'Status asks need order id or phone.'],
            ['greeting', 'assist', 'script:greeting.warm', 'Salam/hi → warm short greeting then ask need.'],
            ['complaint', 'handoff', 'script:complaint.handoff', 'Abuse/threat → apologize + human handoff.'],
            ['upsell', 'assist', 'script:after_price.assist', 'After price clarify, offer size/color help — never force upsell.'],
            ['size', 'clarify', 'script:size_missing.clarify', 'Missing size → ask size chart or measurement.'],
            ['color', 'shortlist', 'script:color_choice.shortlist', 'Color unclear → shortlist available colors from catalog context.'],
            ['cancel', 'clarify', 'script:cancel_intent.clarify', 'Cancel intent → confirm order id before process.'],
            ['discount', 'clarify', 'script:discount_ask.clarify', 'Discount ask → do not invent %; check offer knowledge or handoff.'],
            ['photo', 'assist', 'script:photo_request.assist', 'Customer wants to see → ask for product name then share link/photo flow.'],
            ['address', 'clarify', 'script:address_incomplete.clarify', 'Incomplete address → ask landmark + phone.'],
        ];

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $t = $templates[$i % count($templates)];
            $key = $t[2];
            if ($i >= count($templates)) {
                $key .= '.'.((int) floor($i / count($templates)) + 1);
            }
            $items[] = [
                'lane' => 'experience',
                'signal_type' => 'external',
                'intent' => $t[0],
                'action' => $t[1],
                'weight' => 1.5,
                'pattern_key' => $key,
                'note' => $t[3],
            ];
        }

        return $items;
    }
}
