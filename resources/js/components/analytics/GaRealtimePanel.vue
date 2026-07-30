<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span
                        class="relative flex h-2.5 w-2.5"
                        :class="liveDotClass"
                    >
                        <span
                            v-if="isLive"
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"
                        />
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-current" />
                    </span>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                        Live traffic
                    </p>
                </div>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Google Analytics realtime · last ~30 min
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    icon="pi pi-refresh"
                    size="small"
                    severity="secondary"
                    text
                    rounded
                    :loading="loading"
                    :disabled="loading"
                    aria-label="Refresh realtime"
                    @click="load(true)"
                />
                <Link
                    v-if="seoLearningUrl"
                    :href="seoLearningUrl"
                    class="text-[11px] font-medium text-sky-600 hover:underline dark:text-sky-400"
                >
                    GA settings
                </Link>
            </div>
        </div>

        <div
            v-if="!ready && !loading"
            class="mt-4 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <template v-if="connectUrl">
                <a
                    :href="connectUrl"
                    class="font-semibold underline"
                >Connect Google Analytics</a>
                to see live visitors here.
            </template>
            <template v-else>
                {{ error || 'Ask an admin with roles.manage to connect Google Analytics (SEO & Learning).' }}
            </template>
        </div>

        <template v-else>
            <div class="mt-4 flex flex-wrap items-end gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Active now
                    </p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                        {{ loading && activeUsers === null ? '…' : (activeUsers ?? 0) }}
                    </p>
                </div>
                <p
                    v-if="fetchedLabel"
                    class="mb-1 text-[11px] text-slate-400 dark:text-slate-500"
                >
                    {{ fetchedLabel }}
                </p>
            </div>

            <p
                v-if="error"
                class="mt-3 text-xs text-rose-600 dark:text-rose-300"
            >
                {{ error }}
            </p>

            <div
                v-if="pages.length"
                class="mt-4"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Top pages (titles)
                </p>
                <ul class="mt-2 space-y-1.5">
                    <li
                        v-for="page in pages"
                        :key="page.path"
                        class="flex items-center justify-between gap-2 text-xs"
                    >
                        <span
                            class="min-w-0 truncate font-medium text-slate-700 dark:text-slate-200"
                            :title="page.path"
                        >{{ page.path }}</span>
                        <span class="shrink-0 tabular-nums text-slate-500 dark:text-slate-400">
                            {{ page.users }}
                        </span>
                    </li>
                </ul>
            </div>
            <p
                v-else-if="ready && !loading && !error"
                class="mt-3 text-xs text-slate-500 dark:text-slate-400"
            >
                No active users in the realtime window right now.
            </p>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

const props = defineProps({
    pollMs: { type: Number, default: 45000 },
    autoStart: { type: Boolean, default: true },
});

const loading = ref(false);
const ready = ref(false);
const activeUsers = ref(null);
const pages = ref([]);
const fetchedAt = ref(null);
const error = ref(null);
const connectUrl = ref(null);
const cached = ref(false);

let timer = null;

const seoLearningUrl = computed(() => {
    try {
        return route('blogPosts.seo');
    } catch {
        return null;
    }
});

const isLive = computed(() => ready.value && !error.value);

const liveDotClass = computed(() => {
    if (isLive.value) {
        return 'text-emerald-500';
    }
    if (error.value || !ready.value) {
        return 'text-amber-500';
    }
    return 'text-slate-400';
});

const fetchedLabel = computed(() => {
    if (!fetchedAt.value) {
        return '';
    }
    try {
        const t = new Date(fetchedAt.value).toLocaleTimeString();
        return cached.value ? `Cached · ${t}` : `Updated ${t}`;
    } catch {
        return '';
    }
});

const applyRealtime = (payload) => {
    const r = payload?.realtime || payload || {};
    ready.value = Boolean(r.ready);
    activeUsers.value = typeof r.active_users === 'number' ? r.active_users : null;
    pages.value = Array.isArray(r.pages) ? r.pages : [];
    fetchedAt.value = r.fetched_at || null;
    error.value = r.error || null;
    connectUrl.value = r.connect_url || null;
    cached.value = Boolean(r.cached);
};

const load = async (force = false) => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('siteVisitors.gaRealtime'), {
            params: force ? { force: 1 } : {},
            timeout: 20000,
        });
        applyRealtime(data);
    } catch (e) {
        const status = e?.response?.status;
        if (status === 403) {
            error.value = 'You do not have permission to view live traffic.';
        } else {
            error.value = 'Could not load realtime traffic.';
        }
        ready.value = false;
    } finally {
        loading.value = false;
    }
};

const startPolling = () => {
    stopPolling();
    timer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            load(false);
        }
    }, props.pollMs);
};

const stopPolling = () => {
    if (timer) {
        window.clearInterval(timer);
        timer = null;
    }
};

const onVisibility = () => {
    if (document.visibilityState === 'visible') {
        load(false);
        startPolling();
    } else {
        stopPolling();
    }
};

onMounted(() => {
    if (props.autoStart) {
        load(false);
        startPolling();
        document.addEventListener('visibilitychange', onVisibility);
    }
});

onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibility);
});

defineExpose({ load, startPolling, stopPolling });
</script>
