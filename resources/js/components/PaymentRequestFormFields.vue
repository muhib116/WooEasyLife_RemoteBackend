<template>
    <form class="space-y-4" @submit.prevent="$emit('submit')">
        <SubscriptionPaymentGuide v-if="showPaymentGuide" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div v-if="showDomain" class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium">Domain</label>
                <Select
                    :model-value="form.domain"
                    :options="domainOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Select website domain"
                    class="w-full"
                    @update:model-value="updateField('domain', $event)"
                />
                <small
                    v-if="!domains.length"
                    class="mt-1 block text-amber-600 dark:text-amber-400"
                >
                    {{ emptyDomainsMessage }}
                </small>
                <small v-if="form.errors?.domain" class="mt-1 block text-red-500">
                    {{ form.errors.domain }}
                </small>
            </div>

            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium">Plan</label>
                <Select
                    :model-value="form.package_hub_id"
                    :options="plans"
                    :option-label="planOptionLabel"
                    option-value="id"
                    placeholder="Select plan"
                    class="w-full"
                    @update:model-value="onPlanChange"
                />
                <small v-if="form.errors?.package_hub_id" class="mt-1 block text-red-500">
                    {{ form.errors.package_hub_id }}
                </small>
            </div>

            <div v-if="selectedPlan && isCatalogSelected" class="sm:col-span-2">
                <PlanSelectSummary :plan="selectedPlan" />
            </div>

            <div v-if="!isCatalogSelected" class="sm:col-span-2">
                <OrderLimitPresets
                    :model-value="form.order_limit"
                    @update:model-value="onOrderLimitChange"
                />
                <small v-if="form.errors?.order_limit" class="mt-1 block text-red-500">
                    {{ form.errors.order_limit }}
                </small>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Total amount (TK)</label>
                <InputNumber
                    :model-value="form.total_amount"
                    class="w-full"
                    :min="isCatalogSelected && suggestedTotal === 0 ? 0 : 0.01"
                    :min-fraction-digits="2"
                    placeholder="Enter total paid amount"
                    @update:model-value="updateField('total_amount', $event)"
                />
                <p
                    v-if="selectedPlan && (isCatalogSelected || form.order_limit)"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                >
                    <template v-if="isCatalogSelected">
                        Fixed plan price: {{ suggestedTotal === 0 ? "Free" : `${suggestedTotal} TK` }}
                    </template>
                    <template v-else>
                        Suggested: {{ suggestedTotal }} TK ({{ selectedPlan.per_order_rate }} TK ×
                        {{ form.order_limit }} orders)
                    </template>
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold">Payment method</label>
                <Select
                    :model-value="form.transaction_method"
                    :options="paymentMethods"
                    option-label="title"
                    option-value="id"
                    placeholder="Select payment method"
                    class="w-full"
                    @update:model-value="onMethodChange"
                />
                <small v-if="form.errors?.transaction_method" class="mt-1 block text-red-500">
                    {{ form.errors.transaction_method }}
                </small>
            </div>

            <template v-if="showGatewayFields">
                <div>
                    <label class="mb-1 block text-sm font-semibold">Transaction ID</label>
                    <InputText
                        :model-value="form.transaction_id"
                        class="w-full"
                        placeholder="Enter payment transaction ID"
                        @update:model-value="updateField('transaction_id', $event)"
                    />
                    <small v-if="form.errors?.transaction_id" class="mt-1 block text-red-500">
                        {{ form.errors.transaction_id }}
                    </small>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold">Account number</label>
                    <InputText
                        :model-value="form.account_number"
                        class="w-full"
                        placeholder="Sender mobile or account number"
                        @update:model-value="updateField('account_number', $event)"
                    />
                    <small v-if="form.errors?.account_number" class="mt-1 block text-red-500">
                        {{ form.errors.account_number }}
                    </small>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold">Gateway charge (TK)</label>
                    <InputNumber
                        :model-value="form.transaction_charge"
                        class="w-full"
                        :min="0"
                        :min-fraction-digits="2"
                        placeholder="Enter gateway fee"
                        @update:model-value="updateField('transaction_charge', $event)"
                    />
                    <small v-if="form.errors?.transaction_charge" class="mt-1 block text-red-500">
                        {{ form.errors.transaction_charge }}
                    </small>
                </div>
            </template>
        </div>

        <div class="flex justify-end gap-2">
            <Button
                label="Cancel"
                severity="secondary"
                outlined
                type="button"
                @click="$emit('cancel')"
            />
            <Button
                :label="submitLabel"
                type="submit"
                :loading="form.processing"
                :disabled="!domains.length || !plans.length"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
import OrderLimitPresets from "@/components/OrderLimitPresets.vue";
import PlanSelectSummary from "@/components/PlanSelectSummary.vue";
import SubscriptionPaymentGuide from "@/components/SubscriptionPaymentGuide.vue";
import {
    isCatalogPackage,
    planOptionLabel,
} from "@/data/packageCatalogDraft";
import { computed } from "vue";

type PlanOption = {
    id: number;
    title: string;
    plan_type?: string | null;
    per_order_rate: number;
    package_price?: number | null;
    order_rate_token?: number | null;
    package_duration?: string | null;
};

const props = withDefaults(
    defineProps<{
        form: {
            domain: string;
            package_hub_id: number | null;
            order_limit: number;
            total_amount: number | null;
            transaction_method: string;
            transaction_id: string;
            account_number: string;
            transaction_charge: number;
            processing?: boolean;
            errors?: Record<string, string>;
        };
        plans: PlanOption[];
        domains: string[];
        showDomain?: boolean;
        showPaymentGuide?: boolean;
        includeCashMethod?: boolean;
        submitLabel?: string;
        emptyDomainsMessage?: string;
    }>(),
    {
        showDomain: true,
        showPaymentGuide: true,
        includeCashMethod: false,
        submitLabel: "Submit",
        emptyDomainsMessage:
            "No websites found yet. Contact support if you need a plan assigned.",
    },
);

defineEmits<{
    submit: [];
    cancel: [];
}>();

const domainOptions = computed(() =>
    props.domains.map((domain) => ({
        label: domain,
        value: domain,
    })),
);

const selectedPlan = computed(() =>
    props.plans.find((plan) => plan.id === props.form.package_hub_id),
);

const isCatalogSelected = computed(() =>
    selectedPlan.value ? isCatalogPackage(selectedPlan.value) : false,
);

const suggestedTotal = computed(() => {
    if (!selectedPlan.value) {
        return 0;
    }

    if (isCatalogPackage(selectedPlan.value)) {
        return Number((selectedPlan.value.package_price ?? 0).toFixed(2));
    }

    if (!props.form.order_limit) {
        return 0;
    }

    return Number(
        (selectedPlan.value.per_order_rate * props.form.order_limit).toFixed(2),
    );
});

const updateField = (field: string, value: unknown) => {
    (props.form as Record<string, unknown>)[field] = value;
};

const syncTotalAmount = () => {
    if (!selectedPlan.value) {
        return;
    }

    if (isCatalogPackage(selectedPlan.value)) {
        props.form.total_amount = suggestedTotal.value;
        props.form.order_limit = selectedPlan.value.order_rate_token ?? 0;
        return;
    }

    if (!props.form.order_limit) {
        return;
    }

    props.form.total_amount = suggestedTotal.value;
};

const onPlanChange = (value: number | null) => {
    updateField("package_hub_id", value);
    syncTotalAmount();
};

const onOrderLimitChange = (value: number | null) => {
    updateField("order_limit", value ?? 100);
    syncTotalAmount();
};

const paymentMethods = computed(() => {
    const methods = [
        { title: "Bkash", id: "Bkash" },
        { title: "Nagad", id: "Nagad" },
        { title: "Rocket", id: "Rocket" },
        { title: "Bank", id: "Bank" },
    ];

    if (props.includeCashMethod) {
        methods.push({ title: "Cash", id: "Cash" });
    }

    return methods;
});

const showGatewayFields = computed(
    () => props.form.transaction_method !== "Cash",
);

const onMethodChange = (value: string) => {
    updateField("transaction_method", value);

    if (value === "Cash") {
        updateField("transaction_id", "");
        updateField("account_number", "");
        updateField("transaction_charge", 0);
    }
};
</script>
