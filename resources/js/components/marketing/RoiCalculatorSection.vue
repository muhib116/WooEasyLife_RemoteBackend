<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { RANGE_SLIDER_CLASS, rangeTrackStyle } from '@/utils/rangeSlider';

const props = defineProps({
    config: { type: Object, default: () => ({}) },
    scenarios: { type: Array, default: () => [] },
    primaryCtaUrl: { type: String, default: '#' },
    primaryCtaLabel: { type: String, default: 'ফ্রি ট্রায়াল শুরু করুন' },
    /** When false, hide badge/headline (use on dedicated SEO pages with their own H1). */
    showIntro: { type: Boolean, default: true },
    /** Optional link to the standalone calculator page (homepage section). */
    dedicatedPageHref: { type: String, default: null },
    dedicatedPageLabel: { type: String, default: 'আলাদা পেজে খুলুন' },
    /** 'bn' uses Bangla digits; 'en' uses Western digits + English UI strings from config.ui */
    locale: { type: String, default: 'bn' },
});

const inputs = computed(() => props.config?.inputs ?? {});
const isEn = computed(() => props.locale === 'en');

const model = reactive({
    daily_orders: inputs.value.daily_orders?.default ?? 50,
    return_rate: inputs.value.return_rate?.default ?? 25,
    cost_per_return: inputs.value.cost_per_return?.default ?? 120,
});

const daysPerMonth = computed(() => Number(props.config?.days_per_month ?? 30));
const reductionPercent = computed(() => Number(props.config?.reduction_percent ?? 40));

const monthlyOrders = computed(() => model.daily_orders * daysPerMonth.value);
const monthlyReturns = computed(() => Math.round(monthlyOrders.value * (model.return_rate / 100)));
const currentMonthlyLoss = computed(() => Math.round(monthlyReturns.value * model.cost_per_return));
const returnsAvoided = computed(() => Math.round(monthlyReturns.value * (reductionPercent.value / 100)));
const monthlySavings = computed(() => Math.round(returnsAvoided.value * model.cost_per_return));
const reducedRate = computed(() =>
    Math.max(0, Math.round(model.return_rate * (1 - reductionPercent.value / 100))),
);

const toBnDigits = (value) =>
    String(value).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[Number(d)]);

const formatNumber = (value) => {
    const formatted = Number(value).toLocaleString('en-US');
    return isEn.value ? formatted : toBnDigits(formatted);
};
const formatTaka = (value) => `৳${formatNumber(value)}`;

const ui = computed(() => {
    const fromConfig = props.config?.ui ?? {};
    const defaults = isEn.value
        ? {
              current_loss: 'Current monthly return loss',
              returns_line: '~{returns} returns/month × {cost}',
              savings: 'Estimated monthly savings with WooEasyLife',
              rate_line: 'Return rate {from}% → ~{to}% ({avoided} returns blocked)',
              more_savings: 'Other ways you save time and money',
          }
        : {
              current_loss: 'বর্তমান মাসিক রিটার্ন লস',
              returns_line: 'মাসে ~{returns}টি রিটার্ন × {cost}',
              savings: 'WooEasyLife দিয়ে সম্ভাব্য মাসিক সাশ্রয়',
              rate_line: 'রিটার্ন রেট {from}% → ~{to}% ({avoided}টি রিটার্ন আটকে)',
              more_savings: 'আরও যেসব কাজে আপনার সময় ও খরচ কমবে',
          };

    return { ...defaults, ...fromConfig };
});

const fillTemplate = (template, vars) =>
    Object.entries(vars).reduce((text, [key, val]) => text.replaceAll(`{${key}}`, String(val)), template);

const returnsLine = computed(() =>
    fillTemplate(ui.value.returns_line, {
        returns: formatNumber(monthlyReturns.value),
        cost: formatTaka(model.cost_per_return),
    }),
);

const rateLine = computed(() =>
    fillTemplate(ui.value.rate_line, {
        from: formatNumber(model.return_rate),
        to: formatNumber(reducedRate.value),
        avoided: formatNumber(returnsAvoided.value),
    }),
);

const sliders = computed(() =>
    ['daily_orders', 'return_rate', 'cost_per_return']
        .filter((key) => inputs.value[key])
        .map((key) => ({ key, ...inputs.value[key] })),
);

const displayValue = (slider) =>
    `${slider.prefix ?? ''}${formatNumber(model[slider.key])}${slider.suffix ?? ''}`;

const accentClass = (accent) => {
    const map = {
        rose: 'border-rose-500/25 bg-rose-950/20',
        amber: 'border-amber-500/25 bg-amber-950/20',
        sky: 'border-sky-500/25 bg-sky-950/20',
        violet: 'border-amber-500/25 bg-amber-950/20',
        emerald: 'border-emerald-500/25 bg-emerald-950/20',
    };

    return map[accent] ?? map.violet;
};

const titleClass = (accent) => {
    const map = {
        rose: 'text-rose-300',
        amber: 'text-amber-300',
        sky: 'text-sky-300',
        violet: 'text-amber-300',
        emerald: 'text-emerald-300',
    };

    return map[accent] ?? map.violet;
};
</script>

<template>
    <section id="roi" class="scroll-mt-24 border-y border-white/10 bg-[#111111] py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div v-if="showIntro" class="text-center">
                <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                    {{ config.badge ?? (isEn ? 'ROI calculator' : 'ROI ক্যালকুলেটর') }}
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    {{ config.headline ?? (isEn ? 'How much can you save monthly?' : 'মাসে কত টাকা বাঁচবে — নিজেই হিসাব করুন') }}
                </h2>
                <p v-if="config.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ config.subtitle }}
                </p>
                <p v-if="dedicatedPageHref" class="mt-3">
                    <Link
                        :href="dedicatedPageHref"
                        class="text-sm font-semibold text-amber-400 hover:text-amber-300"
                    >
                        {{ dedicatedPageLabel }} →
                    </Link>
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-2 lg:gap-6" :class="{ 'mt-0': !showIntro }">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-6">
                    <div class="space-y-6">
                        <div v-for="slider in sliders" :key="slider.key">
                            <div class="flex items-baseline justify-between gap-3">
                                <label :for="`roi-${slider.key}`" class="text-sm font-medium text-slate-300">
                                    {{ slider.label }}
                                </label>
                                <span class="text-lg font-bold text-amber-300">{{ displayValue(slider) }}</span>
                            </div>
                            <input
                                :id="`roi-${slider.key}`"
                                v-model.number="model[slider.key]"
                                type="range"
                                :min="slider.min"
                                :max="slider.max"
                                :step="slider.step"
                                :class="RANGE_SLIDER_CLASS"
                                :style="rangeTrackStyle(model[slider.key], slider.min, slider.max)"
                            />
                            <div class="mt-1 flex justify-between text-[11px] text-slate-500">
                                <span>{{ slider.prefix ?? '' }}{{ formatNumber(slider.min) }}{{ slider.suffix ?? '' }}</span>
                                <span>{{ slider.prefix ?? '' }}{{ formatNumber(slider.max) }}{{ slider.suffix ?? '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-2xl border border-rose-500/25 bg-rose-950/20 p-5 sm:p-6">
                        <p class="text-sm text-rose-200/80">{{ ui.current_loss }}</p>
                        <p class="mt-1 text-3xl font-extrabold text-rose-300 sm:text-4xl">
                            ≈ {{ formatTaka(currentMonthlyLoss) }}
                        </p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ returnsLine }}
                        </p>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950/40 to-emerald-900/10 p-5 sm:p-6">
                        <p class="text-sm text-emerald-200/90">{{ ui.savings }}</p>
                        <p class="mt-1 text-4xl font-extrabold text-emerald-300 sm:text-5xl">
                            ≈ {{ formatTaka(monthlySavings) }}
                        </p>
                        <p class="mt-2 text-xs text-emerald-100/70">
                            {{ rateLine }}
                        </p>
                        <p
                            v-if="config.subscription_note"
                            class="mt-4 rounded-xl border border-white/15 bg-black/20 px-3 py-2.5 text-sm font-semibold leading-relaxed text-white"
                        >
                            {{ config.subscription_note }}
                        </p>
                        <Link
                            :href="primaryCtaUrl"
                            class="mt-5 inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-3 text-sm font-bold text-black shadow-lg shadow-amber-900/40 transition hover:from-amber-400 hover:to-yellow-400"
                        >
                            {{ primaryCtaLabel }}
                        </Link>
                    </div>
                </div>
            </div>

            <p v-if="config.note" class="mt-5 text-center text-xs text-slate-500">
                {{ config.note }}
            </p>

            <div v-if="scenarios.length" class="mt-12">
                <p class="text-center text-sm font-semibold text-slate-400">{{ ui.more_savings }}</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="item in scenarios"
                        :key="item.title"
                        class="rounded-2xl border p-5 sm:p-6"
                        :class="accentClass(item.accent)"
                    >
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">{{ item.icon }}</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-bold" :class="titleClass(item.accent)">{{ item.title }}</h3>
                                <p class="mt-2 text-sm font-medium leading-relaxed text-slate-200">{{ item.calculation }}</p>
                                <p class="mt-3 rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm leading-relaxed text-emerald-200/90">
                                    ✓ {{ item.benefit }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
