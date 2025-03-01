<template>
    <AuthenticatedLayout>
        <Card>
            <template #title>
                <div class="flex justify-between">
                    <div>Token Ledger</div>
                    <div class="flex gap-5">
                        <div class="grid">
                            <label class="mb-2 block text-sm font-bold">
                                Start Date
                            </label>
                            <DatePicker
                                size="small"
                                v-model="ledgerForm.start_date"
                            />
                        </div>
                        <div class="grid">
                            <label class="mb-2 block text-sm font-bold">
                                End Date
                            </label>
                            <DatePicker
                                size="small"
                                v-model="ledgerForm.end_date"
                            />
                        </div>
                        <div class="flex items-end gap-3">
                            <Button
                                @click="
                                    () => {
                                        ledgerForm.end_date = null;
                                        ledgerForm.start_date = null;
                                        getLedger();
                                    }
                                "
                                severity="danger"
                                size="small"
                                >Clear</Button
                            >
                            <Button @click="getLedger" size="small"
                                >Submit</Button
                            >
                        </div>
                    </div>
                </div>
            </template>

            <template #content>
                <div v-if="loading" class="grid place-content-center pt-20">
                    <ProgressSpinner />
                </div>
                <DataTable
                    v-else-if="ledger.transactions.length"
                    :value="ledger.transactions"
                    responsiveLayout="scroll"
                >
                    <Column field="date" header="Date" sortable></Column>
                    <Column field="total_token" header="Total Token"></Column>
                    <Column field="opening_balance" header="Opening Balance">
                        <template #body="{ data }">
                            {{ formatCurrency(data.opening_balance) }}
                        </template>
                    </Column>
                    <Column field="closing_balance" header="Closing Balance">
                        <template #body="{ data }">
                            {{ formatCurrency(data.closing_balance) }}
                        </template>
                    </Column>
                    <Column header="Transactions">
                        <template #body="{ data }">
                            <DataTable :value="data.transactions">
                                <Column field="title" header="Package"></Column>
                                <Column field="per_order_rate" header="Rate">
                                    <template #body="{ data }">
                                        {{
                                            formatCurrency(data.per_order_rate)
                                        }}
                                    </template>
                                </Column>
                                <Column
                                    field="total_order_can_handle"
                                    header="Token"
                                ></Column>
                                <Column field="total_cost" header="Cost">
                                    <template #body="{ data }">
                                        <Badge
                                            :value="
                                                formatCurrency(data.total_cost)
                                            "
                                            :severity="
                                                data?.per_order_rate *
                                                    data?.total_order_can_handle ==
                                                data.total_cost
                                                    ? 'success'
                                                    : 'danger'
                                            "
                                        ></Badge>
                                    </template>
                                </Column>
                                <Column
                                    field="transaction_charge"
                                    header="Charge"
                                >
                                    <template #body="{ data }">
                                        {{
                                            formatCurrency(
                                                data.transaction_charge,
                                            )
                                        }}
                                    </template>
                                </Column>
                                <Column
                                    field="transaction_method"
                                    header="Method"
                                ></Column>
                            </DataTable>
                        </template>
                    </Column>
                </DataTable>

                <p v-else>No transactions found for the selected date range.</p>
            </template>

            <template #footer>
                <div class="text-right">
                    <strong
                        >Final Closing Balance:
                        {{
                            formatCurrency(ledger.final_closing_balance)
                        }}</strong
                    >
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { debounce } from "lodash";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";

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
        }, 1000);
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

<style scoped>
.p-datatable {
    margin-top: 1rem;
}

/* HTML: <div class="loader"></div> */
.loader {
    width: 100px;
    aspect-ratio: 1;
    padding: 10px;
    box-sizing: border-box;
    display: grid;
    background: #fff;
    filter: blur(3px) contrast(7) hue-rotate(290deg);
    mix-blend-mode: darken;
}
.loader:before {
    content: "";
    margin: auto;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: #ff0000;
    background: currentColor;
    box-shadow:
        -30px 0,
        30px 0,
        0 30px,
        0 -30px;
    animation: l6 1s infinite alternate;
}
@keyframes l6 {
    90%,
    100% {
        box-shadow:
            -10px 0,
            10px 0,
            0 10px,
            0 -10px;
        transform: rotate(180deg);
    }
}
</style>
