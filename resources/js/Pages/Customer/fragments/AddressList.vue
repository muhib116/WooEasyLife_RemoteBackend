<template>
    {{ addresses }}
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

onMounted(async () => {
    console.log("hi");
    addresses.value = await getCustomerAddress(props.customer?.id);
});
</script>
