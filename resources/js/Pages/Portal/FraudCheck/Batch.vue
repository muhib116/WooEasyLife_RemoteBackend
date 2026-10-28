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
                    <div class="flex items-center gap-3">
                        <div v-if="showElapsed" class="text-right">
                            <p class="font-mono text-sm font-semibold tabular-nums" style="color: var(--mp-ink)">
                                <span class="sr-only">{{ batchState.status === "stopped" ? "Paused at" : "Checking time" }}</span>
                                {{ elapsedLabel }}
                            </p>
                            <p v-if="batchEtaLabel" class="text-xs" style="color: var(--mp-muted)">{{ batchEtaLabel }}</p>
                        </div>
                        <button
                            v-if="showPauseControl"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                            :disabled="pausing"
                            @click="pauseChecking"
                        >
                            {{ resumeInstead ? "Resume" : "Pause" }}
                        </button>
                        <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ percent }}%</p>
                    </div>
                </div>
                <div class="h-2 bg-emerald-100 dark:bg-emerald-950/60" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                    <div
                        class="bulk-progress-fill h-full bg-emerald-500 transition-[width] duration-500"
                        :class="{ 'is-running': running }"
                        :style="{ width: (running && percent === 0 ? 8 : percent) + '%' }"
                    />
                </div>
                <p v-if="finishedSummary" class="px-4 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                    {{ finishedSummary }}
                </p>
                <p v-else-if="checkActive" class="px-4 py-2 text-xs" style="color: var(--mp-muted)">
                    You can leave this page. Checking keeps going.
                </p>
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
                    <Button
                        type="button"
                        label="Check again as new"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="replaying"
                        :disabled="running || replaying"
                        @click="replayAsNew"
                    />
                    <Button type="button" label="Print" icon="pi pi-print" severity="secondary" outlined size="small" @click="printResults" />
                </template>
            </PageHeader>

            <PageCard title="Results" :description="progressLabel">
                <template #actions>
                    <label class="results-search">
                        <span class="sr-only">Search phone number</span>
                        <i class="pi pi-search results-search-icon" />
                        <input
                            v-model="phoneQuery"
                            type="text"
                            inputmode="tel"
                            placeholder="Search number"
                        />
                        <button
                            v-if="phoneQuery"
                            type="button"
                            class="results-search-clear"
                            aria-label="Clear search"
                            @click="phoneQuery = ''"
                        >
                            <i class="pi pi-times" />
                        </button>
                    </label>
                </template>

                <div class="mb-4 flex flex-wrap gap-2 text-xs font-medium">
                    <button
                        type="button"
                        class="rounded-full bg-[#ffc107]/20 px-3 py-1 text-[#5c4500] ring-offset-2 ring-offset-white transition dark:text-[#ffd54f] dark:ring-offset-[#161616]"
                        :class="rowFilter === null ? 'ring-2 ring-[#e6a800]' : 'hover:bg-[#ffc107]/35'"
                        :aria-pressed="rowFilter === null"
                        @click="chooseFilter(null)"
                    >
                        Total {{ items.length }}
                    </button>
                    <span v-if="batchState.duplicates_removed" class="rounded-full bg-slate-100 px-3 py-1 text-slate-600 dark:bg-white/10 dark:text-slate-300">Duplicates removed {{ batchState.duplicates_removed }}</span>
                    <button
                        v-for="chip in filterChips"
                        :key="chip.value"
                        type="button"
                        class="rounded-full px-3 py-1 ring-offset-2 ring-offset-white transition dark:ring-offset-[#161616]"
                        :class="[chip.className, rowFilter === chip.value ? 'ring-2' : 'hover:brightness-95']"
                        :aria-pressed="rowFilter === chip.value"
                        @click="chooseFilter(chip.value)"
                    >
                        {{ chip.label }} {{ chip.count }}
                    </button>
                </div>
                <p v-if="rowFilter || phoneQueryActive" class="mb-4 text-xs" style="color: var(--mp-muted)">
                    Showing {{ sortedItems.length }} of {{ items.length }}
                </p>
                <div
                    v-if="rowFilter === 'incomplete' || refreshingIncomplete"
                    class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-600/30 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    <p>
                        <template v-if="refreshingIncomplete">
                            Refreshing incomplete {{ incompleteRefresh.done }} of {{ incompleteRefresh.total }}.
                        </template>
                        <template v-else-if="incompleteTargets.length">
                            {{ incompleteTargets.length }} incomplete {{ incompleteTargets.length === 1 ? "number" : "numbers" }} can be checked again. A courier did not answer, so these are not Safe.
                        </template>
                        <template v-else>
                            No incomplete numbers can be refreshed.
                        </template>
                    </p>
                    <button
                        v-if="refreshingIncomplete"
                        type="button"
                        class="rounded-lg border border-amber-700/30 bg-white px-3 py-1.5 text-xs font-semibold text-amber-950 dark:border-amber-400/30 dark:bg-transparent dark:text-amber-100"
                        @click="stopIncompleteRefresh"
                    >
                        Stop
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg border border-sky-600/40 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-800 disabled:opacity-50 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200"
                        :disabled="!incompleteTargets.length || running || refreshingId !== null || retryingId !== null"
                        @click="refreshIncompleteRows"
                    >
                        Refresh incomplete
                    </button>
                </div>

                <div
                    v-if="waitingCount > 0"
                    class="mb-4 rounded-xl border border-sky-600/30 bg-sky-50 px-3 py-2 text-sm text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
                >
                    <p class="font-semibold">
                        {{ waitingCount }} {{ waitingCount === 1 ? "number is" : "numbers are" }} waiting for a courier
                    </p>
                    <p class="mt-0.5 text-xs opacity-80">
                        The other couriers already answered. A waiting courier is asked again when it has room, up to {{ incompleteRetryMax }} times.
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button
                            v-if="tryingWaiting"
                            type="button"
                            class="rounded-lg border border-sky-700/30 bg-white px-3 py-1.5 text-xs font-semibold text-sky-950 dark:border-sky-400/30 dark:bg-transparent dark:text-sky-100"
                            @click="stopWaitingTry"
                        >
                            Stop
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-lg border border-sky-600/40 bg-white px-3 py-1.5 text-xs font-semibold text-sky-800 disabled:opacity-50 dark:border-sky-500/30 dark:bg-transparent dark:text-sky-100"
                            :disabled="!waitingTargets.length || retryingId !== null"
                            @click="tryWaitingRows"
                        >
                            Try {{ Math.min(3, waitingTargets.length) }} now
                        </button>
                        <span v-if="tryingWaiting" class="text-xs opacity-80">
                            Trying {{ waitingTry.done }} of {{ waitingTry.total }}.
                        </span>
                    </div>
                    <ul v-if="courierStatus.length" class="mt-2 flex flex-wrap gap-2">
                        <li
                            v-for="status in courierStatus"
                            :key="status.courier"
                            class="rounded-full border border-sky-600/30 bg-white px-2.5 py-0.5 text-xs font-medium dark:border-sky-500/30 dark:bg-transparent"
                        >
                            {{ courierStatusText(status) }}
                        </li>
                    </ul>
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

                <div class="relative mb-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm" style="color: var(--mp-muted)">Sort by</span>
                    <div ref="sortRoot" class="relative">
                        <button
                            type="button"
                            class="inline-flex max-w-[16rem] items-center gap-2 rounded-xl border bg-white px-3 py-1.5 text-sm shadow-sm dark:bg-[#161616]"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                            aria-haspopup="listbox"
                            :aria-expanded="sortOpen"
                            @click="sortOpen = !sortOpen; pageSizeOpen = false"
                        >
                            <span class="truncate">{{ sortLabel }}</span>
                            <i class="pi pi-chevron-down results-caret" style="color: var(--mp-muted)" />
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
                    <div ref="pageSizeRoot" class="relative ml-auto flex items-center gap-2">
                        <span class="text-sm" style="color: var(--mp-muted)">Per page</span>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border bg-white px-3 py-1.5 text-sm shadow-sm dark:bg-[#161616]"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                            aria-haspopup="listbox"
                            :aria-expanded="pageSizeOpen"
                            @click="pageSizeOpen = !pageSizeOpen; sortOpen = false"
                        >
                            <span>{{ pageSize }} per page</span>
                            <i class="pi pi-chevron-down results-caret" style="color: var(--mp-muted)" />
                        </button>
                        <div
                            v-if="pageSizeOpen"
                            class="absolute right-0 top-full z-20 mt-1 w-44 overflow-hidden rounded-xl border bg-white py-1 shadow-lg dark:bg-[#161616]"
                            style="border-color: var(--mp-line)"
                            role="listbox"
                        >
                            <button
                                v-for="size in pageSizeOptions"
                                :key="size"
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm"
                                :class="pageSize === size
                                    ? 'bg-violet-50 text-violet-900 dark:bg-violet-500/15 dark:text-violet-100'
                                    : 'text-[var(--mp-ink)]'"
                                role="option"
                                :aria-selected="pageSize === size"
                                @click="choosePageSize(size)"
                            >
                                <i class="pi pi-check text-xs" :class="pageSize === size ? 'visible' : 'invisible'" />
                                <span>{{ size }} per page</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="checkingRows.length"
                    class="mb-4 rounded-2xl border p-4"
                    style="border-color: var(--mp-line); background: var(--mp-surface)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Checking now</p>
                            <p class="mt-1 flex flex-wrap gap-x-4 font-mono text-lg font-bold" style="color: var(--mp-ink)">
                                <span v-for="row in checkingRows" :key="row.id">{{ row.phone }}</span>
                            </p>
                        </div>
                        <p class="text-xs" style="color: var(--mp-muted)">
                            {{ checkingRows.length > 1 ? `${checkingRows.length} numbers at a time` : "Couriers are answering one by one" }}
                        </p>
                    </div>
                </div>

                <div class="max-w-full overflow-x-auto">
                    <table class="w-full text-left text-sm md:min-w-[40rem]">
                        <thead>
                            <tr class="border-b text-xs font-medium" style="border-color: var(--mp-line); color: var(--mp-muted)">
                                <th class="whitespace-nowrap px-3 py-2.5 font-medium">Phone Number</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Status</th>
                                <th class="hidden whitespace-nowrap px-3 py-2.5 text-center font-medium md:table-cell print:table-cell">Total Orders</th>
                                <th class="hidden whitespace-nowrap px-3 py-2.5 text-center font-medium md:table-cell print:table-cell">Successful</th>
                                <th class="hidden whitespace-nowrap px-3 py-2.5 text-center font-medium md:table-cell print:table-cell">Cancelled</th>
                                <th class="hidden whitespace-nowrap px-3 py-2.5 text-center font-medium md:table-cell print:table-cell">Success Rate</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-center font-medium">Risk</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!sortedItems.length">
                                <td colspan="8" class="px-3 py-8 text-center text-sm" style="color: var(--mp-muted)">
                                    {{ items.length ? "No numbers match." : "No numbers in this batch." }}
                                </td>
                            </tr>
                            <tr v-for="row in pagedItems" :key="row.id" class="border-b" style="border-color: var(--mp-line)">
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <span class="font-mono" style="color: var(--mp-ink)">{{ row.phone }}</span>
                                    <p v-if="isChecking(row)" class="text-[11px] text-emerald-700 dark:text-emerald-300">Checking now</p>
                                    <p v-else-if="row.source === 'cache'" class="text-[11px] text-sky-700 dark:text-sky-300">Already checked today</p>
                                    <p v-else-if="row.status === 'error'" class="text-[11px] text-rose-600">Tried {{ row.attempts }} times</p>
                                    <p v-else-if="row.status === 'pending'" class="text-[11px] text-gray-400">Not checked yet</p>
                                    <p v-else-if="row.risk === 'waiting'" class="text-[11px] text-sky-700 dark:text-sky-300">{{ waitingHint(row) }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center">
                                    <span v-if="isChecking(row)" class="skeleton-block inline-block h-5 w-16 rounded-full" />
                                    <span v-else class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(row.status)">
                                        {{ statusLabel(row.status) }}
                                    </span>
                                </td>
                                <td class="hidden whitespace-nowrap px-3 py-2.5 text-center tabular-nums md:table-cell print:table-cell" style="color: var(--mp-ink)">{{ row.total_orders }}</td>
                                <td class="hidden whitespace-nowrap px-3 py-2.5 text-center tabular-nums text-violet-600 dark:text-violet-300 md:table-cell print:table-cell">{{ row.received }}</td>
                                <td class="hidden whitespace-nowrap px-3 py-2.5 text-center tabular-nums text-rose-600 dark:text-rose-300 md:table-cell print:table-cell">{{ row.cancelled }}</td>
                                <td class="hidden whitespace-nowrap px-3 py-2.5 text-center font-medium tabular-nums md:table-cell print:table-cell" :class="rateClass(row)">{{ tableRate(row) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-center">
                                    <span
                                        v-if="isChecking(row)"
                                        class="skeleton-block inline-block h-5 w-16 rounded-full"
                                    />
                                    <span
                                        v-else-if="riskText(row)"
                                        class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="riskClass(row.risk)"
                                    >
                                        {{ riskText(row) }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <div class="flex flex-wrap items-center justify-end gap-1.5 md:flex-nowrap">
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
                                            :disabled="refreshingIncomplete || refreshingId !== null || retryingId !== null"
                                            @click="refreshRow(row)"
                                        >
                                            {{ isChecking(row) ? "Refreshing" : "Refresh" }}
                                        </button>
                                        <button
                                            v-if="row.status === 'error'"
                                            type="button"
                                            class="rounded-lg border border-emerald-600/40 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 disabled:opacity-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                                            :disabled="running || refreshingIncomplete || limitBlocked || retryingId !== null || refreshingId !== null"
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

                <div
                    v-if="sortedItems.length"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-sm tabular-nums" style="color: var(--mp-muted)">
                        {{ pageFrom }}–{{ pageTo }} of {{ sortedItems.length }}
                    </p>
                    <div class="flex flex-wrap items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex h-8 items-center rounded-lg border px-2.5 text-sm disabled:opacity-40"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                            :disabled="page <= 1"
                            @click="goToPage(page - 1)"
                        >
                            Previous
                        </button>
                        <template v-for="(entry, index) in pageButtons" :key="`${entry}-${index}`">
                            <span v-if="entry === '…'" class="px-1 text-sm" style="color: var(--mp-muted)">…</span>
                            <button
                                v-else
                                type="button"
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-sm tabular-nums"
                                :class="page === entry ? 'border-[#e6a800] bg-[#ffc107] font-semibold text-[#111]' : ''"
                                :style="page === entry ? undefined : 'border-color: var(--mp-line); color: var(--mp-ink)'"
                                :aria-current="page === entry ? 'page' : undefined"
                                @click="goToPage(entry)"
                            >
                                {{ entry }}
                            </button>
                        </template>
                        <button
                            type="button"
                            class="inline-flex h-8 items-center rounded-lg border px-2.5 text-sm disabled:opacity-40"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                            :disabled="page >= pageCount"
                            @click="goToPage(page + 1)"
                        >
                            Next
                        </button>
                    </div>
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
                            :disabled="refreshingIncomplete || refreshingId !== null || retryingId !== null"
                            @click="refreshRow(selected)"
                        >
                            {{ isChecking(selected) ? "Refreshing" : "Refresh" }}
                        </button>
                        <button
                            v-if="selected.status === 'error'"
                            type="button"
                            class="rounded-lg border border-emerald-600/40 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 disabled:opacity-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                            :disabled="running || refreshingIncomplete || limitBlocked || retryingId !== null || refreshingId !== null"
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
                    <p v-if="selected.risk === 'incomplete'" class="rounded-xl border border-amber-600/30 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                        A courier did not answer, so this is not a Safe result. Refresh tries that courier again.
                    </p>
                    <p v-else-if="selected.risk === 'waiting'" class="rounded-xl border border-sky-600/30 bg-sky-50 px-3 py-2 text-sm text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                        {{ riskText(selected) }}. {{ waitingCouriers(selected).length > 1 ? "They are" : "It is" }} asked again when there is room, and this result updates by itself.
                    </p>
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
                                <p class="text-xs leading-snug text-[#6b5e48] dark:text-slate-400">{{ courierLine(courier, selected) }}</p>
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
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const props = defineProps({
    batch: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    quota: { type: Object, default: null },
    workers: { type: Number, default: 1 },
    incompleteRetries: { type: Number, default: 5 },
    incompleteRetryGapSeconds: { type: Number, default: 5 },
    serverTime: { type: String, default: null },
});

const batchState = ref({ ...props.batch });
const items = ref(props.items.map((row) => ({ ...row })));
const quotaState = ref(props.quota);
const running = ref(false);
const selected = ref(null);
const checkingIds = ref([]);
const retryingId = ref(null);
const refreshingId = ref(null);
const refreshingIncomplete = ref(false);
const replaying = ref(false);
const incompleteRefresh = ref({ done: 0, total: 0 });
const tryingWaiting = ref(false);
const waitingTry = ref({ done: 0, total: 0 });
let waitingAbort = null;
const courierStatus = ref([]);
let stopIncompleteRequested = false;
const refreshNotice = ref("");
const limitNotice = ref("");
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref("");
let stopRequested = false;
let runToken = 0;

// Every refresh shares one pace, so parallel lanes stay under the server's
// limit (120 a minute) instead of hitting it and stopping.
const REFRESH_GAP_MS = 600;
const MAX_THROTTLED_TRIES = 5;
const THROTTLE_WAIT_NOTICE = "Couriers were asked too quickly, so refreshing waits a few seconds and then continues.";
let refreshSlotAt = 0;

// Rows that wait for a courier are filled on the server; the page only asks
// what changed.
const POLL_RUNNING_MS = 8000;
const POLL_WAITING_MS = 15000;
const POLL_IDLE_MS = 5000;
let pollTimer = null;
let pollStopped = false;
let pollSince = props.serverTime;

const bulkWorkers = computed(() => Math.min(5, Math.max(1, Number(props.workers) || 1)));
const incompleteRetryMax = computed(() => Math.min(5, Math.max(0, Number(props.incompleteRetries ?? 5))));
const waitingCount = computed(() => countRisk("waiting"));
const pausing = ref(false);
const workersStalled = ref(false);
const clock = ref(Date.now());
const pausedMs = ref(0);
const pauseStartedAt = ref(null);
const finishedAt = ref(null);
const timedThisVisit = ref(false);
let clockTimer = null;
const serverSkew = Number.isFinite(Date.parse(props.serverTime || ""))
    ? Date.parse(props.serverTime) - Date.now()
    : 0;

function serverNow() {
    return Date.now() + serverSkew;
}

const startedAt = computed(() => {
    const parsed = Date.parse(batchState.value.created_at || "");
    return Number.isFinite(parsed) ? parsed : null;
});
const checkActive = computed(() => {
    if (batchState.value.status === "stopped") return false;
    return running.value || batchState.value.status === "running" || waitingCount.value > 0;
});
const showElapsed = computed(() => {
    if (!startedAt.value) return false;
    return checkActive.value || batchState.value.status === "stopped" || finishedAt.value !== null;
});
const elapsedLabel = computed(() => {
    if (!startedAt.value) return "00:00";
    const now = clock.value + serverSkew;
    let end = now;
    if (finishedAt.value !== null) end = finishedAt.value;
    else if (pauseStartedAt.value !== null) end = pauseStartedAt.value;
    return formatElapsed(Math.max(0, end - startedAt.value - pausedMs.value));
});

watch(checkActive, (active) => {
    if (active) {
        timedThisVisit.value = true;
        finishedAt.value = null;
        if (pauseStartedAt.value !== null) {
            pausedMs.value += serverNow() - pauseStartedAt.value;
            pauseStartedAt.value = null;
        }
        return;
    }
    if (batchState.value.status === "stopped") {
        if (pauseStartedAt.value === null) pauseStartedAt.value = serverNow();
        return;
    }
    if (timedThisVisit.value && finishedAt.value === null) finishedAt.value = serverNow();
}, { immediate: true });

function formatElapsed(ms) {
    const total = Math.floor(ms / 1000);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    const pad = (value) => String(value).padStart(2, "0");
    return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`;
}

const doneCount = computed(() => items.value.filter((row) => row.status !== "pending").length);
const percent = computed(() => {
    if (!items.value.length) return 0;
    return Math.round((doneCount.value / items.value.length) * 100);
});
const checkingRows = computed(() => items.value.filter((row) => checkingIds.value.includes(row.id)));

function isChecking(row) {
    return checkingIds.value.includes(row.id);
}

function markChecking(id) {
    if (!checkingIds.value.includes(id)) checkingIds.value = [...checkingIds.value, id];
}

function unmarkChecking(id) {
    checkingIds.value = checkingIds.value.filter((value) => value !== id);
}

const limitBlocked = computed(() => Number(quotaState.value?.remaining ?? 0) < 1);

const progressLabel = computed(() => {
    const done = items.value.filter((row) => row.status !== "pending").length;
    const waiting = waitingCount.value ? ` · ${waitingCount.value} waiting for a courier` : "";
    if (running.value) return `Checking ${done} of ${items.value.length}${waiting}`;
    if (batchState.value.status === "stopped") return `Paused · ${done} of ${items.value.length} checked${waiting}`;
    if (limitBlocked.value && items.value.some((row) => row.status === "pending")) {
        return `Daily limit reached · ${done} of ${items.value.length} checked${waiting}`;
    }
    if (waitingCount.value) return `Waiting for couriers · ${done} of ${items.value.length} checked · ${waitingCount.value} still waiting`;
    if (batchState.value.status === "completed") return `Completed · ${done} of ${items.value.length} checked`;
    return `${batchState.value.status} · ${done} of ${items.value.length} checked${waiting}`;
});

const resumeInstead = computed(() => batchState.value.status === "stopped" || workersStalled.value);

const showPauseControl = computed(() => {
    if (workersStalled.value) return countWaiting() > 0;
    const paused = batchState.value.status === "stopped";
    const workLeft = running.value || batchState.value.status === "running" || waitingCount.value > 0 || batchState.value.pending > 0;
    if (paused) return waitingCount.value > 0 || batchState.value.pending > 0;
    return workLeft;
});

const batchFinished = computed(() => !running.value
    && waitingCount.value === 0
    && countWaiting() === 0
    && batchState.value.status === "completed"
    && items.value.length > 0);

const finishedSummary = computed(() => {
    if (!batchFinished.value) return "";
    const count = items.value.length;
    const noun = count === 1 ? "number" : "numbers";
    if (showElapsed.value && elapsedLabel.value && elapsedLabel.value !== "00:00") {
        return `${count} ${noun} finished in ${elapsedLabel.value}.`;
    }
    return `${count} ${noun} finished.`;
});

// Couriers work through their queues side by side, so the batch ends with the slowest one.
const batchEtaLabel = computed(() => {
    if (batchFinished.value || (!checkActive.value && batchState.value.status !== "stopped")) return "";
    const pending = countWaiting();
    if (pending + waitingCount.value < 1) return "";

    let minutes = 0;
    for (const status of courierStatus.value) {
        const eta = Number(status.eta_minutes) || 0;
        if (eta > minutes) minutes = eta;
    }
    // Numbers not checked yet still need Pathao, the slowest courier.
    const pathao = courierStatus.value.find((status) => status.courier === "pathao");
    const perMinute = Number(pathao?.per_minute) || 0;
    if (pending > 0 && perMinute > 0) {
        const resting = Math.ceil((Number(pathao.resting_seconds) || 0) / 60);
        minutes = Math.max(minutes, Math.ceil(((Number(pathao.waiting) || 0) + pending) / perMinute) + resting);
    }

    if (minutes < 1) return "";
    if (minutes < 60) return `about ${minutes} min left`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest ? `about ${hours}h ${rest}m left` : `about ${hours}h left`;
});

const rowFilter = ref(null);
const phoneQuery = ref("");
const phoneQueryDigits = computed(() => String(phoneQuery.value || "").replace(/\D/g, ""));
const phoneQueryActive = computed(() => String(phoneQuery.value || "").trim() !== "");
const filterChips = computed(() => [
    { value: "found", label: "Found", count: countStatus("found"), className: "bg-violet-100 text-violet-800 ring-violet-400 dark:bg-violet-500/15 dark:text-violet-200" },
    { value: "not_found", label: "Not found", count: countStatus("not_found"), className: "bg-slate-100 text-slate-600 ring-slate-400 dark:bg-white/10 dark:text-slate-300" },
    { value: "error", label: "Failed", count: countStatus("error"), className: "bg-rose-100 text-rose-800 ring-rose-400 dark:bg-rose-500/15 dark:text-rose-200" },
    { value: "invalid", label: "Invalid", count: countStatus("invalid"), className: "bg-amber-100 text-amber-800 ring-amber-400 dark:bg-amber-500/15 dark:text-amber-200" },
    { value: "pending", label: "Not checked", count: countWaiting(), className: "bg-gray-100 text-gray-600 ring-gray-400 dark:bg-white/10 dark:text-gray-300" },
    { value: "safe", label: "Safe", count: countRisk("safe"), className: "bg-emerald-100 text-emerald-800 ring-emerald-500 dark:bg-emerald-500/15 dark:text-emerald-200" },
    { value: "review", label: "Review", count: countRisk("review"), className: "bg-amber-100 text-amber-800 ring-amber-400 dark:bg-amber-500/15 dark:text-amber-200" },
    { value: "waiting", label: "Waiting for courier", count: countRisk("waiting"), className: "bg-sky-100 text-sky-800 ring-sky-400 dark:bg-sky-500/15 dark:text-sky-200" },
    { value: "incomplete", label: "Incomplete", count: countRisk("incomplete"), className: "bg-amber-100 text-amber-900 ring-amber-500 dark:bg-amber-500/20 dark:text-amber-100" },
    { value: "high", label: "High risk", count: countRisk("high"), className: "bg-rose-100 text-rose-800 ring-rose-400 dark:bg-rose-500/15 dark:text-rose-200" },
    { value: "today", label: "From today", count: countSource("cache"), className: "bg-sky-100 text-sky-800 ring-sky-400 dark:bg-sky-500/15 dark:text-sky-200" },
]);

function chooseFilter(value) {
    rowFilter.value = rowFilter.value === value ? null : value;
}

function rowMatchesFilter(row) {
    if (rowFilter.value === "found" || rowFilter.value === "not_found" || rowFilter.value === "error" || rowFilter.value === "invalid") {
        return row.status === rowFilter.value;
    }
    if (rowFilter.value === "pending") return row.status === "pending" || row.status === "checking";
    if (["safe", "review", "waiting", "incomplete", "high"].includes(rowFilter.value)) return row.risk === rowFilter.value;
    if (rowFilter.value === "today") return row.source === "cache";
    return true;
}

function rowMatchesSearch(row) {
    if (!phoneQueryActive.value) return true;
    const query = phoneQueryDigits.value.replace(/^880/, "");
    if (!query) return false;
    const local = String(row.phone || "").replace(/\D/g, "").replace(/^880/, "");
    return local.includes(query);
}

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
    const rows = items.value.filter((row) => rowMatchesFilter(row) && rowMatchesSearch(row));
    if (sortBy.value === "none") return rows;
    const direction = sortBy.value === "high" ? -1 : 1;
    return rows.slice().sort((left, right) => {
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
    page.value = 1;
}

const pageSizeOptions = [100, 200, 300, 400, 500];
const pageSize = ref(100);
const page = ref(1);
const pageSizeOpen = ref(false);
const pageSizeRoot = ref(null);

const pageCount = computed(() => Math.max(1, Math.ceil(sortedItems.value.length / pageSize.value)));

const pagedItems = computed(() => {
    const start = (page.value - 1) * pageSize.value;
    return sortedItems.value.slice(start, start + pageSize.value);
});

const pageFrom = computed(() => (sortedItems.value.length ? (page.value - 1) * pageSize.value + 1 : 0));
const pageTo = computed(() => Math.min(page.value * pageSize.value, sortedItems.value.length));

const pageButtons = computed(() => {
    const last = pageCount.value;
    const current = page.value;
    if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);
    const pages = [1, last, current - 2, current - 1, current, current + 1, current + 2]
        .filter((number, index, list) => number >= 1 && number <= last && list.indexOf(number) === index)
        .sort((left, right) => left - right);
    const buttons = [];
    let previous = 0;
    for (const number of pages) {
        if (previous && number - previous > 1) buttons.push("…");
        buttons.push(number);
        previous = number;
    }
    return buttons;
});

function choosePageSize(size) {
    pageSize.value = size;
    pageSizeOpen.value = false;
    page.value = 1;
}

function goToPage(next) {
    const pageNumber = Number(next);
    if (!Number.isFinite(pageNumber)) return;
    page.value = Math.min(pageCount.value, Math.max(1, pageNumber));
}

watch([rowFilter, () => sortedItems.value.length], () => {
    if (page.value > pageCount.value) page.value = pageCount.value;
});

watch([rowFilter, phoneQuery], () => {
    page.value = 1;
});

function onDocumentClick(event) {
    if (sortOpen.value && sortRoot.value && !sortRoot.value.contains(event.target)) sortOpen.value = false;
    if (pageSizeOpen.value && pageSizeRoot.value && !pageSizeRoot.value.contains(event.target)) pageSizeOpen.value = false;
}

function onEscape(event) {
    if (event.key !== "Escape") return;
    if (sortOpen.value || pageSizeOpen.value) {
        sortOpen.value = false;
        pageSizeOpen.value = false;
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
    clockTimer = setInterval(() => {
        clock.value = Date.now();
    }, 1000);
    window.addEventListener("keydown", onEscape);
    document.addEventListener("click", onDocumentClick);
    schedulePoll(1500);
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
    stopRequested = true;
    stopIncompleteRequested = true;
    pollStopped = true;
    clearInterval(clockTimer);
    clearTimeout(pollTimer);
    window.removeEventListener("keydown", onEscape);
    document.removeEventListener("click", onDocumentClick);
});

function schedulePoll(delay) {
    clearTimeout(pollTimer);
    if (pollStopped) return;
    pollTimer = setTimeout(pollChanges, delay);
}

async function pollChanges() {
    if (pollStopped) return;
    if (!running.value && !waitingCount.value) {
        courierStatus.value = [];
        schedulePoll(POLL_IDLE_MS);
        return;
    }
    try {
        const { data } = await axios.get(route("portal.fraud-check.batches.changes", {
            batch: batchState.value.id,
            ...(pollSince ? { since: pollSince } : {}),
        }));
        if (pollStopped) return;
        if (data.batch) batchState.value = data.batch;
        for (const item of data.items || []) mergeChangedItem(item);
        courierStatus.value = Array.isArray(data.couriers) ? data.couriers : [];
        if (data.server_time) pollSince = data.server_time;
    } catch {
        // The next poll asks again.
    }
    schedulePoll(running.value ? POLL_RUNNING_MS : POLL_WAITING_MS);
}

// A row this page is checking, or a finished row reported as not checked
// by an older answer, keeps what the page already shows.
function mergeChangedItem(item) {
    if (isChecking(item)) return;
    const current = items.value.find((row) => row.id === item.id);
    if (current && current.status !== "pending" && item.status === "pending") return;
    replaceItem(item);
}

function openDetails(row) {
    selected.value = row;
}

function detailBadge(row) {
    if (row.risk) return riskText(row);
    return statusLabel(row.status);
}

function detailBadgeClass(row) {
    if (row.risk === "safe") return "border-emerald-600/30 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300";
    if (row.risk === "waiting") return "border-sky-600/30 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300";
    if (row.risk === "review" || row.risk === "incomplete" || row.status === "invalid") return "border-amber-600/40 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300";
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

function courierKey(title) {
    const key = String(title || "").toLowerCase();
    if (key.includes("stead")) return "steadfast";
    if (key.includes("pathao")) return "pathao";
    if (key.includes("paper")) return "paperfly";
    if (key.includes("red")) return "redx";
    if (key.includes("carry")) return "carrybee";
    return key;
}

function courierName(key) {
    if (key === "steadfast") return "SteadFast";
    if (key === "pathao") return "Pathao";
    if (key === "paperfly") return "Paperfly";
    if (key === "redx") return "RedX";
    if (key === "carrybee") return "Carrybee";
    return key;
}

function clockTime(iso) {
    const time = Date.parse(iso);
    if (!Number.isFinite(time)) return "";
    return new Date(time).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
}

function courierLine(courier, row = null) {
    if (courier.available === false) {
        if (courier.reason === "waiting") {
            const next = row?.waiting?.[courierKey(courier.title)]?.next_at;
            return next && Date.parse(next) > Date.now() ? `Waiting · asked again at ${clockTime(next)}` : "Waiting · asked again soon";
        }
        if (courier.reason === "sample") return "RedX did not return this number's history";
        if (courier.reason === "busy") return "Courier had no room for this number. Refresh asks it again.";
        if (courier.reason === "paused") return "Courier is paused for now. Refresh asks it again later.";
        if (courier.reason === "credentials") return "Courier login was rejected";
        if (courier.reason === "not_set_up") return "Courier is not set up";
    }
    if (!courier.available && !courier.received && !courier.cancelled && !courier.partial) {
        return "Data unavailable";
    }
    if (!courier.received && !courier.cancelled && !courier.partial && courier.success_rate !== null && courier.success_rate !== undefined) {
        return "Ratio / rating only (counts hidden)";
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
        "no delivery history",
        "no delivery records",
        "no order history",
        "no fraud reports",
        "did not answer",
        "did not return",
        "not been checked",
        "not set up for fraud",
        "asked us to slow down",
        "limit reached for this store",
        "temporarily unreachable",
        "temporarily unavailable",
        "showing last known good data",
        "exact parcel counts are hidden",
        "no finished parcels for this number",
        "platform cache",
        "no delivery detail rows",
    ].some((needle) => lower.includes(needle));
}

function notesEmptyLabel(row) {
    const stored = (row.couriers || []).some((courier) => Array.isArray(courier.notes));
    return stored ? "No fraud notes for this number." : "Notes were not stored for this check.";
}

async function replayAsNew() {
    if (replaying.value || running.value) return;
    if (!window.confirm("Check every number in this batch with the couriers again? Numbers already counted today do not use your daily limit again.")) return;

    replaying.value = true;
    try {
        const { data } = await axios.post(route("portal.fraud-check.batches.replay", batchState.value.id));
        applyPayload(data);
        if (data.quota) quotaState.value = data.quota;
        finishedAt.value = null;
        pausedMs.value = 0;
        pauseStartedAt.value = null;
        stopRequested = false;
        await runPending();
    } catch (error) {
        if (error?.response?.status === 429) {
            refreshNotice.value = "Batches can be checked again 5 times an hour. Try again later.";
        } else {
            refreshNotice.value = error?.response?.data?.errors?.batch?.[0] || "Could not start checking this batch again.";
        }
    } finally {
        replaying.value = false;
    }
}

async function continueBatch() {
    if (running.value || refreshingIncomplete.value || limitBlocked.value) {
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
    workersStalled.value = false;
    const run = { token: ++runToken, halted: false };
    const workers = bulkWorkers.value;
    running.value = true;
    schedulePoll(POLL_RUNNING_MS);
    await Promise.all(Array.from({ length: workers }, (_, index) => runWorker(run, index)));
    if (run.token !== runToken) return;
    if (items.value.some((row) => row.status === "pending") && !(batchState.value.pending > 0)) {
        await syncItems();
    }
    checkingIds.value = [];
    running.value = false;
}

// Each worker asks the server for the next number. The server hands every
// number to one request only, so the row a worker marks is only a guess
// until its answer arrives.
async function runWorker(run, index) {
    if (index > 0) await wait(index * 400);
    let idleSince = 0;
    while (run.token === runToken && !run.halted && !stopRequested) {
        const guess = items.value.find((row) => row.status === "pending" && !isChecking(row));
        if (!guess) break;
        markChecking(guess.id);
        let data = null;
        try {
            ({ data } = await axios.post(route("portal.fraud-check.batches.next", batchState.value.id), {}, { timeout: 180000 }));
        } catch {
            data = null;
        } finally {
            unmarkChecking(guess.id);
        }
        if (data) {
            if (data.batch) batchState.value = data.batch;
            if (data.quota) quotaState.value = data.quota;
            if (data.item) replaceItem(data.item);
            if (data.limit_reached) {
                limitNotice.value = "Today's courier history limit is used up. Pending numbers were not checked.";
                run.halted = true;
                break;
            }
            if (data.batch?.status === "stopped") {
                run.halted = true;
                break;
            }
            if (data.done) {
                run.halted = true;
                break;
            }
            if (data.item && !data.retry) {
                idleSince = 0;
                continue;
            }
        }
        if (!idleSince) idleSince = Date.now();
        if (Date.now() - idleSince > 240000) {
            workersStalled.value = true;
            refreshNotice.value = "This page stopped asking for the remaining numbers. Press Resume to continue.";
            run.halted = true;
            break;
        }
        await wait(1000 + index * 300);
    }
}

async function syncItems() {
    try {
        const { data } = await axios.get(route("portal.fraud-check.batches.show", batchState.value.id), {
            headers: { Accept: "application/json" },
        });
        applyPayload(data);
        if (data.quota) quotaState.value = data.quota;
    } catch {
        // The table keeps the rows it already has.
    }
}

function canRefresh(row) {
    return ["found", "not_found", "error"].includes(row.status);
}

async function refreshRow(row, duringRun = false, quiet = false) {
    if (isChecking(row) || retryingId.value || !canRefresh(row)) return { skipped: true };
    if (!duringRun && (refreshingIncomplete.value || refreshingId.value)) {
        return { skipped: true };
    }
    if (!duringRun) refreshingId.value = row.id;
    markChecking(row.id);
    if (!quiet) refreshNotice.value = "";
    try {
        await takeRefreshSlot();
        const { data } = await axios.post(
            route("portal.fraud-check.batches.refresh", { batch: batchState.value.id, item: row.id }),
            {},
            { timeout: 180000 },
        );
        if (data.batch) batchState.value = data.batch;
        if (data.quota) quotaState.value = data.quota;
        if (data.item) replaceItem(data.item);
        if (data.limit_reached) {
            if (!quiet) refreshNotice.value = "Today's courier history limit is used up, so this number was not checked again.";
            return { limit: true, refreshed: false };
        }
        if (data.refreshed === false) {
            if (!quiet) refreshNotice.value = "Could not fetch new courier data for this number. The previous result is still shown.";
            return { refreshed: false };
        }
        return { refreshed: true };
    } catch (error) {
        if (error?.response?.status === 429) {
            const waitMs = retryAfterMs(error);
            refreshSlotAt = Math.max(refreshSlotAt, Date.now() + waitMs);
            if (!quiet) refreshNotice.value = "Refreshing paused so the couriers are not asked too quickly. Press Refresh incomplete again for the rest.";
            return { throttled: true, refreshed: false, waitMs };
        }
        if (!quiet) refreshNotice.value = "Could not fetch new courier data for this number. The previous result is still shown.";
        return { refreshed: false };
    } finally {
        unmarkChecking(row.id);
        if (!duringRun && refreshingId.value === row.id) refreshingId.value = null;
    }
}

async function takeRefreshSlot() {
    const now = Date.now();
    const slot = Math.max(now, refreshSlotAt);
    refreshSlotAt = slot + REFRESH_GAP_MS;
    if (slot > now) await wait(slot - now);
}

function retryAfterMs(error) {
    const seconds = Number(error?.response?.headers?.["retry-after"]);
    const ms = (Number.isFinite(seconds) && seconds > 0 ? seconds : 5) * 1000;
    return Math.min(65000, Math.max(2000, ms));
}

function clearThrottleNotice() {
    if (refreshNotice.value === THROTTLE_WAIT_NOTICE) refreshNotice.value = "";
}

const incompleteTargets = computed(() => sortedItems.value.filter((row) => row.risk === "incomplete" && canRefresh(row)));
const waitingTargets = computed(() => items.value.filter((row) => row.risk === "waiting" && canRefresh(row)));

function stopWaitingTry() {
    waitingAbort?.abort();
}

async function tryWaitingRows() {
    if (tryingWaiting.value || retryingId.value) return;
    const total = Math.min(3, waitingTargets.value.length);
    if (!total) return;

    tryingWaiting.value = true;
    waitingTry.value = { done: 0, total };
    refreshNotice.value = "";
    waitingAbort = new AbortController();
    try {
        const { data } = await axios.post(
            route("portal.fraud-check.batches.fill-waiting", batchState.value.id),
            {},
            { timeout: 180000, signal: waitingAbort.signal },
        );
        if (data.batch) batchState.value = data.batch;
        for (const item of data.items || []) replaceItem(item);
        waitingTry.value.done = Number(data.asked) || 0;
    } catch (error) {
        if (error?.code !== "ERR_CANCELED") {
            refreshNotice.value = "Could not ask the waiting couriers again.";
        }
    } finally {
        waitingAbort = null;
        tryingWaiting.value = false;
    }
}

function stopIncompleteRefresh() {
    stopIncompleteRequested = true;
}

async function refreshIncompleteRows() {
    if (running.value || refreshingIncomplete.value || refreshingId.value || retryingId.value) return;
    const ids = incompleteTargets.value.map((row) => row.id);
    if (!ids.length) return;

    stopIncompleteRequested = false;
    refreshingIncomplete.value = true;
    incompleteRefresh.value = { done: 0, total: ids.length };
    refreshNotice.value = "";
    let failed = 0;
    let halted = false;
    let cursor = 0;
    const throttled = new Map();

    const lane = async (index) => {
        if (index > 0) await wait(index * 400);
        while (!stopIncompleteRequested && !halted) {
            const id = ids[cursor];
            cursor += 1;
            if (id === undefined) return;
            const row = items.value.find((item) => item.id === id);
            if (!row || row.risk !== "incomplete" || !canRefresh(row) || isChecking(row)) {
                incompleteRefresh.value.done += 1;
                continue;
            }
            const result = await refreshRow(row, true, true);
            if (result?.throttled && (throttled.get(id) ?? 0) < MAX_THROTTLED_TRIES) {
                // Asked again at the end of the list, after the server's wait.
                throttled.set(id, (throttled.get(id) ?? 0) + 1);
                ids.push(id);
                refreshNotice.value = THROTTLE_WAIT_NOTICE;
                continue;
            }
            incompleteRefresh.value.done += 1;
            if (result?.limit || result?.throttled) {
                if (result?.throttled) {
                    refreshNotice.value = "Refreshing paused so the couriers are not asked too quickly. Press Refresh incomplete again for the rest.";
                }
                halted = true;
                stopIncompleteRequested = true;
                return;
            }
            clearThrottleNotice();
            if (result && result.refreshed === false && !result.skipped) failed += 1;
        }
    };

    try {
        const lanes = Math.min(bulkWorkers.value, ids.length);
        await Promise.all(Array.from({ length: lanes }, (_, index) => lane(index)));
    } finally {
        refreshingIncomplete.value = false;
    }

    if (!halted && !stopIncompleteRequested && failed > 0) {
        refreshNotice.value = failed === 1
            ? "1 number could not be fetched again. The previous result is still shown."
            : `${failed} numbers could not be fetched again. The previous results are still shown.`;
    }
}

async function retryRow(row) {
    if (running.value || refreshingIncomplete.value || retryingId.value || limitBlocked.value) {
        if (limitBlocked.value) {
            limitNotice.value = "Today's courier history limit is used up, so this number was not checked again.";
        }
        return;
    }
    retryingId.value = row.id;
    markChecking(row.id);
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
        unmarkChecking(row.id);
        retryingId.value = null;
    }
}

async function stop() {
    stopRequested = true;
    const { data } = await axios.post(route("portal.fraud-check.batches.stop", batchState.value.id));
    applyPayload(data);
    running.value = false;
}

async function pauseChecking() {
    if (pausing.value) return;
    if (batchState.value.status === "stopped" || workersStalled.value) {
        continueBatch();
        return;
    }
    pausing.value = true;
    try {
        await stop();
    } finally {
        pausing.value = false;
    }
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

function countWaiting() {
    return items.value.filter((row) => row.status === "pending" || row.status === "checking").length;
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
    if (status === "pending") return "Not checked";
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
    if (risk === "waiting") return "Waiting for courier";
    if (risk === "incomplete") return "Incomplete";
    if (risk === "high") return "High risk";
    return "";
}

function waitingCouriers(row) {
    return Object.keys(row?.waiting || {});
}

function riskText(row) {
    if (row.risk !== "waiting") return riskLabel(row.risk);
    const names = waitingCouriers(row).map(courierName);
    return names.length ? `Waiting for ${names.join(", ")}` : riskLabel(row.risk);
}

function waitingHint(row) {
    const times = waitingCouriers(row)
        .map((key) => Date.parse(row.waiting[key]?.next_at))
        .filter((time) => Number.isFinite(time));
    if (!times.length) return "Asked again soon";
    const next = Math.min(...times);
    return next > Date.now() ? `Asked again at ${clockTime(new Date(next).toISOString())}` : "Asked again soon";
}

function courierStatusText(status) {
    const name = courierName(status.courier);
    const waiting = `${status.waiting} waiting`;
    if (status.paused_until) return `${name} paused until ${clockTime(status.paused_until)} · ${waiting}`;
    if (status.eta_minutes) return `${name} · ${waiting} · about ${status.eta_minutes} min`;
    return `${name} · ${waiting}`;
}

function riskClass(risk) {
    if (risk === "safe") return "bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200";
    if (risk === "review" || risk === "incomplete") return "bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200";
    if (risk === "waiting") return "bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200";
    if (risk === "high") return "bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200";
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
.results-caret {
    font-size: 9.6px;
    line-height: 1;
}

.results-search {
    position: relative;
    display: block;
    width: 100%;
}

.results-search input {
    width: 100%;
    height: 38px;
    border-radius: 999px;
    border: 1px solid var(--mp-line);
    background: #fff;
    color: var(--mp-ink);
    padding: 0 34px 0 36px;
    font-size: 13px;
    outline: none;
    box-shadow: 0 1px 2px rgba(20, 16, 8, 0.04);
}

.results-search input:focus {
    border-color: #e6a800;
    box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.28);
}

.results-search-icon,
.results-search-clear {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: var(--mp-muted);
    font-size: 13px;
    line-height: 1;
}

.results-search-icon {
    left: 13px;
    pointer-events: none;
}

.results-search-clear {
    right: 8px;
    display: inline-flex;
    height: 22px;
    width: 22px;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
}

.results-search-clear:hover {
    background: rgba(20, 16, 8, 0.06);
}

:global(.dark) .results-search input {
    background: #161616;
}

@media (min-width: 640px) {
    .results-search {
        width: 16.5rem;
    }
}

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
