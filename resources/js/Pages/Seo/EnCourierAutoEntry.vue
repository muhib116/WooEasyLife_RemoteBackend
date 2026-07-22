<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaUrl } from '@/utils/marketingCta';
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
const ctaLabel = 'Start free trial';

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const pillars = [
    {
        title: 'Confirm = auto-entry',
        body: 'Confirm an order and name, phone, address, and COD amount enter Pathao, Steadfast, or RedX automatically — no manual copy-paste.',
    },
    {
        title: '3 couriers, one dashboard',
        body: 'Stop logging into separate panels. Entry, status sync, and customer SMS run from WooEasyLife.',
    },
    {
        title: 'Save 3+ hours a day',
        body: 'A few minutes per order × dozens of orders = hours lost. Auto-entry frees staff for packing and follow-up.',
    },
    {
        title: 'Parcel note history',
        body: 'View and update Steadfast parcel notes inside WooEasyLife — no separate courier site visit required.',
    },
];

const steps = [
    'Connect Pathao / Steadfast / RedX accounts inside WooEasyLife.',
    'On new orders, check the mobile number with BD Fraud Checker first.',
    'If risk looks low, confirm the order — confirm triggers courier auto-entry.',
    'Keep status sync and SMS on; use parcel note history for follow-up notes.',
];

const guideSections = [
    {
        heading: 'What courier auto-entry is and why it matters',
        paragraphs: [
            'In Bangladesh COD operations, typing name, phone, address, and COD amount into courier websites wastes hours every day. Typos cause wrong deliveries, returns, and customer complaints. With WooEasyLife courier auto-entry, order confirm = automatic Pathao, Steadfast, or RedX parcel entry.',
            'One dashboard for three couriers, status sync, and customer SMS — staff stop logging into courier sites repeatedly. Even 50+ orders/day stays smooth so packing and follow-up get more time.',
        ],
    },
    {
        heading: 'Manual entry vs auto — time and errors',
        paragraphs: [
            'Manual entry takes a few minutes per order. 50 orders × 2 minutes ≈ 100 minutes/day of copy-paste. Monthly staff time can cost thousands of taka. With WooEasyLife auto-entry, a confirm click is enough.',
            'Hand typing often creates wrong numbers or areas. Auto-entry uses WooCommerce order data, so typos drop. Final charges still follow your courier plan — compare estimates first with the courier charge calculator.',
        ],
    },
    {
        heading: 'Status sync, SMS, and parcel note history',
        paragraphs: [
            'Courier auto status sync pulls delivery or return updates into the WooCommerce order — no endless tab switching. Order and delivery SMS reduce “where is my parcel?” calls.',
            'Steadfast parcel note history is visible and updatable inside WooEasyLife. Keep team notes, call records, and follow-ups in one place.',
        ],
    },
    {
        heading: 'Safe workflow: fraud check → confirm → auto-entry',
        paragraphs: [
            'Auto-entry is fast, but risky orders should not ship automatically. First check courier history and success rate by mobile. Low success rate or repeat returns → phone confirm or hold.',
            'Strong history → confirm quickly — confirm triggers auto-entry. Stop repeat fake patterns with checkout OTP, duplicate blocks, and blacklists. Also measure monthly return cost and real Ads ROAS.',
        ],
    },
];

const compareRows = [
    { label: 'Per-order entry', manual: 'Hand copy-paste', auto: 'Confirm = auto' },
    { label: 'Courier panels', manual: 'Repeated logins', auto: 'One dashboard' },
    { label: 'Typos / wrong address', manual: 'Higher risk', auto: 'From order data' },
    { label: 'Status updates', manual: 'Manual checks', auto: 'Auto sync' },
    { label: 'Customer updates', manual: 'Separate SMS tool', auto: 'SMS in one place' },
    { label: 'Staff time (daily)', manual: 'Hours wasted', auto: 'Can save 3+ hours' },
];

const workflowCards = [
    {
        title: '1. Fraud check',
        body: 'Check Pathao, Steadfast, RedX history and success rate by mobile number.',
        href: '/en/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
        tone: 'warn',
    },
    {
        title: '2. Confirm',
        body: 'Low risk → confirm; high risk → call or hold. Keep OTP and blacklists on.',
        href: '/en/fake-order-protection',
        linkLabel: 'Fake order protection',
        tone: 'good',
    },
    {
        title: '3. Auto-entry',
        body: 'Confirm pushes the parcel to courier — keep status sync and SMS enabled.',
        href: '/pricing',
        linkLabel: 'Start trial',
        tone: 'auto',
    },
];

const mistakeList = [
    'Auto-entering every order without a fraud check.',
    'Running entry with the wrong courier account connected.',
    'Leaving status sync off and checking panels manually.',
    'Assuming zone/weight charges without comparing rates.',
    'Running auto-entry while OTP/blacklists stay off.',
];

const whoFor = [
    'Facebook page sellers handling many COD orders daily',
    'WooCommerce stores where staff are stuck in courier panels',
    'Dropship / reseller teams with rising parcel volume',
    'Agencies entering Pathao/Steadfast/RedX parcels for clients',
];

const relatedLinks = [
    { href: '/en/woocommerce-bangladesh', label: 'WooCommerce Bangladesh guide' },
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/en/fake-order-protection', label: 'Fake order protection' },
    { href: '/en/courier-charge-calculator', label: 'Courier charge calculator' },
    { href: '/en/return-loss-calculator', label: 'Return loss calculator' },
    { href: '/en/ads-roas-calculator', label: 'Ads ROAS calculator' },
    { href: '/en/fraudbd-alternative', label: 'FraudBD Alternative' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/courier-auto-entry', label: 'বাংলা ভার্সন' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">Courier Auto Entry</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'Courier Auto Entry — Pathao, Steadfast, RedX' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Confirm an order and the courier panel fills automatically. Skip manual typing and save time.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    Built for WooCommerce and Facebook page COD sellers — status sync, SMS, and parcel note history in one place.
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_en_courier_auto_entry_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/en/bd-fraud-checker"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        Fraud check first
                    </Link>
                    <Link
                        href="/courier-auto-entry"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 sm:w-auto"
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
                    Courier auto-entry means confirming an order automatically pushes parcel details into Pathao, Steadfast, or RedX.
                    WooCommerce and Facebook page COD sellers can skip manual copy-paste and save 3+ hours a day.
                    Check the number first on
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>,
                    then confirm — confirm triggers auto-entry. Compare charges on the
                    <Link href="/en/courier-charge-calculator" class="font-semibold text-amber-400 hover:text-amber-300">courier charge calculator</Link>.
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
                <h2 class="text-center text-2xl font-bold text-white">How to turn it on</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Four steps to safer courier automation — check first, then confirm, then auto-entry.
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
                        <template v-if="section.heading.startsWith('Manual entry') && idx === 1">
                            Hand typing often creates wrong numbers or areas. Auto-entry uses WooCommerce order data, so typos drop.
                            Final charges still follow your courier plan — compare estimates first on the
                            <Link href="/en/courier-charge-calculator" class="font-semibold text-amber-400 hover:text-amber-300">courier charge calculator</Link>.
                        </template>
                        <template v-else-if="section.heading.startsWith('Safe workflow') && idx === 0">
                            Auto-entry is fast, but risky orders should not ship automatically. First check courier history and success rate on
                            <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>.
                            Low success rate or repeat returns → phone confirm or hold.
                        </template>
                        <template v-else-if="section.heading.startsWith('Safe workflow') && idx === 1">
                            Strong history → confirm quickly — confirm triggers auto-entry. Stop repeat fake patterns with
                            <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake order protection</Link>.
                            Measure monthly return cost with the
                            <Link href="/en/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">return loss calculator</Link>
                            and real ad ROAS with the
                            <Link href="/en/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Ads ROAS calculator</Link>.
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="true" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">Manual entry vs auto</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Side-by-side differences — why COD teams switch to auto-entry.
                </p>
                <div class="-mx-4 mt-8 overflow-x-auto px-4 sm:mx-0 sm:overflow-visible sm:px-0">
                    <div class="min-w-[22rem] overflow-hidden rounded-2xl border border-white/10 sm:min-w-0">
                    <div class="grid grid-cols-3 gap-2 border-b border-white/10 bg-white/10 px-3 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-300 sm:px-4 sm:text-sm">
                        <span>Topic</span>
                        <span>Manual</span>
                        <span class="text-amber-300">Auto</span>
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
                <h2 class="text-center text-2xl font-bold text-white">Safe operations flow</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Fast entry + fraud checks together — speed with safer COD operations.
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
                    <h2 class="text-2xl font-bold text-white">Who this is for</h2>
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
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">AI summary</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    WooEasyLife courier auto-entry pushes Pathao, Steadfast, and RedX parcels when orders are confirmed —
                    saving WooCommerce COD sellers from manual typing and 3+ hours/day. Includes status sync, SMS, and Steadfast parcel note history.
                    Safe flow:
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">fraud check</Link>
                    → confirm → auto-entry; protection via
                    <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake order protection</Link>.
                    Start a trial from
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
                    Bangla:
                    <Link href="/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">Bangla version</Link>.
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_en_courier_auto_entry"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/en/bd-fraud-checker"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Free fraud check
                </Link>
                <Link
                    href="/pricing"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Pricing
                </Link>
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
                            <LinkedRichText :text="item.a" :is-en="true" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
