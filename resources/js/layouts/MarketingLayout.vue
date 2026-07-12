<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { primaryCtaLabel, primaryCtaShortLabel, primaryCtaUrl, merchantLoginHref, merchantLoginLabel } from '@/utils/marketingCta';
import '../../css/marketing.css';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    whatsappUrl: { type: String, default: null },
    activeNav: { type: String, default: null },
    variant: { type: String, default: 'dark' },
    /** Hide floating WhatsApp FAB on mobile when landing sticky CTA is shown */
    suppressMobileWhatsappFab: { type: Boolean, default: false },
});

const page = usePage();
const mobileOpen = ref(false);

const marketing = computed(() => page.props.marketing ?? {});

const announcement = computed(() => marketing.value.announcement ?? {});
const announcementMessages = computed(() =>
    announcement.value.enabled === false ? [] : (announcement.value.messages ?? []),
);

const pricingNavHref = computed(() =>
    props.activeNav === 'home' ? '/#pricing' : route('pricing'),
);

const navLinks = computed(() => [
    { label: 'হোম', href: '/', key: 'home', anchor: false },
    { label: 'ফিচার', href: '/#features', key: 'features', anchor: true },
    { label: 'ফ্রড চেক', href: '/#fraud-check', key: 'fraud-check', anchor: true },
    { label: 'প্রাইসিং', href: pricingNavHref.value, key: 'pricing', anchor: props.activeNav === 'home' },
    { label: 'অ্যাপ', href: '/#download-app', key: 'app', anchor: true },
    { label: 'FAQ', href: '/#faq', key: 'faq', anchor: true },
]);

const footerProductLinks = [
    { label: 'প্রাইসিং', href: route('pricing') },
    { label: 'ফিচার', href: '/#features' },
    { label: 'ফ্রি ফ্রড চেক', href: '/#fraud-check' },
    { label: 'মোবাইল অ্যাপ', href: '/#download-app' },
    { label: 'কিভাবে কাজ করে', href: '/#how-it-works' },
];

const isDark = computed(() => props.variant === 'dark');

const contactUrl = computed(() => props.whatsappUrl || marketing.value.whatsapp_url || null);

const headerCtaUrl = computed(() => primaryCtaUrl());
const headerCtaLabel = computed(() => primaryCtaLabel());
const headerCtaShortLabel = computed(() => primaryCtaShortLabel());
const merchantLoginLink = computed(() => merchantLoginHref(page.props.auth));
const merchantLoginText = computed(() => merchantLoginLabel(page.props.auth));

const helplineDisplay = computed(() => {
    const phone = marketing.value.helpline;

    if (!phone) {
        return null;
    }

    const digits = String(phone).replace(/\D/g, '');

    if (digits.length === 13 && digits.startsWith('880')) {
        return `0${digits.slice(2, 7)}-${digits.slice(7)}`;
    }

    if (digits.length === 11) {
        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    }

    return phone;
});

const navLinkClass = (key) => {
    const active = props.activeNav === key;

    if (active) {
        return isDark.value
            ? 'rounded-lg bg-white/10 px-3 py-1.5 text-amber-300'
            : 'rounded-lg bg-primary-50 px-3 py-1.5 text-primary-700';
    }

    return isDark.value
        ? 'px-1 py-1.5 text-slate-300 transition hover:text-white'
        : 'px-1 py-1.5 text-slate-600 transition hover:text-primary-600';
};

const trustBadgeIcon = (icon) => {
    const icons = {
        check: 'M5 13l4 4L19 7',
        clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        lock: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    };

    return icons[icon] ?? icons.check;
};

const closeMobile = () => {
    mobileOpen.value = false;
};

watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <div
        class="marketing-page min-h-screen"
        :class="isDark ? 'bg-brand-black text-slate-100' : 'bg-slate-50 text-slate-900'"
        lang="bn"
    >
        <!-- Announcement / offer bar -->
        <div
            v-if="announcementMessages.length"
            class="overflow-hidden border-b py-2 text-xs font-medium"
            :class="isDark ? 'border-amber-500/20 bg-amber-500/10 text-amber-200' : 'border-primary-700 bg-primary-600 text-white'"
        >
            <div class="marketing-marquee flex w-max gap-10 whitespace-nowrap pl-10">
                <span
                    v-for="(msg, i) in [...announcementMessages, ...announcementMessages]"
                    :key="i"
                    class="inline-flex items-center gap-2"
                >
                    <span class="text-amber-400" aria-hidden="true">✦</span>
                    {{ msg }}
                </span>
            </div>
        </div>

        <header
            class="sticky top-0 z-40 relative border-b backdrop-blur-xl supports-[backdrop-filter]:backdrop-blur-xl"
            :class="isDark
                ? 'border-white/5 bg-[#0a0a0a]/95 shadow-sm shadow-black/20'
                : 'border-slate-200/80 bg-white/95'"
            style="padding-top: env(safe-area-inset-top, 0px);"
        >
            <nav class="relative mx-auto flex max-w-7xl items-center justify-between gap-2 px-3 py-2.5 sm:gap-4 sm:px-6 sm:py-3 lg:px-8">
                <Link href="/" class="flex min-w-0 shrink items-center gap-2 sm:gap-2.5">
                    <img
                        src="/app-logo"
                        alt="WooEasyLife"
                        class="h-9 w-9 shrink-0 rounded-full object-cover sm:h-10 sm:w-10"
                    />
                    <span
                        class="truncate text-sm font-bold tracking-tight sm:text-base"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        WooEasyLife
                    </span>
                </Link>

                <div class="hidden flex-1 items-center justify-center gap-1 lg:flex xl:gap-2">
                    <component
                        :is="link.anchor ? 'a' : Link"
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        class="text-sm font-medium"
                        :class="navLinkClass(link.key)"
                    >
                        {{ link.label }}
                    </component>
                </div>

                <div class="hidden shrink-0 items-center gap-2 lg:flex">
                    <Link
                        v-if="canLogin"
                        :href="merchantLoginLink"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="isDark ? 'text-slate-300 hover:bg-white/5 hover:text-white' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        {{ merchantLoginText }}
                    </Link>
                    <Link
                        :href="headerCtaUrl"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-4 py-2.5 text-sm font-bold text-black shadow-lg shadow-amber-900/40 transition hover:from-amber-400 hover:to-yellow-400"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        {{ headerCtaLabel }}
                    </Link>
                </div>

                <!-- Mobile: compact CTA + menu -->
                <div class="flex shrink-0 items-center gap-1.5 sm:gap-2 lg:hidden">
                    <Link
                        :href="headerCtaUrl"
                        class="inline-flex min-h-11 max-w-[8.5rem] items-center justify-center rounded-lg bg-gradient-to-r from-amber-500 to-yellow-500 px-3 py-2.5 text-xs font-bold text-black shadow-md shadow-amber-900/30 sm:max-w-none sm:px-3.5 sm:text-sm"
                    >
                        <span class="truncate">{{ headerCtaShortLabel }}</span>
                    </Link>
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border transition"
                        :class="isDark
                            ? 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-nav-panel"
                        aria-label="মেনু খুলুন"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg v-if="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </nav>

            <!-- Mobile menu backdrop -->
            <div
                v-show="mobileOpen"
                class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="closeMobile"
            />

            <!-- Mobile menu panel -->
            <div
                v-show="mobileOpen"
                id="mobile-nav-panel"
                class="absolute left-0 right-0 top-full z-50 max-h-[min(32rem,calc(100dvh-3.5rem))] overflow-y-auto border-t px-3 py-4 shadow-2xl lg:hidden sm:px-4"
                :class="isDark ? 'border-white/10 bg-[#111111]' : 'border-slate-200 bg-white'"
                style="padding-bottom: env(safe-area-inset-bottom, 0px);"
            >
                <div class="grid grid-cols-2 gap-2">
                    <component
                        :is="link.anchor ? 'a' : Link"
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        class="flex items-center justify-center rounded-xl border px-3 py-3 text-center text-sm font-medium transition"
                        :class="[
                            activeNav === link.key
                                ? isDark
                                    ? 'border-amber-500/40 bg-amber-500/15 text-amber-200'
                                    : 'border-primary-200 bg-primary-50 text-primary-700'
                                : isDark
                                    ? 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
                                    : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
                        ]"
                        @click="closeMobile"
                    >
                        {{ link.label }}
                    </component>
                </div>
                <div
                    class="mt-4 flex flex-col gap-2 border-t pt-4"
                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                >
                    <Link
                        v-if="canLogin"
                        :href="merchantLoginLink"
                        class="rounded-xl border px-4 py-3 text-center text-sm font-semibold transition"
                        :class="isDark
                            ? 'border-white/10 text-white hover:bg-white/5'
                            : 'border-slate-200 text-slate-800 hover:bg-slate-50'"
                        @click="closeMobile"
                    >
                        {{ merchantLoginText }}
                    </Link>
                    <Link
                        :href="headerCtaUrl"
                        class="rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-4 py-3 text-center text-sm font-bold text-black shadow-lg shadow-amber-900/30"
                        @click="closeMobile"
                    >
                        {{ headerCtaLabel }}
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="border-t" :class="isDark ? 'border-white/10 bg-[#080808]' : 'border-slate-800 bg-slate-950'">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Brand -->
                    <div class="sm:col-span-2 lg:col-span-1">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 ring-1 ring-amber-400/25">
                                <img src="/app-logo" alt="WooEasyLife" class="h-6 w-6 object-contain" />
                            </div>
                            <span class="text-lg font-bold text-white">WooEasyLife</span>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-slate-300">
                            {{ marketing.footer_tagline }}
                        </p>
                        <p v-if="marketing.footer_tagline_en" class="mt-3 text-xs leading-relaxed text-slate-500">
                            {{ marketing.footer_tagline_en }}
                        </p>
                        <div v-if="marketing.trust_badges?.length" class="mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="badge in marketing.trust_badges"
                                :key="badge.label"
                                class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-slate-300"
                            >
                                <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="trustBadgeIcon(badge.icon)" />
                                </svg>
                                {{ badge.label }}
                            </span>
                        </div>
                    </div>

                    <!-- Product -->
                    <div>
                        <p class="text-sm font-bold text-white">প্রোডাক্ট</p>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <component
                                :is="link.href.startsWith('/#') ? 'a' : Link"
                                v-for="link in footerProductLinks"
                                :key="link.label"
                                :href="link.href"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                {{ link.label }}
                            </component>
                        </div>
                    </div>

                    <!-- Account -->
                    <div>
                        <p class="text-sm font-bold text-white">অ্যাকাউন্ট</p>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <Link
                                v-if="canLogin"
                                :href="merchantLoginLink"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                {{ merchantLoginText }}
                            </Link>
                            <Link
                                :href="route('pricing')"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                ফ্রি ট্রায়াল / প্ল্যান
                            </Link>
                            <a
                                v-if="contactUrl"
                                :href="contactUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                কন্টাক্ট
                            </a>
                        </div>
                    </div>

                    <!-- Legal -->
                    <div>
                        <p class="text-sm font-bold text-white">আইনী তথ্য</p>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <a
                                :href="route('wooeasylife.app.terms-of-service')"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                শর্তাবলী
                            </a>
                            <a
                                :href="route('wooeasylife.app.privacy-policy')"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                গোপনীয়তা নীতি
                            </a>
                            <p v-if="helplineDisplay" class="text-sm text-slate-400">
                                হেল্পলাইন:
                                <a
                                    :href="`tel:${marketing.helpline}`"
                                    class="text-slate-300 transition hover:text-white"
                                >
                                    {{ helplineDisplay }}
                                </a>
                            </p>
                            <p v-if="marketing.location" class="text-sm text-slate-500">
                                {{ marketing.location }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-8 sm:flex-row">
                    <p class="text-xs text-slate-500">
                        &copy; {{ new Date().getFullYear() }} WPSaleHub · WooEasyLife. সর্বস্বত্ব সংরক্ষিত।
                    </p>
                    <p class="text-xs text-slate-600">
                        WPSaleHub প্ল্যাটফর্ম
                    </p>
                </div>
            </div>
        </footer>

        <a
            v-if="contactUrl"
            :href="contactUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed right-4 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-xl shadow-emerald-900/50 transition hover:scale-105 hover:bg-emerald-400 md:bottom-6"
            :class="suppressMobileWhatsappFab ? 'bottom-6 hidden md:flex' : 'bottom-20 md:bottom-6'"
            aria-label="হোয়াটসঅ্যাপে যোগাযোগ"
        >
            <svg class="h-7 w-7" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.529 5.86L0 24l6.335-1.662A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.82a9.82 9.82 0 01-5.01-1.37l-.36-.214-3.76.987 1.004-3.66-.234-.375A9.82 9.82 0 1112 21.82z" />
            </svg>
        </a>
    </div>
</template>

<style scoped>
.marketing-marquee {
    animation: marketing-marquee-scroll 32s linear infinite;
}

.marketing-marquee:hover {
    animation-play-state: paused;
}

@keyframes marketing-marquee-scroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .marketing-marquee {
        animation: none;
    }
}
</style>
