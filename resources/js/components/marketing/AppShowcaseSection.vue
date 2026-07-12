<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    appShowcase: { type: Object, default: () => ({}) },
    appDownloadUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
});

const ROTATE_MS = 3500;

const activeIndex = ref(0);
const mainLoading = ref(true);
const loadedSrcs = ref(new Set());
const paused = ref(false);

let timer = null;

const screenshots = computed(() => {
    const list = props.appShowcase?.screenshots;

    if (Array.isArray(list) && list.length) {
        return list;
    }

    const fallback = props.appShowcase?.screenshot ?? '/images/woo-easy-life/hub.jpg';

    return [
        {
            src: fallback,
            alt: props.appShowcase?.screenshot_alt ?? 'WooEasyLife মোবাইল অ্যাপ',
            label: 'অ্যাপ',
        },
    ];
});

const activeShot = computed(() => screenshots.value[activeIndex.value] ?? screenshots.value[0]);

const markLoaded = (src) => {
    const next = new Set(loadedSrcs.value);
    next.add(src);
    loadedSrcs.value = next;
};

const isThumbLoaded = (src) => loadedSrcs.value.has(src);

const onMainLoad = () => {
    markLoaded(activeShot.value.src);
    mainLoading.value = false;
};

const onMainError = () => {
    mainLoading.value = false;
};

const goTo = (index) => {
    const total = screenshots.value.length;

    if (total < 2) {
        return;
    }

    const next = ((index % total) + total) % total;

    if (next === activeIndex.value) {
        return;
    }

    activeIndex.value = next;
    const src = screenshots.value[next]?.src;
    mainLoading.value = !(src && loadedSrcs.value.has(src));
};

const selectShot = (index) => {
    goTo(index);
    restartTimer();
};

const nextShot = () => {
    goTo(activeIndex.value + 1);
};

const clearTimer = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

const startTimer = () => {
    clearTimer();

    if (screenshots.value.length < 2 || paused.value) {
        return;
    }

    timer = setInterval(nextShot, ROTATE_MS);
};

const restartTimer = () => {
    startTimer();
};

watch(
    () => activeShot.value?.src,
    (src) => {
        if (!src) {
            return;
        }

        if (loadedSrcs.value.has(src)) {
            mainLoading.value = false;
        }
    },
);

watch(
    () => screenshots.value.length,
    () => restartTimer(),
);

onMounted(() => startTimer());
onUnmounted(() => clearTimer());

const onPointerEnter = () => {
    paused.value = true;
    clearTimer();
};

const onPointerLeave = () => {
    paused.value = false;
    startTimer();
};
</script>

<template>
    <section id="download-app" class="scroll-mt-24 border-t border-white/10 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="grid items-center gap-8 sm:gap-10 lg:grid-cols-2 lg:gap-12">
                <div class="order-2 min-w-0 lg:order-1">
                    <span class="inline-flex rounded-full border border-sky-400/30 bg-sky-500/10 px-3 py-1 text-xs font-bold text-sky-300">
                        মোবাইল অ্যাপ
                    </span>
                    <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                        {{ appShowcase.headline }}
                    </h2>
                    <p class="mt-4 text-sm leading-relaxed text-slate-400 sm:text-base">
                        {{ appShowcase.subheadline }}
                    </p>

                    <ul v-if="appShowcase.benefits?.length" class="mt-6 space-y-3">
                        <li
                            v-for="benefit in appShowcase.benefits"
                            :key="benefit"
                            class="flex items-start gap-2.5 text-sm text-slate-200"
                        >
                            <span class="mt-0.5 text-sky-400">✓</span>
                            {{ benefit }}
                        </li>
                    </ul>

                    <div
                        v-if="appShowcase.rating || appShowcase.download_count"
                        class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2"
                    >
                        <div v-if="appShowcase.rating" class="flex items-center gap-1.5">
                            <span class="text-amber-400" aria-hidden="true">★★★★★</span>
                            <span class="text-sm font-bold text-white">{{ appShowcase.rating }}</span>
                            <span v-if="appShowcase.rating_count" class="text-xs text-slate-400">
                                ({{ appShowcase.rating_count }} রিভিউ)
                            </span>
                        </div>
                        <div v-if="appShowcase.download_count" class="flex items-center gap-1.5 text-sm text-slate-300">
                            <svg class="h-4 w-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                            </svg>
                            <span class="font-bold text-white">{{ appShowcase.download_count }}</span> ডাউনলোড
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <a
                            v-if="appDownloadUrl"
                            :href="appDownloadUrl"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black transition hover:bg-amber-400"
                            download
                        >
                            APK ডাউনলোড
                        </a>
                        <a
                            v-if="playStoreUrl"
                            :href="playStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            Google Play
                        </a>
                    </div>
                </div>

                <div
                    class="order-1 min-w-0 lg:order-2"
                    @pointerenter="onPointerEnter"
                    @pointerleave="onPointerLeave"
                >
                    <div class="mx-auto w-full max-w-[260px] sm:max-w-[300px]">
                        <div class="relative aspect-[9/19.5] w-full overflow-hidden bg-slate-900">
                            <div
                                v-show="mainLoading"
                                class="absolute inset-0 z-[1] flex flex-col items-center justify-center gap-3 bg-slate-900"
                                aria-busy="true"
                                aria-label="ছবি লোড হচ্ছে"
                            >
                                <div class="h-9 w-9 animate-spin rounded-full border-2 border-sky-400/30 border-t-sky-400" />
                                <div class="w-[70%] space-y-2 px-4">
                                    <div class="h-2 animate-pulse rounded bg-white/10" />
                                    <div class="h-2 w-4/5 animate-pulse rounded bg-white/10" />
                                    <div class="h-2 w-3/5 animate-pulse rounded bg-white/10" />
                                </div>
                            </div>

                            <img
                                :key="activeShot.src"
                                :src="activeShot.src"
                                :alt="activeShot.alt"
                                class="absolute inset-0 h-full w-full object-cover object-top transition-opacity duration-300"
                                :class="mainLoading ? 'opacity-0' : 'opacity-100'"
                                loading="lazy"
                                decoding="async"
                                fetchpriority="low"
                                @load="onMainLoad"
                                @error="onMainError"
                            >
                        </div>

                        <p
                            v-if="activeShot.label"
                            class="mt-2.5 text-center text-xs font-semibold text-slate-400 sm:mt-3"
                        >
                            {{ activeShot.label }}
                        </p>

                        <div
                            v-if="screenshots.length > 1"
                            class="mt-3 grid grid-cols-5 gap-1.5 sm:mt-4 sm:gap-2"
                            role="tablist"
                            aria-label="অ্যাপ স্ক্রিনশট"
                        >
                            <button
                                v-for="(shot, index) in screenshots"
                                :key="shot.src"
                                type="button"
                                class="relative min-h-11 overflow-hidden border-2 transition active:scale-95 sm:min-h-0"
                                :class="activeIndex === index
                                    ? 'border-amber-400 shadow-md shadow-amber-900/30'
                                    : 'border-white/10 opacity-70 hover:opacity-100'"
                                :aria-pressed="activeIndex === index"
                                :aria-label="shot.label || `Screenshot ${index + 1}`"
                                @click="selectShot(index)"
                            >
                                <div class="relative aspect-[9/16] w-full bg-slate-800">
                                    <div
                                        v-show="!isThumbLoaded(shot.src)"
                                        class="absolute inset-0 animate-pulse bg-white/10"
                                    />
                                    <img
                                        :src="shot.src"
                                        :alt="shot.alt"
                                        class="absolute inset-0 h-full w-full object-cover object-top transition-opacity duration-200"
                                        :class="isThumbLoaded(shot.src) ? 'opacity-100' : 'opacity-0'"
                                        loading="lazy"
                                        decoding="async"
                                        @load="markLoaded(shot.src)"
                                    >
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
