<template>
    <div class="py-5">
        <!-- <div class="grid place-content-center">
            <ProgressSpinner />
        </div> -->
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
            <Column field="total_order_handled" header="Total Handled" />
            <Column field="remaining_order" header="Remaining" />
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
                </div>
                <DataTable
                    :value="item?.cart_contents || []"
                    scrollable
                    scrollHeight="flex"
                    tableStyle="min-width: 50rem"
                >
                    <Column field="order_id" header="Id"></Column>
                    <Column field="product_url" header="Product Link">
                        <template #body="{ data }">
                            <!-- {{ data?.product_url }} -->
                            <Button
                                as="a"
                                size="small"
                                severity="info"
                                target="_blank"
                                v-if="data?.product_url"
                                :href="data?.product_url"
                                >Look Product</Button
                            >
                            <!-- <img :src="data?.product_url" alt=""> -->
                            <!-- <Image :src="data?.product_url" width="80" /> -->
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
import { isString, isArray, set } from "lodash";

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
    console.log(d);
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
    console.log(data);
};

onMounted(() => {
    if (props.id) {
        getUseDetails(props.id);
    }
});
</script>
