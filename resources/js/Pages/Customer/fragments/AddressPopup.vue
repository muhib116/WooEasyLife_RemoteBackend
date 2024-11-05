<template>
    <form @submit.prevent="handleSave">
        <Input.Primary
            v-model="addressForm.phone"
            label="Phone"
            :error="addressForm.errors.phone"
        />
        <Input.Primary
            v-model="addressForm.address"
            label="Address"
            :error="addressForm.errors.address"
        />
        <Input.Primary
            v-model="addressForm.district"
            label="District"
            :error="addressForm.errors.district"
        />
        <Input.Primary
            v-model="addressForm.thana"
            label="Thana"
            :error="addressForm.errors.thana"
        />
        <div class="flex justify-end gap-2">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                @click="showForm = false"
            ></Button>
            <Button
                type="submit"
                :label="addressForm.id ? 'Update' : 'Create'"
                :loading="addressForm.processing"
                @click="handleSave"
            ></Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { Address, Customer } from "@/types";
import { useForm } from "@inertiajs/vue3";
import { Input } from "@/plugins/form";

const props = defineProps<{
    customer: Customer;
    active?: boolean;
}>();

const addressForm = useForm<Address>({
    id: null,
    customer_id: null,
    phone: "",
    district: "",
    thana: "",
    address: "",
});

const handleSave = () => {
    addressForm.post(route('customers.saveAddress', props.customer?.id))
};
</script>
