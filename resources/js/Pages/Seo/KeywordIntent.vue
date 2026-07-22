<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import SeoContentSections from '@/components/marketing/SeoContentSections.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

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
const contentSections = computed(() => props.seo?.content_sections || []);
const isEn = computed(() => String(props.seo?.html_lang || '').startsWith('en'));

const copy = computed(() => {
    const path = String(props.seo?.canonical_path || '');
    if (isEn.value) {
        return {
            stepsTitle: 'Step by step',
            toolBadge: 'Free tool — check now',
            toolHeading: 'Enter a mobile number — see history & ratio',
            fraudChecker: 'Courier Fraud Checker BD',
            protection: 'Fake Order Protection',
            fakeCustomer: 'Fake Customer Check',
            home: 'English home',
            howLink: path.includes('ki-vabe-fake-order-atkabo')
                ? 'বাংলা ভার্সন'
                : 'How to stop fake orders (BN)',
            howHref: '/ki-vabe-fake-order-atkabo',
            footerNote: 'Not tool-only — WooEasyLife combines OTP, blocks, courier auto entry, and the mobile app.',
            faqTitle: 'FAQs',
        };
    }

    return {
        stepsTitle: 'ধাপে ধাপে',
        toolBadge: 'ফ্রি টুল — এখনই চেক করুন',
        toolHeading: 'মোবাইল নম্বর দিন — হিস্টোরি ও রেশিও দেখুন',
        fraudChecker: 'Courier Fraud Checker BD',
        protection: 'Fake Order Protection',
        fakeCustomer: 'Fake Customer Check',
        home: 'হোম',
        howLink: path.includes('ki-vabe-fake-order-atkabo')
            ? 'English version'
            : 'কিভাবে ফেক অর্ডার আটকাবো?',
        howHref: path.includes('ki-vabe-fake-order-atkabo')
            ? '/en/ki-vabe-fake-order-atkabo'
            : '/ki-vabe-fake-order-atkabo',
        footerNote: 'টুল-শুধু নয় — WooEasyLife-এ OTP, ব্লক, কুরিয়ার অটো এন্ট্রি ও WooCommerce অ্যাপ একসাথে।',
        faqTitle: 'যা জানতে চান',
    };
});

const fraudCheckerHref = computed(() => (isEn.value ? '/en/bd-fraud-checker' : '/bd-fraud-checker'));
const protectionHref = computed(() => (isEn.value ? '/en/fake-order-protection' : '/fake-order-protection'));
const fakeCustomerHref = computed(() => (isEn.value ? '/en/fake-customer-check' : '/fake-customer-check'));
const homeHref = computed(() => (isEn.value ? '/en' : '/'));
const showFakeCustomerLink = computed(() => !String(props.seo?.canonical_path || '').includes('fake-customer-check'));
const showLocaleMirror = computed(() => {
    const path = String(props.seo?.canonical_path || '');
    return path.includes('fake-customer-check') || path.includes('ki-vabe-fake-order-atkabo');
});
const localeMirrorHref = computed(() => {
    const path = String(props.seo?.canonical_path || '');
    if (path.includes('fake-customer-check')) {
        return isEn.value ? '/fake-customer-check' : '/en/fake-customer-check';
    }
    return copy.value.howHref;
});
const localeMirrorLabel = computed(() => {
    const path = String(props.seo?.canonical_path || '');
    if (path.includes('fake-customer-check')) {
        return isEn.value ? 'বাংলা ভার্সন' : 'English version';
    }
    return copy.value.howLink;
});
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
                <h2 class="text-xl font-bold text-white">{{ copy.stepsTitle }}</h2>
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
                        {{ copy.toolBadge }}
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white">{{ copy.toolHeading }}</h2>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

        <SeoContentSections :sections="contentSections" />

        <section class="border-t border-white/10 px-4 py-10 lg:px-8">
            <div class="mx-auto flex max-w-3xl flex-wrap gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_keyword_intent"
                    link-class="rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link :href="fraudCheckerHref" class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                    {{ copy.fraudChecker }}
                </Link>
                <Link :href="protectionHref" class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                    {{ copy.protection }}
                </Link>
                <Link
                    v-if="showFakeCustomerLink"
                    :href="fakeCustomerHref"
                    class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    {{ copy.fakeCustomer }}
                </Link>
                <Link :href="homeHref" class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                    {{ copy.home }}
                </Link>
                <Link
                    v-if="showLocaleMirror"
                    :href="localeMirrorHref"
                    class="rounded-xl px-4 py-3 text-sm text-amber-400 hover:text-amber-300"
                >
                    {{ localeMirrorLabel }}
                </Link>
                <Link
                    v-else
                    :href="copy.howHref"
                    class="rounded-xl px-4 py-3 text-sm text-amber-400 hover:text-amber-300"
                >
                    {{ copy.howLink }}
                </Link>
            </div>
            <p class="mx-auto mt-4 max-w-3xl text-sm text-slate-500">
                {{ copy.footerNote }}
            </p>
        </section>

        <section v-if="faqs.length" class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-3">
                <h2 class="text-center text-2xl font-bold text-white">{{ copy.faqTitle }}</h2>
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
                    <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-4 text-sm leading-relaxed text-slate-300 sm:text-base sm:leading-7">
                        <LinkedRichText :text="item.a" :is-en="isEn" />
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
