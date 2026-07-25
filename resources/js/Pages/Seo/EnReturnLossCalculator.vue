<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import RoiCalculatorSection from '@/components/marketing/RoiCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    roiCalculator: { type: Object, default: () => ({}) },
    roiScenarios: { type: Array, default: () => [] },
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
    'Set daily order count on the slider.',
    'Enter your current return/cancel rate.',
    'Align average cost per return (courier + pack + time).',
    'Review monthly loss and estimated savings — then run a fraud check or start a trial.',
];

const pillars = [
    {
        title: 'Monthly return loss',
        body: 'Daily orders × return rate × cost per return — see COD loss at a glance.',
    },
    {
        title: 'Estimated savings',
        body: 'Shows how much you might save if fraud checks and protection block a share of returns.',
    },
    {
        title: 'What to count as cost',
        body: 'Courier return fees, packaging, and staff time — often ৳150–300+ per return.',
    },
    {
        title: 'Next step',
        body: 'If loss is high, turn on fraud checks + OTP/blacklists — do not stop at the estimate.',
    },
];

const guideSections = [
    {
        heading: 'What the return loss calculator shows',
        paragraphs: [
            'Enter daily orders, return/cancel rate, and average cost per return to estimate monthly COD return loss and potential savings.',
            'Cost per return usually includes courier return fees, packaging, and time. Match the slider to your store’s real numbers.',
        ],
    },
    {
        heading: 'Simple formulas',
        paragraphs: [
            'Monthly orders ≈ daily orders × 30. Monthly returns ≈ monthly orders × return rate. Monthly loss ≈ monthly returns × cost per return.',
            'Estimated savings are approximate — sliders update instantly. Decision support, not an audit report.',
        ],
    },
    {
        heading: 'Example: 50 orders/day at 25% return rate',
        paragraphs: [
            'At 50 daily orders, 25% returns, and ৳120 per return you get ≈ 1,500 monthly orders and ≈ 375 returns — loss can reach thousands of taka.',
            'Lowering return rate or blocking fake orders increases savings. Put your numbers on the sliders to see the gap.',
        ],
    },
    {
        heading: 'How to cut return loss',
        paragraphs: [
            'First fraud check, then OTP/blacklists, then auto-entry for safe orders.',
            'Also measure fake purchases on ads — return loss and Ads ROAS together make clearer decisions.',
        ],
    },
];

const formulaRows = [
    { label: 'Monthly orders', value: 'Daily orders × 30' },
    { label: 'Monthly returns', value: 'Monthly orders × return rate' },
    { label: 'Monthly loss', value: 'Monthly returns × cost per return' },
    { label: 'Estimated savings', value: 'Blocked returns × cost per return' },
];

const playbook = [
    {
        title: 'Run a fraud check',
        body: 'Check success rate by mobile before confirming COD orders.',
        href: '/en/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
    },
    {
        title: 'Turn on protection',
        body: 'Use OTP, duplicate blocks, and blacklists so the same risk does not repeat.',
        href: '/en/fake-order-protection',
        linkLabel: 'Fake order protection',
    },
    {
        title: 'See real ROAS',
        body: 'Compare reported vs real Ads ROAS after removing fake purchases.',
        href: '/en/ads-roas-calculator',
        linkLabel: 'Ads ROAS calculator',
    },
];

const mistakeList = [
    'Counting only courier fees and ignoring packing/time.',
    'Calculating once and ignoring the rest of the month.',
    'Seeing loss but not enabling fraud checks/OTP.',
    'Scaling budget from Ads Manager ROAS alone.',
    'Spending big on new audiences without measuring return loss.',
];

const whoFor = [
    'COD / WooCommerce stores with high returns',
    'Facebook page sellers who want monthly loss math',
    'Agencies explaining return cost to clients',
    'Teams measuring ops loss before scaling ads',
];

const relatedLinks = [
    { href: '/en/woocommerce-bangladesh', label: 'WooCommerce Bangladesh guide' },
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/en/fake-order-protection', label: 'Fake order protection' },
    { href: '/en/ads-roas-calculator', label: 'Ads ROAS calculator' },
    { href: '/en/courier-charge-calculator', label: 'Courier charge calculator' },
    { href: '/en/courier-auto-entry', label: 'Courier auto-entry' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/return-loss-calculator', label: 'বাংলা ভার্সন' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-emerald-300/90">Return Loss Calculator</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'How much can you save monthly by cutting return loss?' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Enter daily orders and return rate — see monthly loss and savings.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    Free educational tool for Bangladesh COD and WooCommerce sellers — instant estimates from the sliders.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        href="#calculator"
                        class="inline-flex rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-emerald-400"
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
                        href="/return-loss-calculator"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10"
                    >
                        বাংলা ভার্সন
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-b border-white/10 bg-emerald-950/15 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-emerald-200 sm:text-2xl">Quick answer</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                    Use daily orders, return/cancel rate, and average cost per return to estimate monthly COD return loss and potential savings.
                    Numbers are educational. If loss looks high, enable
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">fraud checks</Link>
                    and
                    <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake-order protection</Link>
                    first.
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

        <RoiCalculatorSection
            :config="roiCalculator"
            :scenarios="roiScenarios"
            :primary-cta-url="ctaUrl"
            :primary-cta-label="ctaLabel"
            :show-intro="false"
            locale="en"
        />

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
                        <template v-if="section.heading.startsWith('How to cut') && idx === 0">
                            First
                            <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>,
                            then
                            <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">fake order protection</Link>,
                            then ship safe orders with
                            <Link href="/en/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">courier auto-entry</Link>.
                        </template>
                        <template v-else-if="section.heading.startsWith('How to cut') && idx === 1">
                            Also measure fake purchases on ads —
                            <Link href="/en/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Ads ROAS calculator</Link>
                            compares reported vs real ROAS.
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="true" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">How the math works</h2>
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

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">How to cut return loss</h2>
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
                    The WooEasyLife return loss calculator estimates monthly COD return loss and savings from daily orders, return rate, and cost per return.
                    Educational only. Cut loss with
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">fraud checks</Link>
                    and
                    <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">protection</Link>.
                    Bangla:
                    <Link href="/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Bangla version</Link>.
                    Start:
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_en_return_loss_calculator"
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
