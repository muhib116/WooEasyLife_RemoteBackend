<template>
    <AuthenticatedLayout title="Route Hit Reports">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between">
                    Use Report
                    <div class="flex gap-3">
                        <div class="relative">
                            <div class="absolute -top-8">End Date</div>
                            <DatePicker v-model="form.start_date" />
                        </div>
                        <div class="relative">
                            <div class="absolute -top-8">End Date</div>
                            <DatePicker v-model="form.end_date" />
                        </div>
                        <Dropdown
                            v-model="selectedUserId"
                            :options="users"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select User"
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
                <div
                    v-for="item in product_sale || []"
                    class="mb-4 rounded-lg border px-4 py-2"
                >
                    <a
                        :href="item?.item?.product_url"
                        class="hover:text-blue-500"
                        target="blank"
                    >
                        {{ item?.item?.name }}
                    </a>
                    <div>
                        {{ item?.total_quantity }}
                        <span class="text-yellow-500">
                            ({{ item?.missing_count || 0 }} from abandon)
                        </span>
                    </div>
                </div>
                <!-- <div v-for="item in uniqueLinksItems || []">
                    <template v-if="item?.products">
                        <a
                            v-for="product in item?.products || []"
                            :href="product?.product_url"
                            class="mb-5 block border px-4 py-2 hover:bg-slate-500/20"
                            target="_blank"
                            >{{ product?.name }}</a
                        >
                    </template>
                    <a
                        v-else
                        :href="item?.product_url"
                        class="mb-5 block border px-4 py-2 hover:bg-slate-500/20"
                        target="_blank"
                        >{{ item?.name }}</a
                    >
                </div> -->
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import { isString, each, isArray } from "lodash";

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

const fetchReport = async () => {
    if (!selectedUserId.value) {
        return;
    }
    loading.value = true;
    const { data } = await axios.post(route("useAnalysis.getUseReport"), {
        user_id: selectedUserId.value,
        ...(form.value || {}),
    });
    loading.value = false;

    report.value = data || [];
    getUniqueLinks(data);
    // console.log(data);
};

const getUniqueLinks = (data) => {
    uniqueLinks.value = [];
    product_sale.value = {};
    const useDetails = data.flatMap((item) => item.use_details);
    // console.log(useDetails)
    each(useDetails, (item) => {
        if (item?.from == "missing_order") {
            // console.log(item)
        }
        if (isArray(item?.cart_contents)) {
            each(item?.cart_contents, (content) => {
                if (!uniqueLinks.value.includes(content?.product_url)) {
                    uniqueLinks.value.push(content?.product_url);
                    uniqueLinksItems.value.push(content);
                }
            });
        } else {
            if (!uniqueLinks.value.includes(item?.cart_contents?.product_url)) {
                uniqueLinks.value.push(item?.cart_contents?.product_url);
                uniqueLinksItems.value.push(item?.cart_contents);
            }
        }

        // =======================================
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
                return [item.cart_contents]; // single object wrapped in array
            }

            return []; // fallback if structure is weird
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

            product_sale.value[url].value.push({
                quantity: quantity,
                item: content_item,
                product_url: url,
            });

            product_sale.value[url].total_quantity += quantity;

            if (item.from === "missing_order") {
                product_sale.value[url].missing_count += 1;
            }
        });
    });
    // console.log(product_sale.value)
    // console.log(uniqueLinks.value);
    // console.log(product_sale.value);
};
</script>
