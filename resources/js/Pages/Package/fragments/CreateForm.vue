<template>
    <form class="space-y-6 pt-3" @submit.prevent="onSubmit">
        <section
            class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-slate-900/40 sm:p-5"
        >
            <div class="flex items-center gap-2 border-b border-gray-200/80 pb-3 dark:border-gray-700/80">
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-300"
                >
                    <i class="pi pi-box text-sm" />
                </span>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">
                    Package details
                </h3>
            </div>

            <div class="space-y-4">
                <div>
                    <label
                        for="package_name"
                        class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                    >
                        Package Name <span class="text-rose-500">*</span>
                    </label>
                    <InputText
                        id="package_name"
                        v-model="draft.package_name"
                        placeholder="e.g. Pro Plus – 1 Month"
                        class="!w-full"
                        autocomplete="off"
                    />
                    <p v-if="errors.package_name" class="mt-1 text-sm text-rose-500">
                        {{ errors.package_name }}
                    </p>
                </div>

                <div
                    :class="[
                        'flex cursor-pointer items-center justify-between gap-4 rounded-xl border px-4 py-3 transition-colors',
                        draft.is_special
                            ? 'border-amber-300 bg-amber-50/90 dark:border-amber-500/35 dark:bg-amber-500/10'
                            : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-slate-950/50',
                    ]"
                    @click="draft.is_special = !draft.is_special"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            :class="[
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                draft.is_special
                                    ? 'bg-amber-200 text-amber-700 dark:bg-amber-500/25 dark:text-amber-300'
                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
                            ]"
                        >
                            <i
                                :class="[
                                    'pi text-sm',
                                    draft.is_special ? 'pi-star-fill' : 'pi-star',
                                ]"
                            />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                Is Special?
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Featured plan — highlighted in the package catalog
                            </p>
                        </div>
                    </div>
                    <ToggleSwitch
                        v-model="draft.is_special"
                        class="pointer-events-none shrink-0"
                    />
                </div>

                <div
                    class="grid gap-4"
                    :class="
                        isFreeTrial
                            ? 'sm:grid-cols-2 lg:grid-cols-4'
                            : 'sm:grid-cols-2 lg:grid-cols-3'
                    "
                >
                    <div>
                        <label
                            for="package_duration"
                            class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Package Duration <span class="text-rose-500">*</span>
                        </label>
                        <Select
                            id="package_duration"
                            v-model="draft.package_duration"
                            :options="durationOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select duration"
                            class="w-full"
                        />
                    </div>

                    <div v-if="isFreeTrial">
                        <label
                            for="trial_days"
                            class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Trial duration (days)
                            <span class="text-rose-500">*</span>
                        </label>
                        <InputNumber
                            id="trial_days"
                            v-model="draft.trial_days"
                            class="w-full"
                            :use-grouping="false"
                            :min="1"
                            suffix=" days"
                            placeholder="e.g. 14"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Active trial length before expiry.
                        </p>
                        <p v-if="errors.trial_days" class="mt-1 text-sm text-rose-500">
                            {{ errors.trial_days }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="package_price"
                            class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Package Pricing <span class="text-rose-500">*</span>
                        </label>
                        <InputNumber
                            id="package_price"
                            v-model="draft.package_price"
                            class="w-full"
                            :use-grouping="false"
                            :min="0"
                            :min-fraction-digits="0"
                            :max-fraction-digits="2"
                            suffix=" TK"
                            placeholder="e.g. 1500"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Total price merchants pay for this package period.
                        </p>
                        <p v-if="errors.package_price" class="mt-1 text-sm text-rose-500">
                            {{ errors.package_price }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="order_rate_token"
                            class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Order Rate Token <span class="text-rose-500">*</span>
                        </label>
                        <InputNumber
                            id="order_rate_token"
                            v-model="draft.order_rate_token"
                            class="w-full"
                            :use-grouping="false"
                            :min="0"
                            placeholder="e.g. 1000"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Usage tokens included for this package period.
                        </p>
                        <p v-if="errors.order_rate_token" class="mt-1 text-sm text-rose-500">
                            {{ errors.order_rate_token }}
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        for="description"
                        class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                    >
                        Features / Description
                    </label>
                    <div
                        class="package-description-editor overflow-hidden rounded-xl border border-gray-200 dark:border-slate-600"
                    >
                        <ClassicEditor v-model="draft.description" />
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Rich text for marketing copy or package feature highlights.
                    </p>
                </div>
            </div>
        </section>

        <section
            class="space-y-4 rounded-xl border border-primary-200 bg-primary-50/40 p-4 dark:border-primary-500/20 dark:bg-primary-500/5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3
                    class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                >
                    Power Full Features
                </h3>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        label="Check all"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="setPowerFeatures(true)"
                    />
                    <Button
                        type="button"
                        label="Uncheck all"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="setPowerFeatures(false)"
                    />
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    v-for="item in powerFeatureDefinitions"
                    :key="item.key"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent px-2 py-1.5 hover:bg-white/60 dark:hover:bg-slate-800/60"
                >
                    <Checkbox
                        v-model="draft.features[item.key]"
                        :input-id="item.key"
                        binary
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ item.label }}
                    </span>
                </label>
            </div>
        </section>

        <div
            class="flex cursor-pointer items-center justify-between rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700"
            @click="draft.is_active = !draft.is_active"
        >
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    Active package
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Only active packages can be assigned to merchants
                </p>
            </div>
            <ToggleSwitch v-model="draft.is_active" class="pointer-events-none" />
        </div>

        <div
            class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700"
        >
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                outlined
                :disabled="saving"
                @click="$emit('onClose')"
            />
            <Button
                type="submit"
                :label="submitLabel"
                icon="pi pi-check"
                :loading="saving"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
import {
    applyFeatureDrivenAppFields,
    buildPackagePayload,
    PACKAGE_DURATION_OPTIONS,
    POWER_FULL_FEATURE_DEFINITIONS,
    setAllFeatures,
    syncDraftAppFields,
} from "@/data/packageCatalogDraft";
import { Classic as ClassicEditor } from "@/plugins/form/editor";
import type { PackageCatalogDraft } from "@/types/packageCatalog";
import { router } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps<{
    draft: PackageCatalogDraft;
    packageId?: number | null;
}>();

const emit = defineEmits<{
    onClose: [];
}>();

const draft = props.draft;
const saving = ref(false);
const isEditing = computed(() => Boolean(props.packageId));
const submitLabel = computed(() =>
    isEditing.value ? "Update Package" : "Save Package",
);
const errors = reactive({
    package_name: "",
    package_price: "",
    order_rate_token: "",
    trial_days: "",
});

const isFreeTrial = computed(() => draft.package_duration === "free_trial");

const durationOptions = PACKAGE_DURATION_OPTIONS;
const powerFeatureDefinitions = POWER_FULL_FEATURE_DEFINITIONS;

watch(
    () => draft.features.app_connect,
    (enabled) => {
        draft.app_connect = enabled;

        if (!enabled) {
            draft.total_website_connect = 1;
        }
    },
);

function setPowerFeatures(enabled: boolean) {
    draft.features = setAllFeatures(
        draft.features,
        enabled,
        POWER_FULL_FEATURE_DEFINITIONS.map((item) => item.key),
    );
    applyFeatureDrivenAppFields(draft);
}

function validate(): boolean {
    errors.package_name = "";
    errors.package_price = "";
    errors.order_rate_token = "";
    errors.trial_days = "";

    if (!draft.package_name.trim()) {
        errors.package_name = "Package name is required.";
    }

    if (draft.package_price == null || draft.package_price < 0) {
        errors.package_price = "Package pricing must be zero or greater.";
    }

    if (draft.order_rate_token == null || draft.order_rate_token < 0) {
        errors.order_rate_token = "Order rate token must be zero or greater.";
    }

    if (
        isFreeTrial.value &&
        (draft.trial_days == null || draft.trial_days < 1)
    ) {
        errors.trial_days = "Trial duration must be at least 1 day.";
    }

    return (
        !errors.package_name &&
        !errors.package_price &&
        !errors.order_rate_token &&
        !errors.trial_days
    );
}

function onSubmit() {
    if (!validate()) {
        return;
    }

    syncDraftAppFields(draft);
    applyFeatureDrivenAppFields(draft);

    const payload = buildPackagePayload(draft);
    saving.value = true;

    const requestOptions = {
        preserveScroll: true,
        onSuccess: () => {
            saving.value = false;
            emit("onClose");
        },
        onError: (serverErrors: Record<string, string>) => {
            saving.value = false;
            errors.package_name = serverErrors.package_name ?? "";
            errors.package_price = serverErrors.package_price ?? "";
            errors.order_rate_token = serverErrors.order_rate_token ?? "";
            errors.trial_days = serverErrors.trial_days ?? "";
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    if (isEditing.value && props.packageId) {
        router.post(route("packages.update", props.packageId), payload, requestOptions);
        return;
    }

    router.post(route("packages.create"), payload, requestOptions);
}
</script>

<style scoped>
.package-description-editor :deep(.ck.ck-editor) {
    width: 100%;
}

.package-description-editor :deep(.ck.ck-toolbar),
.package-description-editor :deep(.ck.ck-editor__top .ck-sticky-panel .ck-toolbar) {
    border: none;
    border-bottom: 1px solid rgb(229 231 235);
    border-radius: 0;
}

.package-description-editor :deep(.ck.ck-editor__main > .ck-editor__editable),
.package-description-editor :deep(.ck-editor__editable_inline) {
    min-height: 14rem;
    max-height: 20rem;
    overflow-y: auto;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0;
    padding: 0.85rem 1rem;
    line-height: 1.55;
}

.package-description-editor :deep(.ck.ck-editor__main > .ck-editor__editable.ck-focused) {
    border: none !important;
    box-shadow: none !important;
}

.package-description-editor :deep(.ck-powered-by) {
    display: none;
}
</style>
