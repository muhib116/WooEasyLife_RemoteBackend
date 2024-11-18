<template>
    <!-- <DataTable :value="virtualCars" scrollable scrollHeight="400px" tableStyle="min-width: 50rem"
    :virtualScrollerOptions="{ lazy: true, onLazyLoad: loadCarsLazy, itemSize: 46, delay: 200, showLoader: true, loading: lazyLoading, numToleratedItems: 10 }"> -->
    <DataTable :value="addresses" scrollable scrollHeight="flex">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xl font-bold">
                    Address of {{ customer.name }}
                </span>
                <Button
                    @click="handleGetCustomer"
                    icon="pi pi-refresh"
                    rounded
                    raised
                />
            </div>
        </template>
        <Column field="phone" header="Phone"></Column>
        <Column field="district" header="District"></Column>
        <Column field="thana" header="Thana"></Column>
        <Column field="address" header="Address"></Column>
    </DataTable>
</template>

<script setup lang="ts">
import { useCustomers } from "@/composable/useCustomers";
import { Customer } from "@/types";
import { onMounted, ref } from "vue";

const props = defineProps<{
    customer: Customer;
}>();

const { getCustomerAddress } = useCustomers();
const addresses = ref();
const isLoading = ref(false);

const handleGetCustomer = async () => {
    isLoading.value = true;
    addresses.value = Array(20).fill({
        phone: "null",
        district: "null",
        thana: "null",
        address: "null",
    });
    // addresses.value = await getCustomerAddress(props.customer?.id);
    // isLoading.value = false;
};

onMounted(async () => {
    handleGetCustomer();
});
</script>
