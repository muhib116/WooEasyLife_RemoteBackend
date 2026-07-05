<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import LandingHeroSection from '@/components/marketing/LandingHeroSection.vue';
import CourierTrustStrip from '@/components/marketing/CourierTrustStrip.vue';
import RoiSavingsSection from '@/components/marketing/RoiSavingsSection.vue';
import LossComparisonSection from '@/components/marketing/LossComparisonSection.vue';
import HowItWorksSection from '@/components/marketing/HowItWorksSection.vue';
import FeatureShowcaseSection from '@/components/marketing/FeatureShowcaseSection.vue';
import AppShowcaseSection from '@/components/marketing/AppShowcaseSection.vue';
import ContactSupportSection from '@/components/marketing/ContactSupportSection.vue';
import FraudBenefitGrid from '@/components/marketing/FraudBenefitGrid.vue';
import ValuePillarsSection from '@/components/marketing/ValuePillarsSection.vue';
import ConversionFeaturesSection from '@/components/marketing/ConversionFeaturesSection.vue';
import ScrollReveal from '@/components/marketing/ScrollReveal.vue';
import { primaryCtaLabel, primaryCtaUrl, merchantLoginHref, merchantLoginLabel } from '@/utils/marketingCta';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    plans: { type: Array, default: () => [] },
    featuredPlan: { type: Object, default: null },
    hero: { type: Object, default: () => ({}) },
    heroBullets: { type: Array, default: () => [] },
    roiScenarios: { type: Array, default: () => [] },
    howItWorks: { type: Array, default: () => [] },
    appShowcase: { type: Object, default: () => ({}) },
    featureShowcases: { type: Array, default: () => [] },
    valuePillars: { type: Array, default: () => [] },
    conversionFeatures: { type: Array, default: () => [] },
    fraudBenefitCards: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    lossComparison: { type: Object, default: () => ({}) },
    paymentMethods: { type: Array, default: () => [] },
    whatsappUrl: { type: String, default: null },
    whatsappContactUrl: { type: String, default: null },
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

    return `Pro Plus ${featured.price_label}/মাস — এক দিনের রিটার্ন লস থেকেই সাশ্রয়`;
});

const faqs = computed(() => [
    {
        q: 'কাদের জন্য WooEasyLife?',
        a: 'বাংলাদেশে WooCommerce ওয়েবসাইট দিয়ে এক বা একাধিক অনলাইন ব্যবসা চালানো মার্চেন্টদের জন্য — যারা ফ্রড কমাতে, সময় বাঁচাতে ও অর্ডার ম্যানেজমেন্ট সেন্ট্রালাইজ করতে চান।',
    },
    {
        q: 'ফ্রি ট্রায়াল কীভাবে পাব?',
        a: trialPlan.value
            ? `${trialPlan.value.title} — ${trialPlan.value.duration_label}, ${trialPlan.value.token_label}। প্রাইসিং পেজ থেকে শুরু করুন।`
            : 'প্রাইসিং পেজ দেখুন বা হোয়াটসঅ্যাপে যোগাযোগ করুন।',
    },
    {
        q: 'Free fraud check কীভাবে কাজ করে?',
        a: `Landing page-এ registration ছাড়াই প্রতিদিন ${props.fraudCheck?.daily_free_limit ?? 5}টি free search। Courier delivery history দেখে order confirm করার আগেই customer কেমন — জেনে নিন।`,
    },
    {
        q: 'মিসিং অর্ডার ফিচার কী?',
        a: 'Facebook বা হোয়াটসঅ্যাপে আসা অর্ডার WordPress-এ না থাকলে মিসিং অর্ডার খুঁজে ওয়ান ক্লিকে তৈরি করতে পারবেন — হারানো বিক্রি ফিরে আসে।',
    },
    {
        q: 'একাধিক ওয়েবসাইট ম্যানেজ করা যাবে?',
        a: 'হ্যাঁ। Growth প্ল্যানে ২টি, Pro Plus-এ ৩টি বা বার্ষিক প্ল্যানে আনলিমিটেড ওয়েবসাইট — সব এক ড্যাশবোর্ড ও মোবাইল অ্যাপে।',
    },
    {
        q: 'টিম ম্যানেজমেন্ট ও পারফরম্যান্স ট্র্যাকিং আছে?',
        a: 'হ্যাঁ। স্টাফ যোগ করুন, রোল ও ওয়েবসাইট অ্যাসাইন করুন, কল হিস্ট্রি ও অর্ডার সোর্স দেখে পারফরম্যান্স মাপুন।',
    },
    {
        q: 'কল হিস্ট্রি ও ক্যানসেল বিশ্লেষণ কীভাবে কাজ করে?',
        a: 'প্লাগইন/ড্যাশবোর্ডে কল হিস্ট্রি দেখুন। মোবাইল অ্যাপ দিয়ে কল করলে প্রতিটি কলের ডিউরেশন (সময়কাল) সেভ হয়। অর্ডারে স্টাফ অ্যাসাইন করলে কে প্রসেস করছে ট্র্যাক হয় — ক্যানসেল অর্ডারে কল + স্টাফ + সোর্স দেখে কেন ক্যানসেল হয়েছিল বোঝা যায়।',
    },
    {
        q: 'POS স্টিকার প্রিন্ট আছে?',
        a: 'হ্যাঁ। পার্সেল প্যাকিংয়ের জন্য POS স্টিকার ও ইনভয়েস ওয়ান ক্লিকে প্রিন্ট করতে পারবেন — প্যাকিং দ্রুত ও ভুল কমে।',
    },
    {
        q: 'কোন কুরিয়ার সাপোর্ট করে?',
        a: 'Steadfast, Pathao, RedX সহ একাধিক কুরিয়ার ইন্টিগ্রেশন — এক ড্যাশবোর্ড থেকে ম্যানেজ করুন।',
    },
    {
        q: 'পেমেন্ট কীভাবে করব?',
        a: 'bKash, Nagad, Rocket বা ব্যাংক ট্রান্সফার — পেমেন্ট জমা দিলে দ্রুত অ্যাক্টিভেশন।',
    },
]);

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};
</script>

<template>
    <Head title="WooEasyLife — ওয়েবসাইট অর্ডার ম্যানেজমেন্ট, ফ্রড চেক ও অটোমেশন" />

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
            :trial-plan="trialPlan"
            :primary-cta-url="primaryCtaUrlValue"
            :primary-cta-label="primaryCtaLabelValue"
            :fraud-check-enabled="fraudCheck?.enabled !== false"
        />

        <!-- 2. Free fraud check -->
        <ScrollReveal as="section" id="fraud-check" class="scroll-mt-24 px-4 pb-10 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="mb-6 text-center sm:mb-8">
                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                        ফ্রি টুল
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                        Free fraud check — এখনই try করুন
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400 sm:text-base">
                        Registration ছাড়াই courier delivery history দেখুন — order confirm করার আগেই customer কেমন জেনে নিন
                    </p>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </ScrollReveal>

        <!-- 3. Courier trust -->
        <ScrollReveal :delay="80">
            <CourierTrustStrip />
        </ScrollReveal>

        <!-- 4. Fraud benefit cards -->
        <ScrollReveal :delay="120">
            <FraudBenefitGrid :section="fraudBenefitCards" />
        </ScrollReveal>

        <!-- 5. Popular features grid -->
        <ScrollReveal :delay="160">
            <ConversionFeaturesSection :features="conversionFeatures" />
        </ScrollReveal>

        <!-- 6. ROI savings -->
        <ScrollReveal :delay="80">
            <RoiSavingsSection :scenarios="roiScenarios" />
        </ScrollReveal>

        <!-- 7. Pain vs solution -->
        <ScrollReveal :delay="120">
            <LossComparisonSection
            :loss-comparison="lossComparison"
            :primary-cta-url="primaryCtaUrlValue"
            :primary-cta-label="primaryCtaLabelValue"
            :fraud-check-enabled="fraudCheck?.enabled !== false"
            />
        </ScrollReveal>

        <!-- 8. Stats -->
        <ScrollReveal as="section" v-if="stats.length" class="border-y border-white/10 bg-[#111111] py-10 sm:py-12">
            <div class="mx-auto grid max-w-6xl grid-cols-2 gap-4 px-4 sm:grid-cols-4 sm:gap-6 lg:px-8">
                <div v-for="stat in stats" :key="stat.label" class="rounded-xl border border-white/10 bg-white/5 p-4 text-center sm:p-5">
                    <p class="text-2xl font-extrabold text-white sm:text-3xl">{{ stat.value }}</p>
                    <p class="mt-1 text-xs text-slate-400 sm:text-sm">{{ stat.label }}</p>
                </div>
            </div>
        </ScrollReveal>

        <!-- 9. Value pillars -->
        <ScrollReveal :delay="100">
            <ValuePillarsSection :pillars="valuePillars" />
        </ScrollReveal>

        <!-- 10. How it works -->
        <ScrollReveal :delay="80">
            <HowItWorksSection :steps="howItWorks" />
        </ScrollReveal>

        <!-- 11. Feature showcases -->
        <ScrollReveal :delay="120">
            <FeatureShowcaseSection :showcases="featureShowcases" />
        </ScrollReveal>

        <!-- 12. Pricing preview -->
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
                            v-if="plan.package_duration === 'free_trial'"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-amber-400 px-3 py-0.5 text-xs font-bold text-black"
                        >
                            শুরু করুন এখান থেকে
                        </span>
                        <span
                            v-else-if="plan.is_special"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-amber-400 px-3 py-0.5 text-xs font-bold text-amber-950"
                        >
                            সবচেয়ে জনপ্রিয়
                        </span>
                        <p class="text-sm text-slate-400">{{ plan.duration_label }}</p>
                        <h3 class="mt-1 text-lg font-bold text-white sm:text-xl">{{ plan.title }}</h3>
                        <p class="mt-2 text-3xl font-extrabold text-white sm:text-4xl">{{ plan.price_label }}</p>
                        <p class="text-sm text-slate-400">{{ plan.token_label }}</p>
                        <p v-if="plan.website_label" class="mt-1 text-xs text-slate-500">{{ plan.website_label }}</p>
                        <ul class="mt-5 flex-1 space-y-2">
                            <li
                                v-for="f in plan.top_features"
                                :key="f.key"
                                class="flex gap-2 text-sm text-slate-300"
                            >
                                <span class="shrink-0 text-emerald-400">✓</span>
                                <span>{{ f.label }}</span>
                            </li>
                        </ul>
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
                    Payment: {{ paymentMethods.join(' · ') }}
                </p>

                <div class="mt-8 text-center">
                    <Link :href="route('pricing')" class="text-sm font-semibold text-amber-400 hover:text-amber-300">
                        সব প্যাকেজ দেখুন →
                    </Link>
                </div>
            </div>
        </ScrollReveal>

        <!-- 13. Free trial CTA banner -->
        <ScrollReveal v-if="trialPlan" :delay="80">
            <section class="px-4 py-10 sm:py-14">
            <div class="mx-auto max-w-4xl">
                <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-amber-600 via-yellow-500 to-amber-800 p-6 text-center shadow-2xl sm:p-10">
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">আজই শুরু করুন বিনামূল্যে!</h2>
                    <p class="mx-auto mt-3 max-w-lg text-sm text-amber-50 sm:text-base">
                        {{ trialPlan.title }} — {{ trialPlan.duration_label }}, {{ trialPlan.token_label }}।
                        মূল ফিচার টেস্ট করে দেখুন, তারপর আপগ্রেড করুন।
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
                    Free fraud check দিয়ে শুরু করুন, তারপর plan বেছে নিন
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a
                        href="#fraud-check"
                        class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-8 py-3.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        Free fraud check করুন
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
