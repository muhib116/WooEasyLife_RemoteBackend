<template>
    <AuthenticatedLayout title="Intelligence Records">
        <div class="space-y-5">
            <PageHeader
                title="Database Records"
                description="Read-only view of all Order Intelligence tables — no data is modified here"
                icon="PhDatabase"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            />

            <IntelSubNav />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Total Records"
                    :value="overview.total_records"
                    icon="PhStack"
                    subtitle="Across all intelligence tables"
                />
                <StatCard
                    title="Tables"
                    :value="overview.tables.length"
                    icon="PhTable"
                    accent-class="bg-violet-500"
                    icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                    icon-class="text-violet-600 dark:text-violet-400"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="table in overview.tables"
                    :key="table.key"
                    type="button"
                    class="rounded-xl border p-4 text-left transition hover:border-violet-300 hover:shadow-sm dark:border-gray-700 dark:hover:border-violet-500/50"
                    :class="
                        selectedTable === table.key
                            ? 'border-violet-500 bg-violet-50 dark:bg-violet-500/10'
                            : 'border-gray-200 bg-white dark:bg-slate-900/60'
                    "
                    @click="selectTable(table.key)"
                >
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ table.label }}
                    </div>
                    <div class="mt-2 text-2xl font-bold text-violet-600 dark:text-violet-400">
                        {{ table.count.toLocaleString() }}
                    </div>
                    <div v-if="table.latest_at" class="mt-1 text-xs text-gray-500">
                        Latest: {{ table.latest_at }}
                    </div>
                </button>
            </div>

            <PageCard
                v-if="selectedTable"
                :title="tableLabel"
                :description="`${tableMeta.total} rows — read only`"
            >
                <DataTable
                    :value="tableRows"
                    size="small"
                    striped-rows
                    scrollable
                    scroll-height="500px"
                    class="professional-table"
                    :loading="tableLoading"
                >
                    <Column
                        v-for="col in tableColumns"
                        :key="col"
                        :field="col"
                        :header="col.replace(/_/g, ' ')"
                    />
                </DataTable>

                <Paginator
                    v-if="tableMeta.last_page > 1"
                    class="mt-4"
                    :rows="tableMeta.per_page"
                    :total-records="tableMeta.total"
                    :first="(tableMeta.current_page - 1) * tableMeta.per_page"
                    @page="onTablePage"
                />
            </PageCard>

            <EmptyState
                v-else
                icon="PhCursorClick"
                title="Select a table"
                description="Click any table card above to browse its records"
            />
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import IntelSubNav from "./fragments/IntelSubNav.vue";

type TableOverview = {
    key: string;
    label: string;
    count: number;
    latest_at: string | null;
};

const props = defineProps<{
    overview: { tables: TableOverview[]; total_records: number };
}>();

const selectedTable = ref<string | null>(null);
const tableLoading = ref(false);
const tableRows = ref<Array<Record<string, unknown>>>([]);
const tableColumns = ref<string[]>([]);
const tableLabel = ref("");
const tableMeta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 });

const selectTable = async (key: string, page = 1) => {
    selectedTable.value = key;
    tableLoading.value = true;
    try {
        const { data } = await axios.get(route("orderIntelligence.recordsTable", key), {
            params: { page, per_page: 25 },
        });
        tableRows.value = data.data;
        tableColumns.value = data.columns;
        tableLabel.value = data.label;
        tableMeta.value = data.meta;
    } finally {
        tableLoading.value = false;
    }
};

const onTablePage = (event: { page: number }) => {
    if (selectedTable.value) {
        selectTable(selectedTable.value, event.page + 1);
    }
};
</script>
