<template>
    <form class="flex flex-col gap-5" @submit.prevent="$emit('handleSave')">
        <p
            v-if="introText"
            class="rounded-lg border border-gray-100 bg-slate-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-800 dark:bg-slate-900/40 dark:text-gray-300"
        >
            {{ introText }}
        </p>

        <!-- Adjust existing subscription (admin override) -->
        <template v-if="mode === 'adjust'">
            <PlanSelectSummary
                v-if="currentPlan"
                :plan="currentPlan"
                :order-limit="
                    currentPlan && !isCatalogPackage(currentPlan)
                        ? form.total_order_can_handle
                        : null
                "
                :total-cost="currentPlanDisplayCost"
            />

            <p
                class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                Use <strong>Renew plan</strong> or <strong>Change plan</strong> to switch plans.
                This form is for manual overrides only.
            </p>

            <section class="space-y-4">
                <FormSection title="Plan quota" step="1">
                    <div class="space-y-1">
                        <label for="remaining_order" class="text-sm font-medium">
                            {{
                                form.plan_type === "catalog"
                                    ? "Remaining tokens"
                                    : "Remaining orders"
                            }}
                        </label>
                        <InputNumber
                            :useGrouping="false"
                            v-model="form.remaining_order"
                            inputId="remaining_order"
                            :max="form.total_order_can_handle ?? undefined"
                            :placeholder="
                                form.plan_type === 'catalog'
                                    ? 'Enter remaining token count'
                                    : 'Enter remaining order count'
                            "
                            fluid
                        />
                        <p
                            v-if="form.total_order_can_handle"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            Plan quota: {{ form.total_order_can_handle }}
                            {{ form.plan_type === "catalog" ? "tokens" : "orders" }}
                        </p>
                        <p
                            v-if="form.errors.remaining_order"
                            class="text-sm text-rose-500"
                        >
                            {{ form.errors.remaining_order }}
                        </p>
                    </div>
                </FormSection>

                <FormSection title="Expiry & status" step="2">
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-sm font-medium">Expires at</label>
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

                        <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2.5 dark:border-gray-800">
                            <div>
                                <p class="text-sm font-medium">Active plan</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Disabled plans stop plugin access for this domain.
                                </p>
                            </div>
                            <ToggleSwitch v-model="form.is_active" />
                        </div>

                        <div class="space-y-1">
                            <label for="edit_note" class="text-sm font-medium">Admin note</label>
                            <Textarea
                                id="edit_note"
                                v-model="form.note"
                                autoResize
                                rows="2"
                                placeholder="Optional note about this plan"
                                class="!w-full"
                            />
                        </div>
                    </div>
                </FormSection>
            </section>
        </template>

        <!-- Assign / add website / change plan -->
        <template v-else>
            <p
                v-if="mode === 'change' && currentPlan"
                class="text-xs font-medium uppercase tracking-wide text-gray-500"
            >
                Current plan
            </p>

            <PlanSelectSummary
                v-if="mode === 'change' && currentPlan"
                class="mb-1"
                :plan="currentPlan"
                :order-limit="
                    currentPlan && !isCatalogPackage(currentPlan)
                        ? form.total_order_can_handle
                        : null
                "
                :total-cost="currentPlanDisplayCost"
            />

            <p
                v-if="mode === 'change'"
                class="rounded-lg border border-gray-100 bg-slate-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-800 dark:bg-slate-900/40 dark:text-gray-300"
            >
                Pick a new plan for this domain. The current subscription will be replaced.
            </p>

            <FormSection
                v-if="!hideDomain"
                title="Store domain"
                step="1"
                :hint="'The hostname your WooCommerce plugin will use (e.g. shop.example.com).'"
            >
                <InputText
                    v-model="form.domain"
                    id="domain"
                    placeholder="shop.example.com"
                    class="!w-full"
                />
                <DomainFieldHint class="mt-2" />
                <p v-if="form.errors.domain" class="mt-1 text-sm text-rose-500">
                    {{ form.errors.domain }}
                </p>
            </FormSection>

            <FormSection
                title="Subscription plan"
                :step="planSelectStep"
                :hint="mode === 'change' ? 'Choose the new plan for this domain.' : 'Choose a plan. Details appear below after selection.'"
            >
                <Select
                    id="package"
                    v-model="form.package_id"
                    :options="groupedPlans"
                    option-label="title"
                    option-value="id"
                    option-group-label="label"
                    option-group-children="items"
                    placeholder="Select a plan"
                    class="w-full"
                    filter
                    filter-placeholder="Search plans..."
                    @update:model-value="onPackageChange"
                >
                    <template #optiongroup="slotProps">
                        <div class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ slotProps.option.label }}
                        </div>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center justify-between gap-3 py-0.5">
                            <span class="font-medium">{{ slotProps.option.title }}</span>
                            <span class="shrink-0 text-xs text-gray-500">
                                {{ planDropdownLabel(slotProps.option) }}
                            </span>
                        </div>
                    </template>
                </Select>
                <p v-if="form.errors.package_id" class="mt-1 text-sm text-rose-500">
                    {{ form.errors.package_id }}
                </p>

                <PlanSelectSummary
                    v-if="selectedPackage"
                    class="mt-3"
                    :plan="selectedPackage"
                    :order-limit="isCatalogSelected ? null : form.limit"
                    :total-cost="getTotalCost"
                />
            </FormSection>

            <FormSection
                v-if="!isCatalogSelected && selectedPackage"
                title="Order quota"
                :step="hideDomain ? '2' : '3'"
                hint="How many orders this legacy plan should cover."
            >
                <OrderLimitPresets v-model="form.limit" />
                <p v-if="form.errors.limit" class="mt-1 text-sm text-rose-500">
                    {{ form.errors.limit }}
                </p>
            </FormSection>

            <FormSection
                v-if="!simplified"
                title="Payment record"
                :step="paymentStep"
                :hint="paymentHint"
                optional
            >
                <div class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Payment method</label>
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

                    <template v-if="form.transaction_method != 'Cash'">
                        <div class="space-y-1">
                            <label class="text-sm font-medium">Transaction number</label>
                            <InputText
                                v-model="form.transaction_number"
                                placeholder="Payment reference number"
                                class="!w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium">Transaction ID</label>
                            <InputText
                                v-model="form.transaction_id"
                                placeholder="Gateway transaction ID"
                                class="!w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium">Gateway charge (TK)</label>
                            <InputNumber
                                :useGrouping="false"
                                :maxFractionDigits="5"
                                v-model="form.transaction_charge"
                                placeholder="0"
                                fluid
                            />
                        </div>
                    </template>

                    <div class="space-y-1">
                        <label for="note" class="text-sm font-medium">Admin note</label>
                        <Textarea
                            id="note"
                            v-model="form.note"
                            autoResize
                            rows="2"
                            placeholder="Optional internal note"
                            class="!w-full"
                        />
                    </div>
                </div>
            </FormSection>

            <details
                v-else-if="!isFreePlan"
                class="rounded-lg border border-gray-100 dark:border-gray-800"
            >
                <summary
                    class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Payment details (optional)
                </summary>
                <div class="space-y-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800">
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
                            placeholder="Optional internal note"
                            class="!w-full"
                        />
                    </div>
                </div>
            </details>
        </template>

        <div
            class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
        >
            <div v-if="mode !== 'adjust'" class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">Total cost</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ totalCostLabel }}
                </p>
            </div>
            <div v-else class="hidden sm:block" />

            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    outlined
                    @click="$emit('onClose')"
                />
                <Button
                    type="submit"
                    :label="submitLabel"
                    icon="pi pi-check"
                    :loading="form.processing"
                />
            </div>
        </div>
    </form>
</template>

<script setup lang="ts">
import DomainFieldHint from "@/components/DomainFieldHint.vue";
import OrderLimitPresets from "@/components/OrderLimitPresets.vue";
import PlanSelectSummary from "@/components/PlanSelectSummary.vue";
import FormSection from "@/components/FormSection.vue";
import {
    groupPlansForSelect,
    isCatalogPackage,
    planDropdownLabel,
} from "@/data/packageCatalogDraft";
import { computed, ref } from "vue";

const props = withDefaults(
    defineProps<{
        form: any;
        packages: any[];
        simplified?: boolean;
        hideDomain?: boolean;
        mode?: "add" | "assign" | "adjust" | "change";
        currentPlan?: any;
    }>(),
    {
        simplified: false,
        hideDomain: false,
        mode: "assign",
        currentPlan: null,
    },
);

defineEmits<{
    handleSave: [];
    onClose: [];
}>();

const groupedPlans = computed(() => groupPlansForSelect(props.packages || []));

const selectedPackage = computed(() =>
    (props.packages || []).find((item) => item.id == props.form.package_id),
);

const isCatalogSelected = computed(() =>
    selectedPackage.value ? isCatalogPackage(selectedPackage.value) : false,
);

const currentPlanDisplayCost = computed(() => {
    if (!props.currentPlan) {
        return 0;
    }

    if (isCatalogPackage(props.currentPlan)) {
        return Number(props.currentPlan.package_price ?? 0);
    }

    return (
        Number(props.currentPlan.per_order_rate ?? 0) *
        Number(props.form.total_order_can_handle ?? 0)
    );
});

const getTotalCost = computed(() => {
    if (!selectedPackage.value) {
        return 0;
    }

    if (isCatalogPackage(selectedPackage.value)) {
        return Number(selectedPackage.value.package_price ?? 0);
    }

    return selectedPackage.value.per_order_rate * props.form.limit || 0;
});

const isFreePlan = computed(() => getTotalCost.value === 0);

const totalCostLabel = computed(() =>
    getTotalCost.value === 0 ? "Free" : `${getTotalCost.value.toLocaleString()} TK`,
);

const introText = computed(() => {
    if (props.mode === "adjust" || props.mode === "change") {
        return null;
    }

    if (props.mode === "add") {
        return "Add a new store by entering its domain and assigning a subscription plan. You can generate a license key after saving.";
    }

    if (props.mode === "assign" && props.form.domain) {
        return `Assign a subscription plan to ${props.form.domain}. Generate a license key afterward if needed.`;
    }

    return null;
});

const planSelectStep = computed(() => {
    if (props.mode === "change") {
        return props.currentPlan ? "2" : "1";
    }

    return props.hideDomain ? "1" : "2";
});

const submitLabel = computed(() => {
    if (props.mode === "adjust") {
        return "Save adjustments";
    }

    if (props.mode === "change") {
        return "Change plan";
    }

    return props.mode === "add" ? "Add Website" : "Assign Plan";
});

const paymentStep = computed(() => {
    if (props.hideDomain) {
        return isCatalogSelected.value ? "2" : "3";
    }

    return isCatalogSelected.value ? "3" : "4";
});

const paymentHint = computed(() =>
    isFreePlan.value
        ? "Optional — free plans do not require payment."
        : "Record how the merchant paid for this assignment.",
);

const onPackageChange = () => {
    if (isCatalogSelected.value) {
        props.form.limit = selectedPackage.value?.order_rate_token ?? 0;
    } else if (!props.form.limit || props.form.limit <= 0) {
        props.form.limit = 300;
    }
};

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
