<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    whatsappUrl: { type: String, default: null },
    activeNav: { type: String, default: null },
    variant: { type: String, default: 'dark' },
});

const mobileOpen = ref(false);

const navLinks = [
    { label: 'হোম', href: '/', key: 'home' },
    { label: 'ফিচার', href: '/#features', key: 'features' },
    { label: 'প্রাইসিং', href: route('pricing'), key: 'pricing' },
    { label: 'অ্যাপ', href: '/#download-app', key: 'app' },
];

const isDark = computed(() => props.variant === 'dark');

const primaryCtaUrl = computed(
    () => props.whatsappUrl || (props.canLogin ? route('login') : route('pricing')),
);
const primaryCtaLabel = computed(() =>
    props.whatsappUrl ? 'ফ্রি ট্রায়াল নিন' : 'এখনই শুরু করুন',
);
const primaryCtaExternal = computed(() => Boolean(props.whatsappUrl));
</script>

<template>
    <div
        class="min-h-screen"
        :class="isDark ? 'bg-[#070b16] text-slate-100' : 'bg-slate-50 text-slate-900'"
        lang="bn"
    >
        <header
            class="sticky top-0 z-40 border-b backdrop-blur-xl"
            :class="isDark ? 'border-white/10 bg-[#070b16]/90' : 'border-slate-200/80 bg-white/95'"
        >
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl ring-1"
                        :class="isDark ? 'bg-violet-500/10 ring-violet-400/20' : 'bg-primary-50 ring-primary-100'"
                    >
                        <img src="/app-logo" alt="" class="h-6 w-6 object-contain" />
                    </div>
                    <div class="leading-tight">
                        <span class="block text-sm font-bold tracking-tight">WooEasyLife</span>
                        <span
                            class="block text-[11px] font-medium"
                            :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                        >
                            WPSaleHub প্ল্যাটফর্ম
                        </span>
                    </div>
                </Link>

                <div class="hidden items-center gap-7 md:flex">
                    <Link
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        class="text-sm font-medium transition"
                        :class="activeNav === link.key
                            ? 'text-violet-400'
                            : isDark
                                ? 'text-slate-300 hover:text-white'
                                : 'text-slate-600 hover:text-primary-600'"
                    >
                        {{ link.label }}
                    </Link>
                </div>

                <div class="hidden items-center gap-2 md:flex">
                    <Link
                        v-if="canLogin && !whatsappUrl"
                        :href="route('login')"
                        class="text-sm font-medium transition"
                        :class="isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600'"
                    >
                        লগইন
                    </Link>
                    <a
                        :href="primaryCtaUrl"
                        :target="primaryCtaExternal ? '_blank' : undefined"
                        :rel="primaryCtaExternal ? 'noopener noreferrer' : undefined"
                        class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-violet-900/40 transition hover:bg-violet-500"
                    >
                        {{ primaryCtaLabel }}
                    </a>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 md:hidden"
                    :class="isDark ? 'text-slate-300 hover:bg-white/5' : 'text-slate-600 hover:bg-slate-100'"
                    aria-label="মেনু"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </nav>

            <div
                v-show="mobileOpen"
                class="border-t px-4 py-4 md:hidden"
                :class="isDark ? 'border-white/10 bg-[#0c1222]' : 'border-slate-200 bg-white'"
            >
                <Link
                    v-for="link in navLinks"
                    :key="link.key"
                    :href="link.href"
                    class="block rounded-lg px-3 py-2.5 text-sm font-medium"
                    :class="isDark ? 'text-slate-200' : 'text-slate-700'"
                    @click="mobileOpen = false"
                >
                    {{ link.label }}
                </Link>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="border-t py-12" :class="isDark ? 'border-white/10 bg-[#050810]' : 'border-slate-800 bg-slate-950'">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 md:grid-cols-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <img src="/app-logo" alt="" class="h-8 w-8 rounded-lg object-contain" />
                            <span class="font-bold text-white">WooEasyLife</span>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">
                            বাংলাদেশের WooCommerce মার্চেন্টদের জন্য ফ্রড চেক, কুরিয়ার অটোমেশন ও অর্ডার ম্যানেজমেন্ট প্ল্যাটফর্ম।
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">লিংক</p>
                        <div class="mt-3 flex flex-col gap-2 text-sm text-slate-400">
                            <Link :href="route('pricing')" class="hover:text-white">প্রাইসিং</Link>
                            <a href="/#features" class="hover:text-white">ফিচার</a>
                            <a href="/#download-app" class="hover:text-white">মোবাইল অ্যাপ</a>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">আইনি</p>
                        <div class="mt-3 flex flex-col gap-2 text-sm text-slate-400">
                            <a :href="route('wooeasylife.app.privacy-policy')" class="hover:text-white">প্রাইভেসি পলিসি</a>
                            <a :href="route('wooeasylife.app.terms-of-service')" class="hover:text-white">সেবার শর্তাবলী</a>
                        </div>
                    </div>
                </div>
                <p class="mt-10 text-center text-xs text-slate-500">
                    &copy; {{ new Date().getFullYear() }} WPSaleHub. সর্বস্বত্ব সংরক্ষিত।
                </p>
            </div>
        </footer>

        <a
            v-if="whatsappUrl"
            :href="whatsappUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-20 right-4 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-xl shadow-emerald-900/50 transition hover:scale-105 hover:bg-emerald-400 md:bottom-6"
            aria-label="হোয়াটসঅ্যাপে যোগাযোগ"
        >
            <svg class="h-7 w-7" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.529 5.86L0 24l6.335-1.662A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.82a9.82 9.82 0 01-5.01-1.37l-.36-.214-3.76.987 1.004-3.66-.234-.375A9.82 9.82 0 1112 21.82z" />
            </svg>
        </a>
    </div>
</template>
