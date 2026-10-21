<template>
    <MerchantPortalLayout title="Search">
        <div class="space-y-4 pb-4 lg:pb-0">
            <section class="box-bg box-color box-border overflow-hidden rounded-2xl border">
                <form class="px-4 py-4 sm:px-5" @submit.prevent="search">
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h1 class="text-lg font-semibold" style="color: var(--mp-ink)">Track courier orders</h1>
                            <p class="mt-0.5 text-sm" style="color: var(--mp-muted)">
                                One phone. Safe is 80% or higher, review is 50–79%, high risk is under 50%.
                            </p>
                        </div>
                        <p
                            class="rounded-full border px-3 py-1 text-xs font-medium"
                            :class="quota.remaining < 1 ? 'border-rose-600/30 text-rose-700 dark:text-rose-300' : ''"
                            :style="quota.remaining < 1 ? undefined : { borderColor: 'var(--mp-line)', color: 'var(--mp-muted)' }"
                        >
                            {{ quota.remaining }} of {{ quota.daily_limit }} left today
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <label class="sr-only" for="courier-phone">Customer phone</label>
                        <input
                            id="courier-phone"
                            ref="phoneInput"
                            v-model="phone"
                            type="tel"
                            inputmode="tel"
                            enterkeyhint="search"
                            autocomplete="tel"
                            placeholder="017XXXXXXXX"
                            class="merchant-field min-h-12 w-full min-w-0 flex-1 rounded-xl px-3 font-mono text-base outline-none"
                            :disabled="searching"
                        />
                        <div class="w-full sm:w-auto">
                            <Button
                                type="submit"
                                label="Search"
                                icon="pi pi-search"
                                class="!min-h-12 !w-full !justify-center sm:!px-5"
                                :loading="searching"
                                :disabled="searching || phone.trim() === ''"
                            />
                        </div>
                    </div>
                    <p v-if="formError" class="mt-2 text-sm text-rose-600" role="alert">{{ formError }}</p>
                    <p v-else class="mt-2 text-xs" style="color: var(--mp-muted)">
                        017…, 880…, and +880… of the same number count once today.
                    </p>
                </form>
            </section>

            <section
                v-if="searching && !result"
                class="space-y-3"
                aria-live="polite"
            >
                <div class="h-20 animate-pulse rounded-2xl border" style="border-color: var(--mp-line); background: var(--mp-surface)" />
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-2xl border" style="border-color: var(--mp-line); background: var(--mp-surface)" />
                </div>
                <div class="h-56 animate-pulse rounded-2xl border" style="border-color: var(--mp-line); background: var(--mp-surface)" />
            </section>

            <template v-else-if="result">
                <section
                    class="rounded-2xl border px-4 py-3 sm:px-5"
                    :class="[bannerClass, searching ? 'opacity-60' : '']"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-sm font-bold" :class="badgeClass">{{ verdict.title }}</span>
                                <p class="break-all font-mono text-lg font-bold tracking-wide">{{ result.phone }}</p>
                            </div>
                            <p class="mt-1 text-sm leading-snug">{{ verdict.detail }}</p>
                        </div>
                        <p v-if="result.source === 'cache'" class="text-xs font-medium">Already checked today</p>
                        <p v-else-if="searching" class="text-xs font-medium">Checking…</p>
                    </div>
                </section>

                <div class="grid grid-cols-2 gap-2.5 sm:gap-3 md:grid-cols-4" :class="searching ? 'opacity-60' : ''">
                    <article class="box-bg box-color box-border rounded-2xl border px-3 py-3 sm:px-4">
                        <div class="flex items-center gap-3">
                            <div class="hidden h-14 w-14 shrink-0 place-items-center rounded-full sm:grid" :style="gaugeStyle" aria-hidden="true">
                                <div class="h-10 w-10 rounded-full" style="background: var(--mp-surface)" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--mp-muted)">Success</p>
                                <p class="text-xl font-bold" :class="rateClass">{{ rateLabel }}</p>
                            </div>
                        </div>
                    </article>
                    <article class="box-bg box-color box-border rounded-2xl border px-3 py-3 sm:px-4">
                        <p class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--mp-muted)">Delivered</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ deliveredLine }}</p>
                        <p class="text-xs" style="color: var(--mp-muted)">{{ parcelLabel(result.received) }}</p>
                    </article>
                    <article class="box-bg box-color box-border rounded-2xl border px-3 py-3 sm:px-4">
                        <p class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--mp-muted)">Cancelled</p>
                        <p class="mt-1 text-xl font-bold text-rose-700 dark:text-rose-300">{{ cancelledLine }}</p>
                        <p class="text-xs" style="color: var(--mp-muted)">{{ parcelLabel(result.cancelled) }}</p>
                    </article>
                    <article class="box-bg box-color box-border rounded-2xl border px-3 py-3 sm:px-4">
                        <p class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--mp-muted)">Orders</p>
                        <p class="mt-1 text-xl font-bold" style="color: var(--mp-ink)">{{ result.total_orders }}</p>
                        <p class="text-xs" style="color: var(--mp-muted)">
                            <template v-if="steadfastParcels !== null">Steadfast {{ steadfastParcels }}</template>
                            <template v-else-if="result.partial">{{ result.partial }} partial</template>
                            <template v-else>Across couriers</template>
                        </p>
                    </article>
                </div>

                <section class="box-bg box-color box-border overflow-hidden rounded-2xl border" :class="searching ? 'opacity-60' : ''">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 sm:px-5" style="border-color: var(--mp-line)">
                        <h2 class="text-sm font-semibold" style="color: var(--mp-ink)">Courier breakdown</h2>
                        <p v-if="result.partial" class="text-xs" style="color: var(--mp-muted)">{{ result.partial }} partial, counted separately</p>
                    </div>

                    <ul class="md:hidden">
                        <li
                            v-for="courier in result.couriers"
                            :key="`card-${courier.title}`"
                            class="border-b px-4 py-3 last:border-b-0"
                            style="border-color: var(--mp-line)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="flex h-9 w-14 shrink-0 items-center justify-center rounded-lg border bg-white px-1 dark:border-white/10">
                                        <img
                                            v-if="courierLogo(courier.title)"
                                            :src="courierLogo(courier.title)"
                                            alt=""
                                            class="h-5 w-auto max-w-full object-contain"
                                        >
                                        <span v-else class="text-[10px] font-semibold uppercase" style="color: var(--mp-muted)">{{ courierInitial(courier.title) }}</span>
                                    </span>
                                    <span class="truncate font-medium" style="color: var(--mp-ink)">{{ courier.title }}</span>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="courierRateClass(courier)">
                                    {{ percentLabel(courier.success_rate) }}
                                </span>
                            </div>
                            <dl class="mt-3 grid gap-2 text-center" :class="showPartial ? 'grid-cols-4' : 'grid-cols-3'">
                                <div class="rounded-xl px-1 py-2" style="background: rgba(255, 193, 7, 0.08)">
                                    <dt class="text-[10px] uppercase tracking-wide" style="color: var(--mp-muted)">Total</dt>
                                    <dd class="mt-0.5 text-sm font-semibold tabular-nums" :class="countClass(courierTotal(courier))">{{ courierTotal(courier) }}</dd>
                                </div>
                                <div class="rounded-xl px-1 py-2" style="background: rgba(255, 193, 7, 0.08)">
                                    <dt class="text-[10px] uppercase tracking-wide" style="color: var(--mp-muted)">Success</dt>
                                    <dd class="mt-0.5 text-sm font-semibold tabular-nums" :class="countClass(courier.received, 'text-emerald-700 dark:text-emerald-300')">{{ courier.received }}</dd>
                                </div>
                                <div class="rounded-xl px-1 py-2" style="background: rgba(255, 193, 7, 0.08)">
                                    <dt class="text-[10px] uppercase tracking-wide" style="color: var(--mp-muted)">Cancel</dt>
                                    <dd class="mt-0.5 text-sm font-semibold tabular-nums" :class="countClass(courier.cancelled, 'text-rose-700 dark:text-rose-300')">{{ courier.cancelled }}</dd>
                                </div>
                                <div v-if="showPartial" class="rounded-xl px-1 py-2" style="background: rgba(255, 193, 7, 0.08)">
                                    <dt class="text-[10px] uppercase tracking-wide" style="color: var(--mp-muted)">Partial</dt>
                                    <dd class="mt-0.5 text-sm font-semibold tabular-nums" :class="countClass(courier.partial, 'text-amber-700 dark:text-amber-300')">{{ courier.partial }}</dd>
                                </div>
                            </dl>
                        </li>
                        <li v-if="!result.couriers?.length" class="px-4 py-8 text-center text-sm" style="color: var(--mp-muted)">
                            No courier breakdown for this number.
                        </li>
                        <li
                            v-if="result.couriers?.length"
                            class="px-4 py-3"
                            style="background: rgba(255, 193, 7, 0.08)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold" style="color: var(--mp-ink)">Total</p>
                                <p class="font-semibold" :class="rateClass">{{ rateLabel }}</p>
                            </div>
                            <p class="mt-1 text-xs" style="color: var(--mp-muted)">
                                {{ result.total_orders }} orders · {{ result.received }} delivered · {{ result.cancelled }} cancelled
                                <template v-if="showPartial"> · {{ result.partial }} partial</template>
                            </p>
                        </li>
                    </ul>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wide" style="color: var(--mp-muted)">
                                    <th class="px-4 py-2 font-medium sm:px-5">Courier</th>
                                    <th class="px-3 py-2 text-right font-medium">Rate</th>
                                    <th class="px-3 py-2 text-right font-medium">Total</th>
                                    <th class="px-3 py-2 text-right font-medium">Success</th>
                                    <th class="px-3 py-2 text-right font-medium">Cancel</th>
                                    <th v-if="showPartial" class="px-3 py-2 text-right font-medium sm:pr-5">Partial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="courier in result.couriers"
                                    :key="courier.title"
                                    class="border-t"
                                    style="border-color: var(--mp-line)"
                                >
                                    <td class="px-4 py-2.5 sm:px-5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-8 w-14 shrink-0 items-center justify-center rounded-lg border bg-white px-1 dark:border-white/10">
                                                <img
                                                    v-if="courierLogo(courier.title)"
                                                    :src="courierLogo(courier.title)"
                                                    alt=""
                                                    class="h-5 w-auto max-w-full object-contain"
                                                >
                                                <span v-else class="text-[10px] font-semibold uppercase" style="color: var(--mp-muted)">{{ courierInitial(courier.title) }}</span>
                                            </span>
                                            <span class="font-medium" style="color: var(--mp-ink)">{{ courier.title }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <span class="inline-block min-w-12 rounded-full px-2 py-0.5 text-xs font-semibold" :class="courierRateClass(courier)">
                                            {{ percentLabel(courier.success_rate) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums" :class="countClass(courierTotal(courier))">{{ courierTotal(courier) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums" :class="countClass(courier.received, 'text-emerald-700 dark:text-emerald-300')">{{ courier.received }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums" :class="countClass(courier.cancelled, 'text-rose-700 dark:text-rose-300')">{{ courier.cancelled }}</td>
                                    <td v-if="showPartial" class="px-3 py-2.5 text-right tabular-nums sm:pr-5" :class="countClass(courier.partial, 'text-amber-700 dark:text-amber-300')">{{ courier.partial }}</td>
                                </tr>
                                <tr v-if="!result.couriers?.length">
                                    <td :colspan="showPartial ? 6 : 5" class="px-4 py-8 text-center text-sm" style="color: var(--mp-muted)">
                                        No courier breakdown for this number.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="result.couriers?.length">
                                <tr class="border-t font-semibold" style="border-color: var(--mp-line); background: rgba(255, 193, 7, 0.08)">
                                    <td class="px-4 py-2.5 sm:px-5" style="color: var(--mp-ink)">Total</td>
                                    <td class="px-3 py-2.5 text-right" :class="rateClass">{{ rateLabel }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums" style="color: var(--mp-ink)">{{ result.total_orders }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ result.received }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ result.cancelled }}</td>
                                    <td v-if="showPartial" class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300 sm:pr-5">{{ result.partial }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section v-if="notes.length" class="box-bg box-color box-border rounded-2xl border px-4 py-3 sm:px-5">
                    <h2 class="text-sm font-semibold" style="color: var(--mp-ink)">Fraud notes</h2>
                    <ul class="mt-2 space-y-2">
                        <li
                            v-for="(note, index) in notes"
                            :key="`${note.courier}-${index}`"
                            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-950 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-50"
                        >
                            <span class="font-semibold">{{ note.courier }}.</span>
                            {{ note.text }}
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import { usePage } from "@inertiajs/vue3";
import axios from "axios";
import Button from "primevue/button";
import { computed, onMounted, ref } from "vue";

const page = usePage();
const phone = ref("");
const phoneInput = ref(null);
const searching = ref(false);
const formError = ref("");
const result = ref(null);
const quota = ref(page.props.auth?.portal?.courier_history ?? {
    remaining: 0,
    daily_limit: 0,
    used_today: 0,
    enabled: false,
});

onMounted(() => {
    if (window.matchMedia("(pointer: fine)").matches) {
        phoneInput.value?.focus();
    }
});

const rateLabel = computed(() => percentLabel(result.value?.success_rate));

const verdict = computed(() => {
    const row = result.value;
    if (!row) return { title: "", detail: "" };
    if (row.status === "error") {
        return { title: "Check failed", detail: "The couriers did not answer. Try this number again." };
    }
    if (row.status === "not_found" || row.risk === null) {
        return { title: "No history", detail: "No courier orders were found for this number." };
    }
    if (row.risk === "safe") {
        return { title: "Safe", detail: `Strong delivery success rate (${rateLabel.value}).` };
    }
    if (row.risk === "review") {
        return { title: "Review", detail: `Confirm this number before shipping. Success rate is ${rateLabel.value}.` };
    }
    return { title: "High risk", detail: `Low delivery success rate (${rateLabel.value}).` };
});

const bannerClass = computed(() => {
    const row = result.value;
    if (!row || row.status === "error" || row.risk === "high") {
        return "border-rose-600/30 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100";
    }
    if (row.risk === "review") {
        return "border-amber-600/30 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100";
    }
    if (row.risk === "safe") {
        return "border-emerald-600/30 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100";
    }
    return "border-slate-300 bg-slate-50 text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-slate-200";
});

const badgeClass = computed(() => {
    const row = result.value;
    if (!row || row.status === "error" || row.risk === "high") return "bg-rose-600 text-white";
    if (row.risk === "review") return "bg-amber-500 text-[#1a1408]";
    if (row.risk === "safe") return "bg-emerald-600 text-white";
    return "bg-slate-500 text-white";
});

const rateClass = computed(() => {
    if (result.value?.risk === "safe") return "text-emerald-700 dark:text-emerald-300";
    if (result.value?.risk === "review") return "text-amber-700 dark:text-amber-300";
    if (result.value?.risk === "high") return "text-rose-700 dark:text-rose-300";
    return "text-gray-500";
});

const gaugeStyle = computed(() => {
    const rate = Number(result.value?.success_rate);
    const value = Number.isFinite(rate) ? Math.max(0, Math.min(100, rate)) : 0;
    const color = result.value?.risk === "high"
        ? "#e11d48"
        : result.value?.risk === "review"
            ? "#d97706"
            : result.value?.risk === "safe"
                ? "#059669"
                : "#94a3b8";
    return {
        background: `conic-gradient(${color} ${value * 3.6}deg, rgba(148, 163, 184, 0.28) 0deg)`,
    };
});

const deliveredLine = computed(() => {
    const share = sharePercent(result.value?.received, result.value?.cancelled);
    return share === null ? `${result.value?.received ?? 0}` : `${share}%`;
});

const cancelledLine = computed(() => {
    const share = sharePercent(result.value?.cancelled, result.value?.received);
    return share === null ? `${result.value?.cancelled ?? 0}` : `${share}%`;
});

const steadfastParcels = computed(() => {
    const courier = (result.value?.couriers ?? []).find((row) => String(row.title || "").toLowerCase().includes("stead"));
    if (!courier) return null;
    return courierTotal(courier);
});

const showPartial = computed(() => Number(result.value?.partial || 0) > 0);

const notes = computed(() => {
    const rows = [];
    for (const courier of result.value?.couriers ?? []) {
        for (const text of courier.notes ?? []) {
            if (isCustomerFraudNote(text)) rows.push({ courier: courier.title, text });
        }
    }
    return rows;
});

function isCustomerFraudNote(text) {
    const lower = String(text || "").trim().toLowerCase();
    if (lower === "") return false;
    return ![
        "login failed",
        "re-login",
        "token expired",
        "session expired",
        "credential",
        "curl error",
        "connection reset",
        "sqlstate",
        "stack trace",
        "smart-check failed",
        "stats request failed",
        "customer lookup failed",
        "http 4",
        "http 5",
        "vendor/",
        ".php",
    ].some((needle) => lower.includes(needle));
}

function percentLabel(value) {
    return value === null || value === undefined || value === "" ? "—" : `${value}%`;
}

function sharePercent(part, other) {
    const left = Number(part);
    const right = Number(other);
    if (!Number.isFinite(left) || !Number.isFinite(right) || left + right < 1) return null;
    return Math.round((left / (left + right)) * 100);
}

function parcelLabel(count) {
    const value = Number(count) || 0;
    return `${value} parcel${value === 1 ? "" : "s"}`;
}

function courierTotal(courier) {
    return Number(courier.received || 0) + Number(courier.cancelled || 0) + Number(courier.partial || 0);
}

function countClass(value, active = "") {
    return Number(value) > 0 ? active : "text-slate-400 dark:text-slate-500";
}

function courierRateClass(courier) {
    const rate = courier.success_rate;
    if (rate === null || rate === undefined || rate === "") return "bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300";
    if (rate >= 80) return "bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200";
    if (rate >= 50) return "bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200";
    return "bg-rose-100 text-rose-800 dark:bg-rose-500/25 dark:text-rose-100";
}

function courierInitial(title) {
    return String(title || "?").slice(0, 2);
}

function courierLogo(title) {
    const key = String(title || "").toLowerCase();
    if (key.includes("stead")) return "/images/steadfast.svg";
    if (key.includes("pathao")) return "/images/pathao.svg";
    if (key.includes("paper")) return "/images/paperfly.png";
    if (key.includes("red")) return "/images/redx.svg";
    if (key.includes("carry")) return "/images/carrybee.svg";
    return null;
}

async function search() {
    formError.value = "";
    if (phone.value.trim() === "" || searching.value) return;
    searching.value = true;
    try {
        const { data } = await axios.post(route("portal.fraud-check.search.store"), {
            phone: phone.value,
        }, { timeout: 180000 });
        result.value = data.result ? { ...data.result, source: data.source } : null;
        if (data.quota) quota.value = data.quota;
    } catch (error) {
        result.value = null;
        formError.value = error?.response?.data?.errors?.phone?.[0]
            || error?.response?.data?.message
            || "Could not check this number.";
    } finally {
        searching.value = false;
    }
}
</script>
