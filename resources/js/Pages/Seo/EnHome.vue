<script setup>
import { Link } from '@inertiajs/vue3';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import { computed } from 'vue';

defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());
</script>

<template>
    <SeoHead :seo="seo" />
    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="home">
        <section class="border-b border-white/10 px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold text-white sm:text-4xl">
                    BD Fraud Checker for WooCommerce COD sellers
                </h1>
                <p class="mt-4 text-base text-slate-300 sm:text-lg">
                    Reduce fake orders, verify courier history, auto-enter Pathao / Steadfast / RedX parcels,
                    and recover missing orders — built for Bangladesh.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link :href="'/en/bd-fraud-checker'" class="rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400">
                        Free fraud check
                    </Link>
                    <Link :href="ctaUrl" class="rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                        {{ ctaLabel }}
                    </Link>
                    <Link href="/" class="rounded-xl px-4 py-3 text-sm text-slate-400 hover:text-amber-300">বাংলা ভার্সন</Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
