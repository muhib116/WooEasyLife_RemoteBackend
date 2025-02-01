<template>
    <form @submit.prevent="$emit('handleSave')">
        <div class="mb-4 flex flex-col gap-1">
            <div for="domain" class="w-24 font-semibold">Domain</div>
            <div class="relative flex-auto">
                <InputText
                    v-model="form.domain"
                    id="domain"
                    placeholder="Domain"
                    class="!w-full"
                />
                <span
                    v-if="form.errors.domain"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ form.errors.domain }}</span
                >
            </div>
        </div>
        <div class="mb-4 flex flex-col gap-1">
            <div for="note" class="w-24 font-semibold">Note</div>
            <div class="relative flex-auto">
                <Textarea
                    id="note"
                    v-model="form.note"
                    autoResize
                    rows="2"
                    placeholder="Note"
                    class="!w-full"
                />
                <span
                    v-if="form.errors.note"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ form.errors.note }}</span
                >
            </div>
        </div>
        <div v-if="!form.id" class="mb-4 flex flex-col gap-1">
            <div class="font-semibold">Transaction Method</div>
            <div class="relative flex-auto">
                <Select
                    class="w-full"
                    v-model="form.transaction_method"
                    :options="methods"
                    @change="onMethodChange"
                    optionLabel="title"
                    optionValue="id"
                    placeholder="Method"
                />
                <span
                    v-if="form.errors.transaction_method"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ form.errors.transaction_method }}</span
                >
            </div>
        </div>
        <template v-if="!form.id && form.transaction_method != 'Cash'">
            <div class="mb-4 flex flex-col gap-1">
                <div class="font-semibold">Transaction Number</div>
                <div class="relative flex-auto">
                    <InputText
                        v-model="form.transaction_number"
                        id="transaction_number"
                        placeholder="Transaction Number"
                        class="!w-full"
                    />
                    <span
                        v-if="form.errors.transaction_number"
                        class="absolute -bottom-6 left-0 text-red-500"
                        >{{ form.errors.transaction_number }}</span
                    >
                </div>
            </div>
            <div class="mb-4 flex flex-col gap-1">
                <div class="font-semibold">Transaction Id</div>
                <div class="relative flex-auto">
                    <InputText
                        v-model="form.transaction_id"
                        id="transaction_id"
                        placeholder="Transaction Id"
                        class="!w-full"
                    />
                    <span
                        v-if="form.errors.transaction_id"
                        class="absolute -bottom-6 left-0 text-red-500"
                        >{{ form.errors.transaction_id }}</span
                    >
                </div>
            </div>
            <div class="mb-4 flex flex-col gap-1">
                <div class="font-semibold">Transaction Charge</div>
                <div class="relative flex-auto">
                    <InputNumber
                        :useGrouping="false"
                        :maxFractionDigits="5"
                        v-model="form.transaction_charge"
                        inputId="limit"
                        fluid
                    />
                    <span
                        v-if="form.errors.transaction_charge"
                        class="absolute -bottom-6 left-0 text-red-500"
                        >{{ form.errors.transaction_charge }}</span
                    >
                </div>
            </div>
        </template>
        <div v-if="!form.id" class="mb-4 flex flex-col gap-1">
            <div for="limit" class="w-24 font-semibold">Order Limit</div>
            <div class="relative flex-auto">
                <InputNumber
                    :useGrouping="false"
                    v-model="form.limit"
                    inputId="limit"
                    fluid
                />

                <span
                    v-if="form.errors.limit"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ form.errors.limit }}</span
                >
            </div>
        </div>
        <div v-if="!form.id" class="mb-4 flex flex-col gap-1">
            <div for="package" class="w-24 font-semibold">Package</div>
            <div class="relative flex-auto">
                <Select
                    class="w-full"
                    v-model="form.package_id"
                    :options="packages"
                    :optionLabel="
                        (item) => `(${item.per_order_rate}TK) ${item.title}`
                    "
                    optionValue="id"
                    placeholder="Package"
                />
                <span
                    v-if="form.errors.package_id"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ form.errors.package_id }}</span
                >
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <div class="pr-5">Total Cost = {{ getTotalCost }} TK</div>
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                @click="$emit('onClose')"
            ></Button>
            <Button
                type="submit"
                :label="form.id ? 'Update' : 'Purchase'"
                :loading="form.processing"
                @click="$emit('handleSave')"
            ></Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";

const props = defineProps<{
    form: any;
    packages: any[];
}>();

const getTotalCost = computed(() => {
    const foundPackage = (props.packages || []).find(
        (item) => item.id == props.form.package_id,
    );
    if (foundPackage) {
        console.log(foundPackage);
        let cost = foundPackage.per_order_rate * props.form.limit;
        return cost || 0;
    }
    return 0;
});

const onMethodChange = () => {
    if (props.form?.transaction_method == "Cash") {
        props.form.transaction_id = null;
        props.form.transaction_charge = 0;
        props.form.transaction_number = null;
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
