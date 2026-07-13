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
                    কুরিয়ার অটো এন্ট্রি — Pathao, Steadfast, RedX
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    অর্ডার কনফার্ম হলেই কুরিয়ার প্যানেলে অটো এন্ট্রি। WooCommerce COD সেলারদের
                    ম্যানুয়াল টাইপ বাদ দিয়ে দিনে ঘণ্টার পর ঘণ্টা সময় বাঁচান।
                </p>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-6 text-sm text-slate-300 sm:text-base">
                <div>
                    <h2 class="text-xl font-bold text-white">হাতে এন্ট্রি vs অটো</h2>
                    <p class="mt-2 text-slate-400">
                        প্রতি অর্ডারে কুরিয়ার প্যানেলে নাম-ঠিকানা-ফোন কপি করা সময় নষ্ট করে ও ভুল বাড়ায়।
                        WooEasyLife-এ কনফার্ম = অটো এন্ট্রি।
                    </p>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">স্ট্যাটাস সিঙ্ক ও SMS</h2>
                    <p class="mt-2 text-slate-400">
                        কুরিয়ার স্ট্যাটাস সিঙ্ক ও কাস্টমার SMS — এক ড্যাশবোর্ড থেকে ট্র্যাক করুন।
                    </p>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">আগে ফ্রড চেক, তারপর কনফার্ম</h2>
                    <p class="mt-2 text-slate-400">
                        <Link href="/bd-fraud-checker" class="text-amber-400 hover:text-amber-300">BD fraud checker</Link>
                        দিয়ে হিস্টোরি দেখে নিরাপদ অর্ডারই কুরিয়ারে পাঠান — রিটার্ন লস কমে।
                    </p>
                </div>
            </div>

            <div class="mx-auto mt-10 flex max-w-3xl flex-wrap gap-3">
                <MetaCtaLink
                    :href="ctaUrl"
                    :label="ctaLabel"
                    location="seo_courier_auto_entry"
                    link-class="inline-flex rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black hover:bg-amber-400"
                />
                <Link
                    :href="route('pricing')"
                    class="inline-flex rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10"
                >
                    প্রাইসিং দেখুন
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
