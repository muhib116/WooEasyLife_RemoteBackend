<template>
    <AuthenticatedLayout title="Fraud Checker">
        <div class="space-y-5">
            <PageHeader
                title="Fraud Checker"
                description="Platform totals for every stored phone. Courier history and plugin orders stay in separate blocks."
                icon="PhChartBar"
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
            />

            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <article
                    v-for="card in headline"
                    :key="card.label"
                    class="box-bg box-color box-border rounded-2xl border p-4 shadow-sm"
                >
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight text-gray-900 dark:text-white">
                        {{ formatCount(card.value) }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ card.hint }}</p>
                </article>
            </section>

            <div class="grid gap-5 xl:grid-cols-2">
                <PageCard
                    title="Plugin order status"
                    description="WooCommerce order statuses saved from the plugin."
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="row in emphasisStatuses"
                            :key="row.key"
                            class="rounded-xl border px-3 py-3"
                            :class="statusTone(row.key)"
                        >
                            <p class="text-xs font-medium">{{ row.label }}</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums">{{ formatCount(row.count) }}</p>
                        </div>
                    </div>
                    <ul class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <li
                            v-for="row in otherStatuses"
                            :key="row.key"
                            class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5"
                        >
                            <span class="text-gray-600 dark:text-gray-300">{{ row.label }}</span>
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCount(row.count) }}</span>
                        </li>
                    </ul>
                </PageCard>

                <PageCard
                    title="From the plugin"
                    description="How those orders arrived."
                >
                    <p v-if="!summary.sources.length" class="text-sm text-gray-500 dark:text-gray-400">
                        No plugin orders yet.
                    </p>
                    <ul v-else class="space-y-3">
                        <li v-for="source in summary.sources" :key="source.key">
                            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-gray-800 dark:text-gray-100">{{ source.label }}</span>
                                <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ formatCount(source.count) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{ width: sourceWidth(source.count) }"
                                />
                            </div>
                        </li>
                    </ul>
                </PageCard>
            </div>

            <PageCard
                title="Courier history"
                description="Sums from saved courier snapshots. Delivered here is a courier confirm count, not a plugin order status."
            >
                <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div v-for="item in courierHeadline" :key="item.label" class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/5">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCount(item.value) }}</p>
                    </div>
                </div>
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <th class="px-3 py-2 font-medium">Courier</th>
                                <th class="px-3 py-2 text-right font-medium">Records</th>
                                <th class="px-3 py-2 text-right font-medium">Orders</th>
                                <th class="px-3 py-2 text-right font-medium">Delivered</th>
                                <th class="px-3 py-2 text-right font-medium">Cancelled</th>
                                <th class="px-3 py-2 text-right font-medium">Partial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!summary.couriers.length">
                                <td colspan="6" class="px-3 py-8 text-center text-gray-500">No courier snapshots yet.</td>
                            </tr>
                            <tr
                                v-for="row in summary.couriers"
                                :key="row.courier"
                                class="border-b last:border-0 dark:border-gray-800"
                            >
                                <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white">{{ row.label }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ formatCount(row.records) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ formatCount(row.orders) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ formatCount(row.delivered) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ formatCount(row.cancelled) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ formatCount(row.partial) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </PageCard>

            <PageCard
                title="One phone"
                description="Look up how many records are stored for a single number."
            >
                <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="search">
                    <label class="sr-only" for="inventory-phone">Phone number</label>
                    <input
                        id="inventory-phone"
                        v-model="form.phone"
                        type="text"
                        inputmode="tel"
                        autocomplete="off"
                        placeholder="01XXXXXXXXX"
                        class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none ring-emerald-500/30 placeholder:text-gray-400 focus:ring-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-60 sm:flex-none sm:px-5"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? "Looking up" : "Look up" }}
                        </button>
                        <button
                            v-if="phone || form.phone"
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                            @click="clearSearch"
                        >
                            Clear
                        </button>
                    </div>
                </form>

                <p
                    v-if="phoneError"
                    class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    {{ phoneError }}
                </p>

                <div v-else-if="person && !person.found" class="mt-4 rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100">No records for {{ person.phone }}</p>
                    <p class="mt-1 text-xs text-gray-500">This number has not been stored on the platform.</p>
                </div>

                <div v-else-if="person" class="mt-5 space-y-5">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ person.phone }}</p>
                            <p v-if="person.name" class="text-sm text-gray-500 dark:text-gray-400">{{ person.name }}</p>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCount(person.snapshots.length) }}</span>
                            {{ person.snapshots.length === 1 ? "courier record" : "courier records" }}
                            ·
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCount(person.orders.total) }}</span>
                            {{ person.orders.total === 1 ? "plugin order" : "plugin orders" }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="row in personEmphasis"
                            :key="row.key"
                            class="rounded-xl border px-3 py-3"
                            :class="statusTone(row.key)"
                        >
                            <p class="text-xs font-medium">{{ row.label }}</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums">{{ formatCount(row.count) }}</p>
                        </div>
                    </div>

                    <ul v-if="personOther.length" class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <li
                            v-for="row in personOther"
                            :key="row.key"
                            class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5"
                        >
                            <span class="text-gray-600 dark:text-gray-300">{{ row.label }}</span>
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCount(row.count) }}</span>
                        </li>
                    </ul>

                    <ul v-if="person.orders.by_source?.length" class="flex flex-wrap gap-2">
                        <li
                            v-for="source in person.orders.by_source"
                            :key="source.key"
                            class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs dark:border-gray-700 dark:bg-white/5"
                        >
                            <span class="text-gray-600 dark:text-gray-300">{{ source.label }}</span>
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatCount(source.count) }}</span>
                        </li>
                    </ul>

                    <div class="max-w-full overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead>
                                <tr class="border-b text-xs font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <th class="px-3 py-2 font-medium">Courier</th>
                                    <th class="px-3 py-2 text-right font-medium">Orders</th>
                                    <th class="px-3 py-2 text-right font-medium">Delivered</th>
                                    <th class="px-3 py-2 text-right font-medium">Cancelled</th>
                                    <th class="px-3 py-2 text-right font-medium">Partial</th>
                                    <th class="px-3 py-2 text-right font-medium">Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!person.snapshots.length">
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">No courier snapshot for this number.</td>
                                </tr>
                                <tr
                                    v-for="row in person.snapshots"
                                    :key="row.courier"
                                    class="border-b last:border-0 dark:border-gray-800"
                                >
                                    <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white">{{ row.label }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ formatCount(row.orders) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ formatCount(row.delivered) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-300">{{ formatCount(row.cancelled) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ formatCount(row.partial) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right text-gray-500">{{ row.fetched_at || "—" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="person.orders.recent.length">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Recent plugin orders</p>
                        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                            <li
                                v-for="(order, index) in person.orders.recent"
                                :key="index"
                                class="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ order.status_label }}</p>
                                    <p class="text-xs text-gray-500">{{ order.source_label }}<span v-if="order.courier"> · {{ order.courier }}</span></p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p v-if="order.amount !== null" class="text-sm tabular-nums text-gray-800 dark:text-gray-100">৳{{ formatCount(order.amount) }}</p>
                                    <p class="text-xs text-gray-500">{{ order.changed_at || "—" }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { router, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    summary: { type: Object, required: true },
    phone: { type: String, default: "" },
    person: { type: Object, default: null },
    phoneError: { type: String, default: null },
});

const form = useForm({ phone: props.phone || "" });

const headline = computed(() => [
    { label: "Phones", value: props.summary.phones, hint: "Numbers stored on the platform" },
    { label: "Courier snapshots", value: props.summary.snapshots, hint: "One row per phone and courier" },
    { label: "Plugin orders", value: props.summary.plugin_orders, hint: "Orders sent in by the plugin" },
    { label: "Couriers", value: props.summary.couriers.length, hint: "Couriers with saved history" },
]);

const emphasisOrder = ["delivered", "canceled", "partially_delivered", "returned"];

function emphasisRows(rows) {
    return emphasisOrder
        .map((key) => (rows || []).find((row) => row.key === key))
        .filter(Boolean);
}

const emphasisStatuses = computed(() => emphasisRows(props.summary.order_status));
const otherStatuses = computed(() => (props.summary.order_status || []).filter((row) => !row.emphasis));
const personEmphasis = computed(() => emphasisRows(props.person?.orders?.by_status));
const personOther = computed(() => (props.person?.orders?.by_status || []).filter((row) => !row.emphasis));

const courierHeadline = computed(() => [
    { label: "Courier orders", value: props.summary.courier_totals?.orders ?? 0 },
    { label: "Delivered", value: props.summary.courier_totals?.delivered ?? 0 },
    { label: "Cancelled", value: props.summary.courier_totals?.cancelled ?? 0 },
    { label: "Partial", value: props.summary.courier_totals?.partial ?? 0 },
]);

const sourceMax = computed(() => Math.max(1, ...(props.summary.sources || []).map((row) => Number(row.count) || 0)));

function sourceWidth(count) {
    const width = Math.max(0, Math.min(100, Math.round((Number(count) / sourceMax.value) * 100)));
    return `${width}%`;
}

function formatCount(value) {
    return Number(value || 0).toLocaleString("en-US");
}

function statusTone(key) {
    if (key === "delivered") return "border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100";
    if (key === "canceled") return "border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100";
    if (key === "partially_delivered") return "border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100";
    if (key === "returned") return "border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100";
    return "border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-100";
}

function search() {
    form.get(route("frauds.dashboard"), {
        preserveScroll: true,
        replace: true,
    });
}

function clearSearch() {
    form.phone = "";
    router.get(route("frauds.dashboard"), {}, {
        preserveScroll: true,
        replace: true,
    });
}
</script>
