<template>
    <form @submit.prevent="$emit('handleSave')">
        <div v-if="!hideDomain" class="mb-4 flex flex-col gap-1">
            <label for="domain" class="text-sm font-semibold">Website domain</label>
            <div class="relative flex-auto">
                <InputText
                    v-model="form.domain"
                    id="domain"
                    placeholder="shop.example.com"
                    class="!w-full"
                    :disabled="Boolean(form.id)"
                />
                <DomainFieldHint class="mt-2" />
                <p
                    v-if="form.errors.domain"
                    class="mt-1 text-sm text-rose-500"
                >
                    {{ form.errors.domain }}
                </p>
            </div>
        </div>

        <template v-if="form.id">
            <div class="mb-4 flex flex-col gap-1">
                <label for="remaining_order" class="text-sm font-semibold">Remaining orders</label>
                <InputNumber
                    :useGrouping="false"
                    v-model="form.remaining_order"
                    inputId="remaining_order"
                    :max="form.total_order_can_handle ?? undefined"
                    placeholder="Enter remaining order count"
                    fluid
                />
                <p
                    v-if="form.total_order_can_handle"
                    class="text-xs text-gray-500 dark:text-gray-400"
                >
                    Plan quota: {{ form.total_order_can_handle }} orders
                </p>
                <p
                    v-if="form.errors.remaining_order"
                    class="mt-1 text-sm text-rose-500"
                >
                    {{ form.errors.remaining_order }}
                </p>
            </div>

            <div class="mb-4 flex flex-col gap-1">
                <label class="text-sm font-semibold">Expires at</label>
                <DatePicker
                    v-model="form.expires_at"
                    show-icon
                    date-format="yy-mm-dd"
                    placeholder="No expiry"
                    class="w-full"
                />
                <span
                    v-if="form.errors.expires_at"
                    class="text-sm text-rose-500"
                >
                    {{ form.errors.expires_at }}
                </span>
            </div>

            <div class="mb-4 flex items-center justify-between gap-3">
                <label class="text-sm font-semibold">Active plan</label>
                <ToggleSwitch v-model="form.is_active" />
            </div>

            <div class="mb-4 flex flex-col gap-1">
                <label for="edit_note" class="text-sm font-semibold">Admin note</label>
                <Textarea
                    id="edit_note"
                    v-model="form.note"
                    autoResize
                    rows="2"
                    placeholder="Optional note about this plan"
                    class="!w-full"
                />
            </div>
        </template>

        <div v-if="!form.id" class="mb-4 flex flex-col gap-1">
            <label for="package" class="text-sm font-semibold">Pricing plan</label>
            <div class="relative flex-auto">
                <Select
                    id="package"
                    class="w-full"
                    v-model="form.package_id"
                    :options="packages"
                    :optionLabel="
                        (item) => `(${item.per_order_rate}TK) ${item.title}`
                    "
                    optionValue="id"
                    placeholder="Select pricing plan"
                />
                <span
                    v-if="form.errors.package_id"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ form.errors.package_id }}</span
                >
            </div>
        </div>

        <div v-if="!form.id" class="mb-4">
            <OrderLimitPresets v-model="form.limit" />
            <p v-if="form.errors.limit" class="mt-1 text-sm text-rose-500">
                {{ form.errors.limit }}
            </p>
        </div>

        <template v-if="!form.id && !simplified">
            <div class="mb-4 flex flex-col gap-1">
                <label for="note" class="text-sm font-semibold">Admin note</label>
                <div class="relative flex-auto">
                    <Textarea
                        id="note"
                        v-model="form.note"
                        autoResize
                        rows="2"
                        placeholder="Optional note about this purchase"
                        class="!w-full"
                    />
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-1">
                <label class="text-sm font-semibold">Payment method</label>
                <div class="relative flex-auto">
                    <Select
                        class="w-full"
                        v-model="form.transaction_method"
                        :options="methods"
                        @change="onMethodChange"
                        optionLabel="title"
                        optionValue="id"
                        placeholder="Select payment method"
                    />
                </div>
            </div>
            <template v-if="form.transaction_method != 'Cash'">
                <div class="mb-4 flex flex-col gap-1">
                    <label class="text-sm font-semibold">Transaction number</label>
                    <InputText
                        v-model="form.transaction_number"
                        placeholder="Enter payment reference number"
                        class="!w-full"
                    />
                </div>
                <div class="mb-4 flex flex-col gap-1">
                    <label class="text-sm font-semibold">Transaction ID</label>
                    <InputText
                        v-model="form.transaction_id"
                        placeholder="Enter gateway transaction ID"
                        class="!w-full"
                    />
                </div>
                <div class="mb-4 flex flex-col gap-1">
                    <label class="text-sm font-semibold">Gateway charge (TK)</label>
                    <InputNumber
                        :useGrouping="false"
                        :maxFractionDigits="5"
                        v-model="form.transaction_charge"
                        placeholder="Enter gateway fee"
                        fluid
                    />
                </div>
            </template>
        </template>

        <details v-if="!form.id && simplified" class="mb-4">
            <summary
                class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-300"
            >
                Payment details (optional)
            </summary>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">Payment method</label>
                    <Select
                        class="w-full"
                        v-model="form.transaction_method"
                        :options="methods"
                        @change="onMethodChange"
                        optionLabel="title"
                        optionValue="id"
                        placeholder="Select payment method"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Admin note</label>
                    <Textarea
                        v-model="form.note"
                        autoResize
                        rows="2"
                        placeholder="Optional note about this purchase"
                        class="!w-full"
                    />
                </div>
            </div>
        </details>

        <div class="flex items-center justify-end gap-2">
            <div v-if="!form.id" class="pr-5 text-sm">
                Total Cost = {{ getTotalCost }} TK
            </div>
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                @click="$emit('onClose')"
            ></Button>
            <Button
                type="submit"
                :label="form.id ? 'Update Plan' : simplified ? 'Assign Plan' : 'Assign Plan'"
                :loading="form.processing"
            ></Button>
        </div>
    </form>
</template>

<script setup lang="ts">
import DomainFieldHint from "@/components/DomainFieldHint.vue";
import OrderLimitPresets from "@/components/OrderLimitPresets.vue";
import { computed, ref } from "vue";

const props = withDefaults(
    defineProps<{
        form: any;
        packages: any[];
        simplified?: boolean;
        hideDomain?: boolean;
    }>(),
    {
        simplified: false,
        hideDomain: false,
    },
);

const getTotalCost = computed(() => {
    const foundPackage = (props.packages || []).find(
        (item) => item.id == props.form.package_id,
    );
    if (foundPackage) {
        return foundPackage.per_order_rate * props.form.limit || 0;
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
    { title: "Bkash", id: "Bkash" },
    { title: "Nagad", id: "Nagad" },
    { title: "Rocket", id: "Rocket" },
    { title: "Bank", id: "Bank" },
    { title: "Cash", id: "Cash" },
]);
</script>
