<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());
const sections = computed(() => props.seo?.content_sections || []);

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const isQuickAnswer = (section) => {
    const h = String(section?.heading || '').toLowerCase();
    return h.includes('দ্রুত') || h.includes('quick');
};

const pillars = [
    {
        title: 'Cancel request প্লাগইনেই',
        body: 'SteadFast Cancellation Requests সিঙ্ক হয় Return Requests-এ—Pending থেকে Decide।',
    },
    {
        title: 'Confirm cancel বা Ask to resend',
        body: 'এক মডালে সিদ্ধান্ত। অর্ডার লিংক, নোট হিস্ট্রি, Edit parcel একসাথে।',
    },
    {
        title: 'রাইডার কল + call log',
        body: 'Assigned rider দেখে Call করুন; history-তে কল লগ থাকে।',
    },
    {
        title: 'AI সাজেশন (অপশনাল)',
        body: 'কী করবেন আর কাস্টমারকে কী বলবেন—AI প্যাকেজ থাকলে Save the sale সহ।',
    },
];

const relatedLinks = [
    { href: '/steadfast-integration', label: 'Steadfast সেটআপ' },
    { href: '/courier-auto-entry', label: 'কুরিয়ার অটো এন্ট্রি' },
    { href: '/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/return-loss-calculator', label: 'রিটার্ন লস ক্যালকুলেটর' },
    { href: '/pricing', label: 'প্রাইসিং' },
    { href: '/en/steadfast-return-hub', label: 'English' },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout
        :can-login="canLogin"
        :whatsapp-url="whatsappUrl"
        active-nav="features"
        suppress-seo-content-sections
    >
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">STEADFAST RETURN HUB</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'SteadFast Return Request Hub' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead }}
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_steadfast_return_hub_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/courier-auto-entry"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        কুরিয়ার অটো এন্ট্রি
                    </Link>
                    <Link
                        href="/en/steadfast-return-hub"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 sm:w-auto"
                    >
                        English
                    </Link>
                </div>
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

        <!-- Same long-form as #seo-prerender (config/seo_content) — crawlers + humans share one source -->
        <section class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-10">
                <article
                    v-for="(section, si) in sections"
                    :key="si"
                    class="space-y-3"
                    :class="{ 'rounded-2xl border border-amber-500/20 bg-amber-950/15 p-5': isQuickAnswer(section) }"
                >
                    <h2
                        v-if="section.heading"
                        class="text-xl font-bold text-white sm:text-2xl"
                        :class="{ 'text-amber-200': isQuickAnswer(section) }"
                    >
                        {{ section.heading }}
                    </h2>
                    <p
                        v-for="(paragraph, idx) in section.paragraphs || []"
                        :key="idx"
                        class="text-sm leading-relaxed text-slate-300 sm:text-base"
                    >
                        <LinkedRichText :text="paragraph" :is-en="false" />
                    </p>
                    <ol v-if="section.list?.length" class="mt-4 list-decimal space-y-2 pl-5 text-sm text-slate-300">
                        <li v-for="(item, li) in section.list" :key="li">
                            <LinkedRichText :text="item" :is-en="false" />
                        </li>
                    </ol>
                    <figure
                        v-for="(figure, fi) in section.figures || []"
                        :key="`${si}-fig-${fi}`"
                        class="mt-4 overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <img
                            :src="figure.src"
                            :alt="figure.alt || section.heading || 'Diagram'"
                            class="h-auto w-full"
                            loading="lazy"
                            decoding="async"
                        />
                        <figcaption
                            v-if="figure.caption"
                            class="border-t border-white/10 px-3 py-2 text-xs text-slate-400 sm:text-sm"
                        >
                            {{ figure.caption }}
                        </figcaption>
                    </figure>
                </article>
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
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white sm:text-base"
                            @click="toggleFaq(i)"
                        >
                            <span>{{ item.q }}</span>
                            <span class="shrink-0 text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-4 text-base leading-relaxed text-slate-300 sm:text-[1.05rem] sm:leading-7">
                            <LinkedRichText :text="item.a" :is-en="false" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-lg font-bold text-white">সম্পর্কিত পেজ</h2>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
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
        </section>

        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_steadfast_return_hub"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/steadfast-integration"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Steadfast সেটআপ
                </Link>
                <Link
                    href="/pricing"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    প্রাইসিং
                </Link>
            </div>
        </section>
    </MarketingLayout>
</template>
