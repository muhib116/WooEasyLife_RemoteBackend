<template>
    <MerchantPortalLayout :title="batch.name">
        <div class="space-y-5">
            <section class="overflow-hidden rounded-2xl border bg-white dark:bg-[#161616]" style="border-color: var(--mp-line)">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                    <p class="text-sm font-semibold" style="color: var(--mp-ink)">
                        {{ doneCount }} of {{ items.length }} checked
                        <span v-if="quotaState" class="ml-2 font-medium" style="color: var(--mp-muted)">
                            · Daily limit {{ quotaState.daily_limit }} · Used {{ quotaState.used_today }} · Left {{ quotaState.remaining }}
                        </span>
                    </p>
                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ percent }}%</p>
                </div>
                <div class="h-2 bg-emerald-100 dark:bg-emerald-950/60" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                    <div
                        class="bulk-progress-fill h-full bg-emerald-500 transition-[width] duration-500"
                        :class="{ 'is-running': running }"
                        :style="{ width: (running && percent === 0 ? 8 : percent) + '%' }"
                    />
                </div>
            </section>

            <PageHeader
                :title="batch.name"
                description="Safe is 80% or higher. Review is 50–79%. High risk is under 50%. Partial parcels are counted on their own, not as received or cancelled."
                icon="PhPackage"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium"
                        style="border-color: var(--mp-line); color: var(--mp-ink)"
                        @click="openPreview"
                    >
                        Preview CSV
                    </button>
                    <a
                        :href="route('portal.fraud-check.batches.export', batch.id)"
                        class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium"
                        style="border-color: var(--mp-line); color: var(--mp-ink)"
                    >
                        Export CSV
                    </a>
                    <Button type="button" label="Print" icon="pi pi-print" severity="secondary" outlined size="small" @click="printResults" />
                </template>
            </PageHeader>

            <PageCard title="Results" :description="progressLabel">
                <template #actions>
                    <Button
                        v-if="running"
                        type="button"
                        label="Stop"
                        icon="pi pi-stop"
                        severity="secondary"
                        outlined
                        size="small"
                        @click="stop"
                    />
                    <Button
                        v-else-if="batchState.pending > 0 && !limitBlocked"
                        type="button"
                        label="Continue"
                        icon="pi pi-play"
                        size="small"
                        @click="continueBatch"
                    />
                </template>

                <div class="mb-4 flex flex-wrap gap-2 text-xs font-medium">
                    <span class="rounded-full bg-[#ffc107]/20 px-3 py-1 text-[#5c4500] dark:text-[#ffd54f]">Total {{ items.length }}</span>
                    <span v-if="batchState.duplicates_removed" class="rounded-full bg-slate-100 px-3 py-1 text-slate-600 dark:bg-white/10 dark:text-slate-300">Duplicates removed {{ batchState.duplicates_removed }}</span>
                    <span class="rounded-full bg-violet-100 px-3 py-1 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200">Found {{ countStatus('found') }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600 dark:bg-white/10 dark:text-slate-300">Not found {{ countStatus('not_found') }}</span>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Safe {{ countRisk('safe') }}</span>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Review {{ countRisk('review') }}</span>
                    <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200">High risk {{ countRisk('high') }}</span>
                    <span class="rounded-full bg-sky-100 px-3 py-1 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">From today {{ countSource('cache') }}</span>
                </div>

                <p v-if="limitNotice" class="mb-4 rounded-xl border border-rose-600/30 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    {{ limitNotice }}
                </p>
                <p v-if="refreshNotice" class="mb-4 rounded-xl border border-rose-600/30 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    {{ refreshNotice }}
                </p>
                <p v-if="previewError" class="mb-4 rounded-xl border border-rose-600/30 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    {{ previewError }}
                </p>

                <div class="relative mb-4 flex items-center gap-2">
                    <span class="text-sm" style="color: var(--mp-muted)">Sort by</span>
                    <div ref="sortRoot" class="relative">
                        <button
                            type="button"
                            class="inline-flex max-w-[16rem] items-center gap-2 rounded-xl border bg-white px-3 py-1.5 text-sm shadow-sm dark:bg-[#161616]"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                            aria-haspopup="listbox"
                            :aria-expanded="sortOpen"
                            @click="sortOpen = !sortOpen"
                        >
                            <span class="truncate">{{ sortLabel }}</span>
                            <i class="pi pi-chevron-down text-xs" style="color: var(--mp-muted)" />
                        </button>
                        <div
                            v-if="sortOpen"
                            class="absolute left-0 top-full z-20 mt-1 w-64 overflow-hidden rounded-xl border bg-white py-1 shadow-lg dark:bg-[#161616]"
                            style="border-color: var(--mp-line)"
                            role="listbox"
                        >
                            <button
                                v-for="option in sortOptions"
                                :key="option.value"
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm"
                                :class="sortBy === option.value
                                    ? 'bg-violet-50 text-violet-900 dark:bg-violet-500/15 dark:text-violet-100'
                                    : 'text-[var(--mp-ink)]'"
                                role="option"
                                :aria-selected="sortBy === option.value"
                                @click="chooseSort(option.value)"
                            >
                                <i class="pi pi-check text-xs" :class="sortBy === option.value ? 'visible' : 'invisible'" />
                                <span>{{ option.label }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="checkingRow"
                    class="mb-4 rounded-2xl border p-4"
                    style="border-color: var(--mp-line); background: var(--mp-surface)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Checking now</p>
                            <p class="mt-1 font-mono text-lg font-bold" style="color: var(--mp-ink)">{{ checkingRow.phone }}</p>
                        </div>
                        <p class="text-xs" style="color: var(--mp-muted)">Couriers are answering one by one</p>
                    </div>
                </div>

                <div class="max-w-full overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs font-medium" style="border-color: var(--mp-line); color: var(--mp-muted)">
                                <th class="whitespace-nowrap px-3 py-2.5 font-medium">Phone Number</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Status</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Total Orders</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Successful</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Cancelled</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Success Rate</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in sortedItems" :key="row.id" class="border-b" style="border-color: var(--mp-line)">
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <span class="font-mono" style="color: var(--mp-ink)">{{ row.phone }}</span>
                                    <p v-if="checkingId === row.id" class="text-[11px] text-emerald-700 dark:text-emerald-300">Checking now</p>
                                    <p v-else-if="row.source === 'cache'" class="text-[11px] text-sky-700 dark:text-sky-300">Already checked today</p>
                                    <p v-else-if="row.status === 'error'" class="text-[11px] text-rose-600">Tried {{ row.attempts }} times</p>
                                    <p v-else-if="row.status === 'pending'" class="text-[11px] text-gray-400">Waiting</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center">
                                    <span v-if="checkingId === row.id" class="skeleton-block inline-block h-5 w-16 rounded-full" />
                                    <span v-else class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(row.status)">
                                        {{ statusLabel(row.status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center tabular-nums" style="color: var(--mp-ink)">{{ row.total_orders }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center tabular-nums text-violet-600 dark:text-violet-300">{{ row.received }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center tabular-nums text-rose-600 dark:text-rose-300">{{ row.cancelled }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center font-medium tabular-nums" :class="rateClass(row)">{{ tableRate(row) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <div class="flex flex-nowrap items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#e6a800]/50 bg-[#ffc107]/15 px-2.5 py-1 text-xs font-semibold text-[#5c4500] dark:border-[#ffc107]/40 dark:bg-[#ffc107]/10 dark:text-[#ffd54f]"
                                            @click="openDetails(row)"
                                        >
                                            Details
                                        </button>
                                        <button
                                            v-if="canRefresh(row)"
                                            type="button"
                                            class="rounded-lg border border-sky-600/40 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 disabled:opacity-50 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200"
                                            :disabled="running || refreshingId !== null || retryingId !== null"
                                            @click="refreshRow(row)"
                                        >
                                            {{ refreshingId === row.id ? "Refreshing" : "Refresh" }}
                                        </button>
                                        <button
                                            v-if="row.status === 'error'"
                                            type="button"
                                            class="rounded-lg border border-emerald-600/40 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 disabled:opacity-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                                            :disabled="running || limitBlocked || retryingId !== null || refreshingId !== null"
                                            @click="retryRow(row)"
                                        >
                                            {{ retryingId === row.id ? "Retrying" : "Retry" }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </PageCard>
        </div>

        <div
            v-if="preview"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[#1a1408]/45 p-3 backdrop-blur-sm sm:items-center sm:p-6 dark:bg-black/70"
            @click.self="preview = null"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-[#111111]"
                style="border-color: var(--mp-line)"
                role="dialog"
                aria-modal="true"
                aria-labelledby="csv-preview-title"
            >
                <div class="flex items-center justify-between gap-3 border-b px-4 py-3" style="border-color: var(--mp-line)">
                    <div>
                        <p id="csv-preview-title" class="text-sm font-semibold" style="color: var(--mp-ink)">CSV preview</p>
                        <p class="text-xs" style="color: var(--mp-muted)">{{ preview.rows.length }} rows · success rate, high to low</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            :href="route('portal.fraud-check.batches.export', batch.id)"
                            class="inline-flex items-center rounded-lg bg-[#ffc107] px-3 py-2 text-sm font-semibold text-[#0a0a0a]"
                        >
                            Download CSV
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg"
                            style="color: var(--mp-muted)"
                            aria-label="Close"
                            @click="preview = null"
                        >
                            <i class="pi pi-times" />
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full min-w-max border-collapse text-left text-xs">
                        <thead class="sticky top-0 bg-white dark:bg-[#161616]">
                            <tr>
                                <th
                                    v-for="header in preview.headers"
                                    :key="header"
                                    class="whitespace-nowrap border-b px-3 py-2 font-semibold"
                                    style="border-color: var(--mp-line); color: var(--mp-muted)"
                                >
                                    {{ header }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in preview.rows" :key="index">
                                <td
                                    v-for="(cell, cellIndex) in row"
                                    :key="cellIndex"
                                    class="max-w-xs truncate whitespace-nowrap border-b px-3 py-2"
                                    style="border-color: var(--mp-line); color: var(--mp-ink)"
                                    :title="String(cell ?? '')"
                                >
                                    {{ cell === '' || cell === null ? '—' : cell }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            v-if="selected"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[#1a1408]/45 p-3 backdrop-blur-sm sm:items-center sm:p-6 dark:bg-black/70"
            @click.self="selected = null"
        >
            <div
                class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-2xl border border-[#e6c56a] bg-[#fffdf8] p-4 text-[#1a1408] shadow-2xl shadow-[#c49000]/20 sm:p-5 dark:border-white/10 dark:bg-[#111111] dark:text-white dark:shadow-black/50"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bulk-detail-phone"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#8d6a00] dark:text-slate-400">Checked number</p>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="canRefresh(selected)"
                            type="button"
                            class="rounded-lg border border-sky-600/40 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 disabled:opacity-50 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200"
                            :disabled="running || refreshingId !== null || retryingId !== null"
                            @click="refreshRow(selected)"
                        >
                            {{ refreshingId === selected.id ? "Refreshing" : "Refresh" }}
                        </button>
                        <button
                            v-if="selected.status === 'error'"
                            type="button"
                            class="rounded-lg border border-emerald-600/40 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 disabled:opacity-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                            :disabled="running || limitBlocked || retryingId !== null || refreshingId !== null"
                            @click="retryRow(selected)"
                        >
                            {{ retryingId === selected.id ? "Retrying" : "Retry" }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#6b5e48] transition hover:bg-[#ffc107]/20 hover:text-[#1a1408] dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                            aria-label="Close"
                            @click="selected = null"
                        >
                            <i class="pi pi-times" />
                        </button>
                    </div>
                </div>
                <p v-if="refreshNotice" class="mb-3 rounded-xl border border-rose-600/30 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    {{ refreshNotice }}
                </p>
                <p v-if="limitNotice" class="mb-3 rounded-xl border border-rose-600/30 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                    {{ limitNotice }}
                </p>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e6c56a]/80 bg-[#fff8e8] px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <p id="bulk-detail-phone" class="font-mono text-xl font-bold tracking-wide">{{ selected.phone }}</p>
                    <span class="rounded-full border px-3 py-1 text-xs font-bold" :class="detailBadgeClass(selected)">
                        {{ detailBadge(selected) }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div class="rounded-xl border border-[#e6c56a]/70 bg-white px-2 py-3 text-center dark:border-white/10 dark:bg-white/5">
                        <p class="text-[11px] text-[#6b5e48] dark:text-slate-400">Total</p>
                        <p class="mt-1 text-xl font-bold">{{ selected.total_orders }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-600/25 bg-emerald-50 px-2 py-3 text-center dark:border-emerald-500/25 dark:bg-emerald-500/10">
                        <p class="text-[11px] text-emerald-800 dark:text-emerald-300">Delivered</p>
                        <p class="mt-1 text-xl font-bold text-emerald-800 dark:text-emerald-300">{{ selected.received }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-600/25 bg-rose-50 px-2 py-3 text-center dark:border-rose-500/25 dark:bg-rose-500/10">
                        <p class="text-[11px] text-rose-800 dark:text-rose-300">Returned</p>
                        <p class="mt-1 text-xl font-bold text-rose-800 dark:text-rose-300">{{ selected.cancelled }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-[#e6c56a]/70 bg-white px-4 py-4 text-center dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs text-[#6b5e48] dark:text-slate-400">Success rate</p>
                    <p
                        class="mt-1 text-4xl font-extrabold tracking-tight"
                        :class="selected.success_rate === null || selected.success_rate === undefined ? 'text-[#a89880] dark:text-slate-300' : 'text-[#c49000] dark:text-amber-300'"
                    >
                        {{ rateLabel(selected) }}
                    </p>
                    <p v-if="selected.partial" class="mt-1 text-xs text-[#8d6a00] dark:text-amber-200/80">{{ selected.partial }} partial, counted separately</p>
                </div>

                <div v-if="!selected.couriers?.length" class="mt-4 rounded-xl border border-[#e6c56a]/60 bg-[#fff8e8] px-4 py-3 text-sm text-[#6b5e48] dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                    No courier history for this number.
                </div>
                <div v-else class="mt-4 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#8d6a00] dark:text-slate-400">Courier breakdown</p>
                    <div
                        v-for="courier in selected.couriers"
                        :key="courier.title"
                        class="flex items-center justify-between gap-3 rounded-xl border border-[#e6c56a]/70 bg-white px-3 py-3 dark:border-white/10 dark:bg-[#0a0a0a]"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <div class="flex h-10 w-[4.75rem] shrink-0 items-center justify-center rounded-lg border border-[#e6c56a]/50 bg-white px-1.5 dark:border-transparent">
                                <img
                                    v-if="courierLogo(courier.title)"
                                    :src="courierLogo(courier.title)"
                                    :alt="courier.title"
                                    class="h-7 w-auto max-w-full object-contain"
                                    @error="hideBrokenLogo"
                                >
                                <span v-else class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ courier.title }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ courier.title }}</p>
                                <p class="text-xs leading-snug text-[#6b5e48] dark:text-slate-400">{{ courierLine(courier) }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 text-sm font-bold" :class="courierRateClass(courier)">
                            {{ courier.success_rate === null || courier.success_rate === undefined ? "—" : courier.success_rate + "%" }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#8d6a00] dark:text-slate-400">Fraud notes</p>
                        <span
                            v-if="detailNotes(selected).length"
                            class="rounded-full border border-rose-300 bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                        >
                            {{ detailNotes(selected).length }}
                        </span>
                    </div>
                    <article
                        v-for="(note, index) in detailNotes(selected)"
                        :key="`${note.courier}-${index}`"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/30 dark:bg-rose-950/40"
                    >
                        <p class="text-xs font-semibold text-rose-800 dark:text-rose-200">{{ note.courier }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-rose-950 dark:text-rose-50">{{ note.text }}</p>
                    </article>
                    <p
                        v-if="!detailNotes(selected).length"
                        class="rounded-xl border border-[#e6c56a]/60 bg-[#fff8e8] px-4 py-3 text-sm text-[#6b5e48] dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                    >
                        {{ notesEmptyLabel(selected) }}
                    </p>
                </div>
            </div>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import axios from "axios";
import Button from "primevue/button";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
    batch: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    quota: { type: Object, default: null },
});

const batchState = ref({ ...props.batch });
const items = ref(props.items.map((row) => ({ ...row })));
const quotaState = ref(props.quota);
const running = ref(false);
const selected = ref(null);
const checkingId = ref(null);
const retryingId = ref(null);
const refreshingId = ref(null);
const refreshNotice = ref("");
const limitNotice = ref("");
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref("");
let stopRequested = false;

const doneCount = computed(() => items.value.filter((row) => row.status !== "pending").length);
const percent = computed(() => {
    if (!items.value.length) return 0;
    return Math.round((doneCount.value / items.value.length) * 100);
});
const checkingRow = computed(() => items.value.find((row) => row.id === checkingId.value) ?? null);

const limitBlocked = computed(() => Number(quotaState.value?.remaining ?? 0) < 1);

const progressLabel = computed(() => {
    const done = items.value.filter((row) => row.status !== "pending").length;
    if (running.value) return `Checking ${done} of ${items.value.length}`;
    if (limitBlocked.value && items.value.some((row) => row.status === "pending")) {
        return `Daily limit reached · ${done} of ${items.value.length} checked`;
    }
    return `${batchState.value.status} · ${done} of ${items.value.length} checked`;
});

const sortBy = ref("none");
const sortOpen = ref(false);
const sortRoot = ref(null);
const sortOptions = [
    { value: "none", label: "No sorting" },
    { value: "high", label: "Success Rate: High to Low" },
    { value: "low", label: "Success Rate: Low to High" },
];
const sortLabel = computed(() => sortOptions.find((option) => option.value === sortBy.value)?.label ?? "No sorting");

function rateSortValue(row) {
    if (row.success_rate === null || row.success_rate === undefined || row.success_rate === "") return null;
    const value = Number(row.success_rate);
    return Number.isFinite(value) ? value : null;
}

const sortedItems = computed(() => {
    if (sortBy.value === "none") return items.value;
    const direction = sortBy.value === "high" ? -1 : 1;
    return items.value.slice().sort((left, right) => {
        const leftRate = rateSortValue(left);
        const rightRate = rateSortValue(right);
        if (leftRate === null && rightRate === null) return 0;
        if (leftRate === null) return 1;
        if (rightRate === null) return -1;
        if (leftRate === rightRate) return 0;
        return leftRate < rightRate ? -direction : direction;
    });
});

function chooseSort(value) {
    sortBy.value = value;
    sortOpen.value = false;
}

function onDocumentClick(event) {
    if (!sortOpen.value || !sortRoot.value) return;
    if (!sortRoot.value.contains(event.target)) sortOpen.value = false;
}

function onEscape(event) {
    if (event.key !== "Escape") return;
    if (sortOpen.value) {
        sortOpen.value = false;
        return;
    }
    if (preview.value) {
        preview.value = null;
        return;
    }
    selected.value = null;
}

async function openPreview() {
    if (previewLoading.value) return;
    previewLoading.value = true;
    previewError.value = "";
    try {
        const { data } = await axios.get(route("portal.fraud-check.batches.preview", batchState.value.id));
        preview.value = data;
    } catch {
        previewError.value = "Could not load the CSV preview.";
    } finally {
        previewLoading.value = false;
    }
}

onMounted(() => {
    window.addEventListener("keydown", onEscape);
    document.addEventListener("click", onDocumentClick);
    const hasPending = items.value.some((row) => row.status === "pending");
    if (limitBlocked.value && hasPending) {
        limitNotice.value = "Today's courier history limit is used up. Pending numbers were not checked.";
        return;
    }
    if (batchState.value.status === "running" && hasPending) {
        running.value = true;
        runPending();
    }
});

onUnmounted(() => {
    window.removeEventListener("keydown", onEscape);
    document.removeEventListener("click", onDocumentClick);
});

function openDetails(row) {
    selected.value = row;
}

function detailBadge(row) {
    if (row.risk) return riskLabel(row.risk);
    return statusLabel(row.status);
}

function detailBadgeClass(row) {
    if (row.risk === "safe") return "border-emerald-600/30 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300";
    if (row.risk === "review" || row.status === "invalid") return "border-amber-600/40 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300";
    if (row.risk === "high" || row.status === "error") return "border-rose-600/30 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300";
    return "border-[#e6c56a] bg-white text-[#6b5e48] dark:border-white/15 dark:bg-white/5 dark:text-slate-300";
}

function hideBrokenLogo(event) {
    event.target.style.display = "none";
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

function courierLine(courier) {
    if (!courier.available && !courier.received && !courier.cancelled && !courier.partial) {
        return "No delivery data";
    }
    const parts = [`${courier.received} delivered`, `${courier.cancelled} returned`];
    if (courier.partial) parts.push(`${courier.partial} partial`);
    return parts.join(" · ");
}

function courierRateClass(courier) {
    if (courier.success_rate === null || courier.success_rate === undefined) return "text-[#a89880] dark:text-slate-400";
    if (courier.success_rate >= 80) return "text-emerald-700 dark:text-emerald-400";
    if (courier.success_rate >= 50) return "text-[#c49000] dark:text-amber-300";
    return "text-rose-700 dark:text-rose-400";
}

function detailNotes(row) {
    const notes = [];
    for (const courier of row.couriers || []) {
        for (const text of courier.notes || []) {
            if (!isCustomerFraudNote(text)) continue;
            notes.push({ courier: courier.title, text });
        }
    }
    return notes;
}

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

function notesEmptyLabel(row) {
    const stored = (row.couriers || []).some((courier) => Array.isArray(courier.notes));
    return stored ? "No fraud notes for this number." : "Notes were not stored for this check.";
}

async function continueBatch() {
    if (running.value || limitBlocked.value) {
        if (limitBlocked.value) {
            limitNotice.value = "Today's courier history limit is used up. Pending numbers were not checked.";
        }
        return;
    }
    stopRequested = false;
    running.value = true;
    try {
        if (batchState.value.status === "stopped") {
            const { data } = await axios.post(route("portal.fraud-check.batches.resume", batchState.value.id));
            applyPayload(data);
        }
        await runPending();
    } catch {
        running.value = false;
    }
}

async function runPending() {
    let pauses = 0;
    while (!stopRequested && items.value.some((row) => row.status === "pending")) {
        checkingId.value = items.value.find((row) => row.status === "pending")?.id ?? null;
        try {
            const { data } = await axios.post(route("portal.fraud-check.batches.next", batchState.value.id), {}, { timeout: 180000 });
            if (data.batch) batchState.value = data.batch;
            if (data.quota) quotaState.value = data.quota;
            if (data.item) replaceItem(data.item);
            if (data.limit_reached) {
                limitNotice.value = "Today's courier history limit is used up. Pending numbers were not checked.";
                break;
            }
            if (data.batch?.status === "stopped") break;
            if (data.retry || (!data.item && !data.done)) {
                pauses += 1;
                if (pauses >= 100) {
                    refreshNotice.value = "Checking paused. Press Continue to try the remaining numbers.";
                    break;
                }
                await wait(1000);
                continue;
            }
            pauses = 0;
            if (data.done) break;
        } catch {
            pauses += 1;
            if (pauses >= 100) {
                refreshNotice.value = "Checking paused. Press Continue to try the remaining numbers.";
                break;
            }
            await wait(1000);
        }
    }
    checkingId.value = null;
    running.value = false;
}

function canRefresh(row) {
    return ["found", "not_found", "error"].includes(row.status);
}

async function refreshRow(row) {
    if (running.value || refreshingId.value || retryingId.value || !canRefresh(row)) return;
    refreshingId.value = row.id;
    checkingId.value = row.id;
    refreshNotice.value = "";
    try {
        const { data } = await axios.post(
            route("portal.fraud-check.batches.refresh", { batch: batchState.value.id, item: row.id }),
            {},
            { timeout: 180000 },
        );
        if (data.batch) batchState.value = data.batch;
        if (data.quota) quotaState.value = data.quota;
        if (data.item) replaceItem(data.item);
        if (data.limit_reached) {
            refreshNotice.value = "Today's courier history limit is used up, so this number was not checked again.";
        } else if (data.refreshed === false) {
            refreshNotice.value = "Could not fetch new courier data for this number. The previous result is still shown.";
        }
    } catch {
        refreshNotice.value = "Could not fetch new courier data for this number. The previous result is still shown.";
    } finally {
        checkingId.value = null;
        refreshingId.value = null;
    }
}

async function retryRow(row) {
    if (running.value || retryingId.value || limitBlocked.value) {
        if (limitBlocked.value) {
            limitNotice.value = "Today's courier history limit is used up, so this number was not checked again.";
        }
        return;
    }
    retryingId.value = row.id;
    checkingId.value = row.id;
    try {
        const { data } = await axios.post(
            route("portal.fraud-check.batches.retry", { batch: batchState.value.id, item: row.id }),
            {},
            { timeout: 180000 },
        );
        if (data.batch) batchState.value = data.batch;
        if (data.quota) quotaState.value = data.quota;
        if (data.item) replaceItem(data.item);
        if (data.limit_reached) {
            limitNotice.value = "Today's courier history limit is used up, so this number was not checked again.";
        }
    } catch {
        // The row stays failed so it can be retried again.
    } finally {
        checkingId.value = null;
        retryingId.value = null;
    }
}

async function stop() {
    stopRequested = true;
    const { data } = await axios.post(route("portal.fraud-check.batches.stop", batchState.value.id));
    applyPayload(data);
    running.value = false;
}

function applyPayload(payload) {
    if (payload.batch) batchState.value = payload.batch;
    if (payload.items) items.value = payload.items;
}

function replaceItem(item) {
    const index = items.value.findIndex((row) => row.id === item.id);
    if (index === -1) {
        items.value.push(item);
        return;
    }
    items.value.splice(index, 1, item);
    if (selected.value?.id === item.id) selected.value = item;
}

function countStatus(status) {
    return items.value.filter((row) => row.status === status).length;
}

function countRisk(risk) {
    return items.value.filter((row) => row.risk === risk).length;
}

function countSource(source) {
    return items.value.filter((row) => row.source === source).length;
}

function statusLabel(status) {
    if (status === "found") return "Found";
    if (status === "not_found") return "Not found";
    if (status === "invalid") return "Invalid";
    if (status === "pending") return "Waiting";
    if (status === "error") return "Failed";
    return status;
}

function statusClass(status) {
    if (status === "found") return "bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200";
    if (status === "not_found") return "bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300";
    if (status === "invalid") return "bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200";
    if (status === "error") return "bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200";
    return "bg-gray-100 text-gray-500";
}

function riskLabel(risk) {
    if (risk === "safe") return "Safe";
    if (risk === "review") return "Review";
    if (risk === "high") return "High risk";
    return "";
}

function rateLabel(row) {
    return row.success_rate === null || row.success_rate === undefined ? "—" : `${row.success_rate}%`;
}

function tableRate(row) {
    if (row.success_rate === null || row.success_rate === undefined || row.success_rate === "") return "-";
    const value = Number(row.success_rate);
    return Number.isFinite(value) ? `${value.toFixed(1)}%` : "-";
}

function rateClass(row) {
    if (row.risk === "safe") return "text-emerald-700 dark:text-emerald-300";
    if (row.risk === "review") return "text-amber-700 dark:text-amber-300";
    if (row.risk === "high") return "text-rose-700 dark:text-rose-300";
    return "text-gray-500";
}

function printResults() {
    selected.value = null;
    window.print();
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
</script>

<style scoped>
.bulk-progress-fill {
    position: relative;
    min-width: 0;
    overflow: hidden;
}

.bulk-progress-fill.is-running::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.55), transparent);
    animation: bulk-progress-sheen 1.2s ease-in-out infinite;
}

@keyframes bulk-progress-sheen {
    from { transform: translateX(-100%); }
    to { transform: translateX(100%); }
}
</style>
