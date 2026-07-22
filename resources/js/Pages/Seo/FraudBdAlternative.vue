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
        title: 'ফ্রড চেক বিল্ট-ইন',
        body: 'Pathao, Steadfast, RedX সহ কুরিয়ার হিস্টোরি ও সাকসেস রেট — অ্যাকাউন্ট ছাড়াই ফ্রি চেক।',
    },
    {
        title: 'ফেক অর্ডার প্রোটেকশন',
        body: 'চেকআউট OTP, ডুপ্লিকেট ব্লক, ব্ল্যাকলিস্ট ও অর্ডার লিমিট — শুধু হিস্টোরি দেখা নয়, ব্লকও হয়।',
    },
    {
        title: 'কুরিয়ার অটো এন্ট্রি',
        body: 'কনফার্ম হলে Pathao/Steadfast/RedX-এ অটো পার্সেল এন্ট্রি — হাতে প্যানেল টাইপ কমে।',
    },
    {
        title: 'অ্যাপ + রিকভারি',
        body: 'মোবাইল অ্যাপ, মাল্টিস্টোর, হারানো অর্ডার রিকভারি ও পার্সেল নোট হিস্ট্রি এক প্ল্যাটফর্মে।',
    },
];

const compareRows = [
    { feature: 'কুরিয়ার হিস্টোরি / ফ্রড চেক', toolOnly: 'হ্যাঁ', woo: 'হ্যাঁ (বিল্ট-ইন)' },
    { feature: 'চেকআউট OTP / ফেক অর্ডার ব্লক', toolOnly: 'সাধারণত না', woo: 'হ্যাঁ' },
    { feature: 'কুরিয়ার অটো এন্ট্রি', toolOnly: 'না', woo: 'Pathao / Steadfast / RedX' },
    { feature: 'পার্সেল নোট হিস্ট্রি', toolOnly: 'না', woo: 'হ্যাঁ (Steadfast সহ)' },
    { feature: 'হারানো অর্ডার রিকভারি', toolOnly: 'না', woo: 'হ্যাঁ' },
    { feature: 'রিটার্ন লস / ROAS টুল', toolOnly: 'সীমিত', woo: 'ক্যালকুলেটরসহ' },
    { feature: 'মোবাইল অ্যাপ + মাল্টিস্টোর', toolOnly: 'সীমিত', woo: 'হ্যাঁ' },
];

const guideSections = [
    {
        heading: 'FraudBD Alternative কেন খোঁজেন',
        paragraphs: [
            'FraudBD বা অন্যান্য শুধু-ফ্রড-চেকার টুল মূলত মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি দেখায়। বাংলাদেশি COD ও WooCommerce সেলারদের জন্য এটা দরকারি — কিন্তু শুধু চেক দেখে সিদ্ধান্ত নেওয়াই যথেষ্ট নয়। একই নম্বর বারবার অর্ডার দিলে, চেকআউটে OTP না থাকলে, বা কনফার্মের পর হাতে কুরিয়ার প্যানেলে এন্ট্রি করতে হলে লস আবার বাড়ে।',
            'WooEasyLife FraudBD Alternative হিসেবে একই BD fraud checker দেয়, সাথে ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি, হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ। টুল বদলে পূর্ণ অপারেশন প্ল্যাটফর্ম — চেক → ব্লক → কনফার্ম → অটো এন্ট্রি এক ফ্লোতে।',
        ],
    },
    {
        heading: 'শুধু-চেকার vs পূর্ণ প্ল্যাটফর্ম',
        paragraphs: [
            'শুধু-চেকার: নম্বর দিলে হিস্টোরি দেখায়। স্টাফ ম্যানুয়ালি সিদ্ধান্ত নেয়; চেকআউট বা কুরিয়ার প্যানেলে আলাদা টুল লাগে।',
            'WooEasyLife: ফ্রি চেক + সাবস্ক্রিপশনে OTP/ব্ল্যাকলিস্ট, কনফার্মে অটো কুরিয়ার এন্ট্রি, স্ট্যাটাস সিঙ্ক ও পার্সেল নোট। রিটার্ন লস ও Ads ROAS ক্যালকুলেটর দিয়ে খরচও মাপা যায়।',
            'যখন দিনে কয়েক ডজন COD অর্ডার আসে, শুধু ট্যাবে চেক করে কপি-পেস্ট করা ধীর ও ভুলপ্রবণ। প্ল্যাটফর্ম ওয়ার্কফ্লো সেই ঘর্ষণ কমায়।',
        ],
    },
    {
        heading: 'কীভাবে সুইচ করবেন',
        paragraphs: [
            'প্রথমে /bd-fraud-checker দিয়ে ফ্রি চেক টেস্ট করুন — অ্যাকাউন্ট লাগে না। তারপর /fake-order-protection ও /courier-auto-entry পড়ে ওয়ার্কফ্লো বুঝুন।',
            '/pricing থেকে ট্রায়াল বা সাবস্ক্রিপশন নিন। WooCommerce কানেক্ট করুন, কুরিয়ার অ্যাকাউন্ট লিংক করুন, প্রোটেকশন রুল চালু করুন। পুরনো চেকার টুল রাখতে পারেন — কিন্তু বেশিরভাগ দৈনন্দিন কাজ WooEasyLife-এ সরে যায়।',
        ],
    },
    {
        heading: 'কারা এই পেজ পড়বেন',
        paragraphs: [
            'যারা “FraudBD alternative”, “fraud checker BD alternative” বা “কুরিয়ার ফ্রড চেক + WooCommerce” খুঁজছেন। Facebook পেজ ও COD স্টোর যারা শুধু হিস্টোরি নয়, পুরো সুরক্ষা চান।',
            'ইংরেজি ভার্সন: /en/fraudbd-alternative। ধাপে ধাপে বাংলা গাইড: /ki-vabe-fake-order-atkabo।',
        ],
    },
];

const workflow = [
    { title: 'চেক', body: 'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে কুরিয়ার সাকসেস রেট দেখুন।', href: '/bd-fraud-checker', linkLabel: 'BD Fraud Checker' },
    { title: 'ব্লক', body: 'OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে একই ফেক প্যাটার্ন আটকান।', href: '/fake-order-protection', linkLabel: 'ফেক অর্ডার প্রোটেকশন' },
    { title: 'অটো এন্ট্রি', body: 'কনফার্ম হলে Pathao/Steadfast/RedX-এ পার্সেল তথ্য অটো যায়।', href: '/courier-auto-entry', linkLabel: 'কুরিয়ার অটো এন্ট্রি' },
];

const tips = [
    {
        title: 'চেক ছাড়া শিপ করবেন না',
        body: 'কম সাকসেস রেট বা বেশি রিটার্ন দেখলে আগে কল ভেরিফাই করুন — সস্তা চেকার টুল থাকলেও নিয়ম একই।',
    },
    {
        title: 'রিটার্ন লস মাপুন',
        body: 'মাসিক রিটার্ন চার্জ না জানলে “সস্তা টুল” আসলে লস লুকাতে পারে। রিটার্ন লস ক্যালকুলেটর ব্যবহার করুন।',
    },
    {
        title: 'পিক্সেল পরিষ্কার রাখুন',
        body: 'ফেক Purchase ROAS ফোলায়। Ads ROAS ক্যালকুলেটর ও পিক্সেল প্রোটেকশন একসাথে দেখুন।',
    },
    {
        title: 'এক ফ্লোতে রাখুন',
        body: 'চেক → কনফার্ম → অটো এন্ট্রি এক সিস্টেমে থাকলে স্টাফ কম ট্যাব খোলে, ভুলও কমে।',
    },
];

const mistakeList = [
    'শুধু ফ্রড চেক টুল রেখে চেকআউট প্রোটেকশন না চালানো।',
    'চেক দেখেও সব অর্ডার কনফার্ম করা।',
    'কুরিয়ার প্যানেলে হাতে এন্ট্রি চালিয়ে যাওয়া।',
    'রিটার্ন লস না মেপে “টুল সস্তা” ধরে নেওয়া।',
    'ফেক অর্ডার বাড়তে দিয়ে অ্যাড বাজেট বাড়ানো।',
];

const relatedLinks = [
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/fake-order-protection', label: 'ফেক অর্ডার প্রোটেকশন' },
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/return-loss-calculator', label: 'রিটার্ন লস ক্যালকুলেটর' },
    { href: '/ads-roas-calculator', label: 'Ads ROAS ক্যালকুলেটর' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/fraudbd-alternative', label: 'English' },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="fraud-check">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">FraudBD Alternative</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'FraudBD Alternative — টুলের বদলে পূর্ণ প্ল্যাটফর্ম' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'শুধু ফ্রড হিস্টোরি নয় — চেক, ব্লক, কুরিয়ার অটো এন্ট্রি ও রিকভারি একসাথে।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    FraudBD বা অন্যান্য শুধু-চেকার টুলের বিকল্প খুঁজছেন? WooEasyLife-এ ফ্রি BD fraud checker
                    ছাড়াও WooCommerce প্রোটেকশন ও কুরিয়ার অটোমেশন পাবেন।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        ফ্রি ফ্রড চেক
                    </Link>
                    <Link
                        href="/pricing"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        প্রাইসিং দেখুন
                    </Link>
                    <Link
                        href="/en/fraudbd-alternative"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
                    >
                        English
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-b border-white/10 bg-sky-950/20 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-sky-200 sm:text-2xl">দ্রুত উত্তর</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    FraudBD Alternative হিসেবে WooEasyLife শুধু কুরিয়ার হিস্টোরি চেক নয় —
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রি ফ্রড চেক</Link>,
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>,
                    <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>
                    ও মোবাইল অ্যাপ এক প্ল্যাটফর্মে দেয়। শুধু-চেকার টুল হিস্টোরি দেখায়; WooEasyLife COD অপারেশন সম্পূর্ণ করে।
                    শুরু:
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>।
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

        <section id="compare" class="scroll-mt-24 border-y border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl overflow-x-auto">
                <h2 class="text-center text-2xl font-bold text-white">দ্রুত তুলনা</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    শুধু-ফ্রড-চেকার টুল vs WooEasyLife — কোনটা আপনার COD ফ্লোর সাথে মিলে।
                </p>
                <table class="mt-8 w-full min-w-[32rem] border-collapse text-left text-sm text-slate-300">
                    <thead>
                        <tr class="border-b border-white/15 text-slate-400">
                            <th class="py-3 pr-4 font-medium">ফিচার</th>
                            <th class="py-3 pr-4 font-medium">টুল-শুধু চেকার</th>
                            <th class="py-3 font-medium text-amber-300">WooEasyLife</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in compareRows"
                            :key="row.feature"
                            class="border-b border-white/10 last:border-b-0"
                        >
                            <td class="py-3 pr-4">{{ row.feature }}</td>
                            <td class="py-3 pr-4">{{ row.toolOnly }}</td>
                            <td class="py-3 text-emerald-400">{{ row.woo }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">চেক → ব্লক → অটো এন্ট্রি</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    FraudBD-স্টাইল চেক রাখুন, তারপর প্রোটেকশন ও কুরিয়ার অটোমেশন যোগ করুন।
                </p>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in workflow"
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
            </div>
        </section>

        <section class="border-t border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">ব্যবহারিক টিপস</h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="item in tips"
                        :key="item.title"
                        class="rounded-2xl border border-white/10 bg-white/5 p-5"
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
                    <h2 class="text-2xl font-bold text-white">সম্পর্কিত পেজ</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">
                        তুলনা পড়ার পর ফ্রি চেক টেস্ট করুন, প্রোটেকশন চালু করুন, তারপর ট্রায়াল নিন।
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
                        <Link
                            href="/bd-fraud-checker"
                            class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                        >
                            ফ্রি ফ্রড চেক
                        </Link>
                        <MetaCtaLink
                            :href="ctaUrl"
                            :label="ctaLabel"
                            location="seo_fraudbd_alternative"
                            link-class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        />
                        <Link
                            href="/pricing"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            প্রাইসিং
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">এআই সারাংশ</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    WooEasyLife হলো FraudBD Alternative — ফ্রি BD fraud checker-এর সাথে ফেক অর্ডার প্রোটেকশন,
                    কুরিয়ার অটো এন্ট্রি, হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ। শুধু-চেকার টুল হিস্টোরি দেখায়;
                    WooEasyLife পুরো COD ওয়ার্কফ্লো কভার করে। ফ্রি চেক:
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেকার</Link>,
                    ট্রায়াল:
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>।
                    English:
                    <Link href="/en/fraudbd-alternative" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>।
                </p>
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
