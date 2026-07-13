<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import LandingFraudCheck from '@/components/marketing/LandingFraudCheck.vue';
import { primaryCtaLabel, primaryCtaUrl } from '@/utils/marketingCta';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    fraudCheck: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
    whatsappContactUrl: { type: String, default: null },
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

    <MarketingLayout
        :can-login="canLogin"
        :whatsapp-url="whatsappUrl"
        active-nav="fraud-check"
        suppress-mobile-whatsapp-fab
    >
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mx-auto max-w-3xl text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    Free Courier Fraud Checker BD — ফ্রি ফ্রড চেকার
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    মোবাইল নম্বর দিয়ে Pathao, Steadfast ও RedX কুরিয়ার হিস্টোরি, সাকসেস রেট ও রিটার্ন রেকর্ড যাচাই করুন।
                    অর্ডার কনফার্মের আগেই ফেক কাস্টমার চেক করুন — ই-কমার্স ও Facebook পেজ সেলারদের জন্য।
                </p>
            </div>
        </section>

        <section id="fraud-check" class="scroll-mt-24 px-4 pb-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <div class="mb-6 text-center">
                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                        ফ্রি টুল — অ্যাকাউন্ট লাগবে না
                    </span>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                        ফোন নম্বর দিন — কুরিয়ার হিস্টোরি দেখুন
                    </h2>
                </div>
                <LandingFraudCheck :fraud-check="fraudCheck" />
            </div>
        </section>

        <section class="border-y border-white/10 bg-[#111111] px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">কীভাবে কাজ করে</h2>
                <ol class="mt-6 space-y-4 text-sm text-slate-300 sm:text-base">
                    <li><span class="font-semibold text-amber-300">১.</span> কাস্টমারের মোবাইল নম্বর দিন।</li>
                    <li><span class="font-semibold text-amber-300">২.</span> Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন রেকর্ড দেখুন।</li>
                    <li><span class="font-semibold text-amber-300">৩.</span> ঝুঁকি কম হলে অর্ডার কনফার্ম করুন — বেশি হলে আগে যাচাই করুন।</li>
                </ol>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-xl font-bold text-white sm:text-2xl">শুধু টুল নয় — পূর্ণ WooCommerce সুরক্ষা</h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">
                    অনেক ফ্রড চেকার শুধু হিস্টোরি দেখায়। WooEasyLife-এ ফ্রড চেক ছাড়াও
                    <Link href="/fake-order-protection" class="text-amber-400 hover:text-amber-300">ফেক অর্ডার প্রোটেকশন</Link>,
                    <Link href="/courier-auto-entry" class="text-amber-400 hover:text-amber-300">কুরিয়ার অটো এন্ট্রি</Link>
                    ও হারানো অর্ডার রিকভারি একসাথে পাবেন।
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        :href="ctaUrl"
                        class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                    >
                        {{ ctaLabel }}
                    </Link>
                    <Link
                        :href="route('pricing')"
                        class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        প্রাইসিং দেখুন
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
                            {{ item.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
