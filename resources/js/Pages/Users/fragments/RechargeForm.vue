<template>
    <div>
        <div class="mb-4 flex flex-col gap-1">
            <label for="domain" class="text-sm font-semibold">Website plan</label>
            <div class="relative flex-auto">
                <Select
                    id="domain"
                    class="w-full"
                    v-model="rechargeForm.domain"
                    :options="user_packages"
                    :optionLabel="(item) => `(${item.domain})`"
                    optionValue="domain"
                    placeholder="Select website plan"
                />
                <span
                    v-if="rechargeForm.errors.domain"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ rechargeForm.errors.domain }}</span
                >
            </div>
        </div>
        <div class="mb-4 flex flex-col gap-1">
            <label for="total_amount" class="text-sm font-semibold">Total amount (TK)</label>
            <div class="relative flex-auto">
                <div class="flex-auto">
                    <InputNumber
                        :useGrouping="false"
                        v-model="rechargeForm.total_amount"
                        inputId="total_amount"
                        placeholder="Enter recharge amount"
                        fluid
                    />
                </div>
                <span
                    v-if="rechargeForm.errors.total_amount"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ rechargeForm.errors.total_amount }}</span
                >
            </div>
        </div>

        <div class="mb-4 flex flex-col gap-1">
            <label class="text-sm font-semibold">Payment method</label>
            <div class="relative flex-auto">
                <Select
                    class="w-full"
                    v-model="rechargeForm.transaction_method"
                    :options="methods"
                    @change="onMethodChange"
                    optionLabel="title"
                    optionValue="id"
                    placeholder="Select payment method"
                />
                <span
                    v-if="rechargeForm.errors.transaction_method"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ rechargeForm.errors.transaction_method }}</span
                >
            </div>
        </div>
        <template v-if="rechargeForm.transaction_method != 'Cash'">
            <div class="mb-4 flex flex-col gap-1">
                <label for="account_number" class="text-sm font-semibold">Account number</label>
                <div class="relative flex-auto">
                    <InputText
                        v-model="rechargeForm.account_number"
                        id="account_number"
                        placeholder="Sender mobile or account number"
                        class="!w-full"
                    />
                    <span
                        v-if="rechargeForm.errors.account_number"
                        class="absolute -bottom-6 left-0 text-red-500"
                        >{{ rechargeForm.errors.account_number }}</span
                    >
                </div>
            </div>
            <div class="mb-4 flex flex-col gap-1">
                <label for="transaction_id" class="text-sm font-semibold">Transaction ID</label>
                <div class="relative flex-auto">
                    <InputText
                        v-model="rechargeForm.transaction_id"
                        id="transaction_id"
                        placeholder="Enter gateway transaction ID"
                        class="!w-full"
                    />
                    <span
                        v-if="rechargeForm.errors.transaction_id"
                        class="absolute -bottom-6 left-0 text-red-500"
                        >{{ rechargeForm.errors.transaction_id }}</span
                    >
                </div>
            </div>
            <div class="mb-4 flex flex-col gap-1">
                <label for="transaction_charge" class="text-sm font-semibold">Gateway charge (TK)</label>
                <div class="relative flex-auto">
                    <InputNumber
                        :useGrouping="false"
                        :maxFractionDigits="5"
                        v-model="rechargeForm.transaction_charge"
                        inputId="transaction_charge"
                        placeholder="Enter gateway fee"
                        fluid
                    />
                    <span
                        v-if="rechargeForm.errors.transaction_charge"
                        class="absolute -bottom-6 left-0 text-red-500"
                        >{{ rechargeForm.errors.transaction_charge }}</span
                    >
                </div>
            </div>
        </template>

        <div class="flex justify-end gap-2">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                @click="$emit('onClose')"
            ></Button>
            <Button
                type="submit"
                :label="rechargeForm.id ? 'Update' : 'Create'"
                :loading="rechargeForm.processing"
                @click="$emit('onSubmit')"
            ></Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";

const props = defineProps<{
    rechargeForm: any;
    user_packages: any[];
}>();

const getTotalCost = computed(() => {
    const foundPackage = (props.user_packages || []).find(
        (item) => item.id == props.rechargeForm.package_id,
    );
    if (foundPackage) {
        console.log(foundPackage);
        let cost = foundPackage.per_order_rate * props.rechargeForm.limit;
        return cost || 0;
    }
    return 0;
});

const onMethodChange = () => {
    if (props.rechargeForm?.transaction_method == "Cash") {
        props.rechargeForm.transaction_id = null;
        props.rechargeForm.transaction_charge = 0;
        props.rechargeForm.transaction_number = null;
    }
};

const methods = ref([
    {
        title: "Bkash",
        id: "Bkash",
    },
    {
        title: "Nagad",
        id: "Nagad",
    },
    {
        title: "Rocket",
        id: "Rocket",
    },
    {
        title: "Bank",
        id: "Bank",
    },
    {
        title: "Cash",
        id: "Cash",
    },
]);
</script>
