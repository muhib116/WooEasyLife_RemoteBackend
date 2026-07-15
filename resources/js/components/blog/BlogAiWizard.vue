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
                Landing-page truth → BD keywords → hooks → outline → SEO draft → marketing banner.
                Output is always a <strong>draft</strong> for human review — strong SEO process, not a ranking guarantee.
            </p>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                    :class="writerMode === 'auto'
                        ? 'bg-amber-500 text-black'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                    :disabled="loading || autoLoading"
                    @click="setWriterMode('auto')"
                >
                    Auto (1-click)
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                    :class="writerMode === 'manual'
                        ? 'bg-amber-500 text-black'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                    :disabled="loading || autoLoading"
                    @click="setWriterMode('manual')"
                >
                    Manual steps
                </button>
            </div>

            <!-- Auto mode -->
            <div v-if="writerMode === 'auto'" class="space-y-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Agents generate each step, review, then advance only on pass. Progress + live score update while running.
                    Optional fields steer; leave blank to use learning + market suggest.
                    <span v-if="!autoGenerateImage"> Auto skips cover images — add OG/cover in the editor before publish.</span>
                </p>

                <div
                    v-if="queueWarning"
                    class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-100"
                >
                    {{ queueWarning }}
                </div>

                <div
                    v-if="learningSummary"
                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-900 dark:text-emerald-100"
                >
                    <p class="font-semibold">Learning from live blog performance</p>
                    <p class="mt-1">{{ learningSummary }}</p>
                </div>

                <div v-if="showAutoInputs" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cluster (optional)</label>
                        <Select
                            v-model="cluster"
                            :options="clusterOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                            placeholder="Auto-pick from learning"
                            show-clear
                            :disabled="autoLoading"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Seed topic (optional)</label>
                        <InputText
                            v-model="seedTopic"
                            class="w-full"
                            placeholder="Leave blank to use next learning idea"
                            :disabled="autoLoading"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keywords (optional)</label>
                        <Textarea
                            v-model="keywordsText"
                            class="w-full"
                            rows="3"
                            placeholder="Optional — otherwise AI generates BD keywords"
                            :disabled="autoLoading"
                        />
                    </div>
                </div>

                <div v-if="autoRun" class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</p>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ autoStepLabel }} · {{ autoRun.progress_pct ?? 0 }}%
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Live score</p>
                            <p
                                class="text-2xl font-bold tabular-nums"
                                :class="scoreClass(autoRun.live_score)"
                            >
                                {{ autoRun.live_score ?? 0 }}
                            </p>
                        </div>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="h-full rounded-full bg-amber-500 transition-all duration-500"
                            :style="{ width: `${Math.min(100, autoRun.progress_pct || 0)}%` }"
                        />
                    </div>
                    <div
                        v-if="autoRun.score_breakdown"
                        class="flex flex-wrap gap-2 text-[11px] text-slate-500"
                    >
                        <span
                            v-for="(val, key) in autoRun.score_breakdown"
                            :key="key"
                            class="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800"
                        >
                            {{ key }}: {{ val ?? '—' }}
                        </span>
                    </div>
                    <div
                        class="max-h-40 space-y-1 overflow-y-auto rounded-lg bg-slate-50 p-2 text-xs dark:bg-slate-900/60"
                    >
                        <div
                            v-for="(entry, idx) in (autoRun.step_log || []).slice().reverse().slice(0, 12)"
                            :key="idx"
                            class="text-slate-600 dark:text-slate-300"
                        >
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ entry.step }}</span>
                            · {{ entry.event }} — {{ entry.message }}
                        </div>
                    </div>
                    <p v-if="autoRun.last_error" class="text-sm text-rose-600 dark:text-rose-400">
                        {{ autoRun.last_error }}
                    </p>
                    <p
                        v-if="queueWaitHint"
                        class="rounded-lg border border-amber-200/80 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        {{ queueWaitHint }}
                    </p>
                    <p
                        v-if="isAutoSuccess"
                        class="text-sm"
                        :class="autoRun.needs_review
                            ? 'text-amber-700 dark:text-amber-300'
                            : 'text-emerald-700 dark:text-emerald-400'"
                    >
                        <template v-if="autoRun.needs_review">
                            Done — needs review (score {{ autoRun.live_score }}).
                            <span v-if="autoRun.soft_pass"> SEO soft-pass.</span>
                            <span v-if="autoRun.soft_pass_steps?.length"> Steps: {{ autoRun.soft_pass_steps.join(', ') }}.</span>
                            <span v-if="autoRun.image_auto_approved"> Cover was auto-approved after QA fail.</span>
                            <span v-if="autoRun.image_skipped"> Cover skipped.</span>
                            <span v-if="autoRun.interrupted_recovery"> Recovered after queue interrupt.</span>
                            Polish in the editor before publishing.
                        </template>
                        <template v-else>
                            Ready
                            <span v-if="autoRun.blog_post_id"> — draft post #{{ autoRun.blog_post_id }}</span>.
                            Score {{ autoRun.live_score }}.
                        </template>
                    </p>
                </div>

                <p v-if="error" class="rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-600 dark:text-rose-400">
                    {{ error }}
                </p>
            </div>

            <template v-else>
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
                <div
                    v-if="learningSummary"
                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-900 dark:text-emerald-100"
                >
                    <p class="font-semibold">Learning from live blog performance</p>
                    <p class="mt-1">{{ learningSummary }}</p>
                    <p v-if="learningGaps.length" class="mt-1 text-emerald-800/80 dark:text-emerald-200/80">
                        Coverage gaps: {{ learningGaps.join(', ') }}
                    </p>
                    <ul v-if="nextPostIdeas.length" class="mt-2 list-disc space-y-1 pl-4 text-emerald-900/90 dark:text-emerald-100/90">
                        <li v-for="(idea, i) in nextPostIdeas" :key="i">
                            <button
                                type="button"
                                class="text-left underline decoration-dotted underline-offset-2 hover:text-amber-700 dark:hover:text-amber-300"
                                @click="applyIdea(idea)"
                            >
                                {{ idea.suggested_title || idea.seed_topic }}
                            </button>
                            <span class="opacity-70"> ({{ idea.cluster }})</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cluster</label>
                    <Select
                        v-model="cluster"
                        :options="clusterOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                        placeholder="Pick a topic cluster"
                        @update:model-value="onClusterChange"
                    />
                    <p
                        v-if="clusterBiasWarning"
                        class="mt-1 text-xs text-amber-700 dark:text-amber-300"
                    >
                        {{ clusterBiasWarning }}
                    </p>
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
                    <span :class="badgeClass(session.draft.quality.internal_links_ok)">
                        Links {{ session.draft.quality.internal_link_count || 0 }}
                    </span>
                    <span :class="badgeClass(session.draft.quality.keyword_in_title)">KW title</span>
                    <span :class="badgeClass(session.draft.quality.keyword_in_first_paragraph)">KW 1st ¶</span>
                    <span :class="badgeClass(session.draft.quality.keyword_in_meta)">KW meta</span>
                    <span :class="badgeClass(session.draft.quality.meta_description_ok)">Meta OK</span>
                    <span :class="badgeClass(session.draft.quality.faq_count_ok)">
                        FAQs {{ session.draft.quality.faq_count || 0 }}
                    </span>
                    <span :class="badgeClass(session.draft.quality.secondary_keyword_in_body)">Secondary KW</span>
                    <span :class="badgeClass(session.draft.quality.has_content_image)">Content img</span>
                    <span :class="badgeClass(!session.draft.quality.slug_collision)">Unique slug</span>
                    <span :class="badgeClass(!session.draft.quality.focus_keyword_collision)">Unique KW</span>
                    <span :class="badgeClass(session.draft.quality.ai_ready)">AI SEO ready</span>
                </div>
                <p
                    v-if="session?.draft?.quality && !session.draft.quality.ai_ready"
                    class="text-xs text-amber-700 dark:text-amber-400"
                >
                    Some SEO checks failed — you can still apply as draft and fix in the editor before publishing.
                    <span v-if="session.draft.quality.failures?.length">
                        ({{ session.draft.quality.failures.join(', ') }})
                    </span>
                </p>
                <p class="text-xs text-slate-500">
                    Calls: {{ session?.usage?.ai_calls || 0 }}
                    · Tokens: {{ session?.usage?.total_tokens || 0 }}
                    · Est. ${{ Number(session?.usage?.estimated_usd || 0).toFixed(4) }}
                </p>
                <p
                    v-if="session?.draft && !session?.draft?.og_image && !session?.image?.url"
                    class="rounded-lg border border-amber-200/80 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    No cover image yet. Prefer “Generate image”, or after “Skip image &amp; apply” add an OG image in the editor before publishing.
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

                <div
                    v-if="session?.status === 'image_needs_fix'"
                    class="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-amber-900 dark:text-amber-100"
                >
                    <p class="font-semibold">AI review: needs fix</p>
                    <p class="text-xs opacity-90">
                        Score: {{ session?.image?.review?.score ?? '—' }}
                        · Attempts: {{ session?.image?.attempts ?? '—' }}
                        · Calls this step: {{ session?.image?.ai_calls_this_step ?? '—' }}
                    </p>
                    <ul
                        v-if="session?.image?.review?.issues?.length"
                        class="list-disc space-y-1 pl-4 text-xs"
                    >
                        <li v-for="(issue, idx) in session.image.review.issues" :key="idx">{{ issue }}</li>
                    </ul>
                    <p v-else-if="session?.last_error" class="text-xs">{{ session.last_error }}</p>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <Button
                            label="Regenerate"
                            icon="pi pi-refresh"
                            size="small"
                            severity="warning"
                            :disabled="loading"
                            @click="regenerateImage"
                        />
                        <Button
                            label="Use anyway"
                            icon="pi pi-check"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="loading"
                            @click="approveImage"
                        />
                    </div>
                </div>

                <p
                    v-if="!session?.image?.url && !session?.draft?.og_image"
                    class="rounded-lg border border-amber-200/80 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    Skipping image is OK for a draft — remember to set OG / cover (+ body image) in the post form before you publish.
                </p>
                <p class="text-xs text-slate-500">
                    Full marketing banner with the same founder identity. AI reviews alignment + consistency before mark ready.
                    Dense banners may crop awkwardly on Facebook OG shares.
                </p>
                <p class="text-xs text-slate-500">
                    Calls: {{ session?.usage?.ai_calls || 0 }}
                    · Est. ${{ Number(session?.usage?.estimated_usd || 0).toFixed(4) }}
                    <span v-if="session?.image?.attempts"> · Banner attempts: {{ session.image.attempts }}</span>
                </p>
                <div
                    v-if="session?.status === 'image_ready' && session?.image?.url"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        label="Regenerate banner"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :disabled="loading"
                        @click="regenerateImage"
                    />
                </div>
            </div>
            </template>
        </div>

        <template #footer>
            <div class="flex flex-wrap justify-between gap-2">
                <Button
                    label="Close"
                    severity="secondary"
                    text
                    :disabled="loading || autoLoading"
                    @click="visibleProxy = false"
                />
                <div class="flex flex-wrap gap-2">
                    <template v-if="writerMode === 'auto'">
                        <Button
                            v-if="autoRunActive"
                            label="Cancel"
                            icon="pi pi-times"
                            severity="danger"
                            outlined
                            :disabled="cancelling"
                            :loading="cancelling"
                            @click="cancelAuto"
                        />
                        <Button
                            v-if="isAutoSuccess && session?.draft"
                            :label="autoRun?.blog_post_id ? 'Open draft post' : 'Apply to form'"
                            icon="pi pi-check"
                            :disabled="autoLoading"
                            @click="applyAutoDraft"
                        />
                        <Button
                            v-if="!autoRunActive"
                            :label="isAutoSuccess ? 'Create another' : 'Create with AI'"
                            icon="pi pi-sparkles"
                            :loading="autoLoading"
                            :disabled="autoLoading || Boolean(queueBlocked)"
                            @click="startAuto"
                        />
                    </template>
                    <template v-else>
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
                    </template>
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

const props = defineProps({
    visible: { type: Boolean, default: false },
});

const emit = defineEmits(['update:visible', 'apply', 'post-created']);

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
const cluster = ref(null);
const seedTopic = ref('');
const keywordsText = ref('');
const selectedHookIds = ref([]);
const queueEnabled = ref(true);
const imageEnabled = ref(true);
const autoEnabled = ref(true);
const autoRequireQueue = ref(false);
const autoApproveImage = ref(true);
const writerMode = ref('auto');
const autoLoading = ref(false);
const cancelling = ref(false);
const autoRun = ref(null);
const queueWaitHint = ref('');
const learningSummary = ref('');
const learningGaps = ref([]);
const recommendedClusters = ref([]);
const nextPostIdeas = ref([]);
const clusterBiasWarning = ref('');
let pollGeneration = 0;
let autoPollGeneration = 0;

const MODE_KEY = 'blog_ai_writer_mode';

const showAutoInputs = computed(() => {
    if (!autoRun.value) return true;
    return !['pending', 'running'].includes(autoRun.value.status);
});

const autoRunActive = computed(() => ['pending', 'running'].includes(autoRun.value?.status));

const isAutoSuccess = computed(() =>
    ['completed', 'completed_needs_review'].includes(autoRun.value?.status),
);

const queueBlocked = computed(() => autoRequireQueue.value && !queueEnabled.value);

const queueWarning = computed(() => {
    if (queueBlocked.value) {
        return 'Auto create is blocked until a queue worker is configured (BLOG_AI_QUEUE=true + php artisan queue:work). Manual steps still work sync.';
    }
    if (!queueEnabled.value) {
        return 'Queue is off — auto runs sync in this request and may time out if image generation is on. Prefer BLOG_AI_QUEUE=true in production.';
    }
    return '';
});

const autoStepLabel = computed(() => {
    const stepName = autoRun.value?.current_step || 'waiting';
    const map = {
        queued: 'Queued',
        intake: 'Market + learning',
        research: 'Keywords',
        hooks: 'Hooks',
        outline: 'Outline',
        draft: 'Draft',
        image: 'Image',
        finalize: 'Finalize',
        done: 'Done',
    };
    return map[stepName] || stepName;
});

const scoreClass = (score) => {
    const n = Number(score || 0);
    if (n >= 80) return 'text-emerald-600 dark:text-emerald-400';
    if (n >= 60) return 'text-amber-600 dark:text-amber-400';
    return 'text-rose-600 dark:text-rose-400';
};

const setWriterMode = (mode) => {
    writerMode.value = mode;
    try {
        localStorage.setItem(MODE_KEY, mode);
    } catch {
        // ignore
    }
};

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
    if (step.value === 4) return imageEnabled.value ? 'Generate marketing banner' : 'Apply to form';
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
    stopAutoPoll();
    step.value = 0;
    loading.value = false;
    autoLoading.value = false;
    cancelling.value = false;
    suggestingKeywords.value = false;
    busyHint.value = '';
    error.value = '';
    session.value = null;
    autoRun.value = null;
    queueWaitHint.value = '';
    selectedHookIds.value = [];
};

const isTransientPollError = (e) => {
    if (! e) {
        return false;
    }
    if (e?.response?.status === 429) {
        return true;
    }
    if (e.code === 'ECONNABORTED' || e.code === 'ERR_NETWORK' || e.code === 'ETIMEDOUT') {
        return true;
    }
    const msg = String(e.message || '').toLowerCase();
    return msg.includes('timeout')
        || msg.includes('network error')
        || msg.includes('err_timed_out')
        || msg.includes('failed to fetch')
        || msg.includes('429');
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const hydrateAutoRun = async (runId) => {
    const { data } = await axios.get(route('blogAi.runs.show', runId), {
        timeout: 45000,
    });
    autoRun.value = data.run;
    if (data.session) {
        session.value = data.session;
    }
    if (data.queue_hint) {
        queueWaitHint.value = data.queue_hint;
    }
    return data;
};

const resumeActiveRun = async (runId) => {
    if (! runId) {
        return false;
    }
    error.value = '';
    try {
        const data = await hydrateAutoRun(runId);
        const status = data.run?.status;
        if (['pending', 'running'].includes(status)) {
            autoLoading.value = true;
            queueWaitHint.value = queueWaitHint.value
                || 'Resumed active Auto run — waiting for progress…';
            await pollAutoRun(runId);
            return true;
        }
        if (['completed', 'completed_needs_review'].includes(status)) {
            autoLoading.value = false;
            queueWaitHint.value = '';
            return true;
        }
        autoLoading.value = false;
        if (status === 'failed' || status === 'cancelled') {
            error.value = data.run?.last_error || 'Previous Auto run stopped.';
        }
        return true;
    } catch (e) {
        autoLoading.value = false;
        error.value = e?.response?.data?.message || e?.message || 'Could not resume Auto run.';
        return false;
    }
};

const onHide = () => {
    reset();
};

const applyAutoDraft = () => {
    if (autoRun.value?.blog_post_id) {
        openCreatedPost();
        return;
    }
    if (!session.value?.draft) {
        return;
    }
    emit('apply', session.value.draft);
    visibleProxy.value = false;
};

const openCreatedPost = () => {
    const id = autoRun.value?.blog_post_id;
    if (!id) {
        return;
    }
    emit('post-created', id);
    visibleProxy.value = false;
    router.visit(route('blogPosts.edit', id));
};

const stopAutoPoll = () => {
    autoPollGeneration += 1;
};

const pollAutoRun = async (runId) => {
    const gen = ++autoPollGeneration;
    // Worker timeout is 900s; allow queue wait + OpenAI. Wall-clock ~28 minutes.
    const deadline = Date.now() + 28 * 60 * 1000;
    let consecutiveFailures = 0;
    let stuckQueuedTicks = 0;
    let stillQueued = true;

    while (Date.now() < deadline) {
        if (gen !== autoPollGeneration) {
            return;
        }
        // Slower while waiting for cron; snappier once the job is running.
        await sleep(stillQueued ? 4000 : 2500);
        if (gen !== autoPollGeneration) {
            return;
        }
        try {
            const { data } = await axios.get(route('blogAi.runs.show', runId), {
                timeout: 45000,
            });
            consecutiveFailures = 0;
            autoRun.value = data.run;
            if (data.session) {
                session.value = data.session;
            }

            const status = data.run?.status;
            const step = data.run?.current_step;
            stillQueued = status === 'pending'
                && (step === 'queued' || ! step || (data.run?.progress_pct ?? 0) === 0);

            if (stillQueued) {
                stuckQueuedTicks += 1;
                if (stuckQueuedTicks >= 8) {
                    queueWaitHint.value = data.queue_hint
                        || 'Still queued — waiting for the server queue worker (cPanel cron: php artisan queue:work database --stop-when-empty --max-jobs=1 --timeout=900). Confirm QUEUE_CONNECTION=database and BLOG_AI_QUEUE=true.';
                }
            } else {
                stuckQueuedTicks = 0;
                queueWaitHint.value = data.queue_hint || '';
            }

            if (['completed', 'completed_needs_review', 'failed', 'cancelled'].includes(status)) {
                autoLoading.value = false;
                queueWaitHint.value = '';
                if (status === 'failed' || status === 'cancelled') {
                    error.value = data.run.last_error || 'Auto pipeline stopped.';
                }
                return;
            }
        } catch (e) {
            consecutiveFailures += 1;
            if (isTransientPollError(e) && consecutiveFailures < 12) {
                const backoff = e?.response?.status === 429
                    ? Math.min(15000, 3000 * consecutiveFailures)
                    : 2000;
                queueWaitHint.value = e?.response?.status === 429
                    ? 'Rate limited — backing off and retrying…'
                    : 'Connection blip while polling — retrying…';
                await sleep(backoff);
                continue;
            }
            autoLoading.value = false;
            error.value = e?.response?.data?.message || e?.message || 'Failed to poll auto run.';
            return;
        }
    }
    autoLoading.value = false;
    // Soft timeout: keep last known run so Cancel/Open still work if the worker finishes late.
    if (['pending', 'running'].includes(autoRun.value?.status)) {
        queueWaitHint.value = 'Still running past the browser wait window. Leave this dialog open and use Cancel if needed, or reopen later — progress will resume.';
        error.value = '';
        // Keep polling lightly in background for another window.
        const softGen = gen;
        for (let i = 0; i < 60; i += 1) {
            if (softGen !== autoPollGeneration) {
                return;
            }
            await sleep(10000);
            if (softGen !== autoPollGeneration) {
                return;
            }
            try {
                const { data } = await axios.get(route('blogAi.runs.show', runId), { timeout: 45000 });
                autoRun.value = data.run;
                if (data.session) {
                    session.value = data.session;
                }
                if (['completed', 'completed_needs_review', 'failed', 'cancelled'].includes(data.run?.status)) {
                    autoLoading.value = false;
                    queueWaitHint.value = '';
                    if (data.run.status === 'failed' || data.run.status === 'cancelled') {
                        error.value = data.run.last_error || 'Auto pipeline stopped.';
                    }
                    return;
                }
            } catch {
                // ignore soft-window blips
            }
        }
        autoLoading.value = false;
        error.value = 'Auto pipeline is taking very long. Check cPanel queue:work cron, then reopen this dialog to resume.';
        return;
    }
    error.value = 'Auto pipeline timed out. Check that cPanel cron is running queue:work, then retry.';
};

const cancelAuto = async () => {
    if (!autoRun.value?.id) {
        return;
    }
    cancelling.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('blogAi.runs.cancel', autoRun.value.id));
        autoRun.value = data.run;
        if (data.session) {
            session.value = data.session;
        }
        stopAutoPoll();
        autoLoading.value = false;
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || 'Cancel failed.';
    } finally {
        cancelling.value = false;
    }
};

const startAuto = async () => {
    if (queueBlocked.value) {
        error.value = queueWarning.value;
        return;
    }
    error.value = '';
    queueWaitHint.value = '';
    autoLoading.value = true;
    autoRun.value = null;
    stopAutoPoll();
    try {
        const { data } = await axios.post(route('blogAi.auto'), {
            cluster: cluster.value || null,
            seed_topic: seedTopic.value || null,
            keywords_text: keywordsText.value || null,
            create_post: true,
        }, {
            timeout: 60000,
        });
        autoRun.value = data.run;
        session.value = data.session;
        if (data.queued) {
            queueWaitHint.value = 'Queued — waiting for the queue worker to start this job…';
            await pollAutoRun(data.run.id);
        } else {
            autoLoading.value = false;
        }
    } catch (e) {
        autoLoading.value = false;
        const activeFromError = Number(
            e?.response?.data?.errors?.active_run_id?.[0]
            || e?.response?.data?.active_run_id
            || 0,
        );
        if (activeFromError) {
            queueWaitHint.value = 'An Auto run is already active — resuming…';
            await resumeActiveRun(activeFromError);
            return;
        }
        // Create may have succeeded even if the HTTP response timed out — resume polling.
        if (isTransientPollError(e)) {
            try {
                const { data: opt } = await axios.get(route('blogAi.options'), { timeout: 20000 });
                const activeId = opt?.auto?.active_run_id;
                if (activeId) {
                    queueWaitHint.value = 'Reconnected to active Auto run after a timeout…';
                    await resumeActiveRun(activeId);
                    return;
                }
            } catch {
                // fall through
            }
        }
        const msg = e?.response?.data?.errors?.ai?.[0]
            || e?.response?.data?.message
            || e?.message
            || 'Auto create failed.';
        error.value = msg;
        if (e?.response?.data?.run) {
            autoRun.value = e.response.data.run;
        }
    }
};

const applyDraft = () => {
    if (!session.value?.draft) {
        return;
    }

    const q = session.value.draft.quality;
    if (q && q.ai_ready === false) {
        const ok = window.confirm(
            'SEO quality checks are incomplete. Apply anyway as a draft? Fix issues before publishing.',
        );
        if (!ok) {
            return;
        }
    }

    if (q?.focus_keyword_collision || q?.slug_collision) {
        const ok = window.confirm(
            'Slug or focus keyword collides with an existing post. Apply anyway? Consider changing them before publish.',
        );
        if (!ok) {
            return;
        }
    }

    const hasCover = Boolean(session.value.image?.url || session.value.draft.og_image);
    if (!hasCover) {
        const ok = window.confirm(
            'No cover/OG image on this draft. Apply anyway? Add an image in the editor before publishing.',
        );
        if (!ok) {
            return;
        }
    }

    emit('apply', session.value.draft);
    visibleProxy.value = false;
};

const loadOptions = async () => {
    try {
        const { data } = await axios.get(route('blogAi.options'), { timeout: 30000 });
        if (data.clusters) {
            clusterOptions.value = Object.entries(data.clusters).map(([value, label]) => ({ value, label }));
        }
        queueEnabled.value = Boolean(data.queue);
        if (typeof data.image_enabled === 'boolean') {
            imageEnabled.value = data.image_enabled;
        }
        autoEnabled.value = data.auto?.enabled !== false;
        autoRequireQueue.value = Boolean(data.auto?.require_queue);
        autoGenerateImage.value = Boolean(data.auto?.generate_image);
        autoApproveImage.value = data.auto?.auto_approve_image_on_fail !== false;
        try {
            const saved = localStorage.getItem(MODE_KEY);
            if (saved === 'manual' || saved === 'auto') {
                writerMode.value = saved;
            }
        } catch {
            // ignore
        }
        if (!autoEnabled.value) {
            writerMode.value = 'manual';
        }
        learningSummary.value = data.learning?.summary_bn
            || (data.learning?.status === 'cold_start' ? data.learning?.note : '')
            || '';
        learningGaps.value = Array.isArray(data.learning?.coverage_gaps)
            ? data.learning.coverage_gaps
            : [];
        recommendedClusters.value = Array.isArray(data.learning?.recommended_clusters)
            ? data.learning.recommended_clusters
            : [];
        nextPostIdeas.value = Array.isArray(data.learning?.next_post_ideas)
            ? data.learning.next_post_ideas.slice(0, 5)
            : [];
        onClusterChange(cluster.value);

        const activeId = data.auto?.active_run_id;
        if (activeId && writerMode.value === 'auto') {
            await resumeActiveRun(activeId);
        }
    } catch {
        // keep defaults
    }
};

const onClusterChange = (value) => {
    const list = recommendedClusters.value || [];
    if (!list.length || !value) {
        clusterBiasWarning.value = '';
        return;
    }
    if (!list.includes(value)) {
        clusterBiasWarning.value = `“${value}” is outside current recommended clusters (${list.join(', ')}). Learning data suggests staying on recommended topics for better results.`;
    } else {
        clusterBiasWarning.value = '';
    }
};

const applyIdea = (idea) => {
    if (!idea) return;
    if (idea.cluster) {
        cluster.value = idea.cluster;
        onClusterChange(idea.cluster);
    }
    if (idea.seed_topic) {
        seedTopic.value = idea.seed_topic;
    }
    if (idea.suggested_title && !keywordsText.value.trim()) {
        keywordsText.value = idea.seed_topic || idea.suggested_title;
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

onBeforeUnmount(() => {
    stopPoll();
    stopAutoPoll();
});

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
    image: ['image_ready', 'image_needs_fix'],
};

const statusMatchesReady = (status, readyStatus) => {
    if (Array.isArray(readyStatus)) {
        return readyStatus.includes(status);
    }
    return status === readyStatus;
};

const pollUntilReady = async (sessionId, readyStatus) => {
    busyHint.value = queueEnabled.value
        ? 'AI is working in the background (queue worker must be running)…'
        : 'AI is generating…';

    const generation = pollGeneration;
    const deadline = Date.now() + 20 * 60 * 1000;
    let failures = 0;
    while (Date.now() < deadline) {
        await sleep(2500);
        if (generation !== pollGeneration) {
            throw new Error('Cancelled');
        }
        try {
            const { data } = await axios.get(route('blogAi.show', sessionId), { timeout: 45000 });
            failures = 0;
            if (generation !== pollGeneration) {
                throw new Error('Cancelled');
            }
            session.value = data.session;
            if (statusMatchesReady(data.session.status, readyStatus)) {
                return data.session;
            }
            if (data.session.status === 'failed') {
                throw new Error(data.session.last_error || 'AI step failed.');
            }
        } catch (e) {
            if (e?.message === 'Cancelled') {
                throw e;
            }
            failures += 1;
            if (isTransientPollError(e) && failures < 10) {
                busyHint.value = 'Connection blip — retrying AI status…';
                await sleep(e?.response?.status === 429 ? 5000 : 2000);
                continue;
            }
            throw new Error(apiError(e));
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
        if (step.value === 0 && clusterBiasWarning.value) {
            const ok = window.confirm(
                `${clusterBiasWarning.value}\n\nContinue with this cluster anyway?`,
            );
            if (!ok) {
                loading.value = false;
                return;
            }
        }

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
            busyHint.value = 'Generating marketing banner + AI review…';
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

const regenerateImage = async () => {
    if (!session.value?.id) {
        return;
    }
    error.value = '';
    loading.value = true;
    busyHint.value = 'Regenerating marketing banner + AI review…';
    try {
        session.value = await runQueuedOrSync(
            axios.post(route('blogAi.image.regenerate', session.value.id)),
            readyStatusForStep.image,
        );
        step.value = 5;
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

const approveImage = async () => {
    if (!session.value?.id) {
        return;
    }
    error.value = '';
    loading.value = true;
    try {
        const { data } = await axios.post(route('blogAi.image.approve', session.value.id));
        session.value = data.session;
    } catch (e) {
        error.value = apiError(e);
    } finally {
        loading.value = false;
    }
};
</script>
