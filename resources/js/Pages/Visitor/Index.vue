<template>
    <AuthenticatedLayout title="Visitor Report">
        <div class="space-y-5">
            <PageHeader
                title="Visitor Report"
                description="Analyze route hits, domains, and error traffic"
                icon="PhChartLineUp"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            >
                <template #actions>
                    <Dropdown
                        v-model="reportType"
                        :options="reportTypes"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Report type"
                        class="w-[200px]"
                        @change="fetchReport"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="loading"
                        @click="fetchReport"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <template v-if="loading">
                    <StatCardSkeleton v-for="index in 2" :key="`visitor-stat-${index}`" :delay="index * 80" />
                </template>
                <template v-else>
                    <StatCard
                        title="Records"
                        :value="report.length"
                        icon="PhRows"
                        :subtitle="currentReportLabel"
                    />
                    <StatCard
                        title="Total Hits"
                        :value="totalHits"
                        icon="PhCursorClick"
                        subtitle="Sum of hit counts in view"
                        accent-class="bg-emerald-500"
                        icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                        icon-class="text-emerald-600 dark:text-emerald-400"
                    />
                </template>
            </div>

            <PageCard
                :title="currentReportLabel"
                :description="`${report.length} record${report.length === 1 ? '' : 's'}`"
                no-padding
            >
                <div
                    v-if="loading"
                    class="border-t border-slate-100 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <i class="pi pi-spin pi-spinner" />
                        Loading visitor report...
                    </div>
                    <TableSkeletonLoader :columns="visitorSkeletonColumns" :rows="5" />
                </div>

                <DataTable
                    v-else-if="report.length"
                    :value="report"
                    :rows="rowsPerPage"
                    paginator
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    :rowsPerPageOptions="[10, 15, 25, 50]"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>

                    <Column
                        v-if="reportType === 'daily'"
                        field="date"
                        header="Date"
                    />
                    <Column
                        v-if="reportType === 'by_path'"
                        field="path"
                        header="Path"
                    />
                    <Column
                        v-if="reportType === 'by_domain'"
                        field="domain"
                        header="Domain"
                    />
                    <Column
                        v-if="reportType === 'errors'"
                        field="path"
                        header="Path"
                    />
                    <Column
                        v-if="reportType === 'errors'"
                        field="status"
                        header="Status"
                    />
                    <Column
                        v-if="reportType === 'errors'"
                        field="error"
                        header="Error"
                    />
                    <Column
                        v-if="['by_path', 'by_domain', 'daily'].includes(reportType)"
                        field="total_hits"
                        header="Hits"
                    />
                    <Column
                        v-if="reportType === 'errors'"
                        field="created_at"
                        header="Date"
                    />
                    <Column v-if="reportType === 'full'" field="path" header="Path" />
                    <Column v-if="reportType === 'full'" field="domain" header="Domain" />
                    <Column v-if="reportType === 'full'" field="status" header="Status" />
                    <Column v-if="reportType === 'full'" field="error" header="Error" />
                    <Column v-if="reportType === 'full'" field="hit_count" header="Hits" />
                    <Column v-if="reportType === 'full'" field="created_at" header="Created At" />
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhChartLineUp"
                    title="No report data"
                    description="Try a different report type or reload"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatCardSkeleton from "@/Pages/Users/fragments/StatCardSkeleton.vue";
import TableSkeletonLoader from "@/Pages/Users/fragments/TableSkeletonLoader.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

const loading = ref(false);
const rowsPerPage = ref(15);
const report = ref([]);

const visitorSkeletonColumns = [
    { width: "2rem", variant: "bar" },
    { width: "14rem", variant: "bar" },
    { width: "6rem", variant: "bar" },
];

const reportType = ref("daily");
const reportTypes = [
    { label: "Daily Hits", value: "daily" },
    { label: "By Path", value: "by_path" },
    { label: "By Domain", value: "by_domain" },
    { label: "Errors", value: "errors" },
    { label: "Full Log", value: "full" },
];

const currentReportLabel = computed(() => {
    return reportTypes.find((r) => r.value === reportType.value)?.label || "Report";
});

const totalHits = computed(() => {
    return report.value.reduce((sum, row) => {
        const hits = row.total_hits ?? row.hit_count ?? 0;
        return sum + Number(hits);
    }, 0);
});

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await axios.get(route("visitor.report"), {
            params: { type: reportType.value },
        });
        report.value = res.data.data || [];
    } catch (err) {
        console.error("Report fetch failed", err);
        report.value = [];
    }
    loading.value = false;
};

onMounted(() => {
    fetchReport();
});
</script>
