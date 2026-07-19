<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import AdsRoasCalculatorSection from '@/components/marketing/AdsRoasCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    adsRoasCalculator: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};

const steps = [
    'মাসিক Facebook Ads স্পেন্ড সেট করুন।',
    'Pixel-এ কতগুলো Purchase ইভেন্ট গেছে তা দিন।',
    'ফেক/ক্যানসেল/রিটার্ন রেট মিলিয়ে নিন।',
    'রিপোর্টেড vs আসল ROAS দেখুন — পিক্সেল প্রোটেকশন দিয়ে ফেক Purchase বন্ধ করুন।',
];
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="tools">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-sky-300/90">Facebook Ads ROAS ক্যালকুলেটর</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'ফেক Purchase বাদ দিয়ে আসল Ads ROAS' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'অ্যাড স্পেন্ড ও Pixel Purchase দিয়ে রিপোর্টেড vs আসল ROAS হিসাব করুন।' }}
                </p>
            </div>
        </section>

        <AdsRoasCalculatorSection
            :config="adsRoasCalculator"
            :primary-cta-url="ctaUrl"
            :primary-cta-label="ctaLabel"
            :show-intro="false"
        />

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-bold text-white">কীভাবে ব্যবহার করবেন</h2>
                <ol class="mt-8 space-y-3">
                    <li
                        v-for="(step, i) in steps"
                        :key="step"
                        class="flex gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300"
                    >
                        <span class="font-bold text-amber-400">{{ String(i + 1).padStart(2, '0') }}</span>
                        <span>{{ step }}</span>
                    </li>
                </ol>

                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    <MetaCtaLink
                        :href="ctaUrl"
                        :label="ctaLabel"
                        location="seo_ads_roas_calculator"
                        link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                    />
                    <Link
                        href="/fake-order-protection"
                        class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ফেক অর্ডার প্রোটেকশন
                    </Link>
                    <Link
                        href="/return-loss-calculator"
                        class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        রিটার্ন লস ক্যালকুলেটর
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="faqs.length" class="border-t border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
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
                            {{ item.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
