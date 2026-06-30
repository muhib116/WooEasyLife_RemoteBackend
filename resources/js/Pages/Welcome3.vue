<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
    stats: { type: Array, default: () => [] },
    lossComparison: { type: Object, default: () => ({}) },
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

const previewPlans = computed(() =>
    props.plans.filter((p) => p.package_duration !== 'free_trial').slice(0, 3),
);

const primaryCtaUrl = computed(() =>
    props.whatsappUrl || (props.canLogin ? route('login') : route('pricing')),
);
const primaryCtaLabel = computed(() =>
    props.whatsappUrl ? 'ফ্রি ট্রায়াল নিন — হোয়াটসঅ্যাপ' : 'ফ্রি ট্রায়াল শুরু করুন',
);
const primaryCtaExternal = computed(() => Boolean(props.whatsappUrl));

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
        q: 'ফ্রি ফ্রড চেক কীভাবে কাজ করে?',
        a: `ল্যান্ডিং পেজে রেজিস্ট্রেশন ছাড়াই প্রতিদিন ${props.fraudCheck?.daily_free_limit ?? 5}টি ফ্রি সার্চ করতে পারবেন। কুরিয়ার ডেলিভারি হিস্ট্রি দেখে ঝুঁকি যাচাই করুন।`,
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

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="home" class="pb-20 md:pb-0">
        <!-- 1. Hero -->
        <LandingHeroSection
            :hero="hero"
            :hero-bullets="heroBullets"
            :trial-plan="trialPlan"
            :primary-cta-url="primaryCtaUrl"
            :primary-cta-label="primaryCtaLabel"
            :primary-cta-external="primaryCtaExternal"
        />

        <!-- 2. Free fraud check -->
        <div id="fraud-check" class="relative mx-auto max-w-3xl scroll-mt-24 px-4 pb-10 lg:px-8">
            <LandingFraudCheck :fraud-check="fraudCheck" />
        </div>

        <!-- 3. Courier trust -->
        <CourierTrustStrip />

        <!-- 4. ROI savings -->
        <RoiSavingsSection :scenarios="roiScenarios" />

        <!-- 5. Pain vs solution -->
        <LossComparisonSection
            :loss-comparison="lossComparison"
            :primary-cta-url="primaryCtaUrl"
            :primary-cta-external="primaryCtaExternal"
            :fraud-check-enabled="fraudCheck?.enabled !== false"
        />

        <!-- 6. How it works -->
        <HowItWorksSection :steps="howItWorks" />

        <!-- 7. Feature showcases (pain → solution → benefit) -->
        <FeatureShowcaseSection :showcases="featureShowcases" />

        <!-- 8. Stats -->
        <section v-if="stats.length" class="border-y border-white/10 bg-[#0a0f1c] py-10 sm:py-12">
            <div class="mx-auto grid max-w-6xl grid-cols-2 gap-4 px-4 sm:grid-cols-4 sm:gap-6 lg:px-8">
                <div v-for="stat in stats" :key="stat.label" class="rounded-xl border border-white/10 bg-white/5 p-4 text-center sm:p-5">
                    <p class="text-2xl font-extrabold text-white sm:text-3xl">{{ stat.value }}</p>
                    <p class="mt-1 text-xs text-slate-400 sm:text-sm">{{ stat.label }}</p>
                </div>
            </div>
        </section>

        <!-- 9. Pricing preview -->
        <section id="pricing" class="scroll-mt-24 py-14 sm:py-20">
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
                        :class="plan.is_special
                            ? 'border-violet-400/50 bg-violet-600/10 shadow-xl shadow-violet-900/30'
                            : 'border-white/10 bg-white/5'"
                    >
                        <span
                            v-if="plan.is_special"
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
                            :class="plan.is_special
                                ? 'bg-violet-600 text-white hover:bg-violet-500'
                                : 'border border-white/15 text-white hover:bg-white/10'"
                        >
                            এই প্ল্যান কিনুন
                        </Link>
                    </article>
                </div>

                <div class="mt-8 text-center">
                    <Link :href="route('pricing')" class="text-sm font-semibold text-violet-400 hover:text-violet-300">
                        সব প্যাকেজ দেখুন →
                    </Link>
                </div>
            </div>
        </section>

        <!-- 10. Free trial CTA -->
        <section v-if="trialPlan" class="px-4 py-10 sm:py-14">
            <div class="mx-auto max-w-4xl">
                <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-fuchsia-600 to-violet-800 p-6 text-center shadow-2xl sm:p-10">
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">আজই শুরু করুন বিনামূল্যে!</h2>
                    <p class="mx-auto mt-3 max-w-lg text-sm text-violet-100 sm:text-base">
                        {{ trialPlan.title }} — {{ trialPlan.duration_label }}, {{ trialPlan.token_label }}।
                        মূল ফিচার টেস্ট করে দেখুন, তারপর আপগ্রেড করুন।
                    </p>
                    <a
                        :href="primaryCtaUrl"
                        :target="primaryCtaExternal ? '_blank' : undefined"
                        class="mt-6 inline-flex rounded-xl bg-white px-8 py-3.5 text-sm font-bold text-violet-700 shadow-lg hover:bg-violet-50"
                    >
                        {{ primaryCtaLabel }}
                    </a>
                </div>
            </div>
        </section>

        <!-- 11. App showcase -->
        <AppShowcaseSection
            :app-showcase="appShowcase"
            :app-download-url="appDownloadUrl"
            :play-store-url="playStoreUrl"
        />

        <!-- 12. FAQ -->
        <section id="faq" class="scroll-mt-24 border-t border-white/10 bg-[#0a0f1c] py-14 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 lg:px-8">
                <h2 class="text-center text-2xl font-bold text-white sm:text-3xl">যা জানতে চান</h2>
                <div class="mt-8 space-y-3 sm:mt-10">
                    <div
                        v-for="(item, i) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white sm:px-5"
                            @click="toggleFaq(i)"
                        >
                            <span>{{ item.q }}</span>
                            <span class="shrink-0 text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <p
                            v-show="openFaq === i"
                            class="border-t border-white/10 px-4 py-4 text-sm leading-relaxed text-slate-400 sm:px-5"
                        >
                            {{ item.a }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact support -->
        <ContactSupportSection :whatsapp-contact-url="whatsappContactUrl" />

        <!-- 13. Final CTA -->
        <section class="px-4 py-12 sm:py-16">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">
                    আজই সিদ্ধান্ত নিন — কাল থেকেই যে টাকা ও সময় যাচ্ছে, বাঁচান
                </h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">
                    ফ্রি ফ্রড চেক দিয়ে শুরু করুন, তারপর প্ল্যান বেছে নিন
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a
                        href="#fraud-check"
                        class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-8 py-3.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ফ্রি ফ্রড চেক করুন
                    </a>
                    <a
                        :href="primaryCtaUrl"
                        :target="primaryCtaExternal ? '_blank' : undefined"
                        class="inline-flex items-center justify-center rounded-xl bg-violet-600 px-8 py-3.5 text-sm font-bold text-white shadow-xl shadow-violet-900/50 hover:bg-violet-500"
                    >
                        {{ primaryCtaLabel }}
                    </a>
                </div>
            </div>
        </section>

        <!-- Mobile sticky CTA -->
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#070b16]/95 p-3 backdrop-blur-md md:hidden">
            <a
                :href="primaryCtaUrl"
                :target="primaryCtaExternal ? '_blank' : undefined"
                class="flex w-full items-center justify-center rounded-xl bg-violet-600 py-3 text-sm font-bold text-white"
            >
                {{ primaryCtaLabel }}
            </a>
        </div>
    </MarketingLayout>
</template>
