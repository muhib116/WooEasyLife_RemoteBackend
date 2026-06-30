<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    hero: { type: Object, default: () => ({}) },
    heroBullets: { type: Array, default: () => [] },
    trialPlan: { type: Object, default: null },
    primaryCtaUrl: { type: String, required: true },
    primaryCtaLabel: { type: String, required: true },
    fraudCheckEnabled: { type: Boolean, default: true },
});
</script>

<template>
    <section class="scroll-reveal scroll-reveal-visible relative overflow-hidden pb-8 pt-10 sm:pb-12 sm:pt-14 lg:pt-16">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(255,193,7,0.12),_transparent_55%)]" />
        <div class="pointer-events-none absolute -right-32 top-20 h-72 w-72 rounded-full bg-amber-500/10 blur-3xl sm:h-96 sm:w-96" />

        <div class="relative mx-auto max-w-6xl px-4 lg:px-8">
            <div class="mx-auto max-w-3xl text-center lg:max-w-none lg:text-left">
                <span
                    v-if="hero.badge"
                    class="mb-4 inline-flex rounded-full border border-amber-400/30 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-300"
                >
                    {{ hero.badge }}
                </span>

                <h1 class="text-3xl font-extrabold leading-[1.49] tracking-tight text-white sm:text-4xl sm:leading-[1.40] lg:text-5xl lg:leading-[1.55]">
                    {{ hero.headline }}
                    <span
                        v-if="hero.headline_accent"
                        class="mt-1 block bg-gradient-to-r from-amber-300 via-yellow-200 to-amber-400 bg-clip-text pb-1 leading-[1] text-transparent sm:leading-[1.58] lg:leading-[1.55]"
                    >
                        {{ hero.headline_accent }}
                    </span>
                </h1>

                <p v-if="hero.subheadline" class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg lg:mx-0">
                    {{ hero.subheadline }}
                </p>

                <ul v-if="heroBullets.length" class="mx-auto mt-6 max-w-xl space-y-2.5 text-left lg:mx-0">
                    <li
                        v-for="item in heroBullets"
                        :key="item"
                        class="flex items-start gap-2.5 text-sm text-slate-200 sm:text-base"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">✓</span>
                        <span>{{ item }}</span>
                    </li>
                </ul>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <Link
                        :href="primaryCtaUrl"
                        class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-7 py-3.5 text-sm font-bold text-black shadow-xl shadow-amber-900/40 transition hover:from-amber-400 hover:to-yellow-400"
                    >
                        {{ primaryCtaLabel }}
                    </Link>
                    <Link
                        :href="route('pricing')"
                        class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                        প্যাকেজ ও মূল্য দেখুন
                    </Link>
                </div>

                <a
                    v-if="fraudCheckEnabled"
                    href="#fraud-check"
                    class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-amber-400 transition hover:text-amber-300 lg:justify-start"
                >
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/10">📞</span>
                    Free fraud check — number দিন, customer কেমন জেনে নিন
                </a>

                <p v-if="trialPlan" class="mt-4 text-sm text-slate-400">
                    {{ trialPlan.title }} · {{ trialPlan.duration_label }} · {{ trialPlan.price_label }}
                </p>
            </div>
        </div>
    </section>
</template>
