<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { primaryCtaLabel, primaryCtaShortLabel, primaryCtaUrl, merchantLoginHref, merchantLoginLabel } from '@/utils/marketingCta';
import {
    attachScrollDepthTracking,
    trackContact,
    trackCtaClick,
    trackOnce,
    trackViewContent,
} from '@/utils/metaPixel';
import {
    attachSiteEngagementTracking,
    attachSiteScrollDepthTracking,
    trackCta as trackSiteCta,
    trackPageView as trackSitePageView,
} from '@/utils/siteVisitors';
import SeoContentSections from '@/components/marketing/SeoContentSections.vue';
import '../../css/marketing.css';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    whatsappUrl: { type: String, default: null },
    activeNav: { type: String, default: null },
    variant: { type: String, default: 'dark' },
    /** Hide floating WhatsApp FAB on mobile when landing sticky CTA is shown */
    suppressMobileWhatsappFab: { type: Boolean, default: false },
    /** Cluster/long-form pages already render content_sections in-body — skip the footer dump */
    suppressSeoContentSections: { type: Boolean, default: false },
});

const page = usePage();
const mobileOpen = ref(false);

const marketing = computed(() => page.props.marketing ?? {});
const seoContentSections = computed(() => (
    props.suppressSeoContentSections ? [] : (page.props.seo?.content_sections ?? [])
));

const announcement = computed(() => marketing.value.announcement ?? {});
const announcementMessages = computed(() =>
    announcement.value.enabled === false ? [] : (announcement.value.messages ?? []),
);

const pricingNavHref = computed(() => {
    if (props.activeNav !== 'home') {
        return route('pricing');
    }
    return isEnLocale.value ? '/en#pricing' : '/#pricing';
});

/** Distinct public tools for the Tools dropdown (locale-aware). */
const toolLinks = computed(() =>
    isEnLocale.value
        ? [
            { label: 'WooCommerce Bangladesh Guide', href: '/en/woocommerce-bangladesh' },
            { label: 'Return Loss Calculator', href: '/en/return-loss-calculator' },
            { label: 'Courier Charge Calculator', href: '/en/courier-charge-calculator' },
            { label: 'Ads ROAS Calculator', href: '/en/ads-roas-calculator' },
            { label: 'Free Fraud Checker', href: '/en/bd-fraud-checker' },
            { label: 'Fake Order Protection', href: '/en/fake-order-protection' },
            { label: 'Courier Auto Entry', href: '/en/courier-auto-entry' },
            { label: 'How to Stop Fake Orders', href: '/en/ki-vabe-fake-order-atkabo' },
        ]
        : [
            { label: 'WooCommerce Bangladesh গাইড', href: '/woocommerce-bangladesh' },
            { label: 'রিটার্ন লস ক্যালকুলেটর', href: '/return-loss-calculator' },
            { label: 'কুরিয়ার চার্জ ক্যালকুলেটর', href: '/courier-charge-calculator' },
            { label: 'Ads ROAS ক্যালকুলেটর', href: '/ads-roas-calculator' },
            { label: 'ফ্রি ফ্রড চেকার', href: '/bd-fraud-checker' },
            { label: 'ফেক অর্ডার প্রোটেকশন', href: '/fake-order-protection' },
            { label: 'কুরিয়ার অটো এন্ট্রি', href: '/courier-auto-entry' },
            { label: 'কিভাবে ফেক অর্ডার আটকাবো', href: '/ki-vabe-fake-order-atkabo' },
        ],
);

const toolsOpen = ref(false);
const mobileToolsOpen = ref(false);
const toolsMenuRef = ref(null);

const currentPath = computed(() => {
    const raw = page.url?.split('?')[0] || '';
    return raw.endsWith('/') && raw.length > 1 ? raw.slice(0, -1) : raw;
});

const isEnLocale = computed(() =>
    String(page.props.seo?.html_lang || '').startsWith('en')
    || currentPath.value === '/en'
    || currentPath.value.startsWith('/en/'),
);

const homeHref = computed(() => (isEnLocale.value ? '/en' : '/'));

const homeAnchor = (hash) => (isEnLocale.value ? `/en#${hash}` : `/#${hash}`);

const isToolsActive = computed(() => {
    if (props.activeNav === 'tools' || props.activeNav === 'fraud-check') {
        return true;
    }

    return toolLinks.value.some((link) => {
        try {
            const path = new URL(link.href, 'https://example.com').pathname;
            return currentPath.value === path || currentPath.value === path.replace(/\/$/, '');
        } catch {
            return false;
        }
    });
});

const navLinks = computed(() => [
    { label: isEnLocale.value ? 'Home' : 'হোম', href: homeHref.value, key: 'home', anchor: false },
    { label: isEnLocale.value ? 'Features' : 'ফিচার', href: homeAnchor('features'), key: 'features', anchor: true },
    { label: isEnLocale.value ? 'Pricing' : 'প্রাইসিং', href: pricingNavHref.value, key: 'pricing', anchor: props.activeNav === 'home' },
    { label: isEnLocale.value ? 'App' : 'অ্যাপ', href: homeAnchor('download-app'), key: 'app', anchor: true },
    { label: isEnLocale.value ? 'Downloads' : 'ডাউনলোড', href: homeAnchor('downloads'), key: 'downloads', anchor: true },
    { label: 'FAQ', href: '/faq', key: 'faq', anchor: false },
]);

const closeToolsMenu = () => {
    toolsOpen.value = false;
};

const toggleToolsMenu = () => {
    toolsOpen.value = !toolsOpen.value;
};

const onDocumentClick = (event) => {
    if (!toolsOpen.value || !toolsMenuRef.value) {
        return;
    }

    if (!toolsMenuRef.value.contains(event.target)) {
        closeToolsMenu();
    }
};

const onDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        closeToolsMenu();
        mobileToolsOpen.value = false;
    }
};

/**
 * Sitewide footer links = full sitemap (minus home). Fixes Ahrefs “Orphaned sitemap pages”.
 */
const footerProductLinks = computed(() => {
    const fromServer = Array.isArray(page.props.sitemapNavLinks) ? page.props.sitemapNavLinks : [];
    const links = fromServer.map((link) => ({
        label: link.label,
        href: link.href,
    }));

    if (! isEnLocale.value) {
        links.push(
            { label: 'মোবাইল অ্যাপ', href: '/#download-app' },
            { label: 'ডাউনলোড', href: '/#downloads' },
        );
    }

    return links;
});

const isDark = computed(() => props.variant === 'dark');

const contactUrl = computed(() => props.whatsappUrl || marketing.value.whatsapp_url || null);

const headerCtaUrl = computed(() => primaryCtaUrl());
const headerCtaLabel = computed(() => primaryCtaLabel(isEnLocale.value ? 'en' : 'bn'));
const headerCtaShortLabel = computed(() => primaryCtaShortLabel(isEnLocale.value ? 'en' : 'bn'));
const merchantLoginLink = computed(() => merchantLoginHref(page.props.auth));
const merchantLoginText = computed(() => merchantLoginLabel(page.props.auth, isEnLocale.value ? 'en' : 'bn'));

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
    mobileToolsOpen.value = false;
    closeToolsMenu();
};

const prefersReducedMotion = () =>
    typeof window !== 'undefined'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const smoothScrollToId = (hash, { updateUrl = true } = {}) => {
    if (typeof window === 'undefined') {
        return false;
    }

    const id = String(hash || '').replace(/^#/, '');
    if (!id) {
        return false;
    }

    const el = document.getElementById(id);
    if (!el) {
        return false;
    }

    el.scrollIntoView({
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        block: 'start',
    });

    if (updateUrl) {
        const next = `${window.location.pathname}${window.location.search}#${id}`;
        window.history.replaceState(window.history.state, '', next);
    }

    return true;
};

const onAnchorNavClick = (event, href) => {
    if (typeof window === 'undefined' || !href?.includes('#')) {
        return;
    }

    let url;
    try {
        url = new URL(href, window.location.origin);
    } catch {
        return;
    }

    if (url.origin !== window.location.origin) {
        return;
    }

    const onHome = window.location.pathname === '/' || window.location.pathname === '';
    const targetIsHome = url.pathname === '/' || url.pathname === '';

    if (onHome && targetIsHome && url.hash) {
        event.preventDefault();
        closeMobile();
        smoothScrollToId(url.hash);
    }
};

const onHeaderCtaClick = (location) => {
    trackCtaClick({
        location,
        label: headerCtaLabel.value,
        href: headerCtaUrl.value,
    });
    const path = page.url?.split('?')[0] || window.location.pathname;
    trackSiteCta(path, headerCtaLabel.value || location || 'header_cta');
};

const onContactClick = (method) => {
    trackContact({ method, content_name: method });
};

let detachScroll = () => {};
let detachSiteScroll = () => {};
let detachSiteEngagement = () => {};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);

    const path = page.url?.split('?')[0] || window.location.pathname;
    const contentName = page.props.seo?.title
        || (typeof document !== 'undefined' ? document.title : path);

    trackSitePageView(path);
    detachSiteScroll = attachSiteScrollDepthTracking(path);
    detachSiteEngagement = attachSiteEngagementTracking(path);

    trackOnce(`viewcontent:page:${path}`, () =>
        trackViewContent({
            content_name: contentName,
            content_category: props.activeNav || 'marketing',
            content_type: 'page',
        }),
    );

    detachScroll = attachScrollDepthTracking(path);

    if (window.location.hash) {
        window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                smoothScrollToId(window.location.hash, { updateUrl: false });
            }, 80);
        });
    }
});

watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
    detachScroll();
    detachSiteScroll();
    detachSiteEngagement();
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
                <Link :href="homeHref" class="flex min-w-0 shrink items-center gap-2 sm:gap-2.5">
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
                        v-for="link in navLinks.filter((l) => l.key === 'home' || l.key === 'features')"
                        :key="link.key"
                        :href="link.href"
                        class="text-sm font-medium"
                        :class="navLinkClass(link.key)"
                        @click="link.anchor ? onAnchorNavClick($event, link.href) : undefined"
                    >
                        {{ link.label }}
                    </component>

                    <!-- Tools dropdown -->
                    <div ref="toolsMenuRef" class="relative">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-sm font-medium"
                            :class="isToolsActive
                                ? (isDark ? 'rounded-lg bg-white/10 px-3 py-1.5 text-amber-300' : 'rounded-lg bg-primary-50 px-3 py-1.5 text-primary-700')
                                : (isDark ? 'px-1 py-1.5 text-slate-300 transition hover:text-white' : 'px-1 py-1.5 text-slate-600 transition hover:text-primary-600')"
                            :aria-expanded="toolsOpen"
                            aria-haspopup="true"
                            @click.stop="toggleToolsMenu"
                        >
                            Tools
                            <svg
                                class="h-3.5 w-3.5 transition"
                                :class="toolsOpen ? 'rotate-180' : ''"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            v-show="toolsOpen"
                            class="absolute left-1/2 top-full z-50 mt-2 w-72 -translate-x-1/2 overflow-hidden rounded-xl border shadow-2xl"
                            :class="isDark ? 'border-white/10 bg-[#111111]' : 'border-slate-200 bg-white'"
                            role="menu"
                        >
                            <div class="max-h-[min(24rem,70vh)] overflow-y-auto py-2">
                                <Link
                                    v-for="tool in toolLinks"
                                    :key="tool.href"
                                    :href="tool.href"
                                    role="menuitem"
                                    class="block px-4 py-2.5 text-sm transition"
                                    :class="isDark
                                        ? 'text-slate-200 hover:bg-white/10 hover:text-amber-300'
                                        : 'text-slate-700 hover:bg-slate-50 hover:text-primary-700'"
                                    @click="closeToolsMenu"
                                >
                                    {{ tool.label }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <component
                        :is="link.anchor ? 'a' : Link"
                        v-for="link in navLinks.filter((l) => l.key !== 'home' && l.key !== 'features')"
                        :key="link.key"
                        :href="link.href"
                        class="text-sm font-medium"
                        :class="navLinkClass(link.key)"
                        @click="link.anchor ? onAnchorNavClick($event, link.href) : undefined"
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
                        @click="onHeaderCtaClick('header_cta')"
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
                        @click="onHeaderCtaClick('header_cta_mobile')"
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
                        @click="link.anchor ? onAnchorNavClick($event, link.href) : closeMobile()"
                    >
                        {{ link.label }}
                    </component>
                </div>

                <div
                    class="mt-3 overflow-hidden rounded-xl border"
                    :class="[
                        isToolsActive
                            ? (isDark ? 'border-amber-500/40' : 'border-primary-200')
                            : (isDark ? 'border-white/10' : 'border-slate-200'),
                    ]"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold"
                        :class="isToolsActive
                            ? (isDark ? 'bg-amber-500/15 text-amber-200' : 'bg-primary-50 text-primary-700')
                            : (isDark ? 'bg-white/5 text-slate-200' : 'bg-slate-50 text-slate-800')"
                        :aria-expanded="mobileToolsOpen"
                        @click="mobileToolsOpen = !mobileToolsOpen"
                    >
                        <span>Tools</span>
                        <svg
                            class="h-4 w-4 transition"
                            :class="mobileToolsOpen ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div
                        v-show="mobileToolsOpen"
                        class="max-h-64 space-y-1 overflow-y-auto border-t px-2 py-2"
                        :class="isDark ? 'border-white/10 bg-[#0a0a0a]' : 'border-slate-200 bg-white'"
                    >
                        <Link
                            v-for="tool in toolLinks"
                            :key="tool.href"
                            :href="tool.href"
                            class="flex min-h-11 items-center rounded-lg px-3 py-2.5 text-sm transition"
                            :class="isDark
                                ? 'text-slate-300 hover:bg-white/10 hover:text-amber-300'
                                : 'text-slate-700 hover:bg-slate-50 hover:text-primary-700'"
                            @click="closeMobile"
                        >
                            {{ tool.label }}
                        </Link>
                    </div>
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
                        @click="onHeaderCtaClick('header_cta_mobile_panel'); closeMobile()"
                    >
                        {{ headerCtaLabel }}
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <slot />
            <SeoContentSections :sections="seoContentSections" />
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
                        <p class="text-sm font-bold text-white">{{ isEnLocale ? 'Site pages' : 'সাইট পেজ' }}</p>
                        <div class="mt-4 flex max-h-80 flex-col gap-1.5 overflow-y-auto pr-1">
                            <component
                                :is="link.href.startsWith('/#') ? 'a' : Link"
                                v-for="link in footerProductLinks"
                                :key="link.href + link.label"
                                :href="link.href"
                                class="text-xs text-slate-400 transition hover:text-white"
                                @click="link.href.startsWith('/#') ? onAnchorNavClick($event, link.href) : undefined"
                            >
                                {{ link.label }}
                            </component>
                        </div>
                    </div>

                    <!-- Account -->
                    <div>
                        <p class="text-sm font-bold text-white">{{ isEnLocale ? 'Account' : 'অ্যাকাউন্ট' }}</p>
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
                                {{ isEnLocale ? 'Free trial / Plans' : 'ফ্রি ট্রায়াল / প্ল্যান' }}
                            </Link>
                            <a
                                v-if="contactUrl"
                                :href="contactUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-slate-400 transition hover:text-white"
                                @click="onContactClick('whatsapp_footer')"
                            >
                                {{ isEnLocale ? 'Contact' : 'কন্টাক্ট' }}
                            </a>
                        </div>
                    </div>

                    <!-- Legal -->
                    <div>
                        <p class="text-sm font-bold text-white">{{ isEnLocale ? 'Legal' : 'আইনী তথ্য' }}</p>
                        <div class="mt-4 flex flex-col gap-2.5">
                            <a
                                :href="route('wooeasylife.app.terms-of-service')"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                {{ isEnLocale ? 'Terms of Service' : 'শর্তাবলী' }}
                            </a>
                            <a
                                :href="route('wooeasylife.app.privacy-policy')"
                                class="text-sm text-slate-400 transition hover:text-white"
                            >
                                {{ isEnLocale ? 'Privacy Policy' : 'গোপনীয়তা নীতি' }}
                            </a>
                            <p v-if="helplineDisplay" class="text-sm text-slate-400">
                                {{ isEnLocale ? 'Helpline:' : 'হেল্পলাইন:' }}
                                <a
                                    :href="`tel:${marketing.helpline}`"
                                    class="text-slate-300 transition hover:text-white"
                                    @click="onContactClick('phone')"
                                >
                                    {{ helplineDisplay }}
                                </a>
                            </p>
                            <p v-if="marketing.admin_email" class="text-sm text-slate-400">
                                {{ isEnLocale ? 'Email:' : 'ইমেইল:' }}
                                <a
                                    :href="`mailto:${marketing.admin_email}`"
                                    class="text-slate-300 transition hover:text-white"
                                    @click="onContactClick('email')"
                                >
                                    {{ marketing.admin_email }}
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
                        &copy; {{ new Date().getFullYear() }} WooEasyLife · a WPSaleHub product · by Muhibbullah Ansary.
                    </p>
                    <p class="text-xs text-slate-600">
                        <Link href="/about" class="text-slate-400 transition hover:text-white">About</Link>
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
            style="margin-bottom: env(safe-area-inset-bottom, 0px);"
            aria-label="হোয়াটসঅ্যাপে যোগাযোগ"
            @click="onContactClick('whatsapp_fab')"
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
