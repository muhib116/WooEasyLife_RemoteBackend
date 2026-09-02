<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    locale: { type: String, default: 'bn' },
    pricingHref: { type: String, default: '/pricing' },
    youtubeUrl: { type: String, default: 'https://www.youtube.com/watch?v=e4FGM12gjxw' },
});

const isEn = computed(() => props.locale === 'en');

const copy = computed(() => (isEn.value
    ? {
        badge: 'New · Funnels (Beta)',
        h2: 'Build a COD landing page for Facebook Ads — without coding',
        p: 'Create a funnel, set product + phone, generate copy with ChatGPT/Gemini (Import JSON), pick a theme/layout, and publish on a clean /wel/ URL with an inline order form.',
        bullets: [
            'Funnels hub in WooEasyLife admin (Beta)',
            'AI prompt → Import JSON content',
            'Inline COD checkout · WhatsApp/call CTAs',
            'Layouts: Classic · Offer · Voyage · Atlas',
        ],
        ctaTrial: 'Start free trial',
        ctaVideo: 'Watch how-to video',
        note: 'Beta — step analytics (views/revenue) are placeholders. No conversion-rate guarantee.',
    }
    : {
        badge: 'নতুন · Funnels (Beta)',
        h2: 'Facebook Ads-এর জন্য COD ল্যান্ডিং পেজ — কোডিং ছাড়াই',
        p: 'ফানেল তৈরি করুন, প্রোডাক্ট ও ফোন সেট করুন, ChatGPT/Gemini দিয়ে কনটেন্ট Import JSON করুন, থিম/লেআউট বেছে /wel/ clean URL-এ পাবলিশ করুন — ইনলাইন অর্ডার ফর্মসহ।',
        bullets: [
            'WooEasyLife অ্যাডমিনে Funnels hub (Beta)',
            'AI প্রম্পট → Import JSON',
            'Inline COD চেকআউট · WhatsApp/কল CTA',
            'লেআউট: Classic · Offer · Voyage · Atlas',
        ],
        ctaTrial: 'ফ্রি ট্রায়াল শুরু করুন',
        ctaVideo: 'হাউ-টু ভিডিও দেখুন',
        note: 'Beta — Views/Revenue এখনো প্লেসহোল্ডার। কনভার্সন গ্যারান্টি দাবি করা হয় না।',
    }));
</script>

<template>
    <section id="funnels" class="scroll-mt-24 px-4 py-12 sm:py-16 lg:px-8">
        <div class="mx-auto max-w-6xl overflow-hidden rounded-3xl border border-orange-500/25 bg-gradient-to-br from-orange-950/40 via-[#141414] to-[#0a0a0a] shadow-lg shadow-orange-950/20">
            <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1.2fr_0.8fr] lg:gap-10 lg:p-10">
                <div>
                    <span class="inline-flex rounded-full border border-orange-400/35 bg-orange-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-orange-300">
                        {{ copy.badge }}
                    </span>
                    <h2 class="mt-4 text-2xl font-bold leading-snug text-white sm:text-3xl">
                        {{ copy.h2 }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                        {{ copy.p }}
                    </p>
                    <ul class="mt-5 space-y-2">
                        <li
                            v-for="item in copy.bullets"
                            :key="item"
                            class="flex gap-2 text-sm text-slate-200"
                        >
                            <span class="shrink-0 font-bold text-orange-400">✓</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <p class="mt-4 text-xs leading-relaxed text-slate-500">
                        {{ copy.note }}
                    </p>
                </div>

                <div class="flex flex-col justify-center gap-3 lg:pl-2">
                    <Link
                        :href="pricingHref"
                        class="inline-flex items-center justify-center rounded-xl bg-orange-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-orange-400"
                    >
                        {{ copy.ctaTrial }}
                    </Link>
                    <a
                        :href="youtubeUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-slate-100 transition hover:border-orange-400/40 hover:bg-orange-500/10"
                    >
                        {{ copy.ctaVideo }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>
