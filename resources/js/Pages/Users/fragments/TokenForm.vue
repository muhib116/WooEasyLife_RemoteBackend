<template>
    <form class="flex flex-col gap-5" @submit.prevent="$emit('handleSave')">
        <p
            v-if="introText"
            class="rounded-lg border border-gray-100 bg-slate-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-800 dark:bg-slate-900/40 dark:text-gray-300"
        >
            {{ introText }}
        </p>

        <FormSection
            title="Website & plan"
            step="1"
            :hint="websiteStepHint"
        >
            <div
                v-if="hideWebsiteSelect && selectedPackage"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-slate-900/50"
            >
                <div
                    class="border-b border-gray-100 bg-slate-50 px-4 py-3 dark:border-gray-800 dark:bg-slate-800/50"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">
                                {{ selectedPackage.domain }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ selectedPackage.title }}
                            </p>
                        </div>
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="planBadgeClass"
                        >
                            {{ planBadgeLabel }}
                        </span>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800">
                    <div class="bg-white px-4 py-3 dark:bg-slate-900/50">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                            {{ quotaLabel }}
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ quotaSummary }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-slate-900/50">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                            Subscription ends
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ planExpiryLabel }}
                        </dd>
                    </div>
                </dl>
            </div>

            <template v-else>
                <Select
                    id="user_package_id"
                    class="w-full"
                    v-model="tokenForm.user_package_id"
                    :options="user_packages"
                    :optionLabel="packageOptionLabel"
                    optionValue="id"
                    placeholder="Select website and plan"
                    filter
                    filter-placeholder="Search domains..."
                />
                <p v-if="tokenForm.errors.user_package_id" class="mt-1 text-sm text-rose-500">
                    {{ tokenForm.errors.user_package_id }}
                </p>
                <p v-if="tokenForm.errors.domain" class="mt-1 text-sm text-rose-500">
                    {{ tokenForm.errors.domain }}
                </p>
            </template>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                The WooCommerce plugin must use this exact hostname in API requests.
            </p>
        </FormSection>

        <FormSection
            title="License details"
            step="2"
            hint="Optional labels for your admin records — the plugin only needs the generated key."
            optional
        >
            <div class="space-y-4">
                <div class="space-y-1">
                    <label for="title" class="text-sm font-medium">License title</label>
                    <InputText
                        v-model="tokenForm.title"
                        id="title"
                        placeholder="e.g. Main store license"
                        class="!w-full"
                    />
                    <p v-if="tokenForm.errors.title" class="text-sm text-rose-500">
                        {{ tokenForm.errors.title }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="description" class="text-sm font-medium">Description</label>
                    <Textarea
                        v-model="tokenForm.description"
                        id="description"
                        placeholder="Optional notes about this license"
                        class="!w-full"
                        autoResize
                        rows="2"
                    />
                    <p v-if="tokenForm.errors.description" class="text-sm text-rose-500">
                        {{ tokenForm.errors.description }}
                    </p>
                </div>
            </div>
        </FormSection>

        <FormSection
            title="Access"
            step="3"
            :hint="accessStepHint"
        >
            <div class="space-y-4">
                <div
                    class="rounded-xl border px-4 py-3 text-sm"
                    :class="effectiveAccessToneClass"
                >
                    <p class="text-xs font-medium uppercase tracking-wide opacity-80">
                        Effective plugin access until
                    </p>
                    <p class="mt-1 text-base font-semibold">
                        {{ effectiveAccess.label }}
                    </p>
                    <p class="mt-1 text-xs opacity-90">
                        {{ effectiveAccess.detail }}
                    </p>
                    <p
                        v-if="effectiveAccess.limitedBy"
                        class="mt-2 text-xs font-medium"
                    >
                        Limited by:
                        <span class="font-normal">{{ effectiveAccess.limitedBy }}</span>
                    </p>
                </div>

                <div
                    class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2.5 dark:border-gray-800"
                >
                    <div>
                        <p class="text-sm font-medium">Active license</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Disabled keys cannot authenticate the WooCommerce plugin.
                        </p>
                    </div>
                    <ToggleSwitch v-model="tokenForm.status" />
                </div>

                <div
                    class="rounded-lg border border-dashed border-gray-200 dark:border-gray-700"
                >
                    <div
                        class="flex items-center justify-between gap-3 px-3 py-2.5"
                    >
                        <div>
                            <p class="text-sm font-medium">
                                Custom license expiry
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{
                                    isCatalogPlan
                                        ? "Usually leave off — subscription expiry already controls access."
                                        : "Optional cutoff for this key only."
                                }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="useCustomLicenseExpiry" />
                    </div>

                    <div
                        v-if="useCustomLicenseExpiry"
                        class="space-y-2 border-t border-gray-100 px-3 py-3 dark:border-gray-800"
                    >
                        <label for="expires_at" class="text-sm font-medium">
                            License key expires at
                        </label>
                        <DatePicker
                            id="expires_at"
                            v-model="tokenForm.expires_at"
                            show-icon
                            show-time
                            hour-format="12"
                            date-format="yy-mm-dd"
                            placeholder="Pick date and time"
                            class="w-full"
                        />
                        <p v-if="tokenForm.errors.expires_at" class="text-sm text-rose-500">
                            {{ tokenForm.errors.expires_at }}
                        </p>
                        <p
                            v-if="customExpiryWarning"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                        >
                            {{ customExpiryWarning }}
                        </p>
                    </div>
                </div>
            </div>
        </FormSection>

        <div
            v-if="selectedPackage"
            class="rounded-xl border border-primary-100 bg-primary-50/60 px-4 py-3 text-sm dark:border-primary-500/20 dark:bg-primary-500/10"
        >
            <p class="font-semibold text-gray-900 dark:text-white">Ready to connect</p>
            <p class="mt-1 text-gray-600 dark:text-gray-300">
                After saving, copy the license key into the WooEasyLife plugin on
                <strong>{{ selectedPackage.domain }}</strong>.
                When the subscription ends, use <strong>Renew plan</strong> — you do not need
                to regenerate the license key.
            </p>
        </div>

        <div
            class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-end dark:border-gray-800"
        >
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
                    icon="pi pi-key"
                    :loading="tokenForm.processing"
                />
            </div>
        </div>
    </form>
</template>

<script setup lang="ts">
import FormSection from "@/components/FormSection.vue";
import { parseSubscriptionExpiry } from "@/utils/subscriptionExpiry";
import { format } from "date-fns";
import { computed, ref, watch } from "vue";

const props = withDefaults(
    defineProps<{
        tokenForm: any;
        user_packages: any[];
        hideWebsiteSelect?: boolean;
        lockedDomain?: string | null;
    }>(),
    {
        hideWebsiteSelect: false,
        lockedDomain: null,
    },
);

defineEmits<{
    handleSave: [];
    onClose: [];
}>();

const useCustomLicenseExpiry = ref(Boolean(props.tokenForm.expires_at));

watch(useCustomLicenseExpiry, (enabled) => {
    if (!enabled) {
        props.tokenForm.expires_at = null;
    }
});

watch(
    [() => props.tokenForm.id, () => props.tokenForm.expires_at],
    () => {
        useCustomLicenseExpiry.value = Boolean(props.tokenForm.expires_at);
    },
);

const isEdit = computed(() => Boolean(props.tokenForm.id));

const selectedPackage = computed(() =>
    props.user_packages.find(
        (item) => item.id == props.tokenForm.user_package_id,
    ),
);

const isCatalogPlan = computed(
    () => (selectedPackage.value?.plan_type ?? "legacy") === "catalog",
);

const quotaLabel = computed(() =>
    isCatalogPlan.value ? "Token quota" : "Order quota",
);

const quotaSummary = computed(() => {
    if (!selectedPackage.value) {
        return "—";
    }

    const unit = isCatalogPlan.value ? "tokens" : "orders";
    return `${selectedPackage.value.remaining_order?.toLocaleString() ?? 0} of ${selectedPackage.value.total_order_can_handle?.toLocaleString() ?? 0} ${unit} left`;
});

const planBadgeLabel = computed(() =>
    isCatalogPlan.value ? "Catalog plan" : "Legacy plan",
);

const planBadgeClass = computed(() =>
    isCatalogPlan.value
        ? "bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
        : "bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300",
);

const planExpiryDate = computed(() =>
    parseSubscriptionExpiry(selectedPackage.value?.expires_at),
);

const licenseExpiryDate = computed(() => {
    const value = props.tokenForm.expires_at;

    if (!value) {
        return null;
    }

    return value instanceof Date ? value : parseSubscriptionExpiry(value);
});

const planExpiryLabel = computed(() => {
    if (!planExpiryDate.value) {
        return isCatalogPlan.value ? "Not set" : "No end date (legacy)";
    }

    return format(planExpiryDate.value, "MMM d, yyyy");
});

const effectiveAccess = computed(() => {
    const planExpiry = planExpiryDate.value;
    const licenseExpiry = licenseExpiryDate.value;

    if (!planExpiry && !licenseExpiry) {
        if (isCatalogPlan.value) {
            return {
                label: "While subscription is active",
                detail: "No subscription end date on file. Access also requires remaining token quota.",
                limitedBy: "Subscription status and token quota",
                tone: "neutral" as const,
            };
        }

        return {
            label: "While order quota remains",
            detail: "Legacy plans have no subscription end date. Access stops when order quota is used up.",
            limitedBy: "Order quota",
            tone: "neutral" as const,
        };
    }

    if (planExpiry && !licenseExpiry) {
        return {
            label: format(planExpiry, "MMM d, yyyy"),
            detail: isCatalogPlan.value
                ? "Recommended setup: license has no separate expiry. Extend access with Renew plan."
                : "Subscription end date controls when order processing stops.",
            limitedBy: "Subscription plan",
            tone: "info" as const,
        };
    }

    if (!planExpiry && licenseExpiry) {
        return {
            label: format(licenseExpiry, "MMM d, yyyy h:mm a"),
            detail: "Only this license key has an expiry. The subscription has no end date.",
            limitedBy: "Custom license expiry",
            tone: "warning" as const,
        };
    }

    const planFirst =
        planExpiry && licenseExpiry && planExpiry.getTime() <= licenseExpiry.getTime();

    if (planFirst) {
        return {
            label: format(planExpiry!, "MMM d, yyyy"),
            detail: "Subscription ends before the custom license expiry. Renew plan to extend plugin access.",
            limitedBy: "Subscription plan (ends first)",
            tone: "info" as const,
        };
    }

    return {
        label: format(licenseExpiry!, "MMM d, yyyy h:mm a"),
        detail: "Custom license expiry is earlier than the subscription. The plugin stops even if the plan is still valid.",
        limitedBy: "Custom license expiry (ends first)",
        tone: "warning" as const,
    };
});

const effectiveAccessToneClass = computed(() => {
    if (effectiveAccess.value.tone === "warning") {
        return "border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100";
    }

    if (effectiveAccess.value.tone === "info") {
        return "border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100";
    }

    return "border-gray-200 bg-slate-50 text-gray-800 dark:border-gray-700 dark:bg-slate-900/50 dark:text-gray-200";
});

const customExpiryWarning = computed(() => {
    if (!useCustomLicenseExpiry.value || !licenseExpiryDate.value) {
        return null;
    }

    const planExpiry = planExpiryDate.value;

    if (planExpiry && licenseExpiryDate.value.getTime() > planExpiry.getTime()) {
        return "This date is after the subscription ends — it will not extend access beyond the plan expiry.";
    }

    if (planExpiry && licenseExpiryDate.value.getTime() < planExpiry.getTime()) {
        return "This date is before the subscription ends — the plugin will stop at this license expiry even if the plan is still active.";
    }

    if (!planExpiry && isCatalogPlan.value) {
        return "Unusual for catalog plans. Prefer leaving custom expiry off and using subscription renewal instead.";
    }

    return null;
});

const accessStepHint = computed(() => {
    if (isCatalogPlan.value) {
        return "For catalog plans, subscription expiry controls access. Custom license expiry is rarely needed.";
    }

    return "Legacy plans use order quota. Set custom license expiry only for special cases.";
});

const introText = computed(() => {
    if (isEdit.value) {
        return "Update license metadata or access settings. The license key string does not change.";
    }

    if (props.hideWebsiteSelect && props.lockedDomain) {
        return `Generate a license key for ${props.lockedDomain}. One key per site is enough — renew the plan later without regenerating.`;
    }

    return "Link a license key to a website plan. The plugin authenticates using the key and the store hostname.";
});

const websiteStepHint = computed(() => {
    if (props.hideWebsiteSelect) {
        return "This license will be tied to the website below.";
    }

    return "Pick the website and subscription plan this license belongs to.";
});

const submitLabel = computed(() =>
    isEdit.value ? "Save changes" : "Generate license",
);

const packageOptionLabel = (item: any) => {
    const unit = (item.plan_type ?? "legacy") === "catalog" ? "tokens" : "orders";

    return `${item.domain} · ${item.title} (${item.remaining_order ?? 0} ${unit} left)`;
};
</script>
