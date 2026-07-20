<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    One-click Smart Post
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Sync learning + GSC → pick best topic → AI writes a draft. Review before publishing.
                </p>
            </div>
            <Button
                label="Generate smart post"
                icon="pi pi-bolt"
                size="small"
                :loading="starting || polling"
                :disabled="starting || polling"
                @click="start"
            />
        </div>

        <div
            v-if="run"
            class="rounded-xl border border-slate-200 px-3 py-3 dark:border-slate-600"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ stepLabel }}
                </p>
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="statusClass"
                >
                    {{ run.status }} · {{ progress }}%
                </span>
            </div>

            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full bg-sky-500 transition-all duration-500"
                    :style="{ width: `${progress}%` }"
                />
            </div>

            <p
                v-if="latestMessage"
                class="mt-2 text-xs text-slate-600 dark:text-slate-300"
            >
                {{ latestMessage }}
            </p>

            <p
                v-if="pickedTopic"
                class="mt-1 text-xs font-medium text-slate-800 dark:text-slate-100"
            >
                Topic: {{ pickedTopic }}
            </p>

            <div
                v-if="error"
                class="mt-2 rounded-lg bg-rose-50 px-2.5 py-2 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-200"
            >
                {{ error }}
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <Button
                    v-if="isActive"
                    label="Cancel"
                    icon="pi pi-times"
                    size="small"
                    severity="danger"
                    outlined
                    :loading="cancelling"
                    @click="cancel"
                />
                <Button
                    v-if="run.blog_post_id"
                    label="Open draft"
                    icon="pi pi-pencil"
                    size="small"
                    @click="openDraft"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const emit = defineEmits(['updated', 'intelligence']);

const toast = useToast();
const starting = ref(false);
const polling = ref(false);
const cancelling = ref(false);
const run = ref(null);
const error = ref('');
let pollTimer = null;

const progress = computed(() => Math.max(0, Math.min(100, Number(run.value?.progress_pct || 0))));
const isActive = computed(() => ['pending', 'running'].includes(run.value?.status));
const latestMessage = computed(() => {
    const log = run.value?.step_log;
    if (!Array.isArray(log) || !log.length) return '';
    return log[log.length - 1]?.message || '';
});
const pickedTopic = computed(() => run.value?.input_json?.smart_pick?.seed_topic || run.value?.input_json?.seed_topic || '');
const stepLabel = computed(() => {
    const step = run.value?.current_step || 'idle';
    const map = {
        queued: 'Queued',
        sync: 'Syncing learning / GSC',
        intake: 'Choosing keywords',
        research: 'Researching keywords',
        hooks: 'Writing hooks',
        outline: 'Building outline',
        draft: 'Writing draft',
        image: 'Cover image',
        finalize: 'Saving draft post',
    };
    return map[step] || step;
});
const statusClass = computed(() => {
    const s = run.value?.status;
    if (s === 'completed') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (s === 'failed' || s === 'cancelled') return 'bg-rose-500/15 text-rose-700 dark:text-rose-300';
    return 'bg-sky-500/15 text-sky-800 dark:text-sky-200';
});

const stopPoll = () => {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
    polling.value = false;
};

const poll = async (runId) => {
    polling.value = true;
    try {
        const { data } = await axios.get(route('blogAi.runs.show', runId), { timeout: 20000 });
        run.value = data.run;
        if (data?.intelligence) {
            emit('intelligence', data.intelligence);
        }
        if (['completed', 'failed', 'cancelled'].includes(data.run?.status)) {
            stopPoll();
            if (data.run.status === 'completed') {
                toast.add({
                    severity: 'success',
                    summary: 'Smart post ready',
                    detail: data.run.blog_post_id
                        ? 'Draft created — review and publish when ready.'
                        : 'Draft ready in the AI session.',
                    life: 6000,
                    group: 'br',
                });
                emit('updated', data);
            } else if (data.run.status === 'failed') {
                error.value = data.run.last_error || 'Smart one-click failed.';
            }
            return;
        }
        pollTimer = setTimeout(() => poll(runId), 2500);
    } catch (e) {
        pollTimer = setTimeout(() => poll(runId), 4000);
    }
};

const start = async (overrides = {}) => {
    error.value = '';
    starting.value = true;
    run.value = null;
    stopPoll();
    try {
        const { data } = await axios.post(
            route('blogAi.smartOneClick'),
            {
                sync_learning: overrides.sync_learning ?? true,
                create_post: true,
                seed_topic: overrides.seed_topic || null,
                cluster: overrides.cluster || null,
                competitor_urls_text: overrides.competitor_urls_text || null,
                action: overrides.action || null,
                target_slug: overrides.target_slug || null,
                target_post_id: overrides.target_post_id || null,
                bucket: overrides.bucket || null,
                opportunity_score: overrides.opportunity_score ?? null,
                strict_draft: overrides.strict_draft ?? false,
            },
            { timeout: 60000 },
        );
        run.value = data.run;
        if (data.intelligence) {
            emit('intelligence', data.intelligence);
        }
        if (data.queued || ['pending', 'running'].includes(data.run?.status)) {
            await poll(data.run.id);
        } else if (data.run?.status === 'completed') {
            emit('updated', data);
            toast.add({
                severity: 'success',
                summary: 'Smart post ready',
                detail: 'Draft created — open it to review.',
                life: 5000,
                group: 'br',
            });
        }
    } catch (e) {
        const activeId = Number(
            e?.response?.data?.errors?.active_run_id?.[0]
            || e?.response?.data?.active_run_id
            || 0,
        );
        if (activeId) {
            await poll(activeId);
            return;
        }
        error.value = e?.response?.data?.errors?.ai?.[0]
            || e?.response?.data?.message
            || e?.message
            || 'Could not start smart one-click.';
    } finally {
        starting.value = false;
    }
};

const cancel = async () => {
    if (!run.value?.id) return;
    cancelling.value = true;
    try {
        const { data } = await axios.post(route('blogAi.runs.cancel', run.value.id));
        run.value = data.run;
        stopPoll();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Cancel failed.';
    } finally {
        cancelling.value = false;
    }
};

const openDraft = () => {
    if (!run.value?.blog_post_id) return;
    router.visit(route('blogPosts.edit', run.value.blog_post_id));
};

onBeforeUnmount(() => stopPoll());

const startForOpportunity = (item) => {
    if (!item?.query) return;
    const refreshBuckets = ['fix_ctr', 'defend', 'cannibalized'];
    const action = item.slug && refreshBuckets.includes(item.bucket) ? 'refresh' : 'new';
    return start({
        seed_topic: item.query,
        action,
        target_slug: item.slug || null,
        bucket: item.bucket || null,
        opportunity_score: item.opportunity_score ?? null,
        sync_learning: false,
    });
};

defineExpose({ start, startForOpportunity });
</script>
