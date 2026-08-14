<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import AdsRoasCalculatorSection from '@/components/marketing/AdsRoasCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    adsRoasCalculator: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const steps = [
    'মাসিক Facebook Ads স্পেন্ড স্লাইডারে সেট করুন (Ads Manager বা পেমেন্ট রিপোর্ট থেকে)।',
    'একই মাসে Pixel-এ কতগুলো Purchase ইভেন্ট গেছে তা দিন।',
    'ফেক / ক্যানসেল / রিটার্ন রেট ও গড় অর্ডার ভ্যালু (AOV) মিলিয়ে নিন।',
    'রিপোর্টেড vs আসল ROAS দেখুন — ফেক Purchase সিগন্যাল ও আনুমানিক বাজেট অপচয় নোট করুন।',
];

const pillars = [
    {
        title: 'রিপোর্টেড ROAS কী দেখায়',
        body: 'Pixel-এ যাওয়া সব Purchase × AOV ÷ অ্যাড স্পেন্ড। Ads Manager-এ যে সংখ্যা দেখেন — তাতে ফেক, ক্যানসেল ও রিটার্ন অর্ডারও মিশে থাকতে পারে।',
    },
    {
        title: 'আসল ROAS কেন আলাদা',
        body: 'শুধু কনফার্মড/ডেলিভার্ড অর্ডার ধরলে ROAS কমে আসা স্বাভাবিক। এটাই ক্যাম্পেইন স্কেল করার আগে দেখার সংখ্যা।',
    },
    {
        title: 'ফেক Purchase কেন ক্ষতিকর',
        body: 'Facebook ভুল অডিয়েন্সে অপটিমাইজ করে। বাজেট বাড়লেও ডেলিভারি ও ক্যাশফ্লো খারাপ হতে পারে।',
    },
    {
        title: 'WooEasyLife কী করে',
        body: 'ফ্রড চেক, ফেক অর্ডার প্রোটেকশন ও পিক্সেল প্রোটেকশন দিয়ে শুধু কনফার্মড Purchase পাঠাতে সাহায্য করে — অপটিমাইজেশন পরিষ্কার থাকে।',
    },
];

const playbook = [
    {
        title: 'ফেক রেট কমান',
        body: 'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে কুরিয়ার সাকসেস রেট দেখুন। ঝুঁকিপূর্ণ অর্ডার পার্সেল পাঠাবেন না।',
        href: '/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
    },
    {
        title: 'চেকআউট সুরক্ষা চালু করুন',
        body: 'OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে একই ফেক প্যাটার্ন বারবার আসা কমান।',
        href: '/fake-order-protection',
        linkLabel: 'ফেক অর্ডার প্রোটেকশন',
    },
    {
        title: 'রিটার্ন লস মাপুন',
        body: 'প্রতি রিটার্নের কুরিয়ার ও প্যাক খরচ হিসাব করে মাসিক লস দেখুন — অ্যাড বাজেটের পাশাপাশি অপারেশন লসও বোঝা যায়।',
        href: '/return-loss-calculator',
        linkLabel: 'রিটার্ন লস ক্যালকুলেটর',
    },
];

const guideSections = [
    {
        heading: 'ফেক Purchase বাদ দিয়ে আসল Facebook Ads ROAS কত — কেন জরুরি',
        paragraphs: [
            'বাংলাদেশি COD ও WooCommerce সেলাররা Facebook Ads Manager-এ উচ্চ ROAS দেখে প্রায়ই বাজেট বাড়ায়। কিন্তু Pixel-এ যাওয়া প্রতিটি Purchase যে কনফার্মড বা ডেলিভার্ড অর্ডার নয় — এটাই লুকানো লস। ফেক, ক্যানসেল ও রিটার্ন মিশে থাকলে রিপোর্টেড ROAS ফুলে দেখায়, আর বিকাশ বা ব্যাংকে লাভ কম থাকে।',
            'এই পেজের Facebook Ads ROAS ক্যালকুলেটর স্পেন্ড, Pixel Purchase, ফেক/ক্যানসেল রেট ও AOV দিয়ে রিপোর্টেড vs আসল ROAS তুলনা করে। সংখ্যা শিক্ষামূলক — Attribution উইন্ডো ও ডেলিভারি রেট অনুযায়ী ফল ভিন্ন হতে পারে। সিদ্ধান্ত নেওয়ার আগে নিজের স্টোরের হার মিলিয়ে নিন।',
        ],
    },
    {
        heading: 'রিপোর্টেড ROAS vs আসল ROAS — পার্থক্য কোথায়',
        paragraphs: [
            'রিপোর্টেড ROAS = (Pixel Purchase × AOV) ÷ Ads স্পেন্ড। Ads Manager যে সংখ্যা দেখায়, তার কাছাকাছি। আসল ROAS = (কনফার্মড Purchase × AOV) ÷ Ads স্পেন্ড — যেখানে কনফার্মড ≈ Pixel Purchase × (১ − ফেক/ক্যানসেল%)।',
            'ফেক Purchase থাকলে Facebook ভুল অডিয়েন্সে অপটিমাইজ করে। বাজেট বাড়লেও রিটার্ন ও নেগেটিভ ক্যাশফ্লো বাড়তে পারে। WooEasyLife পিক্সেল প্রোটেকশন শুধু কনফার্মড অর্ডারকে Purchase হিসেবে পাঠাতে সাহায্য করে, যাতে অপটিমাইজেশন পরিষ্কার ডাটায় চলে।',
        ],
    },
    {
        heading: 'উদাহরণ: ৳৮৫০,০০০ স্পেন্ড ও ২০০ Purchase',
        paragraphs: [
            'ধরুন মাসিক Ads স্পেন্ড ৳৮৫০,০০০ এবং Pixel-এ ২০০টি Purchase। AOV বেশি হলে রিপোর্টেড ROAS ~৪.৮x দেখা যেতে পারে। কিন্তু ফেক/ক্যানসেল রেট ৩০% হলে কনফার্মড Purchase কমে যায় — আসল ROAS তখন উল্লেখযোগ্যভাবে নিচে নামে।',
            'স্লাইডারে নিজের সংখ্যা বসিয়ে গ্যাপটা দেখুন। গ্যাপ বড় হলে আগে ফ্রড চেক ও ফেক অর্ডার প্রোটেকশন ঠিক করুন, তারপর বাজেট স্কেল করুন। শুধু রিপোর্টেড ROAS দেখে দ্বিগুণ বাজেট দেওয়া ঝুঁকিপূর্ণ।',
        ],
    },
    {
        heading: 'আসল ROAS বাড়াতে WooEasyLife ওয়ার্কফ্লো',
        paragraphs: [
            'প্রথমে অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে কুরিয়ার সাকসেস রেট দেখুন — BD Fraud Checker ব্যবহার করুন। তারপর চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু করে একই ঝুঁকি বারবার আসা কমান।',
            'Pixel ইভেন্ট পরিষ্কার রাখুন: শুধু কনফার্মড অর্ডার Purchase হিসেবে যাক। রিটার্ন লস ক্যালকুলেটর দিয়ে মাসিক অপারেশন লস মাপুন। কুরিয়ার অটো এন্ট্রি চালু থাকলে কনফার্মড অর্ডার দ্রুত পার্সেল যায় — অপারেশন লিক কম হয়।',
            'হারানো বা অসম্পূর্ণ অর্ডার রিকভারিও সেলস বাড়াতে সাহায্য করে। কনফার্মড সেলস বাড়লে আসল রেভিনিউ বাড়ে; পরিষ্কার Pixel থাকলে অপটিমাইজেশনও ভালো থাকে। প্ল্যান জানতে প্রাইসিং দেখুন।',
        ],
    },
    {
        heading: 'সাপ্তাহিক অ্যাকশন প্ল্যান',
        paragraphs: [
            'দিন ১: গত সপ্তাহের Ads স্পেন্ড, Pixel Purchase ও রিটার্ন রেট নোট করুন। দিন ২: এই ক্যালকুলেটরে আসল ROAS হিসাব করুন। দিন ৩: খারাপ অ্যাডসেট থামান বা বাজেট কমান।',
            'দিন ৪–৫: ফ্রড চেক ও প্রোটেকশন রুটিন টিমকে মানাবেন। দিন ৬: ক্রিয়েটিভ/অফার অডিট — অস্পষ্ট দাম লো-কোয়ালিটি অর্ডার বাড়ায়। দিন ৭: আবার ROAS তুলনা করে পরের সপ্তাহের বাজেট স্থির করুন।',
        ],
    },
];

const mistakeList = [
    'শুধু Ads Manager ROAS দেখে বাজেট দ্বিগুণ করা।',
    'ক্যানসেল/রিটার্ন বাদ না দিয়ে Purchase কাউন্ট করা।',
    'Attribution উইন্ডো বদলে “বাড়তি” কনভার্শন দেখে স্কেল করা।',
    'ফ্রড চেক ছাড়াই নতুন অডিয়েন্সে বড় বাজেট দেওয়া।',
    'এজেন্সি রিপোর্টের রিপোর্টেড ROAS-কে ক্যাশফ্লো ধরে নেওয়া।',
];

const weeklyChecklist = [
    {
        title: 'স্পেন্ড vs ক্যাশ ইন',
        body: 'সপ্তাহের Ads খরচ ও বিকাশ/ব্যাংকে আসা টাকা পাশাপাশি রাখুন। গ্যাপ বড় হলে ফেক/রিটার্ন রেট আপডেট করে ক্যালকুলেটর চালান।',
    },
    {
        title: 'অ্যাডসেট আলাদা করে দেখুন',
        body: 'যে অ্যাডসেটে Purchase বেশি কিন্তু রিটার্নও বেশি, সেখানে শুধু রিপোর্টেড ROAS দেখে স্কেল করবেন না।',
    },
    {
        title: 'ক্রিয়েটিভ ও অফার',
        body: 'অতিরিক্ত ডিসকাউন্ট বা অস্পষ্ট দাম অনেক সময় লো-কোয়ালিটি অর্ডার বাড়ায় — আসল ROAS নামিয়ে দেয়।',
    },
    {
        title: 'পিক্সেল ইভেন্ট অডিট',
        body: 'টেস্ট অর্ডার, ডুপ্লিকেট Purchase, বা অসম্পূর্ণ চেকআউট থেকে ইভেন্ট যাচ্ছে কিনা চেক করুন।',
    },
];

const formulaRows = [
    { label: 'রিপোর্টেড রেভিনিউ', value: 'Pixel Purchase × AOV' },
    { label: 'রিপোর্টেড ROAS', value: 'রিপোর্টেড রেভিনিউ ÷ Ads স্পেন্ড' },
    { label: 'কনফার্মড Purchase', value: 'Pixel Purchase × (১ − ফেক/ক্যানসেল%)' },
    { label: 'আসল ROAS', value: '(কনফার্মড Purchase × AOV) ÷ Ads স্পেন্ড' },
];

const whoFor = [
    'Facebook Ads চালানো COD / WooCommerce স্টোর',
    'পেজ সেলার যাদের রিটার্ন ও ক্যানসেল বেশি',
    'এজেন্সি বা ইন-হাউস মিডিয়া বায়ার যাদের ক্লায়েন্টকে আসল লাভ বোঝাতে হয়',
    'যারা নতুন ক্যাম্পেইন স্কেল করার আগে ঝুঁকি মাপতে চান',
];
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="tools">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-sky-300/90">Facebook Ads ROAS ক্যালকুলেটর</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'ফেক Purchase বাদ দিয়ে আসল Ads ROAS' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'অ্যাড স্পেন্ড ও Pixel Purchase দিয়ে রিপোর্টেড vs আসল ROAS হিসাব করুন।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    বাংলাদেশি COD ও WooCommerce সেলারদের জন্য ফ্রি শিক্ষামূলক টুল।
                    Pixel Purchase ≠ কনফার্মড অর্ডার — স্লাইডার দিয়ে আসল ROAS ও অ্যাড বাজেট অপচয় দেখুন।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#calculator"
                        class="inline-flex rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-400"
                    >
                        ক্যালকুলেটর খুলুন
                    </a>
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ফ্রি ফ্রড চেক
                    </Link>
                    <Link
                        href="/en/ads-roas-calculator"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
                    >
                        English version
                    </Link>
                </div>
            </div>
        </section>

        <!-- দ্রুত উত্তর (featured snippet style) -->
        <section class="border-b border-white/10 bg-sky-950/20 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-sky-200 sm:text-2xl">দ্রুত উত্তর</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    ফেক Purchase বাদ দিয়ে আসল Facebook Ads ROAS বের করতে অ্যাড স্পেন্ড, Pixel Purchase, ফেক/ক্যানসেল রেট ও AOV দিন।
                    রিপোর্টেড ROAS Pixel-এর সব Purchase ধরে; আসল ROAS শুধু কনফার্মড অর্ডার ধরে।
                    COD স্টোরে গ্যাপ বড় হলে আগে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেক</Link>
                    ও
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>
                    চালু করুন, তারপর বাজেট স্কেল করুন। WooEasyLife পিক্সেল প্রোটেকশন শুধু কনফার্মড Purchase পাঠাতে সাহায্য করে।
                </p>
            </div>
        </section>

        <section class="px-4 py-10 lg:px-8">
            <div class="mx-auto grid max-w-5xl gap-4 sm:grid-cols-2">
                <article
                    v-for="item in pillars"
                    :key="item.title"
                    class="rounded-2xl border border-white/10 bg-white/5 p-5 text-left"
                >
                    <h2 class="text-base font-bold text-white sm:text-lg">{{ item.title }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ item.body }}</p>
                </article>
            </div>
        </section>

        <AdsRoasCalculatorSection
            :config="adsRoasCalculator"
            :primary-cta-url="ctaUrl"
            :primary-cta-label="ctaLabel"
            :show-intro="false"
        />

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">কীভাবে ব্যবহার করবেন</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    চার ধাপে রিপোর্টেড ও আসল ROAS তুলনা করুন। সংখ্যা শিক্ষামূলক — Attribution ও ডেলিভারি রেট অনুযায়ী ফল ভিন্ন হতে পারে।
                </p>
                <ol class="mt-8 space-y-3">
                    <li
                        v-for="(step, i) in steps"
                        :key="step"
                        class="flex gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300"
                    >
                        <span class="font-bold text-amber-400">{{ String(i + 1).padStart(2, '0') }}</span>
                        <span>{{ step }}</span>
                    </li>
                </ol>
            </div>
        </section>

        <!-- Long-form guide (paste-pack depth, ROAS topic) -->
        <section class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-10">
                <article v-for="section in guideSections" :key="section.heading" class="space-y-3">
                    <h2 class="text-xl font-bold text-white sm:text-2xl">{{ section.heading }}</h2>
                    <p
                        v-for="(paragraph, idx) in section.paragraphs"
                        :key="idx"
                        class="text-sm leading-relaxed text-slate-300 sm:text-base"
                    >
                        <LinkedRichText :text="paragraph" :is-en="false" />
                    </p>
                </article>

                <div class="flex flex-wrap gap-3">
                    <Link href="/bd-fraud-checker" class="text-sm font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker →</Link>
                    <Link href="/fake-order-protection" class="text-sm font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন →</Link>
                    <Link href="/return-loss-calculator" class="text-sm font-semibold text-amber-400 hover:text-amber-300">রিটার্ন লস ক্যালকুলেটর →</Link>
                    <Link href="/pricing" class="text-sm font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং →</Link>
                    <Link href="/en/ads-roas-calculator" class="text-sm font-semibold text-amber-400 hover:text-amber-300">English version →</Link>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">আসল ROAS বাড়াতে করণীয়</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    ক্যালকুলেটর সমস্যা দেখায় — সমাধান হয় ফেক অর্ডার কমিয়ে ও Pixel পরিষ্কার রেখে।
                </p>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in playbook"
                        :key="item.title"
                        class="rounded-2xl border border-white/10 bg-white/5 p-5"
                    >
                        <h3 class="text-base font-bold text-white">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ item.body }}</p>
                        <Link :href="item.href" class="mt-3 inline-flex text-sm font-semibold text-amber-400 hover:text-amber-300">
                            {{ item.linkLabel }} →
                        </Link>
                    </article>
                </div>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">হিসাব কীভাবে কাজ করে</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    ক্যালকুলেটরের পেছনের সরল ফর্মুলা — COD বাস্তবতায় আসল লাভ বোঝার জন্য।
                </p>
                <div class="mt-8 overflow-hidden rounded-2xl border border-white/10">
                    <div
                        v-for="row in formulaRows"
                        :key="row.label"
                        class="flex flex-col gap-1 border-b border-white/10 bg-white/5 px-4 py-3 last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span class="text-sm font-semibold text-slate-200">{{ row.label }}</span>
                        <span class="font-mono text-sm text-amber-300/90">{{ row.value }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">সাপ্তাহিক ROAS চেকলিস্ট</h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="item in weeklyChecklist"
                        :key="item.title"
                        class="rounded-2xl border border-white/10 bg-white/5 p-5"
                    >
                        <h3 class="text-base font-bold text-white">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ item.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-bold text-white">সাধারণ ভুল যা এড়াবেন</h2>
                    <ul class="mt-6 space-y-3">
                        <li
                            v-for="item in mistakeList"
                            :key="item"
                            class="flex gap-3 rounded-xl border border-rose-500/15 bg-rose-950/10 px-4 py-3 text-sm text-slate-300"
                        >
                            <span class="shrink-0 font-bold text-rose-400">×</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-white">কার জন্য এই টুল</h2>
                    <ul class="mt-6 space-y-3">
                        <li
                            v-for="item in whoFor"
                            :key="item"
                            class="flex gap-3 rounded-xl border border-emerald-500/15 bg-emerald-950/10 px-4 py-3 text-sm text-slate-300"
                        >
                            <span class="shrink-0 font-bold text-emerald-400">✓</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <p class="mt-6 text-sm leading-relaxed text-slate-400">
                        কুরিয়ার চার্জ তুলনায়
                        <Link href="/courier-charge-calculator" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার চার্জ ক্যালকুলেটর</Link>
                        · অপারেশনে
                        <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>
                    </p>
                </div>
            </div>
        </section>

        <!-- এআই সারাংশ -->
        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">এআই সারাংশ</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    Facebook Ads ROAS ক্যালকুলেটর দিয়ে রিপোর্টেড vs আসল ROAS তুলনা করুন — ফেক Purchase বাদ দিয়ে।
                    COD সেলারদের জন্য আসল ROASই স্কেল সিদ্ধান্তের ভিত্তি। WooEasyLife ফ্রড চেক, ফেক অর্ডার প্রোটেকশন ও পিক্সেল প্রোটেকশন দিয়ে পরিষ্কার Purchase সিগন্যাল রাখতে সাহায্য করে।
                    হিসাব শিক্ষামূলক; নিজের রিটার্ন রেট মিলিয়ে নিন,                     তারপর
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>
                    থেকে ট্রায়াল শুরু করুন। English:
                    <Link href="/en/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>।
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_ads_roas_calculator"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/fake-order-protection"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    ফেক অর্ডার প্রোটেকশন
                </Link>
                <Link
                    href="/pricing"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    প্রাইসিং
                </Link>
                <Link
                    href="/en/ads-roas-calculator"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10"
                >
                    English version
                </Link>
            </div>
        </section>

        <section v-if="faqs.length" class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">যা জানতে চান</h2>
                <div class="mt-8 space-y-3">
                    <div
                        v-for="(item, i) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white"
                            @click="toggleFaq(i)"
                        >
                            <span>{{ item.q }}</span>
                            <span class="shrink-0 text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-3 text-sm text-slate-300">
                            <LinkedRichText :text="item.a" :is-en="false" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
