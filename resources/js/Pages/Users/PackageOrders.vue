<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <div class="flex gap-3">
                        <div class="relative min-w-[300px]">
                            <div class="absolute -top-8">End Date</div>
                            <DatePicker 
                                class="w-full"
                                v-model="form.start_date" 
                                showTime
                                hourFormat="12"
                            />
                        </div>
                        <div class="relative min-w-[300px]">
                            <div class="absolute -top-8">End Date</div>
                            <DatePicker 
                                class="w-full"
                                v-model="form.end_date" 
                                showTime
                                hourFormat="12"
                            />
                        </div>
                        <Button @click="() => {
                            form.start_date = null
                            form.end_date = null
                            handleSubmit()
                        }">Clear</Button>
                        <Button @click="handleSubmit" severity="secondary">Submit</Button>
                    </div>
<pre>
{{ overview }}
</pre>
                    <div v-for="(item, index) in modifiedHistory">
                        <PackageOrderDetails
                            :orderItem="item"
                            :saleInfo="showSales(item)"
                        />
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
import { useForm } from "@inertiajs/vue3";

defineOptions({
    name: "PackagesOrders",
});

const props = defineProps<{
    modifiedHistory: any[];
    userId: any
    end_date: any,
    start_date: any
}>();

const form = useForm({
    start_date: props.start_date,
    end_date: props.end_date,
})

const handleSubmit = () => {
    form.get(route('users.packagesOrders', props.userId))
}

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
