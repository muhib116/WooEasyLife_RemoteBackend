<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';
import MetaCtaLink from '@/components/marketing/MetaCtaLink.vue';

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

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="features">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    কিভাবে ফেক অর্ডার আটকাবো — Fake Order Protection
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    COD ও Facebook পেজ ব্যবসায় ফেক অর্ডার মানে রিটার্ন চার্জ ও নষ্ট অ্যাড বাজেট।
                    ফ্রড চেক + OTP + ব্লক দিয়ে অর্ডার পাঠানোর আগেই ঝুঁকি আটকান।
                </p>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto grid max-w-4xl gap-6 sm:grid-cols-2">
                <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <h2 class="text-lg font-bold text-white">কুরিয়ার হিস্টোরি চেক</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        অর্ডার কনফার্মের আগে
                        <Link href="/bd-fraud-checker" class="text-amber-400 hover:text-amber-300">BD fraud checker</Link>
                        দিয়ে ডেলিভারি সাকসেস রেট দেখুন।
                    </p>
                </article>
                <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <h2 class="text-lg font-bold text-white">চেকআউট OTP</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        ফোন নম্বর OTP দিয়ে যাচাই — ভুল/ফেক নম্বরে অর্ডার কমে।
                    </p>
                </article>
                <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <h2 class="text-lg font-bold text-white">ডুপ্লিকেট ও ব্ল্যাকলিস্ট</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        একই অর্ডার বারবার আসা বন্ধ করুন। সমস্যাযুক্ত ফোন, IP বা ডিভাইস ব্লক রাখুন।
                    </p>
                </article>
                <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <h2 class="text-lg font-bold text-white">রিটার্ন লস কমান</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        দিনে কয়েকটি ফেক অর্ডার আটকালেই মাসে হাজার হাজার টাকা রিটার্ন চার্জ বাঁচে।
                    </p>
                </article>
            </div>

            <div class="mx-auto mt-10 flex max-w-4xl flex-wrap gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_fake_order_protection"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    href="/bd-fraud-checker"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    ফ্রি ফ্রড চেক করুন
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
