<template>
    <div class="space-y-4">
        <div
            v-if="intelligence"
            class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600"
        >
            <BlogIntelligenceRing :data="intelligence" />
        </div>

        <div
            v-if="blogLearning"
            class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
        >
            <p class="font-semibold">Latest learning snapshot</p>
            <p class="mt-1">{{ blogLearning.summary_bn || 'Snapshot ready.' }}</p>
            <p class="mt-1 text-xs opacity-80">
                Built {{ formatDate(blogLearning.generated_at) }}
                · posts {{ blogLearning.posts_analyzed }}
                · events {{ blogLearning.events_analyzed }}
            </p>
            <ul
                v-if="(blogLearning.next_post_ideas || []).length"
                class="mt-2 list-disc space-y-1 pl-5 text-xs"
            >
                <li
                    v-for="(idea, idx) in blogLearning.next_post_ideas"
                    :key="idx"
                >
                    <strong>{{ idea.suggested_title || idea.seed_topic }}</strong>
                    <span class="opacity-80"> — {{ idea.cluster }} ({{ idea.reason }})</span>
                </li>
            </ul>
        </div>
        <p
            v-else
            class="text-sm text-slate-500 dark:text-slate-400"
        >
            No learning snapshot yet. Run <strong>Blog learning insights</strong> below.
        </p>

        <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
            <GscStatusPanel
                :data="gscStatus"
                :probing="runningAction === 'seo_gsc_status'"
                :disabled="busy"
                @probe="confirmRun('seo_gsc_status')"
            />
        </div>

        <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
            <RankOpportunitiesPanel :data="rankOpportunities" />
        </div>

        <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
            <CompetitorAnalyzerPanel
                :initial-items="competitors"
                :clusters="clusters"
                @intelligence="(next) => { intelligence = next; }"
            />
        </div>

        <div class="grid grid-cols-1 gap-3">
            <div
                v-for="action in blogActions"
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
                        v-if="action.include_in_run_all === false"
                        class="mt-1 text-[11px] text-amber-700 dark:text-amber-300"
                    >
                        Not included in “Run everything” (side effects / conflicts)
                    </p>
                </div>
                <Button
                    label="Run"
                    size="small"
                    severity="secondary"
                    outlined
                    class="!inline-flex shrink-0 whitespace-nowrap"
                    :loading="runningAction === action.key"
                    :disabled="busy"
                    @click="confirmRun(action.key)"
                />
            </div>
        </div>

        <div
            v-if="!blogActions.length && !loading"
            class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400"
        >
            Could not load blog maintenance actions.
            <button
                type="button"
                class="ml-1 font-medium text-sky-600 underline dark:text-sky-400"
                @click="loadStatus"
            >
                Retry
            </button>
        </div>

        <div
            v-if="lastOutput"
            class="space-y-2"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Last command output
            </p>
            <pre
                class="max-h-48 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-relaxed text-emerald-300"
            >{{ lastOutput }}</pre>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { useConfirm } from 'primevue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import RankOpportunitiesPanel from '@/components/blog/RankOpportunitiesPanel.vue';
import GscStatusPanel from '@/components/blog/GscStatusPanel.vue';
import BlogIntelligenceRing from '@/components/blog/BlogIntelligenceRing.vue';
import CompetitorAnalyzerPanel from '@/components/blog/CompetitorAnalyzerPanel.vue';

const emit = defineEmits(['updated']);

const confirm = useConfirm();
const toast = useToast();

const loading = ref(false);
const runningAction = ref(null);
const lastOutput = ref('');
const actions = ref([]);
const blogLearning = ref(null);
const rankOpportunities = ref(null);
const gscStatus = ref(null);
const intelligence = ref(null);
const competitors = ref([]);
const clusters = ref({});

const busy = computed(() => loading.value || runningAction.value !== null);

const blogActions = computed(() =>
    (actions.value || []).filter((action) => action.group === 'blog'),
);

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const applyStatus = (next) => {
    if (!next) return;
    actions.value = next.actions || [];
    blogLearning.value = next.blog_learning ?? null;
    rankOpportunities.value = next.rank_opportunities ?? null;
    gscStatus.value = next.gsc_status ?? null;
    intelligence.value = next.intelligence ?? intelligence.value;
    competitors.value = next.competitors ?? competitors.value;
    if (next.clusters) {
        clusters.value = next.clusters;
    }
};

const loadStatus = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('maintenance.status'));
        applyStatus(data);
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Could not load status',
            detail: error?.response?.data?.message || 'Failed to load blog SEO status.',
            life: 4000,
            group: 'br',
        });
    } finally {
        loading.value = false;
    }
};

const runAction = async (action) => {
    runningAction.value = action;

    try {
        const { data } = await axios.post(
            route('maintenance.run'),
            { action },
            { timeout: 120000 },
        );
        applyStatus(data?.status);
        lastOutput.value = data?.output || '';
        toast.add({
            severity: data?.success ? 'success' : 'warn',
            summary: 'Blog SEO & learning',
            detail: data?.message || 'Done.',
            life: 5000,
            group: 'br',
        });
        emit('updated', data?.status);
    } catch (error) {
        const payload = error?.response?.data;
        applyStatus(payload?.status);
        lastOutput.value = payload?.output || payload?.message || '';
        toast.add({
            severity: 'error',
            summary: 'Action failed',
            detail: payload?.message || 'Could not run blog maintenance action.',
            life: 6000,
            group: 'br',
        });
    } finally {
        runningAction.value = null;
    }
};

const confirmRun = (action) => {
    const meta = actions.value.find((item) => item.key === action);
    confirm.require({
        header: meta?.label || 'Run action?',
        message: meta?.description || 'This will run Artisan commands on the live server.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Run now',
        rejectLabel: 'Cancel',
        acceptClass: 'p-button-primary',
        accept: () => runAction(action),
    });
};

onMounted(() => {
    loadStatus();
});

defineExpose({ loadStatus });
</script>
