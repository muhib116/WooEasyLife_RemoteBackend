<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    whatsappContactUrl: { type: String, default: null },
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
        title: 'কুরিয়ার হিস্টোরি এক নজরে',
        body: 'মোবাইল নম্বর দিলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন রেকর্ড একসাথে দেখা যায়।',
    },
    {
        title: 'সাকসেস রেট দিয়ে সিদ্ধান্ত',
        body: 'সাকসেস রেট কম বা রিটার্ন বেশি হলে অর্ডার কনফার্মের আগেই থামুন — পার্সেল পাঠিয়ে লস নেওয়ার দরকার নেই।',
    },
    {
        title: 'ফ্রি টুল — অ্যাকাউন্ট লাগবে না',
        body: 'প্রতিদিন সীমিত সংখ্যক ফ্রি চেক অ্যাকাউন্ট ছাড়াই করা যায়। ই-কমার্স ও Facebook পেজ সেলারদের জন্য তৈরি।',
    },
    {
        title: 'চেক + প্রোটেকশন একসাথে',
        body: 'শুধু হিস্টোরি নয় — WooEasyLife-এ OTP, ডুপ্লিকেট ব্লক, ব্ল্যাকলিস্ট ও কুরিয়ার অটো এন্ট্রি এক প্ল্যাটফর্মে পাওয়া যায়।',
    },
];

const steps = [
    'কাস্টমারের বাংলাদেশি মোবাইল নম্বর দিন (অর্ডার কনফার্মের আগে)।',
    'Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি দেখুন।',
    'সাকসেস রেট ও রিটার্ন প্যাটার্ন মিলিয়ে ঝুঁকি কম হলে কনফার্ম করুন — বেশি হলে ফোন-যাচাই বা বাতিল রাখুন।',
    'বারবার ফেক অর্ডার আটকাতে OTP, ব্ল্যাকলিস্ট ও প্রোটেকশন চালু করুন।',
];

const guideSections = [
    {
        heading: 'Courier Fraud Checker BD কী এবং কেন লাগে',
        paragraphs: [
            'Free Courier Fraud Checker BD হলো বাংলাদেশি COD ও WooCommerce সেলারদের জন্য ফ্রি টুল — কাস্টমারের মোবাইল নম্বর দিয়ে কুরিয়ার ডেলিভারি হিস্টোরি, সাকসেস রেট ও রিটার্ন রেকর্ড যাচাই করা যায়। অর্ডার কনফার্মের আগেই ফেক বা ঝুঁকিপূর্ণ কাস্টমার চেক করলে পার্সেল রিটার্ন, প্যাকেজিং লস ও অ্যাড বাজেটের অপচয় কমে।',
            'Facebook পেজ ও ই-কমার্সে অনেক অর্ডার ভুল নম্বর, মজার অর্ডার বা আগে রিটার্ন করা কাস্টমার থেকে আসে। হিস্টোরি না দেখে শিপ করলে রিটার্ন চার্জ বিক্রেতার ঘাড়ে পড়ে। এই পেজে অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক করে শুরু করতে পারেন।',
        ],
    },
    {
        heading: 'কীভাবে সাকসেস রেট পড়বেন',
        paragraphs: [
            'উচ্চ সাকসেস রেট সাধারণত নিরাপদ সিগন্যাল — দ্রুত কনফার্ম করে কুরিয়ার এন্ট্রিতে পাঠাতে পারেন। খুব কম সাকসেস রেট, বারবার রিটার্ন বা নতুন/সন্দেহজনক প্যাটার্ন দেখলে ফোন-কনফার্ম করুন বা অর্ডার আটকে রাখুন।',
            'একটি খারাপ রেকর্ড মানেই সবসময় ফেক নয় — কখনো কুরিয়ার এলাকা বা ঠিকানা সমস্যাও থাকে। তাই ফ্রড চেক + ছোট কল ভেরিফিকেশন একসাথে সবচেয়ে নিরাপদ। মাসিক লস হিসাব করতে রিটার্ন লস ক্যালকুলেটর ব্যবহার করুন।',
        ],
    },
    {
        heading: 'ফেক অর্ডার আটকানোর পূর্ণ ওয়ার্কফ্লো',
        paragraphs: [
            'নতুন অর্ডার → এই পেজে মোবাইল নম্বর চেক → প্রয়োজনে ফোন কনফার্ম → কনফার্ম → কুরিয়ার অটো এন্ট্রি। এই ফ্লো মেনে চললে ফেক পার্সেল ও রিটার্ন লস উল্লেখযোগ্যভাবে কমে।',
            'শুধু ম্যানুয়াল চেক দীর্ঘমেয়াদে যথেষ্ট নয়। WooEasyLife সাবস্ক্রিপশনে চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ফোন/ডিভাইস ব্ল্যাকলিস্ট, দৈনিক অর্ডার লিমিট ও পার্সেল নোট হিস্ট্রি একসাথে পাবেন। ফেক অর্ডার প্রোটেকশন পেজে বিস্তারিত আছে।',
            'অ্যাড বাজেটের দিক থেকেও ফেক অর্ডার ক্ষতিকর — Pixel-এ ফেক Purchase গেলে ROAS ফুলে দেখায়। আসল ROAS দেখতে Ads ROAS ক্যালকুলেটর ব্যবহার করুন।',
        ],
    },
    {
        heading: 'কাদের জন্য Courier Fraud Checker BD',
        paragraphs: [
            'Facebook পেজ সেলার, WooCommerce COD স্টোর, ড্রপশিপিং/রিসেলার, এবং এজেন্সি টিম যারা ক্লায়েন্টের জন্য অর্ডার ভেরিফাই করে — সবার আগে এই ফ্রি চেক কাজে লাগে।',
            'ইংরেজি ভার্সন দরকার হলে English version পেজ ব্যবহার করুন। ধাপে ধাপে বাংলা গাইড: কিভাবে ফেক অর্ডার আটকাবো। FraudBD-এর বিকল্প হিসেবে পূর্ণ প্ল্যাটফর্ম চান হলে FraudBD Alternative পড়ুন।',
        ],
    },
];

const decisionList = [
    {
        title: 'কনফার্ম করুন',
        body: 'ভালো সাকসেস রেট, কম রিটার্ন, পরিচিত নম্বর — দ্রুত কনফার্ম করে অটো কুরিয়ার এন্ট্রি চালান।',
        tone: 'good',
    },
    {
        title: 'আগে কল করুন',
        body: 'মাঝারি ঝুঁকি বা অসম্পূর্ণ ঠিকানা — ফোন-কনফার্ম ও ঠিকানা যাচাই করে নিন।',
        tone: 'warn',
    },
    {
        title: 'আটকে রাখুন',
        body: 'খুব কম সাকসেস রেট, বারবার রিটার্ন, সন্দেহজনক প্যাটার্ন — অগ্রিম ছাড়া শিপ করবেন না।',
        tone: 'bad',
    },
];

const mistakeList = [
    'হিস্টোরি না দেখেই সব COD অর্ডার শিপ করা।',
    'শুধু একবার চেক করে মাসের বাকি অর্ডার অচেক রাখা।',
    'খারাপ রেট দেখেও “হয়তো ডেলিভারি হবে” বলে পাঠানো।',
    'ফ্রড চেক আছে বলে OTP/ব্ল্যাকলিস্ট বন্ধ রাখা।',
    'রিপোর্টেড Ads ROAS দেখে বাজেট বাড়ানো, রিটার্ন না মাপা।',
];

const relatedLinks = [
    { href: '/woocommerce-bangladesh', label: 'WooCommerce Bangladesh গাইড' },
    { href: '/en/bd-fraud-checker', label: 'English version' },
    { href: '/fake-customer-check', label: 'Fake Customer Check' },
    { href: '/fake-order-protection', label: 'ফেক অর্ডার প্রোটেকশন' },
    { href: '/return-loss-calculator', label: 'রিটার্ন লস ক্যালকুলেটর' },
    { href: '/ads-roas-calculator', label: 'Ads ROAS ক্যালকুলেটর' },
    { href: '/ki-vabe-fake-order-atkabo', label: 'কিভাবে ফেক অর্ডার আটকাবো' },
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/fraudbd-alternative', label: 'FraudBD Alternative' },
    { href: '/', label: 'হোম' },
    { href: '/pricing', label: 'প্রাইসিং' },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout
        :can-login="canLogin"
        :whatsapp-url="whatsappUrl"
        active-nav="fraud-check"
        suppress-mobile-whatsapp-fab
    >
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mx-auto max-w-3xl text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">Courier Fraud Checker BD</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'Free Courier Fraud Checker BD — ফ্রি ফ্রড চেকার' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি ও সাকসেস রেট চেক করুন।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    Pathao, Steadfast, RedX — অর্ডার কনফার্মের আগেই ফেক কাস্টমার চেক করুন।
                    ই-কমার্স ও Facebook পেজ সেলারদের জন্য ফ্রি টুল।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#fraud-check"
                        class="inline-flex rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        এখনই চেক করুন
                    </a>
                    <Link
                        href="/fake-order-protection"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ফেক অর্ডার প্রোটেকশন
                    </Link>
                    <Link
                        href="/en/bd-fraud-checker"
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
                    Free Courier Fraud Checker BD-এ কাস্টমারের মোবাইল নম্বর দিলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি হিস্টোরি ও সাকসেস রেট দেখা যায়।
                    অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক করা যায়। সাকসেস রেট কম হলে অর্ডার কনফার্মের আগে থামুন — পার্সেল রিটার্ন ও অ্যাড লস কমে।
                    দীর্ঘমেয়াদে WooEasyLife-এর
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>
                    ও কুরিয়ার অটো এন্ট্রি একসাথে চালান।
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

        <section id="fraud-check" class="scroll-mt-24 border-y border-white/10 bg-[#111111] px-4 pb-12 pt-12 sm:pt-14 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="mb-6 text-center">
                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                        ফ্রি টুল — অ্যাকাউন্ট লাগবে না
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                        ফোন নম্বর দিন — কুরিয়ার হিস্টোরি দেখুন
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400">
                        নম্বর দিন — রিটার্নের ঝুঁকি কমান। অর্ডার কনফার্মের আগেই চেক করুন।
                    </p>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

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
                        <template v-if="section.heading.startsWith('কাদের জন্য') && idx === 1">
                            ইংরেজি ভার্সন দরকার হলে
                            <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>
                            ব্যবহার করুন। ধাপে ধাপে বাংলা গাইড:
                            <Link href="/ki-vabe-fake-order-atkabo" class="font-semibold text-amber-400 hover:text-amber-300">কিভাবে ফেক অর্ডার আটকাবো</Link>।
                            FraudBD-এর বিকল্প হিসেবে পূর্ণ প্ল্যাটফর্ম চান হলে
                            <Link href="/fraudbd-alternative" class="font-semibold text-amber-400 hover:text-amber-300">FraudBD Alternative</Link>
                            পড়ুন।
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="false" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">চেকের পর কী করবেন</h2>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in decisionList"
                        :key="item.title"
                        class="rounded-2xl border p-5"
                        :class="{
                            'border-emerald-500/25 bg-emerald-950/20': item.tone === 'good',
                            'border-amber-500/25 bg-amber-950/20': item.tone === 'warn',
                            'border-rose-500/25 bg-rose-950/20': item.tone === 'bad',
                        }"
                    >
                        <h3 class="text-base font-bold text-white">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ item.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
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
                    <h2 class="text-2xl font-bold text-white">শুধু টুল নয় — পূর্ণ সুরক্ষা</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">
                        অনেক ফ্রড চেকার শুধু হিস্টোরি দেখায়। WooEasyLife-এ ফ্রড চেক ছাড়াও চেকআউট OTP, ডুপ্লিকেট ব্লক,
                        ব্ল্যাকলিস্ট, কুরিয়ার অটো এন্ট্রি ও হারানো অর্ডার রিকভারি একসাথে পাবেন।
                    </p>
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
                    <div class="mt-8 flex flex-wrap gap-3">
                        <MetaCtaLink
                            :href="ctaUrl"
                            :label="ctaLabel"
                            location="seo_bd_fraud_checker"
                            link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                        />
                        <Link
                            :href="route('pricing')"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            প্রাইসিং দেখুন
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">এআই সারাংশ</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    WooEasyLife Free Courier Fraud Checker BD মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX কুরিয়ার হিস্টোরি ও সাকসেস রেট চেক করে —
                    ফেক অর্ডার ও পার্সেল রিটার্ন কমাতে। অ্যাকাউন্ট ছাড়াই ফ্রি চেক সম্ভব। পূর্ণ সুরক্ষায় OTP, ব্ল্যাকলিস্ট ও কুরিয়ার অটো এন্ট্রি যোগ করুন।
                    শুরু করতে এই পেজে নম্বর সার্চ করুন, বা
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>
                    থেকে ট্রায়াল নিন। English:
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>।
                </p>
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
