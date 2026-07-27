<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaUrl } from '@/utils/marketingCta';
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
const ctaLabel = 'Start free trial';
const sections = computed(() => props.seo?.content_sections || []);

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const isQuickAnswer = (section) => {
    const h = String(section?.heading || '').toLowerCase();
    return h.includes('quick');
};

const pillars = [
    {
        title: 'Cancel requests in the plugin',
        body: 'SteadFast Cancellation Requests sync into Return Requests—Pending to Decide.',
    },
    {
        title: 'Confirm cancel or Ask to resend',
        body: 'One modal for the decision, with order link, note history, and Edit parcel.',
    },
    {
        title: 'Rider call + call log',
        body: 'See the assigned rider, tap Call, and keep history on the order.',
    },
    {
        title: 'Optional AI guidance',
        body: 'What to do next and what to tell the customer—Save the sale when an AI package is on.',
    },
];

const relatedLinks = [
    { href: '/en/steadfast-integration', label: 'Steadfast setup' },
    { href: '/en/courier-auto-entry', label: 'Courier Auto Entry' },
    { href: '/en/bd-fraud-checker', label: 'BD Fraud Checker' },
    { href: '/en/return-loss-calculator', label: 'Return Loss Calculator' },
    { href: '/pricing', label: 'Pricing' },
    { href: '/steadfast-return-hub', label: 'বাংলা' },
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
                        location="seo_en_steadfast_return_hub_hero"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                    <Link
                        href="/en/courier-auto-entry"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        Courier Auto Entry
                    </Link>
                    <Link
                        href="/steadfast-return-hub"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 sm:w-auto"
                    >
                        বাংলা
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
                        <LinkedRichText :text="paragraph" :is-en="true" />
                    </p>
                    <ol v-if="section.list?.length" class="mt-4 list-decimal space-y-2 pl-5 text-sm text-slate-300">
                        <li v-for="(item, li) in section.list" :key="li">
                            <LinkedRichText :text="item" :is-en="true" />
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
                <h2 class="text-center text-2xl font-bold text-white">FAQ</h2>
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
                            <LinkedRichText :text="item.a" :is-en="true" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-lg font-bold text-white">Related pages</h2>
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
                    location="seo_en_steadfast_return_hub"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/en/steadfast-integration"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Steadfast setup
                </Link>
                <Link
                    href="/pricing"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    Pricing
                </Link>
            </div>
        </section>
    </MarketingLayout>
</template>
