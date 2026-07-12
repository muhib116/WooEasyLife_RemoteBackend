<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    section: { type: Object, default: () => ({}) },
});

const animate = ref(false);

onMounted(() => {
    requestAnimationFrame(() => {
        setTimeout(() => {
            animate.value = true;
        }, 150);
    });
});

const kpiToneClass = (tone) => {
    const map = {
        neutral: 'border-white/10 bg-white/5 text-white',
        safe: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
        risky: 'border-rose-500/25 bg-rose-500/10 text-rose-300',
        accent: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
    };

    return map[tone] ?? map.neutral;
};

const kpiIcon = (icon) => {
    const icons = {
        box: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        check: 'M5 13l4 4L19 7',
        x: 'M6 18L18 6M6 6l12 12',
        trend: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    };

    return icons[icon] ?? icons.box;
};

const barColor = (rate) => {
    if (rate >= 90) {
        return 'from-emerald-500 to-emerald-400';
    }

    if (rate >= 80) {
        return 'from-amber-500 to-yellow-400';
    }

    return 'from-rose-500 to-rose-400';
};

const rateTextColor = (rate) => {
    if (rate >= 90) {
        return 'text-emerald-400';
    }

    if (rate >= 80) {
        return 'text-amber-400';
    }

    return 'text-rose-400';
};
</script>

<template>
    <section
        v-if="section?.couriers?.length"
        id="courier-performance"
        class="scroll-mt-24 border-y border-white/10 bg-[#111111] py-14 sm:py-20"
    >
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span
                    v-if="section.badge"
                    class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300"
                >
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400" />
                    {{ section.badge }}
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    {{ section.headline }}
                </h2>
                <p v-if="section.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ section.subtitle }}
                </p>
            </div>

            <!-- KPI cards -->
            <div v-if="section.kpis?.length" class="mt-10 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div
                    v-for="kpi in section.kpis"
                    :key="kpi.label"
                    class="rounded-2xl border p-4 text-center sm:p-5"
                    :class="kpiToneClass(kpi.tone)"
                >
                    <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-black/20">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="height:1.1rem;width:1.1rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="kpiIcon(kpi.icon)" />
                        </svg>
                    </span>
                    <p class="mt-3 text-2xl font-extrabold sm:text-3xl">{{ kpi.value }}</p>
                    <p class="mt-1 text-xs text-slate-400 sm:text-sm">{{ kpi.label }}</p>
                </div>
            </div>

            <!-- Per-courier performance bars -->
            <div class="mt-6 grid gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-4">
                <div
                    v-for="courier in section.couriers"
                    :key="courier.name"
                    class="rounded-2xl border border-white/10 bg-white/5 p-4 sm:p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <div class="flex h-10 w-[4.75rem] shrink-0 items-center justify-center rounded-lg bg-white px-1.5">
                                <img
                                    :src="courier.logo"
                                    :alt="courier.name"
                                    class="h-6 w-auto max-w-full object-contain"
                                />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-white">{{ courier.name }}</p>
                                <p v-if="courier.delivered || courier.returned" class="flex flex-wrap gap-x-2 gap-y-0.5 text-xs text-slate-400">
                                    <span v-if="courier.delivered" class="text-emerald-400">● {{ courier.delivered }} ডেলিভারি</span>
                                    <span v-if="courier.returned" class="text-rose-400">● {{ courier.returned }} রিটার্ন</span>
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 text-lg font-extrabold" :class="rateTextColor(courier.success_rate)">
                            {{ courier.success_rate }}%
                        </span>
                    </div>

                    <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full bg-gradient-to-r transition-[width] duration-1000 ease-out"
                            :class="barColor(courier.success_rate)"
                            :style="{ width: animate ? `${courier.success_rate}%` : '0%' }"
                        />
                    </div>
                </div>
            </div>

            <p v-if="section.note" class="mt-6 text-center text-xs text-slate-500">
                {{ section.note }}
            </p>
        </div>
    </section>
</template>
