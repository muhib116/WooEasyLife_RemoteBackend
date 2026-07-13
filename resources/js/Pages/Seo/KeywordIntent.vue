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
    showChecker: { type: Boolean, default: true },
    steps: { type: Array, default: () => [] },
    headline: { type: String, default: '' },
    lead: { type: String, default: '' },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());
const h1 = computed(() => props.headline || props.seo?.prerender_h1 || 'WooEasyLife');
const leadText = computed(() => props.lead || props.seo?.prerender_lead || '');
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="fraud-check">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl">
                    {{ h1 }}
                </h1>
                <p class="mt-4 text-base text-slate-300 sm:text-lg">{{ leadText }}</p>
            </div>
        </section>

        <section v-if="steps.length" class="px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white">ধাপে ধাপে</h2>
                <ol class="mt-6 space-y-4 text-sm text-slate-300 sm:text-base">
                    <li v-for="(step, i) in steps" :key="i">
                        <span class="font-semibold text-amber-300">{{ i + 1 }}.</span> {{ step }}
                    </li>
                </ol>
            </div>
        </section>

        <section v-if="showChecker" id="fraud-check" class="scroll-mt-24 px-4 pb-12 pt-12 sm:pt-14 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="mb-6 text-center">
                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                        ফ্রি টুল — এখনই চেক করুন
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white">মোবাইল নম্বর দিন — হিস্টোরি ও রেশিও দেখুন</h2>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

        <section class="border-t border-white/10 px-4 py-10 lg:px-8">
            <div class="mx-auto flex max-w-3xl flex-wrap gap-3">
                <Link :href="ctaUrl" class="rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400">
                    {{ ctaLabel }}
                </Link>
                <Link href="/bd-fraud-checker" class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                    Courier Fraud Checker BD
                </Link>
                <Link href="/ki-vabe-fake-order-atkabo" class="rounded-xl px-4 py-3 text-sm text-amber-400 hover:text-amber-300">
                    কিভাবে ফেক অর্ডার আটকাবো?
                </Link>
            </div>
            <p class="mx-auto mt-4 max-w-3xl text-sm text-slate-500">
                টুল-শুধু নয় — WooEasyLife-এ OTP, ব্লক, কুরিয়ার অটো এন্ট্রি ও WooCommerce অ্যাপ একসাথে।
            </p>
        </section>

        <section v-if="faqs.length" class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-3">
                <h2 class="text-center text-2xl font-bold text-white">যা জানতে চান</h2>
                <div
                    v-for="(item, i) in faqs"
                    :key="item.q"
                    class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white"
                        @click="openFaq = openFaq === i ? null : i"
                    >
                        <span>{{ item.q }}</span>
                        <span class="text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                    </button>
                    <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-3 text-sm text-slate-300">
                        {{ item.a }}
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
