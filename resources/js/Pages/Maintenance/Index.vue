<template>
    <AuthenticatedLayout title="System Maintenance">
        <div class="space-y-5">
            <PageHeader
                title="System Maintenance"
                description="Clear caches, rebuild optimized files, and manage the public storage symlink from a secured admin panel"
                icon="PhWrench"
                icon-bg-class="bg-slate-100 dark:bg-slate-500/15"
                icon-class="text-slate-700 dark:text-slate-300"
            >
                <template #actions>
                    <Button
                        label="Clear all caches"
                        icon="pi pi-trash"
                        size="small"
                        class="!inline-flex shrink-0 whitespace-nowrap"
                        :loading="runningAction === 'all'"
                        :disabled="busy"
                        @click="confirmRun('all')"
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
                    title="Environment"
                    :value="status.app_env"
                    icon="PhGlobe"
                    subtitle="Current APP_ENV"
                />
                <StatCard
                    title="Debug"
                    :value="status.app_debug ? 'On' : 'Off'"
                    icon="PhBug"
                    :subtitle="status.app_debug ? 'APP_DEBUG enabled' : 'APP_DEBUG disabled'"
                    :accent-class="status.app_debug ? 'bg-amber-500' : 'bg-emerald-500'"
                    :icon-bg-class="status.app_debug ? 'bg-amber-50 dark:bg-amber-500/15' : 'bg-emerald-50 dark:bg-emerald-500/15'"
                    :icon-class="status.app_debug ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                />
                <StatCard
                    title="Storage link"
                    :value="status.storage_link_exists ? 'Ready' : 'Missing'"
                    icon="PhLink"
                    :subtitle="status.storage_link_exists ? 'public/storage exists' : 'Run storage link'"
                    :accent-class="status.storage_link_exists ? 'bg-emerald-500' : 'bg-rose-500'"
                    :icon-bg-class="status.storage_link_exists ? 'bg-emerald-50 dark:bg-emerald-500/15' : 'bg-rose-50 dark:bg-rose-500/15'"
                    :icon-class="status.storage_link_exists ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                />
            </div>

            <PageCard
                title="Artisan actions"
                description="These replace the old open /clear-* and /storage-link URLs. Admin login is required."
            >
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div
                        v-for="action in status.actions"
                        :key="action.key"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ action.label }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ action.description }}
                            </p>
                        </div>
                        <Button
                            :label="action.key === 'storage_link' ? 'Create link' : 'Run'"
                            size="small"
                            :outlined="action.key !== 'all'"
                            :severity="action.key === 'all' ? undefined : 'secondary'"
                            class="!inline-flex shrink-0 whitespace-nowrap"
                            :loading="runningAction === action.key"
                            :disabled="busy"
                            @click="confirmRun(action.key)"
                        />
                    </div>
                </div>
            </PageCard>

            <PageCard
                title="Storage path"
                description="Symlink from public/storage to storage/app/public"
            >
                <div class="space-y-2 font-mono text-xs text-slate-600 dark:text-slate-300">
                    <p>
                        <span class="text-slate-400">link → </span>{{ status.storage_link_path }}
                    </p>
                    <p>
                        <span class="text-slate-400">target → </span>{{ status.public_storage_path }}
                    </p>
                </div>
            </PageCard>

            <PageCard
                v-if="lastOutput"
                title="Last command output"
                description="Artisan output from the most recent maintenance action"
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

defineOptions({ name: "SystemMaintenance" });

type MaintenanceStatus = {
    storage_link_exists: boolean;
    storage_link_path: string;
    public_storage_path: string;
    app_env: string;
    app_debug: boolean;
    actions: Array<{ key: string; label: string; description: string }>;
};

const props = defineProps<{
    initialStatus: MaintenanceStatus;
}>();

const confirm = useConfirm();
const toast = useToast();

const status = reactive<MaintenanceStatus>({ ...props.initialStatus });
const loading = ref(false);
const runningAction = ref<string | null>(null);
const lastOutput = ref("");

const busy = computed(() => loading.value || runningAction.value !== null);

const applyStatus = (next?: MaintenanceStatus) => {
    if (!next) {
        return;
    }

    status.storage_link_exists = next.storage_link_exists;
    status.storage_link_path = next.storage_link_path;
    status.public_storage_path = next.public_storage_path;
    status.app_env = next.app_env;
    status.app_debug = next.app_debug;
    status.actions = next.actions;
};

const loadStatus = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route("maintenance.status"));
        applyStatus(data);
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Could not load status",
            detail: error?.response?.data?.message || "Failed to load maintenance status.",
            life: 4000,
        });
    } finally {
        loading.value = false;
    }
};

const runAction = async (action: string) => {
    runningAction.value = action;

    try {
        const { data } = await axios.post(route("maintenance.run"), { action });
        applyStatus(data?.status);
        lastOutput.value = data?.output || "";
        toast.add({
            severity: data?.success ? "success" : "warn",
            summary: "Maintenance",
            detail: data?.message || "Done.",
            life: 5000,
        });
    } catch (error: any) {
        const payload = error?.response?.data;
        applyStatus(payload?.status);
        lastOutput.value = payload?.output || payload?.message || "";
        toast.add({
            severity: "error",
            summary: "Action failed",
            detail: payload?.message || "Could not run maintenance action.",
            life: 6000,
        });
    } finally {
        runningAction.value = null;
    }
};

const confirmRun = (action: string) => {
    const meta = status.actions.find((item) => item.key === action);
    confirm.require({
        header: meta?.label || "Run action?",
        message: meta?.description
            || "This will run Artisan commands on the live server.",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Run now",
        rejectLabel: "Cancel",
        acceptClass: action === "all" ? "p-button-warning" : "p-button-primary",
        accept: () => runAction(action),
    });
};
</script>
