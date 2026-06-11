<template>
    <AuthenticatedLayout title="Token Ledger">
        <div class="space-y-5">
            <PageHeader
                title="Token Ledger"
                description="Daily token balance and transaction history"
                icon="PhCoins"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            >
                <template #actions>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                Start Date
                            </label>
                            <DatePicker
                                v-model="ledgerForm.start_date"
                                size="small"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                End Date
                            </label>
                            <DatePicker
                                v-model="ledgerForm.end_date"
                                size="small"
                            />
                        </div>
                        <Button
                            label="Clear"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="clearFilters"
                        />
                        <Button
                            label="Apply"
                            icon="pi pi-search"
                            size="small"
                            :loading="loading"
                            @click="getLedger"
                        />
                    </div>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Opening Balance"
                    :value="formatCurrency(ledger.initial_opening_balance)"
                    icon="PhWallet"
                    subtitle="At period start"
                />
                <StatCard
                    title="Days with Activity"
                    :value="ledger.transactions.length"
                    icon="PhCalendar"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Closing Balance"
                    :value="formatCurrency(ledger.final_closing_balance)"
                    icon="PhCoinVertical"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
            </div>

            <PageCard
                title="Daily Ledger"
                :description="`${ledger.transactions.length} day${ledger.transactions.length === 1 ? '' : 's'} in range`"
                no-padding
            >
                <div v-if="loading" class="flex justify-center py-16">
                    <ProgressSpinner />
                </div>

                <DataTable
                    v-else-if="ledger.transactions.length"
                    :value="ledger.transactions"
                    v-model:expandedRows="expandedRows"
                    dataKey="date"
                    scrollable
                    scrollHeight="420px"
                    class="professional-table text-sm"
                >
                    <Column expander style="width: 3rem" />
                    <Column field="date" header="Date" sortable />
                    <Column
                        field="transaction_length"
                        header="Orders"
                        sortable
                    />
                    <Column field="total_token" header="Tokens" sortable />
                    <Column field="opening_balance" header="Opening">
                        <template #body="{ data }">
                            <span class="tabular-nums">{{
                                formatCurrency(data.opening_balance)
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="total_transaction_amount"
                        header="Day Total"
                    >
                        <template #body="{ data }">
                            <span class="tabular-nums">{{
                                formatCurrency(data.total_transaction_amount)
                            }}</span>
                        </template>
                    </Column>
                    <Column field="closing_balance" header="Closing">
                        <template #body="{ data }">
                            <span class="tabular-nums font-medium">{{
                                formatCurrency(data.closing_balance)
                            }}</span>
                        </template>
                    </Column>
                    <template #expansion="{ data }">
                        <div class="bg-slate-50/80 px-4 py-3 dark:bg-slate-900/40">
                            <DataTable
                                :value="data.transactions"
                                class="professional-table text-sm"
                            >
                                <Column field="title" header="Package" />
                                <Column field="per_order_rate" header="Rate">
                                    <template #body="{ data: row }">
                                        {{ formatCurrency(row.per_order_rate) }}
                                    </template>
                                </Column>
                                <Column
                                    field="total_order_can_handle"
                                    header="Tokens"
                                />
                                <Column field="total_cost" header="Cost">
                                    <template #body="{ data: row }">
                                        <Badge
                                            :value="formatCurrency(row.total_cost)"
                                            :severity="
                                                row?.per_order_rate *
                                                    row?.total_order_can_handle ==
                                                row.total_cost
                                                    ? 'success'
                                                    : 'danger'
                                            "
                                        />
                                    </template>
                                </Column>
                                <Column
                                    field="transaction_charge"
                                    header="Charge"
                                >
                                    <template #body="{ data: row }">
                                        {{
                                            formatCurrency(
                                                row.transaction_charge,
                                            )
                                        }}
                                    </template>
                                </Column>
                                <Column
                                    field="transaction_method"
                                    header="Method"
                                />
                            </DataTable>
                        </div>
                    </template>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhCoins"
                    title="No transactions"
                    description="Adjust the date range or reload the ledger"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

const ledgerForm = ref({
    start_date: null,
    end_date: null,
    user_id: null,
});

const ledger = ref({
    initial_opening_balance: 0,
    transactions: [],
    final_closing_balance: 0,
});

const loading = ref(false);
const expandedRows = ref({});

const clearFilters = () => {
    ledgerForm.value.end_date = null;
    ledgerForm.value.start_date = null;
    getLedger();
};

const getLedger = async () => {
    loading.value = true;
    try {
        const params = {
            ...(ledgerForm.value.start_date && {
                start_date: ledgerForm.value.start_date
                    .toISOString()
                    .split("T")[0],
            }),
            ...(ledgerForm.value.end_date && {
                end_date: ledgerForm.value.end_date.toISOString().split("T")[0],
            }),
            ...(ledgerForm.value.user_id && {
                user_id: ledgerForm.value.user_id,
            }),
        };

        const response = await axios.post(route("getTokenLedger", params));
        ledger.value = response.data;
    } catch (error) {
        console.error("Error fetching ledger:", error);
    } finally {
        setTimeout(() => {
            loading.value = false;
        }, 400);
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "BDT",
    }).format(value);
};

onMounted(getLedger);
</script>
