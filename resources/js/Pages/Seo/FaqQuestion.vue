<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import SeoContentSections from '@/components/marketing/SeoContentSections.vue';
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
const h1 = computed(() => props.seo?.prerender_h1 || 'FAQ');
const leadText = computed(() => props.seo?.prerender_lead || '');
const contentSections = computed(() => props.seo?.content_sections || []);
const related = computed(() => props.seo?.cluster_links || []);
const toolHref = computed(() => props.seo?.pillar_path || '/bd-fraud-checker');
</script>

<template>
    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="faq" suppress-seo-content-sections>
        <SeoHead :seo="seo" />

        <section class="border-b border-white/10 bg-[#0a0a0a] px-4 pb-10 pt-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-amber-400/90">
                    <Link href="/faq" class="hover:text-amber-300">FAQ</Link>
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ h1 }}</h1>
                <p v-if="leadText" class="mt-3 text-base leading-relaxed text-slate-400 sm:text-lg">{{ leadText }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Link
                        :href="toolHref"
                        class="inline-flex rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        টুল খুলুন
                    </Link>
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_faq_question"
                        link-class="inline-flex rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    />
                </div>
            </div>
        </section>

        <SeoContentSections :sections="contentSections" />

        <section v-if="related.length" class="border-t border-white/10 bg-[#111111] px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white">সম্পর্কিত লিংক</h2>
                <ul class="mt-4 flex flex-wrap gap-2">
                    <li v-for="item in related" :key="item.path">
                        <Link
                            :href="item.path"
                            class="inline-flex rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/10"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="faqs.length" class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-2xl font-bold text-white">সম্পর্কিত প্রশ্ন</h2>
                <div class="mt-6 space-y-2">
                    <div
                        v-for="(item, index) in faqs"
                        :key="index"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-white"
                            @click="openFaq = openFaq === index ? null : index"
                        >
                            <span>{{ item.q }}</span>
                            <span class="text-slate-500">{{ openFaq === index ? '−' : '+' }}</span>
                        </button>
                        <div v-show="openFaq === index" class="border-t border-white/10 px-4 py-3 text-sm leading-relaxed text-slate-400">
                            <LinkedRichText :text="item.a" />
                        </div>
                    </div>
                </div>
                <p class="mt-6 text-sm text-slate-500">
                    <Link href="/faq" class="font-semibold text-amber-400 hover:text-amber-300">← সব FAQ</Link>
                </p>
            </div>
        </section>
    </MarketingLayout>
</template>
