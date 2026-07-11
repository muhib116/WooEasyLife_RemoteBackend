<template>
    <AuthenticatedLayout title="Database Migrations">
        <div class="space-y-5">
            <PageHeader
                title="Database Migrations"
                description="Run pending Laravel migrations and safely roll back recent batches from the admin panel"
                icon="PhStack"
                icon-bg-class="bg-indigo-50 dark:bg-indigo-500/15"
                icon-class="text-indigo-600 dark:text-indigo-400"
            >
                <template #actions>
                    <Button
                        label="Dry run"
                        icon="pi pi-eye"
                        severity="secondary"
                        outlined
                        size="small"
                        class="!inline-flex shrink-0 whitespace-nowrap"
                        :loading="running && pretend"
                        :disabled="busy"
                        @click="runMigrate(true)"
                    />
                    <Button
                        label="Run migrations"
                        icon="pi pi-play"
                        size="small"
                        class="!inline-flex shrink-0 whitespace-nowrap"
                        :loading="running && !pretend"
                        :disabled="busy || status.pending_count === 0"
                        @click="confirmMigrate"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        class="!inline-flex shrink-0 whitespace-nowrap"
                        :loading="loading"
                        :disabled="busy && !loading"
                        @click="loadStatus"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Pending"
                    :value="status.pending_count"
                    icon="PhHourglass"
                    subtitle="Waiting to run"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
                <StatCard
                    title="Ran"
                    :value="status.ran_count"
                    icon="PhCheckCircle"
                    subtitle="Already applied"
                />
                <StatCard
                    title="Latest batch"
                    :value="status.latest_batch ?? '—'"
                    icon="PhStackSimple"
                    subtitle="Most recent migration batch"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
            </div>

            <PageCard
                title="Connection"
                :description="`Using database connection “${status.connection}”`"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
                    :class="status.repository_ready
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                        : 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'"
                >
                    <div class="flex items-center gap-3">
                        <i
                            class="pi text-lg"
                            :class="status.repository_ready
                                ? 'pi-check-circle text-emerald-600'
                                : 'pi-exclamation-triangle text-amber-600'"
                        />
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ status.repository_ready
                                    ? 'Migrations table is ready'
                                    : 'Migrations table is missing — run migrations to create it' }}
                            </p>
                            <p class="text-xs text-slate-600 dark:text-slate-300">
                                Prefer a backup before rollback. Rollback is limited to 1–5 steps.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <InputNumber
                            v-model="rollbackSteps"
                            :min="1"
                            :max="5"
                            show-buttons
                            class="w-28"
                            input-class="w-full"
                        />
                        <Button
                            label="Rollback"
                            icon="pi pi-undo"
                            severity="danger"
                            outlined
                            size="small"
                            class="!inline-flex shrink-0 whitespace-nowrap"
                            :loading="rollingBack"
                            :disabled="busy || status.ran_count === 0"
                            @click="confirmRollback"
                        />
                    </div>
                </div>
            </PageCard>

            <PageCard
                title="Pending migrations"
                :description="status.pending_count
                    ? `${status.pending_count} file${status.pending_count === 1 ? '' : 's'} not applied yet`
                    : 'Database is up to date'"
            >
                <div
                    v-if="!status.pending.length"
                    class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-600"
                >
                    No pending migrations.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="item in status.pending"
                        :key="item.name"
                        class="rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10"
                    >
                        <p class="font-mono text-sm text-slate-800 dark:text-slate-100">{{ item.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ item.file }}</p>
                    </div>
                </div>
            </PageCard>

            <PageCard
                title="Applied migrations"
                :description="`${status.ran_count} migration${status.ran_count === 1 ? '' : 's'} recorded`"
            >
                <div
                    v-if="!status.ran.length"
                    class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-600"
                >
                    No applied migrations found.
                </div>
                <div v-else class="max-h-[28rem] space-y-2 overflow-auto pr-1">
                    <div
                        v-for="item in status.ran"
                        :key="item.name"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600"
                    >
                        <p class="min-w-0 break-all font-mono text-sm text-slate-800 dark:text-slate-100">
                            {{ item.name }}
                        </p>
                        <span
                            class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            batch {{ item.batch ?? '—' }}
                        </span>
                    </div>
                </div>
            </PageCard>

            <PageCard
                v-if="lastOutput"
                title="Last command output"
                description="Artisan console output from the most recent migrate/rollback"
            >
                <pre
                    class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-emerald-300"
                >{{ lastOutput }}</pre>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { computed, reactive, ref } from "vue";
import axios from "axios";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";

defineOptions({ name: "DatabaseMigrations" });

type MigrationStatus = {
    pending_count: number;
    ran_count: number;
    latest_batch: number | null;
    repository_ready: boolean;
    pending: Array<{ name: string; file: string }>;
    ran: Array<{ name: string; batch: number | null }>;
    connection: string;
};

const props = defineProps<{
    initialStatus: MigrationStatus;
}>();

const confirm = useConfirm();
const toast = useToast();

const status = reactive<MigrationStatus>({ ...props.initialStatus });
const loading = ref(false);
const running = ref(false);
const rollingBack = ref(false);
const pretend = ref(false);
const rollbackSteps = ref(1);
const lastOutput = ref("");

const busy = computed(() => loading.value || running.value || rollingBack.value);

const applyStatus = (next?: MigrationStatus) => {
    if (!next) {
        return;
    }

    status.pending_count = next.pending_count;
    status.ran_count = next.ran_count;
    status.latest_batch = next.latest_batch;
    status.repository_ready = next.repository_ready;
    status.pending = next.pending;
    status.ran = next.ran;
    status.connection = next.connection;
};

const loadStatus = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route("migrations.status"));
        applyStatus(data);
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Could not load status",
            detail: error?.response?.data?.message || "Failed to load migration status.",
            life: 4000,
        });
    } finally {
        loading.value = false;
    }
};

const runMigrate = async (isPretend = false) => {
    running.value = true;
    pretend.value = isPretend;

    try {
        const { data } = await axios.post(route("migrations.run"), {
            pretend: isPretend,
        });
        applyStatus(data?.status);
        lastOutput.value = data?.output || "";
        toast.add({
            severity: data?.success ? "success" : "warn",
            summary: isPretend ? "Dry run" : "Migrations",
            detail: data?.message || "Done.",
            life: 5000,
        });
    } catch (error: any) {
        const payload = error?.response?.data;
        applyStatus(payload?.status);
        lastOutput.value = payload?.output || payload?.message || "";
        toast.add({
            severity: "error",
            summary: "Migration failed",
            detail: payload?.message || "Could not run migrations.",
            life: 6000,
        });
    } finally {
        running.value = false;
        pretend.value = false;
    }
};

const confirmMigrate = () => {
    confirm.require({
        header: "Run migrations?",
        message: status.pending_count
            ? `This will apply ${status.pending_count} pending migration${status.pending_count === 1 ? "" : "s"} to the live database.`
            : "No pending migrations are listed.",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Run now",
        rejectLabel: "Cancel",
        acceptClass: "p-button-warning",
        accept: () => runMigrate(false),
    });
};

const runRollback = async () => {
    rollingBack.value = true;

    try {
        const { data } = await axios.post(route("migrations.rollback"), {
            step: rollbackSteps.value || 1,
            pretend: false,
        });
        applyStatus(data?.status);
        lastOutput.value = data?.output || "";
        toast.add({
            severity: data?.success ? "success" : "warn",
            summary: "Rollback",
            detail: data?.message || "Done.",
            life: 5000,
        });
    } catch (error: any) {
        const payload = error?.response?.data;
        applyStatus(payload?.status);
        lastOutput.value = payload?.output || payload?.message || "";
        toast.add({
            severity: "error",
            summary: "Rollback failed",
            detail: payload?.message || "Could not roll back migrations.",
            life: 6000,
        });
    } finally {
        rollingBack.value = false;
    }
};

const confirmRollback = () => {
    const steps = rollbackSteps.value || 1;
    confirm.require({
        header: "Roll back migrations?",
        message: `This will undo the last ${steps} migration step${steps === 1 ? "" : "s"}. Take a backup first if you are unsure.`,
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Rollback",
        rejectLabel: "Cancel",
        acceptClass: "p-button-danger",
        accept: () => runRollback(),
    });
};
</script>
