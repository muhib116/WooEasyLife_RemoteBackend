<template>
    <AuthenticatedLayout title="Webhook Activities">
        <div class="space-y-5">
            <PageHeader
                title="Webhook Activities"
                description="Monitor courier webhook events, forward status, and retry queue"
                icon="PhArrowClockwise"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <Button
                        label="Test webhook"
                        icon="pi pi-send"
                        severity="info"
                        size="small"
                        class="w-full sm:w-auto"
                        @click="openCourierTestDialog"
                    />
                    <Button
                        label="Process waiting"
                        icon="pi pi-play"
                        severity="help"
                        size="small"
                        class="w-full sm:w-auto"
                        :loading="processingRetries"
                        @click="handleProcessRetries"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        class="w-full sm:w-auto"
                        :loading="loading"
                        @click="reloadAll"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template v-if="loading">
                    <StatCardSkeleton
                        v-for="index in 4"
                        :key="`stat-skeleton-${index}`"
                        :delay="index * 90"
                    />
                </template>
                <template v-else>
                    <StatCard
                        title="Total Events"
                        :value="eventStats.total_events"
                        icon="PhListBullets"
                        :subtitle="eventsStatSubtitle"
                    />
                    <StatCard
                        title="Forwarded"
                        :value="eventStats.success_count"
                        icon="PhCheckCircle"
                        subtitle="Successful forwards"
                        accent-class="bg-emerald-500"
                        icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                        icon-class="text-emerald-600 dark:text-emerald-400"
                    />
                    <StatCard
                        title="Pending Retries"
                        :value="summary.pending_retries"
                        icon="PhClock"
                        :subtitle="`${eventStats.queued_count + eventStats.forwarding_count} waiting to forward`"
                        accent-class="bg-amber-500"
                        icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                        icon-class="text-amber-600 dark:text-amber-400"
                    />
                    <StatCard
                        title="Failed / Orphan"
                        :value="eventStats.failed_count + eventStats.orphan_count"
                        icon="PhWarningCircle"
                        :subtitle="`${summary.failed_retries} failed retries`"
                        accent-class="bg-rose-500"
                        icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                        icon-class="text-rose-600 dark:text-rose-400"
                    />
                </template>
            </div>

            <div class="grid grid-cols-2 gap-2 rounded-2xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-gray-700 dark:bg-slate-900/60">
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'events'
                            ? 'bg-violet-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-slate-800'
                    "
                    @click="activeTab = 'events'"
                >
                    Webhook Events
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="activeTab === 'events' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-gray-300'"
                    >
                        {{ events.total }}
                    </span>
                </button>
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'retries'
                            ? 'bg-violet-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-slate-800'
                    "
                    @click="activeTab = 'retries'"
                >
                    Retry Queue
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="activeTab === 'retries' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-gray-300'"
                    >
                        {{ retries.total }}
                    </span>
                </button>
            </div>

            <PageCard
                v-show="activeTab === 'events'"
                title="Recent Webhook Events"
                :description="eventsDescription"
                no-padding
            >
                <template #actions>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ events.total }} total
                    </span>
                </template>

                <div class="space-y-0 border-b border-gray-100 dark:border-gray-800">
                    <div class="grid grid-cols-1 gap-3 px-4 py-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Select
                            v-model="eventFilters.partner"
                            :options="partnerOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All partners"
                            showClear
                            class="w-full"
                            @update:model-value="handleEventFiltersChange"
                        />
                        <Select
                            v-model="eventFilters.site_url"
                            :options="storeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All stores"
                            filter
                            filterPlaceholder="Search store..."
                            showClear
                            class="w-full"
                            @update:model-value="handleEventFiltersChange"
                        />
                        <Select
                            v-model="eventFilters.environment"
                            :options="environmentOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All environments"
                            showClear
                            class="w-full"
                            @update:model-value="handleEventFiltersChange"
                        />
                        <Select
                            v-model="eventFilters.forward_status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All statuses"
                            showClear
                            class="w-full"
                            @update:model-value="handleEventFiltersChange"
                        />
                        <span class="relative sm:col-span-2 xl:col-span-3">
                            <InputText
                                v-model="eventFilters.search"
                                placeholder="Search consignment, site, order..."
                                class="w-full pr-10"
                                @keyup.enter="handleEventFiltersChange"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                @click="handleEventFiltersChange"
                            >
                                <i class="pi pi-search text-sm" />
                            </button>
                        </span>
                        <Button
                            v-if="hasActiveEventFilters"
                            label="Clear filters"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="w-full sm:w-auto"
                            @click="clearEventFilters"
                        />
                    </div>

                    <div
                        v-if="selectedEvents.length || selectAllMatchingEvents"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-violet-100 bg-violet-50/80 px-4 py-3 dark:border-violet-500/20 dark:bg-violet-500/10"
                    >
                        <div class="space-y-1 text-sm">
                            <p class="font-medium text-violet-900 dark:text-violet-100">
                                {{ eventSelectionLabel }}
                            </p>
                            <p
                                v-if="selectAllMatchingEvents"
                                class="text-xs text-violet-700 dark:text-violet-200"
                            >
                                Selection applies across all pages that match the current filters.
                            </p>
                            <p
                                v-else-if="showSelectAllEventsPrompt"
                                class="text-xs text-violet-700 dark:text-violet-200"
                            >
                                All {{ events.data.length }} on this page are selected.
                                <button
                                    type="button"
                                    class="font-medium underline"
                                    @click="selectAllMatchingEvents = true"
                                >
                                    Select all {{ events.total }} matching events
                                </button>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                label="Clear selection"
                                severity="secondary"
                                text
                                size="small"
                                @click="clearEventSelection"
                            />
                            <Button
                                label="Delete selected"
                                icon="pi pi-trash"
                                severity="danger"
                                size="small"
                                :loading="deletingEvents"
                                @click="confirmDeleteEvents"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="loadingEvents"
                    class="border-t border-slate-100 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <i class="pi pi-spin pi-spinner" />
                        Loading webhook events...
                    </div>
                    <TableSkeletonLoader class="hidden md:block" :columns="eventSkeletonColumns" :rows="7" />
                </div>

                <template v-else-if="events.data.length">
                    <div class="space-y-3 p-3 md:hidden">
                        <article
                            v-for="data in events.data"
                            :key="data.id"
                            class="rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-slate-900"
                        >
                            <div class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 shrink-0"
                                    :checked="isEventSelected(data)"
                                    :aria-label="`Select event ${data.consignment_id || data.id}`"
                                    @change="toggleEventSelection(data)"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {{ formatDate(data.created_at) }}
                                            </p>
                                            <p class="mt-0.5 font-mono text-xs text-gray-500">
                                                {{ data.event_type || "—" }}
                                            </p>
                                        </div>
                                        <Tag
                                            :value="statusLabel(data.forward_status)"
                                            :severity="statusSeverity(data.forward_status)"
                                        />
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                            :class="partnerBadgeClass(data.partner)"
                                        >
                                            {{ data.partner }}
                                        </span>
                                        <span class="text-xs text-gray-500">{{ data.environment }}</span>
                                        <Tag
                                            v-if="data.is_admin_test"
                                            value="Admin test"
                                            severity="info"
                                        />
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                                        {{ data.consignment_id || "—" }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Order #{{ data.wc_order_id || "—" }}
                                    </p>
                                    <p class="mt-1 break-all text-xs text-gray-500">
                                        {{ data.site_url || "—" }}
                                    </p>
                                    <p
                                        v-if="data.forward_message"
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{ data.forward_message }}
                                    </p>
                                    <div class="mt-3">
                                        <TableActions align="left">
                                            <TableActionButton
                                                v-if="canTestPlugin(data)"
                                                action="link"
                                                tooltip="Test plugin"
                                                :loading="testingEventId === data.id"
                                                @click="handleTestPlugin(data.id)"
                                            />
                                            <TableActionButton
                                                v-if="canRetryEvent(data)"
                                                action="retry"
                                                tooltip="Retry forward"
                                                :loading="retryingEventId === data.id"
                                                @click="handleRetryEvent(data.id)"
                                            />
                                            <TableActionButton
                                                action="delete"
                                                tooltip="Delete event"
                                                :loading="deletingEventId === data.id"
                                                @click="confirmDeleteSingleEvent(data)"
                                            />
                                        </TableActions>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <div class="flex items-center justify-between gap-2 pt-1">
                            <Button
                                label="Previous"
                                icon="pi pi-chevron-left"
                                severity="secondary"
                                outlined
                                size="small"
                                :disabled="events.current_page <= 1"
                                @click="fetchEvents(events.current_page - 1)"
                            />
                            <span class="text-xs text-gray-500">
                                {{ events.current_page }} / {{ pageCount(events.total, events.per_page) }}
                            </span>
                            <Button
                                label="Next"
                                icon="pi pi-chevron-right"
                                iconPos="right"
                                severity="secondary"
                                outlined
                                size="small"
                                :disabled="events.current_page >= pageCount(events.total, events.per_page)"
                                @click="fetchEvents(events.current_page + 1)"
                            />
                        </div>
                    </div>

                    <div class="hidden md:block">
                <DataTable
                    v-model:selection="selectedEvents"
                    :value="events.data"
                    dataKey="id"
                    :rows="eventsPerPage"
                    :totalRecords="events.total"
                    :first="(events.current_page - 1) * eventsPerPage"
                    lazy
                    paginator
                    paginatorTemplate="RowsPerPageDropdown PrevPageLink CurrentPageReport NextPageLink"
                    :rowsPerPageOptions="pageSizeValues"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="webhook-activity-table professional-table text-sm"
                    @page="onEventPage"
                    @update:selection="handleEventSelectionChange"
                >
                    <Column selectionMode="multiple" headerStyle="width:3rem" />
                    <Column header="Received" style="min-width: 8.5rem">
                        <template #body="{ data }">
                            <div class="font-medium text-gray-800 dark:text-gray-100">
                                {{ formatDate(data.created_at) }}
                            </div>
                            <div class="mt-1 font-mono text-xs text-gray-500">
                                {{ data.event_type || "—" }}
                            </div>
                            <Tag
                                v-if="data.is_admin_test"
                                value="Admin test"
                                severity="info"
                                class="mt-1"
                            />
                        </template>
                    </Column>
                    <Column header="Partner" style="min-width: 7rem">
                        <template #body="{ data }">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                :class="partnerBadgeClass(data.partner)"
                            >
                                {{ data.partner }}
                            </span>
                            <div class="mt-1 text-xs text-gray-500">{{ data.environment }}</div>
                        </template>
                    </Column>
                    <Column header="Shipment" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.consignment_id || "—" }}</div>
                            <div class="text-xs text-gray-500">
                                Order #{{ data.wc_order_id || "—" }}
                            </div>
                            <div class="mt-1 line-clamp-2 break-all text-xs text-gray-500">
                                {{ data.site_url || "—" }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7.5rem">
                        <template #body="{ data }">
                            <Tag
                                :value="statusLabel(data.forward_status)"
                                :severity="statusSeverity(data.forward_status)"
                            />
                            <p
                                v-if="data.forward_message"
                                class="mt-1 line-clamp-2 text-xs text-gray-500"
                            >
                                {{ data.forward_message }}
                            </p>
                        </template>
                    </Column>
                    <Column header="Action" header-class="text-right" headerStyle="width:7.5rem">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    v-if="canTestPlugin(data)"
                                    action="link"
                                    tooltip="Test plugin"
                                    :loading="testingEventId === data.id"
                                    @click="handleTestPlugin(data.id)"
                                />
                                <TableActionButton
                                    v-if="canRetryEvent(data)"
                                    action="retry"
                                    tooltip="Retry forward"
                                    :loading="retryingEventId === data.id"
                                    @click="handleRetryEvent(data.id)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Delete event"
                                    :loading="deletingEventId === data.id"
                                    @click="confirmDeleteSingleEvent(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>
                    </div>
                </template>

                <EmptyState
                    v-else
                    icon="PhArrowClockwise"
                    :title="hasActiveEventFilters ? 'No matching events' : 'No webhook events'"
                    :description="
                        hasActiveEventFilters
                            ? 'Try adjusting your filters or clearing the search'
                            : 'Events will appear here when couriers send status updates'
                    "
                >
                    <Button
                        v-if="hasActiveEventFilters"
                        label="Clear filters"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        size="small"
                        class="mt-4"
                        @click="clearEventFilters"
                    />
                </EmptyState>
            </PageCard>

            <PageCard
                v-show="activeTab === 'retries'"
                title="Forward Retry Queue"
                description="Pending and failed WordPress forward attempts"
                no-padding
            >
                <template #actions>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ retries.total }} total
                    </span>
                </template>

                <div
                    v-if="selectedRetries.length || selectAllMatchingRetries"
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-violet-100 bg-violet-50/80 px-4 py-3 dark:border-violet-500/20 dark:bg-violet-500/10"
                >
                    <div class="space-y-1 text-sm">
                        <p class="font-medium text-violet-900 dark:text-violet-100">
                            {{ retrySelectionLabel }}
                        </p>
                        <p class="text-xs text-violet-700 dark:text-violet-200">
                            Bulk delete only affects pending and failed retry rows.
                        </p>
                        <p
                            v-if="selectAllMatchingRetries"
                            class="text-xs text-violet-700 dark:text-violet-200"
                        >
                            Selection applies across all pages in the retry queue.
                        </p>
                        <p
                            v-else-if="showSelectAllRetriesPrompt"
                            class="text-xs text-violet-700 dark:text-violet-200"
                        >
                            All {{ retries.data.length }} on this page are selected.
                            <button
                                type="button"
                                class="font-medium underline"
                                @click="selectAllMatchingRetries = true"
                            >
                                Select all {{ retries.total }} matching retries
                            </button>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="Clear selection"
                            severity="secondary"
                            text
                            size="small"
                            @click="clearRetrySelection"
                        />
                        <Button
                            label="Delete selected"
                            icon="pi pi-trash"
                            severity="danger"
                            size="small"
                            :loading="deletingRetries"
                            @click="confirmDeleteRetries"
                        />
                    </div>
                </div>

                <div
                    v-if="loadingRetries"
                    class="border-t border-slate-100 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <i class="pi pi-spin pi-spinner" />
                        Loading retry queue...
                    </div>
                    <TableSkeletonLoader class="hidden md:block" :columns="retrySkeletonColumns" :rows="5" />
                </div>

                <template v-else-if="retries.data.length">
                    <div class="space-y-3 p-3 md:hidden">
                        <article
                            v-for="data in retries.data"
                            :key="data.id"
                            class="rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-slate-900"
                        >
                            <div class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 shrink-0"
                                    :checked="isRetrySelected(data)"
                                    :aria-label="`Select retry ${data.consignment_id || data.id}`"
                                    @change="toggleRetrySelection(data)"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span
                                                v-if="data.partner"
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                                :class="partnerBadgeClass(data.partner)"
                                            >
                                                {{ data.partner }}
                                            </span>
                                            <span v-else class="text-sm text-gray-500">—</span>
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ data.attempts }} / {{ data.max_attempts }} attempts
                                            </p>
                                        </div>
                                        <Tag :value="retryStatusLabel(data.status)" :severity="retrySeverity(data.status)" />
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                                        {{ data.consignment_id || "—" }}
                                    </p>
                                    <p class="mt-1 break-all text-xs text-gray-500">
                                        {{ data.site_url || "—" }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ data.next_retry_at ? formatDate(data.next_retry_at) : "No next retry" }}
                                    </p>
                                    <p
                                        v-if="data.last_error"
                                        class="mt-1 text-xs text-rose-600 dark:text-rose-400"
                                    >
                                        {{ data.last_error }}
                                    </p>
                                    <div class="mt-3">
                                        <TableActions align="left">
                                            <TableActionButton
                                                action="link"
                                                tooltip="Test plugin"
                                                :loading="testingRetryId === data.id"
                                                @click="handleTestRetryPlugin(data.id, data.event_id)"
                                            />
                                            <TableActionButton
                                                v-if="data.status !== 'completed'"
                                                action="retry"
                                                tooltip="Retry now"
                                                :loading="retryingRetryId === data.id"
                                                @click="handleRetryForward(data.id)"
                                            />
                                            <TableActionButton
                                                action="delete"
                                                tooltip="Delete retry"
                                                :loading="deletingRetryId === data.id"
                                                @click="confirmDeleteSingleRetry(data)"
                                            />
                                        </TableActions>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <div class="flex items-center justify-between gap-2 pt-1">
                            <Button
                                label="Previous"
                                icon="pi pi-chevron-left"
                                severity="secondary"
                                outlined
                                size="small"
                                :disabled="retries.current_page <= 1"
                                @click="fetchRetries(retries.current_page - 1)"
                            />
                            <span class="text-xs text-gray-500">
                                {{ retries.current_page }} / {{ pageCount(retries.total, retries.per_page) }}
                            </span>
                            <Button
                                label="Next"
                                icon="pi pi-chevron-right"
                                iconPos="right"
                                severity="secondary"
                                outlined
                                size="small"
                                :disabled="retries.current_page >= pageCount(retries.total, retries.per_page)"
                                @click="fetchRetries(retries.current_page + 1)"
                            />
                        </div>
                    </div>

                    <div class="hidden md:block">
                <DataTable
                    v-model:selection="selectedRetries"
                    :value="retries.data"
                    dataKey="id"
                    :rows="retriesPerPage"
                    :totalRecords="retries.total"
                    :first="(retries.current_page - 1) * retriesPerPage"
                    lazy
                    paginator
                    paginatorTemplate="RowsPerPageDropdown PrevPageLink CurrentPageReport NextPageLink"
                    :rowsPerPageOptions="pageSizeValues"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="webhook-activity-table professional-table text-sm"
                    @page="onRetryPage"
                    @update:selection="handleRetrySelectionChange"
                >
                    <Column selectionMode="multiple" headerStyle="width:3rem" />
                    <Column header="Partner" style="min-width: 7rem">
                        <template #body="{ data }">
                            <span
                                v-if="data.partner"
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                :class="partnerBadgeClass(data.partner)"
                            >
                                {{ data.partner }}
                            </span>
                            <span v-else>—</span>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ data.attempts }} / {{ data.max_attempts }} attempts
                            </div>
                        </template>
                    </Column>
                    <Column header="Shipment" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.consignment_id || "—" }}</div>
                            <div class="mt-1 line-clamp-2 break-all text-xs text-gray-500">
                                {{ data.site_url || "—" }}
                            </div>
                            <p
                                v-if="data.last_error"
                                class="mt-1 line-clamp-2 text-xs text-rose-600 dark:text-rose-400"
                            >
                                {{ data.last_error }}
                            </p>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag :value="retryStatusLabel(data.status)" :severity="retrySeverity(data.status)" />
                            <div class="mt-1 text-xs text-gray-500">
                                {{ data.next_retry_at ? formatDate(data.next_retry_at) : "No next retry" }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Action" header-class="text-right" headerStyle="width:7.5rem">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="link"
                                    tooltip="Test plugin"
                                    :loading="testingRetryId === data.id"
                                    @click="handleTestRetryPlugin(data.id, data.event_id)"
                                />
                                <TableActionButton
                                    v-if="data.status !== 'completed'"
                                    action="retry"
                                    tooltip="Retry now"
                                    :loading="retryingRetryId === data.id"
                                    @click="handleRetryForward(data.id)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Delete retry"
                                    :loading="deletingRetryId === data.id"
                                    @click="confirmDeleteSingleRetry(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>
                    </div>
                </template>

                <EmptyState
                    v-else
                    icon="PhCheckCircle"
                    title="No pending retries"
                    description="All webhook forwards are up to date"
                />
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="testDialogVisible"
            header="Plugin Reachability Test"
            :style="{ width: 'min(34rem, calc(100vw - 1.5rem))' }"
        >
            <div v-if="testResult" class="space-y-4 text-sm">
                <div
                    class="rounded-xl border px-4 py-3"
                    :class="
                        testResult.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                            : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200'
                    "
                >
                    <p class="font-semibold">
                        {{ testResult.success ? "Plugin reachable" : "Plugin not reachable" }}
                    </p>
                    <p class="mt-1">{{ testResult.message }}</p>
                    <p v-if="testResult.result?.site_url" class="mt-2 break-all text-xs opacity-80">
                        Site: {{ testResult.result.site_url }}
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="font-medium text-gray-800 dark:text-gray-100">
                                License Endpoint
                            </p>
                            <Tag
                                :value="testResult.result?.license_probe?.success ? 'OK' : 'Failed'"
                                :severity="testResult.result?.license_probe?.success ? 'success' : 'danger'"
                            />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.license_probe?.detail || "—" }}
                        </p>
                        <p class="mt-2 break-all text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.license_probe?.url || "—" }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="font-medium text-gray-800 dark:text-gray-100">
                                Courier Status Hook
                            </p>
                            <Tag
                                :value="testResult.result?.forward_probe?.success ? 'OK' : 'Failed'"
                                :severity="testResult.result?.forward_probe?.success ? 'success' : 'danger'"
                            />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.forward_probe?.detail || "—" }}
                        </p>
                        <p class="mt-2 break-all text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.forward_probe?.url || "—" }}
                        </p>
                    </div>
                </div>
            </div>
        </AdminDialog>

        <AdminDialog
            v-model:visible="courierTestDialogVisible"
            header="Test Courier Webhook"
            :style="{ width: 'min(36rem, calc(100vw - 1.5rem))' }"
        >
            <div class="space-y-4 text-sm">
                <p class="text-gray-500 dark:text-gray-400">
                    Sends a sample payload through the same inbound handler used by the public
                    courier webhook routes.
                </p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            Courier partner
                        </label>
                        <Dropdown
                            v-model="courierTestForm.partner"
                            :options="courierPartnerOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            @change="handleCourierPartnerChange"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            Test type
                        </label>
                        <Dropdown
                            v-model="courierTestForm.test_type"
                            :options="courierTestTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            Environment
                        </label>
                        <Dropdown
                            v-model="courierTestForm.environment"
                            :options="environmentOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ courierConsignmentLabel }}
                        </label>
                        <InputText
                            v-model="courierTestForm.consignment_id"
                            :placeholder="courierConsignmentPlaceholder"
                            class="w-full"
                        />
                    </div>
                </div>

                <div
                    v-if="courierTestForm.partner !== 'pathao' || courierTestForm.test_type !== 'webhook_integration'"
                    class="space-y-1.5"
                >
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        Invoice (optional)
                    </label>
                    <InputText
                        v-model="courierTestForm.invoice"
                        placeholder="Auto-generated if empty"
                        class="w-full"
                    />
                </div>

                <div
                    v-if="showCourierAuthField"
                    class="space-y-1.5"
                >
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        {{ courierAuthLabel }}
                    </label>
                    <InputText
                        v-model="courierTestForm.auth_token"
                        :placeholder="courierAuthPlaceholder"
                        class="w-full"
                    />
                </div>

                <div
                    v-if="courierTestResult"
                    class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-medium text-gray-800 dark:text-gray-100">
                            Test result
                        </p>
                        <Tag
                            :value="courierTestResult.docs_compliant ? 'Docs compliant' : 'Check response'"
                            :severity="courierTestResult.docs_compliant ? 'success' : 'warn'"
                        />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ courierTestResult.partner }} · {{ courierTestResult.test_type }} ·
                        HTTP {{ courierTestResult.http_status }} ·
                        {{ courierTestResult.message }}
                    </p>
                    <p class="break-all text-xs text-gray-500 dark:text-gray-400">
                        Callback: {{ courierTestResult.callback_url }}
                    </p>
                    <p
                        v-if="courierTestResult.event"
                        class="text-xs text-gray-600 dark:text-gray-300"
                    >
                        Event #{{ courierTestResult.event.id }} ·
                        {{ courierTestResult.event.forward_status }} ·
                        {{ courierTestResult.event.forward_message || "no message" }}
                    </p>
                    <pre class="overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{{ JSON.stringify(courierTestResult.response, null, 2) }}</pre>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="courierTestDialogVisible = false"
                />
                <Button
                    label="Run Test"
                    icon="pi pi-send"
                    severity="info"
                    :loading="testingCourierWebhook"
                    @click="handleTestCourierWebhook"
                />
            </template>
        </AdminDialog>

    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { computed, onMounted, reactive, ref } from "vue";
import { watchDebounced } from "@vueuse/core";
import axios from "axios";
import { format } from "date-fns";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatCardSkeleton from "@/Pages/Users/fragments/StatCardSkeleton.vue";
import TableSkeletonLoader from "@/Pages/Users/fragments/TableSkeletonLoader.vue";
import AdminDialog from "@/Pages/Users/fragments/AdminDialog.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineOptions({
    name: "WebhookActivities",
});

type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

const toast = useToast();
const confirm = useConfirm();

const activeTab = ref<"events" | "retries">("events");
const eventsPerPage = ref(20);
const retriesPerPage = ref(20);
const loading = ref(false);
const loadingEvents = ref(false);
const loadingRetries = ref(false);
const processingRetries = ref(false);
const retryingEventId = ref<number | null>(null);
const retryingRetryId = ref<number | null>(null);
const testingEventId = ref<number | null>(null);
const testingRetryId = ref<number | null>(null);
const testingCourierWebhook = ref(false);
const deletingEvents = ref(false);
const deletingRetries = ref(false);
const deletingEventId = ref<number | null>(null);
const deletingRetryId = ref<number | null>(null);
const testDialogVisible = ref(false);
const courierTestDialogVisible = ref(false);
const courierTestResult = ref<any>(null);
const selectedEvents = ref<any[]>([]);
const selectedRetries = ref<any[]>([]);
const selectAllMatchingEvents = ref(false);
const selectAllMatchingRetries = ref(false);
const testResult = ref<{
    success: boolean;
    message: string;
    result?: {
        site_url?: string | null;
        license_probe?: {
            success?: boolean;
            detail?: string;
            url?: string | null;
        };
        forward_probe?: {
            success?: boolean;
            detail?: string;
            url?: string | null;
        };
    };
} | null>(null);

const summary = reactive({
    admin_test_count: 0,
    pending_retries: 0,
    failed_retries: 0,
    last_event_at: null as string | null,
    bulk_delete_warning_threshold: 50,
});

const eventStats = reactive({
    total_events: 0,
    success_count: 0,
    failed_count: 0,
    retry_queued_count: 0,
    queued_count: 0,
    forwarding_count: 0,
    orphan_count: 0,
});

const applyEventStats = (stats?: Partial<typeof eventStats>) => {
    if (!stats) {
        return;
    }

    Object.assign(eventStats, stats);
};

const events = ref<Paginated<any>>({
    data: [],
    current_page: 1,
    per_page: 20,
    total: 0,
});

const retries = ref<Paginated<any>>({
    data: [],
    current_page: 1,
    per_page: 20,
    total: 0,
});

const eventFilters = reactive({
    partner: null as string | null,
    site_url: null as string | null,
    environment: null as string | null,
    forward_status: null as string | null,
    search: "",
});

const storeOptions = ref<{ label: string; value: string }[]>([]);

const syncStoreOptions = (...siteLists: Array<string[] | undefined>) => {
    const merged = new Set<string>();

    for (const list of siteLists) {
        for (const siteUrl of list ?? []) {
            const normalized = String(siteUrl).trim();

            if (normalized) {
                merged.add(normalized);
            }
        }
    }

    for (const event of events.value.data) {
        if (event.site_url) {
            merged.add(String(event.site_url).trim());
        }
    }

    storeOptions.value = [...merged]
        .sort((a, b) => a.localeCompare(b))
        .map((siteUrl) => ({
            label: siteUrl,
            value: siteUrl,
        }));
};

const partnerOptions = [
    { label: "Steadfast", value: "steadfast" },
    { label: "Pathao", value: "pathao" },
    { label: "RedX", value: "redx" },
];

const environmentOptions = [
    { label: "Live", value: "live" },
    { label: "Sandbox", value: "sandbox" },
];

const statusOptions = [
    { label: "Queued", value: "queued" },
    { label: "Forwarding", value: "forwarding" },
    { label: "Success", value: "success" },
    { label: "Retry queued", value: "retry_queued" },
    { label: "Failed", value: "failed" },
    { label: "Orphan", value: "orphan" },
    { label: "Received", value: "received" },
];

const pageSizeValues = [10, 20, 25, 50, 100];

const eventSkeletonColumns = [
    { width: "1.25rem", variant: "checkbox" as const },
    { width: "7rem", variant: "stack" as const, subWidth: "4rem" },
    { width: "5.5rem", variant: "stack" as const, subWidth: "3.5rem" },
    { width: "7rem", variant: "stack" as const, subWidth: "5rem" },
    { width: "4.5rem", variant: "badge" as const },
    { width: "5.5rem", variant: "actions" as const },
];

const retrySkeletonColumns = [
    { width: "1.25rem", variant: "checkbox" as const },
    { width: "5rem", variant: "stack" as const, subWidth: "4rem" },
    { width: "7rem", variant: "stack" as const, subWidth: "5rem" },
    { width: "4.5rem", variant: "badge" as const },
    { width: "5.5rem", variant: "actions" as const },
];

const courierPartnerOptions = [
    { label: "Steadfast", value: "steadfast" },
    { label: "Pathao", value: "pathao" },
    { label: "RedX", value: "redx" },
];

const courierTestTypeOptionsByPartner: Record<string, { label: string; value: string }[]> = {
    steadfast: [
        { label: "Delivery status", value: "delivery_status" },
        { label: "Tracking update", value: "tracking_update" },
    ],
    pathao: [
        { label: "Webhook integration", value: "webhook_integration" },
        { label: "Order delivered", value: "order_delivered" },
        { label: "Order picked up", value: "order_picked_up" },
    ],
    redx: [
        { label: "Delivered", value: "delivered" },
        { label: "Partial delivery", value: "partial_delivery" },
    ],
};

const courierTestForm = reactive({
    partner: "steadfast",
    test_type: "delivery_status",
    environment: "live",
    consignment_id: "",
    invoice: "",
    auth_token: "",
});

const courierTestTypeOptions = computed(
    () => courierTestTypeOptionsByPartner[courierTestForm.partner] ?? []
);

const showCourierAuthField = computed(() => {
    if (courierTestForm.partner === "pathao" && courierTestForm.test_type === "webhook_integration") {
        return false;
    }

    return true;
});

const courierAuthLabel = computed(() => {
    switch (courierTestForm.partner) {
        case "pathao":
            return "X-PATHAO-Signature";
        case "redx":
            return "Webhook token";
        default:
            return "Bearer token";
    }
});

const courierAuthPlaceholder = computed(() => {
    switch (courierTestForm.partner) {
        case "pathao":
            return "Uses saved Pathao webhook secret if empty";
        case "redx":
            return "Uses saved RedX webhook token if empty";
        default:
            return "Uses saved Steadfast API key if empty";
    }
});

const courierConsignmentLabel = computed(() => {
    return courierTestForm.partner === "redx" ? "Tracking number" : "Consignment ID";
});

const courierConsignmentPlaceholder = computed(() => {
    switch (courierTestForm.partner) {
        case "pathao":
            return "Leave empty to use latest mapped shipment or DL121224VS8TTJ";
        case "redx":
            return "Leave empty to use latest mapped shipment or a generated test ID";
        default:
            return "Leave empty to use latest mapped shipment or 12345";
    }
});

const hasActiveEventFilters = computed(() => {
    return Boolean(
        eventFilters.partner ||
            eventFilters.site_url ||
            eventFilters.environment ||
            eventFilters.forward_status ||
            eventFilters.search.trim()
    );
});

const buildEventFilterParams = (page: number, perPage: number) => {
    const params: Record<string, string | number> = {
        page,
        per_page: perPage,
    };

    if (eventFilters.partner) {
        params.partner = eventFilters.partner;
    }

    if (eventFilters.site_url) {
        params.site_url = eventFilters.site_url;
    }

    if (eventFilters.environment) {
        params.environment = eventFilters.environment;
    }

    if (eventFilters.forward_status) {
        params.forward_status = eventFilters.forward_status;
    }

    const search = eventFilters.search.trim();

    if (search) {
        params.search = search;
    }

    return params;
};

const eventsDescription = computed(() => {
    if (hasActiveEventFilters.value) {
        return "Filtered webhook events and WordPress forward results";
    }

    return "Inbound courier webhooks and WordPress forward results";
});

const eventsStatSubtitle = computed(() => {
    if (hasActiveEventFilters.value) {
        return `${eventStats.total_events} matching event(s)`;
    }

    if (summary.last_event_at) {
        const adminTests =
            summary.admin_test_count > 0
                ? ` · ${summary.admin_test_count} admin test(s)`
                : "";

        return `Last: ${formatDate(summary.last_event_at)}${adminTests}`;
    }

    return eventStats.total_events > 0 ? "Latest webhook activity" : "No events yet";
});

const allPageEventsSelected = computed(() => {
    return events.value.data.length > 0 && selectedEvents.value.length === events.value.data.length;
});

const allPageRetriesSelected = computed(() => {
    return retries.value.data.length > 0 && selectedRetries.value.length === retries.value.data.length;
});

const showSelectAllEventsPrompt = computed(() => {
    return allPageEventsSelected.value
        && !selectAllMatchingEvents.value
        && events.value.total > events.value.data.length;
});

const showSelectAllRetriesPrompt = computed(() => {
    return allPageRetriesSelected.value
        && !selectAllMatchingRetries.value
        && retries.value.total > retries.value.data.length;
});

const eventSelectionLabel = computed(() => {
    if (selectAllMatchingEvents.value) {
        return `All ${events.value.total} matching events selected`;
    }

    return `${selectedEvents.value.length} event(s) selected`;
});

const retrySelectionLabel = computed(() => {
    if (selectAllMatchingRetries.value) {
        return `All ${retries.value.total} matching retries selected`;
    }

    return `${selectedRetries.value.length} retry row(s) selected`;
});

const partnerBadgeClass = (partner: string) => {
    switch (partner) {
        case "steadfast":
            return "bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300";
        case "pathao":
            return "bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300";
        case "redx":
            return "bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300";
        default:
            return "bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300";
    }
};

const buildBulkDeleteMessage = (label: string, count: number, extra = "") => {
    const warningThreshold = summary.bulk_delete_warning_threshold || 50;
    const base = `This will permanently delete ${count} ${label}. This cannot be undone.`;
    const largeDeleteWarning =
        count >= warningThreshold
            ? ` This is a large destructive action affecting ${count} records.`
            : "";

    return `${base}${largeDeleteWarning}${extra ? ` ${extra}` : ""}`;
};

const formatDate = (value?: string | null) => {
    if (!value) return "—";

    try {
        return format(new Date(value), "dd MMM yyyy, hh:mm a");
    } catch {
        return value;
    }
};

const statusLabel = (status: string) => {
    return statusOptions.find((option) => option.value === status)?.label || status || "—";
};

const statusSeverity = (status: string) => {
    switch (status) {
        case "success":
            return "success";
        case "queued":
        case "forwarding":
        case "retry_queued":
            return "warn";
        case "failed":
        case "orphan":
            return "danger";
        default:
            return "secondary";
    }
};

const retryStatusLabel = (status: string) => {
    switch (status) {
        case "pending":
            return "Pending";
        case "completed":
            return "Completed";
        case "failed":
            return "Failed";
        default:
            return status || "—";
    }
};

const retrySeverity = (status: string) => {
    switch (status) {
        case "completed":
            return "success";
        case "pending":
            return "warn";
        case "failed":
            return "danger";
        default:
            return "secondary";
    }
};

const canRetryEvent = (event: { forward_status: string; wc_order_id?: number | null }) => {
    return ["retry_queued", "failed"].includes(event.forward_status) && Boolean(event.wc_order_id);
};

const canTestPlugin = (event: { site_url?: string | null; wc_order_id?: number | null }) => {
    return Boolean(event.site_url || event.wc_order_id);
};

const pageCount = (total: number, perPage: number) => {
    const size = Number(perPage) || 20;

    return Math.max(1, Math.ceil((Number(total) || 0) / size));
};

const isEventSelected = (event: { id: number }) => {
    return selectedEvents.value.some((row) => row.id === event.id);
};

const toggleEventSelection = (event: { id: number }) => {
    selectedEvents.value = isEventSelected(event)
        ? selectedEvents.value.filter((row) => row.id !== event.id)
        : [...selectedEvents.value, event];
    handleEventSelectionChange();
};

const isRetrySelected = (retry: { id: number }) => {
    return selectedRetries.value.some((row) => row.id === retry.id);
};

const toggleRetrySelection = (retry: { id: number }) => {
    selectedRetries.value = isRetrySelected(retry)
        ? selectedRetries.value.filter((row) => row.id !== retry.id)
        : [...selectedRetries.value, retry];
    handleRetrySelectionChange();
};

const fetchSummary = async () => {
    try {
        const { data } = await axios.get(route("webhooks.summary"));
        Object.assign(summary, data);
        applyEventStats(data);
        syncStoreOptions(data.stores);
    } catch (error) {
        console.error("Error fetching webhook summary:", error);
    }
};

const fetchEvents = async (page = events.value.current_page) => {
    loadingEvents.value = true;

    try {
        const { data } = await axios.get(route("webhooks.events"), {
            params: buildEventFilterParams(page, eventsPerPage.value),
        });
        events.value = data;
        if (data.per_page) {
            eventsPerPage.value = data.per_page;
        }
        applyEventStats(data.stats);
        syncStoreOptions(data.stores);
        clearEventSelection();
    } catch (error) {
        console.error("Error fetching webhook events:", error);
        events.value = { data: [], current_page: 1, per_page: 20, total: 0 };
        clearEventSelection();
    } finally {
        loadingEvents.value = false;
    }
};

const fetchRetries = async (page = retries.value.current_page) => {
    loadingRetries.value = true;

    try {
        const { data } = await axios.get(route("webhooks.retries"), {
            params: {
                page,
                per_page: retriesPerPage.value,
            },
        });
        retries.value = data;
        if (data.per_page) {
            retriesPerPage.value = data.per_page;
        }
        clearRetrySelection();
    } catch (error) {
        console.error("Error fetching webhook retries:", error);
        retries.value = { data: [], current_page: 1, per_page: 20, total: 0 };
        clearRetrySelection();
    } finally {
        loadingRetries.value = false;
    }
};

const handleEventFiltersChange = () => {
    fetchEvents(1);
};

const clearEventFilters = () => {
    eventFilters.partner = null;
    eventFilters.site_url = null;
    eventFilters.environment = null;
    eventFilters.forward_status = null;
    eventFilters.search = "";
    fetchEvents(1);
};

const clearEventSelection = () => {
    selectedEvents.value = [];
    selectAllMatchingEvents.value = false;
};

const clearRetrySelection = () => {
    selectedRetries.value = [];
    selectAllMatchingRetries.value = false;
};

const handleEventSelectionChange = () => {
    if (!allPageEventsSelected.value) {
        selectAllMatchingEvents.value = false;
    }
};

const handleRetrySelectionChange = () => {
    if (!allPageRetriesSelected.value) {
        selectAllMatchingRetries.value = false;
    }
};

const buildEventDeletePayload = (ids?: number[]) => {
    if (selectAllMatchingEvents.value) {
        return {
            select_all: true,
            partner: eventFilters.partner || undefined,
            site_url: eventFilters.site_url || undefined,
            environment: eventFilters.environment || undefined,
            forward_status: eventFilters.forward_status || undefined,
            search: eventFilters.search.trim() || undefined,
        };
    }

    return {
        ids: ids ?? selectedEvents.value.map((event) => event.id),
    };
};

const buildRetryDeletePayload = (ids?: number[]) => {
    if (selectAllMatchingRetries.value) {
        return {
            select_all: true,
        };
    }

    return {
        ids: ids ?? selectedRetries.value.map((retry) => retry.id),
    };
};

const deleteEvents = async (ids?: number[]) => {
    deletingEvents.value = true;

    try {
        const { data } = await axios.delete(route("webhooks.deleteEvents"), {
            data: buildEventDeletePayload(ids),
        });

        toast.add({
            severity: "success",
            summary: "Events deleted",
            detail: data.message,
            life: 4000,
        });

        clearEventSelection();
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Delete failed",
            detail: error?.response?.data?.message || "Unable to delete webhook events",
            life: 4000,
        });
    } finally {
        deletingEvents.value = false;
        deletingEventId.value = null;
    }
};

const deleteRetries = async (ids?: number[]) => {
    deletingRetries.value = true;

    try {
        const { data } = await axios.delete(route("webhooks.deleteRetries"), {
            data: buildRetryDeletePayload(ids),
        });

        toast.add({
            severity: "success",
            summary: "Retries deleted",
            detail: data.message,
            life: 4000,
        });

        clearRetrySelection();
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Delete failed",
            detail: error?.response?.data?.message || "Unable to delete retry rows",
            life: 4000,
        });
    } finally {
        deletingRetries.value = false;
        deletingRetryId.value = null;
    }
};

const confirmDeleteEvents = () => {
    const count = selectAllMatchingEvents.value
        ? events.value.total
        : selectedEvents.value.length;

    confirm.require({
        header: count >= (summary.bulk_delete_warning_threshold || 50)
            ? "Delete many webhook events?"
            : "Delete webhook events?",
        message: buildBulkDeleteMessage(
            "webhook event(s) and any linked retry rows",
            count
        ),
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: count >= (summary.bulk_delete_warning_threshold || 50) ? "Delete all" : "Delete",
            severity: "danger",
            size: "small",
        },
        accept: () => deleteEvents(),
    });
};

const confirmDeleteRetries = () => {
    const count = selectAllMatchingRetries.value
        ? retries.value.total
        : selectedRetries.value.length;

    confirm.require({
        header: count >= (summary.bulk_delete_warning_threshold || 50)
            ? "Delete many retry rows?"
            : "Delete retry rows?",
        message: buildBulkDeleteMessage(
            "pending or failed retry row(s)",
            count,
            "Completed retries are not included."
        ),
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
            size: "small",
        },
        accept: () => deleteRetries(),
    });
};

const confirmDeleteSingleEvent = (event: { id: number; consignment_id?: string | null }) => {
    confirm.require({
        header: "Delete webhook event?",
        message: `Delete event for consignment ${event.consignment_id || event.id}? Linked retry rows will also be removed.`,
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            deletingEventId.value = event.id;
            deleteEvents([event.id]);
        },
    });
};

const confirmDeleteSingleRetry = (retry: { id: number; consignment_id?: string | null }) => {
    confirm.require({
        header: "Delete retry row?",
        message: `Delete retry row for consignment ${retry.consignment_id || retry.id}?`,
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            deletingRetryId.value = retry.id;
            deleteRetries([retry.id]);
        },
    });
};

const reloadAll = async () => {
    loading.value = true;

    try {
        await Promise.all([fetchSummary(), fetchEvents(), fetchRetries()]);
    } finally {
        loading.value = false;
    }
};

const onEventPage = (event: { page: number; rows: number }) => {
    if (event.rows !== eventsPerPage.value) {
        eventsPerPage.value = event.rows;
        fetchEvents(1);
        return;
    }

    fetchEvents(event.page + 1);
};

const onRetryPage = (event: { page: number; rows: number }) => {
    if (event.rows !== retriesPerPage.value) {
        retriesPerPage.value = event.rows;
        fetchRetries(1);
        return;
    }

    fetchRetries(event.page + 1);
};

const handleProcessRetries = async () => {
    processingRetries.value = true;

    try {
        const { data } = await axios.post(route("webhooks.processRetries"));
        const recovered = Number(data.recovered?.processed || 0);
        toast.add({
            severity: "success",
            summary: "Waiting events processed",
            detail: `Claimed ${recovered} waiting. Retries processed ${data.result.processed}, succeeded ${data.result.succeeded}, failed ${data.result.failed}`,
            life: 4000,
        });
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Retry processing failed",
            detail: error?.response?.data?.message || "Unable to process retries",
            life: 4000,
        });
    } finally {
        processingRetries.value = false;
    }
};

const handleRetryEvent = async (eventId: number) => {
    retryingEventId.value = eventId;

    try {
        const { data } = await axios.post(route("webhooks.retryEvent", eventId));
        toast.add({
            severity: data.success ? "success" : "warn",
            summary: data.success ? "Forward succeeded" : "Forward failed",
            detail: data.message,
            life: 4000,
        });
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Retry failed",
            detail: error?.response?.data?.message || "Unable to retry event",
            life: 4000,
        });
    } finally {
        retryingEventId.value = null;
    }
};

const handleRetryForward = async (retryId: number) => {
    retryingRetryId.value = retryId;

    try {
        const { data } = await axios.post(route("webhooks.retryForward", retryId));
        toast.add({
            severity: data.success ? "success" : "warn",
            summary: data.success ? "Forward succeeded" : "Forward failed",
            detail: data.message,
            life: 4000,
        });
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Retry failed",
            detail: error?.response?.data?.message || "Unable to retry forward",
            life: 4000,
        });
    } finally {
        retryingRetryId.value = null;
    }
};

const runPluginTest = async (eventId: number) => {
    const { data } = await axios.post(route("webhooks.testPlugin", eventId));
    testResult.value = data;
    testDialogVisible.value = true;

    toast.add({
        severity: data.success ? "success" : "warn",
        summary: data.success ? "Plugin reachable" : "Plugin unreachable",
        detail: data.message,
        life: 4000,
    });
};

const handleTestPlugin = async (eventId: number) => {
    testingEventId.value = eventId;

    try {
        await runPluginTest(eventId);
    } catch (error: any) {
        const responseData = error?.response?.data;
        if (responseData?.result) {
            testResult.value = responseData;
            testDialogVisible.value = true;
        }

        toast.add({
            severity: "error",
            summary: "Plugin test failed",
            detail: responseData?.message || "Unable to test plugin reachability",
            life: 4000,
        });
    } finally {
        testingEventId.value = null;
    }
};

const handleTestRetryPlugin = async (retryId: number, eventId?: number | null) => {
    if (!eventId) {
        toast.add({
            severity: "warn",
            summary: "No linked event",
            detail: "This retry row has no webhook event to test against.",
            life: 4000,
        });
        return;
    }

    testingRetryId.value = retryId;

    try {
        await runPluginTest(eventId);
    } catch (error: any) {
        const responseData = error?.response?.data;
        if (responseData?.result) {
            testResult.value = responseData;
            testDialogVisible.value = true;
        }

        toast.add({
            severity: "error",
            summary: "Plugin test failed",
            detail: responseData?.message || "Unable to test plugin reachability",
            life: 4000,
        });
    } finally {
        testingRetryId.value = null;
    }
};

onMounted(() => {
    reloadAll();
});

watchDebounced(
    () => eventFilters.search,
    () => {
        fetchEvents(1);
    },
    { debounce: 400 },
);

const openCourierTestDialog = () => {
    courierTestResult.value = null;
    courierTestDialogVisible.value = true;
};

const handleCourierPartnerChange = () => {
    const options = courierTestTypeOptionsByPartner[courierTestForm.partner] ?? [];
    courierTestForm.test_type = options[0]?.value ?? "";
};

const handleTestCourierWebhook = async () => {
    testingCourierWebhook.value = true;

    try {
        const { data } = await axios.post(route("webhooks.testWebhook"), {
            partner: courierTestForm.partner,
            test_type: courierTestForm.test_type,
            environment: courierTestForm.environment,
            consignment_id: courierTestForm.consignment_id || undefined,
            invoice: courierTestForm.invoice || undefined,
            auth_token: courierTestForm.auth_token || undefined,
        });

        courierTestResult.value = data;

        toast.add({
            severity: data.success ? "success" : "warn",
            summary: data.success
                ? `${data.partner} webhook test passed`
                : `${data.partner} webhook test completed with issues`,
            detail: data.message,
            life: 5000,
        });

        await reloadAll();
    } catch (error: any) {
        const responseData = error?.response?.data;
        if (responseData) {
            courierTestResult.value = responseData;
        }

        toast.add({
            severity: "error",
            summary: "Courier webhook test failed",
            detail: responseData?.message || "Unable to run courier webhook test",
            life: 5000,
        });
    } finally {
        testingCourierWebhook.value = false;
    }
};
</script>

<style scoped>
@media (max-width: 767px) {
    .webhook-activity-table :deep(.p-datatable-thead > tr > th),
    .webhook-activity-table :deep(.p-datatable-tbody > tr > td) {
        padding: 0.65rem 0.55rem;
    }

    .webhook-activity-table :deep(.p-paginator) {
        flex-wrap: wrap;
        justify-content: center;
        row-gap: 0.35rem;
        padding: 0.75rem 0.5rem;
    }
}
</style>
