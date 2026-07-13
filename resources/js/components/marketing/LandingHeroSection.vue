<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { trackCtaClick } from '@/utils/metaPixel';

const props = defineProps({
    hero: { type: Object, default: () => ({}) },
    heroBullets: { type: Array, default: () => [] },
    heroTrustBadges: { type: Array, default: () => [] },
    trialPlan: { type: Object, default: null },
    primaryCtaUrl: { type: String, required: true },
    primaryCtaLabel: { type: String, required: true },
    fraudCheckEnabled: { type: Boolean, default: true },
});

const badgeLabel = computed(() => {
    const raw = props.hero.badge || '';

    return raw.replace(/^[\p{Emoji_Presentation}\p{Extended_Pictographic}\uFE0F\s]+/u, '').trim() || raw;
});

const onHeroCta = (location, href, label) => {
    trackCtaClick({ location, href, label });
};
</script>

<template>
    <section
        class="landing-hero relative overflow-hidden pb-10 pt-8 sm:pb-14 sm:pt-12 lg:flex lg:min-h-[min(88svh,820px)] lg:items-center lg:pb-16 lg:pt-14"
    >
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(255,193,7,0.12),_transparent_55%)]" />
            <div class="landing-hero__glow absolute -right-24 top-16 h-64 w-64 rounded-full bg-amber-500/20 blur-3xl sm:h-[28rem] sm:w-[28rem]" />
            <div class="landing-hero__glow landing-hero__glow--delayed absolute bottom-10 left-[-4rem] h-48 w-48 rounded-full bg-amber-400/10 blur-3xl sm:h-72 sm:w-72" />
        </div>

        <div class="relative mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl lg:mx-0 lg:max-w-xl xl:max-w-2xl">
                <p class="landing-hero__item landing-hero__item--1 text-sm font-semibold tracking-[0.18em] text-amber-300/90 sm:text-base">
                    WooEasyLife
                </p>

                <span
                    v-if="badgeLabel"
                    class="landing-hero__item landing-hero__item--2 mt-3 inline-flex max-w-full items-center rounded-full border border-amber-400/25 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold text-amber-200/95 sm:mt-4 sm:text-xs"
                >
                    {{ badgeLabel }}
                </span>

                <h1 class="landing-hero__item landing-hero__item--3 mt-4 text-[1.7rem] font-extrabold leading-[1.35] tracking-tight text-white sm:mt-5 sm:text-4xl sm:leading-[1.28] lg:text-[2.75rem] lg:leading-[1.25]">
                    {{ hero.headline }}
                    <span
                        v-if="hero.headline_accent"
                        class="mt-2 block bg-gradient-to-r from-amber-300 via-yellow-200 to-amber-400 bg-clip-text pb-0.5 text-transparent"
                    >
                        {{ hero.headline_accent }}
                    </span>
                </h1>

                <p
                    v-if="hero.subheadline"
                    class="landing-hero__item landing-hero__item--4 mt-3 text-sm leading-relaxed text-slate-300 sm:mt-4 sm:text-base sm:leading-relaxed lg:text-lg"
                >
                    {{ hero.subheadline }}
                </p>

                <div class="landing-hero__item landing-hero__item--5 mt-6 flex flex-col gap-3 sm:mt-8 sm:flex-row sm:items-stretch">
                    <Link
                        :href="primaryCtaUrl"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-3.5 text-sm font-bold text-black shadow-xl shadow-amber-900/40 transition hover:from-amber-400 hover:to-yellow-400 active:scale-[0.99] sm:min-h-[3.25rem] sm:w-auto sm:px-8"
                        @click="onHeroCta('hero_primary', primaryCtaUrl, primaryCtaLabel)"
                    >
                        {{ primaryCtaLabel }}
                    </Link>
                    <Link
                        :href="route('pricing')"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/10 active:scale-[0.99] sm:min-h-[3.25rem] sm:w-auto sm:px-8"
                        @click="onHeroCta('hero_pricing', route('pricing'), 'প্যাকেজ ও মূল্য দেখুন')"
                    >
                        প্যাকেজ ও মূল্য দেখুন
                    </Link>
                </div>

                <a
                    v-if="fraudCheckEnabled"
                    href="#fraud-check"
                    class="landing-hero__item landing-hero__item--6 group mt-3 flex min-h-12 w-full items-center gap-3 rounded-xl border border-amber-500/25 bg-amber-500/[0.08] px-3.5 py-3 text-left transition hover:border-amber-400/40 hover:bg-amber-500/15 sm:mt-4 sm:px-4"
                    @click="onHeroCta('hero_fraud_anchor', '#fraud-check', 'ফ্রি ফ্রড চেক')"
                >
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/15 text-amber-300 transition group-hover:border-amber-400/50">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5.75C3 4.784 3.784 4 4.75 4h2.086c.4 0 .765.24.92.612l1.15 2.76a1 1 0 01-.232 1.11L7.5 9.66a12.04 12.04 0 006.84 6.84l1.178-1.174a1 1 0 011.11-.232l2.76 1.15c.372.155.612.52.612.92V19.25A1.75 1.75 0 0117.25 21h-.5C8.716 21 3 15.284 3 8.25v-.5z" />
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1 text-sm font-semibold leading-snug text-amber-200">
                        শুধু নম্বর দিন, কাস্টমারের কুরিয়ার হিস্টোরি দেখে নিন —
                        <span class="text-amber-400">একদম ফ্রি</span>
                    </span>
                    <svg class="hidden h-4 w-4 shrink-0 text-amber-400/80 transition group-hover:translate-x-0.5 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <ul
                    v-if="heroBullets.length"
                    class="landing-hero__item landing-hero__item--7 mt-6 space-y-2.5 sm:mt-7 sm:space-y-3"
                >
                    <li
                        v-for="(item, index) in heroBullets"
                        :key="item"
                        class="flex items-start gap-2.5 text-[13px] leading-snug text-slate-200/95 sm:text-sm sm:leading-relaxed"
                        :class="index > 1 ? 'hidden sm:flex' : ''"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-[11px] font-bold text-amber-300">✓</span>
                        <span>{{ item }}</span>
                    </li>
                </ul>

                <div class="landing-hero__item landing-hero__item--8 mt-5 flex flex-col gap-3 border-t border-white/10 pt-4 sm:mt-6 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-5 sm:gap-y-2">
                    <p v-if="trialPlan" class="text-xs text-slate-400 sm:text-sm">
                        <span class="font-medium text-slate-300">{{ trialPlan.title }}</span>
                        <span class="mx-1.5 text-slate-600">·</span>
                        {{ trialPlan.duration_label }}
                        <span class="mx-1.5 text-slate-600">·</span>
                        {{ trialPlan.price_label }}
                    </p>

                    <ul
                        v-if="heroTrustBadges.length"
                        class="flex flex-wrap gap-x-4 gap-y-2"
                    >
                        <li
                            v-for="badge in heroTrustBadges"
                            :key="badge"
                            class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400 sm:text-xs"
                        >
                            <svg class="h-3.5 w-3.5 text-emerald-400 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ badge }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>
