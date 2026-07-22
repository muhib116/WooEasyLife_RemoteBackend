<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { RANGE_SLIDER_CLASS, rangeTrackStyle } from '@/utils/rangeSlider';
import { trackToolAction } from '@/utils/siteVisitors';

const props = defineProps({
    config: { type: Object, default: () => ({}) },
    primaryCtaUrl: { type: String, default: '#' },
    primaryCtaLabel: { type: String, default: 'ফ্রি ট্রায়াল শুরু করুন' },
    showIntro: { type: Boolean, default: true },
    locale: { type: String, default: 'bn' },
});

const isEn = computed(() => props.locale === 'en');
const inputs = computed(() => props.config?.inputs ?? {});

const model = reactive({
    ad_spend: inputs.value.ad_spend?.default ?? 50000,
    pixel_purchases: inputs.value.pixel_purchases?.default ?? 200,
    fake_cancel_rate: inputs.value.fake_cancel_rate?.default ?? 30,
    aov: inputs.value.aov?.default ?? 1200,
});

let toolActionSent = false;
watch(
    () => [model.ad_spend, model.pixel_purchases, model.fake_cancel_rate, model.aov],
    () => {
        if (toolActionSent) {
            return;
        }
        toolActionSent = true;
        const path = typeof window !== 'undefined' ? window.location.pathname : '/';
        trackToolAction(path, 'ads_roas_calculate');
    },
);

const toBnDigits = (value) =>
    String(value).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[Number(d)]);

const formatNumber = (value, decimals = 0) => {
    const n = Number(value);
    if (! Number.isFinite(n)) {
        return isEn.value ? '0' : toBnDigits('0');
    }
    const fixed = decimals > 0 ? n.toFixed(decimals) : Math.round(n).toString();
    const [intPart, decPart] = fixed.split('.');
    const withCommas = Number(intPart).toLocaleString('en-US');
    const formatted = decPart ? `${withCommas}.${decPart}` : withCommas;
    return isEn.value ? formatted : toBnDigits(formatted);
};

const formatTaka = (value) => `৳${formatNumber(value)}`;

const confirmedPurchases = computed(() =>
    Math.round(model.pixel_purchases * (1 - model.fake_cancel_rate / 100)),
);
const fakePurchases = computed(() => Math.max(0, model.pixel_purchases - confirmedPurchases.value));
const reportedRevenue = computed(() => Math.round(model.pixel_purchases * model.aov));
const realRevenue = computed(() => Math.round(confirmedPurchases.value * model.aov));
const reportedRoas = computed(() =>
    model.ad_spend > 0 ? reportedRevenue.value / model.ad_spend : 0,
);
const realRoas = computed(() =>
    model.ad_spend > 0 ? realRevenue.value / model.ad_spend : 0,
);
const wastedSpend = computed(() =>
    Math.round(model.ad_spend * (model.fake_cancel_rate / 100)),
);
const fakeRevenueSignal = computed(() => Math.round(fakePurchases.value * model.aov));

const sliderKeys = ['ad_spend', 'pixel_purchases', 'fake_cancel_rate', 'aov'];

const displayValue = (key) => {
    const slider = inputs.value[key];
    if (!slider) {
        return '';
    }
    return `${slider.prefix ?? ''}${formatNumber(model[key])}${slider.suffix ?? ''}`;
};

const copy = computed(() => (isEn.value
    ? {
        reported: 'Pixel reported ROAS',
        reportedDetail: (purchases, aov, revenue) =>
            `${formatNumber(purchases)} purchases × ${formatTaka(aov)} = ${formatTaka(revenue)}`,
        real: 'Real ROAS (confirmed orders)',
        realDetail: (confirmed, revenue) =>
            `≈ ${formatNumber(confirmed)} confirmed · revenue ${formatTaka(revenue)}`,
        fake: 'Fake purchase signal',
        fakeDetail: (fake, signal) =>
            `${formatNumber(fake)} · ≈ ${formatTaka(signal)}`,
        waste: (waste, rate) =>
            `Estimated ad budget waste ≈ ${formatTaka(waste)} (${formatNumber(rate)}% of spend)`,
    }
    : {
        reported: 'Pixel রিপোর্টেড ROAS',
        reportedDetail: (purchases, aov, revenue) =>
            `${formatNumber(purchases)}টি Purchase × ${formatTaka(aov)} = ${formatTaka(revenue)}`,
        real: 'আসল ROAS (কনফার্মড অর্ডার)',
        realDetail: (confirmed, revenue) =>
            `≈ ${formatNumber(confirmed)}টি কনফার্মড · রেভিনিউ ${formatTaka(revenue)}`,
        fake: 'ফেক Purchase সিগন্যাল',
        fakeDetail: (fake, signal) =>
            `${formatNumber(fake)}টি · ≈ ${formatTaka(signal)}`,
        waste: (waste, rate) =>
            `আনুমানিক অ্যাড বাজেট অপচয় ≈ ${formatTaka(waste)} (স্পেন্ডের ${formatNumber(rate)}%)`,
    }));
</script>

<template>
    <section id="ads-roas" class="scroll-mt-24 border-y border-white/10 bg-[#111111] py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div v-if="showIntro" class="text-center">
                <span class="inline-flex rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs font-bold text-sky-300">
                    {{ config.badge ?? 'Ads ROAS' }}
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    {{ config.headline }}
                </h2>
                <p v-if="config.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ config.subtitle }}
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-2 lg:gap-6" :class="{ 'mt-0': !showIntro }">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-6">
                    <div class="space-y-6">
                        <div v-for="key in sliderKeys" :key="key">
                            <div class="flex items-baseline justify-between gap-3">
                                <label :for="`roas-${key}`" class="text-sm font-medium text-slate-300">
                                    {{ inputs[key]?.label }}
                                </label>
                                <span class="text-lg font-bold text-amber-300">{{ displayValue(key) }}</span>
                            </div>
                            <input
                                :id="`roas-${key}`"
                                v-model.number="model[key]"
                                type="range"
                                :min="inputs[key]?.min"
                                :max="inputs[key]?.max"
                                :step="inputs[key]?.step"
                                :class="RANGE_SLIDER_CLASS"
                                :style="rangeTrackStyle(model[key], inputs[key]?.min, inputs[key]?.max)"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-2xl border border-rose-500/25 bg-rose-950/20 p-5 sm:p-6">
                        <p class="text-sm text-rose-200/80">{{ copy.reported }}</p>
                        <p class="mt-1 text-3xl font-extrabold text-rose-300 sm:text-4xl">
                            {{ formatNumber(reportedRoas, 2) }}x
                        </p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ copy.reportedDetail(model.pixel_purchases, model.aov, reportedRevenue) }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950/40 to-emerald-900/10 p-5 sm:p-6">
                        <p class="text-sm text-emerald-200/90">{{ copy.real }}</p>
                        <p class="mt-1 text-4xl font-extrabold text-emerald-300 sm:text-5xl">
                            {{ formatNumber(realRoas, 2) }}x
                        </p>
                        <p class="mt-2 text-xs text-emerald-100/70">
                            {{ copy.realDetail(confirmedPurchases, realRevenue) }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-amber-500/25 bg-amber-950/20 p-5">
                        <p class="text-sm text-amber-200/80">{{ copy.fake }}</p>
                        <p class="mt-1 text-xl font-extrabold text-amber-300">
                            {{ copy.fakeDetail(fakePurchases, fakeRevenueSignal) }}
                        </p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ copy.waste(wastedSpend, model.fake_cancel_rate) }}
                        </p>
                    </div>

                    <p
                        v-if="config.subscription_note"
                        class="rounded-xl border border-white/15 bg-black/20 px-3 py-2.5 text-sm font-semibold leading-relaxed text-white"
                    >
                        {{ config.subscription_note }}
                    </p>
                    <Link
                        :href="primaryCtaUrl"
                        class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-3 text-sm font-bold text-black shadow-lg shadow-amber-900/40 transition hover:from-amber-400 hover:to-yellow-400"
                    >
                        {{ primaryCtaLabel }}
                    </Link>
                </div>
            </div>

            <p v-if="config.note" class="mt-5 text-center text-xs text-slate-500">
                {{ config.note }}
            </p>
        </div>
    </section>
</template>
