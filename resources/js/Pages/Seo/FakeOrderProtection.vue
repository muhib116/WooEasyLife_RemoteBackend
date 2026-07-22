<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';

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
        title: 'কুরিয়ার হিস্টোরি চেক',
        body: 'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX সাকসেস রেট ও রিটার্ন হিস্টোরি দেখুন।',
    },
    {
        title: 'চেকআউট OTP',
        body: 'ফোন নম্বরে OTP যাচাই — ভুয়া/ভুল নম্বরে অর্ডার আগেই আটকে। ফর্ম ভ্যালিডেশনও একসাথে চালু রাখুন।',
    },
    {
        title: 'ডুপ্লিকেট ও ব্ল্যাকলিস্ট',
        body: 'একই অর্ডার বারবার আসা বন্ধ করুন। সমস্যাযুক্ত ফোন, ইমেইল, IP বা ডিভাইস ব্লক রাখুন।',
    },
    {
        title: 'রিটার্ন লস ও Ads ROAS',
        body: 'ফেক পার্সেল কমলে রিটার্ন চার্জ বাঁচে; ফেক Pixel Purchase কমলে অ্যাড অপটিমাইজেশন পরিষ্কার থাকে।',
    },
];

const steps = [
    'নতুন অর্ডারে BD Fraud Checker দিয়ে মোবাইল নম্বর চেক করুন।',
    'ঝুঁকি বেশি হলে ফোন-কনফার্ম বা হোল্ড করুন; কম হলে এগোন।',
    'চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন যাতে একই প্যাটার্ন না ফিরে আসে।',
    'নিরাপদ অর্ডার কনফার্ম করে কুরিয়ার অটো এন্ট্রিতে পাঠান — প্যাকিং ও ফলো-আপে ফোকাস করুন।',
];

const guideSections = [
    {
        heading: 'মাল্টি-লেয়ার ফেক অর্ডার প্রোটেকশন কী',
        paragraphs: [
            'কিভাবে ফেক অর্ডার আটকাবো — এই প্রশ্নের উত্তর একটা টুলে থেমে যায় না। প্রথমে মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX হিস্টোরি চেক করুন। তারপর চেকআউটে OTP, ফর্ম ভ্যালিডেশন, ডুপ্লিকেট অর্ডার ব্লক ও দৈনিক অর্ডার লিমিট চালু করুন।',
            'COD ও Facebook পেজ ব্যবসায় ভুয়া নম্বর বা মজার অর্ডার সাধারণ। পার্সেল চলে গেলে রিটার্ন চার্জ, প্যাকেজিং ও সময় বিক্রেতার। WooEasyLife-এ ফ্রড চেক, চেকআউট সুরক্ষা ও কুরিয়ার অটো এন্ট্রি এক প্ল্যাটফর্মে।',
        ],
    },
    {
        heading: 'লেয়ার বাই লেয়ার: চেক → OTP → ব্লক',
        paragraphs: [
            'লেয়ার ১ — ফ্রড/কুরিয়ার হিস্টোরি: অর্ডার কনফার্মের আগে নম্বর চেক করুন। কম সাকসেস রেট বা বারবার রিটার্ন হলে কল বা হোল্ড।',
            'লেয়ার ২ — চেকআউট OTP ও ফর্ম ভ্যালিডেশন: ভুয়া/ভুল নম্বরে অর্ডার আগেই আটকে। অসম্পূর্ণ ঠিকানাও কমে।',
            'লেয়ার ৩ — ডুপ্লিকেট ব্লক, ব্ল্যাকলিস্ট ও দৈনিক লিমিট: একই প্যাটার্ন বারবার আসা বন্ধ হয়।',
        ],
    },
    {
        heading: 'প্র্যাকটিক্যাল ওয়ার্কফ্লো',
        paragraphs: [
            'নতুন অর্ডার → ফ্রড চেক → প্রয়োজনে ফোন কনফার্ম → কনফার্ম → কুরিয়ার অটো এন্ট্রি। এই ফ্লো মেনে চললে ফেক পার্সেল ও রিটার্ন লস উল্লেখযোগ্যভাবে কমে।',
            'শুধু ম্যানুয়াল চেক দীর্ঘমেয়াদে যথেষ্ট নয় — OTP ও ব্ল্যাকলিস্ট চালু রাখুন। ধাপে ধাপে বাংলা গাইডও আছে।',
        ],
    },
    {
        heading: 'ফেক অর্ডার, রিটার্ন লস ও Ads ROAS',
        paragraphs: [
            'ফেক অর্ডার শুধু কুরিয়ার লস নয় — Facebook Pixel-এ ফেক Purchase গেলে অ্যাড অপটিমাইজেশনও নষ্ট হয়।',
            'মাসিক রিটার্ন খরচ ও আসল ROAS মাপুন। FraudBD-স্টাইল শুধু-চেকারের বদলে পূর্ণ প্রোটেকশন চাইলে তুলনা পেজ পড়ুন।',
        ],
    },
];

const layers = [
    {
        title: '১. চেক',
        body: 'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি ও সাকসেস রেট দেখুন।',
        href: '/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
        tone: 'warn',
    },
    {
        title: '২. OTP ও ব্লক',
        body: 'চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন।',
        href: '/pricing',
        linkLabel: 'ট্রায়াল শুরু',
        tone: 'good',
    },
    {
        title: '৩. অটো এন্ট্রি',
        body: 'নিরাপদ অর্ডার কনফার্ম হলেই কুরিয়ারে পাঠান — সময় ও ভুল কমে।',
        href: '/courier-auto-entry',
        linkLabel: 'কুরিয়ার অটো এন্ট্রি',
        tone: 'auto',
    },
];

const mistakeList = [
    'হিস্টোরি না দেখেই সব COD অর্ডার শিপ করা।',
    'শুধু একবার চেক করে OTP/ব্ল্যাকলিস্ট বন্ধ রাখা।',
    'খারাপ সাকসেস রেট দেখেও “হয়তো ডেলিভারি হবে” বলে পাঠানো।',
    'রিপোর্টেড Ads ROAS দেখে বাজেট বাড়ানো, রিটার্ন না মাপা।',
    'প্রোটেকশন চালু করেও কুরিয়ার স্ট্যাটাস/অটো এন্ট্রি উপেক্ষা করা।',
];

const whoFor = [
    'Facebook পেজ সেলার যাদের COD রিটার্ন বেশি',
    'WooCommerce স্টোর যেখানে ভুয়া নম্বরের অর্ডার আসে',
    'এজেন্সি/টিম যারা ক্লায়েন্টের অর্ডার ভেরিফাই করে',
    'যারা অ্যাড বাজেট স্কেলের আগে ফেক Purchase কমাতে চান',
];

const relatedLinks = [
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/ki-vabe-fake-order-atkabo', label: 'কিভাবে ফেক অর্ডার আটকাবো' },
    { href: '/return-loss-calculator', label: 'রিটার্ন লস ক্যালকুলেটর' },
    { href: '/ads-roas-calculator', label: 'Ads ROAS ক্যালকুলেটর' },
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/fraudbd-alternative', label: 'FraudBD Alternative' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/fake-order-protection', label: 'English version' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">ফেক অর্ডার প্রোটেকশন</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'কিভাবে ফেক অর্ডার আটকাবো — Fake Order Protection' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'ফ্রড চেক + OTP + ব্লক — মাল্টি-লেয়ার প্রোটেকশনে ফেক অর্ডার ও রিটার্ন লস কমান।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    COD ও Facebook পেজ ব্যবসায় ফেক অর্ডার মানে রিটার্ন চার্জ ও নষ্ট অ্যাড বাজেট — অর্ডার পাঠানোর আগেই ঝুঁকি আটকান।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_fake_order_protection_hero"
                        link-class="inline-flex rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                    />
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ফ্রি ফ্রড চেক
                    </Link>
                    <Link
                        href="/en/fake-order-protection"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
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
                    কিভাবে ফেক অর্ডার আটকাবো: প্রথমে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>
                    দিয়ে মোবাইল নম্বরের কুরিয়ার হিস্টোরি ও সাকসেস রেট দেখুন। তারপর চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ফোন/ডিভাইস ব্ল্যাকলিস্ট ও দৈনিক অর্ডার লিমিট চালু করুন।
                    মাসিক লস হিসাব করতে
                    <Link href="/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">রিটার্ন লস ক্যালকুলেটর</Link>
                    ব্যবহার করুন।
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
                <h2 class="text-center text-2xl font-bold text-white">কীভাবে ব্যবহার করবেন</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    চার ধাপে নিরাপদ COD ওয়ার্কফ্লো — চেক, প্রোটেকশন, কনফার্ম, তারপর অটো এন্ট্রি।
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
                        <template v-if="section.heading.startsWith('লেয়ার') && idx === 0">
                            লেয়ার ১ — ফ্রড/কুরিয়ার হিস্টোরি: অর্ডার কনফার্মের আগে নম্বর চেক করুন। কম সাকসেস রেট বা বারবার রিটার্ন হলে কল বা হোল্ড। ফ্রি টুল:
                            <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>।
                        </template>
                        <template v-else-if="section.heading.startsWith('প্র্যাকটিক্যাল') && idx === 0">
                            নতুন অর্ডার →
                            <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেক</Link>
                            → প্রয়োজনে ফোন কনফার্ম → কনফার্ম →
                            <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>।
                            এই ফ্লো মেনে চললে ফেক পার্সেল ও রিটার্ন লস উল্লেখযোগ্যভাবে কমে।
                        </template>
                        <template v-else-if="section.heading.startsWith('প্র্যাকটিক্যাল') && idx === 1">
                            শুধু ম্যানুয়াল চেক দীর্ঘমেয়াদে যথেষ্ট নয় — OTP ও ব্ল্যাকলিস্ট চালু রাখুন। ধাপে ধাপে গাইড:
                            <Link href="/ki-vabe-fake-order-atkabo" class="font-semibold text-amber-400 hover:text-amber-300">কিভাবে ফেক অর্ডার আটকাবো</Link>।
                        </template>
                        <template v-else-if="section.heading.startsWith('ফেক অর্ডার, রিটার্ন') && idx === 0">
                            ফেক অর্ডার শুধু কুরিয়ার লস নয় — Facebook Pixel-এ ফেক Purchase গেলে অ্যাড অপটিমাইজেশনও নষ্ট হয়। আসল ROAS দেখতে
                            <Link href="/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Ads ROAS ক্যালকুলেটর</Link>
                            ব্যবহার করুন।
                        </template>
                        <template v-else-if="section.heading.startsWith('ফেক অর্ডার, রিটার্ন') && idx === 1">
                            মাসিক রিটার্ন খরচ দেখতে
                            <Link href="/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">রিটার্ন লস ক্যালকুলেটর</Link>।
                            FraudBD-স্টাইল শুধু-চেকারের বদলে পূর্ণ প্রোটেকশন চাইলে
                            <Link href="/fraudbd-alternative" class="font-semibold text-amber-400 hover:text-amber-300">FraudBD Alternative</Link>
                            পড়ুন।
                        </template>
                        <template v-else>{{ paragraph }}</template>
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">৩ লেয়ার সুরক্ষা ফ্লো</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    চেক + OTP/ব্লক + অটো এন্ট্রি — শুধু টুল নয়, পূর্ণ COD সুরক্ষা।
                </p>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in layers"
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
                    <h2 class="text-2xl font-bold text-white">কার জন্য এই পেজ</h2>
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
                    WooEasyLife ফেক অর্ডার প্রোটেকশন মাল্টি-লেয়ার: কুরিয়ার হিস্টোরি চেক, চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে COD ফেক অর্ডার ও রিটার্ন লস কমায়।
                    ফ্রি চেক
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>;
                    পূর্ণ সুরক্ষা
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>।
                    গাইড:
                    <Link href="/ki-vabe-fake-order-atkabo" class="font-semibold text-amber-400 hover:text-amber-300">কিভাবে ফেক অর্ডার আটকাবো</Link>।
                    English:
                    <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">/en/fake-order-protection</Link>।
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_fake_order_protection"
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
                            {{ item.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
