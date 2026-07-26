<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import ClusterGuideBlocks from '@/components/marketing/ClusterGuideBlocks.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { buildContentBlocks, injectFigureBlocks } from '@/utils/clusterContentBlocks';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const tocOpen = ref(false);
const showBackTop = ref(false);
const readProgress = ref(0);

const isEn = computed(() => (props.seo?.html_lang || '').startsWith('en'));
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel(isEn.value ? 'en' : 'bn'));
const isPillar = computed(() => Boolean(props.seo?.is_pillar));
const isAbout = computed(() => props.seo?.page_kind === 'about');
const sections = computed(() => props.seo?.content_sections || []);
const clusterLinks = computed(() => props.seo?.cluster_links || []);
const founderPortrait = computed(() => props.seo?.author_image || '/images/seo/about/founder-headshot.jpg');
const founderName = computed(() => props.seo?.author_name || 'Muhibbullah Ansary');
const founderRole = computed(() => props.seo?.author_role || 'Founder & CEO, WPSaleHub');

const isRedundantStubSection = (section) => {
    if (!section) return true;
    const heading = String(section.heading || '').trim();
    const paras = (section.paragraphs || []).map((p) => String(p || '').trim()).filter(Boolean);
    const list = (section.list || []).map((item) => String(item || '').trim()).filter(Boolean);

    // Keep "দ্রুত উত্তর / Quick answer" — Semrush AI content checks need that TL;DR visible.
    // Only drop the old landscape stub that repeated the chapter title with almost no body.
    if (/ভূমিকা এবং দেশের ই-কমার্স ল্যান্ডস্কেপ/i.test(heading)) {
        const joined = [...paras, ...list].join(' ');
        if (joined.length < 160) return true;
        if (paras.some((p) => p.startsWith(heading.slice(0, 18)) || /^ভূমিকা এবং/.test(p))) return true;
    }

    return false;
};

const isQuickAnswerSection = (section) => {
    const heading = String(section?.heading || '').toLowerCase();
    return heading.includes('দ্রুত') || heading.includes('quick');
};

const extractPart = (heading = '') => {
    const m = String(heading).match(
        /(?:অংশ|part|পার্ট)\s*(\d+)\s*\/\s*(?:৩০|30)|(\d+)\s*\/\s*(?:৩০|30)/i,
    );
    if (!m) return null;
    return Number(m[1] || m[2]);
};

const bodySections = computed(() => {
    let tocPosition = 0;

    return sections.value
        .filter((s) => !isRedundantStubSection(s))
        .map((s, idx) => {
            const part = extractPart(s.heading);
            const isQuickAnswer = isQuickAnswerSection(s);
            // Keep ItemList JSON-LD anchors in sync (SeoMetaService skips quick answers).
            let id = 'guide-section-quick';
            if (!isQuickAnswer) {
                tocPosition += 1;
                id = `guide-section-${tocPosition}`;
            }
            const paragraphs = (s.paragraphs || []).filter((p) => {
                const t = String(p || '').trim();
                // Drop truncated next-chapter title leaks: "পার্ট ২: … ("
                if (/^(?:পার্ট|অংশ|Part)\s*\d+/i.test(t) && t.endsWith('(')) return false;
                return t.length > 0;
            });
            const figuresForBlocks = s.layout === 'founder_hero' ? [] : (s.figures || []);
            // About pages are hand-written prose: keep paragraphs verbatim.
            // buildContentBlocks joins paragraphs (for pasted ASCII diagrams) and
            // would glue sentences together without spaces.
            const blocks = isAbout.value
                ? injectFigureBlocks(
                    paragraphs.map((p) => ({ type: 'paragraph', text: String(p).trim() })),
                    figuresForBlocks,
                )
                : buildContentBlocks(paragraphs, figuresForBlocks);
            return {
                ...s,
                paragraphs,
                list: (s.list || []).map((item) => String(item || '').trim()).filter(Boolean),
                id,
                part,
                blocks,
                tone: idx % 3,
                isQuickAnswer,
                layout: s.layout || null,
                founder_name: s.founder_name || null,
                founder_title: s.founder_title || null,
                founder_quote: s.founder_quote || null,
            };
        });
});

const tocItems = computed(() => bodySections.value
    .filter((s) => s.heading)
    .map((s) => ({
        id: s.id,
        label: s.heading,
        part: s.part,
    })));

const showToc = computed(() => !isAbout.value && tocItems.value.length >= 6);

const journeyGroups = computed(() => {
    if (isAbout.value || !isPillar.value || bodySections.value.length < 8) return [];

    const groups = isEn.value
        ? [
            { key: 'setup', label: 'Store & checkout', range: [1, 7], hint: 'Fast checkout, recovery, OTP' },
            { key: 'ops', label: 'Courier & ops', range: [8, 13], hint: 'App, tracking, LTV' },
            { key: 'ads', label: 'Ads & growth', range: [14, 22], hint: 'Pixel, Shopping, SEO' },
            { key: 'scale', label: 'Scale & future', range: [23, 30], hint: 'AI, marketplace, roadmap' },
        ]
        : [
            { key: 'setup', label: 'স্টোর ও চেকআউট', range: [1, 7], hint: 'ফাস্ট চেকআউট, রিকভারি, OTP' },
            { key: 'ops', label: 'কুরিয়ার ও অপস', range: [8, 13], hint: 'অ্যাপ, ট্র্যাকিং, LTV' },
            { key: 'ads', label: 'অ্যাডস ও গ্রোথ', range: [14, 22], hint: 'পিক্সেল, Shopping, SEO' },
            { key: 'scale', label: 'স্কেল ও ফিউচার', range: [23, 30], hint: 'AI, মার্কেটপ্লেস, রোডম্যাপ' },
        ];

    return groups.map((g) => {
        const first = bodySections.value.find((s) => s.part != null && s.part >= g.range[0] && s.part <= g.range[1]);
        return { ...g, targetId: first?.id || bodySections.value[0]?.id };
    });
});

const heroStats = computed(() => {
    if (isAbout.value || !isPillar.value) return [];
    return isEn.value
        ? [
            { value: '30', label: 'Guide parts' },
            { value: String(clusterLinks.value.length || '12'), label: 'Spoke pages' },
            { value: 'BN+EN', label: 'Languages' },
        ]
        : [
            { value: '৩০', label: 'গাইড অংশ' },
            { value: String(clusterLinks.value.length || '১২'), label: 'স্পোক পেজ' },
            { value: 'BN+EN', label: 'ভাষা' },
        ];
});

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const onScroll = () => {
    showBackTop.value = window.scrollY > 720;
    const doc = document.documentElement;
    const max = doc.scrollHeight - window.innerHeight;
    readProgress.value = max > 0 ? Math.min(100, Math.round((window.scrollY / max) * 100)) : 0;
};

const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const jumpTo = (id) => {
    tocOpen.value = false;
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout
        :can-login="canLogin"
        :whatsapp-url="whatsappUrl"
        active-nav="features"
        suppress-seo-content-sections
    >
        <!-- Reading progress -->
        <div
            class="pointer-events-none fixed inset-x-0 top-0 z-[45] h-0.5 bg-transparent"
            aria-hidden="true"
        >
            <div
                class="h-full bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-300 transition-[width] duration-150 ease-out"
                :style="{ width: `${readProgress}%` }"
            />
        </div>

        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-white/10">
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(245,158,11,0.14),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(16,185,129,0.08),_transparent_45%)]"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px); background-size: 48px 48px;"
                aria-hidden="true"
            />

            <div class="relative mx-auto max-w-5xl px-4 py-8 sm:py-12 lg:px-8 lg:py-16">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>

                <div
                    class="mt-2 grid gap-8 lg:grid-cols-[1.35fr_0.65fr]"
                    :class="isAbout ? 'items-center' : 'items-end'"
                >
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full border border-amber-400/25 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-200 sm:text-xs">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400" aria-hidden="true" />
                            {{ seo?.cluster_eyebrow || 'WooCommerce Bangladesh' }}
                        </p>
                        <h1 class="mt-4 max-w-3xl break-words text-[1.7rem] font-extrabold !leading-[1.15] text-white sm:text-4xl lg:text-[2.75rem]">
                            {{ seo?.prerender_h1 }}
                        </h1>
                        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-lg">
                            {{ seo?.prerender_lead }}
                        </p>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            <MetaCtaLink
                                :href="ctaUrl"
                                :label="ctaLabel"
                                location="seo_cluster_hero"
                                link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black shadow-lg shadow-amber-900/30 hover:bg-amber-400 sm:w-auto"
                            />
                            <Link
                                v-if="seo?.pillar_path && !seo?.is_pillar && !isAbout"
                                :href="seo.pillar_path"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                            >
                                {{ isEn ? 'Cluster hub' : 'ক্লাস্টার হাব' }}
                            </Link>
                            <Link
                                v-if="seo?.alternate_path"
                                :href="seo.alternate_path"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 sm:w-auto"
                            >
                                {{ seo?.alternate_label || (isEn ? 'বাংলা ভার্সন' : 'English version') }}
                            </Link>
                            <button
                                v-if="bodySections.length"
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-amber-400/30 bg-amber-500/10 px-5 py-3 text-sm font-semibold text-amber-200 hover:bg-amber-500/20 sm:w-auto"
                                @click="jumpTo(bodySections[0].id)"
                            >
                                {{ isEn ? (isAbout ? 'Read our story →' : 'Start reading →') : (isAbout ? 'আমাদের গল্প পড়ুন →' : 'পড়া শুরু →') }}
                            </button>
                        </div>
                    </div>

                    <aside
                        v-if="isAbout"
                        class="relative mx-auto flex w-full max-w-[17.5rem] flex-col items-center text-center lg:mx-0 lg:max-w-none"
                    >
                        <div class="relative mx-auto aspect-square w-full max-w-[15.5rem] sm:max-w-[16.5rem]">
                            <div
                                class="pointer-events-none absolute -inset-6 rounded-full bg-[radial-gradient(circle,_rgba(245,158,11,0.28)_0%,_rgba(245,158,11,0.08)_38%,_transparent_68%)] blur-2xl"
                                aria-hidden="true"
                            />
                            <div
                                class="pointer-events-none absolute inset-0 rounded-full ring-1 ring-amber-300/25"
                                aria-hidden="true"
                            />
                            <img
                                :src="founderPortrait"
                                :alt="`${founderName} — ${founderRole}`"
                                class="relative z-[1] h-full w-full rounded-full object-cover drop-shadow-[0_24px_48px_rgba(0,0,0,0.55)]"
                                width="1200"
                                height="1200"
                                loading="eager"
                                decoding="async"
                                fetchpriority="high"
                            />
                        </div>
                        <div class="mt-5">
                            <p class="text-lg font-bold tracking-tight text-white sm:text-xl">{{ founderName }}</p>
                            <p class="mt-1 text-sm font-medium text-amber-200/90">{{ founderRole }}</p>
                        </div>
                    </aside>

                    <aside
                        v-else-if="heroStats.length"
                        class="grid grid-cols-3 gap-2 sm:gap-3 lg:grid-cols-1"
                    >
                        <div
                            v-for="stat in heroStats"
                            :key="stat.label"
                            class="rounded-2xl border border-white/10 bg-black/30 px-2 py-3 text-center backdrop-blur sm:px-4 sm:py-4 lg:text-left"
                        >
                            <p class="text-lg font-extrabold text-amber-300 sm:text-2xl">{{ stat.value }}</p>
                            <p class="mt-0.5 text-[10px] leading-snug text-slate-400 sm:text-xs">{{ stat.label }}</p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <!-- Journey map (pillar) -->
        <section v-if="journeyGroups.length" class="border-b border-white/10 px-4 py-8 sm:py-10 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white sm:text-2xl">
                            {{ isEn ? 'Your 30-part roadmap' : '৩০ অংশের রোডম্যাপ' }}
                        </h2>
                        <p class="mt-2 max-w-xl text-sm text-slate-400">
                            {{ isEn
                                ? 'Jump into a stage — each chapter below expands when you need depth.'
                                : 'যে ধাপে দরকার সেখানে যান — প্রতিটি চ্যাপ্টার চাইলে বিস্তারিত খুলবে।' }}
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <button
                        v-for="(group, gi) in journeyGroups"
                        :key="group.key"
                        type="button"
                        class="group rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-left transition hover:border-amber-400/40 hover:bg-amber-500/5"
                        @click="jumpTo(group.targetId)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-300/80">
                                {{ String(gi + 1).padStart(2, '0') }}
                            </span>
                            <span class="text-[11px] text-slate-500">{{ group.range[0] }}–{{ group.range[1] }}</span>
                        </div>
                        <p class="mt-2 text-sm font-bold text-white group-hover:text-amber-100">{{ group.label }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-400">{{ group.hint }}</p>
                    </button>
                </div>
            </div>
        </section>

        <!-- Sticky TOC -->
        <section
            v-if="showToc"
            class="sticky top-[4.75rem] z-30 border-b border-white/10 bg-[#0a0a0a]/95 px-4 py-2 backdrop-blur sm:top-20 lg:px-8"
        >
            <div class="mx-auto max-w-5xl">
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-left text-sm font-semibold text-white lg:hidden"
                    :aria-expanded="tocOpen"
                    @click="tocOpen = !tocOpen"
                >
                    <span>{{ isEn ? 'On this page' : 'এই পেজে' }} ({{ tocItems.length }})</span>
                    <span class="text-amber-300" aria-hidden="true">{{ tocOpen ? '−' : '+' }}</span>
                </button>

                <div class="hidden lg:block">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ isEn ? 'On this page' : 'এই পেজে' }}
                    </p>
                    <div class="mt-2 flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <button
                            v-for="item in tocItems"
                            :key="item.id"
                            type="button"
                            class="shrink-0 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 hover:border-amber-400/40 hover:text-amber-200"
                            @click="jumpTo(item.id)"
                        >
                            <span v-if="item.part" class="mr-1 text-amber-400/80">{{ item.part }}.</span>
                            {{ item.label.length > 36 ? item.label.slice(0, 34) + '…' : item.label }}
                        </button>
                        <!-- Crawlable in-page anchors (visually mirrored by buttons) -->
                        <a
                            v-for="item in tocItems"
                            :key="`a-${item.id}`"
                            :href="`#${item.id}`"
                            class="sr-only"
                        >{{ item.label }}</a>
                    </div>
                </div>

                <div
                    v-show="tocOpen"
                    class="mt-2 max-h-[min(60dvh,24rem)] space-y-1 overflow-y-auto rounded-xl border border-white/10 bg-[#111] p-2 lg:hidden"
                >
                    <button
                        v-for="item in tocItems"
                        :key="item.id"
                        type="button"
                        class="flex min-h-11 w-full items-start gap-2 rounded-lg px-3 py-2.5 text-left text-sm leading-snug text-slate-300 hover:bg-white/5 hover:text-amber-200"
                        @click="jumpTo(item.id)"
                    >
                        <span
                            v-if="item.part"
                            class="mt-0.5 shrink-0 rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold text-amber-300"
                        >
                            {{ item.part }}
                        </span>
                        <span class="break-words">{{ item.label }}</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Cluster spokes -->
        <section v-if="clusterLinks.length" class="border-b border-white/10 px-4 py-8 sm:py-10 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-xl font-bold text-white sm:text-2xl">
                    {{ isAbout
                        ? (isEn ? 'Explore WooEasyLife' : 'WooEasyLife এক্সপ্লোর করুন')
                        : (isEn ? 'Dive into a spoke' : 'স্পোক পেজে ডুব দিন') }}
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-center text-sm leading-relaxed text-slate-400">
                    {{ isAbout
                        ? (isEn
                            ? 'Guides and tools that show how our automation vision works in practice.'
                            : 'গাইড ও টুলস—আমাদের অটোমেশন ভিশন বাস্তবে কীভাবে কাজ করে।')
                        : (isEn
                            ? 'Focused guides linked from this hub — pick the problem you need to solve now.'
                            : 'এই হাব থেকে লিংক করা ফোকাসড গাইড — এখন যে সমস্যাটা জরুরি সেখানে যান।') }}
                </p>

                <div
                    class="mt-6 flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory [-ms-overflow-style:none] [scrollbar-width:none] sm:hidden [&::-webkit-scrollbar]:hidden"
                >
                    <Link
                        v-for="(item, i) in clusterLinks"
                        :key="`m-${item.path}`"
                        :href="item.path"
                        class="snap-start shrink-0 w-[78%] rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.07] to-transparent p-4 active:border-amber-400/40"
                    >
                        <p class="text-[11px] font-bold text-amber-300/80">{{ String(i + 1).padStart(2, '0') }}</p>
                        <p class="mt-2 text-sm font-semibold leading-snug text-white">{{ item.label }}</p>
                        <p class="mt-3 text-xs font-semibold text-amber-200">
                            {{ isEn ? 'Open →' : 'খুলুন →' }}
                        </p>
                    </Link>
                </div>

                <div class="mt-8 hidden gap-3 sm:grid sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="(item, i) in clusterLinks"
                        :key="item.path"
                        :href="item.path"
                        class="group flex min-h-[5.5rem] flex-col justify-between rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:border-amber-400/40 hover:bg-amber-500/[0.06]"
                    >
                        <div>
                            <p class="text-[11px] font-bold text-amber-300/70">{{ String(i + 1).padStart(2, '0') }}</p>
                            <p class="mt-2 break-words text-sm font-semibold leading-snug text-amber-100 group-hover:text-amber-50">
                                {{ item.label }}
                            </p>
                        </div>
                        <span class="mt-3 text-xs font-semibold text-slate-500 transition group-hover:text-amber-300">
                            {{ isEn ? 'Read guide →' : 'গাইড পড়ুন →' }}
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Chapter body -->
        <section class="px-4 py-8 sm:py-12 lg:px-8">
            <div :class="isAbout ? 'mx-auto max-w-5xl' : 'mx-auto max-w-3xl'">
                <div v-if="!isAbout" class="mb-8 flex items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-300/80">
                            {{ isEn ? 'Guide body' : 'গাইড বডি' }}
                        </p>
                        <p class="mt-2 text-sm text-slate-400">
                            {{ isEn
                                ? 'Long chapters stay collapsed — expand only what you need. Full text remains in the page for search engines.'
                                : 'লম্বা চ্যাপ্টার ডিফল্টে ভাঁজ থাকে — যা লাগে খুলুন। পুরো টেক্সট পেজে থাকে (SEO-friendly)।' }}
                        </p>
                    </div>
                    <p class="shrink-0 text-xs font-semibold text-slate-500">
                        {{ bodySections.length }}
                        {{ isEn ? 'chapters' : 'টি' }}
                    </p>
                </div>

                <div
                    class="relative space-y-5 sm:space-y-6"
                    :class="isAbout ? 'space-y-8 sm:space-y-10' : ''"
                >
                    <div
                        v-if="!isAbout"
                        class="pointer-events-none absolute bottom-4 left-[1.15rem] top-4 w-px bg-gradient-to-b from-amber-400/40 via-white/10 to-transparent sm:left-[1.35rem]"
                        aria-hidden="true"
                    />

                    <template v-for="(section, si) in bodySections" :key="section.id">
                        <!-- Founder hero layout -->
                        <article
                            v-if="section.layout === 'founder_hero'"
                            :id="section.id"
                            class="scroll-mt-36 overflow-hidden rounded-[1.75rem] border border-amber-400/20 bg-gradient-to-br from-amber-500/[0.08] via-[#111]/95 to-[#0a0a0a] shadow-[0_0_0_1px_rgba(255,255,255,0.03)]"
                        >
                            <div class="grid items-center gap-6 p-6 sm:gap-8 sm:p-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-10 lg:p-10">
                                <div class="relative mx-auto aspect-square w-full max-w-[16rem] sm:max-w-[18rem] lg:mx-0 lg:max-w-[19rem]">
                                    <div
                                        class="pointer-events-none absolute -inset-5 rounded-full bg-[radial-gradient(circle,_rgba(245,158,11,0.22)_0%,_transparent_70%)] blur-xl"
                                        aria-hidden="true"
                                    />
                                    <img
                                        v-if="section.figures?.[0]?.src"
                                        :src="section.figures[0].src"
                                        :alt="section.figures[0].alt || `${section.founder_name || founderName}`"
                                        class="relative z-[1] h-full w-full rounded-full object-cover drop-shadow-[0_20px_40px_rgba(0,0,0,0.5)]"
                                        width="1200"
                                        height="1200"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </div>
                                <div class="relative flex flex-col justify-center text-center lg:text-left">
                                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-300/80">
                                        {{ section.heading || (isEn ? 'Our founder' : 'আমাদের প্রতিষ্ঠাতা') }}
                                    </p>
                                    <h2 class="mt-3 text-2xl font-extrabold leading-tight text-white sm:text-3xl lg:text-[2.15rem]">
                                        {{ section.founder_name || founderName }}
                                    </h2>
                                    <p class="mt-2 text-base font-semibold text-amber-200 sm:text-lg">
                                        {{ section.founder_title || founderRole }}
                                    </p>
                                    <blockquote
                                        v-if="section.founder_quote"
                                        class="mt-5 border-amber-400/60 pl-0 text-sm italic leading-relaxed text-slate-200 sm:text-base lg:border-l-2 lg:pl-4"
                                    >
                                        “{{ section.founder_quote }}”
                                    </blockquote>
                                    <div class="mt-5 space-y-3 text-left">
                                        <p
                                            v-for="(paragraph, pIndex) in section.paragraphs"
                                            :key="pIndex"
                                            class="text-sm leading-7 text-slate-300 sm:text-[0.95rem] sm:leading-8"
                                        >
                                            <LinkedRichText :text="paragraph" :is-en="isEn" />
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- About story sections -->
                        <article
                            v-else-if="isAbout"
                            :id="section.id"
                            class="scroll-mt-36 rounded-2xl border border-white/10 bg-[#111]/80 p-5 sm:p-8"
                        >
                            <h2
                                v-if="section.heading"
                                class="break-words text-xl font-bold leading-snug text-white sm:text-2xl"
                            >
                                {{ section.heading }}
                            </h2>
                            <ClusterGuideBlocks
                                class="mt-4 sm:mt-5"
                                :blocks="section.blocks"
                                :is-en="isEn"
                                lead-first
                            />
                        </article>

                        <!-- Default guide chapters -->
                        <article
                            v-else
                            :id="section.id"
                            class="cluster-chapter relative scroll-mt-36 rounded-2xl border border-white/10 bg-[#111]/80 p-4 pl-14 shadow-[0_0_0_1px_rgba(255,255,255,0.02)] backdrop-blur-sm sm:scroll-mt-32 sm:p-7 sm:pl-16"
                            :class="{
                                'border-amber-400/35 bg-gradient-to-br from-amber-500/[0.08] via-[#111]/95 to-[#0a0a0a]': section.isQuickAnswer,
                                'border-amber-400/20': !section.isQuickAnswer && section.tone === 0,
                                'border-emerald-400/15': !section.isQuickAnswer && section.tone === 1,
                                'border-sky-400/15': !section.isQuickAnswer && section.tone === 2,
                            }"
                        >
                            <div
                                class="absolute left-3 top-4 flex h-8 w-8 items-center justify-center rounded-full border text-[11px] font-bold sm:left-4 sm:top-6 sm:h-9 sm:w-9 sm:text-xs"
                                :class="{
                                    'border-amber-400/40 bg-amber-500/15 text-amber-200': section.tone === 0 || section.isQuickAnswer,
                                    'border-emerald-400/40 bg-emerald-500/15 text-emerald-200': !section.isQuickAnswer && section.tone === 1,
                                    'border-sky-400/40 bg-sky-500/15 text-sky-200': !section.isQuickAnswer && section.tone === 2,
                                }"
                            >
                                {{ section.isQuickAnswer ? (isEn ? 'QA' : 'দ্রুত') : (section.part || String(si + 1).padStart(2, '0')) }}
                            </div>

                            <h2
                                v-if="section.heading"
                                class="break-words text-lg font-bold leading-snug text-white sm:text-xl"
                                :class="section.isQuickAnswer ? 'text-amber-100' : ''"
                            >
                                {{ section.heading }}
                            </h2>

                            <ClusterGuideBlocks
                                class="mt-3 sm:mt-4"
                                :blocks="section.blocks"
                                :is-en="isEn"
                                lead-first
                            />

                            <ol
                                v-if="section.list?.length"
                                class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-relaxed text-slate-300 sm:text-base"
                            >
                                <li
                                    v-for="(item, lIndex) in section.list"
                                    :key="`${section.id}-list-${lIndex}`"
                                    class="pl-1"
                                >
                                    <LinkedRichText :text="item" :is-en="isEn" />
                                </li>
                            </ol>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <!-- Mid CTA band -->
        <section class="relative overflow-hidden border-y border-white/10 px-4 py-10 sm:py-12 lg:px-8">
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,_rgba(245,158,11,0.12),_transparent_60%)]"
                aria-hidden="true"
            />
            <div class="relative mx-auto max-w-3xl text-center">
                <h2 class="text-xl font-bold text-white sm:text-2xl">
                    {{ isAbout
                        ? (isEn ? 'Ready to automate your ops?' : 'অপস অটোমেট করতে প্রস্তুত?')
                        : (isEn ? 'Put the system to work' : 'সিস্টেম কাজে লাগান') }}
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-400">
                    {{ isAbout
                        ? (isEn
                            ? 'Start with WooEasyLife — fraud checks, courier automation, and order workflows in one place.'
                            : 'WooEasyLife দিয়ে শুরু করুন—ফ্রড চেক, কুরিয়ার অটোমেশন ও অর্ডার ওয়ার্কফ্লো এক জায়গায়।')
                        : (isEn
                            ? 'Free fraud check first, then trial protection, courier auto-entry, and messaging.'
                            : 'আগে ফ্রি ফ্রড চেক, তারপর প্রোটেকশন, কুরিয়ার অটো এন্ট্রি ও মেসেজিং ট্রায়াল।') }}
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <Link
                        :href="isEn ? '/en/bd-fraud-checker' : '/bd-fraud-checker'"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto"
                    >
                        {{ isEn ? 'Free fraud check' : 'ফ্রি ফ্রড চেক' }}
                    </Link>
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_cluster_mid"
                        link-class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 sm:w-auto"
                    />
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="px-4 py-10 pb-24 sm:py-12 sm:pb-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-300/80">
                        {{ isEn ? 'FAQ' : 'সচরাচর জিজ্ঞাসা' }}
                    </p>
                    <h2 class="mt-2 text-xl font-bold text-white sm:text-2xl">
                        {{ isEn ? 'Common questions' : 'যা প্রায়ই জিজ্ঞেস করা হয়' }}
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400">
                        {{ isAbout
                            ? (isEn
                                ? 'Clear answers about WPSaleHub, WooEasyLife, and how to reach the founder.'
                                : 'WPSaleHub, WooEasyLife এবং প্রতিষ্ঠাতার যোগাযোগ নিয়ে স্পষ্ট উত্তর।')
                            : (isEn
                                ? 'Detailed answers with labeled links to the tools and spoke guides you need next.'
                                : 'বিস্তারিত উত্তর—পরবর্তী টুল ও স্পোক গাইডের লেবেলযুক্ত লিংকসহ।') }}
                    </p>
                </div>
                <div class="mt-6 space-y-3 sm:mt-8">
                    <div
                        v-for="(faq, i) in faqs"
                        :key="i"
                        class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]"
                    >
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-3.5 text-left text-sm font-semibold text-white sm:text-[0.95rem]"
                            :aria-expanded="openFaq === i"
                            :aria-controls="`faq-answer-${i}`"
                            @click="toggleFaq(i)"
                        >
                            <span class="break-words pr-2">{{ faq.q }}</span>
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-300"
                                aria-hidden="true"
                            >
                                {{ openFaq === i ? '−' : '+' }}
                            </span>
                        </button>
                        <div
                            :id="`faq-answer-${i}`"
                            class="border-t border-white/10 px-4 py-3.5 text-sm leading-7 text-slate-300 sm:leading-8"
                            :class="openFaq === i ? '' : 'hidden'"
                        >
                            <p>
                                <LinkedRichText :text="faq.a" :is-en="isEn" />
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <button
            v-show="showBackTop"
            type="button"
            class="fixed bottom-24 left-4 z-30 inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border border-white/15 bg-[#151515]/95 text-sm font-bold text-amber-300 shadow-lg backdrop-blur hover:bg-white/10 sm:bottom-8 sm:left-6"
            style="margin-bottom: env(safe-area-inset-bottom, 0px);"
            :aria-label="isEn ? 'Back to top' : 'উপরে যান'"
            @click="scrollTop"
        >
            ↑
        </button>
    </MarketingLayout>
</template>
