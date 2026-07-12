<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    config: { type: Object, default: () => ({}) },
    scenarios: { type: Array, default: () => [] },
    primaryCtaUrl: { type: String, default: '#' },
    primaryCtaLabel: { type: String, default: 'ফ্রি ট্রায়াল শুরু করুন' },
});

const inputs = computed(() => props.config?.inputs ?? {});

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

const formatBnNumber = (value) => toBnDigits(Number(value).toLocaleString('en-US'));
const formatTaka = (value) => `৳${formatBnNumber(value)}`;

const sliders = computed(() =>
    ['daily_orders', 'return_rate', 'cost_per_return']
        .filter((key) => inputs.value[key])
        .map((key) => ({ key, ...inputs.value[key] })),
);

const displayValue = (slider) =>
    `${slider.prefix ?? ''}${formatBnNumber(model[slider.key])}${slider.suffix ?? ''}`;

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
            <div class="text-center">
                <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                    {{ config.badge ?? 'ROI ক্যালকুলেটর' }}
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    {{ config.headline ?? 'মাসে কত টাকা বাঁচবে — নিজেই হিসাব করুন' }}
                </h2>
                <p v-if="config.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ config.subtitle }}
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-2 lg:gap-6">
                <!-- Controls -->
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
                                class="mt-3 h-3 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-amber-500 [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-amber-400 [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-amber-400"
                            />
                            <div class="mt-1 flex justify-between text-[11px] text-slate-500">
                                <span>{{ slider.prefix ?? '' }}{{ formatBnNumber(slider.min) }}{{ slider.suffix ?? '' }}</span>
                                <span>{{ slider.prefix ?? '' }}{{ formatBnNumber(slider.max) }}{{ slider.suffix ?? '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div class="flex flex-col gap-4">
                    <div class="rounded-2xl border border-rose-500/25 bg-rose-950/20 p-5 sm:p-6">
                        <p class="text-sm text-rose-200/80">বর্তমান মাসিক রিটার্ন লস</p>
                        <p class="mt-1 text-3xl font-extrabold text-rose-300 sm:text-4xl">
                            ≈ {{ formatTaka(currentMonthlyLoss) }}
                        </p>
                        <p class="mt-2 text-xs text-slate-400">
                            মাসে ~{{ formatBnNumber(monthlyReturns) }}টি রিটার্ন × {{ formatTaka(model.cost_per_return) }}
                        </p>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950/40 to-emerald-900/10 p-5 sm:p-6">
                        <p class="text-sm text-emerald-200/90">WooEasyLife দিয়ে সম্ভাব্য মাসিক সাশ্রয়</p>
                        <p class="mt-1 text-4xl font-extrabold text-emerald-300 sm:text-5xl">
                            ≈ {{ formatTaka(monthlySavings) }}
                        </p>
                        <p class="mt-2 text-xs text-emerald-100/70">
                            রিটার্ন রেট {{ formatBnNumber(model.return_rate) }}% → ~{{ formatBnNumber(reducedRate) }}%
                            ({{ formatBnNumber(returnsAvoided) }}টি রিটার্ন আটকে)
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

            <!-- Supporting savings scenarios -->
            <div v-if="scenarios.length" class="mt-12">
                <p class="text-center text-sm font-semibold text-slate-400">আরও যেসব কাজে আপনার সময় ও খরচ কমবে</p>
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
