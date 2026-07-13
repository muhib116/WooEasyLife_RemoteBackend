<template>
    <Dialog
        v-model:visible="visibleProxy"
        modal
        header="AI Blog Writer (Bangladesh)"
        :style="{ width: 'min(96vw, 820px)' }"
        :breakpoints="{ '960px': '96vw' }"
        @hide="onHide"
    >
        <div class="space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Landing-page truth → BD keywords → hooks → outline → SEO draft → optional cover image.
                Always applied as <strong>draft</strong> — you review before publish.
            </p>

            <div class="flex flex-wrap gap-2 text-xs">
                <span
                    v-for="(label, idx) in stepLabels"
                    :key="label"
                    class="rounded-full px-2.5 py-1 font-medium"
                    :class="step === idx
                        ? 'bg-amber-500 text-black'
                        : step > idx
                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                >
                    {{ idx + 1 }}. {{ label }}
                </span>
            </div>

            <p v-if="error" class="rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-600 dark:text-rose-400">
                {{ error }}
            </p>
            <p v-if="loading && busyHint" class="text-xs text-slate-500">
                {{ busyHint }}
            </p>
            <div
                v-if="session?.status === 'failed' || (session?.busy && !loading)"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-800 dark:text-amber-200"
            >
                <span>{{ session?.last_error || 'Session is stuck or failed.' }}</span>
                <Button
                    label="Unlock session"
                    icon="pi pi-unlock"
                    size="small"
                    severity="warning"
                    outlined
                    :disabled="loading"
                    @click="unlockSession"
                />
            </div>

            <!-- Step 0: topic -->
            <div v-if="step === 0" class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cluster</label>
                    <Select
                        v-model="cluster"
                        :options="clusterOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                        placeholder="Pick a topic cluster"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Seed topic (optional)</label>
                    <InputText
                        v-model="seedTopic"
                        class="w-full"
                        placeholder="e.g. ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি"
                    />
                </div>
                <div>
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Keywords <span class="text-rose-500">*</span>
                        </label>
                        <Button
                            label="Generate with AI"
                            icon="pi pi-sparkles"
                            size="small"
                            severity="secondary"
                            outlined
                            :loading="suggestingKeywords"
                            :disabled="loading || suggestingKeywords"
                            @click="generateKeywords"
                        />
                    </div>
                    <Textarea
                        v-model="keywordsText"
                        class="w-full"
                        rows="4"
                        placeholder="Paste from Keyword Planner, or Generate with AI&#10;ফেক অর্ডার&#10;কুরিয়ার হিস্টোরি চেক"
                    />
                    <small class="mt-1 block text-slate-500">
                        Paste BD keywords or generate from cluster + seed topic (Google Suggest BD + AI). Edit before research.
                    </small>
                </div>
            </div>

            <!-- Step 1: keywords result -->
            <div v-else-if="step === 1" class="space-y-3">
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase text-slate-500">Primary</p>
                    <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ session?.keywords?.primary || '—' }}
                    </p>
                    <p class="mt-3 text-xs font-semibold uppercase text-slate-500">Secondary</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span
                            v-for="kw in (session?.keywords?.secondary || [])"
                            :key="kw"
                            class="rounded-md bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800"
                        >
                            {{ kw }}
                        </span>
                    </div>
                </div>
                <div
                    v-if="session?.keywords?.live_suggestions?.length"
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                >
                    <p class="text-xs font-semibold uppercase text-slate-500">Live Google Suggest (BD)</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span
                            v-for="kw in session.keywords.live_suggestions"
                            :key="kw"
                            class="rounded-md bg-sky-500/10 px-2 py-0.5 text-xs text-sky-800 dark:text-sky-300"
                        >
                            {{ kw }}
                        </span>
                    </div>
                </div>
                <div
                    v-if="session?.keywords?.cannibalization?.length"
                    class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200"
                >
                    <p class="font-semibold">Possible keyword overlap with existing posts:</p>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="row in session.keywords.cannibalization" :key="row.id">
                            {{ row.title }} ({{ row.status }}) — {{ row.focus_keyword || row.slug }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Step 2: hooks -->
            <div v-else-if="step === 2" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs text-slate-500">Select 1–3 hooks</p>
                    <Button
                        label="Regenerate hooks"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        text
                        :disabled="loading"
                        @click="regenerateHooks"
                    />
                </div>
                <label
                    v-for="hook in (session?.hooks || [])"
                    :key="hook.id"
                    class="flex cursor-pointer gap-3 rounded-xl border p-3 transition"
                    :class="selectedHookIds.includes(hook.id)
                        ? 'border-amber-500 bg-amber-500/10'
                        : 'border-slate-200 dark:border-slate-700'"
                >
                    <input
                        v-model="selectedHookIds"
                        type="checkbox"
                        class="mt-1"
                        :value="hook.id"
                        :disabled="!selectedHookIds.includes(hook.id) && selectedHookIds.length >= 3"
                    >
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ hook.title }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ hook.focus_keyword }} · {{ hook.angle }}
                        </p>
                        <p v-if="hook.why_it_ranks" class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            {{ hook.why_it_ranks }}
                        </p>
                    </div>
                </label>
            </div>

            <!-- Step 3: outline -->
            <div v-else-if="step === 3" class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">H1</p>
                    <p class="font-medium">{{ session?.outline?.h1 }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">Sections</p>
                    <ul class="mt-1 space-y-1">
                        <li v-for="(sec, i) in (session?.outline?.sections || [])" :key="i">
                            <span class="font-medium">{{ sec.heading }}</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">Internal links</p>
                    <ul class="mt-1 list-disc pl-5 text-slate-600 dark:text-slate-300">
                        <li v-for="(link, i) in (session?.link_plan || [])" :key="i">
                            <code>{{ link.path }}</code> — {{ link.anchor }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Step 4: draft preview -->
            <div v-else-if="step === 4" class="space-y-3 text-sm">
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <p class="font-semibold">{{ session?.draft?.title }}</p>
                    <p class="mt-1 font-mono text-xs text-slate-500">/blog/{{ session?.draft?.slug }}</p>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">{{ session?.draft?.meta_description }}</p>
                </div>
                <div v-if="session?.draft?.quality" class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-md bg-slate-100 px-2 py-1 dark:bg-slate-800">
                        Words: {{ session.draft.quality.word_count }}
                    </span>
                    <span :class="badgeClass(session.draft.quality.has_h2)">H2</span>
                    <span :class="badgeClass(session.draft.quality.has_internal_link)">Internal links</span>
                    <span :class="badgeClass(session.draft.quality.keyword_in_title)">KW in title</span>
                    <span :class="badgeClass(session.draft.quality.meta_description_ok)">Meta OK</span>
                </div>
                <p class="text-xs text-slate-500">
                    Calls: {{ session?.usage?.ai_calls || 0 }}
                    · Tokens: {{ session?.usage?.total_tokens || 0 }}
                    · Est. ${{ Number(session?.usage?.estimated_usd || 0).toFixed(4) }}
                </p>
            </div>

            <!-- Step 5: cover image -->
            <div v-else-if="step === 5" class="space-y-3 text-sm">
                <div
                    v-if="session?.image?.url || session?.draft?.og_image"
                    class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700"
                >
                    <img
                        :src="session?.image?.url || session?.draft?.og_image"
                        alt="AI cover"
                        class="max-h-64 w-full object-cover"
                    >
                </div>
                <p v-else class="text-slate-500">No cover image yet. Generate one or apply the draft without it.</p>
                <p class="text-xs text-slate-500">
                    Consistent founder look; outfit/posture varies. Bangla headline can be composited later.
                </p>
                <p class="text-xs text-slate-500">
                    Calls: {{ session?.usage?.ai_calls || 0 }}
                    · Est. ${{ Number(session?.usage?.estimated_usd || 0).toFixed(4) }}
                </p>
            </div>
        </div>

        <template #footer>
            <div class="flex flex-wrap justify-between gap-2">
                <Button
                    label="Close"
                    severity="secondary"
                    text
                    :disabled="loading"
                    @click="visibleProxy = false"
                />
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="step > 0 && step < 5"
                        label="Back"
                        severity="secondary"
                        outlined
                        :disabled="loading"
                        @click="step -= 1"
                    />
                    <Button
                        v-if="step === 4"
                        label="Skip image & apply"
                        severity="secondary"
                        outlined
                        :disabled="loading || !session?.draft"
                        @click="applyDraft"
                    />
                    <Button
                        v-if="step === 5 && !session?.image?.url && !session?.draft?.og_image"
                        label="Apply without image"
                        severity="secondary"
                        outlined
                        :disabled="loading || !session?.draft"
                        @click="applyDraft"
                    />
                    <Button
                        :label="primaryLabel"
                        icon="pi pi-sparkles"
                        :loading="loading"
                        :disabled="!canPrimary"
                        @click="runPrimary"
                    />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

const props = defineProps({
    visible: { type: Boolean, default: false },
});

const emit = defineEmits(['update:visible', 'apply']);

const visibleProxy = computed({
    get: () => props.visible,
    set: (v) => emit('update:visible', v),
});

const stepLabels = ['Topic', 'Keywords', 'Hooks', 'Outline', 'Draft', 'Image'];
const step = ref(0);
const loading = ref(false);
const suggestingKeywords = ref(false);
const busyHint = ref('');
const error = ref('');
const session = ref(null);
const cluster = ref('fake_order');
const seedTopic = ref('');
const keywordsText = ref('');
const selectedHookIds = ref([]);
const queueEnabled = ref(true);
const imageEnabled = ref(true);
let pollGeneration = 0;

const clusterOptions = ref([
    { value: 'fake_order', label: 'ফেক অর্ডার / COD fraud' },
    { value: 'fraud_checker', label: 'ফ্রড চেকার / Courier history' },
    { value: 'checkout_protection', label: 'চেকআউট সুরক্ষা / OTP & block' },
    { value: 'courier', label: 'কুরিয়ার / অটো এন্ট্রি' },
    { value: 'missing_order', label: 'হারানো অর্ডার / Missing order' },
    { value: 'facebook_ads', label: 'Facebook Ads / Pixel' },
    { value: 'ai_orders', label: 'AI অর্ডার / Message & image to order' },
    { value: 'packing_print', label: 'প্যাকিং / Invoice & sticker' },
    { value: 'multistore_app', label: 'মাল্টিস্টোর / Mobile app' },
    { value: 'team_calls', label: 'টিম / Call tracking' },
    { value: 'operations', label: 'অপারেশন / ড্যাশবোর্ড' },
    { value: 'general', label: 'সাধারণ WooCommerce BD' },
]);

const primaryLabel = computed(() => {
    if (step.value === 0) return 'Research keywords';
    if (step.value === 1) return 'Generate hooks';
    if (step.value === 2) return 'Build outline';
    if (step.value === 3) return 'Write full draft';
    if (step.value === 4) return imageEnabled.value ? 'Generate cover image' : 'Apply to form';
    return 'Apply to form';
});

const canPrimary = computed(() => {
    if (loading.value) return false;
    if (step.value === 0) return keywordsText.value.trim().length > 0;
    if (step.value === 2) return selectedHookIds.value.length >= 1;
    if (step.value === 4) return Boolean(session.value?.draft);
    if (step.value === 5) return Boolean(session.value?.draft);
    return true;
});

const badgeClass = (ok) => ok
    ? 'rounded-md bg-emerald-500/15 px-2 py-1 text-emerald-700 dark:text-emerald-400'
    : 'rounded-md bg-slate-100 px-2 py-1 text-slate-500 dark:bg-slate-800';

const stopPoll = () => {
    pollGeneration += 1;
};

const reset = () => {
    stopPoll();
    step.value = 0;
    loading.value = false;
    suggestingKeywords.value = false;
    busyHint.value = '';
    error.value = '';
    session.value = null;
    selectedHookIds.value = [];
};

const onHide = () => {
    reset();
};

const applyDraft = () => {
    if (!session.value?.draft) {
        return;
    }
    emit('apply', session.value.draft);
    visibleProxy.value = false;
};

const loadOptions = async () => {
    try {
        const { data } = await axios.get(route('blogAi.options'));
        if (data.clusters) {
            clusterOptions.value = Object.entries(data.clusters).map(([value, label]) => ({ value, label }));
        }
        queueEnabled.value = Boolean(data.queue);
        if (typeof data.image_enabled === 'boolean') {
            imageEnabled.value = data.image_enabled;
        }
    } catch {
        // keep defaults
    }
};

watch(
    () => props.visible,
    (open) => {
        if (open) {
            reset();
            loadOptions();
        }
    },
);

onBeforeUnmount(() => stopPoll());

const apiError = (e) => e?.response?.data?.message
    || e?.response?.data?.errors?.ai?.[0]
    || e?.response?.data?.errors?.keywords_text?.[0]
    || e?.response?.data?.errors?.draft?.[0]
    || Object.values(e?.response?.data?.errors || {})?.[0]?.[0]
    || e?.message
    || 'Request failed';

const readyStatusForStep = {
    research: 'keywords_ready',
    hooks: 'hooks_ready',
    outline: 'outline_ready',
    draft: 'draft_ready',
    image: 'image_ready',
};

const pollUntilReady = async (sessionId, readyStatus) => {
    busyHint.value = queueEnabled.value
        ? 'AI is working in the background (queue worker must be running)…'
        : 'AI is generating…';

    const generation = pollGeneration;
    const maxAttempts = 90;
    for (let i = 0; i < maxAttempts; i += 1) {
        await new Promise((r) => setTimeout(r, 2000));
        if (generation !== pollGeneration) {
            throw new Error('Cancelled');
        }
        const { data } = await axios.get(route('blogAi.show', sessionId));
        if (generation !== pollGeneration) {
            throw new Error('Cancelled');
        }
        session.value = data.session;
        if (data.session.status === readyStatus) {
            return data.session;
        }
        if (data.session.status === 'failed') {
            throw new Error(data.session.last_error || 'AI step failed.');
        }
    }

    // Best-effort unlock so the wizard is usable again.
    try {
        const { data } = await axios.post(route('blogAi.recover', sessionId));
        session.value = data.session;
    } catch {
        // ignore
    }

    throw new Error(
        queueEnabled.value
            ? 'Timed out waiting for AI. Run `php artisan queue:work` (or set BLOG_AI_QUEUE=false), unlock, and retry.'
            : 'Timed out waiting for AI. Unlock the session and try again.',
    );
};

const runQueuedOrSync = async (requestPromise, readyStatus) => {
    const { data } = await requestPromise;
    session.value = data.session;
    if (data.queued) {
        return pollUntilReady(data.session.id, readyStatus);
    }
    return data.session;
};

const unlockSession = async () => {
    if (!session.value?.id) {
        return;
    }
    error.value = '';
    loading.value = true;
    try {
        const { data } = await axios.post(route('blogAi.recover', session.value.id));
        session.value = data.session;
    } catch (e) {
        error.value = apiError(e);
    } finally {
        loading.value = false;
    }
};

const generateKeywords = async () => {
    error.value = '';
    suggestingKeywords.value = true;
    busyHint.value = 'Generating BD keywords…';
    try {
        const { data } = await axios.post(route('blogAi.suggestKeywords'), {
            cluster: cluster.value,
            seed_topic: seedTopic.value || null,
        });
        const text = data.keywords_text || (data.keywords || []).join('\n');
        if (!text) {
            error.value = 'AI returned no keywords. Try a seed topic or paste manually.';
            return;
        }
        keywordsText.value = text;
    } catch (e) {
        error.value = apiError(e);
    } finally {
        suggestingKeywords.value = false;
        busyHint.value = '';
    }
};

const regenerateHooks = async () => {
    error.value = '';
    loading.value = true;
    busyHint.value = 'Regenerating hooks…';
    try {
        session.value = await runQueuedOrSync(
            axios.post(route('blogAi.hooks', session.value.id)),
            readyStatusForStep.hooks,
        );
        selectedHookIds.value = [];
    } catch (e) {
        error.value = apiError(e);
    } finally {
        loading.value = false;
        busyHint.value = '';
    }
};

const runPrimary = async () => {
    error.value = '';
    loading.value = true;
    try {
        if (step.value === 0) {
            if (!keywordsText.value.trim()) {
                error.value = 'Paste at least one BD keyword first.';
                return;
            }
            const created = await axios.post(route('blogAi.store'), {
                cluster: cluster.value,
                seed_topic: seedTopic.value || null,
                keywords_text: keywordsText.value,
            });
            session.value = created.data.session;
            busyHint.value = 'Researching keywords…';
            session.value = await runQueuedOrSync(
                axios.post(route('blogAi.research', session.value.id), {
                    cluster: cluster.value,
                    seed_topic: seedTopic.value || null,
                    keywords_text: keywordsText.value,
                }),
                readyStatusForStep.research,
            );
            step.value = 1;
            return;
        }

        if (step.value === 1) {
            busyHint.value = 'Generating hooks…';
            session.value = await runQueuedOrSync(
                axios.post(route('blogAi.hooks', session.value.id)),
                readyStatusForStep.hooks,
            );
            selectedHookIds.value = [];
            step.value = 2;
            return;
        }

        if (step.value === 2) {
            busyHint.value = 'Building outline…';
            session.value = await runQueuedOrSync(
                axios.post(route('blogAi.outline', session.value.id), {
                    selected_hook_ids: selectedHookIds.value,
                }),
                readyStatusForStep.outline,
            );
            step.value = 3;
            return;
        }

        if (step.value === 3) {
            busyHint.value = 'Writing full draft (may take a minute)…';
            session.value = await runQueuedOrSync(
                axios.post(route('blogAi.draft', session.value.id)),
                readyStatusForStep.draft,
            );
            step.value = 4;
            return;
        }

        if (step.value === 4) {
            if (!imageEnabled.value) {
                applyDraft();
                return;
            }
            busyHint.value = 'Generating cover image…';
            session.value = await runQueuedOrSync(
                axios.post(route('blogAi.image', session.value.id)),
                readyStatusForStep.image,
            );
            step.value = 5;
            return;
        }

        if (step.value === 5 && session.value?.draft) {
            applyDraft();
        }
    } catch (e) {
        if (e?.message === 'Cancelled') {
            return;
        }
        error.value = apiError(e);
    } finally {
        loading.value = false;
        busyHint.value = '';
    }
};
</script>
