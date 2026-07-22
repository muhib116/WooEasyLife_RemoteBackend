<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
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
        title: 'Courier history at a glance',
        body: 'Enter a mobile number to see delivery and return records from Pathao, Steadfast, RedX, and other supported couriers.',
    },
    {
        title: 'Decide with success rate',
        body: 'Low success rate or heavy returns? Hold confirmation. Strong history? Confirm faster and ship with confidence.',
    },
    {
        title: 'Free tool — no account required',
        body: 'Limited free daily checks work without signup. Built for Bangladesh WooCommerce and Facebook page sellers.',
    },
    {
        title: 'Checker + full protection',
        body: 'WooEasyLife also adds checkout OTP, duplicate blocks, blacklists, courier auto-entry, and parcel note history.',
    },
];

const steps = [
    'Enter the customer’s Bangladesh mobile number before you confirm the COD order.',
    'Review Pathao, Steadfast, RedX (and other supported) delivery and return history.',
    'If risk looks high, call to verify or hold shipping. If history looks strong, confirm.',
    'Turn on OTP, blacklist, and protection features to stop repeat fake orders.',
];

const guideSections = [
    {
        heading: 'What is Courier Fraud Checker BD and why it matters',
        paragraphs: [
            'Free Courier Fraud Checker BD lets Bangladesh COD and WooCommerce sellers verify a customer’s courier delivery history, success rate, and return pattern by mobile number. Checking before confirmation cuts parcel returns, packaging loss, and wasted ad spend.',
            'Many Facebook page and ecommerce orders use wrong numbers, joke orders, or customers with repeat returns. Shipping without history review puts return fees on the seller. Start with limited free daily checks on this page — no account required.',
        ],
    },
    {
        heading: 'How to read success rate',
        paragraphs: [
            'High success rate is usually a safer signal — you can confirm faster and move into courier entry. Very low success rate, repeated returns, or suspicious patterns mean you should call first or hold the order.',
            'One bad record is not always fraud — address or area issues happen too. Pair the fraud check with a short verification call. Estimate monthly return cost with the return-loss calculator.',
        ],
    },
    {
        heading: 'Full workflow to stop fake COD orders',
        paragraphs: [
            'New order → check the phone number here → call if needed → confirm → courier auto-entry. Following this flow reduces fake parcels and return loss.',
            'Manual checks alone are not enough long term. WooEasyLife subscriptions add checkout OTP, duplicate order blocks, phone/device blacklists, daily order limits, and parcel note history. See fake-order protection for the full stack.',
            'Fake orders also pollute Facebook Pixel purchases and inflate reported ROAS. Use the Ads ROAS calculator to compare reported vs real ROAS after removing fake purchases.',
        ],
    },
    {
        heading: 'Who should use this page',
        paragraphs: [
            'Facebook page sellers, WooCommerce COD stores, resellers, and agency teams verifying client orders — all benefit from a free courier history check before shipping.',
            'Prefer Bangla? Use /bd-fraud-checker. Step-by-step Bangla guide: /ki-vabe-fake-order-atkabo. Comparing checker-only tools? Read /en/fraudbd-alternative. English Ads ROAS tool: /en/ads-roas-calculator.',
        ],
    },
];

const decisionList = [
    {
        title: 'Confirm',
        body: 'Strong success rate, low returns, known number — confirm and send via courier auto-entry.',
        tone: 'good',
    },
    {
        title: 'Call first',
        body: 'Medium risk or incomplete address — verify by phone and confirm the delivery details.',
        tone: 'warn',
    },
    {
        title: 'Hold',
        body: 'Very low success rate, repeat returns, suspicious pattern — do not ship without advance payment.',
        tone: 'bad',
    },
];

const mistakeList = [
    'Shipping every COD order without checking history.',
    'Checking once, then skipping checks for the rest of the month.',
    'Shipping anyway after a poor success rate (“maybe it will deliver”).',
    'Relying on the checker alone and leaving OTP/blacklist off.',
    'Scaling ad budget from reported ROAS while ignoring returns.',
];

const relatedLinks = [
    { href: '/en/fake-order-protection', label: 'Fake order protection' },
    { href: '/en/return-loss-calculator', label: 'Return loss calculator' },
    { href: '/en/ads-roas-calculator', label: 'Ads ROAS calculator' },
    { href: '/ki-vabe-fake-order-atkabo', label: 'How to stop fake orders' },
    { href: '/courier-auto-entry', label: 'Courier auto-entry' },
    { href: '/en/courier-auto-entry', label: 'Courier auto-entry (EN)' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/bd-fraud-checker', label: 'বাংলা ভার্সন' },
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
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">Courier Fraud Checker BD</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'Free Courier Fraud Checker BD — delivery history by phone' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Verify courier history and success rate before confirming COD orders.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    Pathao, Steadfast, RedX — check fake or risky customers before you ship.
                    Free tool for Bangladesh ecommerce and Facebook page sellers.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#fraud-check"
                        class="inline-flex rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        Check now
                    </a>
                    <Link
                        href="/en/fake-order-protection"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        Fake order protection
                    </Link>
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
                    >
                        বাংলা ভার্সন
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-b border-white/10 bg-amber-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-amber-200 sm:text-2xl">Quick answer</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    Free Courier Fraud Checker BD shows Pathao, Steadfast, and RedX delivery history and success rate from a customer mobile number.
                    Limited free daily checks work without an account. If success rate is low, pause confirmation — you cut parcel returns and ad waste.
                    For lasting protection, combine this with
                    <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake order protection</Link>
                    and courier auto-entry.
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
                        Free tool — no account needed
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                        Enter a phone number — view courier history
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400">
                        Check before you confirm. Reduce return risk on COD parcels.
                    </p>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">How to use it</h2>
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
                        {{ paragraph }}
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">What to do after the check</h2>
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
                    <h2 class="text-2xl font-bold text-white">Common mistakes to avoid</h2>
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
                    <h2 class="text-2xl font-bold text-white">More than a checker</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">
                        Many fraud tools only show history. WooEasyLife also includes checkout OTP, duplicate blocking,
                        blacklists, courier auto-entry, and missing-order recovery in one platform.
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
                            location="seo_en_bd_fraud"
                            link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                        />
                        <Link
                            href="/pricing"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            View pricing
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">AI summary</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    WooEasyLife Free Courier Fraud Checker BD verifies Pathao, Steadfast, and RedX delivery history and success rate by mobile number
                    to reduce fake COD orders and parcel returns. Free checks work without an account. Add OTP, blacklists, and courier auto-entry for full protection.
                    Start with a phone search on this page, or begin a trial from
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
                    Bangla mirror:
                    <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">/bd-fraud-checker</Link>.
                </p>
            </div>
        </section>

        <section v-if="faqs.length" id="faq" class="scroll-mt-24 border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">FAQs</h2>
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
