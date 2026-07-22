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
        title: 'Courier history check',
        body: 'Before confirming, check Pathao, Steadfast, and RedX success rate and return history by mobile number.',
    },
    {
        title: 'Checkout OTP',
        body: 'Verify the phone with OTP — fake or wrong numbers get blocked earlier. Keep form validation on too.',
    },
    {
        title: 'Duplicates & blacklists',
        body: 'Stop the same order from repeating. Block risky phones, emails, IPs, or devices.',
    },
    {
        title: 'Return loss & Ads ROAS',
        body: 'Fewer fake parcels cut return fees; fewer fake Pixel purchases keep ad optimization cleaner.',
    },
];

const steps = [
    'On new orders, check the mobile number with BD Fraud Checker.',
    'If risk looks high, call or hold; if low, continue.',
    'Keep checkout OTP, duplicate blocks, and blacklists on so the same pattern does not return.',
    'Confirm safe orders and send them via courier auto-entry — focus staff on packing and follow-up.',
];

const guideSections = [
    {
        heading: 'What multi-layer fake order protection means',
        paragraphs: [
            'Stopping fake COD orders is not a single tool. Start with Pathao, Steadfast, and RedX history by phone. Then turn on checkout OTP, form validation, duplicate blocks, and daily order limits.',
            'Wrong numbers and joke orders are common on COD and Facebook page sales. Once a parcel ships, return fees, packaging, and time hit the seller. WooEasyLife keeps fraud checks, checkout protection, and courier auto-entry in one platform.',
        ],
    },
    {
        heading: 'Layer by layer: check → OTP → block',
        paragraphs: [
            'Layer 1 — fraud/courier history: check the number before confirming. Low success rate or repeat returns → call or hold.',
            'Layer 2 — checkout OTP and form validation: stop fake/wrong numbers earlier. Incomplete addresses also drop.',
            'Layer 3 — duplicate blocks, blacklists, and daily limits: stop the same pattern from repeating.',
        ],
    },
    {
        heading: 'Practical workflow',
        paragraphs: [
            'New order → fraud check → call if needed → confirm → courier auto-entry. Following this flow cuts fake parcels and return loss.',
            'Manual checks alone are not enough long term — keep OTP and blacklists on. A Bangla step-by-step guide is also available.',
        ],
    },
    {
        heading: 'Fake orders, return loss, and Ads ROAS',
        paragraphs: [
            'Fake orders are not only courier loss — fake Pixel purchases also pollute ad optimization.',
            'Measure monthly return cost and real ROAS. Prefer a full platform over checker-only tools? Read the comparison page.',
        ],
    },
];

const layers = [
    {
        title: '1. Check',
        body: 'Review courier history and success rate by mobile number.',
        href: '/en/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
        tone: 'warn',
    },
    {
        title: '2. OTP & blocks',
        body: 'Turn on checkout OTP, duplicate blocks, and blacklists.',
        href: '/pricing',
        linkLabel: 'Start trial',
        tone: 'good',
    },
    {
        title: '3. Auto-entry',
        body: 'Confirm safe orders and push parcels to courier — less time and fewer typos.',
        href: '/en/courier-auto-entry',
        linkLabel: 'Courier auto-entry',
        tone: 'auto',
    },
];

const mistakeList = [
    'Shipping every COD order without a history check.',
    'Checking once, then leaving OTP/blacklists off.',
    'Shipping after a poor success rate (“maybe it will deliver”).',
    'Scaling ad budget from reported ROAS without measuring returns.',
    'Turning protection on but ignoring courier status/auto-entry.',
];

const whoFor = [
    'Facebook page sellers with high COD returns',
    'WooCommerce stores getting fake-number orders',
    'Agencies/teams verifying client orders',
    'Teams that want fewer fake Pixel purchases before scaling ads',
];

const relatedLinks = [
    { href: '/en/woocommerce-bangladesh', label: 'WooCommerce Bangladesh guide' },
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/ki-vabe-fake-order-atkabo', label: 'How to stop fake orders (BN)' },
    { href: '/en/return-loss-calculator', label: 'Return loss calculator' },
    { href: '/en/ads-roas-calculator', label: 'Ads ROAS calculator' },
    { href: '/en/courier-auto-entry', label: 'Courier auto-entry' },
    { href: '/en/fraudbd-alternative', label: 'FraudBD Alternative' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/fake-order-protection', label: 'বাংলা ভার্সন' },
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
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">Fake Order Protection</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'How to stop fake orders — Fake Order Protection' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Fraud check + OTP + blocks — multi-layer protection to cut fake COD orders and return loss.' }}
                </p>
                <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-400">
                    On COD and Facebook page sales, fake orders mean return fees and wasted ad budget — stop risk before you ship.
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_en_fake_order_protection_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/en/bd-fraud-checker"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        Free fraud check
                    </Link>
                    <Link
                        href="/fake-order-protection"
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
                    How to stop fake orders: first check courier history and success rate by mobile on
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>.
                    Then enable checkout OTP, duplicate order blocks, phone/device blacklists, and daily order limits.
                    Estimate monthly loss with the
                    <Link href="/en/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">return loss calculator</Link>.
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
                <h2 class="text-center text-2xl font-bold text-white">How to use it</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Four steps to safer COD operations — check, protect, confirm, then auto-entry.
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
                        <template v-if="section.heading.startsWith('Layer by layer') && idx === 0">
                            Layer 1 — fraud/courier history: check the number before confirming. Low success rate or repeat returns → call or hold. Free tool:
                            <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>.
                        </template>
                        <template v-else-if="section.heading.startsWith('Practical') && idx === 0">
                            New order →
                            <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">fraud check</Link>
                            → call if needed → confirm →
                            <Link href="/en/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">courier auto-entry</Link>.
                            Following this flow cuts fake parcels and return loss.
                        </template>
                        <template v-else-if="section.heading.startsWith('Practical') && idx === 1">
                            Manual checks alone are not enough long term — keep OTP and blacklists on. Bangla step guide:
                            <Link href="/ki-vabe-fake-order-atkabo" class="font-semibold text-amber-400 hover:text-amber-300">কিভাবে ফেক অর্ডার আটকাবো</Link>.
                        </template>
                        <template v-else-if="section.heading.startsWith('Fake orders, return') && idx === 0">
                            Fake orders are not only courier loss — fake Pixel purchases also pollute ad optimization. Compare reported vs real ROAS on the
                            <Link href="/en/ads-roas-calculator" class="font-semibold text-amber-400 hover:text-amber-300">Ads ROAS calculator</Link>.
                        </template>
                        <template v-else-if="section.heading.startsWith('Fake orders, return') && idx === 1">
                            Estimate monthly return cost with the
                            <Link href="/en/return-loss-calculator" class="font-semibold text-amber-400 hover:text-amber-300">return loss calculator</Link>.
                            Prefer a full platform over checker-only tools? Read
                            <Link href="/en/fraudbd-alternative" class="font-semibold text-amber-400 hover:text-amber-300">FraudBD Alternative</Link>.
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="true" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">3-layer protection flow</h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm text-slate-400">
                    Check + OTP/blocks + auto-entry — not just a tool, a full COD safety stack.
                </p>
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
                    <h2 class="text-2xl font-bold text-white">Who this page is for</h2>
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
                    WooEasyLife fake order protection is multi-layer: courier history checks, checkout OTP, duplicate blocks, and blacklists to cut COD fake orders and return loss.
                    Free checks on
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>;
                    full protection via
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">pricing</Link>.
                    Bangla guide:
                    <Link href="/ki-vabe-fake-order-atkabo" class="font-semibold text-amber-400 hover:text-amber-300">কিভাবে ফেক অর্ডার আটকাবো</Link>.
                    Bangla mirror:
                    <Link href="/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">Bangla version</Link>.
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_en_fake_order_protection"
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
