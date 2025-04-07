<template>
    <AuthenticatedLayout title="Route Hit Reports">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between">
                    Visitor Route Report
                    <div class="flex gap-3">
                        <Dropdown
                            v-model="reportType"
                            :options="reportTypes"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Report Type"
                            class="w-[250px]"
                            @change="fetchReport"
                        />
                        <Button
                            label="Reload"
                            icon="pi pi-refresh"
                            @click="fetchReport"
                            :loading="loading"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable
                    v-if="loading"
                    :value="new Array(4)"
                    tableStyle="min-width: 50rem;"
                >
                    <Column header="Path"><template #body><Skeleton /></template></Column>
                    <Column header="Hits"><template #body><Skeleton /></template></Column>
                </DataTable>

                <DataTable
                    v-else-if="report.length"
                    :value="report"
                    :rows="10"
                    paginator
                    tableStyle="min-width: 50rem;"
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
                        field="total_hits"
                        header="Hits"
                        v-if="['by_path', 'by_domain', 'daily'].includes(reportType)"
                    />
                    <Column
                        field="created_at"
                        header="Date"
                        v-if="reportType === 'errors'"
                    />
                    <!-- Full Log columns -->
                    <Column v-if="reportType === 'full'" field="path" header="Path" />
                    <Column v-if="reportType === 'full'" field="domain" header="Domain" />
                    <Column v-if="reportType === 'full'" field="status" header="Status" />
                    <Column v-if="reportType === 'full'" field="error" header="Error" />
                    <Column v-if="reportType === 'full'" field="hit_count" header="Hit Count" />
                    <Column v-if="reportType === 'full'" field="created_at" header="Created At" />
                </DataTable>

                <p v-else class="text-gray-400">No report data available.</p>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";

const loading = ref(false);
const report = ref([]);

const reportType = ref("daily");
const reportTypes = [
    { label: "Daily Hits", value: "daily" },
    { label: "By Path", value: "by_path" },
    { label: "By Domain", value: "by_domain" },
    { label: "Errors", value: "errors" },
    { label: "Full Log", value: "full" },
];

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
