<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { RANGE_SLIDER_CLASS, rangeTrackStyle } from '@/utils/rangeSlider';

const props = defineProps({
    config: { type: Object, default: () => ({}) },
    primaryCtaUrl: { type: String, default: '#' },
    primaryCtaLabel: { type: String, default: 'ফ্রি ট্রায়াল শুরু করুন' },
    showIntro: { type: Boolean, default: true },
    locale: { type: String, default: 'bn' },
});

const isEn = computed(() => props.locale === 'en');
const zones = computed(() => props.config?.zones ?? {});
const couriers = computed(() => props.config?.couriers ?? {});
const inputs = computed(() => props.config?.inputs ?? {});
const officialLinks = computed(() => props.config?.official_links ?? []);

const model = reactive({
    zone: Object.keys(zones.value)[0] || 'dhaka',
    weight_kg: inputs.value.weight_kg?.default ?? 1,
    cod_amount: inputs.value.cod_amount?.default ?? 0,
});

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

/** Mirrors steadfast.com.bd/pricing calcPrice for Dhaka-origin parcel. */
const steadfastBillableWeight = (zone, weight) => {
    const w = Math.max(0, Number(weight) || 0);
    if (zone === 'dhaka') {
        if (w <= 0.15) return 0.15;
        if (w <= 0.5) return 0.5;
        return Math.max(1, Math.ceil(w));
    }
    if (w <= 0.5) return 0.5;
    return Math.max(1, Math.ceil(w));
};

const steadfastDelivery = (zone, weight, charges) => {
    const c = charges || {};
    const n = steadfastBillableWeight(zone, weight);
    if (zone === 'dhaka') {
        if (n <= 0.15) return Number(c.samecity_dhaka_150 || 0);
        if (n <= 0.5) return Number(c.samecity_dhaka_500 || 0);
        return Number(c.samecity_dhaka || 0) + Number(c.samecity_weight || 0) * (n - 1);
    }
    if (zone === 'suburb') {
        if (n <= 0.5) return Number(c.isd_to_sub_500 || 0);
        return Number(c.isd_to_sub || 0) + Number(c.isd_to_sub_weight || 0) * (n - 1);
    }
    if (n <= 0.5) return Number(c.isd_to_osd_500 || 0);
    return Number(c.isd_to_osd || 0) + Number(c.isd_to_osd_weight || 0) * (n - 1);
};

const zoneTableDelivery = (courier, zone, weight) => {
    const zoneRate = courier.zones?.[zone] ?? { base: 0, per_kg_extra: 0 };
    const included = Number(courier.included_kg ?? 1);
    const w = Number(weight) || 0;
    const extraKg = Math.max(0, w - included);
    return Number(zoneRate.base) + extraKg * Number(zoneRate.per_kg_extra);
};

const estimateCourier = (courierKey) => {
    const courier = couriers.value[courierKey];
    if (!courier) {
        return null;
    }

    let delivery = 0;
    if (courier.pricing_mode === 'steadfast_live' && courier.charges) {
        delivery = steadfastDelivery(model.zone, model.weight_kg, courier.charges);
    } else {
        delivery = zoneTableDelivery(courier, model.zone, model.weight_kg);
    }

    const codFee = Math.round((Number(model.cod_amount) || 0) * (Number(courier.cod_percent) || 0) / 100);
    const total = Math.round(delivery + codFee);

    return {
        key: courierKey,
        label: courier.label,
        delivery: Math.round(delivery),
        codFee,
        total,
        source: courier.source || 'fallback',
        sourceUrl: courier.source_url || null,
        syncedAt: courier.synced_at || null,
    };
};

const results = computed(() =>
    Object.keys(couriers.value)
        .map((key) => estimateCourier(key))
        .filter(Boolean)
        .sort((a, b) => a.total - b.total),
);

const cheapest = computed(() => results.value[0] ?? null);

const lastSyncedLabel = computed(() => {
    const raw = props.config?.last_synced_at;
    if (!raw) return null;
    try {
        return new Date(raw).toLocaleString(isEn.value ? 'en-GB' : 'bn-BD', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return raw;
    }
});

const ui = computed(() => (isEn.value
    ? {
        zoneLabel: 'Delivery zone (from Dhaka)',
        officialTitle: 'Official calculators',
        cheapest: 'Lowest',
        delivery: 'Delivery',
        codFee: 'COD fee',
        liveRate: 'Live rate',
        approxRate: 'Estimate',
        source: 'Source',
        liveUpdated: 'Live rates updated:',
        defaultBadge: 'Courier charge',
    }
    : {
        zoneLabel: 'ডেলিভারি জোন (ঢাকা থেকে)',
        officialTitle: 'অফিসিয়াল ক্যালকুলেটর',
        cheapest: 'সবচেয়ে কম',
        delivery: 'ডেলিভারি',
        codFee: 'COD ফি',
        liveRate: 'লাইভ রেট',
        approxRate: 'আনুমানিক রেট',
        source: 'সোর্স',
        liveUpdated: 'লাইভ রেট আপডেট:',
        defaultBadge: 'কুরিয়ার চার্জ',
    }));
</script>

<template>
    <section id="courier-charge" class="scroll-mt-24 border-y border-white/10 bg-[#111111] py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div v-if="showIntro" class="text-center">
                <span class="inline-flex rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs font-bold text-sky-300">
                    {{ config.badge ?? ui.defaultBadge }}
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    {{ config.headline }}
                </h2>
                <p v-if="config.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ config.subtitle }}
                </p>
                <p v-if="lastSyncedLabel" class="mt-2 text-xs text-emerald-300/80">
                    {{ ui.liveUpdated }} {{ lastSyncedLabel }}
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-2 lg:gap-6" :class="{ 'mt-0': !showIntro }">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-6">
                    <p class="text-sm font-medium text-slate-300">{{ ui.zoneLabel }}</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="(label, key) in zones"
                            :key="key"
                            type="button"
                            class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                            :class="model.zone === key
                                ? 'border-amber-500/50 bg-amber-500/15 text-amber-200'
                                : 'border-white/10 bg-black/20 text-slate-300 hover:bg-white/10'"
                            @click="model.zone = key"
                        >
                            {{ label }}
                        </button>
                    </div>

                    <div class="mt-6 space-y-6">
                        <div v-for="key in ['weight_kg', 'cod_amount']" :key="key">
                            <div class="flex items-baseline justify-between gap-3">
                                <label :for="`courier-${key}`" class="text-sm font-medium text-slate-300">
                                    {{ inputs[key]?.label }}
                                </label>
                                <span class="text-lg font-bold text-amber-300">
                                    {{ inputs[key]?.prefix || '' }}{{ formatNumber(model[key], key === 'weight_kg' ? 1 : 0) }}{{ inputs[key]?.suffix || '' }}
                                </span>
                            </div>
                            <input
                                :id="`courier-${key}`"
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

                    <div v-if="officialLinks.length" class="mt-6 space-y-1 border-t border-white/10 pt-4 text-xs text-slate-400">
                        <p class="font-semibold text-slate-300">{{ ui.officialTitle }}</p>
                        <a
                            v-for="link in officialLinks"
                            :key="link.url"
                            :href="link.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block text-amber-400 hover:text-amber-300"
                        >
                            {{ link.label }} ↗
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <article
                        v-for="row in results"
                        :key="row.key"
                        class="rounded-2xl border p-4 sm:p-5"
                        :class="cheapest?.key === row.key
                            ? 'border-emerald-500/40 bg-emerald-950/30'
                            : 'border-white/10 bg-white/5'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-white">
                                    {{ row.label }}
                                    <span
                                        v-if="cheapest?.key === row.key"
                                        class="ml-2 rounded-full bg-emerald-500/20 px-2 py-0.5 text-[11px] font-bold text-emerald-300"
                                    >
                                        {{ ui.cheapest }}
                                    </span>
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ ui.delivery }} {{ formatTaka(row.delivery) }}
                                    <span v-if="row.codFee"> · {{ ui.codFee }} {{ formatTaka(row.codFee) }}</span>
                                </p>
                                <p class="mt-1 text-[11px]" :class="row.source === 'live' ? 'text-emerald-400/90' : 'text-slate-500'">
                                    {{ row.source === 'live' ? ui.liveRate : ui.approxRate }}
                                    <a
                                        v-if="row.sourceUrl"
                                        :href="row.sourceUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="ml-1 text-amber-400/90 hover:text-amber-300"
                                    >{{ ui.source }}</a>
                                </p>
                            </div>
                            <p class="text-2xl font-extrabold text-amber-300">{{ formatTaka(row.total) }}</p>
                        </div>
                    </article>

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
            <p v-if="!showIntro && lastSyncedLabel" class="mt-2 text-center text-xs text-emerald-300/80">
                {{ ui.liveUpdated }} {{ lastSyncedLabel }}
            </p>
        </div>
    </section>
</template>
