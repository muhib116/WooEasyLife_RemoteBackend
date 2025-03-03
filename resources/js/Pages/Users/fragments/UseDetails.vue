<template>
    <div class="py-5">
        <!-- <div class="grid place-content-center">
            <ProgressSpinner />
        </div> -->
        <div class="mb-5 flex gap-4">
            <Badge severity="info" :value="`Total Order: ${analyze?.totalOrder}`" />
            <Badge severity="info" :value="`Total Value: ${analyze?.totalValue}TK`" />
            <Badge severity="danger" :value="`Total Missing Order: ${analyze?.totalMissingOrder}`" />
            <Badge severity="danger" :value="`Total Missing Value: ${analyze?.totalMissingValue}TK`" />
        </div>
        <DataTable
            :value="useDetails"
            tableStyle="min-width: 50rem"
            showGridlines
            :rows="5"
            paginator
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            :rowsPerPageOptions="[5, 10, 25, 50, 100, 200]"
            currentPageReportTemplate="{first} to {last} of {totalRecords} Record &nbsp;"
        >
            <!-- <Column field="total_can_handle" header="Total Can" /> -->
            <Column field="order_count" header="Order Count" />
            <Column field="order_count" header="From">
                <template #body="{data}">
                    {{ get(data, 'use_details[0].from') }}
                </template>
            </Column>
            <Column field="total_order_handled" header="Total Handled" />
            <Column field="remaining_order" header="Remaining" />
            <Column field="created_at" header="Created">
                <template #body="{ data }">
                    {{ formatExpiresAt(data?.created_at) }}
                </template>
            </Column>
            <Column header="Remaining">
                <template #body="{ data }">
                    <Button
                        @click="showSales(data)"
                        icon="pi pi-sliders-v"
                        size="small"
                        label="Sale Info"
                    />
                </template>
            </Column>
        </DataTable>

        <Dialog
            v-model:visible="dialogVisible"
            header="Sale Info"
            modal
            maximizable
            :style="{ width: '50rem' }"
            draggable
            dismissableMask
        >
            <!-- <div class="w-[500px]">hi</div> -->
            <div v-for="(item, index) in saleInfo">
                <div>
                    Order ({{ index + 1 }}) with order value of ({{
                        item?.total_value
                    }})
                    <Badge :value="item?.from" />
                </div>
                <DataTable
                    :value="item?.from == 'missing_order' ? item?.cart_contents?.products || [] : item?.cart_contents || []"
                    scrollable
                    scrollHeight="flex"
                    tableStyle="min-width: 50rem"
                >
                    <Column field="order_id" header="Id"></Column>
                    <Column field="product_url" header="Product Link">
                        <template #body="{ data }">
                            <Button
                                as="a"
                                size="small"
                                severity="info"
                                target="_blank"
                                v-if="data?.product_url"
                                :href="data?.product_url"
                                >Look Product</Button
                            >
                        </template>
                    </Column>
                    <Column field="name" header="Name"></Column>
                    <Column field="quantity" header="Quantity"></Column>
                    <Column field="price" header="Price"></Column>
                    <Column field="total_price" header="Total Price"></Column>
                </DataTable>
            </div>
            <template #footer>
                <Button
                    label="Ok"
                    icon="pi pi-check"
                    @click="dialogVisible = false"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { onMounted, ref } from "vue";
import { isString, isArray, set, get, each } from "lodash";
import { format, parseISO } from "date-fns";

// showUseDetails
const props = defineProps<{
    id: any;
    user: any;
}>();

const loading = ref(false);
const dialogVisible = ref(false);
const saleInfo = ref([]);

const useDetails = ref<any[]>([]);
const showUseDetails = ref(false);
const analyze = ref<any>({})

function formatExpiresAt(expiresAt) {
    if (expiresAt === null) {
        return "";
    }
    return format(parseISO(expiresAt), "dd MMM yyyy, hh:mm a"); // Example: Jan 18, 2025, 12:00 AM
}

const getTotalMissingOrder = (useDetails) => {
    // {{ get(data, 'use_details[0].from') }}
    let totalMissingOrder = 0
    let totalOrder = 0
    let totalValue = 0
    let totalMissingValue = 0
    each(useDetails, item => {
        if(get(item, 'use_details[0].from') == 'missing_order') {
            totalMissingOrder++
        } else {
            totalOrder++
        }
        get(item, 'use_details')?.forEach(i => {
            if(i?.from == "missing_order") {
                totalMissingValue += Number(i?.total_value) || 0
            } else {
                totalValue += Number(i?.total_value) || 0
            }
        })
    })
    return {
        totalMissingOrder,
        totalOrder,
        totalValue,
        totalMissingValue
    }
}

const showSales = (item) => {
    if (!isArray(item?.use_details)) {
        set(item, "use_details", []);
    }
    const d = (item?.use_details || []).map((item) => {
        if (isString(item?.cart_contents)) {
            console.log(item.cart_contents);
            item.cart_contents = [];
        }
        return item;
    });
    dialogVisible.value = true;
    // console.log(d);
    saleInfo.value = d;
};

const getUseDetails = async (packageId: any) => {
    useDetails.value = [];
    loading.value = true;
    showUseDetails.value = true;
    const { data } = await axios.get(
        route("users.useDetails", {
            user_id: props.user.id,
            package_id: packageId,
        }),
    );
    useDetails.value = data || [];
    loading.value = false;
    analyze.value = getTotalMissingOrder(data || [])
    // console.log(data);
};

onMounted(() => {
    if (props.id) {
        getUseDetails(props.id);
    }
});
</script>
