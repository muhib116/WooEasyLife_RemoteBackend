<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
});

const openFaq = ref(null);
const ctaUrl = computed(() => primaryCtaUrl());
const ctaLabel = computed(() => primaryCtaLabel());

const toggleFaq = (i) => {
    openFaq.value = openFaq.value === i ? null : i;
};
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="fraud-check">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    FraudBD Alternative — টুল নয়, পূর্ণ প্ল্যাটফর্ম
                </h1>
                <p class="mt-4 text-base text-slate-300 sm:text-lg">
                    FraudBD বা অন্যান্য শুধু-ফ্রড-চেকার টুল শুধু কুরিয়ার হিস্টোরি দেখায়।
                    WooEasyLife-এ একই BD fraud checker-এর সাথে ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি,
                    হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ একসাথে পাবেন।
                </p>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl overflow-x-auto">
                <h2 class="text-xl font-bold text-white">দ্রুত তুলনা</h2>
                <table class="mt-6 w-full min-w-[32rem] border-collapse text-left text-sm text-slate-300">
                    <thead>
                        <tr class="border-b border-white/15 text-slate-400">
                            <th class="py-3 pr-4 font-medium">ফিচার</th>
                            <th class="py-3 pr-4 font-medium">টুল-শুধু চেকার</th>
                            <th class="py-3 font-medium text-amber-300">WooEasyLife</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-white/10">
                            <td class="py-3 pr-4">কুরিয়ার হিস্টোরি / ফ্রড চেক</td>
                            <td class="py-3 pr-4">হ্যাঁ</td>
                            <td class="py-3 text-emerald-400">হ্যাঁ (বিল্ট-ইন)</td>
                        </tr>
                        <tr class="border-b border-white/10">
                            <td class="py-3 pr-4">চেকআউট OTP / ফেক অর্ডার ব্লক</td>
                            <td class="py-3 pr-4">সাধারণত না</td>
                            <td class="py-3 text-emerald-400">হ্যাঁ</td>
                        </tr>
                        <tr class="border-b border-white/10">
                            <td class="py-3 pr-4">কুরিয়ার অটো এন্ট্রি</td>
                            <td class="py-3 pr-4">না</td>
                            <td class="py-3 text-emerald-400">Pathao / Steadfast / RedX</td>
                        </tr>
                        <tr class="border-b border-white/10">
                            <td class="py-3 pr-4">হারানো অর্ডার রিকভারি</td>
                            <td class="py-3 pr-4">না</td>
                            <td class="py-3 text-emerald-400">হ্যাঁ</td>
                        </tr>
                        <tr>
                            <td class="py-3 pr-4">মোবাইল অ্যাপ + মাল্টিস্টোর</td>
                            <td class="py-3 pr-4">সীমিত</td>
                            <td class="py-3 text-emerald-400">হ্যাঁ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mx-auto mt-10 flex max-w-3xl flex-wrap gap-3">
                <Link
                    href="/bd-fraud-checker"
                    class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                >
                    ফ্রি ফ্রড চেক করুন
                </Link>
                <Link
                    :href="ctaUrl"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    {{ ctaLabel }}
                </Link>
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
