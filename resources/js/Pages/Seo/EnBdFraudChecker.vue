<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());
</script>

<template>
    <SeoHead :seo="seo" />
    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="fraud-check">
        <section class="border-b border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                <h1 class="mt-2 text-3xl font-extrabold text-white sm:text-4xl">
                    Free Courier Fraud Checker BD — delivery history by phone
                </h1>
                <p class="mt-4 text-slate-300">
                    Enter a phone number to review Pathao, Steadfast, and RedX history and success rate before confirming COD orders.
                </p>
            </div>
        </section>
        <section class="px-4 pb-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <LandingFraudCheck :fraud-check="fraudCheck" />
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link :href="ctaUrl" class="rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black">{{ ctaLabel }}</Link>
                    <Link href="/bd-fraud-checker" class="rounded-xl px-4 py-3 text-sm text-slate-400 hover:text-amber-300">বাংলা ভার্সন</Link>
                </div>
            </div>
        </section>
        <section v-if="faqs.length" class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-3">
                <div v-for="(item, i) in faqs" :key="item.q" class="rounded-xl border border-white/10 bg-white/5">
                    <button type="button" class="flex w-full justify-between px-4 py-4 text-left text-sm font-semibold text-white" @click="openFaq = openFaq === i ? null : i">
                        <span>{{ item.q }}</span><span>{{ openFaq === i ? '−' : '+' }}</span>
                    </button>
                    <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-3 text-sm text-slate-300">{{ item.a }}</div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
