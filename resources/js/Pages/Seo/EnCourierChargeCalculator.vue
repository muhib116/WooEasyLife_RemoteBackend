<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import CourierChargeCalculatorSection from '@/components/marketing/CourierChargeCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import { primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    courierChargeCalculator: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = 'Start free trial';

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const steps = [
    'Choose the delivery zone (inside Dhaka / suburb / outside).',
    'Set parcel weight on the slider.',
    'Add a COD amount to include an estimated COD fee.',
    'Compare Pathao, Steadfast, and RedX estimates — then try courier auto-entry.',
];

const pillars = [
    {
        title: 'Three couriers side by side',
        body: 'Compare approximate Pathao, Steadfast, and RedX delivery charges on one page — by zone and weight.',
    },
    {
        title: 'Zone + weight + COD',
        body: 'Inside Dhaka / suburb / outside, parcel kg, and COD amount feed an estimated fee into the total.',
    },
    {
        title: 'Estimate vs official',
        body: 'Steadfast rates may sync from public pricing; Pathao/RedX are often estimates. Verify final charges on the courier panel.',
    },
    {
        title: 'Save time with auto-entry',
        body: 'Knowing the charge is not enough — WooEasyLife auto-enters confirmed orders into the courier so less manual math.',
    },
];

const guideSections = [
    {
        heading: 'Why a courier charge calculator helps',
        paragraphs: [
            'Free courier charge calculator for Bangladesh COD and WooCommerce sellers. Compare approximate Pathao, Steadfast, and RedX delivery charges by Dhaka, suburb, or outside zone and parcel weight. Include COD fee to see which courier looks cheaper.',
            'A ৳20–50 difference per order can become thousands monthly. Before picking the cheapest courier, weigh delivery quality and return rate — cheap charge + high returns = larger loss.',
        ],
    },
    {
        heading: 'How to read the rates (approximate)',
        paragraphs: [
            'Steadfast rates try to sync daily from their public pricing. Pathao’s public calculator is not always available without login — merchant API can update samples, otherwise it is approximate. RedX is often approximate too. Always verify the final bill on the courier panel or contract.',
            'Extra kg adds charge as weight rises. Entering a COD amount may add ~1% COD fee as an example — match the slider to your plan if it differs.',
        ],
    },
    {
        heading: 'Cheapest charge ≠ more profit',
        paragraphs: [
            'Do not decide from the lowest delivery charge alone. High fake orders or returns can wipe out savings. Check success rate with BD Fraud Checker before confirming, and keep fake-order protection on.',
            'Estimate monthly return cost with the return loss calculator. For ads budget, use the Ads ROAS calculator for real ROAS. Courier auto-entry saves operations time at scale.',
        ],
    },
    {
        heading: 'WooEasyLife workflow',
        paragraphs: [
            'Compare charges → fraud-check before confirm → confirm → Pathao/Steadfast/RedX auto-entry. That cuts repeated address and charge typing in panels.',
            'See pricing for plans and trial. Keeping parcel note history and status sync in one place means staff visit courier sites less.',
        ],
    },
];

const tips = [
    {
        title: 'Pick the right zone',
        body: 'Wrong zone shows wrong charges. Separate inside Dhaka, suburb, and outside carefully.',
    },
    {
        title: 'Use realistic weight',
        body: 'Include packing weight. Understating it can mean extra bills or delays later.',
    },
    {
        title: 'Do not ignore COD fee',
        body: 'Compare total cost including COD fee — not only the base delivery charge.',
    },
    {
        title: 'Measure return cost separately',
        body: 'Returns often add another charge. Use the return loss calculator for monthly loss.',
    },
];

const mistakeList = [
    'Picking only the cheapest courier without quality.',
    'Understating weight in the estimate.',
    'Comparing totals without COD fee.',
    'Optimizing charge while fake orders stay high.',
    'Finalizing without checking panel rates.',
];

const relatedLinks = [
    { href: '/en/courier-auto-entry', label: 'Courier auto entry' },
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/en/return-loss-calculator', label: 'Return loss calculator' },
    { href: '/en/ads-roas-calculator', label: 'Ads ROAS calculator' },
    { href: '/fake-order-protection', label: 'Fake order protection' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/courier-charge-calculator', label: 'বাংলা ভার্সন' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-sky-300/90">Courier charge calculator</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'Pathao · Steadfast · RedX — estimate delivery charges' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Compare approximate charges for three couriers by zone and weight.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    Free tool for Bangladesh COD merchants. Estimates include COD fee —
                    verify final charges on the courier panel.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#courier-charge"
                        class="inline-flex rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-400"
                    >
                        Open calculator
                    </a>
                    <Link
                        href="/en/courier-auto-entry"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        Courier auto entry
                    </Link>
                    <Link
                        href="/courier-charge-calculator"
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
                    Use the courier charge calculator to compare approximate Pathao, Steadfast, and RedX delivery charges by zone and weight.
                    Adding a COD amount includes an estimated COD fee. Rates can be approximate — check the final bill on the panel.
                    Keep
                    <Link href="/en/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">courier auto-entry</Link>
                    on for confirmed orders so less manual charge math. Fraud-check numbers first on
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>.
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
            locale="en"
            :show-intro="false"
        />

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">How to use it</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Compare three courier estimates in four steps. Numbers are educational — verify final panel rates.
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
                        {{ paragraph }}
                    </p>
                </article>
                <div class="flex flex-wrap gap-3">
                    <Link href="/en/courier-auto-entry" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Courier auto entry →</Link>
                    <Link href="/en/bd-fraud-checker" class="text-sm font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker →</Link>
                    <Link href="/en/return-loss-calculator" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Return loss calculator →</Link>
                    <Link href="/pricing" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Pricing →</Link>
                    <Link href="/courier-charge-calculator" class="text-sm font-semibold text-amber-400 hover:text-amber-300">বাংলা ভার্সন →</Link>
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
                    <h2 class="text-2xl font-bold text-white">Related tools</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">
                        After comparing charges, review fraud checks, return loss, and auto-entry together for full COD cost.
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
                            location="seo_en_courier_charge_calculator"
                            link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                        />
                        <Link
                            href="/en/courier-auto-entry"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            Courier auto entry
                        </Link>
                        <Link
                            href="/pricing"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                        >
                            Pricing
                        </Link>
                        <Link
                            href="/courier-charge-calculator"
                            class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10"
                        >
                            বাংলা ভার্সন
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">AI summary</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    Compare approximate Pathao, Steadfast, and RedX delivery charges by zone and weight — including COD fee —
                    on the WooEasyLife courier charge calculator. Rates can be approximate; verify on the panel. Keep auto-entry on for confirmed orders and fraud-check before shipping.
                    Start a trial from
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
                    Bangla mirror:
                    <Link href="/courier-charge-calculator" class="font-semibold text-amber-400 hover:text-amber-300">/courier-charge-calculator</Link>.
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
                            {{ item.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
