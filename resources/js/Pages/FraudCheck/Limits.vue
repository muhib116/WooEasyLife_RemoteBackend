<template>
    <AuthenticatedLayout title="Bulk check">
        <div class="space-y-5">
            <PageHeader
                title="Bulk check"
                description="How many numbers run at once, how fast each courier is asked, and how long a number waits for a courier. Free daily checks are below. Saved numbers replace the .env defaults."
                icon="PhSlidersHorizontal"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            />

            <p
                v-if="savedMessage"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                {{ savedMessage }}
            </p>

            <PageCard
                title="Bulk check"
                description="How many numbers run at once, how fast each courier is asked, and how long a number waits for a courier. Saved numbers replace the .env defaults. A number that matches .env is not stored."
            >
                <form class="space-y-4" @submit.prevent="saveBulk">
                    <div
                        v-for="field in bulkFields"
                        :key="field.key"
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-600 dark:bg-slate-900/30"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <label
                                    :for="`bulk-${field.key}`"
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{ field.label }}
                                </label>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ field.help }}
                                </p>
                            </div>
                            <span
                                v-if="isBulkOverridden(field.key)"
                                class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                            >
                                saved here
                            </span>
                            <span
                                v-else
                                class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                .env default
                            </span>
                        </div>
                        <InputNumber
                            :input-id="`bulk-${field.key}`"
                            v-model="bulkForm[field.key]"
                            :min="field.min"
                            :max="field.max"
                            :use-grouping="false"
                            class="mt-3 w-full max-w-xs"
                            input-class="w-full"
                        />
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            .env default: {{ field.defaultValue }}. Allowed {{ field.min }}–{{ field.max }}.
                        </p>
                        <p v-if="bulkForm.errors[field.key]" class="mt-1 text-sm text-rose-600">
                            {{ bulkForm.errors[field.key] }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            v-if="hasBulkOverride"
                            type="button"
                            label="Use .env defaults"
                            severity="secondary"
                            outlined
                            class="!min-h-11 w-full sm:w-auto"
                            :loading="resettingBulk"
                            @click="resetBulkDefaults"
                        />
                        <Button
                            type="submit"
                            label="Save bulk settings"
                            icon="pi pi-save"
                            class="!min-h-11 w-full sm:w-auto"
                            :loading="bulkForm.processing"
                        />
                    </div>
                </form>
            </PageCard>

            <PageCard
                title="Free checks per day"
                description="Saved numbers replace the .env defaults. A number that matches .env is not stored."
            >
                <form class="space-y-4" @submit.prevent="save">
                    <div
                        v-for="field in fields"
                        :key="field.key"
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-600 dark:bg-slate-900/30"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <label
                                    :for="`quota-${field.key}`"
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{ field.label }}
                                </label>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ field.help }}
                                </p>
                            </div>
                            <span
                                v-if="isOverridden(field.key)"
                                class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                            >
                                saved here
                            </span>
                            <span
                                v-else
                                class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                .env default
                            </span>
                        </div>
                        <InputNumber
                            :input-id="`quota-${field.key}`"
                            v-model="form[field.key]"
                            :min="field.min"
                            :max="field.max"
                            :use-grouping="false"
                            class="mt-3 w-full max-w-xs"
                            input-class="w-full"
                        />
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            .env default: {{ field.defaultValue }}. Allowed {{ field.min }}–{{ field.max }}.
                        </p>
                        <p v-if="form.errors[field.key]" class="mt-1 text-sm text-rose-600">
                            {{ form.errors[field.key] }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            v-if="hasOverride"
                            type="button"
                            label="Use .env defaults"
                            severity="secondary"
                            outlined
                            class="!min-h-11 w-full sm:w-auto"
                            :loading="resetting"
                            @click="resetDefaults"
                        />
                        <Button
                            type="submit"
                            label="Save limits"
                            icon="pi pi-save"
                            class="!min-h-11 w-full sm:w-auto"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import InputNumber from "primevue/inputnumber";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({
    name: "FraudCheckLimits",
});

type QuotaField = {
    min?: number;
    max?: number;
    label?: string;
    help?: string;
};

type QuotaSnapshot = {
    values: Record<string, number>;
    defaults: Record<string, number>;
    overrides: Record<string, number>;
    fields: Record<string, QuotaField>;
};

const props = defineProps({
    quotas: {
        type: Object as () => QuotaSnapshot,
        required: true,
    },
    bulk: {
        type: Object as () => QuotaSnapshot,
        default: () => ({
            values: {},
            defaults: {},
            overrides: {},
            fields: {},
        }),
    },
});

const bulkKeys = [
    "bulk_workers",
    "bulk_incomplete_retries",
    "bulk_incomplete_retry_gap_seconds",
    "bulk_waiting_max_minutes",
    "pathao_per_minute",
    "pathao_live_reserve",
    "paperfly_gap_seconds",
    "paperfly_pause_minutes",
    "redx_sample_skip_minutes",
    "bulk_missing_courier_retries",
    "bulk_number_budget_seconds",
    "bulk_stale_check_seconds",
    "courier_retry_pause_after_failures",
    "courier_retry_cooldown_seconds",
] as const;

function bulkFormState(values: Record<string, number> | undefined) {
    return Object.fromEntries(bulkKeys.map((key) => [key, Number(values?.[key] ?? 0)]));
}

const page = usePage();
const resetting = ref(false);
const resettingBulk = ref(false);
const savedMessage = computed(() => {
    const flash = page.props.flash as { success?: string } | undefined;

    return flash?.success || "";
});

const form = useForm({
    landing_daily_free_limit: Number(props.quotas.values.landing_daily_free_limit ?? 5),
    plugin_daily_free_limit: Number(props.quotas.values.plugin_daily_free_limit ?? 10),
});

const bulkForm = useForm(bulkFormState(props.bulk?.values));

watch(
    () => props.quotas.values,
    (values) => {
        form.landing_daily_free_limit = Number(values?.landing_daily_free_limit ?? 5);
        form.plugin_daily_free_limit = Number(values?.plugin_daily_free_limit ?? 10);
    },
);

watch(
    () => props.bulk?.values,
    (values) => {
        Object.assign(bulkForm, bulkFormState(values));
    },
);

const fields = computed(() => [
    {
        key: "landing_daily_free_limit" as const,
        label: props.quotas.fields?.landing_daily_free_limit?.label || "Hub page daily free checks",
        help: props.quotas.fields?.landing_daily_free_limit?.help || "",
        min: props.quotas.fields?.landing_daily_free_limit?.min ?? 1,
        max: props.quotas.fields?.landing_daily_free_limit?.max ?? 1000,
        defaultValue: props.quotas.defaults?.landing_daily_free_limit ?? 5,
    },
    {
        key: "plugin_daily_free_limit" as const,
        label: props.quotas.fields?.plugin_daily_free_limit?.label || "Plugin daily free checks",
        help: props.quotas.fields?.plugin_daily_free_limit?.help || "",
        min: props.quotas.fields?.plugin_daily_free_limit?.min ?? 1,
        max: props.quotas.fields?.plugin_daily_free_limit?.max ?? 1000,
        defaultValue: props.quotas.defaults?.plugin_daily_free_limit ?? 10,
    },
]);

const hasOverride = computed(() => Object.keys(props.quotas.overrides ?? {}).length > 0);

const isOverridden = (key: string) =>
    Object.prototype.hasOwnProperty.call(props.quotas.overrides ?? {}, key);

const bulkFields = computed(() => bulkKeys.map((key) => ({
    key,
    label: props.bulk?.fields?.[key]?.label || key,
    help: props.bulk?.fields?.[key]?.help || "",
    min: props.bulk?.fields?.[key]?.min ?? 0,
    max: props.bulk?.fields?.[key]?.max ?? 1000,
    defaultValue: props.bulk?.defaults?.[key] ?? 0,
})));

const hasBulkOverride = computed(() => Object.keys(props.bulk?.overrides ?? {}).length > 0);

const isBulkOverridden = (key: string) =>
    Object.prototype.hasOwnProperty.call(props.bulk?.overrides ?? {}, key);

const save = () => {
    form.put(route("frauds.limits.update"), {
        preserveScroll: true,
    });
};

const resetDefaults = () => {
    resetting.value = true;

    router.post(route("frauds.limits.reset"), {}, {
        preserveScroll: true,
        onFinish: () => {
            resetting.value = false;
        },
    });
};

const saveBulk = () => {
    bulkForm.put(route("frauds.limits.bulk.update"), {
        preserveScroll: true,
    });
};

const resetBulkDefaults = () => {
    resettingBulk.value = true;

    router.post(route("frauds.limits.bulk.reset"), {}, {
        preserveScroll: true,
        onFinish: () => {
            resettingBulk.value = false;
        },
    });
};
</script>
