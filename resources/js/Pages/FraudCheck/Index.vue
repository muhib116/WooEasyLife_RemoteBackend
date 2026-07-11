<template>
    <component
        :is="$page.props?.auth?.user?.id ? AuthenticatedLayout : 'div'"
        title="Fraud Checker"
    >
        <div class="space-y-5">
            <PageHeader
                v-if="$page.props?.auth?.user?.id"
                title="Fraud Checker"
                description="Check customer delivery success rate across couriers"
                icon="PhUserCheck"
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
            />

            <PageCard
                title="Developer: cache & mode"
                description="Runtime overrides for fraud-check caching. Does not edit .env — reset clears DB overrides."
            >
                <div class="space-y-5">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-600 dark:bg-slate-900/40">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <label
                                    for="fraud-mode"
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    Fraud check mode
                                </label>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ runtimeConfig?.fields?.mode?.help }}
                                </p>
                            </div>
                            <span
                                v-if="isOverridden('mode')"
                                class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                            >
                                overridden
                            </span>
                        </div>
                        <Dropdown
                            id="fraud-mode"
                            v-model="configForm.mode"
                            :options="modeOptions"
                            option-label="label"
                            option-value="value"
                            class="mt-3 w-full"
                            :pt="{ root: { class: 'w-full' } }"
                        />
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Cache behavior
                        </p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="toggle in cacheToggles"
                                :key="toggle.key"
                                class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-600 dark:bg-slate-900/30"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <label
                                            :for="`cfg-${toggle.key}`"
                                            class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-100"
                                        >
                                            {{ toggle.label }}
                                        </label>
                                        <ToggleSwitch
                                            :input-id="`cfg-${toggle.key}`"
                                            v-model="configForm[toggle.key]"
                                            :aria-label="toggle.label"
                                        />
                                    </div>
                                    <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                        {{ toggle.help }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Timing
                        </p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="field in timingFields"
                                :key="field.key"
                                class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-600 dark:bg-slate-900/30"
                            >
                                <label
                                    :for="`cfg-${field.key}`"
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{ field.label }}
                                </label>
                                <InputNumber
                                    :input-id="`cfg-${field.key}`"
                                    v-model="configForm[field.key]"
                                    :min="field.min"
                                    :max="field.max"
                                    class="mt-2 w-full"
                                    input-class="w-full"
                                />
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ field.help }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 rounded-xl border border-dashed border-violet-300 bg-violet-50/70 p-4 dark:border-violet-500/40 dark:bg-violet-500/10 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-violet-900 dark:text-violet-200">
                                Developer debug trail
                            </p>
                            <p class="mt-1 text-xs leading-relaxed text-violet-800/80 dark:text-violet-300/80">
                                Shows a step-by-step decision log on this admin page after each check.
                                Never sent to plugin <code class="text-[11px]">/api/fraud-check</code> responses.
                                When saved, also writes Laravel <code class="text-[11px]">Log::debug</code> lines.
                            </p>
                        </div>
                        <ToggleSwitch
                            input-id="cfg-debug_trace"
                            v-model="configForm.debug_trace"
                            aria-label="Developer debug trail"
                        />
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <p
                            v-if="configMessage"
                            class="text-xs"
                            :class="configError
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ configMessage }}
                        </p>
                        <span v-else class="text-xs text-slate-400">Changes apply on save</span>
                        <div class="ml-auto flex flex-wrap items-center gap-2">
                            <Button
                                label="Reset to .env"
                                severity="secondary"
                                outlined
                                size="small"
                                :loading="configResetting"
                                :disabled="configSaving"
                                @click="resetRuntimeConfig"
                            />
                            <Button
                                label="Save settings"
                                icon="pi pi-save"
                                size="small"
                                :loading="configSaving"
                                :disabled="configResetting"
                                @click="saveRuntimeConfig"
                            />
                        </div>
                    </div>
                </div>
            </PageCard>

            <PageCard title="Phone Lookup" description="Enter a Bangladesh mobile number to check">
                <div class="mx-auto max-w-md space-y-3">
                    <InputGroup>
                        <InputMask
                            v-model="form.phone"
                            mask="99999-999999"
                            placeholder="01XXX-XXXXXX"
                            class="w-full"
                        />
                        <Button
                            label="Check"
                            icon="pi pi-search"
                            :loading="isLoading"
                            @click="handleSearch"
                        />
                    </InputGroup>

                    <div
                        v-if="props.debugMode"
                        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-amber-300 bg-amber-50/60 px-4 py-3 dark:border-amber-500/40 dark:bg-amber-500/10"
                    >
                        <p class="text-xs font-medium text-amber-800 dark:text-amber-300">
                            Debug: expire partner login sessions only (does not clear platform fraud cache)
                        </p>
                        <Button
                            label="Expire courier sessions"
                            icon="pi pi-sign-out"
                            severity="danger"
                            outlined
                            size="small"
                            :loading="expiringSession"
                            @click="expireSessions"
                        />
                        <p
                            v-if="sessionExpireMessage"
                            class="text-xs"
                            :class="sessionExpireError
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ sessionExpireMessage }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                >
                    {{ errorMessage }}
                </div>

                <div class="flex min-h-[280px] flex-col items-center justify-center py-8">
                    <div
                        v-if="isLoading"
                        class="flex flex-col items-center gap-4"
                    >
                        <div class="skeleton-block h-12 w-12 rounded-full" />
                        <div class="skeleton-block h-3 w-32" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Checking courier history...
                        </p>
                    </div>
                    <img
                        v-else-if="!response"
                        :src="SecurityOn"
                        alt=""
                        class="max-w-[220px] opacity-80"
                    />
                    <div v-else class="mx-auto w-full max-w-2xl space-y-6">
                        <div
                            v-if="responseSourceLabel"
                            class="rounded-lg border px-3 py-2 text-center text-xs font-medium"
                            :class="responseSourceClass"
                        >
                            {{ responseSourceLabel }}
                        </div>

                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center dark:border-slate-600 dark:bg-slate-900/40"
                        >
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                Success Rate
                            </p>
                            <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ response?.success_rate }}
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div
                                class="flex flex-col items-center rounded-xl border border-slate-200 p-3 dark:border-slate-600"
                            >
                                <span class="text-xs font-medium text-slate-500">Total Orders</span>
                                <p class="text-2xl font-bold">{{ response?.total_order ?? 0 }}</p>
                            </div>
                            <div
                                class="flex flex-col items-center rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-600 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                            >
                                <span class="text-xs font-medium">Delivered</span>
                                <p class="text-2xl font-bold">{{ response?.confirmed ?? 0 }}</p>
                            </div>
                            <div
                                class="flex flex-col items-center rounded-xl border border-rose-200 bg-rose-50 p-3 text-rose-600 dark:border-rose-500/30 dark:bg-rose-500/10"
                            >
                                <span class="text-xs font-medium">Cancelled</span>
                                <p class="text-2xl font-bold">{{ response?.cancel ?? 0 }}</p>
                            </div>
                        </div>

                        <div v-if="response?.courier?.length" class="space-y-3">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                                Courier breakdown
                            </p>
                            <div
                                v-for="item in response.courier"
                                :key="item.title"
                                class="rounded-xl border border-slate-200 p-3 dark:border-slate-600"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ item.title }}</span>
                                    <span
                                        class="text-sm font-semibold"
                                        :class="courierRateClass(item.report)"
                                    >
                                        {{ item.report?.success_rate }}
                                    </span>
                                </div>
                                <p
                                    v-if="item.report?.total_order > 0"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ item.report.confirmed }} delivered ·
                                    {{ item.report.cancel }} cancelled
                                    <span
                                        v-if="courierCacheLabel(item.report)"
                                        class="text-amber-600 dark:text-amber-400"
                                    >
                                        · {{ courierCacheLabel(item.report) }}
                                    </span>
                                </p>
                                <p
                                    v-else-if="item.report?.data_type === 'rating'"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Rating only (no delivery counts)
                                    <span v-if="item.report.estimated_success_rate">
                                        · est. {{ item.report.estimated_success_rate }}
                                    </span>
                                    <span
                                        v-if="courierCacheLabel(item.report)"
                                        class="text-amber-600 dark:text-amber-400"
                                    >
                                        · {{ courierCacheLabel(item.report) }}
                                    </span>
                                </p>
                                <p
                                    v-else-if="item.report?.data_type === 'fraud_reports'"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Fraud reports:
                                    {{ item.report.frauds_count ?? 0 }}
                                    <span
                                        v-if="courierCacheLabel(item.report)"
                                        class="text-amber-600 dark:text-amber-400"
                                    >
                                        · {{ courierCacheLabel(item.report) }}
                                    </span>
                                </p>
                                <p
                                    v-else-if="item.report?.message"
                                    class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    {{ item.report.message }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </PageCard>

            <PageCard
                v-if="debugSteps.length"
                title="Debug trail"
                description="Decision steps for the last check — useful to verify cache vs live behavior"
            >
                <div class="space-y-2">
                    <div
                        v-for="(step, index) in debugSteps"
                        :key="`${step.at}-${index}`"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-600 dark:bg-slate-900/40"
                    >
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="font-mono text-[11px] text-slate-400">{{ step.at }}</span>
                            <span
                                class="rounded bg-slate-200 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                            >
                                {{ step.step }}
                            </span>
                            <span class="text-sm text-slate-700 dark:text-slate-200">{{ step.message }}</span>
                        </div>
                        <pre
                            v-if="step.context && Object.keys(step.context).length"
                            class="mt-2 max-h-40 overflow-auto rounded bg-slate-900/90 p-2 text-[11px] leading-relaxed text-emerald-300"
                        >{{ formatDebugContext(step.context) }}</pre>
                    </div>
                </div>
            </PageCard>

            <PageCard
                v-if="slangs?.length"
                title="Fraud Reports"
                :description="`${slangs.length} reported issue${slangs.length === 1 ? '' : 's'}`"
            >
                <SlangItems :items="slangs" />
            </PageCard>
        </div>

        <div
            v-if="props.debugMode && $page.props?.auth?.user?.id"
            class="mt-4 flex justify-center"
        >
            <Link
                :href="route('frauds.expire')"
                class="text-sm text-primary-600 hover:underline dark:text-primary-400"
            >
                Admin: Token & CURL settings
            </Link>
        </div>
    </component>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm, Link } from "@inertiajs/vue3";
import SecurityOn from "@/images/security_on.svg";
import { ref, reactive, computed } from "vue";
import axios from "axios";
import SlangItems from "./SlangItems.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({
    name: "FraudCheck",
});

type RuntimeConfigSnapshot = {
    values: Record<string, any>;
    defaults: Record<string, any>;
    overrides: Record<string, any>;
    fields: Record<string, any>;
};

const props = defineProps({
    debugMode: { type: Boolean, default: false },
    runtimeConfig: {
        type: Object as () => RuntimeConfigSnapshot | null,
        default: null,
    },
});

const isLoading = ref(false);
const expiringSession = ref(false);
const response = ref<any>(null);
const errorMessage = ref("");
const sessionExpireMessage = ref("");
const sessionExpireError = ref(false);
const slangs = ref<any[]>([]);
const configSaving = ref(false);
const configResetting = ref(false);
const configMessage = ref("");
const configError = ref(false);
const runtimeConfig = ref<RuntimeConfigSnapshot | null>(props.runtimeConfig);

const modeOptions = [
    { label: "hybrid (cache first)", value: "hybrid" },
    { label: "external_only (always live)", value: "external_only" },
    { label: "platform_first", value: "platform_first" },
];

const configForm = reactive({
    mode: "hybrid",
    stale_while_revalidate: true,
    preserve_snapshot_on_failure: true,
    partial_refresh: true,
    max_snapshot_staleness_hours: 5,
    refresh_unique_for_seconds: 900,
    min_platform_orders: 1,
    debug_trace: false,
});

const cacheToggles = computed(() => [
    {
        key: "stale_while_revalidate" as const,
        label: "Stale while revalidate",
        help: runtimeConfig.value?.fields?.stale_while_revalidate?.help
            ?? "Serve cache now; refresh stale/failed couriers in the background",
    },
    {
        key: "preserve_snapshot_on_failure" as const,
        label: "Preserve on failure",
        help: runtimeConfig.value?.fields?.preserve_snapshot_on_failure?.help
            ?? "Keep last good courier data when a partner fetch fails",
    },
    {
        key: "partial_refresh" as const,
        label: "Partial refresh",
        help: runtimeConfig.value?.fields?.partial_refresh?.help
            ?? "Background refresh only failed/stale couriers",
    },
]);

const timingFields = computed(() => [
    {
        key: "max_snapshot_staleness_hours" as const,
        label: "Freshness (hours)",
        min: 1,
        max: 168,
        help: runtimeConfig.value?.fields?.max_snapshot_staleness_hours?.help
            ?? "How long snapshots stay fresh",
    },
    {
        key: "refresh_unique_for_seconds" as const,
        label: "Cooldown (seconds)",
        min: 60,
        max: 86400,
        help: runtimeConfig.value?.fields?.refresh_unique_for_seconds?.help
            ?? "Min wait before another background refresh",
    },
    {
        key: "min_platform_orders" as const,
        label: "Min platform orders",
        min: 0,
        max: 1000,
        help: runtimeConfig.value?.fields?.min_platform_orders?.help
            ?? "Orders needed for platform sufficiency",
    },
]);

const applyConfigSnapshot = (snapshot: RuntimeConfigSnapshot | null | undefined) => {
    if (!snapshot?.values) {
        return;
    }

    runtimeConfig.value = snapshot;
    configForm.mode = String(snapshot.values.mode ?? "hybrid");
    configForm.stale_while_revalidate = Boolean(snapshot.values.stale_while_revalidate);
    configForm.preserve_snapshot_on_failure = Boolean(snapshot.values.preserve_snapshot_on_failure);
    configForm.partial_refresh = Boolean(snapshot.values.partial_refresh);
    configForm.max_snapshot_staleness_hours = Number(snapshot.values.max_snapshot_staleness_hours ?? 5);
    configForm.refresh_unique_for_seconds = Number(snapshot.values.refresh_unique_for_seconds ?? 900);
    configForm.min_platform_orders = Number(snapshot.values.min_platform_orders ?? 1);
    configForm.debug_trace = Boolean(snapshot.values.debug_trace);
};

applyConfigSnapshot(props.runtimeConfig);

const isOverridden = (key: string) =>
    Object.prototype.hasOwnProperty.call(runtimeConfig.value?.overrides ?? {}, key);

const form = useForm({
    phone: "",
});

const debugSteps = computed(() => {
    const steps = response.value?._debug?.steps;
    return Array.isArray(steps) ? steps : [];
});

const formatDebugContext = (context: Record<string, unknown>) =>
    JSON.stringify(context, null, 2);

const responseSourceLabel = computed(() => {
    const source = String(response.value?.source ?? "");

    if (source === "platform") {
        return "Served from platform cache (no live courier calls for this response)";
    }

    if (source === "hybrid") {
        return "Live courier check + platform enrichment";
    }

    if (source === "external") {
        return "Live courier check (no prior cache)";
    }

    return "";
});

const responseSourceClass = computed(() => {
    const source = String(response.value?.source ?? "");

    if (source === "platform") {
        return "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300";
    }

    if (source === "hybrid") {
        return "border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300";
    }

    return "border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300";
});

const courierCacheLabel = (report: any) => {
    if (!report) {
        return "";
    }

    if (report.cache_label) {
        return String(report.cache_label);
    }

    if (report.from_cache || report.source === "platform_cache") {
        return "platform cache";
    }

    return "";
};

const courierRateClass = (report: any) => {
    const rate = String(report?.success_rate ?? "").toLowerCase();

    if (rate.includes("good") || rate.includes("excellent") || rate.includes("%")) {
        return "text-emerald-600 dark:text-emerald-400";
    }

    if (rate.includes("poor") || rate.includes("risky") || rate.includes("average")) {
        return "text-rose-600 dark:text-rose-400";
    }

    return "text-slate-500";
};

const saveRuntimeConfig = async () => {
    configSaving.value = true;
    configMessage.value = "";
    configError.value = false;

    try {
        const { data } = await axios.put(route("frauds.updateRuntimeConfig"), { ...configForm });
        applyConfigSnapshot(data?.config);
        configMessage.value = data?.message || "Settings saved.";
    } catch (error: any) {
        configError.value = true;
        const errors = error?.response?.data?.errors;
        const firstError = errors
            ? Object.values(errors).flat()[0]
            : null;
        configMessage.value =
            (typeof firstError === "string" ? firstError : null) ||
            error?.response?.data?.message ||
            "Unable to save settings.";
    } finally {
        configSaving.value = false;
    }
};

const resetRuntimeConfig = async () => {
    configResetting.value = true;
    configMessage.value = "";
    configError.value = false;

    try {
        const { data } = await axios.post(route("frauds.resetRuntimeConfig"));
        applyConfigSnapshot(data?.config);
        configMessage.value = data?.message || "Settings reset to .env defaults.";
    } catch (error: any) {
        configError.value = true;
        configMessage.value =
            error?.response?.data?.message || "Unable to reset settings.";
    } finally {
        configResetting.value = false;
    }
};

const expireSessions = async () => {
    expiringSession.value = true;
    sessionExpireMessage.value = "";
    sessionExpireError.value = false;

    try {
        const { data } = await axios.post(route("frauds.expireSession"));
        const cleared = data?.cleared
            ? ` (Steadfast: ${data.cleared.steadfast ? "cleared" : "none"}, Pathao: ${data.cleared.pathao ? "cleared" : "none"}, Paperfly: ${data.cleared.paperfly ? "cleared" : "none"}, RedX: ${data.cleared.redx ? "cleared" : "none"}, Carrybee: ${data.cleared.carrybee ? "cleared" : "none"})`
            : "";
        sessionExpireMessage.value = (data?.message || "Courier sessions expired.") + cleared;
    } catch (error: any) {
        sessionExpireError.value = true;
        sessionExpireMessage.value =
            error?.response?.data?.message ||
            "Unable to expire courier sessions. Please try again.";
    } finally {
        expiringSession.value = false;
    }
};

const handleSearch = async () => {
    if (!form.phone) {
        return;
    }

    isLoading.value = true;
    response.value = null;
    errorMessage.value = "";
    slangs.value = [];

    try {
        const phone = String(form.phone).replace(/-/g, "");
        const { data } = await axios.post(
            route("frauds.adminFraudCheck"),
            {
                phone,
                debug: Boolean(configForm.debug_trace),
            },
            { timeout: 120000 },
        );
        response.value = data;
        slangs.value = data?.frauds || [];
        if (configForm.debug_trace && data?._debug) {
            // eslint-disable-next-line no-console
            console.groupCollapsed("[fraud-check] debug trail");
            // eslint-disable-next-line no-console
            console.log(data._debug);
            // eslint-disable-next-line no-console
            console.groupEnd();
        }    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Unable to complete fraud check. Please try again.";
    } finally {
        isLoading.value = false;
    }
};
</script>

<style scoped>
.loader {
    width: 120px;
    height: 22px;
    border-radius: 40px;
    color: var(--p-primary-color);
    border: 2px solid;
    position: relative;
}
.loader::before {
    content: "";
    position: absolute;
    margin: 2px;
    width: 25%;
    top: 0;
    bottom: 0;
    left: 0;
    border-radius: inherit;
    background: currentColor;
    animation: fraud-loader 1s infinite linear;
}
@keyframes fraud-loader {
    50% {
        left: 100%;
        transform: translateX(calc(-100% - 4px));
    }
}
</style>
