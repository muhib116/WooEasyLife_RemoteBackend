<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    showcases: { type: Array, default: () => [] },
});

const defaultOpenId = () =>
    props.showcases.find((s) => s.id === 'fraud')?.id
    ?? props.showcases[0]?.id
    ?? null;

const openId = ref(defaultOpenId());
const readMoreOpenIds = ref(new Set());
const featureDetailOpenKeys = ref(new Set());

watch(
    () => props.showcases,
    (showcases) => {
        if (!openId.value && showcases.length) {
            openId.value = showcases.find((s) => s.id === 'fraud')?.id ?? showcases[0].id;
        }
    },
    { immediate: true },
);

const toggleSection = (id) => {
    openId.value = openId.value === id ? null : id;
};

const toggleReadMore = (id) => {
    const next = new Set(readMoreOpenIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    readMoreOpenIds.value = next;
};

const toggleFeatureDetail = (showcaseId, featureKey) => {
    const key = `${showcaseId}:${featureKey}`;
    const next = new Set(featureDetailOpenKeys.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    featureDetailOpenKeys.value = next;
};

const isFeatureDetailOpen = (showcaseId, featureKey) =>
    featureDetailOpenKeys.value.has(`${showcaseId}:${featureKey}`);

const accentStyles = (accent) => {
    const map = {
        emerald: {
            badge: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
            border: 'border-emerald-500/20',
            bg: 'from-emerald-950/30 to-[#111111]',
            dot: 'text-emerald-400',
            benefit: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-200',
            highlight: 'text-emerald-400',
            scenario: 'border-emerald-500/25 bg-emerald-950/30',
        },
        amber: {
            badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
            border: 'border-amber-500/20',
            bg: 'from-amber-950/30 to-[#111111]',
            dot: 'text-amber-400',
            benefit: 'border-amber-500/20 bg-amber-500/10 text-amber-200',
            highlight: 'text-amber-400',
            scenario: 'border-amber-500/25 bg-amber-950/30',
        },
        sky: {
            badge: 'border-sky-400/30 bg-sky-500/10 text-sky-300',
            border: 'border-sky-500/20',
            bg: 'from-sky-950/30 to-[#111111]',
            dot: 'text-sky-400',
            benefit: 'border-sky-500/20 bg-sky-500/10 text-sky-200',
            highlight: 'text-sky-400',
            scenario: 'border-sky-500/25 bg-sky-950/30',
        },
        violet: {
            badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
            border: 'border-amber-500/20',
            bg: 'from-amber-950/30 to-[#111111]',
            dot: 'text-amber-400',
            benefit: 'border-amber-500/20 bg-amber-500/10 text-amber-200',
            highlight: 'text-amber-400',
            scenario: 'border-amber-500/25 bg-amber-950/30',
        },
        fuchsia: {
            badge: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-300',
            border: 'border-fuchsia-500/20',
            bg: 'from-fuchsia-950/30 to-[#111111]',
            dot: 'text-fuchsia-400',
            benefit: 'border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-200',
            highlight: 'text-fuchsia-400',
            scenario: 'border-fuchsia-500/25 bg-fuchsia-950/30',
        },
        orange: {
            badge: 'border-orange-400/30 bg-orange-500/10 text-orange-300',
            border: 'border-orange-500/20',
            bg: 'from-orange-950/30 to-[#111111]',
            dot: 'text-orange-400',
            benefit: 'border-orange-500/20 bg-orange-500/10 text-orange-200',
            highlight: 'text-orange-400',
            scenario: 'border-orange-500/25 bg-orange-950/30',
        },
        cyan: {
            badge: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-300',
            border: 'border-cyan-500/20',
            bg: 'from-cyan-950/30 to-[#111111]',
            dot: 'text-cyan-400',
            benefit: 'border-cyan-500/20 bg-cyan-500/10 text-cyan-200',
            highlight: 'text-cyan-400',
            scenario: 'border-cyan-500/25 bg-cyan-950/30',
        },
    };

    return map[accent] ?? map.violet;
};
</script>

<template>
    <section id="features" class="scroll-mt-24 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-300">
                    সম্পূর্ণ ফিচার গাইড
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    আপনার ব্যবসার জন্য প্রতিটি ফিচার
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    কীভাবে সময় বাঁচাবে, লস কমাবে ও বিক্রি বাড়াবে—এক নজরে দেখুন।
                </p>
            </div>

            <div class="mt-10 space-y-4">
                <article
                    v-for="(showcase, index) in showcases"
                    :key="showcase.id"
                    class="overflow-hidden rounded-2xl border bg-gradient-to-br shadow-lg transition"
                    :class="[
                        accentStyles(showcase.accent).border,
                        accentStyles(showcase.accent).bg,
                        openId === showcase.id ? 'shadow-amber-900/20' : 'hover:border-white/20',
                    ]"
                >
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-4 p-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 sm:p-6"
                        :aria-expanded="openId === showcase.id"
                        :aria-controls="`feature-panel-${showcase.id}`"
                        @click="toggleSection(showcase.id)"
                    >
                        <div class="min-w-0">
                            <span
                                class="inline-flex rounded-full border px-3 py-1 text-xs font-bold"
                                :class="accentStyles(showcase.accent).badge"
                            >
                                {{ showcase.badge }}
                            </span>
                            <h3 class="mt-3 text-lg font-bold leading-snug text-white sm:text-xl">
                                {{ showcase.headline }}
                            </h3>
                            <p
                                v-if="showcase.teaser && openId !== showcase.id"
                                class="mt-2 text-sm leading-relaxed text-slate-400"
                            >
                                {{ showcase.teaser }}
                            </p>
                        </div>
                        <span
                            class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-sm font-bold text-slate-300 sm:h-10 sm:w-10"
                            aria-hidden="true"
                        >
                            {{ openId === showcase.id ? '−' : '+' }}
                        </span>
                    </button>

                    <div
                        v-show="openId === showcase.id"
                        :id="`feature-panel-${showcase.id}`"
                        role="region"
                        class="border-t border-white/10"
                    >
                        <div class="p-5 sm:p-7 lg:p-8">
                            <!-- Highlights -->
                            <ul
                                v-if="showcase.highlights?.length"
                                class="mb-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap"
                            >
                                <li
                                    v-for="item in showcase.highlights"
                                    :key="item"
                                    class="inline-flex items-start gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-200 sm:text-sm"
                                >
                                    <span class="shrink-0 font-bold" :class="accentStyles(showcase.accent).highlight">✓</span>
                                    <span>{{ item }}</span>
                                </li>
                            </ul>

                            <!-- Profit / ROI calculation -->
                            <div
                                v-if="showcase.profit"
                                class="mb-6 rounded-2xl border p-4 sm:p-5"
                                :class="accentStyles(showcase.accent).scenario"
                            >
                                <div class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-baseline sm:justify-between sm:gap-x-4">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                                        {{ showcase.profit.label ?? 'প্রতি মাসে সম্ভাব্য সাশ্রয়' }}
                                    </p>
                                    <p
                                        class="text-2xl font-extrabold sm:text-3xl"
                                        :class="accentStyles(showcase.accent).highlight"
                                    >
                                        ≈ {{ showcase.profit.monthly }}
                                        <span class="text-sm font-semibold text-slate-400">/ মাস</span>
                                    </p>
                                </div>
                                <p v-if="showcase.profit.basis" class="mt-1.5 text-xs leading-relaxed text-slate-400">
                                    হিসাব: {{ showcase.profit.basis }}
                                </p>
                                <p
                                    v-if="showcase.profit.highlight"
                                    class="mt-3 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-3 py-2 text-xs font-medium leading-relaxed text-emerald-100"
                                >
                                    💡 {{ showcase.profit.highlight }}
                                </p>
                                <p v-if="showcase.profit.compare" class="mt-3 text-sm font-semibold leading-relaxed text-white">
                                    {{ showcase.profit.compare }}
                                </p>
                                <p class="mt-2 text-[11px] text-slate-500">
                                    {{ showcase.profit.note ?? 'এটি একটি উদাহরণভিত্তিক হিসাব। প্রকৃত সাশ্রয় আপনার অর্ডার, রিটার্ন ও ব্যবসার ধরন অনুযায়ী ভিন্ন হতে পারে।' }}
                                </p>
                            </div>

                            <div
                                class="grid gap-6 lg:grid-cols-2 lg:gap-8"
                                :class="index % 2 === 1 ? 'lg:[&>*:first-child]:order-2' : ''"
                            >
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-rose-500/20 bg-rose-950/20 px-4 py-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-rose-400">সমস্যা</p>
                                        <p class="mt-1 text-sm leading-relaxed text-rose-100/90">{{ showcase.pain }}</p>
                                    </div>
                                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">সমাধান</p>
                                        <p class="mt-1 text-sm leading-relaxed text-slate-200">{{ showcase.solution }}</p>
                                    </div>
                                    <div
                                        class="rounded-xl border px-4 py-3"
                                        :class="accentStyles(showcase.accent).benefit"
                                    >
                                        <p class="text-xs font-bold uppercase tracking-wide opacity-80">লাভ</p>
                                        <p class="mt-1 text-sm font-medium leading-relaxed">{{ showcase.benefit }}</p>
                                    </div>

                                    <!-- Scenario (team / cancel example) -->
                                    <div
                                        v-if="showcase.scenario?.title"
                                        class="rounded-xl border px-4 py-4"
                                        :class="accentStyles(showcase.accent).scenario"
                                    >
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">উদাহরণ</p>
                                        <p class="mt-1 text-sm font-bold text-white">{{ showcase.scenario.title }}</p>
                                        <ol class="mt-3 space-y-2">
                                            <li
                                                v-for="(step, stepIndex) in showcase.scenario.steps"
                                                :key="step"
                                                class="flex gap-2.5 text-sm leading-relaxed text-slate-300"
                                            >
                                                <span
                                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-slate-400"
                                                >
                                                    {{ stepIndex + 1 }}
                                                </span>
                                                <span>{{ step }}</span>
                                            </li>
                                        </ol>
                                    </div>
                                </div>

                                <!-- Feature list with per-feature read more -->
                                <div class="rounded-2xl border border-white/10 bg-black/20 p-4 sm:p-5">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">অন্তর্ভুক্ত ফিচার</p>
                                    <ul class="mt-4 space-y-3">
                                        <li
                                            v-for="feat in showcase.features"
                                            :key="feat.key"
                                            class="rounded-xl border border-white/10 bg-white/5 p-3"
                                        >
                                            <div class="flex gap-3">
                                                <span class="shrink-0 font-bold" :class="accentStyles(showcase.accent).dot">✦</span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold text-white">{{ feat.label }}</p>
                                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-400">
                                                        {{ feat.description }}
                                                    </p>
                                                    <div
                                                        v-if="isFeatureDetailOpen(showcase.id, feat.key) && feat.detail"
                                                        class="mt-2 rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-xs leading-relaxed text-slate-300"
                                                    >
                                                        {{ feat.detail }}
                                                    </div>
                                                    <button
                                                        v-if="feat.detail && feat.detail !== feat.description"
                                                        type="button"
                                                        class="mt-2 inline-flex min-h-10 items-center rounded-lg px-2 py-2 text-xs font-semibold transition hover:bg-white/5 hover:opacity-90"
                                                        :class="accentStyles(showcase.accent).highlight"
                                                        @click="toggleFeatureDetail(showcase.id, feat.key)"
                                                    >
                                                        {{ isFeatureDetailOpen(showcase.id, feat.key) ? 'সংক্ষেপে দেখুন ↑' : 'বিস্তারিত দেখুন ↓' }}
                                                    </button>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Showcase-level read more -->
                            <div
                                v-if="showcase.read_more?.length"
                                class="mt-6 border-t border-white/10 pt-6"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10"
                                    @click="toggleReadMore(showcase.id)"
                                >
                                    <span>{{ readMoreOpenIds.has(showcase.id) ? 'সংক্ষেপে দেখুন' : 'আরও পড়ুন — সম্পূর্ণ ব্যাখ্যা' }}</span>
                                    <span aria-hidden="true">{{ readMoreOpenIds.has(showcase.id) ? '▲' : '▼' }}</span>
                                </button>

                                <div
                                    v-show="readMoreOpenIds.has(showcase.id)"
                                    class="mt-4 space-y-4"
                                >
                                    <div
                                        v-for="(block, blockIndex) in showcase.read_more"
                                        :key="blockIndex"
                                        class="rounded-xl border border-white/10 bg-white/5 px-4 py-4 sm:px-5"
                                    >
                                        <h4 class="text-sm font-bold text-white">{{ block.title }}</h4>
                                        <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ block.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
