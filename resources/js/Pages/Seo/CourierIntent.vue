<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import SeoContentSections from '@/components/marketing/SeoContentSections.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

const props = defineProps({
    courierName: { type: String, required: true },
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());

const contentSections = computed(() => props.seo?.content_sections || []);
const clusterLinks = computed(() => props.seo?.cluster_links || []);
const externalLinks = computed(() => props.seo?.external_links || []);
const trustSignals = computed(() => props.seo?.trust_signals || null);
const trustExamples = computed(() => trustSignals.value?.examples || []);
const trustCannotDo = computed(() => trustSignals.value?.cannot_do || []);
const trustTips = computed(() => trustSignals.value?.decision_tips || []);
const trustMistakes = computed(() => trustSignals.value?.mistakes || []);
const trustMistakesCta = computed(() => trustSignals.value?.mistakes_cta || null);
const showTrustBlock = computed(
    () =>
        trustExamples.value.length > 0
        || trustCannotDo.value.length > 0
        || trustTips.value.length > 0
        || trustMistakes.value.length > 0,
);
const h1 = computed(
    () => props.seo?.prerender_h1 || `${props.courierName} Fraud Check বাংলাদেশ`,
);
const lead = computed(
    () =>
        props.seo?.prerender_lead
        || `ফোন নম্বর দিয়ে ${props.courierName} কুরিয়ার হিস্টোরি যাচাই করুন। COD অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান — WooEasyLife BD fraud checker দিয়ে।`,
);
const authorName = computed(() => props.seo?.author_name || null);
const authorRole = computed(() => props.seo?.author_role || null);
const authorImage = computed(() => props.seo?.author_image || null);
const lastUpdatedLabel = computed(() => props.seo?.last_updated_label || '৩০ জুলাই ২০২৬');
const honestyLine = computed(
    () =>
        props.seo?.honesty_line
        || 'This tool helps you make a better-informed decision. It does not guarantee that an order is fake or genuine.',
);
const videoYoutubeId = computed(() => {
    const id = String(props.seo?.video_youtube_id || '').trim();
    return /^[A-Za-z0-9_-]{6,}$/.test(id) ? id : null;
});
const videoTitle = computed(
    () => props.seo?.video_title || `${props.courierName} Fraud Check Complete Guide`,
);
const useGuideAnchors = computed(() => Boolean(props.seo?.is_pillar));

const isSkippedForGuideToc = (heading) => {
    const h = String(heading || '').trim();
    if (!h) return true;
    const lower = h.toLowerCase();
    if (h.includes('দ্রুত') || lower.includes('quick')) return true;
    if (h.includes('এআই সারাংশ') || lower.includes('ai summary')) return true;
    return false;
};

const tocItems = computed(() => {
    const items = [];
    if (useGuideAnchors.value) {
        let tocPosition = 0;
        for (const s of contentSections.value) {
            const heading = String(s?.heading || '').trim();
            if (!heading || isSkippedForGuideToc(heading)) continue;
            tocPosition += 1;
            items.push({ id: `guide-section-${tocPosition}`, heading });
        }
    } else {
        contentSections.value.forEach((s, i) => {
            const heading = String(s?.heading || '').trim();
            if (!heading || isSkippedForGuideToc(heading)) return;
            items.push({ id: `seo-section-${i + 1}`, heading });
        });
    }
    items.push({ id: 'video', heading: 'ডেমো ভিডিও' });
    if (showTrustBlock.value) {
        items.push({ id: 'trust', heading: 'বিশ্বাস ও সীমা (Trust)' });
    }
    if (externalLinks.value.length) {
        items.push({ id: 'external-refs', heading: 'অফিসিয়াল রেফারেন্স' });
    }
    if (props.faqs?.length) {
        items.push({ id: 'faq', heading: 'যা জানতে চান (FAQ)' });
    }
    return items;
});

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout
        :can-login="canLogin"
        :whatsapp-url="whatsappUrl"
        active-nav="fraud-check"
        suppress-mobile-whatsapp-fab
        suppress-seo-content-sections
    >
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mx-auto max-w-3xl text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ h1 }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ lead }}
                </p>
                <p
                    class="mx-auto mt-4 max-w-2xl rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-left text-xs leading-relaxed text-amber-100/90 sm:text-sm"
                >
                    {{ honestyLine }}
                </p>
                <div
                    v-if="authorName"
                    class="mx-auto mt-6 flex max-w-xl items-center justify-center gap-3 text-left"
                >
                    <img
                        v-if="authorImage"
                        :src="authorImage"
                        :alt="authorName"
                        class="h-12 w-12 rounded-full object-cover ring-2 ring-amber-500/40"
                        width="48"
                        height="48"
                        loading="lazy"
                        decoding="async"
                    />
                    <div>
                        <p class="text-sm font-semibold text-white">{{ authorName }}</p>
                        <p v-if="authorRole" class="text-xs text-slate-400">{{ authorRole }}</p>
                        <p class="text-xs text-slate-500">শেষ হালনাগাদ: {{ lastUpdatedLabel }}</p>
                        <Link href="/about" class="text-xs font-semibold text-amber-400 hover:text-amber-300">
                            ফাউন্ডার প্রোফাইল →
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section id="fraud-check" class="scroll-mt-24 px-4 pb-12 pt-12 sm:pt-14 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="mb-6 text-center">
                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                        ফ্রি টুল — অ্যাকাউন্ট লাগবে না
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                        {{ courierName }} হিস্টোরি চেক করুন
                    </h2>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

        <nav
            v-if="tocItems.length >= 4"
            class="border-y border-white/10 bg-[#111111] px-4 py-8 lg:px-8"
            aria-label="সূচিপত্র"
        >
            <div class="mx-auto max-w-3xl">
                <h2 class="text-lg font-bold text-white">সূচিপত্র</h2>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-slate-300">
                    <li v-for="item in tocItems" :key="item.id">
                        <a :href="`#${item.id}`" class="text-amber-400 hover:text-amber-300">
                            {{ item.heading }}
                        </a>
                    </li>
                </ol>
            </div>
        </nav>

        <section class="border-b border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">
                    {{ courierName }} Fraud Check কীভাবে করবেন
                </h2>
                <ol class="mt-6 space-y-4 text-sm text-slate-300 sm:text-base">
                    <li>
                        <span class="font-semibold text-amber-300">১.</span>
                        কাস্টমারের মোবাইল নম্বর দিন।
                    </li>
                    <li>
                        <span class="font-semibold text-amber-300">২.</span>
                        {{ courierName }} সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন রেকর্ড দেখুন।
                    </li>
                    <li>
                        <span class="font-semibold text-amber-300">৩.</span>
                        ঝুঁকি কম হলে অর্ডার কনফার্ম করুন — বেশি হলে আগে যাচাই করুন।
                    </li>
                </ol>
            </div>
        </section>

        <SeoContentSections :sections="contentSections" :use-guide-anchors="useGuideAnchors" />

        <section
            v-if="showTrustBlock"
            id="trust"
            class="scroll-mt-24 border-y border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8"
        >
            <div class="mx-auto max-w-3xl space-y-10">
                <div>
                    <h2 class="text-xl font-bold text-white sm:text-2xl">বিশ্বাস ও সীমা — Trust signals</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-300 sm:text-base">
                        {{ honestyLine }}
                    </p>
                    <p class="mt-2 text-sm text-slate-400">
                        আমরা “No.1” বা ফেক AggregateRating দেখাই না। নিচে বাস্তব ওয়ার্কফ্লো উদাহরণ, যা টুল পারে না, এবং সিদ্ধান্ত টিপস।
                    </p>
                </div>

                <div v-if="trustExamples.length">
                    <h3 class="text-base font-bold text-white">বাস্তব অর্ডার উদাহরণ (অনামিক)</h3>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="ex in trustExamples"
                            :key="ex.title"
                            class="rounded-xl border border-white/10 bg-white/5 px-4 py-3"
                        >
                            <h4 class="text-sm font-semibold text-amber-300">{{ ex.title }}</h4>
                            <p class="mt-1 text-sm leading-relaxed text-slate-300">{{ ex.body }}</p>
                        </article>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div v-if="trustCannotDo.length">
                        <h3 class="text-base font-bold text-white">টুল যা করতে পারে না</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-300">
                            <li
                                v-for="item in trustCannotDo"
                                :key="item"
                                class="flex gap-2"
                            >
                                <span class="shrink-0 font-bold text-rose-400">×</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                    <div v-if="trustTips.length">
                        <h3 class="text-base font-bold text-white">সিদ্ধান্ত টিপস</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-300">
                            <li
                                v-for="item in trustTips"
                                :key="item"
                                class="flex gap-2"
                            >
                                <span class="shrink-0 font-bold text-emerald-400">✓</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-if="trustMistakes.length">
                    <h3 class="text-base font-bold text-white">সাধারণ ভুল (সংক্ষেপ)</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-300">
                        <li v-for="item in trustMistakes" :key="item" class="flex gap-2">
                            <span class="shrink-0 text-amber-400">•</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <Link
                        v-if="trustMistakesCta?.path"
                        :href="trustMistakesCta.path"
                        class="mt-4 inline-flex text-sm font-semibold text-amber-400 hover:text-amber-300"
                    >
                        {{ trustMistakesCta.label || 'বিস্তারিত পড়ুন' }} →
                    </Link>
                </div>
            </div>
        </section>

        <section id="video" class="scroll-mt-24 border-y border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">ডেমো ভিডিও</h2>
                <div v-if="videoYoutubeId" class="mt-6 overflow-hidden rounded-xl border border-white/10 bg-black">
                    <div class="relative aspect-video w-full">
                        <iframe
                            class="absolute inset-0 h-full w-full"
                            :src="`https://www.youtube.com/embed/${videoYoutubeId}`"
                            :title="videoTitle"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen
                        />
                    </div>
                </div>
                <template v-else>
                    <p class="mt-3 text-sm leading-relaxed text-slate-300 sm:text-base">
                        Complete Guide ও Shorts ভিডিও Step 4-এ পাবলিশ হলে এখানে এম্বেড হবে। এখনই লাইভ ডেমো:
                        উপরের ফ্রি চেকার বা
                        <Link href="/bd-fraud-checker" class="font-semibold text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>।
                    </p>
                </template>
            </div>
        </section>

        <section
            v-if="externalLinks.length"
            id="external-refs"
            class="scroll-mt-24 px-4 py-12 lg:px-8"
        >
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">অফিসিয়াল রেফারেন্স</h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">
                    কুরিয়ার চার্জ, টার্মস ও সাপোর্ট SteadFast-এর নিজস্ব সাইট থেকে যাচাই করুন—এটি প্রতিযোগী হেট নয়, মার্চেন্ট রেফারেন্স।
                </p>
                <ul class="mt-6 space-y-2 text-sm sm:text-base">
                    <li v-for="link in externalLinks" :key="link.href">
                        <a
                            :href="link.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold text-amber-400 hover:text-amber-300"
                        >
                            {{ link.label }} ↗
                        </a>
                    </li>
                </ul>
            </div>
        </section>

        <section class="border-y border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">পূর্ণ BD Fraud Checker ও প্রাইসিং</h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">
                    শুধু {{ courierName }} নয় — Pathao, Steadfast, RedX একসাথে দেখতে
                    <Link href="/bd-fraud-checker" class="text-amber-400 hover:text-amber-300">BD Fraud Checker</Link>
                    ব্যবহার করুন। পূর্ণ সুরক্ষা ও অটোমেশনের জন্য সাবস্ক্রিপশন নিন।
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        BD Fraud Checker
                    </Link>
                    <Link
                        :href="route('pricing')"
                        class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        প্রাইসিং দেখুন
                    </Link>
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_courier_intent"
                        link-class="inline-flex rounded-xl border border-amber-500/40 px-6 py-3 text-sm font-semibold text-amber-300 hover:bg-amber-500/10"
                    />
                </div>
            </div>
        </section>

        <section
            v-if="clusterLinks.length"
            class="px-4 py-12 lg:px-8"
            aria-label="সম্পর্কিত পেজ"
        >
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">সম্পর্কিত পেজ</h2>
                <div class="mt-6 flex flex-wrap gap-2">
                    <Link
                        v-for="link in clusterLinks"
                        :key="link.path"
                        :href="link.path"
                        class="inline-flex rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-amber-500/10 sm:text-sm"
                    >
                        {{ link.label }}
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="faqs.length" id="faq" class="scroll-mt-24 border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">যা জানতে চান</h2>
                <div class="mt-8 space-y-3">
                    <div
                        v-for="(item, i) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white"
                            @click="toggleFaq(i)"
                        >
                            <span>{{ item.q }}</span>
                            <span class="shrink-0 text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-3 text-sm text-slate-300">
                            <LinkedRichText :text="item.a" :is-en="false" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
