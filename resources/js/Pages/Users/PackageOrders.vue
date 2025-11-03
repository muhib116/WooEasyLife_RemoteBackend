<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <div class="flex gap-3">
                        <div class="relative">
                            <div class="absolute -top-8">End Date</div>
                            <DatePicker v-model="form.start_date" />
                        </div>
                        <div class="relative">
                            <div class="absolute -top-8">End Date</div>
                            <DatePicker v-model="form.end_date" />
                        </div>
                    </div>
<pre>
{{ overview }}
</pre>
                    <div v-for="(item, index) in modifiedHistory">
                        <PackageOrderDetails :saleInfo="showSales(item)" />
                    </div>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import PackageOrderDetails from "./PackageOrderDetails.vue";
import PackageForm from "./fragments/PackageForm.vue";
import UseDetails from "./fragments/UseDetails.vue";
import { isString, isArray, set, sumBy, get, each } from "lodash";
import { computed, ref } from "vue";

defineOptions({
    name: "PackagesOrders",
});

const props = defineProps<{
    modifiedHistory: any[];
}>();

const form = ref({
    start_date: null,
    end_date: null,
})

const overview = computed(() => {
    let total_order_count = props.modifiedHistory.length
    let total_order_amount = 0
    let total_missing_order_count = 0
    let total_missing_order_amount = 0
    let total_real_order_count = 0
    let total_real_order_amount = 0
    props.modifiedHistory?.forEach(item => {
        item?.use_details?.forEach(_item2 => {
            if(_item2?.from == 'missing_order') {
                console.log({..._item2})
                total_missing_order_count += 1
            } else {
                // const sum = sumBy(_item2?.cart_contents, (i) => Number(i?.quantity) || 0)
                total_real_order_count += 1
            }
            total_order_amount += Number(_item2?.total_value) || 0
        })
    })

    return {
        total_order_count,
        total_missing_order_count,
        total_real_order_count,
        total_order_amount: `${total_order_amount}TK`,
    }
})

const showSales = (item) => {
    if (!isArray(item?.use_details)) {
        set(item, "use_details", []);
    }
    return (item?.use_details || []).map((item) => {
        if (isString(item?.cart_contents)) {
            item.cart_contents = [];
        }
        return item;
    });
};
</script>
