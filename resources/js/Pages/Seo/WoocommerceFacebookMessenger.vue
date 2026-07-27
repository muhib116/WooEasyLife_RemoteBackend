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
        title: 'WP অ্যাডমিনে Page ইনবক্স',
        body: 'Facebook Page কানেক্ট করে মেসেঞ্জার চ্যাট WooEasyLife / WordPress অ্যাডমিনে — স্টোরফ্রন্ট চ্যাট বাবল নয়।',
    },
    {
        title: 'টেক্সট · ইমেজ · ভয়েস',
        body: 'রিপ্লাই, মিডিয়া, ভয়েস নোট ও টাইপিং ইন্ডিকেটর — মানব ইনবক্স হিসেবে দৈনন্দিন পেজ সেল।',
    },
    {
        title: 'Lead labels',
        body: 'new → nurture → converted লেবেল (ম্যানুয়াল ওভাররাইড) — কোন থ্রেড হট লিড তা টিম দেখে।',
    },
    {
        title: 'অর্ডার লিংক পাথ',
        body: 'থ্রেড থেকে WooCommerce অর্ডারের দিকে যাওয়া যায় (Sales Agent path; admin approval ডিফল্ট)। AI Full সবার জন্য আনলক নয়।',
    },
];

const steps = [
    'WooEasyLife → Messenger থেকে Facebook Page কানেক্ট করুন (এক পেজ)।',
    'ইনবক্সে নতুন মেসেজ দেখুন; প্রয়োজনে লেবেল দিন (new / nurture / converted)।',
    'টেক্সট, ইমেজ বা ভয়েস দিয়ে রিপ্লাই করুন — অর্ডার কনফার্মের আগে ফ্রড চেক করুন।',
    'কনফার্ম হলে কুরিয়ার অটো এন্ট্রি; ঝুঁকি বেশি হলে OTP/ব্ল্যাকলিস্ট চালু রাখুন।',
];

const guideSections = [
    {
        heading: 'WooCommerce Facebook Messenger ইনবক্স কী',
        paragraphs: [
            'বাংলাদেশি Facebook পেজ COD সেলাররা অর্ডারের বেশিরভাগ কথোপকথন মেসেঞ্জারে করে। WooEasyLife Messenger ইনবক্স সেই চ্যাট WordPress অ্যাডমিনে আনে — ManyChat/Chatfuel স্টাইল স্টোরফ্রন্ট উইজেট নয়।',
            'একটি কানেক্টেড Page (v1)। টোকেন হাবে সেভ; কানেক্ট/ডিসকানেক্ট UI থেকেই।',
        ],
    },
    {
        heading: 'মানব ইনবক্স vs AI Semi',
        paragraphs: [
            'শিপড কোর: মানব রিপ্লাই, মিডিয়া, ভয়েস, টাইপিং, লিড লেবেল। AI Semi (সাজেশন ড্রাফট) প্যাকেজ-গেটেড beta — AI Full বা “Meta AI Bot” আলাদা প্রোডাক্ট হিসেবে দাবি করবেন না।',
            'পেজ সেলারদের জয়ের অ্যাঙ্গেল: ইনবক্স → অর্ডার পাথ, ওয়েবসাইট চ্যাট বাবল নয়।',
        ],
    },
    {
        heading: 'COD ওয়ার্কফ্লো: মেসেজ → চেক → কনফার্ম',
        paragraphs: [
            'মেসেঞ্জার থেকে অর্ডার নেওয়ার পর মোবাইল নম্বর ফ্রড চেকারে দিন। ঝুঁকি কম হলে কনফার্ম → কুরিয়ার অটো এন্ট্রি। বারবার ফেক আটকাতে ফেক অর্ডার প্রোটেকশন চালু রাখুন।',
            'পেজ COD ম্যানেজমেন্ট গাইড ও প্রাইসিং থেকে ট্রায়াল নিয়ে শুরু করতে পারেন।',
        ],
    },
    {
        heading: 'কার জন্য / কী নয়',
        paragraphs: [
            'এক পেজ COD সেলার ও ছোট টিমের জন্য। মাল্টি-Page ইনবক্স বা আনলকড AI Full এই পেজের দাবি নয়।',
            'কুরিয়ার রিটার্ন অপসের জন্য SteadFast Return Hub আলাদা পেজ।',
        ],
    },
];

const layers = [
    {
        title: '১. ইনবক্স',
        body: 'Page মেসেজ WP অ্যাডমিনে পড়ুন ও রিপ্লাই দিন।',
        href: '/woocommerce-facebook-messenger',
        linkLabel: 'এই পেজ',
        tone: 'auto',
    },
    {
        title: '২. ফ্রড চেক',
        body: 'কনফার্মের আগে মোবাইল হিস্টোরি দেখুন।',
        href: '/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
        tone: 'warn',
    },
    {
        title: '৩. বুকিং',
        body: 'নিরাপদ অর্ডার কনফার্ম = কুরিয়ার অটো এন্ট্রি।',
        href: '/courier-auto-entry',
        linkLabel: 'কুরিয়ার অটো এন্ট্রি',
        tone: 'good',
    },
];

const mistakeList = [
    'মেসেঞ্জার অর্ডার অন্ধ কনফার্ম করে ফ্রড চেক স্কিপ করা।',
    'স্টোরফ্রন্ট চ্যাট প্লাগইনকে এই ইনবক্সের সাথে গুলিয়ে ফেলা।',
    'AI Full / Meta AI Bot লাইভ বলে বিজ্ঞাপন দেওয়া।',
    'মাল্টি-Page ইনবক্স আছে বলে দাবি করা (v1 = এক পেজ)।',
];

const whoFor = [
    'Facebook পেজ COD সেলার যারা মেসেঞ্জার থেকে অর্ডার নেন',
    'WooCommerce স্টোর যারা WP অ্যাডমিনে ইনবক্স চান',
    'টিম যারা lead label দিয়ে nurture ট্র্যাক করে',
    'যারা চ্যাট বাবল নয়, অর্ডার-লিংকড ইনবক্স চান',
];

const relatedLinks = [
    { href: '/facebook-page-cod-management', label: 'Facebook Page COD' },
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/fake-order-protection', label: 'ফেক অর্ডার প্রোটেকশন' },
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/steadfast-return-hub', label: 'SteadFast Return Hub' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/woocommerce-facebook-messenger', label: 'English version' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">FACEBOOK MESSENGER</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'WooCommerce Facebook Messenger ইনবক্স' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Page মেসেঞ্জার চ্যাট WordPress অ্যাডমিনে — লিড লেবেল, মিডিয়া রিপ্লাই, অর্ডার পাথ।' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    BD পেজ COD সেলারদের জন্য মানব ইনবক্স। AI Semi beta/গেটেড — Meta AI Bot নয়।
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_woocommerce_facebook_messenger_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        ফ্রি ফ্রড চেক
                    </Link>
                    <Link
                        href="/en/woocommerce-facebook-messenger"
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
                    WooEasyLife-এ Facebook Page কানেক্ট করে মেসেঞ্জার ইনবক্স WP অ্যাডমিনে চালান — টেক্সট/ইমেজ/ভয়েস রিপ্লাই ও lead labels। অর্ডার কনফার্মের আগে
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>
                    ব্যবহার করুন; শুরু করতে
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

        <section class="border-y border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
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
                        <template v-if="section.heading.startsWith('COD ওয়ার্কফ্লো') && idx === 0">
                            মেসেঞ্জার থেকে অর্ডার নেওয়ার পর মোবাইল নম্বর
                            <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">ফ্রড চেকারে</Link>
                            দিন। ঝুঁকি কম হলে কনফার্ম →
                            <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>।
                            বারবার ফেক আটকাতে
                            <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>
                            চালু রাখুন।
                        </template>
                        <template v-else-if="section.heading.startsWith('COD ওয়ার্কফ্লো') && idx === 1">
                            পেজ COD গাইড:
                            <Link href="/facebook-page-cod-management" class="font-semibold text-amber-400 hover:text-amber-300">Facebook Page COD ম্যানেজমেন্ট</Link>।
                            ট্রায়াল:
                            <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>।
                        </template>
                        <template v-else-if="section.heading.startsWith('কার জন্য') && idx === 1">
                            কুরিয়ার রিটার্ন অপস:
                            <Link href="/steadfast-return-hub" class="font-semibold text-amber-400 hover:text-amber-300">SteadFast Return Hub</Link>।
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="false" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">৩ ধাপ সেল ফ্লো</h2>
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
                    WooEasyLife WooCommerce Facebook Messenger ইনবক্স: Page কানেক্ট, মানব চ্যাট, lead labels, অর্ডার পাথ — BD পেজ COD সেলারদের জন্য। ট্রায়াল
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">প্রাইসিং</Link>।
                    English:
                    <Link href="/en/woocommerce-facebook-messenger" class="font-semibold text-amber-400 hover:text-amber-300">English version</Link>।
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_woocommerce_facebook_messenger"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/facebook-page-cod-management"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Page COD গাইড
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
