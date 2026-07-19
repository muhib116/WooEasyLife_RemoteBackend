<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import CourierChargeCalculatorSection from '@/components/marketing/CourierChargeCalculatorSection.vue';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    courierChargeCalculator: { type: Object, default: () => ({}) },
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
    'ডেলিভারি জোন বেছে নিন (ঢাকার ভিতর / সাবআরবান / বাইরে)।',
    'পার্সেল ওজন স্লাইডারে সেট করুন।',
    'COD অ্যামাউন্ট দিলে আনুমানিক COD ফি যোগ হবে।',
    'Pathao, Steadfast ও RedX-এর আনুমানিক চার্জ তুলনা করুন — তারপর অটো এন্ট্রি ট্রায়াল শুরু করুন।',
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
                <p class="text-sm font-semibold tracking-[0.18em] text-sky-300/90">কুরিয়ার চার্জ ক্যালকুলেটর</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ seo?.prerender_h1 || 'Pathao · Steadfast · RedX ডেলিভারি চার্জ হিসাব' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ seo?.prerender_lead || 'জোন ও ওজন দিয়ে তিন কুরিয়ারের আনুমানিক চার্জ তুলনা করুন।' }}
                </p>
            </div>
        </section>

        <CourierChargeCalculatorSection
            :config="courierChargeCalculator"
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
                        location="seo_courier_charge_calculator"
                        link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                    />
                    <Link
                        href="/courier-auto-entry"
                        class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        কুরিয়ার অটো এন্ট্রি
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
