<template>
    <AuthenticatedLayout title="System Maintenance">
        <div class="space-y-5">
            <PageHeader
                title="System Maintenance"
                description="Run artisan maintenance commands from a secured admin panel — no SSH required."
                icon="PhWrench"
                icon-bg-class="bg-slate-100 dark:bg-slate-500/15"
                icon-class="text-slate-700 dark:text-slate-300"
            >
                <template #actions>
                    <Button
                        label="Run everything"
                        icon="pi pi-play"
                        size="small"
                        severity="warning"
                        class="!inline-flex shrink-0 whitespace-nowrap"
                        :loading="runningAction === 'run_all'"
                        :disabled="busy"
                        @click="confirmRun('run_all')"
                    />
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

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                    title="Cache driver"
                    :value="status.cache?.driver || 'database'"
                    icon="PhDatabase"
                    :subtitle="cacheSourceLabel"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
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
                title="Cache driver"
                description="Controls Laravel CACHE_DRIVER for Visitors dedupe/quotas and framework cache. Default is database."
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">
                            Active driver
                        </label>
                        <SelectButton
                            v-model="cacheDriverDraft"
                            :options="cacheDriverOptions"
                            option-label="label"
                            option-value="value"
                            :allow-empty="false"
                            :disabled="busy || savingCache"
                            class="flex flex-wrap"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ status.cache?.note || 'Use database, redis, or file — not array.' }}
                            <span v-if="status.cache?.source === 'database'" class="text-sky-600 dark:text-sky-400">
                                Currently overridden in admin settings.
                            </span>
                            <span v-else-if="status.cache?.source === 'env'">
                                Using .env CACHE_DRIVER={{ status.cache?.env_default }}.
                            </span>
                            <span v-else>
                                Using app default (database).
                            </span>
                        </p>
                        <p
                            v-if="cacheDriverDraft === 'redis' && !status.cache?.redis_configured"
                            class="text-xs text-amber-700 dark:text-amber-300"
                        >
                            Redis host/client does not look configured — set Redis in .env before switching.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="Save driver"
                            icon="pi pi-save"
                            size="small"
                            :loading="savingCache"
                            :disabled="busy || cacheDriverDraft === status.cache?.driver"
                            @click="saveCacheDriver"
                        />
                        <Button
                            label="Reset to .env"
                            icon="pi pi-replay"
                            size="small"
                            severity="secondary"
                            outlined
                            :loading="savingCache"
                            :disabled="busy || status.cache?.source !== 'database'"
                            @click="resetCacheDriver"
                        />
                    </div>
                </div>
            </PageCard>

            <PageCard
                v-for="section in actionSections"
                :key="section.group"
                :title="section.title"
                :description="section.description"
            >
                <div
                    v-if="section.group === 'blog' && status.blog_learning"
                    class="mb-4 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
                >
                    <p class="font-semibold">Latest learning snapshot</p>
                    <p class="mt-1">{{ status.blog_learning.summary_bn || 'Snapshot ready.' }}</p>
                    <p class="mt-1 text-xs opacity-80">
                        Built {{ formatDate(status.blog_learning.generated_at) }}
                        · posts {{ status.blog_learning.posts_analyzed }}
                        · events {{ status.blog_learning.events_analyzed }}
                    </p>
                    <ul
                        v-if="(status.blog_learning.next_post_ideas || []).length"
                        class="mt-2 list-disc space-y-1 pl-5 text-xs"
                    >
                        <li
                            v-for="(idea, idx) in status.blog_learning.next_post_ideas"
                            :key="idx"
                        >
                            <strong>{{ idea.suggested_title || idea.seed_topic }}</strong>
                            <span class="opacity-80"> — {{ idea.cluster }} ({{ idea.reason }})</span>
                        </li>
                    </ul>
                </div>
                <p
                    v-else-if="section.group === 'blog' && !status.blog_learning"
                    class="mb-4 text-sm text-slate-500"
                >
                    No learning snapshot yet. Run <strong>Blog learning insights</strong> below.
                </p>

                <div
                    v-if="section.group === 'blog'"
                    class="mb-4 space-y-4"
                >
                    <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
                        <GscStatusPanel
                            :data="status.gsc_status"
                            :probing="runningAction === 'seo_gsc_status'"
                            :disabled="busy"
                            @probe="confirmRun('seo_gsc_status')"
                        />
                    </div>
                    <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
                        <RankOpportunitiesPanel :data="status.rank_opportunities" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div
                        v-for="action in section.actions"
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
                            <p
                                v-if="action.include_in_run_all === false && !action.is_batch"
                                class="mt-1 text-[11px] text-amber-700 dark:text-amber-300"
                            >
                                Not included in “Run everything” (side effects / conflicts)
                            </p>
                        </div>
                        <Button
                            :label="buttonLabel(action)"
                            size="small"
                            :outlined="!action.is_batch && action.key !== 'all'"
                            :severity="actionSeverity(action)"
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
import RankOpportunitiesPanel from "@/components/blog/RankOpportunitiesPanel.vue";
import GscStatusPanel from "@/components/blog/GscStatusPanel.vue";

defineOptions({ name: "SystemMaintenance" });

type MaintenanceAction = {
    key: string;
    label: string;
    description: string;
    group?: string;
    include_in_run_all?: boolean;
    is_batch?: boolean;
};

type CacheDriverStatus = {
    driver?: string;
    env_default?: string;
    source?: 'database' | 'env' | 'default';
    options?: string[];
    redis_configured?: boolean;
    note?: string;
};

type MaintenanceStatus = {
    storage_link_exists: boolean;
    storage_link_path: string;
    public_storage_path: string;
    app_env: string;
    app_debug: boolean;
    cache?: CacheDriverStatus | null;
    actions: MaintenanceAction[];
    groups?: Record<string, string>;
    blog_learning?: {
        generated_at?: string | null;
        summary_bn?: string | null;
        posts_analyzed?: number;
        events_analyzed?: number;
        next_post_ideas?: Array<{
            cluster?: string;
            suggested_title?: string;
            seed_topic?: string;
            reason?: string;
        }>;
    } | null;
    rank_opportunities?: {
        configured?: boolean;
        table_ready?: boolean;
        refreshed_at?: string | null;
        summary?: Record<string, number>;
        items?: Array<Record<string, unknown>>;
    } | null;
    gsc_status?: {
        site_url?: string | null;
        has_site_url?: boolean;
        has_client_id?: boolean;
        has_client_secret?: boolean;
        has_refresh_token?: boolean;
        has_static_access_token?: boolean;
        auth_mode?: string;
        ready?: boolean;
        can_connect?: boolean;
        connect_url?: string | null;
        disconnect_url?: string | null;
        refresh_token_source?: string | null;
    } | null;
};

const GROUP_ORDER = ["meta", "cache", "blog", "subscriptions", "domains", "ops"];

const GROUP_DESCRIPTIONS: Record<string, string> = {
    meta: "One-click batch runner for every included maintenance command.",
    cache: "Framework caches, storage symlink, queue restart, migrations, and related housekeeping.",
    blog: "SEO reports and AI learning jobs for the blog writer.",
    subscriptions: "Expiry, alert scan, and merchant notifications.",
    domains: "Domain audits, normalization, and website backfill.",
    ops: "Courier retries, search reindex, and integration refreshers.",
};

const props = defineProps<{
    initialStatus: MaintenanceStatus;
}>();

const confirm = useConfirm();
const toast = useToast();

const status = reactive<MaintenanceStatus>({
    ...props.initialStatus,
    cache: props.initialStatus.cache ?? null,
    groups: props.initialStatus.groups ?? {},
    blog_learning: props.initialStatus.blog_learning ?? null,
    rank_opportunities: props.initialStatus.rank_opportunities ?? null,
    gsc_status: props.initialStatus.gsc_status ?? null,
});
const loading = ref(false);
const runningAction = ref<string | null>(null);
const savingCache = ref(false);
const lastOutput = ref("");
const cacheDriverDraft = ref(props.initialStatus.cache?.driver || "database");

const busy = computed(() => loading.value || runningAction.value !== null || savingCache.value);

const cacheDriverOptions = computed(() => {
    const options = status.cache?.options?.length
        ? status.cache.options
        : ["database", "redis", "file"];
    return options.map((value) => ({
        label: value,
        value,
    }));
});

const cacheSourceLabel = computed(() => {
    const source = status.cache?.source;
    if (source === "database") return "Admin override";
    if (source === "env") return "From .env";
    return "App default";
});

const actionSections = computed(() => {
    const groups = status.groups || {};
    const byGroup = new Map<string, MaintenanceAction[]>();

    for (const action of status.actions || []) {
        const group = action.group || "cache";
        if (!byGroup.has(group)) {
            byGroup.set(group, []);
        }
        byGroup.get(group)!.push(action);
    }

    const ordered = [
        ...GROUP_ORDER.filter((g) => byGroup.has(g)),
        ...[...byGroup.keys()].filter((g) => !GROUP_ORDER.includes(g)),
    ];

    return ordered.map((group) => ({
        group,
        title: groups[group] || group,
        description: GROUP_DESCRIPTIONS[group] || "Artisan maintenance commands",
        actions: byGroup.get(group) || [],
    }));
});

const formatDate = (value?: string | null) => {
    if (!value) return "—";
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const buttonLabel = (action: MaintenanceAction) => {
    if (action.key === "storage_link") return "Create link";
    if (action.is_batch || action.key === "run_all") return "Run all";
    if (action.key === "all") return "Clear";
    return "Run";
};

const actionSeverity = (action: MaintenanceAction) => {
    if (action.key === "run_all") return "warning";
    if (action.key === "all") return undefined;
    return "secondary";
};

const applyStatus = (next?: MaintenanceStatus) => {
    if (!next) {
        return;
    }

    status.storage_link_exists = next.storage_link_exists;
    status.storage_link_path = next.storage_link_path;
    status.public_storage_path = next.public_storage_path;
    status.app_env = next.app_env;
    status.app_debug = next.app_debug;
    status.cache = next.cache ?? null;
    status.actions = next.actions;
    status.groups = next.groups ?? {};
    status.blog_learning = next.blog_learning ?? null;
    status.rank_opportunities = next.rank_opportunities ?? null;
    status.gsc_status = next.gsc_status ?? null;
    if (next.cache?.driver) {
        cacheDriverDraft.value = next.cache.driver;
    }
};

const saveCacheDriver = async () => {
    savingCache.value = true;
    try {
        const { data } = await axios.put(route("maintenance.cacheDriver.update"), {
            driver: cacheDriverDraft.value,
        });
        applyStatus(data?.status);
        toast.add({
            severity: "success",
            summary: "Cache driver",
            detail: data?.message || "Saved.",
            life: 4000,
            group: "br",
        });
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Could not save",
            detail: error?.response?.data?.message || error?.response?.data?.errors?.driver?.[0] || "Failed to update cache driver.",
            life: 5000,
            group: "br",
        });
    } finally {
        savingCache.value = false;
    }
};

const resetCacheDriver = async () => {
    savingCache.value = true;
    try {
        const { data } = await axios.post(route("maintenance.cacheDriver.reset"));
        applyStatus(data?.status);
        toast.add({
            severity: "success",
            summary: "Cache driver",
            detail: data?.message || "Reset.",
            life: 4000,
            group: "br",
        });
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Could not reset",
            detail: error?.response?.data?.message || "Failed to reset cache driver.",
            life: 5000,
            group: "br",
        });
    } finally {
        savingCache.value = false;
    }
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
            group: "br",
        });
    } finally {
        loading.value = false;
    }
};

const runAction = async (action: string) => {
    runningAction.value = action;

    try {
        const { data } = await axios.post(route("maintenance.run"), { action }, {
            timeout: action === "run_all" ? 600000 : 120000,
        });
        applyStatus(data?.status);
        lastOutput.value = data?.output || "";
        toast.add({
            severity: data?.success ? "success" : "warn",
            summary: "Maintenance",
            detail: data?.message || "Done.",
            life: 5000,
            group: "br",
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
            group: "br",
        });
    } finally {
        runningAction.value = null;
    }
};

const confirmRun = (action: string) => {
    const meta = status.actions.find((item) => item.key === action);
    const isBatch = action === "run_all" || meta?.is_batch;
    confirm.require({
        header: meta?.label || "Run action?",
        message: isBatch
            ? (meta?.description || "This will run many Artisan commands on the live server. Continue?")
            : (meta?.description || "This will run Artisan commands on the live server."),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: isBatch ? "Run everything" : "Run now",
        rejectLabel: "Cancel",
        acceptClass: isBatch || action === "all" ? "p-button-warning" : "p-button-primary",
        accept: () => runAction(action),
    });
};
</script>
