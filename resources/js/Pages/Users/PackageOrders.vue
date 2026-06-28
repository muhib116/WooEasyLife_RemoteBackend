<template>
    <UserLayout
        title="Usage History"
        section="Usage History"
        subtitle="Order quota consumption and package usage records"
        :user="user"
    >
        <div class="space-y-5">
            <div
                v-if="filterDomain"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-900 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-100"
            >
                <span>
                    Showing usage for
                    <strong>{{ filterDomain }}</strong>
                </span>
                <Link
                    :href="route('users.packagesOrders', { user_id: userId })"
                >
                    <Button
                        label="Clear domain filter"
                        size="small"
                        severity="secondary"
                        outlined
                        as="span"
                    />
                </Link>
            </div>

            <PageCard title="Filter Period" description="Narrow results by date range">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400"
                        >
                            Start Date
                        </label>
                        <DatePicker
                            v-model="form.start_date"
                            class="w-full"
                            show-time
                            hour-format="12"
                        />
                    </div>
                    <div class="min-w-[220px] flex-1">
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400"
                        >
                            End Date
                        </label>
                        <DatePicker
                            v-model="form.end_date"
                            class="w-full"
                            show-time
                            hour-format="12"
                        />
                    </div>
                    <Button
                        label="Clear"
                        severity="secondary"
                        outlined
                        @click="
                            () => {
                                form.start_date = null;
                                form.end_date = null;
                                handleSubmit();
                            }
                        "
                    />
                    <Button label="Apply Filter" icon="pi pi-filter" @click="handleSubmit" />
                </div>
            </PageCard>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Total Records"
                    :value="overview.total_order_count"
                    icon="PhListBullets"
                />
                <StatCard
                    title="Real Orders"
                    :value="overview.total_real_order_count"
                    icon="PhShoppingCart"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Missing Orders"
                    :value="overview.total_missing_order_count"
                    icon="PhWarningCircle"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
                <StatCard
                    title="Total Amount"
                    :value="overview.total_order_amount"
                    icon="PhCurrencyCircleDollar"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
            </div>

            <PageCard
                title="Order History"
                :description="`${modifiedHistory?.length || 0} record${modifiedHistory?.length === 1 ? '' : 's'} in selected period`"
            >
                <EmptyState
                    v-if="!modifiedHistory?.length"
                    title="No orders found"
                    description="No package usage records match the selected date range."
                    icon="PhShoppingCart"
                />

                <div v-else class="space-y-4">
                    <PackageOrderDetails
                        v-for="(item, index) in modifiedHistory"
                        :key="index"
                        :order-item="item"
                        :sale-info="showSales(item)"
                    />
                </div>
            </PageCard>
        </div>
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "./UserLayout.vue";
import PageCard from "./fragments/PageCard.vue";
import StatCard from "./fragments/StatCard.vue";
import EmptyState from "./fragments/EmptyState.vue";
import PackageOrderDetails from "./PackageOrderDetails.vue";
import { isString, isArray, set } from "lodash";
import { computed } from "vue";
import { Link, useForm } from "@inertiajs/vue3";

defineOptions({
    name: "PackagesOrders",
});

const props = defineProps<{
    modifiedHistory: any[];
    userId: number;
    user: { id: number; name: string } | null;
    end_date: any;
    start_date: any;
    filterDomain?: string | null;
}>();

const form = useForm({
    start_date: props.start_date,
    end_date: props.end_date,
    domain: props.filterDomain ?? null,
});

const handleSubmit = () => {
    form.get(route("users.packagesOrders", props.userId), {
        preserveState: true,
    });
};

const overview = computed(() => {
    let total_order_count = props.modifiedHistory.length;
    let total_order_amount = 0;
    let total_missing_order_count = 0;
    let total_real_order_count = 0;

    props.modifiedHistory?.forEach((item) => {
        item?.use_details?.forEach((_item2: any) => {
            if (_item2?.from == "missing_order") {
                total_missing_order_count += 1;
            } else {
                total_real_order_count += 1;
            }
            total_order_amount += Number(_item2?.total_value) || 0;
        });
    });

    return {
        total_order_count,
        total_missing_order_count,
        total_real_order_count,
        total_order_amount: `${total_order_amount} TK`,
    };
});

const showSales = (item: any) => {
    if (!isArray(item?.use_details)) {
        set(item, "use_details", []);
    }

    return (item?.use_details || []).map((entry: any) => {
        if (isString(entry?.cart_contents)) {
            entry.cart_contents = [];
        }

        return entry;
    });
};
</script>
