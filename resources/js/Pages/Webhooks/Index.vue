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
                        label="Test Courier Webhook"
                        icon="pi pi-send"
                        severity="info"
                        size="small"
                        @click="openCourierTestDialog"
                    />
                    <Button
                        label="Process Due Retries"
                        icon="pi pi-play"
                        severity="help"
                        size="small"
                        :loading="processingRetries"
                        @click="handleProcessRetries"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
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
                        :value="summary.total_events"
                        icon="PhListBullets"
                        :subtitle="eventsStatSubtitle"
                    />
                    <StatCard
                        title="Forwarded"
                        :value="summary.success_count"
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
                        :subtitle="`${summary.retry_queued_count} queued events`"
                        accent-class="bg-amber-500"
                        icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                        icon-class="text-amber-600 dark:text-amber-400"
                    />
                    <StatCard
                        title="Failed / Orphan"
                        :value="summary.failed_count + summary.orphan_count"
                        icon="PhWarningCircle"
                        :subtitle="`${summary.failed_retries} failed retries`"
                        accent-class="bg-rose-500"
                        icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                        icon-class="text-rose-600 dark:text-rose-400"
                    />
                </template>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-gray-700 dark:bg-slate-900/60">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-colors"
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
                        {{ summary.total_events }}
                    </span>
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-colors"
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
                        {{ summary.pending_retries + summary.failed_retries }}
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
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <Dropdown
                            v-model="eventFilters.partner"
                            :options="partnerOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All partners"
                            class="w-[150px]"
                            showClear
                            @change="handleEventFiltersChange"
                        />
                        <Dropdown
                            v-model="eventFilters.environment"
                            :options="environmentOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All environments"
                            class="w-[150px]"
                            showClear
                            @change="handleEventFiltersChange"
                        />
                        <Dropdown
                            v-model="eventFilters.forward_status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="All statuses"
                            class="w-[170px]"
                            showClear
                            @change="handleEventFiltersChange"
                        />
                        <Dropdown
                            v-model="eventFilters.source"
                            :options="sourceOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-[170px]"
                            @change="handleEventFiltersChange"
                        />
                        <span class="relative w-full min-w-[220px] flex-1 sm:max-w-xs">
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
                            text
                            size="small"
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
                    <TableSkeletonLoader :columns="eventSkeletonColumns" :rows="7" />
                </div>

                <DataTable
                    v-else-if="events.data.length"
                    v-model:selection="selectedEvents"
                    :value="events.data"
                    dataKey="id"
                    :rows="events.per_page"
                    :totalRecords="events.total"
                    :first="(events.current_page - 1) * events.per_page"
                    lazy
                    paginator
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table text-sm"
                    @page="onEventPage"
                    @update:selection="handleEventSelectionChange"
                >
                    <Column selectionMode="multiple" headerStyle="width:3rem" />
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ (events.current_page - 1) * events.per_page + slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column header="Received" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="font-medium text-gray-800 dark:text-gray-100">
                                {{ formatDate(data.created_at) }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Partner" style="min-width: 8rem">
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
                    <Column header="Shipment" style="min-width: 10rem">
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.consignment_id || "—" }}</div>
                            <div class="text-xs text-gray-500">
                                Order #{{ data.wc_order_id || "—" }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Site" style="min-width: 11rem">
                        <template #body="{ data }">
                            <span class="line-clamp-2 break-all text-xs text-gray-600 dark:text-gray-300">
                                {{ data.site_url || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Event" style="min-width: 8rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-mono text-xs">{{ data.event_type || "—" }}</span>
                                <Tag
                                    v-if="data.is_admin_test"
                                    value="Admin test"
                                    severity="info"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="Forward Status" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.forward_status"
                                :severity="statusSeverity(data.forward_status)"
                            />
                        </template>
                    </Column>
                    <Column header="Message" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-xs text-gray-600 dark:text-gray-300">
                                {{ data.forward_message || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Action" headerStyle="width:9rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    v-if="canTestPlugin(data)"
                                    icon="pi pi-link"
                                    size="small"
                                    text
                                    rounded
                                    v-tooltip.top="'Test plugin'"
                                    :loading="testingEventId === data.id"
                                    @click="handleTestPlugin(data.id)"
                                />
                                <Button
                                    v-if="canRetryEvent(data)"
                                    icon="pi pi-replay"
                                    size="small"
                                    text
                                    rounded
                                    severity="warn"
                                    v-tooltip.top="'Retry forward'"
                                    :loading="retryingEventId === data.id"
                                    @click="handleRetryEvent(data.id)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    rounded
                                    severity="danger"
                                    v-tooltip.top="'Delete event'"
                                    :loading="deletingEventId === data.id"
                                    @click="confirmDeleteSingleEvent(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

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
                    <TableSkeletonLoader :columns="retrySkeletonColumns" :rows="5" />
                </div>

                <DataTable
                    v-else-if="retries.data.length"
                    v-model:selection="selectedRetries"
                    :value="retries.data"
                    dataKey="id"
                    :rows="retries.per_page"
                    :totalRecords="retries.total"
                    :first="(retries.current_page - 1) * retries.per_page"
                    lazy
                    paginator
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table text-sm"
                    @page="onRetryPage"
                    @update:selection="handleRetrySelectionChange"
                >
                    <Column selectionMode="multiple" headerStyle="width:3rem" />
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ (retries.current_page - 1) * retries.per_page + slotProps.index + 1 }}
                        </template>
                    </Column>
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
                        </template>
                    </Column>
                    <Column header="Consignment" field="consignment_id" style="min-width: 9rem" />
                    <Column header="Site" style="min-width: 11rem">
                        <template #body="{ data }">
                            <span class="line-clamp-2 break-all text-xs">{{ data.site_url || "—" }}</span>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="retrySeverity(data.status)" />
                        </template>
                    </Column>
                    <Column header="Attempts" style="min-width: 6rem">
                        <template #body="{ data }">
                            {{ data.attempts }} / {{ data.max_attempts }}
                        </template>
                    </Column>
                    <Column header="Next Retry" style="min-width: 9rem">
                        <template #body="{ data }">
                            {{ data.next_retry_at ? formatDate(data.next_retry_at) : "—" }}
                        </template>
                    </Column>
                    <Column header="Last Error" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-xs text-rose-600 dark:text-rose-400">
                                {{ data.last_error || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Action" headerStyle="width:9rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    icon="pi pi-link"
                                    size="small"
                                    text
                                    rounded
                                    v-tooltip.top="'Test plugin'"
                                    :loading="testingRetryId === data.id"
                                    @click="handleTestRetryPlugin(data.id, data.event_id)"
                                />
                                <Button
                                    v-if="data.status !== 'completed'"
                                    icon="pi pi-replay"
                                    size="small"
                                    text
                                    rounded
                                    severity="warn"
                                    v-tooltip.top="'Retry now'"
                                    :loading="retryingRetryId === data.id"
                                    @click="handleRetryForward(data.id)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    size="small"
                                    text
                                    rounded
                                    severity="danger"
                                    v-tooltip.top="'Delete retry'"
                                    :loading="deletingRetryId === data.id"
                                    @click="confirmDeleteSingleRetry(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhCheckCircle"
                    title="No pending retries"
                    description="All webhook forwards are up to date"
                />
            </PageCard>
        </div>

        <Dialog
            v-model:visible="testDialogVisible"
            modal
            header="Plugin Reachability Test"
            :style="{ width: '34rem' }"
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
        </Dialog>

        <Dialog
            v-model:visible="courierTestDialogVisible"
            modal
            header="Test Courier Webhook"
            :style="{ width: '36rem' }"
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
                    :loading="testingCourierWebhook"
                    @click="handleTestCourierWebhook"
                />
            </template>
        </Dialog>

        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatCardSkeleton from "@/Pages/Users/fragments/StatCardSkeleton.vue";
import TableSkeletonLoader from "@/Pages/Users/fragments/TableSkeletonLoader.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

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
    total_events: 0,
    success_count: 0,
    failed_count: 0,
    retry_queued_count: 0,
    orphan_count: 0,
    admin_test_count: 0,
    pending_retries: 0,
    failed_retries: 0,
    last_event_at: null as string | null,
    bulk_delete_warning_threshold: 50,
});

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
    environment: null as string | null,
    forward_status: null as string | null,
    source: "courier",
    search: "",
});

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
    { label: "Received", value: "received" },
    { label: "Success", value: "success" },
    { label: "Retry Queued", value: "retry_queued" },
    { label: "Failed", value: "failed" },
    { label: "Orphan", value: "orphan" },
];

const sourceOptions = [
    { label: "Live webhooks", value: "courier" },
    { label: "Admin tests", value: "admin_test" },
    { label: "All sources", value: "all" },
];

const eventSkeletonColumns = [
    { width: "1.25rem", variant: "checkbox" as const },
    { width: "2rem", variant: "bar" as const },
    { width: "7rem", variant: "bar" as const },
    { width: "5.5rem", variant: "stack" as const, subWidth: "3.5rem" },
    { width: "6.5rem", variant: "stack" as const, subWidth: "4rem" },
    { width: "8rem", variant: "bar" as const, heightClass: "h-3" },
    { width: "5rem", variant: "bar" as const },
    { width: "4.5rem", variant: "badge" as const },
    { width: "7rem", variant: "bar" as const, heightClass: "h-3" },
    { width: "5.5rem", variant: "actions" as const },
];

const retrySkeletonColumns = [
    { width: "1.25rem", variant: "checkbox" as const },
    { width: "2rem", variant: "bar" as const },
    { width: "4.5rem", variant: "badge" as const },
    { width: "6rem", variant: "bar" as const },
    { width: "8rem", variant: "bar" as const, heightClass: "h-3" },
    { width: "4rem", variant: "badge" as const },
    { width: "3.5rem", variant: "bar" as const },
    { width: "6rem", variant: "bar" as const },
    { width: "7rem", variant: "bar" as const, heightClass: "h-3" },
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
            eventFilters.environment ||
            eventFilters.forward_status ||
            eventFilters.source !== "courier" ||
            eventFilters.search.trim()
    );
});

const eventsDescription = computed(() => {
    if (hasActiveEventFilters.value) {
        return "Filtered webhook events and WordPress forward results";
    }

    return "Inbound courier webhooks and WordPress forward results";
});

const eventsStatSubtitle = computed(() => {
    if (summary.last_event_at) {
        const adminTests =
            summary.admin_test_count > 0
                ? ` · ${summary.admin_test_count} admin test(s) hidden`
                : "";

        return `Last: ${formatDate(summary.last_event_at)}${adminTests}`;
    }

    return summary.admin_test_count > 0
        ? `${summary.admin_test_count} admin test(s) hidden from totals`
        : "No live events yet";
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

const statusSeverity = (status: string) => {
    switch (status) {
        case "success":
            return "success";
        case "retry_queued":
            return "warn";
        case "failed":
        case "orphan":
            return "danger";
        default:
            return "secondary";
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

const fetchSummary = async () => {
    const { data } = await axios.get(route("webhooks.summary"));
    Object.assign(summary, data);
};

const fetchEvents = async (page = events.value.current_page) => {
    loadingEvents.value = true;

    try {
        const { data } = await axios.get(route("webhooks.events"), {
            params: {
                page,
                partner: eventFilters.partner,
                environment: eventFilters.environment,
                forward_status: eventFilters.forward_status,
                source: eventFilters.source,
                search: eventFilters.search || undefined,
            },
        });
        events.value = data;
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
        const { data } = await axios.get(route("webhooks.retries"), { params: { page } });
        retries.value = data;
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
    eventFilters.environment = null;
    eventFilters.forward_status = null;
    eventFilters.source = "courier";
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
            environment: eventFilters.environment || undefined,
            forward_status: eventFilters.forward_status || undefined,
            source: eventFilters.source || undefined,
            search: eventFilters.search || undefined,
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
        rejectLabel: "Cancel",
        acceptLabel: count >= (summary.bulk_delete_warning_threshold || 50) ? "Delete all" : "Delete",
        acceptClass: "p-button-danger",
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
        rejectLabel: "Cancel",
        acceptLabel: "Delete",
        acceptClass: "p-button-danger",
        accept: () => deleteRetries(),
    });
};

const confirmDeleteSingleEvent = (event: { id: number; consignment_id?: string | null }) => {
    confirm.require({
        header: "Delete webhook event?",
        message: `Delete event for consignment ${event.consignment_id || event.id}? Linked retry rows will also be removed.`,
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Cancel",
        acceptLabel: "Delete",
        acceptClass: "p-button-danger",
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
        rejectLabel: "Cancel",
        acceptLabel: "Delete",
        acceptClass: "p-button-danger",
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

const onEventPage = (event: { page: number }) => {
    fetchEvents(event.page + 1);
};

const onRetryPage = (event: { page: number }) => {
    fetchRetries(event.page + 1);
};

const handleProcessRetries = async () => {
    processingRetries.value = true;

    try {
        const { data } = await axios.post(route("webhooks.processRetries"));
        toast.add({
            severity: "success",
            summary: "Retries processed",
            detail: `Processed ${data.result.processed}, succeeded ${data.result.succeeded}, failed ${data.result.failed}`,
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
