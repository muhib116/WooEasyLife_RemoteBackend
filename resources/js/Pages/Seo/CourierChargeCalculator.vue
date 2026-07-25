<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import CourierChargeCalculatorSection from '@/components/marketing/CourierChargeCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    courierChargeCalculator: { type: Object, default: () => ({}) },
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
    'ডেলিভারি জোন বেছে নিন (ঢাকার ভিতর / সাবআরবান / বাইরে)।',
    'পার্সেল ওজন স্লাইডারে সেট করুন।',
    'COD অ্যামাউন্ট দিলে আনুমানিক COD ফি যোগ হবে।',
    'Pathao, Steadfast ও RedX-এর আনুমানিক চার্জ তুলনা করুন — তারপর অটো এন্ট্রি ট্রায়াল শুরু করুন।',
];

const pillars = [
    {
        title: 'তিন কুরিয়ার একসাথে',
        body: 'Pathao, Steadfast ও RedX-এর আনুমানিক ডেলিভারি চার্জ এক পেজে তুলনা করুন — জোন ও ওজন অনুযায়ী।',
    },
    {
        title: 'জোন + ওজন + COD',
        body: 'ঢাকা / সাবআরবান / বাইরে জোন, পার্সেল কেজি এবং COD অ্যামাউন্ট দিলে আনুমানিক ফিসহ হিসাব হয়।',
    },
    {
        title: 'আনুমানিক vs অফিসিয়াল',
        body: 'Steadfast রেট পাবলিক প্রাইসিং থেকে সিঙ্ক হতে পারে; Pathao/RedX অনেক ক্ষেত্রে আনুমানিক। চূড়ান্ত চার্জ প্যানেলে যাচাই করুন।',
    },
    {
        title: 'অটো এন্ট্রি দিয়ে সময় বাঁচান',
        body: 'চার্জ জানাই যথেষ্ট নয় — কনফার্ম হলে WooEasyLife কুরিয়ারে অটো এন্ট্রি করে, হাতে চার্জ হিসাব কমে।',
    },
];

const guideSections = [
    {
        heading: 'কুরিয়ার চার্জ ক্যালকুলেটর কেন লাগে',
        paragraphs: [
            'বাংলাদেশি COD ও WooCommerce সেলারদের জন্য ফ্রি কুরিয়ার চার্জ ক্যালকুলেটর। ঢাকা, সাবআরবান বা বাইরের জোন ও পার্সেল ওজন দিয়ে Pathao, Steadfast ও RedX-এর আনুমানিক ডেলিভারি চার্জ একসাথে তুলনা করুন। COD ফিসহ হিসাব করে কোন কুরিয়ারে খরচ কম বোঝা যায়।',
            'প্রতি অর্ডারে ৳২০–৫০ পার্থক্যও মাসে হাজার হাজার টাকা হতে পারে। সস্তা কুরিয়ার বেছে নেওয়ার আগে ডেলিভারি কোয়ালিটি ও রিটার্ন রেটও দেখুন — সস্তা চার্জ + বেশি রিটার্ন = বড় লস।',
        ],
    },
    {
        heading: 'রেট কীভাবে বুঝবেন (আনুমানিক)',
        paragraphs: [
            'Steadfast রেট তাদের পাবলিক প্রাইসিং থেকে প্রতিদিন সিঙ্ক হওয়ার চেষ্টা করা হয়। Pathao-এর পাবলিক ক্যালকুলেটর লগইন ছাড়া সবসময় পাওয়া যায় না — মার্চেন্ট API থাকলে আপডেট হয়, নইলে আনুমানিক। RedXও অনেক ক্ষেত্রে আনুমানিক। চূড়ান্ত বিল সবসময় কুরিয়ার প্যানেল/কন্ট্রাক্টে যাচাই করুন।',
            'ওজন বাড়লে অতিরিক্ত কেজির চার্জ যোগ হয়। COD অ্যামাউন্ট দিলে উদাহরণ হিসেবে প্রায় ১% COD ফি যোগ হতে পারে — আপনার প্ল্যান ভিন্ন হলে স্লাইডার মিলিয়ে নিন।',
        ],
    },
    {
        heading: 'সস্তা চার্জ ≠ বেশি লাভ',
        paragraphs: [
            'শুধু সবচেয়ে কম ডেলিভারি চার্জ দেখে সিদ্ধান্ত নেবেন না। ফেক অর্ডার বা রিটার্ন বেশি হলে সস্তা চার্জও লসে যায়। অর্ডার কনফার্মের আগে BD Fraud Checker দিয়ে সাকসেস রেট দেখুন এবং ফেক অর্ডার প্রোটেকশন চালু রাখুন।',
            'রিটার্ন লস ক্যালকুলেটরে মাসিক রিটার্ন খরচ হিসাব করুন। অ্যাড বাজেটের দিক থেকে Ads ROAS ক্যালকুলেটর দিয়ে আসল ROAS দেখুন। পূর্ণ অপারেশনে কুরিয়ার অটো এন্ট্রি সময় বাঁচায়।',
        ],
    },
    {
        heading: 'WooEasyLife ওয়ার্কফ্লো',
        paragraphs: [
            'চার্জ তুলনা → অর্ডার কনফার্মের আগে ফ্রড চেক → কনফার্ম → Pathao/Steadfast/RedX অটো এন্ট্রি। এতে প্যানেলে বারবার ঠিকানা ও চার্জ টাইপ করতে হয় না।',
            'প্ল্যান ও ট্রায়াল জানতে প্রাইসিং দেখুন। পার্সেল নোট হিস্ট্রি ও স্ট্যাটাস সিঙ্ক এক জায়গায় রাখলে স্টাফ কুরিয়ার সাইটে কম ঘোরে।',
        ],
    },
];

const tips = [
    {
        title: 'জোন ঠিকমতো বাছুন',
        body: 'ভুল জোন দিলে চার্জ ভুল দেখাবে। ঢাকা / সাবআরবান / বাইরে আলাদা করে নিন।',
    },
    {
        title: 'ওজন বাস্তব রাখুন',
        body: 'প্যাকিংসহ ওজন দিন। কম দেখালে পরে অতিরিক্ত বিল বা ডিলে হতে পারে।',
    },
    {
        title: 'COD ফি ভুলবেন না',
        body: 'শুধু ডেলিভারি বেস চার্জ নয় — COD অ্যামাউন্টের ফিও মোট খরচে যোগ করুন।',
    },
    {
        title: 'রিটার্ন খরচ আলাদা মাপুন',
        body: 'রিটার্ন হলে আবার চার্জ লাগে। রিটার্ন লস ক্যালকুলেটর দিয়ে মাসিক লস দেখুন।',
    },
];

const mistakeList = [
    'শুধু সবচেয়ে সস্তা কুরিয়ার বেছে নেওয়া, কোয়ালিটি না দেখা।',
    'ওজন কম দেখিয়ে হিসাব করা।',
    'COD ফি বাদ দিয়ে মোট খরচ তুলনা করা।',
    'ফেক অর্ডার বেশি থাকা সত্ত্বেও শুধু চার্জ অপটিমাইজ করা।',
    'প্যানেলে চূড়ান্ত রেট না দেখেই সিদ্ধান্ত ফাইনাল করা।',
];

const relatedLinks = [
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/return-loss-calculator', label: 'রিটার্ন লস ক্যালকুলেটর' },
    { href: '/ads-roas-calculator', label: 'Ads ROAS ক্যালকুলেটর' },
    { href: '/fake-order-protection', label: 'ফেক অর্ডার প্রোটেকশন' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/courier-charge-calculator', label: 'English' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-sky-300/90">কুরিয়ার চার্জ ক্যালকুলেটর</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'Pathao · Steadfast · RedX ডেলিভারি চার্জ হিসাব' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'জোন ও ওজন দিয়ে তিন কুরিয়ারের আনুমানিক চার্জ তুলনা করুন।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    বাংলাদেশি COD মার্চেন্টদের জন্য ফ্রি টুল। COD ফিসহ আনুমানিক হিসাব —
                    চূড়ান্ত চার্জ কুরিয়ার প্যানেলে যাচাই করুন।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#calculator"
                        class="inline-flex rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-400"
                    >
                        ক্যালকুলেটর খুলুন
                    </a>
                    <Link
                        href="/courier-auto-entry"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        কুরিয়ার অটো এন্ট্রি
                    </Link>
                    <Link
                        href="/en/courier-charge-calculator"
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
                    কুরিয়ার চার্জ ক্যালকুলেটরে জোন ও পার্সেল ওজন দিয়ে Pathao, Steadfast ও RedX-এর আনুমানিক ডেলিভারি চার্জ তুলনা করুন।
                    COD অ্যামাউন্ট দিলে আনুমানিক COD ফি যোগ হয়। রেট আনুমানিক হতে পারে — চূড়ান্ত বিল প্যানেলে চেক করুন।
                    কনফার্মড অর্ডারে
                    <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>
                    চালু রাখলে হাতে চার্জ হিসাব কমে। ফেক অর্ডার আটকাতে আগে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেক</Link>
                    করুন।
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

        <CourierChargeCalculatorSection
            :config="courierChargeCalculator"
            :primary-cta-url="ctaUrl"
            :primary-cta-label="ctaLabel"
            :show-intro="false"
        />

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">কীভাবে ব্যবহার করবেন</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    চার ধাপে তিন কুরিয়ারের আনুমানিক চার্জ তুলনা করুন। সংখ্যা শিক্ষামূলক — প্যানেলে চূড়ান্ত রেট যাচাই করুন।
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
                    <h2 class="text-2xl font-bold text-white">সম্পর্কিত টুল</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">
                        চার্জ তুলনার পর ফ্রড চেক, রিটার্ন লস ও অটো এন্ট্রি একসাথে দেখুন — পুরো COD খরচ বোঝা যায়।
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
                            location="seo_courier_charge_calculator"
                            link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                        />
                        <Link
                            href="/courier-auto-entry"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            কুরিয়ার অটো এন্ট্রি
                        </Link>
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
                    WooEasyLife কুরিয়ার চার্জ ক্যালকুলেটরে Pathao, Steadfast ও RedX-এর আনুমানিক ডেলিভারি চার্জ জোন ও ওজন দিয়ে তুলনা করুন — COD ফিসহ।
                    রেট আনুমানিক হতে পারে; চূড়ান্ত চার্জ প্যানেলে যাচাই করুন। কনফার্মড অর্ডারে অটো এন্ট্রি চালু রাখুন, ফেক অর্ডার আটকাতে ফ্রড চেক করুন।
                    শুরু করতে এই পেজে হিসাব করুন, বা
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>
                    থেকে ট্রায়াল নিন।
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
