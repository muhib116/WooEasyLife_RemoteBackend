<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import RoiCalculatorSection from '@/components/marketing/RoiCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    roiCalculator: { type: Object, default: () => ({}) },
    roiScenarios: { type: Array, default: () => [] },
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
    'দৈনিক অর্ডার সংখ্যা স্লাইডারে সেট করুন।',
    'বর্তমান রিটার্ন/ক্যানসেল রেট দিন।',
    'প্রতি রিটার্নে গড় খরচ (কুরিয়ার + প্যাক + সময়) মিলিয়ে নিন।',
    'মাসিক লস ও সম্ভাব্য সাশ্রয় দেখুন — তারপর ফ্রড চেক বা ট্রায়াল শুরু করুন।',
];

const pillars = [
    {
        title: 'মাসিক রিটার্ন লস',
        body: 'দৈনিক অর্ডার × রিটার্ন রেট × প্রতি রিটার্নের খরচ — এক নজরে মাসিক COD লস দেখুন।',
    },
    {
        title: 'সম্ভাব্য সাশ্রয়',
        body: 'ফ্রড চেক ও প্রোটেকশন দিয়ে রিটার্নের একটি অংশ আটকালে কত বাঁচতে পারে তা আনুমানিক দেখায়।',
    },
    {
        title: 'খরচ কী ধরবেন',
        body: 'কুরিয়ার রিটার্ন চার্জ, প্যাকেজিং ও স্টাফ সময় — অনেক স্টোরে ৳১৫০–৩০০ বা তার বেশি।',
    },
    {
        title: 'পরের ধাপ',
        body: 'লস বেশি হলে ফ্রড চেক + OTP/ব্ল্যাকলিস্ট চালু করুন — শুধু হিসাব নয়, অ্যাকশন নিন।',
    },
];

const guideSections = [
    {
        heading: 'রিটার্ন লস ক্যালকুলেটর দিয়ে কী বুঝবেন',
        paragraphs: [
            'ফ্রি রিটার্ন লস ক্যালকুলেটরে দৈনিক অর্ডার, রিটার্ন/ক্যানসেল রেট ও প্রতি রিটার্নের গড় খরচ দিয়ে মাসিক COD রিটার্ন লস ও সম্ভাব্য সাশ্রয় দেখা যায়।',
            'প্রতি রিটার্নে সাধারণত কুরিয়ার রিটার্ন চার্জ, প্যাকেজিং ও সময়ের খরচ থাকে। স্লাইডার মিলিয়ে আপনার স্টোরের বাস্তব সংখ্যা বসান।',
        ],
    },
    {
        heading: 'হিসাবের ফর্মুলা (সহজ ভাষায়)',
        paragraphs: [
            'মাসিক অর্ডার ≈ দৈনিক অর্ডার × ৩০। মাসিক রিটার্ন ≈ মাসিক অর্ডার × রিটার্ন রেট। মাসিক লস ≈ মাসিক রিটার্ন × প্রতি রিটার্নের খরচ।',
            'সম্ভাব্য সাশ্রয় আনুমানিক — স্লাইডার সরালেই আপডেট হয়। সিদ্ধান্ত সহায়ক, অডিট রিপোর্ট নয়।',
        ],
    },
    {
        heading: 'উদাহরণ: দিনে ৫০ অর্ডার ও ২৫% রিটার্ন',
        paragraphs: [
            'দিনে ৫০ অর্ডার, ২৫% রিটার্ন, প্রতি রিটার্নে ৳১২০ হলে মাসে ≈ ১,৫০০ অর্ডার ও ≈ ৩৭৫ রিটার্ন — লস হাজার হাজার টাকা হতে পারে।',
            'রিটার্ন রেট কমিয়ে বা ফেক অর্ডার আটকিয়ে সাশ্রয় বাড়ে। নিজের সংখ্যা স্লাইডারে বসিয়ে গ্যাপটা দেখুন।',
        ],
    },
    {
        heading: 'রিটার্ন লস কমাতে করণীয়',
        paragraphs: [
            'প্রথমে ফ্রড চেক, তারপর OTP/ব্ল্যাকলিস্ট, তারপর নিরাপদ অর্ডার অটো এন্ট্রি।',
            'অ্যাড বাজেটের দিক থেকে ফেক Purchaseও মাপুন — রিটার্ন লস ও Ads ROAS একসাথে দেখলে সিদ্ধান্ত পরিষ্কার হয়।',
        ],
    },
];

const formulaRows = [
    { label: 'মাসিক অর্ডার', value: 'দৈনিক অর্ডার × ৩০' },
    { label: 'মাসিক রিটার্ন', value: 'মাসিক অর্ডার × রিটার্ন রেট' },
    { label: 'মাসিক লস', value: 'মাসিক রিটার্ন × প্রতি রিটার্নের খরচ' },
    { label: 'সম্ভাব্য সাশ্রয়', value: 'আটকানো রিটার্ন × প্রতি রিটার্নের খরচ' },
];

const playbook = [
    {
        title: 'ফ্রড চেক করুন',
        body: 'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে সাকসেস রেট দেখুন।',
        href: '/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
    },
    {
        title: 'প্রোটেকশন চালু করুন',
        body: 'OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে একই ঝুঁকি বারবার আসা কমান।',
        href: '/fake-order-protection',
        linkLabel: 'ফেক অর্ডার প্রোটেকশন',
    },
    {
        title: 'আসল ROAS দেখুন',
        body: 'ফেক Purchase বাদ দিয়ে রিপোর্টেড vs আসল Ads ROAS তুলনা করুন।',
        href: '/ads-roas-calculator',
        linkLabel: 'Ads ROAS ক্যালকুলেটর',
    },
];

const mistakeList = [
    'প্রতি রিটার্নের খরচ শুধু কুরিয়ার ফি ধরে প্যাকিং/সময় বাদ দেওয়া।',
    'একবার হিসাব করে মাসের বাকিটা অচেক রাখা।',
    'লস দেখেও ফ্রড চেক/OTP না চালু করা।',
    'শুধু Ads Manager ROAS দেখে বাজেট বাড়ানো।',
    'রিটার্ন লস না মাপেই নতুন অডিয়েন্সে বড় স্পেন্ড দেওয়া।',
];

const whoFor = [
    'COD / WooCommerce স্টোর যাদের রিটার্ন বেশি',
    'Facebook পেজ সেলার যারা মাসিক লস হিসাব করতে চান',
    'এজেন্সি/টিম যারা ক্লায়েন্টকে রিটার্ন খরচ বোঝাতে চায়',
    'অ্যাড স্কেলের আগে অপারেশন লস মাপতে চাওয়া টিম',
];

const relatedLinks = [
    { href: '/woocommerce-bangladesh', label: 'WooCommerce Bangladesh গাইড' },
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/fake-order-protection', label: 'ফেক অর্ডার প্রোটেকশন' },
    { href: '/ads-roas-calculator', label: 'Ads ROAS ক্যালকুলেটর' },
    { href: '/courier-charge-calculator', label: 'কুরিয়ার চার্জ ক্যালকুলেটর' },
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/return-loss-calculator', label: 'English version' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-emerald-300/90">রিটার্ন লস ক্যালকুলেটর</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'রিটার্ন লস কমিয়ে মাসে কত টাকা বাঁচাতে পারবেন?' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'দৈনিক অর্ডার আর রিটার্ন রেট দিন — মাসিক লস ও সাশ্রয় দেখুন।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    বাংলাদেশি COD ও WooCommerce সেলারদের জন্য ফ্রি শিক্ষামূলক টুল — স্লাইডার দিয়ে তাৎক্ষণিক হিসাব।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#calculator"
                        class="inline-flex rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-emerald-400"
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
                        href="/en/return-loss-calculator"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
                    >
                        English version
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-b border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">দ্রুত উত্তর</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    রিটার্ন লস ক্যালকুলেটরে দৈনিক অর্ডার, রিটার্ন/ক্যানসেল রেট ও প্রতি রিটার্নের গড় খরচ দিয়ে মাসিক COD রিটার্ন লস ও সম্ভাব্য সাশ্রয় দেখুন।
                    হিসাব শিক্ষামূলক। লস বেশি হলে আগে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেক</Link>
                    ও
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>
                    চালু করুন।
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

        <RoiCalculatorSection
            :config="roiCalculator"
            :scenarios="roiScenarios"
            :primary-cta-url="ctaUrl"
            :primary-cta-label="ctaLabel"
            :show-intro="false"
            locale="bn"
        />

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">কীভাবে ব্যবহার করবেন</h2>
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

        <section class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-10">
                <article v-for="section in guideSections" :key="section.heading" class="space-y-3">
                    <h2 class="text-xl font-bold text-white sm:text-2xl">{{ section.heading }}</h2>
                    <p
                        v-for="(paragraph, idx) in section.paragraphs"
                        :key="idx"
                        class="text-sm leading-relaxed text-slate-300 sm:text-base"
                    >
                        <template v-if="section.heading.startsWith('রিটার্ন লস কমাতে') && idx === 0">
                            প্রথমে
                            <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>,
                            তারপর
                            <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>,
                            তারপর নিরাপদ অর্ডার
                            <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>।
                        </template>
                        <template v-else-if="section.heading.startsWith('রিটার্ন লস কমাতে') && idx === 1">
                            অ্যাড বাজেটের দিক থেকে ফেক Purchaseও মাপুন —
                            <Link href="/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Ads ROAS ক্যালকুলেটর</Link>
                            দিয়ে রিপোর্টেড vs আসল ROAS দেখুন।
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="false" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">হিসাব কীভাবে কাজ করে</h2>
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

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">রিটার্ন লস কমাতে করণীয়</h2>
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
                    <div class="mt-6 flex flex-wrap gap-2">
                        <Link
                            v-for="link in relatedLinks"
                            :key="link.href"
                            :href="link.href"
                            class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-white/10"
                        >
                            {{ link.label }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">এআই সারাংশ</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    WooEasyLife রিটার্ন লস ক্যালকুলেটর দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ দিয়ে মাসিক COD রিটার্ন লস ও সম্ভাব্য সাশ্রয় দেখায়।
                    হিসাব শিক্ষামূলক। লস কমাতে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেক</Link>
                    ও
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">প্রোটেকশন</Link>
                    চালু করুন। English:
                    <Link href="/en/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>।
                    শুরু:
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>।
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_return_loss_calculator"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/bd-fraud-checker"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    ফ্রি ফ্রড চেক
                </Link>
                <Link
                    href="/pricing"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    প্রাইসিং
                </Link>
            </div>
        </section>

        <section v-if="faqs.length" id="faq" class="scroll-mt-24 border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
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
