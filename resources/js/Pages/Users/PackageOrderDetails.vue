<template>
    <div v-for="(item, index) in saleInfo">
        <div>
            Order ({{ index + 1 }}) with order value of ({{
                item?.total_value
            }})
            <Badge :value="item?.from" />
        </div>
        <DataTable
            :value="
                item?.from == 'missing_order'
                    ? item?.cart_contents?.products || []
                    : item?.cart_contents || []
            "
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
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import PackageForm from "./fragments/PackageForm.vue";
import UseDetails from "./fragments/UseDetails.vue";

defineOptions({
    name: "PackagesOrders",
});

const props = defineProps<{
    saleInfo: any[];
}>();
</script>
