<template>
    <AuthenticatedLayout title="Visitors">
        <div class="min-w-0 space-y-5">
            <PageHeader
                title="Visitors"
                description="Public marketing & SEO traffic, engagement, sources, and tool conversions"
                icon="PhUsers"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            />

            <div
                class="box-bg box-color box-border overflow-hidden rounded-2xl border px-3 py-3 shadow-sm sm:px-4"
            >
                <div class="flex flex-col gap-3">
                    <div class="min-w-0 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <SelectButton
                            v-model="rangePreset"
                            :options="rangePresets"
                            option-label="label"
                            option-value="value"
                            :allow-empty="false"
                            class="visitors-range-select inline-flex w-max max-w-none"
                            @change="applyPreset"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                        <div class="min-w-0">
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-500">From</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                                @change="onCustomDate"
                            />
                        </div>
                        <div class="min-w-0">
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-500">To</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                                @change="onCustomDate"
                            />
                        </div>
                        <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap sm:justify-end lg:col-span-1">
                            <Button
                                label="Refresh"
                                icon="pi pi-refresh"
                                severity="secondary"
                                outlined
                                size="small"
                                class="!justify-center [&_.p-button-label]:sr-only sm:[&_.p-button-label]:not-sr-only sm:[&_.p-button-label]:inline"
                                :loading="loading"
                                aria-label="Refresh"
                                @click="fetchReport"
                            />
                            <Button
                                label="Sync GSC"
                                icon="pi pi-google"
                                severity="secondary"
                                outlined
                                size="small"
                                class="!justify-center [&_.p-button-label]:sr-only sm:[&_.p-button-label]:not-sr-only sm:[&_.p-button-label]:inline"
                                :loading="syncingGsc"
                                aria-label="Sync Google Search Console SEO"
                                v-tooltip.top="'Sync Google Search Console SEO'"
                                @click="syncGsc"
                            />
                            <Button
                                label="Export"
                                icon="pi pi-download"
                                severity="secondary"
                                outlined
                                size="small"
                                class="!justify-center [&_.p-button-label]:sr-only sm:[&_.p-button-label]:not-sr-only sm:[&_.p-button-label]:inline"
                                :disabled="!rows.length"
                                aria-label="Export CSV"
                                @click="exportCsv"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="pathFilter"
                class="flex flex-col gap-3 rounded-xl border border-sky-200/80 bg-sky-50/80 px-4 py-3 text-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between dark:border-sky-500/30 dark:bg-sky-500/10"
            >
                <div class="flex min-w-0 items-start gap-2 text-sky-900 dark:text-sky-100 sm:items-center">
                    <i class="pi pi-filter mt-0.5 shrink-0 text-xs sm:mt-0" />
                    <div class="min-w-0">
                        <span class="mr-2">Scoped to</span>
                        <code class="inline-block max-w-full break-all rounded bg-white/80 px-2 py-0.5 font-mono text-xs dark:bg-slate-900/60">{{ pathFilter }}</code>
                    </div>
                </div>
                <Button label="Clear path filter" size="small" text class="!justify-start sm:!justify-center" @click="clearPathFilter" />
            </div>

            <!-- Primary KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template v-if="loading && !overview">
                    <StatCardSkeleton v-for="index in 4" :key="`kpi-a-${index}`" :delay="index * 60" />
                </template>
                <template v-else>
                    <StatCard
                        title="Unique visitors"
                        :value="overview?.visitors ?? 0"
                        icon="PhUsers"
                        :subtitle="`${rangeDaysLabel} day range`"
                    />
                    <StatCard
                        title="Pageviews"
                        :value="overview?.pageviews ?? 0"
                        icon="PhEye"
                        :subtitle="`${overview?.pages_per_visitor ?? 0} per visitor`"
                        accent-class="bg-sky-500"
                        icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                        icon-class="text-sky-600 dark:text-sky-400"
                    />
                    <StatCard
                        title="Sessions"
                        :value="overview?.sessions ?? 0"
                        icon="PhBrowsers"
                        :subtitle="`${overview?.pages_per_session ?? 0} pages / session`"
                        accent-class="bg-indigo-500"
                        icon-bg-class="bg-indigo-50 dark:bg-indigo-500/15"
                        icon-class="text-indigo-600 dark:text-indigo-400"
                    />
                    <StatCard
                        title="Avg engaged time"
                        :value="formatDuration(overview?.avg_engaged_ms ?? 0)"
                        icon="PhTimer"
                        subtitle="Max time per session, averaged"
                        accent-class="bg-amber-500"
                        icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                        icon-class="text-amber-600 dark:text-amber-400"
                    />
                </template>
            </div>

            <!-- Conversion / depth KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template v-if="loading && !overview">
                    <StatCardSkeleton v-for="index in 4" :key="`kpi-b-${index}`" :delay="index * 60" />
                </template>
                <template v-else>
                    <StatCard
                        title="CTA clicks"
                        :value="overview?.cta_clicks ?? 0"
                        icon="PhCursorClick"
                        :subtitle="`${overview?.cta_rate ?? 0}% of pageviews`"
                        accent-class="bg-emerald-500"
                        icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                        icon-class="text-emerald-600 dark:text-emerald-400"
                    />
                    <StatCard
                        title="Tool actions"
                        :value="overview?.tool_actions ?? 0"
                        icon="PhWrench"
                        :subtitle="`${overview?.tool_rate ?? 0}% of pageviews`"
                        accent-class="bg-fuchsia-500"
                        icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                        icon-class="text-fuchsia-600 dark:text-fuchsia-400"
                    />
                    <StatCard
                        title="Scroll ≥50%"
                        :value="overview?.scroll_50 ?? 0"
                        icon="PhArrowFatLinesDown"
                        :subtitle="`${overview?.scroll_50_rate ?? 0}% depth rate`"
                        accent-class="bg-cyan-500"
                        icon-bg-class="bg-cyan-50 dark:bg-cyan-500/15"
                        icon-class="text-cyan-600 dark:text-cyan-400"
                    />
                    <StatCard
                        title="Pages tracked"
                        :value="overview?.pages_tracked ?? 0"
                        icon="PhFiles"
                        subtitle="Distinct URLs with traffic"
                        accent-class="bg-slate-500"
                        icon-bg-class="bg-slate-100 dark:bg-slate-500/15"
                        icon-class="text-slate-600 dark:text-slate-300"
                    />
                </template>
            </div>

            <!-- Insight panels -->
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <PageCard title="Traffic sources" description="Share of pageviews by channel" class="xl:col-span-1">
                    <div v-if="insights?.sources?.length" class="space-y-3">
                        <div v-for="src in insights.sources" :key="src.source_channel" class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <StatusBadge :label="formatChannel(src.source_channel)" :variant="channelVariant(src.source_channel)" format="none" />
                                <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ src.pageviews }}</span>
                                    · {{ src.share }}%
                                </div>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-violet-500 transition-all" :style="{ width: `${Math.min(100, src.share || 0)}%` }" />
                            </div>
                            <div class="flex justify-between text-[11px] text-slate-500">
                                <span>{{ src.unique_visitors }} visitors</span>
                                <span>{{ src.cta_clicks }} CTAs · {{ src.cta_rate }}%</span>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else icon="PhGlobe" title="No source data" description="Sources appear after public pageviews are tracked" />
                </PageCard>

                <PageCard title="Devices" description="Desktop / mobile / tablet mix" class="xl:col-span-1">
                    <div v-if="insights?.devices?.length" class="space-y-3">
                        <div v-for="device in insights.devices" :key="device.device_type" class="space-y-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium capitalize text-slate-800 dark:text-slate-100">{{ device.device_type }}</span>
                                <span class="text-xs text-slate-500">{{ device.share }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="deviceBarClass(device.device_type)"
                                    :style="{ width: `${Math.min(100, device.share || 0)}%` }"
                                />
                            </div>
                            <div class="flex justify-between text-[11px] text-slate-500">
                                <span>{{ device.pageviews }} views</span>
                                <span>{{ device.unique_visitors }} visitors · {{ device.cta_clicks }} CTAs</span>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else icon="PhDeviceMobile" title="No device data" description="Device split fills in with traffic" />
                </PageCard>

                <PageCard title="Daily trend" :description="`${insights?.daily?.length || 0} days`" class="xl:col-span-1">
                    <div v-if="insights?.daily?.length" class="space-y-3">
                        <div class="overflow-x-auto">
                            <div class="flex h-28 min-w-[16rem] items-end gap-1">
                                <div
                                    v-for="day in insights.daily"
                                    :key="day.date"
                                    class="group relative min-w-[4px] flex-1 rounded-t bg-sky-500/80 transition hover:bg-sky-500"
                                    :style="{ height: `${dailyBarHeight(day.pageviews)}%` }"
                                    :title="`${day.date}: ${day.pageviews} views, ${day.unique_visitors} visitors`"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-[11px] text-slate-500">
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ dailyTotals.pageviews }}</div>
                                <div>Views</div>
                            </div>
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ dailyTotals.visitors }}</div>
                                <div>Visitor-days</div>
                            </div>
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ dailyTotals.ctas }}</div>
                                <div>CTAs</div>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else icon="PhChartLineUp" title="No daily data" description="Trend builds as events arrive" />
                </PageCard>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <PageCard title="Top referrers" description="Where visitors came from" no-padding>
                    <div v-if="insights?.referrers?.length" class="overflow-x-auto">
                    <DataTable
                        :value="insights.referrers"
                        class="professional-table min-w-[22rem] text-sm"
                        :rows="8"
                    >
                        <Column header="Referrer">
                            <template #body="{ data }">
                                <span class="font-mono text-xs">{{ data.referrer_host }}</span>
                            </template>
                        </Column>
                        <Column field="pageviews" header="Views" />
                        <Column field="unique_visitors" header="Visitors" />
                    </DataTable>
                    </div>
                    <div v-else class="p-5">
                        <EmptyState icon="PhLink" title="No referrers yet" description="External hosts appear here" />
                    </div>
                </PageCard>

                <PageCard title="UTM campaigns" description="Paid / campaign tagged traffic" no-padding>
                    <div v-if="insights?.campaigns?.length" class="overflow-x-auto">
                    <DataTable
                        :value="insights.campaigns"
                        class="professional-table min-w-[22rem] text-sm"
                        :rows="8"
                    >
                        <Column header="Campaign">
                            <template #body="{ data }">
                                <div class="font-medium">{{ data.utm_campaign }}</div>
                                <div class="text-[11px] text-slate-500">{{ data.utm_source }} / {{ data.utm_medium }}</div>
                            </template>
                        </Column>
                        <Column field="pageviews" header="Views" />
                        <Column field="cta_clicks" header="CTAs" />
                    </DataTable>
                    </div>
                    <div v-else class="p-5">
                        <EmptyState icon="PhMegaphone" title="No UTM traffic" description="Add ?utm_source=&utm_campaign= to campaign links" />
                    </div>
                </PageCard>
            </div>

            <!-- SEO / keywords -->
            <PageCard
                title="SEO keywords & opportunities"
                :description="seoDescription"
            >
                <div
                    v-if="!seo?.configured"
                    class="mb-4 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    Connect Google Search Console in System Maintenance, then click <strong>Sync GSC SEO</strong> to pull top keywords, CTR gaps, and landing-page ranks.
                </div>
                <div
                    v-else-if="seo?.configured && !(seo?.top_keywords || []).length"
                    class="mb-4 rounded-xl border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400"
                >
                    GSC is connected but no keyword rows yet. Click <strong>Sync GSC SEO</strong> (or wait for the nightly sync).
                </div>

                <div
                    v-if="seoSummaryChips.length"
                    class="mb-4 flex flex-wrap gap-1.5"
                >
                    <span
                        v-for="chip in seoSummaryChips"
                        :key="chip.key"
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="chip.className"
                    >
                        {{ chip.label }} {{ chip.count }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">Top GSC keywords</h4>
                        <p class="mb-3 text-[11px] text-slate-500">Last 28 days · impressions & clicks</p>
                        <div v-if="seo?.top_keywords?.length" class="max-h-80 space-y-2 overflow-auto">
                            <div
                                v-for="(kw, idx) in seo.top_keywords"
                                :key="`${kw.query}-${idx}`"
                                class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700"
                            >
                                <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ kw.query }}</div>
                                <div class="mt-0.5 text-[11px] text-slate-500">
                                    impr {{ kw.impressions_28d }} · clicks {{ kw.clicks_28d }} · CTR {{ formatPct(kw.ctr_28d) }} · pos {{ formatNum(kw.position_28d) }}
                                </div>
                            </div>
                        </div>
                        <EmptyState v-else icon="PhMagnifyingGlass" title="No GSC keywords" description="Sync Search Console to populate" />
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">Rank opportunities</h4>
                        <p class="mb-3 text-[11px] text-slate-500">What to improve first for SEO</p>
                        <div v-if="seo?.opportunities?.length" class="max-h-80 space-y-2 overflow-auto">
                            <div
                                v-for="(item, idx) in seo.opportunities"
                                :key="`${item.query}-${item.path}-${idx}`"
                                class="rounded-xl border border-slate-200 px-3 py-2.5 dark:border-slate-700"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.query }}</div>
                                        <button
                                            type="button"
                                            class="mt-0.5 font-mono text-[11px] text-sky-600 hover:underline dark:text-sky-400"
                                            @click="filterByPath(item.path)"
                                        >
                                            {{ item.path }}
                                        </button>
                                    </div>
                                    <StatusBadge :label="item.bucket_label || item.bucket" :variant="bucketVariant(item.bucket)" format="none" />
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500">
                                    pos {{ formatNum(item.position_28d) }} · impr {{ item.impressions_28d }} · CTR {{ formatPct(item.ctr_28d) }}
                                </p>
                                <p v-if="item.improvement_hint" class="mt-1 text-[11px] text-slate-600 dark:text-slate-300">
                                    {{ item.improvement_hint }}
                                </p>
                            </div>
                        </div>
                        <EmptyState v-else icon="PhTarget" title="No opportunities yet" description="Sync GSC after pages have search impressions" />
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">GSC landing pages</h4>
                        <p class="mb-3 text-[11px] text-slate-500">Organic entry URLs by impressions</p>
                        <div v-if="seo?.landing_pages?.length" class="max-h-80 space-y-2 overflow-auto">
                            <div
                                v-for="page in seo.landing_pages"
                                :key="page.path"
                                class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700"
                            >
                                <button
                                    type="button"
                                    class="font-mono text-xs text-sky-600 hover:underline dark:text-sky-400"
                                    @click="filterByPath(page.path)"
                                >
                                    {{ page.path }}
                                </button>
                                <div class="mt-0.5 text-[11px] text-slate-500">
                                    impr {{ page.impressions_28d }} · clicks {{ page.clicks_28d }} · CTR {{ formatPct(page.ctr_28d) }} · pos {{ formatNum(page.position_28d) }}
                                </div>
                            </div>
                        </div>
                        <EmptyState v-else icon="PhFiles" title="No GSC pages" description="Landing pages appear after sync" />
                    </div>
                </div>

                <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <h4 class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">First-party keyword signals</h4>
                    <p class="mb-3 text-[11px] text-slate-500">
                        From <code class="text-[10px]">utm_term</code> and rare search-referrer query params (Google rarely exposes these anymore)
                    </p>
                    <div v-if="insights?.first_party_keywords?.length" class="overflow-x-auto">
                    <DataTable
                        :value="insights.first_party_keywords"
                        class="professional-table min-w-[22rem] text-sm"
                        :rows="10"
                    >
                        <Column field="keyword" header="Keyword" />
                        <Column field="hits" header="Hits" />
                        <Column field="unique_visitors" header="Visitors" />
                        <Column field="pages" header="Pages" />
                    </DataTable>
                    </div>
                    <EmptyState
                        v-else
                        icon="PhTag"
                        title="No tagged keywords yet"
                        description="Use utm_term= on ads/campaign links to capture intent keywords here"
                    />
                </div>
            </PageCard>

            <!-- Detailed report table -->
            <PageCard
                :title="currentReportLabel"
                :description="tableDescription"
                no-padding
            >
                <div class="flex flex-col gap-3 border-b border-slate-100 px-3 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-4 dark:border-slate-800">
                    <div class="min-w-0 w-full sm:w-auto">
                        <Dropdown
                            v-model="reportType"
                            :options="reportTypes"
                            option-label="label"
                            option-value="value"
                            class="w-full md:hidden"
                            @update:model-value="fetchReport"
                        />
                        <div class="hidden min-w-0 overflow-x-auto pb-0.5 md:block [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <SelectButton
                                v-model="reportType"
                                :options="reportTypes"
                                option-label="label"
                                option-value="value"
                                :allow-empty="false"
                                class="inline-flex w-max max-w-none"
                                @change="fetchReport"
                            />
                        </div>
                    </div>
                    <div class="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <IconField class="w-full sm:w-auto">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="tableSearch"
                                placeholder="Filter table…"
                                class="w-full text-sm sm:w-48"
                            />
                        </IconField>
                        <span
                            v-if="meta?.by_path_source"
                            class="text-[11px] text-slate-400"
                        >
                            Data: {{ meta.by_path_source === 'daily_stats' ? 'rollup + events' : 'live events' }}
                        </span>
                    </div>
                </div>

                <div v-if="loading" class="border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <i class="pi pi-spin pi-spinner" />
                        Loading detailed report…
                    </div>
                    <TableSkeletonLoader :columns="skeletonColumns" :rows="6" />
                </div>

                <div v-else-if="filteredRows.length" class="overflow-x-auto">
                <DataTable
                    :value="filteredRows"
                    :rows="rowsPerPage"
                    paginator
                    sortMode="single"
                    removableSort
                    paginatorTemplate="RowsPerPageDropdown PrevPageLink CurrentPageReport NextPageLink"
                    :rowsPerPageOptions="[10, 15, 25, 50]"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table min-w-[40rem] text-sm visitors-report-table"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>

                    <Column
                        v-if="['by_path', 'engagement', 'actions'].includes(reportType)"
                        field="path"
                        header="Path"
                        sortable
                    >
                        <template #body="{ data }">
                            <button
                                type="button"
                                class="font-mono text-xs text-sky-600 hover:underline dark:text-sky-400"
                                @click="filterByPath(data.path)"
                            >
                                {{ data.path }}
                            </button>
                        </template>
                    </Column>

                    <Column v-if="reportType === 'by_source'" field="source_channel" header="Source" sortable>
                        <template #body="{ data }">
                            <StatusBadge :label="formatChannel(data.source_channel)" :variant="channelVariant(data.source_channel)" format="none" />
                        </template>
                    </Column>
                    <Column v-if="reportType === 'devices'" field="device_type" header="Device" sortable>
                        <template #body="{ data }">
                            <span class="capitalize">{{ data.device_type }}</span>
                        </template>
                    </Column>
                    <Column v-if="reportType === 'daily'" field="date" header="Date" sortable />
                    <Column v-if="reportType === 'referrers'" field="referrer_host" header="Referrer" sortable>
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{ data.referrer_host }}</span>
                        </template>
                    </Column>
                    <Column v-if="reportType === 'keywords'" field="keyword" header="Keyword" sortable />
                    <Column v-if="reportType === 'keywords'" field="hits" header="Hits" sortable />
                    <Column v-if="reportType === 'keywords'" field="unique_visitors" header="Visitors" sortable />
                    <Column v-if="reportType === 'keywords'" field="pages" header="Pages" sortable />

                    <Column
                        v-if="['by_path', 'by_source', 'devices', 'daily', 'referrers', 'engagement'].includes(reportType)"
                        field="pageviews"
                        header="Pageviews"
                        sortable
                    />
                    <Column
                        v-if="['by_path', 'by_source', 'devices', 'daily', 'referrers'].includes(reportType)"
                        field="unique_visitors"
                        header="Visitors"
                        sortable
                    />
                    <Column v-if="reportType === 'by_path'" field="sessions" header="Sessions" sortable />
                    <Column v-if="reportType === 'by_path'" field="pages_per_visitor" header="Views/visitor" sortable />
                    <Column
                        v-if="['by_path', 'by_source', 'devices', 'daily'].includes(reportType)"
                        field="cta_clicks"
                        header="CTAs"
                        sortable
                    />
                    <Column v-if="reportType === 'by_source'" field="tool_actions" header="Tools" sortable />
                    <Column v-if="['by_path', 'by_source', 'devices'].includes(reportType)" field="cta_rate" header="CTA %" sortable>
                        <template #body="{ data }">{{ data.cta_rate ?? 0 }}%</template>
                    </Column>
                    <Column v-if="['by_source', 'devices'].includes(reportType)" field="share" header="Share" sortable>
                        <template #body="{ data }">{{ data.share ?? 0 }}%</template>
                    </Column>
                    <Column v-if="reportType === 'by_path'" field="scroll_50" header="Scroll 50%" sortable />
                    <Column v-if="reportType === 'by_path'" field="scroll_50_rate" header="Scroll %" sortable>
                        <template #body="{ data }">{{ data.scroll_50_rate ?? 0 }}%</template>
                    </Column>
                    <Column v-if="reportType === 'by_path'" field="avg_engaged_ms" header="Avg engaged" sortable>
                        <template #body="{ data }">{{ formatDuration(data.avg_engaged_ms) }}</template>
                    </Column>

                    <Column v-if="reportType === 'engagement'" field="avg_engaged_ms" header="Avg engaged" sortable>
                        <template #body="{ data }">{{ formatDuration(data.avg_engaged_ms) }}</template>
                    </Column>
                    <Column v-if="reportType === 'engagement'" field="max_engaged_ms" header="Max engaged" sortable>
                        <template #body="{ data }">{{ formatDuration(data.max_engaged_ms) }}</template>
                    </Column>
                    <Column v-if="reportType === 'engagement'" field="scroll_25_rate" header="25%" sortable>
                        <template #body="{ data }">{{ data.scroll_25_rate }}%</template>
                    </Column>
                    <Column v-if="reportType === 'engagement'" field="scroll_50_rate" header="50%" sortable>
                        <template #body="{ data }">{{ data.scroll_50_rate }}%</template>
                    </Column>
                    <Column v-if="reportType === 'engagement'" field="scroll_75_rate" header="75%" sortable>
                        <template #body="{ data }">{{ data.scroll_75_rate }}%</template>
                    </Column>
                    <Column v-if="reportType === 'engagement'" field="scroll_90_rate" header="90%" sortable>
                        <template #body="{ data }">{{ data.scroll_90_rate }}%</template>
                    </Column>

                    <Column v-if="reportType === 'daily'" field="tool_actions" header="Tools" sortable />

                    <Column v-if="reportType === 'actions'" field="event_type" header="Type" sortable>
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.event_type === 'tool_action' ? 'Tool' : 'CTA'"
                                :variant="data.event_type === 'tool_action' ? 'info' : 'success'"
                                format="none"
                            />
                        </template>
                    </Column>
                    <Column v-if="reportType === 'actions'" field="label" header="Label" sortable />
                    <Column v-if="reportType === 'actions'" field="total" header="Total" sortable />
                    <Column v-if="reportType === 'actions'" field="unique_visitors" header="Unique" sortable />
                </DataTable>
                </div>

                <EmptyState
                    v-else
                    icon="PhUsers"
                    title="No rows for this report"
                    description="Try another date range, clear the path filter, or switch report type"
                />
            </PageCard>

            <PageCard
                v-if="insights?.top_actions?.length"
                title="Top conversions"
                description="Highest CTA and tool actions in this range"
                no-padding
            >
                <div class="overflow-x-auto">
                <DataTable :value="insights.top_actions" class="professional-table min-w-[28rem] text-sm" :rows="8">
                    <Column field="path" header="Path">
                        <template #body="{ data }">
                            <button type="button" class="font-mono text-xs text-sky-600 hover:underline dark:text-sky-400" @click="filterByPath(data.path)">
                                {{ data.path }}
                            </button>
                        </template>
                    </Column>
                    <Column field="event_type" header="Type">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.event_type === 'tool_action' ? 'Tool' : 'CTA'"
                                :variant="data.event_type === 'tool_action' ? 'info' : 'success'"
                                format="none"
                            />
                        </template>
                    </Column>
                    <Column field="label" header="Label" />
                    <Column field="total" header="Total" />
                    <Column field="unique_visitors" header="Unique" />
                </DataTable>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { AuthenticatedLayout } from '@/layouts';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import StatCard from '@/Pages/Users/fragments/StatCard.vue';
import StatCardSkeleton from '@/Pages/Users/fragments/StatCardSkeleton.vue';
import TableSkeletonLoader from '@/Pages/Users/fragments/TableSkeletonLoader.vue';
import EmptyState from '@/Pages/Users/fragments/EmptyState.vue';
import StatusBadge from '@/Pages/Users/fragments/StatusBadge.vue';

const loading = ref(false);
const insightsLoading = ref(false);
const seoLoading = ref(false);
const syncingGsc = ref(false);
const rowsPerPage = ref(15);
const rows = ref([]);
const overview = ref(null);
const insights = ref(null);
const seo = ref(null);
const meta = ref(null);
const pathFilter = ref('');
const reportType = ref('by_path');
const tableSearch = ref('');
const rangePreset = ref('28d');

const today = new Date();
const toInputDate = (d) => {
    const copy = new Date(d);
    const offset = copy.getTimezoneOffset();
    copy.setMinutes(copy.getMinutes() - offset);
    return copy.toISOString().slice(0, 10);
};

const dateFrom = ref(toInputDate(new Date(today.getTime() - 27 * 86400000)));
const dateTo = ref(toInputDate(today));

const rangePresets = [
    { label: '7d', value: '7d' },
    { label: '28d', value: '28d' },
    { label: '90d', value: '90d' },
    { label: 'Custom', value: 'custom' },
];

const reportTypes = [
    { label: 'By URL', value: 'by_path' },
    { label: 'By source', value: 'by_source' },
    { label: 'Devices', value: 'devices' },
    { label: 'Daily', value: 'daily' },
    { label: 'Referrers', value: 'referrers' },
    { label: 'Keywords', value: 'keywords' },
    { label: 'Engagement', value: 'engagement' },
    { label: 'Actions', value: 'actions' },
];

const currentReportLabel = computed(() =>
    reportTypes.find((t) => t.value === reportType.value)?.label || 'Report',
);

const tableDescription = computed(() => {
    const n = filteredRows.value.length;
    return `${n} row${n === 1 ? '' : 's'} · click a path to drill down`;
});

const rangeDaysLabel = computed(() => {
    const days = Number(meta.value?.range_days);
    if (!Number.isFinite(days) || days <= 0) {
        return '—';
    }
    return String(Math.round(days));
});

const skeletonColumns = [
    { header: 'Path', width: '35%' },
    { header: 'Pageviews', width: '15%' },
    { header: 'Visitors', width: '15%' },
    { header: 'CTAs', width: '15%' },
    { header: 'Rates', width: '20%' },
];

const filteredRows = computed(() => {
    const q = tableSearch.value.trim().toLowerCase();
    if (!q) {
        return rows.value;
    }
    return rows.value.filter((row) => JSON.stringify(row).toLowerCase().includes(q));
});

const seoDescription = computed(() => {
    if (seo.value?.refreshed_at) {
        return `GSC last synced ${seo.value.refreshed_at}`;
    }
    return 'Google Search Console keywords, CTR gaps, and landing-page ranks';
});

const seoSummaryChips = computed(() => {
    const summary = seo.value?.summary || {};
    const styles = {
        striking_distance: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
        fix_ctr: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
        defend: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
        buried: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
        cannibalized: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-500/20 dark:text-fuchsia-200',
        other: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    };
    const labels = {
        striking_distance: 'Striking',
        fix_ctr: 'Fix CTR',
        defend: 'Defend',
        buried: 'Buried',
        cannibalized: 'Cannibalized',
        other: 'Other',
    };
    return Object.entries(summary)
        .filter(([, count]) => Number(count) > 0)
        .map(([key, count]) => ({
            key,
            label: labels[key] || key,
            count,
            className: styles[key] || styles.other,
        }));
});

const dailyTotals = computed(() => {
    const days = insights.value?.daily || [];
    return {
        pageviews: days.reduce((s, d) => s + (d.pageviews || 0), 0),
        visitors: days.reduce((s, d) => s + (d.unique_visitors || 0), 0),
        ctas: days.reduce((s, d) => s + (d.cta_clicks || 0), 0),
    };
});

const maxDailyViews = computed(() =>
    Math.max(1, ...(insights.value?.daily || []).map((d) => d.pageviews || 0)),
);

const dailyBarHeight = (views) => Math.max(4, Math.round(((views || 0) / maxDailyViews.value) * 100));

const formatDuration = (ms) => {
    const total = Math.max(0, Math.round(Number(ms) || 0) / 1000);
    if (total < 60) {
        return `${Math.round(total)}s`;
    }
    const m = Math.floor(total / 60);
    const s = Math.round(total % 60);
    if (m < 60) {
        return `${m}m ${s}s`;
    }
    const h = Math.floor(m / 60);
    return `${h}h ${m % 60}m`;
};

const formatChannel = (channel) => {
    const map = {
        organic: 'Organic',
        direct: 'Direct',
        referral: 'Referral',
        social: 'Social',
        paid: 'Paid',
        email: 'Email',
        other: 'Other',
    };
    return map[channel] || channel || 'Other';
};

const channelVariant = (channel) => {
    const map = {
        organic: 'success',
        direct: 'neutral',
        referral: 'info',
        social: 'primary',
        paid: 'warning',
        email: 'info',
        other: 'neutral',
    };
    return map[channel] || 'neutral';
};

const deviceBarClass = (type) => {
    if (type === 'mobile') return 'bg-emerald-500';
    if (type === 'tablet') return 'bg-amber-500';
    return 'bg-sky-500';
};

const formatPct = (value) => {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    const n = Number(value);
    const pct = n <= 1 ? n * 100 : n;
    return `${pct.toFixed(1)}%`;
};

const formatNum = (value) => {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    return Number(value).toFixed(1);
};

const bucketVariant = (bucket) => {
    const map = {
        striking_distance: 'success',
        fix_ctr: 'warning',
        defend: 'info',
        buried: 'danger',
        cannibalized: 'primary',
        other: 'neutral',
    };
    return map[bucket] || 'neutral';
};

const applyPreset = () => {
    if (rangePreset.value === 'custom') {
        return;
    }
    const days = rangePreset.value === '7d' ? 6 : rangePreset.value === '90d' ? 89 : 27;
    const end = new Date();
    const start = new Date(end.getTime() - days * 86400000);
    dateFrom.value = toInputDate(start);
    dateTo.value = toInputDate(end);
    fetchReport();
};

const onCustomDate = () => {
    rangePreset.value = 'custom';
    fetchReport();
};

const fetchReport = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('siteVisitors.report'), {
            params: {
                type: reportType.value,
                date_from: dateFrom.value,
                date_to: dateTo.value,
                path: pathFilter.value || undefined,
            },
        });
        overview.value = data.overview ?? null;
        meta.value = data.meta ?? null;
        rows.value = data.rows ?? [];
        if (data.type) {
            reportType.value = data.type;
        }
    } catch {
        overview.value = null;
        meta.value = null;
        rows.value = [];
    } finally {
        loading.value = false;
    }

    // Heavy panels load after the table so the dashboard feels snappy.
    fetchInsights();
    fetchSeo();
};

const filterParams = () => ({
    date_from: dateFrom.value,
    date_to: dateTo.value,
    path: pathFilter.value || undefined,
});

const fetchInsights = async () => {
    insightsLoading.value = true;
    try {
        const { data } = await axios.get(route('siteVisitors.insights'), {
            params: filterParams(),
        });
        insights.value = data.insights ?? null;
    } catch {
        insights.value = null;
    } finally {
        insightsLoading.value = false;
    }
};

const fetchSeo = async () => {
    seoLoading.value = true;
    try {
        const { data } = await axios.get(route('siteVisitors.seo'), {
            params: { path: pathFilter.value || undefined },
        });
        seo.value = data.seo ?? null;
    } catch {
        seo.value = null;
    } finally {
        seoLoading.value = false;
    }
};

const filterByPath = (path) => {
    pathFilter.value = path;
    reportType.value = 'by_path';
    fetchReport();
};

const clearPathFilter = () => {
    pathFilter.value = '';
    fetchReport();
};

const syncGsc = async () => {
    syncingGsc.value = true;
    try {
        const { data } = await axios.post(route('siteVisitors.syncGsc'));
        if (data?.seo) {
            seo.value = data.seo;
        } else {
            await fetchSeo();
        }
    } catch {
        // keep existing seo panel
    } finally {
        syncingGsc.value = false;
    }
};

const exportCsv = () => {
    if (!filteredRows.value.length) {
        return;
    }
    const keys = Object.keys(filteredRows.value[0]);
    const escape = (v) => `"${String(v ?? '').replaceAll('"', '""')}"`;
    const lines = [
        keys.join(','),
        ...filteredRows.value.map((row) => keys.map((k) => escape(row[k])).join(',')),
    ];
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `visitors-${reportType.value}-${dateFrom.value}-to-${dateTo.value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

onMounted(fetchReport);
</script>

<style scoped>
:deep(.visitors-range-select .p-selectbutton),
:deep(.visitors-range-select.p-selectbutton) {
    display: inline-flex;
    flex-wrap: nowrap;
}

:deep(.visitors-report-table .p-paginator) {
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.75rem;
}
</style>
