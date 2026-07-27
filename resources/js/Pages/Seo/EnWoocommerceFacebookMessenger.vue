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
        title: 'Page inbox in WP admin',
        body: 'Connect a Facebook Page and run Messenger chats inside WooEasyLife / WordPress admin — not a storefront chat bubble.',
    },
    {
        title: 'Text · image · voice',
        body: 'Replies, media, voice notes, and typing indicators — a human inbox for daily page sales.',
    },
    {
        title: 'Lead labels',
        body: 'new → nurture → converted (manual override) so the team sees which threads are hot.',
    },
    {
        title: 'Order-link path',
        body: 'Move from thread toward WooCommerce orders (Sales Agent path; admin approval default). AI Full is not unlocked for everyone.',
    },
];

const steps = [
    'Connect your Facebook Page from WooEasyLife → Messenger (one page).',
    'Review new messages; label threads (new / nurture / converted) as needed.',
    'Reply with text, image, or voice — run a fraud check before you confirm.',
    'On confirm, use courier auto-entry; keep OTP/blacklist on for repeats.',
];

const guideSections = [
    {
        heading: 'What WooCommerce Facebook Messenger inbox means',
        paragraphs: [
            'Bangladesh Facebook page COD sellers close most deals in Messenger. WooEasyLife brings that inbox into WordPress admin — not a ManyChat/Chatfuel-style storefront widget.',
            'One connected Page in v1. Tokens stay on the hub; connect/disconnect from the UI.',
        ],
    },
    {
        heading: 'Human inbox vs AI Semi',
        paragraphs: [
            'Shipped core: human reply, media, voice, typing, lead labels. AI Semi (suggestion drafts) is package-gated beta — do not claim AI Full or a separate “Meta AI Bot” product.',
            'Win angle for page sellers: inbox → order path, not a website chat bubble.',
        ],
    },
    {
        heading: 'COD workflow: message → check → confirm',
        paragraphs: [
            'After taking an order in Messenger, run the mobile on the fraud checker. Low risk → confirm → courier auto-entry. Keep Fake Order Protection on for repeats.',
            'Start from the page COD guide and Pricing trial.',
        ],
    },
    {
        heading: 'Who it is for / what it is not',
        paragraphs: [
            'Single-page COD sellers and small teams. Multi-page inbox or unlocked AI Full are not claims on this page.',
            'For courier return ops, use the SteadFast Return Hub.',
        ],
    },
];

const layers = [
    {
        title: '1. Inbox',
        body: 'Read and reply to Page messages in WP admin.',
        href: '/en/woocommerce-facebook-messenger',
        linkLabel: 'This page',
        tone: 'auto',
    },
    {
        title: '2. Fraud check',
        body: 'Review mobile history before you confirm.',
        href: '/en/bd-fraud-checker',
        linkLabel: 'BD Fraud Checker',
        tone: 'warn',
    },
    {
        title: '3. Booking',
        body: 'Safe confirm = courier auto-entry.',
        href: '/en/courier-auto-entry',
        linkLabel: 'Courier Auto Entry',
        tone: 'good',
    },
];

const mistakeList = [
    'Blind-confirming Messenger orders without a fraud check.',
    'Confusing storefront chat plugins with this admin inbox.',
    'Advertising AI Full / Meta AI Bot as live for everyone.',
    'Claiming multi-page inbox (v1 = one page).',
];

const whoFor = [
    'Facebook page COD sellers who take orders in Messenger',
    'WooCommerce stores that want inbox inside WP admin',
    'Teams tracking nurture with lead labels',
    'Sellers who want order-linked inbox, not a chat bubble',
];

const relatedLinks = [
    { href: '/en/facebook-page-cod-management', label: 'Facebook Page COD' },
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/en/fake-order-protection', label: 'Fake Order Protection' },
    { href: '/en/courier-auto-entry', label: 'Courier Auto Entry' },
    { href: '/en/steadfast-return-hub', label: 'SteadFast Return Hub' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/woocommerce-facebook-messenger', label: 'বাংলা ভার্সন' },
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
                    {{ seo?.prerender_h1 || 'WooCommerce Facebook Messenger inbox' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'Page Messenger chats in WordPress admin — lead labels, media replies, order path.' }}
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_en_woocommerce_facebook_messenger_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/en/bd-fraud-checker"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        Free fraud check
                    </Link>
                    <Link
                        href="/woocommerce-facebook-messenger"
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
                    Connect a Facebook Page in WooEasyLife and run Messenger in WP admin — text/image/voice replies and lead labels. Before confirm, use
                    <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>;
                    start on
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">Pricing</Link>.
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
                        <template v-if="section.heading.startsWith('COD workflow') && idx === 0">
                            After Messenger orders, run the mobile on
                            <Link href="/en/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">fraud checker</Link>.
                            Low risk → confirm →
                            <Link href="/en/courier-auto-entry" class="font-semibold text-amber-400 hover:text-amber-300">Courier Auto Entry</Link>.
                            Keep
                            <Link href="/en/fake-order-protection" class="font-semibold text-amber-400 hover:text-amber-300">Fake Order Protection</Link>
                            on for repeats.
                        </template>
                        <template v-else-if="section.heading.startsWith('COD workflow') && idx === 1">
                            Page COD guide:
                            <Link href="/en/facebook-page-cod-management" class="font-semibold text-amber-400 hover:text-amber-300">Facebook Page COD management</Link>.
                            Trial:
                            <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">Pricing</Link>.
                        </template>
                        <template v-else-if="section.heading.startsWith('Who it is for') && idx === 1">
                            Courier return ops:
                            <Link href="/en/steadfast-return-hub" class="font-semibold text-amber-400 hover:text-amber-300">SteadFast Return Hub</Link>.
                        </template>
                        <template v-else><LinkedRichText :text="paragraph" :is-en="true" /></template>
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-white/10 bg-[#0d0d0d] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-bold text-white">3-step sales flow</h2>
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
                    <h2 class="text-2xl font-bold text-white">Mistakes to avoid</h2>
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
                    WooEasyLife WooCommerce Facebook Messenger inbox: Page connect, human chat, lead labels, order path for BD page COD sellers. Trial
                    <Link href="/pricing" class="font-semibold text-amber-400 hover:text-amber-300">Pricing</Link>.
                    Bangla:
                    <Link href="/woocommerce-facebook-messenger" class="font-semibold text-amber-400 hover:text-amber-300">বাংলা ভার্সন</Link>.
                </p>
            </div>
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_en_woocommerce_facebook_messenger"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/en/facebook-page-cod-management"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Page COD guide
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
