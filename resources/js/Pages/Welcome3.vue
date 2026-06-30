<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    plans: { type: Array, default: () => [] },
    featuredPlan: { type: Object, default: null },
    featureHighlights: { type: Array, default: () => [] },
    conversionFeatures: { type: Array, default: () => [] },
    heroBullets: { type: Array, default: () => [] },
    valuePillars: { type: Array, default: () => [] },
    featureGroups: { type: Array, default: () => [] },
    stats: { type: Array, default: () => [] },
    lossComparison: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    appDownloadUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
});

const openFaq = ref(null);

const aiPillar = computed(() => props.valuePillars.find((p) => p.id === 'ai') ?? null);
const multistorePillar = computed(() => props.valuePillars.find((p) => p.id === 'multistore') ?? null);

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

const integrations = [
    { name: 'Steadfast', logo: '/images/steadfast.svg' },
    { name: 'Pathao', logo: '/images/pathao.svg' },
    { name: 'RedX', logo: '/images/redx.svg' },
];

const steps = [
    { step: '০১', title: 'অ্যাকাউন্ট ও প্ল্যান', desc: 'প্রাইসিং পেজ থেকে প্ল্যান বেছে নিন বা হোয়াটসঅ্যাপে যোগাযোগ করুন।' },
    { step: '০২', title: 'প্লাগইন কানেক্ট', desc: 'WooCommerce সাইটে WooEasyLife প্লাগইন ইনস্টল করে লাইসেন্স অ্যাক্টিভ করুন।' },
    { step: '০৩', title: 'অটোমেশন চালু', desc: 'ফ্রড চেক, কুরিয়ার ও এসএমএস চালু করুন — লাভ বাড়তে শুরু করুন।' },
];

const faqs = computed(() => [
    { q: 'কাদের জন্য WooEasyLife?', a: 'বাংলাদেশে WooCommerce দিয়ে এক বা একাধিক স্টোর চালানো মার্চেন্টদের জন্য — যারা এআই অটোমেশন, ফ্রড কমাতে ও সময় বাঁচাতে চান।' },
    {
        q: 'ফ্রি ট্রায়াল কীভাবে পাব?',
        a: trialPlan.value
            ? `${trialPlan.value.title} — ${trialPlan.value.duration_label}, ${trialPlan.value.token_label}। প্রাইসিং পেজ থেকে শুরু করুন।`
            : 'প্রাইসিং পেজ দেখুন বা হোয়াটসঅ্যাপে যোগাযোগ করুন।',
    },
    { q: 'ফ্রি ফ্রড চেক কীভাবে কাজ করে?', a: `ল্যান্ডিং পেজে রেজিস্ট্রেশন ছাড়াই প্রতিদিন ${props.fraudCheck?.daily_free_limit ?? 5}টি ফ্রি সার্চ করতে পারবেন। কুরিয়ার ডেলিভারি হিস্ট্রি দেখে ঝুঁকি যাচাই করুন।` },
    { q: 'এআই ফিচার কী কাজে লাগে?', a: 'কাস্টমারের মেসেজ/ছবি থেকে অর্ডার তৈরি, অসম্পূর্ণ ঠিকানা পূরণ, কাস্টমার স্কোরিং — ম্যানুয়াল টাইপিং কমায়, ভুল কমায়।' },
    { q: 'কোন কুরিয়ার সাপোর্ট করে?', a: 'Steadfast, Pathao, RedX সহ একাধিক কুরিয়ার ইন্টিগ্রেশন — এক ড্যাশবোর্ড থেকে ম্যানেজ করুন।' },
    { q: 'পেমেন্ট কীভাবে করব?', a: 'bKash, Nagad, Rocket বা ব্যাংক ট্রান্সফার — পেমেন্ট জমা দিলে দ্রুত অ্যাক্টিভেশন।' },
]);

const featureColorClass = (color) => {
    const map = {
        violet: 'border-violet-500/30 bg-violet-500/10 text-violet-300',
        emerald: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        sky: 'border-sky-500/30 bg-sky-500/10 text-sky-300',
        amber: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        rose: 'border-rose-500/30 bg-rose-500/10 text-rose-300',
        cyan: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-300',
        fuchsia: 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-300',
        lime: 'border-lime-500/30 bg-lime-500/10 text-lime-300',
    };
    return map[color] ?? map.violet;
};

const pillarAccentClass = (accent) => {
    const map = {
        fuchsia: 'from-fuchsia-600/20 to-violet-600/10 border-fuchsia-500/30',
        sky: 'from-sky-600/20 to-cyan-600/10 border-sky-500/30',
        emerald: 'from-emerald-600/20 to-teal-600/10 border-emerald-500/30',
        amber: 'from-amber-600/20 to-orange-600/10 border-amber-500/30',
        violet: 'from-violet-600/20 to-purple-600/10 border-violet-500/30',
    };
    return map[accent] ?? map.violet;
};

const pillarBadgeClass = (accent) => {
    const map = {
        fuchsia: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-300',
        sky: 'border-sky-400/30 bg-sky-500/10 text-sky-300',
        emerald: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
        amber: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
        violet: 'border-violet-400/30 bg-violet-500/10 text-violet-300',
    };
    return map[accent] ?? map.violet;
};

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};
</script>

<template>
    <Head title="WooEasyLife — ফ্রড কমান, অর্ডার বাড়ান" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="home">
        <!-- Hero -->
        <section class="relative overflow-hidden pb-20 pt-14 sm:pt-20">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(124,58,237,0.18),_transparent_55%)]" />
            <div class="pointer-events-none absolute -right-32 top-20 h-96 w-96 rounded-full bg-violet-600/10 blur-3xl" />

            <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 lg:grid-cols-2 lg:px-8">
                <div>
                    <span class="mb-4 inline-flex rounded-full border border-violet-400/30 bg-violet-500/10 px-3 py-1 text-xs font-bold text-violet-300">
                        🤖 এআই + মাল্টি-স্টোর · বাংলাদেশের WooCommerce সলিউশন
                    </span>
                    <h1 class="text-4xl font-extrabold leading-[1.4] tracking-tight text-white sm:text-5xl sm:leading-[1.35] lg:text-[3.25rem] lg:leading-[1.3]">
                        একাধিক স্টোর, এআই অটোমেশন —
                        <span class="block bg-gradient-to-r from-violet-400 to-fuchsia-400 bg-clip-text pb-1 text-transparent">
                            সব আপনার আঙুলের ডগায়
                        </span>
                    </h1>
                    <p class="mt-5 text-lg leading-relaxed text-slate-300">
                        WooEasyLife দিয়ে টেক্সট/ছবি থেকে এআই অর্ডার, সব ওয়েবসাইট এক ড্যাশবোর্ডে,
                        ফ্রড চেক ও কুরিয়ার অটোমেশন — সময় নষ্ট না করে ব্যবসা বাড়ান।
                    </p>

                    <ul v-if="heroBullets.length" class="mt-6 space-y-2">
                        <li
                            v-for="item in heroBullets"
                            :key="item"
                            class="flex items-center gap-2 text-sm text-slate-200"
                        >
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">✓</span>
                            {{ item }}
                        </li>
                    </ul>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a
                            :href="primaryCtaUrl"
                            :target="primaryCtaExternal ? '_blank' : undefined"
                            :rel="primaryCtaExternal ? 'noopener noreferrer' : undefined"
                            class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-7 py-3.5 text-sm font-bold text-white shadow-xl shadow-violet-900/50 transition hover:bg-violet-500"
                        >
                            {{ primaryCtaLabel }}
                        </a>
                        <Link
                            :href="route('pricing')"
                            class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            প্যাকেজ ও মূল্য দেখুন
                        </Link>
                    </div>

                    <p v-if="trialPlan" class="mt-4 text-sm text-slate-400">
                        {{ trialPlan.title }} · {{ trialPlan.duration_label }} · {{ trialPlan.price_label }}
                    </p>
                </div>

                <!-- Dashboard mockup -->
                <div class="relative">
                    <div class="absolute -inset-4 rounded-3xl bg-violet-600/20 blur-2xl" />
                    <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-[#0f1729] p-5 shadow-2xl">
                        <div class="mb-4 flex gap-2">
                            <span class="rounded-lg bg-violet-600/30 px-3 py-1 text-xs font-bold text-violet-200">স্টোর ১</span>
                            <span class="rounded-lg bg-white/5 px-3 py-1 text-xs text-slate-400">স্টোর ২</span>
                            <span class="rounded-lg bg-white/5 px-3 py-1 text-xs text-slate-400">স্টোর ৩</span>
                        </div>
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img src="/app-logo" alt="" class="h-10 w-10 rounded-xl" />
                                <div>
                                    <p class="text-sm font-bold text-white">এআই অর্ডার + ট্রাস্ট স্কোর</p>
                                    <p class="text-xs text-slate-400">মেসেজ থেকে অর্ডার #4821</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-400">৮৮% নিরাপদ</span>
                        </div>
                        <div class="mb-4 h-2 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full w-[88%] rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400" />
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between rounded-xl bg-fuchsia-500/10 px-4 py-3">
                                <span class="text-sm text-slate-200">এআই টেক্সট অর্ডার</span>
                                <span class="text-xs font-bold text-fuchsia-400">তৈরি ✓</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3">
                                <span class="text-sm text-slate-200">ফ্রড চেক</span>
                                <span class="text-xs font-bold text-emerald-400">পাস ✓</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3">
                                <span class="text-sm text-slate-200">কুরিয়ার এন্ট্রি</span>
                                <span class="text-xs font-bold text-violet-400">অটো ✓</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-sky-500/10 px-4 py-3">
                                <span class="text-sm text-slate-200">মাল্টি-স্টোর অ্যালার্ট</span>
                                <span class="text-xs font-bold text-sky-400">লাইভ ✓</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative mx-auto mt-10 max-w-3xl px-4 lg:px-8">
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

        <!-- Courier trust -->
        <section class="border-y border-white/10 bg-[#0a0f1c] py-10">
            <div class="mx-auto max-w-6xl px-4 text-center lg:px-8">
                <p class="text-sm font-semibold text-slate-400">যে কুরিয়ার দিয়ে কাজ করেন — আমরা সাপোর্ট করি</p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4">
                    <div
                        v-for="item in integrations"
                        :key="item.name"
                        class="flex h-14 items-center justify-center rounded-xl border border-white/20 bg-white px-8 shadow-sm"
                    >
                        <img :src="item.logo" :alt="item.name" class="max-h-8 max-w-[6rem] object-contain" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Value pillars -->
        <section v-if="valuePillars.length" class="py-20">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-white sm:text-4xl">কেন মার্চেন্টরা WooEasyLife বেছে নেন</h2>
                    <p class="mt-3 text-slate-400">এআই, মাল্টি-স্টোর, ফ্রড প্রোটেকশন ও ফুল অটোমেশন — এক প্ল্যাটফর্মে</p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <article
                        v-for="pillar in valuePillars"
                        :key="pillar.id"
                        class="rounded-2xl border bg-gradient-to-br p-6"
                        :class="pillarAccentClass(pillar.accent)"
                    >
                        <span
                            class="inline-flex rounded-full border px-3 py-1 text-xs font-bold"
                            :class="pillarBadgeClass(pillar.accent)"
                        >
                            {{ pillar.badge }}
                        </span>
                        <h3 class="mt-4 text-xl font-bold text-white">{{ pillar.headline }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ pillar.subheadline }}</p>
                        <ul class="mt-5 space-y-2">
                            <li
                                v-for="feat in pillar.features.slice(0, 4)"
                                :key="feat.key"
                                class="flex items-start gap-2 text-sm text-slate-200"
                            >
                                <span class="mt-0.5 text-emerald-400">✓</span>
                                <span>
                                    <span class="font-semibold text-white">{{ feat.label }}</span>
                                    <span class="block text-xs text-slate-400">{{ feat.description }}</span>
                                </span>
                            </li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        <!-- AI spotlight -->
        <section v-if="aiPillar" class="border-y border-white/10 bg-[#0a0f1c] py-20">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 lg:grid-cols-2 lg:px-8">
                <div>
                    <span class="inline-flex rounded-full border border-fuchsia-400/30 bg-fuchsia-500/10 px-3 py-1 text-xs font-bold text-fuchsia-300">
                        {{ aiPillar.badge }}
                    </span>
                    <h2 class="mt-4 text-3xl font-bold text-white sm:text-4xl">{{ aiPillar.headline }}</h2>
                    <p class="mt-4 text-slate-400">{{ aiPillar.subheadline }}</p>
                    <ul class="mt-6 space-y-3">
                        <li
                            v-for="feat in aiPillar.features"
                            :key="feat.key"
                            class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-4"
                        >
                            <span class="text-fuchsia-400">✦</span>
                            <div>
                                <p class="font-semibold text-white">{{ feat.label }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ feat.description }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-fuchsia-500/20 bg-gradient-to-br from-fuchsia-950/40 to-violet-950/30 p-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-fuchsia-300">এআই ওয়ার্কফ্লো</p>
                    <div class="mt-4 space-y-3 font-mono text-sm">
                        <div class="rounded-lg bg-black/30 p-3 text-slate-300">
                            📱 কাস্টমার: "২টা শার্ট L সাইজ, ঢাকা মিরপুর"
                        </div>
                        <div class="flex justify-center text-fuchsia-400">↓ এআই প্রসেসিং</div>
                        <div class="rounded-lg border border-emerald-500/30 bg-emerald-950/30 p-3 text-emerald-200">
                            ✓ অর্ডার #4821 তৈরি · ঠিকানা অটো-কমপ্লিট · স্কোর ৮৮%
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Multi-store spotlight -->
        <section v-if="multistorePillar" class="py-20">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 lg:grid-cols-2 lg:px-8">
                <div class="order-2 lg:order-1 rounded-2xl border border-sky-500/20 bg-gradient-to-br from-sky-950/40 to-cyan-950/30 p-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-sky-300">এক অ্যাপ, সব স্টোর</p>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="(store, i) in ['Fashion Store', 'Gadget Hub', 'Organic Shop']"
                            :key="store"
                            class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/20 text-xs font-bold text-sky-300">
                                    {{ i + 1 }}
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ store }}</p>
                                    <p class="text-xs text-slate-400">{{ i === 0 ? '৩টি নতুন অর্ডার' : 'সব ঠিক আছে' }}</p>
                                </div>
                            </div>
                            <span
                                v-if="i === 0"
                                class="rounded-full bg-rose-500/20 px-2 py-0.5 text-xs font-bold text-rose-300"
                            >
                                নতুন
                            </span>
                        </div>
                    </div>
                    <p class="mt-4 text-center text-xs text-slate-400">সাইট বদলাতে লগইন-লগআউটের দিন শেষ</p>
                </div>
                <div class="order-1 lg:order-2">
                    <span class="inline-flex rounded-full border border-sky-400/30 bg-sky-500/10 px-3 py-1 text-xs font-bold text-sky-300">
                        {{ multistorePillar.badge }}
                    </span>
                    <h2 class="mt-4 text-3xl font-bold text-white sm:text-4xl">{{ multistorePillar.headline }}</h2>
                    <p class="mt-4 text-slate-400">{{ multistorePillar.subheadline }}</p>
                    <ul class="mt-6 space-y-3">
                        <li
                            v-for="feat in multistorePillar.features"
                            :key="feat.key"
                            class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-4"
                        >
                            <span class="text-sky-400">✦</span>
                            <div>
                                <p class="font-semibold text-white">{{ feat.label }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ feat.description }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Loss aversion -->
        <section v-if="lossComparison.headline" class="py-20">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <h2 class="text-center text-3xl font-bold text-white sm:text-4xl">
                    {{ lossComparison.headline }}
                </h2>
                <div class="mt-12 grid gap-6 lg:grid-cols-2">
                    <article class="rounded-2xl border border-red-500/30 bg-red-950/20 p-6">
                        <h3 class="text-lg font-bold text-red-300">{{ lossComparison.without?.title }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="item in lossComparison.without?.items ?? []"
                                :key="item"
                                class="flex items-start gap-2 text-sm text-red-100/80"
                            >
                                <span class="text-red-400">✕</span>{{ item }}
                            </li>
                        </ul>
                        <p class="mt-5 rounded-lg bg-red-500/10 px-4 py-2 text-sm font-bold text-red-300">
                            {{ lossComparison.without?.summary }}
                        </p>
                    </article>
                    <article class="rounded-2xl border border-emerald-500/30 bg-emerald-950/20 p-6">
                        <h3 class="text-lg font-bold text-emerald-300">{{ lossComparison.with?.title }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="item in lossComparison.with?.items ?? []"
                                :key="item"
                                class="flex items-start gap-2 text-sm text-emerald-100/80"
                            >
                                <span class="text-emerald-400">✓</span>{{ item }}
                            </li>
                        </ul>
                        <p class="mt-5 rounded-lg bg-emerald-500/10 px-4 py-2 text-sm font-bold text-emerald-300">
                            {{ lossComparison.with?.summary }}
                        </p>
                        <a
                            :href="primaryCtaUrl"
                            :target="primaryCtaExternal ? '_blank' : undefined"
                            class="mt-4 inline-flex rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-500"
                        >
                            আজই শুরু করুন
                        </a>
                    </article>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section v-if="stats.length" class="border-y border-white/10 bg-[#0a0f1c] py-12">
            <div class="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-4 sm:grid-cols-4 lg:px-8">
                <div v-for="stat in stats" :key="stat.label" class="text-center">
                    <p class="text-3xl font-extrabold text-white">{{ stat.value }}</p>
                    <p class="mt-1 text-sm text-slate-400">{{ stat.label }}</p>
                </div>
            </div>
        </section>

        <!-- Features from package -->
        <section id="features" class="py-20">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-white sm:text-4xl">আমাদের শক্তিশালী ফিচার</h2>
                    <p v-if="featuredPlan" class="mt-3 text-slate-400">
                        {{ featuredPlan.title }} প্ল্যানে {{ featuredPlan.enabled_feature_count }}+ প্রিমিয়াম ফিচার
                    </p>
                </div>
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="feature in conversionFeatures"
                        :key="feature.key"
                        class="rounded-2xl border p-5 transition hover:-translate-y-0.5"
                        :class="featureColorClass(feature.color)"
                    >
                        <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-lg">
                            ★
                        </div>
                        <h3 class="font-bold text-white">{{ feature.label }}</h3>
                        <p class="mt-2 text-sm leading-relaxed opacity-80">{{ feature.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="border-t border-white/10 bg-[#0a0f1c] py-20">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <h2 class="text-center text-3xl font-bold text-white">মাত্র ৩ ধাপে শুরু করুন</h2>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="item in steps"
                        :key="item.step"
                        class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center"
                    >
                        <span class="text-4xl font-extrabold text-violet-500/40">{{ item.step }}</span>
                        <h3 class="mt-3 text-lg font-bold text-white">{{ item.title }}</h3>
                        <p class="mt-2 text-sm text-slate-400">{{ item.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing preview -->
        <section id="pricing" class="py-20">
            <div class="mx-auto max-w-6xl px-4 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-white">আপনার ব্যবসার জন্য সঠিক প্ল্যান</h2>
                    <p class="mt-3 text-slate-400">স্বচ্ছ মূল্য — কোনো হিডেন চার্জ নেই</p>
                </div>
                <div class="mt-12 grid gap-6 lg:grid-cols-3">
                    <article
                        v-for="plan in previewPlans"
                        :key="plan.id"
                        class="relative flex flex-col rounded-2xl border p-6"
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
                        <h3 class="mt-1 text-xl font-bold text-white">{{ plan.title }}</h3>
                        <p class="mt-3 text-4xl font-extrabold text-white">{{ plan.price_label }}</p>
                        <p class="text-sm text-slate-400">{{ plan.token_label }}</p>
                        <ul class="mt-5 flex-1 space-y-2">
                            <li
                                v-for="f in plan.top_features"
                                :key="f.key"
                                class="flex gap-2 text-sm text-slate-300"
                            >
                                <span class="text-emerald-400">✓</span>{{ f.label }}
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
                    <Link
                        :href="route('pricing')"
                        class="text-sm font-semibold text-violet-400 hover:text-violet-300"
                    >
                        সব প্যাকেজ দেখুন →
                    </Link>
                </div>
            </div>
        </section>

        <!-- Free trial CTA -->
        <section v-if="trialPlan" class="py-16">
            <div class="mx-auto max-w-4xl px-4 lg:px-8">
                <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-fuchsia-600 to-violet-800 p-8 text-center shadow-2xl sm:p-12">
                    <h2 class="text-3xl font-extrabold text-white">আজই শুরু করুন বিনামূল্যে!</h2>
                    <p class="mx-auto mt-3 max-w-lg text-violet-100">
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

        <!-- App download -->
        <section id="download-app" class="border-t border-white/10 py-20">
            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 lg:grid-cols-2 lg:px-8">
                <div>
                    <h2 class="text-3xl font-bold text-white">মোবাইল অ্যাপ দিয়ে সব স্টোর কন্ট্রোল করুন</h2>
                    <p class="mt-4 text-slate-400">
                        নতুন অর্ডার, কুরিয়ার আপডেট, কাস্টমার কল — সব নোটিফিকেশন ফোনে পান।
                        একাধিক WooCommerce স্টোর একসাথে ম্যানেজ করুন।
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a
                            v-if="appDownloadUrl"
                            :href="appDownloadUrl"
                            class="rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white"
                            download
                        >
                            APK ডাউনলোড
                        </a>
                        <a
                            v-if="playStoreUrl"
                            :href="playStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white"
                        >
                            Google Play
                        </a>
                    </div>
                </div>
                <div class="flex justify-center">
                    <img
                        src="/images/woo-easy-life/app_icon.jpg"
                        alt="WooEasyLife"
                        class="h-44 w-44 rounded-[2rem] shadow-2xl ring-4 ring-violet-500/30"
                    />
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="border-t border-white/10 bg-[#0a0f1c] py-20">
            <div class="mx-auto max-w-3xl px-4 lg:px-8">
                <h2 class="text-center text-3xl font-bold text-white">যা জানতে চান</h2>
                <div class="mt-10 space-y-3">
                    <div
                        v-for="(item, i) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-5 py-4 text-left text-sm font-semibold text-white"
                            @click="toggleFaq(i)"
                        >
                            {{ item.q }}
                            <span class="text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <p v-show="openFaq === i" class="border-t border-white/10 px-5 py-4 text-sm text-slate-400">
                            {{ item.a }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="py-16">
            <div class="mx-auto max-w-3xl px-4 text-center lg:px-8">
                <h2 class="text-3xl font-bold text-white">
                    আজই সিদ্ধান্ত নিন — কাল থেকেই ফ্রডে যে টাকা যাচ্ছে, বাঁচান
                </h2>
                <a
                    :href="primaryCtaUrl"
                    :target="primaryCtaExternal ? '_blank' : undefined"
                    class="mt-8 inline-flex rounded-xl bg-violet-600 px-10 py-4 text-base font-bold text-white shadow-xl shadow-violet-900/50 hover:bg-violet-500"
                >
                    {{ primaryCtaLabel }}
                </a>
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
