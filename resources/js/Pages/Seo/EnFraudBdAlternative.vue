<script setup>
import { Link, usePage } from '@inertiajs/vue3';
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
        title: 'Built-in fraud check',
        body: 'Courier history and success rate for Pathao, Steadfast, RedX, and more — free checks without an account.',
    },
    {
        title: 'Fake-order protection',
        body: 'Checkout OTP, duplicate blocks, blacklists, and order limits — not just history, actual blocking.',
    },
    {
        title: 'Courier auto-entry',
        body: 'On confirm, parcels push to Pathao/Steadfast/RedX — less manual panel typing.',
    },
    {
        title: 'App + recovery',
        body: 'Mobile app, multi-store, missing-order recovery, and parcel note history in one platform.',
    },
];

const couriers = usePage().props.publicCouriers || {};
const compareRows = [
    { feature: 'Courier history', fraudbd: 'Courier history check by phone', woo: couriers.historyPhraseEn || '' },
    { feature: 'WordPress / WooCommerce', fraudbd: 'WordPress/WooCommerce plugin', woo: 'Checkout OTP, duplicate-order block and blacklist' },
    { feature: 'API', fraudbd: 'REST API', woo: '' },
    { feature: 'Android app', fraudbd: 'Android app on Google Play', woo: 'Android app' },
    { feature: 'Price', fraudbd: 'Public price table', woo: 'Own plan cards' },
    { feature: 'Courier auto-entry', fraudbd: '', woo: couriers.bookingPhraseEn || '' },
    { feature: 'Missing orders', fraudbd: '', woo: 'Missing-order recovery' },
];

const guideSections = [
    {
        heading: 'Why people search for a FraudBD alternative',
        paragraphs: [
            'FraudBD and similar checker-only tools mainly show courier history from a mobile number. That helps Bangladesh COD and WooCommerce sellers — but checking alone is not enough. Repeat numbers, no checkout OTP, or manual courier panel entry after confirm still grow losses.',
            'As a FraudBD alternative, WooEasyLife includes the same style of BD fraud checker plus fake-order protection, courier auto-entry, missing-order recovery, and a mobile app. Not just another tool — a full operations platform: check → block → confirm → auto-entry.',
        ],
    },
    {
        heading: 'Checker-only vs full platform',
        paragraphs: [
            'Checker-only: enter a number, see history. Staff decide manually; checkout and courier panels still need other tools.',
            'WooEasyLife: free checks plus subscription OTP/blacklist, auto courier entry on confirm, status sync, and parcel notes. Return-loss and Ads ROAS calculators help measure cost too.',
            'When dozens of COD orders arrive daily, tab-hopping to check and copy-paste is slow and error-prone. A platform workflow reduces that friction.',
        ],
    },
    {
        heading: 'How to switch',
        paragraphs: [
            'First test free checks on /en/bd-fraud-checker — no account needed. Then read /en/fake-order-protection and /en/courier-auto-entry for the workflow.',
            'Start a trial from /pricing. Connect WooCommerce, link courier accounts, turn on protection rules. You can keep an old checker tab, but most daily work moves into WooEasyLife.',
        ],
    },
    {
        heading: 'Who this page is for',
        paragraphs: [
            'Anyone searching “FraudBD alternative”, “fraud checker BD alternative”, or “courier fraud check + WooCommerce”. Facebook page and COD stores that want full protection — not history alone.',
            'Bangla version: /fraudbd-alternative. Step-by-step Bangla guide: /ki-vabe-fake-order-atkabo.',
        ],
    },
];

const workflow = [
    { title: 'Check', body: 'Before confirming, review courier success rate by mobile number.', href: '/en/bd-fraud-checker', linkLabel: 'BD Fraud Checker' },
    { title: 'Block', body: 'Stop repeat fake patterns with OTP, duplicate blocks, and blacklists.', href: '/fake-order-protection', linkLabel: 'Fake order protection' },
    { title: 'Auto-entry', body: 'On confirm, parcel data goes to Pathao/Steadfast/RedX automatically.', href: '/en/courier-auto-entry', linkLabel: 'Courier auto entry' },
];

const tips = [
    {
        title: 'Do not ship without a check',
        body: 'Low success rate or heavy returns? Call to verify first — same rule whether you use a cheap checker or WooEasyLife.',
    },
    {
        title: 'Measure return loss',
        body: 'Without monthly return cost, a “cheap tool” can hide larger losses. Use the return loss calculator.',
    },
    {
        title: 'Keep Pixel clean',
        body: 'Fake purchases inflate ROAS. Pair the Ads ROAS calculator with pixel protection.',
    },
    {
        title: 'Keep one flow',
        body: 'Check → confirm → auto-entry in one system means fewer tabs and fewer typos.',
    },
];

const mistakeList = [
    'Keeping only a fraud-check tool without checkout protection.',
    'Confirming every order after a quick glance at history.',
    'Continuing manual courier panel entry after confirm.',
    'Calling a tool “cheap” without measuring return loss.',
    'Raising ad budget while fake orders keep rising.',
];

const relatedLinks = [
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/en/fake-customer-check', label: 'Fake Customer Check' },
    { href: '/en/fake-order-protection', label: 'Fake order protection' },
    { href: '/en/courier-auto-entry', label: 'Courier auto entry' },
    { href: '/en/return-loss-calculator', label: 'Return loss calculator' },
    { href: '/en/ads-roas-calculator', label: 'Ads ROAS calculator' },
    { href: '/en', label: 'English home' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/fraudbd-alternative', label: 'বাংলা ভার্সন' },
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
                    {{ seo?.prerender_h1 || 'FraudBD Alternative — a full platform, not just a tool' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Not only fraud history — check, block, courier auto-entry, and recovery together.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    FraudBD’s public site lists courier history, a WooCommerce plugin, a REST API, and an Android app.
                    WooEasyLife adds checkout OTP, blocking, and courier auto-entry beside history checks.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <Link
                        href="/en/bd-fraud-checker"
                        class="inline-flex rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        Free fraud check
                    </Link>
                    <Link
                        href="/pricing"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        View pricing
                    </Link>
                    <Link
                        href="/fraudbd-alternative"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
                    >
                        বাংলা ভার্সন
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-b border-white/10 bg-sky-950/20 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-sky-200 sm:text-2xl">Quick answer</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    As a FraudBD alternative, WooEasyLife is more than courier history —
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">free fraud checks</Link>,
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake-order protection</Link>,
                    <Link href="/en/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">courier auto-entry</Link>,
                    and a mobile app. The dated table below uses only public facts from 31 Oct 2026.
                    Start at
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
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
                <h2 class="text-center text-2xl font-bold text-white">FraudBD vs WooEasyLife (checked 31 Oct 2026)</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Verified public facts only. FraudBD prices are not copied here.
                </p>
                <table class="mt-8 w-full min-w-[32rem] border-collapse text-left text-sm text-slate-300">
                    <thead>
                        <tr class="border-b border-white/15 text-slate-400">
                            <th class="py-3 pr-4 font-medium">Topic</th>
                            <th class="py-3 pr-4 font-medium">FraudBD</th>
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
                            <td class="py-3 pr-4">
                                <a
                                    v-if="row.feature === 'Price'"
                                    href="https://fraudbd.com/"
                                    class="font-semibold text-amber-400 hover:text-amber-300"
                                    rel="noopener noreferrer"
                                >{{ row.fraudbd }}</a>
                                <template v-else>{{ row.fraudbd }}</template>
                            </td>
                            <td class="py-3 text-emerald-400">
                                <Link v-if="row.feature === 'Price'" href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">/pricing</Link>
                                <template v-else>{{ row.woo }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-slate-400">
                    If you only need phone-number lookups or an API for a custom (non-WooCommerce) platform, a checker-only tool can be enough.
                    Competitor features change — verify on their site before deciding.
                </p>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">Check → block → auto-entry</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Keep FraudBD-style checks, then add protection and courier automation.
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
                        <LinkedRichText :text="paragraph" :is-en="true" />
                    </p>
                </article>
                <div class="flex flex-wrap gap-3">
                    <Link href="/en/bd-fraud-checker" class="text-sm font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker →</Link>
                    <Link href="/fake-order-protection" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Fake order protection →</Link>
                    <Link href="/en/courier-auto-entry" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Courier auto entry →</Link>
                    <Link href="/pricing" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Pricing →</Link>
                    <Link href="/fraudbd-alternative" class="text-sm font-semibold text-amber-400 hover:text-amber-300">বাংলা ভার্সন →</Link>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">Practical tips</h2>
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
                    <h2 class="text-2xl font-bold text-white">Related pages</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">
                        After the comparison, try a free check, turn on protection, then start a trial.
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
                            href="/en/bd-fraud-checker"
                            class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                        >
                            Free fraud check
                        </Link>
                        <MetaCtaLink
                            :href="ctaUrl"
                            :label="ctaLabel"
                            location="seo_en_fraudbd_alternative"
                            link-class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        />
                        <Link
                            href="/pricing"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            Pricing
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">In short</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    WooEasyLife is a FraudBD alternative — free BD fraud checker plus fake-order protection,
                    courier auto-entry, missing-order recovery, and a mobile app. Checker-only tools show history;
                    WooEasyLife covers the full COD workflow. Free check:
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>,
                    trial:
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">Pricing</Link>.
                    Bangla:
                    <Link href="/fraudbd-alternative" class="font-semibold text-amber-400 hover:text-amber-300">Bangla version</Link>.
                </p>
            </div>
        </section>

        <section v-if="faqs.length" class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">FAQ</h2>
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
