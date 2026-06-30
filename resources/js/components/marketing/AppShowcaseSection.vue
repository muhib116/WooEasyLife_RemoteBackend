<script setup>
import { computed } from 'vue';

const props = defineProps({
    appShowcase: { type: Object, default: () => ({}) },
    appDownloadUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
});

const screenshotSrc = computed(
    () => props.appShowcase.screenshot ?? '/images/woo-easy-life/app-left-sidebar.png',
);

const screenshotAlt = computed(
    () => props.appShowcase.screenshot_alt ?? 'WooEasyLife mobile app screenshot',
);
</script>

<template>
    <section id="download-app" class="scroll-mt-24 border-t border-white/10 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-12">
                <div class="order-2 lg:order-1">
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

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a
                            v-if="appDownloadUrl"
                            :href="appDownloadUrl"
                            class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black transition hover:bg-amber-400"
                            download
                        >
                            APK ডাউনলোড
                        </a>
                        <a
                            v-if="playStoreUrl"
                            :href="playStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            Google Play
                        </a>
                    </div>
                </div>

                <div class="order-1 flex justify-center lg:order-2">
                    <div class="relative w-full max-w-[260px] sm:max-w-[280px]">
                        <div class="absolute -inset-4 rounded-[2.5rem] bg-sky-600/20 blur-2xl" />
                        <div class="relative overflow-hidden rounded-[2rem] border-[6px] border-slate-800 bg-slate-900 shadow-2xl shadow-sky-900/30 ring-1 ring-white/10">
                            <div class="pointer-events-none absolute left-1/2 top-0 z-10 h-5 w-24 -translate-x-1/2 rounded-b-2xl bg-slate-900" />
                            <img
                                :src="screenshotSrc"
                                :alt="screenshotAlt"
                                class="block w-full object-cover object-top"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
