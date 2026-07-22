<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import AdsRoasCalculatorSection from '@/components/marketing/AdsRoasCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    adsRoasCalculator: { type: Object, default: () => ({}) },
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
    'Set monthly Facebook Ads spend from Ads Manager or payment reports.',
    'Enter how many Pixel Purchase events fired in the same month.',
    'Align fake/cancel/return rate and average order value (AOV).',
    'Compare reported vs real ROAS — note fake purchase signal and estimated budget waste.',
];

const pillars = [
    {
        title: 'What reported ROAS shows',
        body: 'Every Pixel Purchase × AOV ÷ ad spend. The Ads Manager number can include fake, cancelled, and returned orders.',
    },
    {
        title: 'Why real ROAS differs',
        body: 'Counting only confirmed/delivered orders usually lowers ROAS. That is the number to review before scaling campaigns.',
    },
    {
        title: 'Why fake purchases hurt',
        body: 'Facebook optimizes toward the wrong audience. Higher budget can still mean weaker delivery and cash flow.',
    },
    {
        title: 'What WooEasyLife does',
        body: 'Fraud checks, fake-order protection, and pixel protection help send only confirmed purchases — cleaner optimization.',
    },
];

const playbook = [
    {
        title: 'Lower fake rate',
        body: 'Check courier success rate by mobile before confirming. Do not ship high-risk parcels.',
        href: '/en/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
    },
    {
        title: 'Turn on checkout protection',
        body: 'Use OTP, duplicate blocks, and blacklists so the same fake patterns do not repeat.',
        href: '/fake-order-protection',
        linkLabel: 'Fake order protection',
    },
    {
        title: 'Measure return loss',
        body: 'Estimate courier and packing cost per return to see monthly operations loss beside ad spend.',
        href: '/en/return-loss-calculator',
        linkLabel: 'Return loss calculator',
    },
];

const guideSections = [
    {
        heading: 'Why real Facebook Ads ROAS matters after removing fake purchases',
        paragraphs: [
            'Bangladesh COD and WooCommerce sellers often raise budget when Ads Manager shows high ROAS. But not every Pixel Purchase is confirmed or delivered — that gap is hidden loss. Fake, cancelled, and returned orders inflate reported ROAS while bKash or bank profit stays weaker.',
            'This Facebook Ads ROAS calculator compares reported vs real ROAS with spend, Pixel purchases, fake/cancel rate, and AOV. Numbers are educational — attribution windows and delivery rates can change results. Match your store rates before deciding.',
        ],
    },
    {
        heading: 'Reported ROAS vs real ROAS — where they diverge',
        paragraphs: [
            'Reported ROAS = (Pixel Purchase × AOV) ÷ ad spend — close to Ads Manager. Real ROAS = (confirmed purchases × AOV) ÷ ad spend, where confirmed ≈ Pixel Purchase × (1 − fake/cancel%).',
            'Fake purchases push Facebook toward the wrong audience. Scaling budget can grow returns and negative cash flow. WooEasyLife pixel protection helps send only confirmed orders as Purchase events so optimization runs on cleaner data.',
        ],
    },
    {
        heading: 'Example: ৳850,000 spend and 200 purchases',
        paragraphs: [
            'Suppose monthly ad spend is ৳850,000 and Pixel shows 200 purchases. With a higher AOV, reported ROAS can look ~4.8x. At a 30% fake/cancel rate, confirmed purchases drop and real ROAS falls. Put your numbers on the sliders to see the gap.',
            'If the gap is large, fix fraud checks and fake-order protection first, then scale. Doubling spend from reported ROAS alone is risky.',
        ],
    },
    {
        heading: 'Weekly action plan',
        paragraphs: [
            'Day 1: note last week’s ad spend, Pixel purchases, and return rate. Day 2: calculate real ROAS here. Day 3: pause or cut weak ad sets.',
            'Days 4–5: enforce fraud-check and protection routines. Day 6: audit creative/offers — unclear pricing often raises low-quality orders. Day 7: compare ROAS again and set next week’s budget.',
        ],
    },
];

const mistakeList = [
    'Doubling budget from Ads Manager ROAS alone.',
    'Counting purchases without removing cancels/returns.',
    'Scaling after an attribution-window change that inflates conversions.',
    'Spending big on new audiences without fraud checks.',
    'Treating agency reported ROAS as cash-flow profit.',
];

const weeklyChecklist = [
    {
        title: 'Spend vs cash in',
        body: 'Compare weekly ad spend with money received. If the gap grows, update fake/return rate and rerun the calculator.',
    },
    {
        title: 'Review ad sets separately',
        body: 'Do not scale an ad set with high purchases and high returns from reported ROAS alone.',
    },
    {
        title: 'Creative and offer',
        body: 'Heavy discounts or unclear pricing often raise low-quality orders and lower real ROAS.',
    },
    {
        title: 'Pixel event audit',
        body: 'Check whether test orders, duplicate purchases, or incomplete checkouts are firing events.',
    },
];

const formulaRows = [
    { label: 'Reported revenue', value: 'Pixel Purchase × AOV' },
    { label: 'Reported ROAS', value: 'Reported revenue ÷ Ads spend' },
    { label: 'Confirmed purchases', value: 'Pixel Purchase × (1 − fake/cancel%)' },
    { label: 'Real ROAS', value: '(Confirmed purchases × AOV) ÷ Ads spend' },
];

const whoFor = [
    'COD / WooCommerce stores running Facebook Ads',
    'Page sellers with high returns and cancels',
    'Agencies or in-house media buyers who need to explain real profit',
    'Teams measuring risk before scaling a new campaign',
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
                <p class="text-sm font-semibold tracking-[0.18em] text-sky-300/90">Facebook Ads ROAS calculator</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'What is your real Ads ROAS after removing fake purchases?' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Compare reported vs real ROAS with ad spend and Pixel purchases.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    Free educational tool for Bangladesh COD & WooCommerce sellers.
                    Pixel Purchase ≠ confirmed order — use the sliders to see real ROAS and ad budget waste.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#ads-roas"
                        class="inline-flex rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-400"
                    >
                        Open calculator
                    </a>
                    <Link
                        href="/en/bd-fraud-checker"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        Free fraud check
                    </Link>
                    <Link
                        href="/ads-roas-calculator"
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
                    Enter ad spend, Pixel purchases, fake/cancel rate, and AOV to estimate real Facebook Ads ROAS after removing fake purchases.
                    Reported ROAS counts every Pixel purchase; real ROAS only counts confirmed orders.
                    If the gap is large, turn on
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">fraud checks</Link>
                    and
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake-order protection</Link>
                    before scaling. WooEasyLife pixel protection helps send only confirmed purchases.
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

        <AdsRoasCalculatorSection
            :config="adsRoasCalculator"
            :primary-cta-url="ctaUrl"
            :primary-cta-label="ctaLabel"
            locale="en"
            :show-intro="false"
        />

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">How to use it</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Compare reported and real ROAS in four steps. Numbers are educational — results vary with attribution and delivery rate.
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
                        <LinkedRichText :text="paragraph" :is-en="true" />
                    </p>
                </article>

                <div class="flex flex-wrap gap-3">
                    <Link href="/en/bd-fraud-checker" class="text-sm font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker →</Link>
                    <Link href="/fake-order-protection" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Fake order protection →</Link>
                    <Link href="/en/return-loss-calculator" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Return loss calculator →</Link>
                    <Link href="/pricing" class="text-sm font-semibold text-amber-400 hover:text-amber-300">Pricing →</Link>
                    <Link href="/ads-roas-calculator" class="text-sm font-semibold text-amber-400 hover:text-amber-300">বাংলা ভার্সন →</Link>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">What to do to raise real ROAS</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    The calculator shows the problem — fixing it means fewer fake orders and cleaner Pixel events.
                </p>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="item in playbook"
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

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">How the math works</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Simple formulas behind the calculator — for understanding real COD profit.
                </p>
                <div class="mt-8 overflow-hidden rounded-2xl border border-white/10">
                    <div
                        v-for="row in formulaRows"
                        :key="row.label"
                        class="flex flex-col gap-1 border-b border-white/10 bg-white/5 px-4 py-3 last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span class="text-sm font-semibold text-slate-200">{{ row.label }}</span>
                        <span class="font-mono text-sm text-amber-300/90">{{ row.value }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">Weekly ROAS checklist</h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="item in weeklyChecklist"
                        :key="item.title"
                        class="rounded-2xl border border-white/10 bg-white/5 p-5"
                    >
                        <h3 class="text-base font-bold text-white">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ item.body }}</p>
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
                    <h2 class="text-2xl font-bold text-white">Who this tool is for</h2>
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
                    <p class="mt-6 text-sm leading-relaxed text-slate-400">
                        Prefer Bangla?
                        <Link href="/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Bangla version</Link>
                    </p>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">AI summary</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    Compare reported vs real Facebook Ads ROAS after removing fake purchases.
                    For COD sellers, real ROAS should drive scale decisions. WooEasyLife helps with fraud checks, fake-order protection, and pixel protection for cleaner Purchase signals.
                    Estimates are educational — match your return rate, then start a trial from
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_en_ads_roas_calculator"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/fake-order-protection"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Fake order protection
                </Link>
                <Link
                    href="/pricing"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Pricing
                </Link>
                <Link
                    href="/ads-roas-calculator"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10"
                >
                    বাংলা ভার্সন
                </Link>
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
