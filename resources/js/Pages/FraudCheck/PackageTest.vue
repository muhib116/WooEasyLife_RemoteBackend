<template>
    <AuthenticatedLayout title="Fraud Package Test">
        <div class="space-y-5">
            <PageHeader
                title="Courier Fraud Checker Test"
                description="Run internal multi-courier fraud checks (Steadfast, Pathao, Paperfly, RedX, Carrybee)"
                icon="PhFlask"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            >
                <template #actions>
                    <Link :href="route('frauds.index')">
                        <Button
                            label="Open Fraud Checker"
                            icon="pi pi-external-link"
                            size="small"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                </template>
            </PageHeader>

            <div class="grid gap-4 lg:grid-cols-3">
                <PageCard title="Checkers" description="App-owned HTTP clients (no third-party fraud package)" class="lg:col-span-1">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Name</dt>
                            <dd class="font-mono text-xs font-medium">{{ package.name }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Version</dt>
                            <dd class="font-medium">{{ package.version }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1 text-gray-500">Couriers</dt>
                            <dd class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="courier in package.couriers"
                                    :key="courier"
                                    class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium capitalize text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    {{ courier }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </PageCard>

                <PageCard
                    title="Credentials"
                    description="Configured in .env / courier-checker.php"
                    class="lg:col-span-2"
                >
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div
                            v-for="(ok, name) in credentials"
                            :key="name"
                            class="rounded-xl border px-3 py-2 text-center text-sm"
                            :class="
                                ok
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                    : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300'
                            "
                        >
                            <p class="text-[10px] font-semibold uppercase tracking-wide opacity-70">
                                {{ name }}
                            </p>
                            <p class="mt-0.5 font-medium">{{ ok ? "Ready" : "Missing" }}</p>
                        </div>
                    </div>
                </PageCard>
            </div>

            <PageCard title="Run check" description="Enter a BD mobile number and compare sources">
                <div class="flex flex-col gap-4 md:flex-row md:items-end">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-gray-500">Phone</label>
                        <InputText
                            v-model="phone"
                            class="w-full"
                            placeholder="017XXXXXXXX"
                            @keyup.enter="runCheck"
                        />
                    </div>
                    <div class="flex flex-wrap gap-4 pb-2 text-sm text-gray-700 dark:text-gray-300">
                        <label class="inline-flex items-center gap-2">
                            <input v-model="includePackage" type="checkbox" class="rounded border-gray-300" />
                            Package
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input v-model="includeInternal" type="checkbox" class="rounded border-gray-300" />
                            Internal
                        </label>
                    </div>
                    <Button
                        label="Run fraud check"
                        icon="pi pi-search"
                        :loading="loading"
                        :disabled="!phone || (!includePackage && !includeInternal)"
                        @click="runCheck"
                    />
                </div>

                <p v-if="error" class="mt-3 text-sm text-rose-600 dark:text-rose-400">
                    {{ error }}
                </p>
            </PageCard>

            <PageCard
                v-if="result?.package?.ok && (result.package.analysis?.length || result.package.repairs?.length)"
                title="Analysis & repairs"
                description="Why package data failed and what we fixed with internal checkers"
            >
                <ul v-if="result.package.analysis?.length" class="mb-3 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                    <li v-for="(note, i) in result.package.analysis" :key="`a-${i}`">{{ note }}</li>
                </ul>
                <div v-if="result.package.repairs?.length" class="space-y-2">
                    <div
                        v-for="(repair, i) in result.package.repairs"
                        :key="`r-${i}`"
                        class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-sm dark:border-emerald-500/30 dark:bg-emerald-500/10"
                    >
                        <p class="font-semibold capitalize text-emerald-800 dark:text-emerald-300">
                            {{ repair.courier }} · {{ repair.action }}
                        </p>
                        <p class="text-xs text-emerald-900/80 dark:text-emerald-200/80">{{ repair.detail }}</p>
                    </div>
                </div>
            </PageCard>

            <div v-if="result" class="grid gap-5 xl:grid-cols-2">
                <PageCard
                    title="Package result (repaired)"
                    :description="result.package ? `${result.package.ms} ms` : 'Not requested'"
                >
                    <div v-if="!result.package" class="text-sm text-gray-500">Skipped</div>
                    <div v-else-if="!result.package.ok" class="text-sm text-rose-600">
                        {{ result.package.error }}
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-if="result.package.data?.aggregate"
                            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                        >
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Success</p>
                                <p class="mt-1 text-lg font-semibold">
                                    {{ result.package.data.aggregate.total_success }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Cancel</p>
                                <p class="mt-1 text-lg font-semibold">
                                    {{ result.package.data.aggregate.total_cancel }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Total</p>
                                <p class="mt-1 text-lg font-semibold">
                                    {{ result.package.data.aggregate.total_deliveries }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Success %</p>
                                <p class="mt-1 text-lg font-semibold">
                                    {{ result.package.data.aggregate.success_ratio }}%
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="key in packageCourierKeys"
                                :key="key"
                                class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"
                            >
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold capitalize">{{ key }}</p>
                                    <span
                                        v-if="packageCourierLabel(key)"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                        :class="
                                            packageCourierLabel(key) === 'internal'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                : 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'
                                        "
                                    >
                                        {{ packageCourierLabel(key) }}
                                    </span>
                                </div>
                                <p
                                    v-if="packageCourierError(key)"
                                    class="text-xs text-rose-600 dark:text-rose-400"
                                >
                                    {{ packageCourierError(key) }}
                                </p>
                                <p v-else class="text-xs text-gray-600 dark:text-gray-400">
                                    success {{ result.package.data[key]?.success ?? 0 }} ·
                                    cancel {{ result.package.data[key]?.cancel ?? 0 }} ·
                                    total {{ result.package.data[key]?.total ?? 0 }} ·
                                    ratio {{ result.package.data[key]?.success_ratio ?? 0 }}%
                                    <template v-if="result.package.data[key]?.customer_rating">
                                        · rating {{ result.package.data[key].customer_rating }}
                                    </template>
                                    <template v-if="result.package.data[key]?.estimated_success_rate">
                                        · est {{ result.package.data[key].estimated_success_rate }}
                                    </template>
                                    <template v-if="result.package.data[key]?.customer_segment">
                                        · {{ result.package.data[key].customer_segment }}
                                    </template>
                                    <template v-if="result.package.data[key]?.frauds_count != null">
                                        · fraud reports {{ result.package.data[key].frauds_count }}
                                    </template>
                                </p>
                            </div>
                        </div>

                        <details class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-gray-500">
                                Repaired JSON
                            </summary>
                            <pre class="overflow-x-auto p-3 text-xs">{{ pretty(result.package.data) }}</pre>
                        </details>
                        <details
                            v-if="result.package.raw"
                            class="rounded-xl border border-amber-200 dark:border-amber-500/30"
                        >
                            <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                                Original package JSON (before repair)
                            </summary>
                            <pre class="overflow-x-auto p-3 text-xs">{{ pretty(result.package.raw) }}</pre>
                        </details>
                    </div>
                </PageCard>

                <PageCard
                    title="Internal result"
                    :description="result.internal ? `${result.internal.ms} ms` : 'Not requested'"
                >
                    <div v-if="!result.internal" class="text-sm text-gray-500">Skipped</div>
                    <div v-else-if="!result.internal.ok" class="text-sm text-rose-600">
                        {{ result.internal.error }}
                    </div>
                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Confirmed</p>
                                <p class="mt-1 text-lg font-semibold">{{ result.internal.data.confirmed }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Cancel</p>
                                <p class="mt-1 text-lg font-semibold">{{ result.internal.data.cancel }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Total</p>
                                <p class="mt-1 text-lg font-semibold">{{ result.internal.data.total_order }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <p class="text-[10px] uppercase tracking-wide text-gray-500">Success</p>
                                <p class="mt-1 text-lg font-semibold">{{ result.internal.data.success_rate }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="courier in result.internal.data.courier || []"
                                :key="courier.title"
                                class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"
                            >
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold">{{ courier.title }}</p>
                                    <span class="text-xs text-gray-500">
                                        {{ courier.report?.success_rate }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    total {{ courier.report?.total_order ?? 0 }} ·
                                    confirmed {{ courier.report?.confirmed ?? 0 }} ·
                                    cancel {{ courier.report?.cancel ?? 0 }}
                                    <template v-if="courier.report?.customer_rating">
                                        · rating {{ courier.report.customer_rating }}
                                    </template>
                                </p>
                            </div>
                        </div>

                        <details class="rounded-xl border border-gray-200 dark:border-gray-700">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-gray-500">
                                Raw JSON
                            </summary>
                            <pre class="overflow-x-auto p-3 text-xs">{{ pretty(result.internal.data) }}</pre>
                        </details>
                    </div>
                </PageCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineProps<{
    package: {
        name: string;
        version: string;
        couriers: string[];
    };
    credentials: Record<string, boolean>;
}>();

const packageCourierKeys = ["steadfast", "pathao", "redx", "paperfly", "carrybee"] as const;

const phone = ref("01770989591");
const includePackage = ref(true);
const includeInternal = ref(true);
const loading = ref(false);
const error = ref("");
const result = ref<any>(null);

const pretty = (data: unknown) => JSON.stringify(data, null, 2);

const packageCourierError = (key: string) => {
    const row = result.value?.package?.data?.[key];
    if (!row) {
        return "No data";
    }
    if (row.unavailable) {
        return String(row.message || row.error || "Unavailable");
    }
    if (row.error && row.source === "package") {
        return String(row.error) + (row.message ? `: ${row.message}` : "");
    }
    return "";
};

const packageCourierLabel = (key: string) => {
    const row = result.value?.package?.data?.[key];
    if (!row) {
        return "";
    }
    if (row.source === "internal") {
        return "internal";
    }
    if (row.source === "package") {
        return "package";
    }
    return "";
};

const runCheck = async () => {
    error.value = "";
    loading.value = true;
    result.value = null;

    const sources: string[] = [];
    if (includePackage.value) {
        sources.push("package");
    }
    if (includeInternal.value) {
        sources.push("internal");
    }

    try {
        const { data } = await axios.post(route("fraudPackageTest.check"), {
            phone: phone.value,
            sources,
        });
        result.value = data;
    } catch (e: any) {
        error.value =
            e?.response?.data?.message ||
            e?.message ||
            "Unable to complete fraud package test.";
    } finally {
        loading.value = false;
    }
};
</script>
