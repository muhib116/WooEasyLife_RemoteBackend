<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    lossComparison: { type: Object, default: () => ({}) },
    primaryCtaUrl: { type: String, default: '#' },
    primaryCtaLabel: { type: String, default: 'ফ্রি ট্রায়াল শুরু করুন' },
    fraudCheckEnabled: { type: Boolean, default: true },
});

const withoutIcons = ['💸', '⏳', '📦', '📱'];
const withIcons = ['🛡️', '🚚', '✅', '🔔', '🌐'];

const withoutItems = computed(() => props.lossComparison.without?.items ?? []);
const withItems = computed(() => props.lossComparison.with?.items ?? []);

const scrollToFraudCheck = () => {
    document.getElementById('fraud-check')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <section v-if="lossComparison.headline" class="relative overflow-hidden py-14 sm:py-20 lg:py-24">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_left,_rgba(239,68,68,0.08),_transparent_50%)]" />
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_right,_rgba(16,185,129,0.1),_transparent_50%)]" />

        <div class="relative mx-auto max-w-6xl px-4 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex rounded-full border border-rose-500/30 bg-rose-500/10 px-3 py-1 text-xs font-bold text-rose-300">
                    তুলনা করে দেখুন
                </span>
                <h2 class="mt-4 text-2xl font-extrabold leading-tight text-white sm:text-4xl lg:text-[2.75rem]">
                    {{ lossComparison.headline }}
                </h2>
                <p v-if="lossComparison.subtitle" class="mt-3 text-sm leading-relaxed text-slate-400 sm:mt-4 sm:text-lg">
                    {{ lossComparison.subtitle }}
                </p>
            </div>

            <div class="relative mt-12 lg:mt-14">
                <div
                    class="pointer-events-none absolute left-1/2 top-1/2 z-10 hidden -translate-x-1/2 -translate-y-1/2 lg:flex"
                >
                    <span class="flex h-14 w-14 items-center justify-center rounded-full border border-white/15 bg-[#111111] text-sm font-extrabold tracking-wider text-slate-300 shadow-xl">
                        VS
                    </span>
                </div>

                <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                    <!-- Without -->
                    <article class="flex flex-col rounded-3xl border border-rose-500/25 bg-gradient-to-br from-rose-950/40 to-[#111111] p-6 sm:p-7">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-rose-500/30 bg-rose-500/15 text-lg">
                                ✕
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-300/80">বর্তমান সমস্যা</p>
                                <h3 class="text-xl font-bold text-rose-200">
                                    {{ lossComparison.without?.title }}
                                </h3>
                            </div>
                        </div>

                        <ul class="mt-6 flex-1 space-y-3">
                            <li
                                v-for="(item, index) in withoutItems"
                                :key="item"
                                class="flex items-start gap-3 rounded-2xl border border-rose-500/15 bg-rose-500/5 px-4 py-3.5"
                            >
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-sm">
                                    {{ withoutIcons[index] ?? '✕' }}
                                </span>
                                <span class="text-sm leading-relaxed text-rose-100/90">{{ item }}</span>
                            </li>
                        </ul>

                        <div class="mt-6 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-center">
                            <p class="text-sm font-bold text-rose-200">
                                {{ lossComparison.without?.summary }}
                            </p>
                        </div>
                    </article>

                    <div class="flex items-center justify-center lg:hidden">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-[#111111] text-xs font-extrabold tracking-wider text-slate-300 shadow-lg">
                            VS
                        </span>
                    </div>

                    <!-- With -->
                    <article class="relative flex flex-col rounded-3xl border border-emerald-400/40 bg-gradient-to-br from-emerald-950/50 via-[#141414] to-amber-950/20 p-6 shadow-2xl shadow-emerald-900/20 sm:p-7 lg:-translate-y-1 lg:scale-[1.02]">
                        <span class="absolute -top-3 right-6 rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 px-3 py-1 text-xs font-bold text-emerald-950">
                            সুপারিশকৃত
                        </span>

                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/15 text-lg">
                                ✓
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300/80">স্মার্ট সমাধান</p>
                                <h3 class="text-xl font-bold text-emerald-200">
                                    {{ lossComparison.with?.title }}
                                </h3>
                            </div>
                        </div>

                        <ul class="mt-6 flex-1 space-y-3">
                            <li
                                v-for="(item, index) in withItems"
                                :key="item"
                                class="flex items-start gap-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3.5 transition hover:border-emerald-400/30"
                            >
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-sm">
                                    {{ withIcons[index] ?? '✓' }}
                                </span>
                                <span class="text-sm leading-relaxed text-emerald-50/95">{{ item }}</span>
                            </li>
                        </ul>

                        <div class="mt-6 rounded-2xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-center">
                            <p class="text-sm font-bold text-emerald-200">
                                {{ lossComparison.with?.summary }}
                            </p>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                            <Link
                                :href="primaryCtaUrl"
                                class="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-5 py-3.5 text-sm font-bold text-black shadow-lg shadow-amber-900/40 transition hover:from-amber-400 hover:to-yellow-400"
                            >
                                {{ primaryCtaLabel }}
                            </Link>
                            <button
                                v-if="fraudCheckEnabled"
                                type="button"
                                class="inline-flex flex-1 items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                                @click="scrollToFraudCheck"
                            >
                                Free fraud check করুন
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
