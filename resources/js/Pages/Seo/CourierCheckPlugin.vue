<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';
import { trackEvent } from '@/utils/analytics';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
    wporgLive: { type: Boolean, default: false },
    downloadUrl: { type: String, default: '' },
    screenshots: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const isEn = computed(() => String(props.seo?.html_lang || '').startsWith('en'));
const language = computed(() => (isEn.value ? 'en' : 'bn'));
const sections = computed(() => (Array.isArray(props.seo?.content_sections) ? props.seo.content_sections : []));
const showDownload = computed(() => props.wporgLive && props.downloadUrl);

const track = (name) => {
    trackEvent(name, { language: language.value });
};
</script>

<template>
    <SeoHead :seo="seo" />
    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="features">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">EasyLife Courier Check</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead }}
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                    <a
                        v-if="showDownload"
                        :href="downloadUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black hover:bg-amber-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 sm:w-auto"
                        @click="track('courier_plugin_download_click')"
                    >
                        {{ seo?.plugin_download_label }}
                    </a>
                    <button
                        v-else
                        type="button"
                        aria-disabled="true"
                        disabled
                        class="inline-flex min-h-11 w-full cursor-not-allowed items-center justify-center rounded-xl bg-white/10 px-5 py-3 text-sm font-bold text-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 sm:w-auto"
                    >
                        {{ seo?.plugin_download_label }}
                    </button>
                    <Link
                        :href="seo?.plugin_checker_href || (isEn ? '/en/bd-fraud-checker' : '/bd-fraud-checker')"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 sm:w-auto"
                        @click="track('courier_plugin_fraud_checker_click')"
                    >
                        {{ seo?.plugin_checker_label }}
                    </Link>
                    <Link
                        href="/pricing"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 sm:w-auto"
                        @click="track('courier_plugin_pricing_click')"
                    >
                        {{ seo?.plugin_pricing_label }}
                    </Link>
                    <Link
                        :href="isEn ? '/courier-check-plugin' : '/en/courier-check-plugin'"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 sm:w-auto"
                    >
                        {{ isEn ? 'বাংলায় দেখুন' : 'English version' }}
                    </Link>
                </div>
            </div>
        </section>

        <section
            v-for="section in sections"
            :key="section.heading"
            class="border-b border-white/10 px-4 py-12 lg:px-8"
        >
            <div class="mx-auto max-w-3xl">
                <h2 class="text-2xl font-bold text-white">{{ section.heading }}</h2>
                <p
                    v-for="(paragraph, idx) in section.paragraphs || []"
                    :key="`${section.heading}-p-${idx}`"
                    class="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base"
                >
                    <LinkedRichText :text="paragraph" :is-en="isEn" />
                </p>
                <p
                    v-if="section.note"
                    class="mt-4 rounded-xl border border-amber-400/40 bg-amber-950/30 px-4 py-3 text-sm leading-relaxed text-amber-100"
                >
                    <LinkedRichText :text="section.note" :is-en="isEn" />
                </p>
                <ol v-if="section.layout === 'steps'" class="mt-6 space-y-3">
                    <li
                        v-for="(item, idx) in section.list || []"
                        :key="`${section.heading}-step-${idx}`"
                        class="flex gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300"
                    >
                        <span class="font-bold text-amber-400">{{ String(idx + 1).padStart(2, '0') }}</span>
                        <LinkedRichText :text="item" :is-en="isEn" />
                    </li>
                </ol>
                <ul v-else-if="section.layout === 'features'" class="mt-6 space-y-3">
                    <li
                        v-for="(item, idx) in section.list || []"
                        :key="`${section.heading}-feature-${idx}`"
                        class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm leading-relaxed text-slate-300"
                    >
                        <LinkedRichText :text="item" :is-en="isEn" />
                    </li>
                </ul>
                <div v-else-if="section.layout === 'comparison'" class="mt-6">
                    <div class="hidden md:block">
                        <table class="w-full border-collapse text-left text-sm text-slate-300">
                            <thead>
                                <tr class="border-b border-white/10 text-white">
                                    <th class="py-3 pr-3 font-semibold">{{ isEn ? 'Feature' : 'সুবিধা' }}</th>
                                    <th class="px-3 py-3 font-semibold">EasyLife Courier Check</th>
                                    <th class="py-3 pl-3 font-semibold">WooEasyLife</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in section.rows || []" :key="row.feature" class="border-b border-white/10">
                                    <td class="py-3 pr-3">{{ row.feature }}</td>
                                    <td class="px-3 py-3">{{ row.free }}</td>
                                    <td class="py-3 pl-3">
                                        <Link
                                            v-if="row.href"
                                            :href="row.href"
                                            class="font-semibold text-amber-400 hover:text-amber-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                                        >
                                            {{ row.paid }}
                                        </Link>
                                        <template v-else>{{ row.paid }}</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="space-y-3 md:hidden">
                        <div
                            v-for="row in section.rows || []"
                            :key="`stack-${row.feature}`"
                            class="rounded-xl border border-white/10 bg-white/5 p-4"
                        >
                            <p class="font-semibold text-white">{{ row.feature }}</p>
                            <p class="mt-2 text-sm text-slate-300">EasyLife Courier Check: {{ row.free }}</p>
                            <p class="mt-1 text-sm text-slate-300">
                                WooEasyLife:
                                <Link
                                    v-if="row.href"
                                    :href="row.href"
                                    class="font-semibold text-amber-400 hover:text-amber-300"
                                >
                                    {{ row.paid }}
                                </Link>
                                <template v-else>{{ row.paid }}</template>
                            </p>
                        </div>
                    </div>
                </div>
                <div v-if="section.layout === 'steps' && screenshots.length" class="mt-8 grid gap-4">
                    <figure v-for="shot in screenshots" :key="shot.src" class="overflow-hidden rounded-2xl border border-white/10">
                        <img
                            :src="shot.src"
                            :alt="shot.alt"
                            :width="shot.width"
                            :height="shot.height"
                            loading="lazy"
                            decoding="async"
                            class="h-auto w-full"
                        />
                    </figure>
                </div>
            </div>
        </section>

        <section v-if="faqs.length" id="faq" class="border-b border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">{{ seo?.faq_heading }}</h2>
                <div class="mt-8 space-y-3">
                    <div
                        v-for="(item, i) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                            @click="openFaq = openFaq === i ? null : i"
                        >
                            <span>{{ item.q }}</span>
                            <span class="shrink-0 text-slate-400">{{ openFaq === i ? '−' : '+' }}</span>
                        </button>
                        <div v-show="openFaq === i" class="border-t border-white/10 px-4 py-3 text-sm leading-relaxed text-slate-300">
                            <LinkedRichText :text="item.a" :is-en="isEn" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-4 py-10 lg:px-8">
            <p class="mx-auto max-w-3xl text-center text-sm text-slate-400">
                <template v-if="isEn">
                    Built by
                    <Link href="/en/about" class="font-semibold text-amber-400 hover:text-amber-300">Muhibbullah Ansary</Link>,
                    Founder, WPSaleHub
                </template>
                <template v-else>
                    তৈরি করেছেন
                    <Link href="/about" class="font-semibold text-amber-400 hover:text-amber-300">মুহিব্বুল্লাহ আনসারী</Link>,
                    Founder, WPSaleHub
                </template>
            </p>
        </section>
    </MarketingLayout>
</template>
