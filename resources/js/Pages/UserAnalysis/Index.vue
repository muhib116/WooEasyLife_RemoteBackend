<template>
    <AuthenticatedLayout title="Use Analysis">
        <div class="space-y-5">
            <PageHeader
                title="Use Analysis"
                description="Order and abandon metrics per merchant and product"
                icon="PhChartBar"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                Start Date
                            </label>
                            <DatePicker v-model="form.start_date" size="small" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                End Date
                            </label>
                            <DatePicker v-model="form.end_date" size="small" />
                        </div>
                        <Dropdown
                            v-model="selectedUserId"
                            :options="users"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select merchant"
                            class="w-[220px]"
                            @change="fetchReport"
                        />
                        <Button
                            label="Load Report"
                            icon="pi pi-search"
                            size="small"
                            :loading="loading"
                            :disabled="!selectedUserId"
                            @click="fetchReport"
                        />
                    </div>
                </template>
            </PageHeader>

            <div
                v-if="selectedUserId"
                class="grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <StatCard
                    title="Completed Orders"
                    :value="total_order - total_abandon"
                    icon="PhShoppingCart"
                    subtitle="Excluding abandons"
                />
                <StatCard
                    title="Abandoned"
                    :value="total_abandon"
                    icon="PhXCircle"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
                <StatCard
                    title="All Total"
                    :value="total_order"
                    icon="PhPackage"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
            </div>

            <PageCard
                title="Product Breakdown"
                :description="
                    selectedUserId
                        ? `${productList.length} product${productList.length === 1 ? '' : 's'}`
                        : 'Select a merchant to view product stats'
                "
            >
                <EmptyState
                    v-if="!selectedUserId"
                    icon="PhUser"
                    title="No merchant selected"
                    description="Choose a merchant from the dropdown to load use analysis"
                />

                <div
                    v-else-if="loading"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Skeleton v-for="n in 6" :key="n" height="7rem" class="rounded-xl" />
                </div>

                <div
                    v-else-if="productList.length"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="(item, index) in productList"
                        :key="index"
                        class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <a
                            :href="item?.item?.product_url"
                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ item?.item?.name || "Unknown product" }}
                        </a>
                        <div class="mt-3 space-y-1 text-sm">
                            <div class="text-emerald-600 dark:text-emerald-400">
                                Orders:
                                {{
                                    (item?.total_quantity || 0) -
                                    (item?.missing_count || 0)
                                }}
                            </div>
                            <div class="text-amber-600 dark:text-amber-400">
                                Abandon: {{ item?.missing_count || 0 }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Total: {{ item?.total_quantity || 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-else
                    icon="PhPackage"
                    title="No product data"
                    description="No orders found for the selected filters"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import { each, isArray } from "lodash";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

const props = defineProps({
    users: Array,
});

const form = ref({
    start_date: null,
    end_date: null,
});

const loading = ref(false);
const selectedUserId = ref();
const report = ref([]);
const uniqueLinks = ref([]);
const uniqueLinksItems = ref([]);
const product_sale = ref({});
const total_order = ref(0);
const total_abandon = ref(0);

const productList = computed(() => Object.values(product_sale.value));

const fetchReport = async () => {
    if (!selectedUserId.value) {
        return;
    }
    loading.value = true;
    try {
        const { data } = await axios.post(route("useAnalysis.getUseReport"), {
            user_id: selectedUserId.value,
            ...(form.value || {}),
        });
        report.value = data || [];
        getUniqueLinks(data);
    } finally {
        loading.value = false;
    }
};

const getUniqueLinks = (data) => {
    uniqueLinks.value = [];
    product_sale.value = {};
    total_abandon.value = 0;
    total_order.value = 0;
    const useDetails = data.flatMap((item) => item.use_details);

    each(useDetails, (item) => {
        if (isArray(item?.cart_contents)) {
            each(item?.cart_contents, (content) => {
                if (!uniqueLinks.value.includes(content?.product_url)) {
                    uniqueLinks.value.push(content?.product_url);
                    uniqueLinksItems.value.push(content);
                }
            });
        } else if (item?.cart_contents?.product_url) {
            if (!uniqueLinks.value.includes(item?.cart_contents?.product_url)) {
                uniqueLinks.value.push(item?.cart_contents?.product_url);
                uniqueLinksItems.value.push(item?.cart_contents);
            }
        }

        const contents = (() => {
            if (Array.isArray(item.cart_contents)) {
                return item.cart_contents;
            }

            if (Array.isArray(item.cart_contents?.products)) {
                return item.cart_contents.products;
            }

            if (
                typeof item.cart_contents === "object" &&
                item.cart_contents !== null
            ) {
                return [item.cart_contents];
            }

            return [];
        })();

        contents.forEach((content_item) => {
            const url = content_item.product_url;

            if (!product_sale.value[url]) {
                product_sale.value[url] = {
                    value: [],
                    item: content_item,
                    total_quantity: 0,
                    missing_count: 0,
                };
            }

            const quantity = parseInt(content_item.quantity) || 0;

            if (quantity < 500) {
                product_sale.value[url].value.push({
                    quantity: quantity,
                    item: content_item,
                    product_url: url,
                });

                product_sale.value[url].total_quantity += quantity;
                total_order.value += quantity;

                if (item.from === "missing_order") {
                    product_sale.value[url].missing_count += quantity;
                    total_abandon.value += quantity;
                }
            }
        });
    });
};
</script>
