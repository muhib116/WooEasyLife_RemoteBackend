<template>
    <div class="flex min-h-svh bg-slate-950">
        <div
            class="relative hidden w-[44%] overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12 xl:w-[46%]"
        >
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_rgba(255,193,7,0.18),_transparent_55%)]" />
            <div class="pointer-events-none absolute -right-24 top-1/3 h-80 w-80 rounded-full bg-amber-500/10 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-amber-600/10 blur-3xl" />
            <div class="pointer-events-none absolute inset-0 opacity-[0.035] [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:24px_24px]" />

            <div class="relative">
                <Link href="/" class="group inline-flex items-center gap-3 transition hover:opacity-90">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/10 backdrop-blur">
                        <img src="/app-logo" alt="" class="h-8 w-8 object-contain" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-white">WooEasyLife</p>
                        <p class="text-xs text-amber-400/80">Merchant portal</p>
                    </div>
                </Link>
            </div>

            <div class="relative space-y-8">
                <div class="space-y-4">
                    <h1 class="max-w-lg text-3xl font-extrabold leading-tight tracking-tight text-white xl:text-4xl">
                        Run your store with
                        <span class="bg-gradient-to-r from-amber-300 via-yellow-200 to-amber-400 bg-clip-text text-transparent">
                            fraud checks, SMS
                        </span>
                        &amp; billing
                    </h1>
                    <p class="max-w-md text-sm leading-relaxed text-slate-400">
                        Manage websites, recharge packages, track orders, and give your team secure portal access — all in one place.
                    </p>
                </div>

                <ul class="space-y-3">
                    <li
                        v-for="feature in features"
                        :key="feature"
                        class="flex items-start gap-3 text-sm text-slate-300"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-xs text-amber-400">
                            ✓
                        </span>
                        <span>{{ feature }}</span>
                    </li>
                </ul>
            </div>

            <div class="relative flex items-center justify-between gap-4 text-xs text-slate-500">
                <p>&copy; {{ new Date().getFullYear() }} WooEasyLife</p>
                <Link
                    href="/"
                    class="font-medium text-slate-400 transition hover:text-amber-400"
                >
                    Back to website
                </Link>
            </div>
        </div>

        <div class="relative flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-8">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(255,193,7,0.06),_transparent_50%)] lg:hidden" />

            <div class="relative mb-8 flex w-full max-w-md items-center justify-between lg:hidden">
                <Link href="/" class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10">
                        <img src="/app-logo" alt="" class="h-7 w-7 object-contain" />
                    </div>
                    <div>
                        <p class="font-bold text-white">WooEasyLife</p>
                        <p class="text-xs text-slate-400">Merchant portal</p>
                    </div>
                </Link>
                <Link
                    href="/"
                    class="text-xs font-medium text-slate-400 transition hover:text-amber-400"
                >
                    Home
                </Link>
            </div>

            <div
                class="relative w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-black/30 backdrop-blur-sm sm:p-8"
            >
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const features = [
    'Website & package management',
    'SMS balance and billing recharge',
    'Free fraud check on customer numbers',
    'Team roles with portal permissions',
];
</script>
