<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const pillars = [
    {
        title: 'কনফার্ম = অটো এন্ট্রি',
        body: 'অর্ডার কনফার্ম হলেই Pathao, Steadfast বা RedX-এ নাম, ফোন, ঠিকানা ও COD অ্যামাউন্ট স্বয়ংক্রিয় এন্ট্রি — ম্যানুয়াল কপি-পেস্ট লাগে না।',
    },
    {
        title: '৩ কুরিয়ার এক ড্যাশবোর্ড',
        body: 'আলাদা প্যানেলে বারবার লগইন বাদ। WooEasyLife থেকেই এন্ট্রি, স্ট্যাটাস সিঙ্ক ও কাস্টমার SMS।',
    },
    {
        title: 'দিনে ৩+ ঘণ্টা বাঁচান',
        body: 'প্রতি অর্ডারে কয়েক মিনিট × ডজন ডজন অর্ডার = ঘণ্টার পর ঘণ্টা। অটো এন্ট্রিতে স্টাফ প্যাকিং ও ফলো-আপে ফোকাস করে।',
    },
    {
        title: 'পার্সেল নোট হিস্ট্রি',
        body: 'Steadfast পার্সেল নোট ও হিস্ট্রি WooEasyLife থেকেই দেখা ও আপডেট — আলাদা কুরিয়ার সাইটে যেতে হয় না।',
    },
];

const steps = [
    'কুরিয়ার অ্যাকাউন্ট (Pathao / Steadfast / RedX) WooEasyLife-এ কানেক্ট করুন।',
    'নতুন অর্ডারে আগে BD Fraud Checker দিয়ে মোবাইল নম্বর চেক করুন।',
    'ঝুঁকি কম হলে অর্ডার কনফার্ম করুন — কনফার্ম হলেই কুরিয়ারে অটো এন্ট্রি।',
    'স্ট্যাটাস সিঙ্ক ও SMS চালু রাখুন; প্রয়োজনে পার্সেল নোট হিস্ট্রি থেকে ফলো-আপ নোট রাখুন।',
];

const guideSections = [
    {
        heading: 'কুরিয়ার অটো এন্ট্রি কী এবং কেন লাগে',
        paragraphs: [
            'বাংলাদেশি COD ব্যবসায় প্রতি অর্ডারে কুরিয়ার ওয়েবসাইটে নাম, ফোন, ঠিকানা ও COD অ্যামাউন্ট হাতে টাইপ করা দিনে ঘণ্টার পর ঘণ্টা নষ্ট করে। ভুল এন্ট্রি মানে ভুল ডেলিভারি, রিটার্ন ও কাস্টমার অভিযোগ। WooEasyLife কুরিয়ার অটো এন্ট্রিতে অর্ডার কনফার্ম = Pathao, Steadfast বা RedX-এ স্বয়ংক্রিয় পার্সেল এন্ট্রি।',
            'এক ড্যাশবোর্ড থেকে তিন কুরিয়ার, স্ট্যাটাস সিঙ্ক ও কাস্টমার SMS — স্টাফ কুরিয়ার সাইটে বারবার লগইন করে না। দিনে ৫০+ অর্ডারও স্মুথলি চলে; প্যাকিং ও ফলো-আপে বেশি সময় যায়।',
        ],
    },
    {
        heading: 'হাতে এন্ট্রি vs অটো — সময় ও ভুল',
        paragraphs: [
            'ম্যানুয়াল এন্ট্রিতে প্রতি অর্ডার কয়েক মিনিট লাগে। ৫০ অর্ডার × ২ মিনিট = দিনে ~১০০ মিনিট শুধু কপি-পেস্ট। মাসে স্টাফ সময়ের খরচ হাজার হাজার টাকা হতে পারে। WooEasyLife অটো এন্ট্রিতে কনফার্ম ক্লিকই যথেষ্ট।',
            'হাতে টাইপে ভুল নম্বর বা ভুল এলাকা সাধারণ। অটো এন্ট্রি WooCommerce অর্ডার ডাটা থেকে যায়, তাই টাইপো কম। চূড়ান্ত চার্জ কুরিয়ার প্ল্যান অনুযায়ী হতে পারে — আগে কুরিয়ার চার্জ ক্যালকুলেটর দিয়ে আনুমানিক তুলনা করুন।',
        ],
    },
    {
        heading: 'স্ট্যাটাস সিঙ্ক, SMS ও পার্সেল নোট হিস্ট্রি',
        paragraphs: [
            'কুরিয়ার অটো স্ট্যাটাস সিঙ্ক ডেলিভারি বা রিটার্ন আপডেট WooCommerce অর্ডারে নিয়ে আসে — আলাদা ট্যাবে বারবার চেক করতে হয় না। অর্ডার ও ডেলিভারি আপডেট SMS পাঠালে «পার্সেল কোথায়?» কল কমে।',
            'Steadfast পার্সেল নোট হিস্ট্রি WooEasyLife থেকেই দেখা ও মার্চেন্ট নোট আপডেট করা যায়। টিম নোট, কল রেকর্ড ও ফলো-আপ এক জায়গায় রাখা যায়।',
        ],
    },
    {
        heading: 'নিরাপদ ওয়ার্কফ্লো: ফ্রড চেক → কনফার্ম → অটো এন্ট্রি',
        paragraphs: [
            'অটো এন্ট্রি দ্রুত, কিন্তু ঝুঁকিপূর্ণ অর্ডার অটো শিপ করা উচিত নয়। প্রথমে মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি ও সাকসেস রেট দেখুন। কম সাকসেস রেট বা বারবার রিটার্ন হলে ফোন-কনফার্ম বা হোল্ড করুন।',
            'ভালো হিস্টোরি থাকলে দ্রুত কনফার্ম করুন — কনফার্ম হলেই অটো এন্ট্রি। বারবার ফেক প্যাটার্ন আটকাতে চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন। মাসিক রিটার্ন খরচ ও আসল Ads ROASও মাপুন।',
        ],
    },
];

const compareRows = [
    { label: 'প্রতি অর্ডার এন্ট্রি', manual: 'হাতে কপি-পেস্ট', auto: 'কনফার্ম = অটো' },
    { label: 'কুরিয়ার প্যানেল', manual: 'বারবার লগইন', auto: 'এক ড্যাশবোর্ড' },
    { label: 'টাইপো / ভুল ঠিকানা', manual: 'বেশি ঝুঁকি', auto: 'অর্ডার ডাটা থেকে' },
    { label: 'স্ট্যাটাস আপডেট', manual: 'ম্যানুয়াল চেক', auto: 'অটো সিঙ্ক' },
    { label: 'কাস্টমার আপডেট', manual: 'আলাদা SMS টুল', auto: 'SMS এক জায়গায়' },
    { label: 'স্টাফ সময় (দিনে)', manual: 'ঘণ্টা নষ্ট', auto: '৩+ ঘণ্টা বাঁচতে পারে' },
];

const workflowCards = [
    {
        title: '১. ফ্রড চেক',
        body: 'মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX হিস্টোরি ও সাকসেস রেট দেখুন।',
        href: '/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
        tone: 'warn',
    },
    {
        title: '২. কনফার্ম',
        body: 'ঝুঁকি কম হলে কনফার্ম; বেশি হলে কল বা হোল্ড। OTP ও ব্ল্যাকলিস্ট চালু রাখুন।',
        href: '/fake-order-protection',
        linkLabel: 'ফেক অর্ডার প্রোটেকশন',
        tone: 'good',
    },
    {
        title: '৩. অটো এন্ট্রি',
        body: 'কনফার্ম হলেই কুরিয়ারে পার্সেল এন্ট্রি — স্ট্যাটাস সিঙ্ক ও SMS চালু রাখুন।',
        href: '/pricing',
        linkLabel: 'ট্রায়াল শুরু',
        tone: 'auto',
    },
];

const mistakeList = [
    'ফ্রড চেক ছাড়াই সব অর্ডার অটো এন্ট্রি করা।',
    'কুরিয়ার অ্যাকাউন্ট ভুল কানেক্ট রেখে এন্ট্রি চালানো।',
    'স্ট্যাটাস সিঙ্ক বন্ধ রেখে ম্যানুয়াল চেক চালিয়ে যাওয়া।',
    'চার্জ না জেনেই জোন/ওজন ধরে নেওয়া।',
    'শুধু অটো এন্ট্রি চালিয়ে OTP/ব্ল্যাকলিস্ট বন্ধ রাখা।',
];

const whoFor = [
    'দিনে অনেক COD অর্ডার হ্যান্ডেল করা Facebook পেজ সেলার',
    'WooCommerce স্টোর যেখানে স্টাফ কুরিয়ার প্যানেলে আটকে থাকে',
    'ড্রপশিপ / রিসেলার টিম যাদের পার্সেল ভলিউম বাড়ছে',
    'এজেন্সি যারা ক্লায়েন্টের জন্য Pathao/Steadfast/RedX এন্ট্রি করে',
];

const relatedLinks = [
    { href: '/woocommerce-bangladesh', label: 'WooCommerce Bangladesh গাইড' },
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/fake-order-protection', label: 'ফেক অর্ডার প্রোটেকশন' },
    { href: '/courier-charge-calculator', label: 'কুরিয়ার চার্জ ক্যালকুলেটর' },
    { href: '/return-loss-calculator', label: 'রিটার্ন লস ক্যালকুলেটর' },
    { href: '/ads-roas-calculator', label: 'Ads ROAS ক্যালকুলেটর' },
    { href: '/fraudbd-alternative', label: 'FraudBD Alternative' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/courier-auto-entry', label: 'English version' },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="features">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">কুরিয়ার অটো এন্ট্রি</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'কুরিয়ার অটো এন্ট্রি — Pathao, Steadfast, RedX' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'অর্ডার কনফার্ম হলেই কুরিয়ার প্যানেলে অটো এন্ট্রি। ম্যানুয়াল টাইপ বাদ দিয়ে সময় বাঁচান।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    WooCommerce ও Facebook পেজ COD সেলারদের জন্য — স্ট্যাটাস সিঙ্ক, SMS ও পার্সেল নোট হিস্ট্রি এক জায়গায়।
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_courier_auto_entry_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        আগে ফ্রড চেক
                    </Link>
                    <Link
                        href="/courier-charge-calculator"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 sm:w-auto"
                    >
                        চার্জ ক্যালকুলেটর
                    </Link>
                    <Link
                        href="/en/courier-auto-entry"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 sm:w-auto"
                    >
                        English version
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-b border-white/10 bg-amber-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-amber-200 sm:text-2xl">দ্রুত উত্তর</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    কুরিয়ার অটো এন্ট্রি মানে অর্ডার কনফার্ম হলেই Pathao, Steadfast বা RedX প্যানেলে পার্সেল তথ্য স্বয়ংক্রিয় এন্ট্রি।
                    WooCommerce ও Facebook পেজ COD সেলাররা ম্যানুয়াল কপি-পেস্ট বাদ দিয়ে দিনে ৩+ ঘণ্টা সময় বাঁচাতে পারে।
                    আগে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>
                    দিয়ে নম্বর চেক করুন, তারপর কনফার্ম — কনফার্ম হলেই অটো এন্ট্রি। চার্জ তুলনায়
                    <Link href="/courier-charge-calculator" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার চার্জ ক্যালকুলেটর</Link>।
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

        <section class="border-y border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">কীভাবে চালু করবেন</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    চার ধাপে নিরাপদ কুরিয়ার অটোমেশন — আগে চেক, তারপর কনফার্ম, তারপর অটো এন্ট্রি।
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

        <section class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-10">
                <article v-for="section in guideSections" :key="section.heading" class="space-y-3">
                    <h2 class="text-xl font-bold text-white sm:text-2xl">{{ section.heading }}</h2>
                    <p
                        v-for="(paragraph, idx) in section.paragraphs"
                        :key="idx"
                        class="text-sm leading-relaxed text-slate-300 sm:text-base"
                    >
                        <template v-if="section.heading.startsWith('হাতে এন্ট্রি') && idx === 1">
                            হাতে টাইপে ভুল নম্বর বা ভুল এলাকা সাধারণ। অটো এন্ট্রি WooCommerce অর্ডার ডাটা থেকে যায়, তাই টাইপো কম। চূড়ান্ত চার্জ কুরিয়ার প্ল্যান অনুযায়ী হতে পারে — আগে
                            <Link href="/courier-charge-calculator" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার চার্জ ক্যালকুলেটর</Link>
                            দিয়ে আনুমানিক তুলনা করুন।
                        </template>
                        <template v-else-if="section.heading.startsWith('নিরাপদ ওয়ার্কফ্লো') && idx === 0">
                            অটো এন্ট্রি দ্রুত, কিন্তু ঝুঁকিপূর্ণ অর্ডার অটো শিপ করা উচিত নয়। প্রথমে
                            <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>
                            দিয়ে মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি ও সাকসেস রেট দেখুন। কম সাকসেস রেট বা বারবার রিটার্ন হলে ফোন-কনফার্ম বা হোল্ড করুন।
                        </template>
                        <template v-else-if="section.heading.startsWith('নিরাপদ ওয়ার্কফ্লো') && idx === 1">
                            ভালো হিস্টোরি থাকলে দ্রুত কনফার্ম করুন — কনফার্ম হলেই অটো এন্ট্রি। বারবার ফেক প্যাটার্ন আটকাতে
                            <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>
                            চালু রাখুন। মাসিক রিটার্ন খরচ দেখতে
                            <Link href="/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">রিটার্ন লস ক্যালকুলেটর</Link>;
                            অ্যাড বাজেটের আসল ROAS দেখতে
                            <Link href="/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Ads ROAS ক্যালকুলেটর</Link>।
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="false" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">হাতে এন্ট্রি vs অটো</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    এক নজরে পার্থক্য — কেন COD টিম অটো এন্ট্রিতে যায়।
                </p>
                <div class="-mx-4 mt-8 overflow-x-auto px-4 sm:mx-0 sm:overflow-visible sm:px-0">
                    <div class="min-w-[22rem] overflow-hidden rounded-2xl border border-white/10 sm:min-w-0">
                    <div class="grid grid-cols-3 gap-2 border-b border-white/10 bg-white/10 px-3 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-300 sm:px-4 sm:text-sm">
                        <span>বিষয়</span>
                        <span>ম্যানুয়াল</span>
                        <span class="text-amber-300">অটো</span>
                    </div>
                    <div
                        v-for="row in compareRows"
                        :key="row.label"
                        class="grid grid-cols-3 gap-2 border-b border-white/10 bg-white/5 px-3 py-3 text-xs text-slate-300 last:border-b-0 sm:px-4 sm:text-sm"
                    >
                        <span class="font-semibold text-slate-200">{{ row.label }}</span>
                        <span class="text-rose-300/90">{{ row.manual }}</span>
                        <span class="text-emerald-300/90">{{ row.auto }}</span>
                    </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">নিরাপদ অপারেশন ফ্লো</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    দ্রুত এন্ট্রি + ফ্রড চেক একসাথে — শুধু গতি নয়, নিরাপদ COD ওয়ার্কফ্লো।
                </p>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in workflowCards"
                        :key="item.title"
                        class="rounded-2xl border p-5"
                        :class="{
                            'border-amber-500/25 bg-amber-950/20': item.tone === 'warn',
                            'border-emerald-500/25 bg-emerald-950/20': item.tone === 'good',
                            'border-sky-500/25 bg-sky-950/20': item.tone === 'auto',
                        }"
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
                    <h2 class="text-2xl font-bold text-white">কার জন্য এই ফিচার</h2>
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
                    WooEasyLife কুরিয়ার অটো এন্ট্রি অর্ডার কনফার্মে Pathao, Steadfast, RedX-এ স্বয়ংক্রিয় পার্সেল এন্ট্রি করে —
                    WooCommerce COD সেলারদের ম্যানুয়াল টাইপ ও দিনে ৩+ ঘণ্টা সময় বাঁচায়। সাথে স্ট্যাটাস সিঙ্ক, SMS ও Steadfast পার্সেল নোট হিস্ট্রি।
                    নিরাপদ ফ্লো:
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেক</Link>
                    → কনফার্ম → অটো এন্ট্রি; প্রোটেকশন
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>।
                    শুরু করতে
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>
                    থেকে ট্রায়াল নিন। English:
                    <Link href="/en/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>।
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_courier_auto_entry"
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
