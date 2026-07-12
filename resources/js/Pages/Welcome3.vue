<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import LandingHeroSection from '@/components/marketing/LandingHeroSection.vue';
import CourierTrustStrip from '@/components/marketing/CourierTrustStrip.vue';
import CourierPerformanceSection from '@/components/marketing/CourierPerformanceSection.vue';
import RoiCalculatorSection from '@/components/marketing/RoiCalculatorSection.vue';
import LossComparisonSection from '@/components/marketing/LossComparisonSection.vue';
import HowItWorksSection from '@/components/marketing/HowItWorksSection.vue';
import FeatureShowcaseSection from '@/components/marketing/FeatureShowcaseSection.vue';
import AppShowcaseSection from '@/components/marketing/AppShowcaseSection.vue';
import IntegrationsSection from '@/components/marketing/IntegrationsSection.vue';
import ContactSupportSection from '@/components/marketing/ContactSupportSection.vue';
import EnterpriseCtaSection from '@/components/marketing/EnterpriseCtaSection.vue';
import FraudBenefitGrid from '@/components/marketing/FraudBenefitGrid.vue';
import ScrollReveal from '@/components/marketing/ScrollReveal.vue';
import PlanFeatureList from '@/components/marketing/PlanFeatureList.vue';
import { primaryCtaLabel, primaryCtaUrl, merchantLoginHref, merchantLoginLabel } from '@/utils/marketingCta';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    plans: { type: Array, default: () => [] },
    featuredPlan: { type: Object, default: null },
    hero: { type: Object, default: () => ({}) },
    heroBullets: { type: Array, default: () => [] },
    heroTrustBadges: { type: Array, default: () => [] },
    integrations: { type: Object, default: () => ({}) },
    roiScenarios: { type: Array, default: () => [] },
    roiCalculator: { type: Object, default: () => ({}) },
    howItWorks: { type: Array, default: () => [] },
    appShowcase: { type: Object, default: () => ({}) },
    featureShowcases: { type: Array, default: () => [] },
    fraudBenefitCards: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    courierPerformance: { type: Object, default: () => ({}) },
    lossComparison: { type: Object, default: () => ({}) },
    paymentMethods: { type: Array, default: () => [] },
    whatsappUrl: { type: String, default: null },
    whatsappContactUrl: { type: String, default: null },
    enterpriseCta: { type: Object, default: () => ({}) },
    appDownloadUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
});

const openFaq = ref(null);

const trialPlan = computed(() =>
    props.plans.find((p) => p.package_duration === 'free_trial') ?? null,
);

const previewPlans = computed(() => {
    const trial = props.plans.find((p) => p.package_duration === 'free_trial');
    const paid = props.plans.filter((p) => p.package_duration !== 'free_trial').slice(0, trial ? 2 : 3);

    return trial ? [trial, ...paid] : paid;
});

const primaryCtaUrlValue = computed(() => primaryCtaUrl());
const primaryCtaLabelValue = computed(() => primaryCtaLabel());
const page = usePage();
const merchantLoginLink = computed(() => merchantLoginHref(page.props.auth));
const merchantLoginText = computed(() => merchantLoginLabel(page.props.auth));

const pricingHook = computed(() => {
    const featured = props.featuredPlan;

    if (!featured?.price_label) {
        return null;
    }

    return `${featured.title ?? 'প্ল্যান'} ${featured.price_label}/মাস — এক দিনের রিটার্ন লস থেকেই সাশ্রয়`;
});

const faqs = computed(() => [
    {
        q: 'কাদের জন্য WooEasyLife?',
        a: 'বাংলাদেশে WooCommerce ওয়েবসাইট দিয়ে অনলাইন ব্যবসা চালানোদের জন্য — যারা ফেক অর্ডার কমাতে, কুরিয়ার সহজ করতে ও সময় বাঁচাতে চান।',
    },
    {
        q: 'ফ্রি ট্রায়াল কীভাবে পাব?',
        a: trialPlan.value
            ? `${trialPlan.value.title} — ${trialPlan.value.duration_label}। কোনো কার্ড লাগবে না। প্রাইসিং পেজ থেকে শুরু করুন।`
            : 'প্রাইসিং পেজ দেখুন বা হোয়াটসঅ্যাপে যোগাযোগ করুন।',
    },
    {
        q: 'ফ্রি ফ্রড চেক কীভাবে কাজ করে?',
        a: `এই পেজেই অ্যাকাউন্ট ছাড়া প্রতিদিন ${props.fraudCheck?.daily_free_limit ?? 5}টি ফ্রি চেক করতে পারবেন। নম্বর দিলে কুরিয়ার ডেলিভারি রেকর্ড দেখা যায় — অর্ডার পাঠানোর আগেই বুঝে নিন কাস্টমার বিশ্বস্ত না ঝুঁকিপূর্ণ।`,
    },
    {
        q: 'হারানো অর্ডার কীভাবে ফেরাব?',
        a: 'কাস্টমার কার্টে প্রোডাক্ট রেখে বা অর্ডার শেষ না করলে সেটি আলাদা লিস্টে জমা হয়। কল বা ফ্রড চেক করে এক ক্লিকে অর্ডার বানিয়ে বিক্রি ফিরিয়ে আনতে পারবেন।',
    },
    {
        q: 'একাধিক ওয়েবসাইট চালানো যাবে?',
        a: 'হ্যাঁ। প্ল্যান অনুযায়ী ২টি, ৩টি বা আনলিমিটেড ওয়েবসাইট — সব এক ড্যাশবোর্ড ও মোবাইল অ্যাপে।',
    },
    {
        q: 'স্টাফের কাজ কীভাবে দেখব?',
        a: 'স্টাফ যোগ করুন, অর্ডার অ্যাসাইন করুন। অ্যাপ দিয়ে কল করলে কতক্ষণ কথা হয়েছিল সেভ হয় — ক্যানসেল হলে কারণ বোঝা সহজ।',
    },
    {
        q: 'পার্সেল স্টিকার প্রিন্ট করা যায়?',
        a: 'হ্যাঁ। নাম-ঠিকানা-ফোন সহ স্টিকার ও ইনভয়েস এক ক্লিকে প্রিন্ট — প্যাকিং দ্রুত, ভুল কমে।',
    },
    {
        q: 'কোন কুরিয়ার কাজ করে?',
        a: 'Pathao, Steadfast, RedX সহ একাধিক কুরিয়ার — এক জায়গা থেকে এন্ট্রি ও আপডেট।',
    },
    {
        q: 'ফেক অর্ডার কি Facebook অ্যাড নষ্ট করে?',
        a: 'হ্যাঁ। সাধারণ সেটআপে ফেক/ক্যানসেল অর্ডারও «বিক্রি» হিসেবে গোনা হয়, তাই Facebook ভুল মানুষের কাছে অ্যাড দেখায়। WooEasyLife শুধু কনফার্মড অর্ডারকেই বিক্রি ধরে — অ্যাড বাজেট বাঁচে।',
    },
    {
        q: 'সমস্যায় পড়লে সাহায্য পাব কীভাবে?',
        a: 'প্রতিটি ফিচার পেজে ভিডিও গাইড আছে। আটকে গেলে হোয়াটসঅ্যাপে সরাসরি সাহায্য পাবেন।',
    },
    {
        q: 'পেমেন্ট কীভাবে করব?',
        a: 'bKash, Nagad, Rocket বা ব্যাংক — পেমেন্ট জমা দিলে দ্রুত চালু হয়ে যায়।',
    },
]);

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};
</script>

<template>
    <Head title="WooEasyLife — ফেক অর্ডার আটকান, কুরিয়ার সহজ করুন, লাভ বাড়ান" />

    <MarketingLayout
        :can-login="canLogin"
        :whatsapp-url="whatsappUrl"
        active-nav="home"
        suppress-mobile-whatsapp-fab
        class="pb-20 md:pb-0"
    >
        <!-- 1. Hero -->
        <LandingHeroSection
            :hero="hero"
            :hero-bullets="heroBullets"
            :hero-trust-badges="heroTrustBadges"
            :trial-plan="trialPlan"
            :primary-cta-url="primaryCtaUrlValue"
            :primary-cta-label="primaryCtaLabelValue"
            :fraud-check-enabled="fraudCheck?.enabled !== false"
        />

        <!-- 2. Free fraud check (try before buy) -->
        <ScrollReveal as="section" id="fraud-check" class="scroll-mt-24 px-4 pb-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="mb-6 text-center sm:mb-8">
                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                        ফ্রি টুল — এখনই চেষ্টা করুন
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                        নম্বর দিন — কাস্টমার কেমন, সাথে সাথে জানুন
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400 sm:text-base">
                        অ্যাকাউন্ট ছাড়াই কুরিয়ার ডেলিভারি রেকর্ড দেখুন। অর্ডার পাঠানোর আগেই সিদ্ধান্ত নিন।
                    </p>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </ScrollReveal>

        <!-- 3. Courier trust + performance proof -->
        <ScrollReveal :delay="80">
            <CourierTrustStrip />
        </ScrollReveal>

        <ScrollReveal :delay="100">
            <CourierPerformanceSection :section="courierPerformance" />
        </ScrollReveal>

        <!-- 4. What you get from fraud check -->
        <ScrollReveal :delay="80">
            <FraudBenefitGrid :section="fraudBenefitCards" />
        </ScrollReveal>

        <!-- 5. ROI calculator — money-first for BD merchants -->
        <ScrollReveal :delay="60">
            <RoiCalculatorSection
                :config="roiCalculator"
                :scenarios="roiScenarios"
                :primary-cta-url="primaryCtaUrlValue"
                :primary-cta-label="primaryCtaLabelValue"
            />
        </ScrollReveal>

        <!-- 6. Pain vs solution (with numbers) -->
        <ScrollReveal :delay="80">
            <LossComparisonSection
                :loss-comparison="lossComparison"
                :primary-cta-url="primaryCtaUrlValue"
                :primary-cta-label="primaryCtaLabelValue"
                :fraud-check-enabled="fraudCheck?.enabled !== false"
            />
        </ScrollReveal>

        <!-- 7. Outcome stats -->
        <ScrollReveal as="section" v-if="stats.length" class="border-y border-white/10 bg-[#111111] py-10 sm:py-12">
            <div class="mx-auto grid max-w-6xl grid-cols-2 gap-4 px-4 sm:grid-cols-4 sm:gap-6 lg:px-8">
                <div v-for="stat in stats" :key="stat.label" class="rounded-xl border border-white/10 bg-white/5 p-4 text-center sm:p-5">
                    <p class="text-2xl font-extrabold text-white sm:text-3xl">{{ stat.value }}</p>
                    <p class="mt-1 text-xs text-slate-400 sm:text-sm">{{ stat.label }}</p>
                </div>
            </div>
        </ScrollReveal>

        <!-- 8. How it works -->
        <ScrollReveal :delay="80">
            <HowItWorksSection :steps="howItWorks" />
        </ScrollReveal>

        <!-- 9. Feature deep-dives (accordion) -->
        <ScrollReveal as="section" id="features" class="scroll-mt-24">
            <FeatureShowcaseSection :showcases="featureShowcases" />
        </ScrollReveal>

        <!-- 10. Integrations -->
        <ScrollReveal :delay="80">
            <IntegrationsSection :section="integrations" />
        </ScrollReveal>

        <!-- 11. Pricing preview -->
        <ScrollReveal as="section" id="pricing" class="scroll-mt-24 py-14 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl">আপনার ব্যবসার জন্য সঠিক প্ল্যান</h2>
                    <p class="mt-3 text-sm text-slate-400 sm:text-base">স্বচ্ছ মূল্য — কোনো হিডেন চার্জ নেই</p>
                    <p v-if="pricingHook" class="mx-auto mt-2 max-w-lg text-sm font-medium text-emerald-400">
                        {{ pricingHook }}
                    </p>
                </div>

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                    <article
                        v-for="plan in previewPlans"
                        :key="plan.id"
                        class="relative flex flex-col rounded-2xl border p-5 sm:p-6"
                        :class="[
                            plan.package_duration === 'free_trial'
                                ? 'border-amber-400/40 bg-amber-500/10'
                                : plan.is_special
                                    ? 'border-amber-400/50 bg-amber-500/10 shadow-xl shadow-amber-900/30'
                                    : 'border-white/10 bg-white/5',
                        ]"
                    >
                        <span
                            v-if="plan.badge_label"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full px-3 py-0.5 text-xs font-bold"
                            :class="plan.package_duration === 'free_trial'
                                ? 'bg-emerald-400 text-emerald-950'
                                : 'bg-amber-400 text-amber-950'"
                        >
                            {{ plan.badge_label }}
                        </span>
                        <p class="text-sm text-slate-400">{{ plan.duration_label }}</p>
                        <h3 class="mt-1 text-lg font-bold text-white sm:text-xl">{{ plan.title }}</h3>
                        <p class="mt-2 text-3xl font-extrabold text-white sm:text-4xl">{{ plan.price_label }}</p>
                        <p class="text-sm text-slate-400">{{ plan.token_label }}</p>
                        <p v-if="plan.website_label" class="mt-1 text-xs text-slate-500">{{ plan.website_label }}</p>
                        <PlanFeatureList :plan="plan" />
                        <Link
                            :href="route('pricing')"
                            class="mt-6 block rounded-xl py-3 text-center text-sm font-bold transition"
                            :class="plan.package_duration === 'free_trial'
                                ? 'bg-amber-500 text-black hover:bg-amber-400'
                                : plan.is_special
                                    ? 'bg-amber-500 text-black hover:bg-amber-400'
                                    : 'border border-white/15 text-white hover:bg-white/10'"
                        >
                            {{ plan.package_duration === 'free_trial' ? 'ফ্রি ট্রায়াল শুরু করুন' : 'এই প্ল্যান কিনুন' }}
                        </Link>
                    </article>
                </div>

                <p v-if="paymentMethods.length" class="mt-6 text-center text-xs text-slate-500">
                    পেমেন্ট: {{ paymentMethods.join(' · ') }}
                </p>

                <div class="mt-8 text-center">
                    <Link :href="route('pricing')" class="text-sm font-semibold text-amber-400 hover:text-amber-300">
                        সব প্যাকেজ দেখুন →
                    </Link>
                </div>
            </div>
        </ScrollReveal>

        <!-- 12. Enterprise -->
        <ScrollReveal :delay="60">
            <EnterpriseCtaSection :cta="enterpriseCta" :contact-url="whatsappContactUrl" />
        </ScrollReveal>

        <!-- 13. Free trial banner -->
        <ScrollReveal v-if="trialPlan" :delay="80">
            <section class="px-4 py-10 sm:py-14">
            <div class="mx-auto max-w-4xl">
                <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-amber-600 via-yellow-500 to-amber-800 p-6 text-center shadow-2xl sm:p-10">
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">আজই শুরু করুন বিনামূল্যে!</h2>
                    <p class="mx-auto mt-3 max-w-lg text-sm text-amber-50 sm:text-base">
                        {{ trialPlan.title }} — {{ trialPlan.duration_label }}।
                        নিজে ব্যবহার করে দেখুন, ভালো লাগলে প্ল্যান নিন।
                    </p>
                    <Link
                        :href="primaryCtaUrlValue"
                        class="mt-6 inline-flex rounded-xl bg-white px-8 py-3.5 text-sm font-bold text-amber-950 shadow-lg hover:bg-amber-50"
                    >
                        {{ primaryCtaLabelValue }}
                    </Link>
                </div>
            </div>
            </section>
        </ScrollReveal>

        <!-- 14. App showcase -->
        <ScrollReveal :delay="100">
            <AppShowcaseSection
                :app-showcase="appShowcase"
                :app-download-url="appDownloadUrl"
                :play-store-url="playStoreUrl"
            />
        </ScrollReveal>

        <!-- 15. FAQ -->
        <ScrollReveal as="section" id="faq" class="scroll-mt-24 border-t border-white/10 bg-[#111111] py-14 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 lg:px-8">
                <h2 class="text-center text-2xl font-bold text-white sm:text-3xl">যা জানতে চান</h2>
                <div class="mt-8 space-y-3 sm:mt-10">
                    <div
                        v-for="(item, i) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            :id="`faq-button-${i}`"
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 sm:px-5"
                            :aria-expanded="openFaq === i"
                            :aria-controls="`faq-panel-${i}`"
                            @click="toggleFaq(i)"
                        >
                            <span>{{ item.q }}</span>
                            <span class="shrink-0 text-slate-400" aria-hidden="true">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <div
                            v-show="openFaq === i"
                            :id="`faq-panel-${i}`"
                            role="region"
                            :aria-labelledby="`faq-button-${i}`"
                            class="border-t border-white/10 px-4 py-4 text-sm leading-relaxed text-slate-400 sm:px-5"
                        >
                            {{ item.a }}
                        </div>
                    </div>
                </div>
            </div>
        </ScrollReveal>

        <!-- Contact support -->
        <ScrollReveal :delay="80">
            <ContactSupportSection :whatsapp-contact-url="whatsappContactUrl" />
        </ScrollReveal>

        <!-- 16. Final CTA -->
        <ScrollReveal :delay="100">
            <section class="px-4 py-12 sm:py-16">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">
                    আজই সিদ্ধান্ত নিন — কাল থেকেই যে টাকা ও সময় যাচ্ছে, বাঁচান
                </h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">
                    আগে ফ্রি ফ্রড চেক করুন — ভালো লাগলে প্ল্যান নিন
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a
                        href="#fraud-check"
                        class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-8 py-3.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ফ্রি ফ্রড চেক করুন
                    </a>
                    <Link
                        v-if="canLogin"
                        :href="merchantLoginLink"
                        class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-8 py-3.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        {{ merchantLoginText }}
                    </Link>
                    <Link
                        :href="primaryCtaUrlValue"
                        class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-8 py-3.5 text-sm font-bold text-black shadow-xl shadow-amber-900/50 hover:bg-amber-400"
                    >
                        {{ primaryCtaLabelValue }}
                    </Link>
                </div>
            </div>
            </section>
        </ScrollReveal>

        <!-- Mobile sticky CTA -->
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#0a0a0a]/95 p-3 backdrop-blur-md md:hidden">
            <Link
                :href="primaryCtaUrlValue"
                class="flex w-full items-center justify-center rounded-xl bg-amber-500 py-3 text-sm font-bold text-black"
            >
                {{ primaryCtaLabelValue }}
            </Link>
        </div>
    </MarketingLayout>
</template>
