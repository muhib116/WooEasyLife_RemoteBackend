<template>
    <AuthenticatedLayout title="Wise AI — Log Hub">
        <div class="space-y-5">
            <PageHeader
                title="Log Hub"
                description="Sealed decide traffic — request, response, trace, and thread in one analyzer"
                icon="PhHardDrives"
                icon-bg-class="bg-slate-50 dark:bg-slate-500/15"
                icon-class="text-slate-700 dark:text-slate-200"
            >
                <template #actions>
                    <StatusBadge :label="'brain ' + brain_version" variant="neutral" format="none" />
                    <Button
                        label="Refresh"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :loading="refreshing"
                        @click="refresh"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <StatCard
                    title="Matched"
                    :value="stats.matched"
                    icon="PhChatCircleDots"
                    accent-class="bg-slate-700"
                    icon-bg-class="bg-slate-50 dark:bg-slate-500/15"
                    icon-class="text-slate-700 dark:text-slate-200"
                    :subtitle="windowLabel"
                />
                <StatCard
                    title="Gap rate"
                    :value="stats.gap_rate + '%'"
                    icon="PhWarning"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                    :subtitle="stats.gaps + ' gaps'"
                />
                <StatCard
                    title="Avg latency"
                    :value="latencyLabel(stats.avg_latency_ms)"
                    icon="PhTimer"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                    :subtitle="stats.p95_latency_ms != null ? 'p95 ' + stats.p95_latency_ms + ' ms' : 'p95 —'"
                />
                <StatCard
                    title="Avg confidence"
                    :value="stats.avg_confidence != null ? stats.avg_confidence + '%' : '—'"
                    icon="PhTarget"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                    subtitle="Filtered set"
                />
                <PageCard class="!p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Action mix</p>
                    <ul v-if="stats.action_mix.length" class="mt-2 space-y-1.5">
                        <li
                            v-for="row in stats.action_mix.slice(0, 4)"
                            :key="row.action"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <button
                                type="button"
                                class="truncate text-left font-medium text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                                @click="quickFilter({ action: row.action })"
                            >
                                {{ row.action }}
                            </button>
                            <span class="font-semibold text-gray-800 dark:text-white">{{ row.count }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-gray-400">No actions in window</p>
                </PageCard>
            </div>

            <PageCard title="Probe filters" description="Time window + search across text, reply, intent, conversation">
                <form class="space-y-3" @submit.prevent="applyFilters(1)">
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="opt in hour_options"
                            :key="opt.value"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                Number(draft.hours) === Number(opt.value)
                                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-800 dark:text-gray-300'
                            "
                            @click="draft.hours = opt.value"
                        >
                            {{ opt.label }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500">Search</label>
                            <input
                                ref="searchInput"
                                v-model="draft.q"
                                type="search"
                                placeholder="text · reply · intent · conversation · turn #"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Channel</label>
                            <select
                                v-model="draft.channel"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            >
                                <option value="">All channels</option>
                                <option v-for="ch in channels" :key="ch" :value="ch">{{ ch }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">API key</label>
                            <select
                                v-model="draft.key_id"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            >
                                <option :value="null">All keys</option>
                                <option v-for="k in api_keys" :key="k.id" :value="k.id">
                                    {{ k.name }} ({{ k.key_prefix }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Gap</label>
                            <select
                                v-model="draft.gap"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            >
                                <option value="all">All</option>
                                <option value="yes">Gap only</option>
                                <option value="no">No gap</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Action</label>
                            <select
                                v-model="draft.action"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            >
                                <option value="">All</option>
                                <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Source</label>
                            <select
                                v-model="draft.source"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            >
                                <option value="">All</option>
                                <option v-for="s in sources" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Status</label>
                            <select
                                v-model="draft.status"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            >
                                <option value="">All</option>
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="submit" label="Apply" icon="pi pi-filter" size="small" />
                        <Button
                            type="button"
                            label="Clear"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="clearFilters"
                        />
                        <span
                            v-if="draft.conversation_id"
                            class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200"
                            :title="draft.conversation_id"
                        >
                            <Icon name="PhChatTeardropText" :size="12" />
                            <span class="max-w-[14rem] truncate font-mono">{{ shortId(draft.conversation_id) }}</span>
                            <button
                                type="button"
                                class="rounded-full px-1 opacity-70 hover:bg-indigo-100 hover:opacity-100 dark:hover:bg-indigo-500/30"
                                aria-label="Clear thread filter"
                                @click="clearThreadFilter"
                            >
                                ×
                            </button>
                        </span>
                        <span class="ml-auto hidden text-[11px] text-gray-400 sm:inline">
                            j/k · / · c · r
                        </span>
                    </div>
                </form>
            </PageCard>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 xl:items-start">
                <PageCard
                    class="xl:col-span-5 xl:sticky xl:top-4 xl:self-start"
                    title="Traffic"
                    :description="listRangeLabel"
                    :no-padding="turns.length > 0"
                >
                    <div
                        v-if="turns.length"
                        class="max-h-[calc(100vh-10rem)] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800"
                    >
                        <button
                            v-for="row in turns"
                            :key="row.id"
                            type="button"
                            class="w-full border-l-2 px-4 py-3 text-left transition-colors"
                            :class="
                                selectedId === row.id
                                    ? 'border-l-fuchsia-500 bg-fuchsia-50/90 dark:bg-fuchsia-500/10'
                                    : 'border-l-transparent hover:bg-gray-50 dark:hover:bg-slate-800/60'
                            "
                            @click="selectTurn(row.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono text-[11px] text-gray-400">#{{ row.id }}</span>
                                        <StatusBadge :label="row.channel || '—'" variant="neutral" format="none" />
                                        <StatusBadge
                                            :label="row.action || '?'"
                                            :variant="actionVariant(row.action)"
                                            format="none"
                                        />
                                        <StatusBadge v-if="row.gap" label="gap" variant="danger" format="none" />
                                        <StatusBadge
                                            v-if="row.status && row.status !== 'ok'"
                                            :label="row.status"
                                            variant="warning"
                                            format="none"
                                        />
                                    </div>
                                    <p class="mt-1.5 line-clamp-2 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ row.text || "—" }}
                                    </p>
                                    <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ row.suggested_reply || "No suggested reply" }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-400">
                                        <span :title="formatAbsolute(row.created_at)">{{ formatWhen(row.created_at) }}</span>
                                        <span>{{ row.intent || "?" }} · {{ row.confidence ?? 0 }}%</span>
                                        <span>{{ row.source || "—" }}</span>
                                        <span v-if="row.has_context">ctx {{ row.context_keys }}</span>
                                        <span v-if="row.knowledge_id">kb #{{ row.knowledge_id }}</span>
                                    </div>
                                </div>
                                <div class="w-16 shrink-0 text-right">
                                    <p class="font-mono text-[11px] text-gray-500">
                                        {{ row.latency_ms != null ? row.latency_ms + "ms" : "—" }}
                                    </p>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800">
                                        <div
                                            class="h-full rounded-full"
                                            :class="latencyBarClass(row.latency_ms)"
                                            :style="{ width: latencyBarWidth(row.latency_ms) }"
                                        />
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800">
                                        <div
                                            class="h-full rounded-full bg-emerald-500"
                                            :style="{ width: confidenceBarWidth(row.confidence) }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                    <EmptyState
                        v-else
                        title="No turns in this probe"
                        description="Widen the time window or clear filters. Playground / Messenger decides land here."
                    />

                    <div
                        v-if="pagination.last_page > 1"
                        class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800"
                    >
                        <Button
                            label="Prev"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="pagination.page <= 1"
                            @click="applyFilters(pagination.page - 1)"
                        />
                        <span class="text-xs text-gray-500">
                            Page {{ pagination.page }} / {{ pagination.last_page }}
                        </span>
                        <Button
                            label="Next"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="pagination.page >= pagination.last_page"
                            @click="applyFilters(pagination.page + 1)"
                        />
                    </div>
                </PageCard>

                <PageCard
                    class="xl:col-span-7 xl:sticky xl:top-4 xl:self-start"
                    title="Inspector"
                    :description="inspectorHint"
                >
                    <div v-if="detailLoading" class="py-16 text-center text-sm text-gray-500">
                        Loading sealed turn…
                    </div>
                    <div v-else-if="detailError" class="py-10 text-center text-sm text-rose-600">
                        {{ detailError }}
                    </div>
                    <div v-else-if="!detail" class="py-16 text-center text-sm text-gray-400">
                        Select a turn from traffic to inspect request ↔ response.
                    </div>
                    <div v-else class="max-h-[calc(100vh-8rem)] space-y-4 overflow-y-auto pr-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm font-semibold text-gray-800 dark:text-white">
                                #{{ detail.turn.id }}
                            </span>
                            <StatusBadge :label="detail.turn.channel || '—'" variant="neutral" format="none" />
                            <StatusBadge
                                v-if="detail.highlights.action"
                                :label="String(detail.highlights.action)"
                                :variant="actionVariant(String(detail.highlights.action))"
                                format="none"
                            />
                            <StatusBadge v-if="detail.turn.gap" label="gap" variant="danger" format="none" />
                            <span
                                v-if="detail.turn.latency_ms != null"
                                class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-slate-800 dark:text-gray-300"
                            >
                                {{ detail.turn.latency_ms }} ms
                            </span>
                            <span
                                v-if="detail.turn.brain_version"
                                class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-slate-800 dark:text-gray-300"
                            >
                                {{ detail.turn.brain_version }}
                            </span>
                            <span class="text-[11px] text-gray-400">
                                {{ detail.turn.key_name || "key" }}
                            </span>
                            <div class="ml-auto flex shrink-0 gap-1.5">
                                <Button
                                    icon="pi pi-chevron-left"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="!detail.nav.prev_id"
                                    v-tooltip.top="'Older turn (k)'"
                                    @click="selectTurn(detail.nav.prev_id)"
                                />
                                <Button
                                    icon="pi pi-chevron-right"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="!detail.nav.next_id"
                                    v-tooltip.top="'Newer turn (j)'"
                                    @click="selectTurn(detail.nav.next_id)"
                                />
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <section
                                class="flex min-w-0 flex-col rounded-xl border border-sky-100 bg-sky-50/40 p-3.5 dark:border-sky-900/40 dark:bg-sky-950/20"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                                        Request
                                    </h3>
                                    <Button
                                        label="Copy"
                                        size="small"
                                        text
                                        @click="copyText(pretty(detail.request), 'Request copied')"
                                    />
                                </div>
                                <p class="mt-2 line-clamp-4 text-sm font-medium leading-relaxed text-gray-900 dark:text-white">
                                    {{ detail.highlights.text || "—" }}
                                </p>

                                <div class="mt-auto space-y-2 pt-3">
                                    <div
                                        v-if="detail.turn.conversation_id"
                                        class="rounded-lg border border-sky-100/80 bg-white/70 px-2.5 py-2 dark:border-sky-900/50 dark:bg-slate-950/40"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                                Conversation
                                            </p>
                                            <div class="flex shrink-0 gap-0.5">
                                                <button
                                                    type="button"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
                                                    title="Filter traffic to this thread"
                                                    @click="filterThread(String(detail.turn.conversation_id))"
                                                >
                                                    Filter
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800"
                                                    title="Copy conversation id"
                                                    @click="copyText(String(detail.turn.conversation_id), 'Conversation id copied')"
                                                >
                                                    Copy
                                                </button>
                                            </div>
                                        </div>
                                        <p
                                            class="mt-1 truncate font-mono text-[11px] text-gray-700 dark:text-gray-200"
                                            :title="detail.turn.conversation_id"
                                        >
                                            {{ shortId(String(detail.turn.conversation_id)) }}
                                        </p>
                                    </div>
                                    <p v-else class="text-[11px] text-gray-400">No conversation_id</p>
                                    <p v-if="detail.highlights.context_keys?.length" class="text-[11px] text-gray-500">
                                        context: {{ detail.highlights.context_keys.join(", ") }}
                                        <span class="text-gray-400">
                                            ({{ detail.highlights.context_bytes }} B)
                                        </span>
                                    </p>
                                </div>
                            </section>

                            <section
                                class="flex min-w-0 flex-col rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                                        Response
                                    </h3>
                                    <Button
                                        label="Copy"
                                        size="small"
                                        text
                                        @click="copyText(pretty(detail.response), 'Response copied')"
                                    />
                                </div>
                                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ detail.highlights.action || "?" }}
                                    <span class="font-normal text-gray-400">→</span>
                                    {{ detail.highlights.intent || "?" }}
                                    <span class="text-gray-400">
                                        ({{ detail.highlights.confidence ?? 0 }}%)
                                    </span>
                                </p>
                                <p class="mt-2 line-clamp-4 flex-1 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                                    {{ detail.highlights.suggested_reply || "—" }}
                                </p>
                                <p class="mt-3 text-[11px] text-gray-500">
                                    source {{ detail.highlights.source || "—" }}
                                    <template v-if="detail.highlights.knowledge_id">
                                        · kb #{{ detail.highlights.knowledge_id }}
                                    </template>
                                    <template v-if="detail.highlights.match_score != null">
                                        · match {{ detail.highlights.match_score }}
                                    </template>
                                </p>
                            </section>
                        </div>

                        <div
                            class="grid grid-cols-3 gap-0.5 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-slate-900/60 sm:grid-cols-6"
                        >
                            <button
                                v-for="tab in inspectorTabs"
                                :key="tab.id"
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-1 rounded-lg px-1.5 text-[11px] font-semibold transition-colors"
                                :class="
                                    inspectorTab === tab.id
                                        ? 'bg-white text-fuchsia-700 shadow-sm dark:bg-slate-800 dark:text-fuchsia-300'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'
                                "
                                :title="tab.label"
                                @click="inspectorTab = tab.id"
                            >
                                <Icon :name="tab.icon" :size="13" class="shrink-0" />
                                <span class="truncate">{{ tab.short }}</span>
                            </button>
                        </div>

                        <div v-if="inspectorTab === 'pair'" class="grid gap-3 lg:grid-cols-2">
                            <div class="min-w-0 overflow-hidden rounded-xl border border-sky-900/30 bg-slate-950">
                                <div class="flex items-center justify-between border-b border-sky-900/40 px-3 py-1.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-sky-400">
                                        Request JSON
                                    </span>
                                    <button
                                        type="button"
                                        class="text-[10px] font-semibold text-sky-400 hover:text-sky-200"
                                        @click="copyText(pretty(detail.request), 'Request copied')"
                                    >
                                        Copy
                                    </button>
                                </div>
                                <pre class="max-h-[24rem] overflow-auto p-3 font-mono text-[11px] leading-relaxed text-sky-200">{{ pretty(detail.request) }}</pre>
                            </div>
                            <div class="min-w-0 overflow-hidden rounded-xl border border-emerald-900/30 bg-slate-950">
                                <div class="flex items-center justify-between border-b border-emerald-900/40 px-3 py-1.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400">
                                        Response JSON
                                    </span>
                                    <button
                                        type="button"
                                        class="text-[10px] font-semibold text-emerald-400 hover:text-emerald-200"
                                        @click="copyText(pretty(detail.response), 'Response copied')"
                                    >
                                        Copy
                                    </button>
                                </div>
                                <pre class="max-h-[24rem] overflow-auto p-3 font-mono text-[11px] leading-relaxed text-emerald-200">{{ pretty(detail.response) }}</pre>
                            </div>
                        </div>

                        <div v-else-if="inspectorTab === 'trace'" class="space-y-2">
                            <ol v-if="detail.trace_steps.length" class="space-y-2">
                                <li
                                    v-for="(step, idx) in detail.trace_steps"
                                    :key="step.key + '-' + idx"
                                    class="rounded-xl border border-gray-100 px-3 py-2 dark:border-gray-800"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-fuchsia-50 font-mono text-[10px] font-bold text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-300"
                                        >
                                            {{ idx + 1 }}
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ step.key }}</span>
                                    </div>
                                    <p class="mt-1 pl-7 text-xs text-gray-500">{{ step.summary }}</p>
                                    <details class="mt-1 pl-7">
                                        <summary class="cursor-pointer text-[11px] text-fuchsia-600 dark:text-fuchsia-300">
                                            raw
                                        </summary>
                                        <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-slate-950 p-2 font-mono text-[10px] text-emerald-200">{{ pretty(step.raw) }}</pre>
                                    </details>
                                </li>
                            </ol>
                            <EmptyState v-else title="No trace" description="This turn has no sealed pipeline trace." />
                        </div>

                        <div v-else-if="inspectorTab === 'thread'" class="space-y-2">
                            <div
                                v-if="detail.turn.conversation_id"
                                class="flex items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-2 text-[11px] dark:bg-slate-800/50"
                            >
                                <span class="shrink-0 text-gray-400">Thread</span>
                                <span
                                    class="min-w-0 truncate font-mono text-gray-700 dark:text-gray-200"
                                    :title="detail.turn.conversation_id"
                                >
                                    {{ shortId(String(detail.turn.conversation_id)) }}
                                </span>
                                <button
                                    type="button"
                                    class="ml-auto shrink-0 font-semibold text-indigo-600 hover:underline dark:text-indigo-300"
                                    @click="filterThread(String(detail.turn.conversation_id))"
                                >
                                    Filter list
                                </button>
                            </div>
                            <button
                                v-for="item in detail.thread"
                                :key="item.id"
                                type="button"
                                class="flex w-full items-start justify-between gap-3 rounded-xl border px-3 py-2 text-left transition-colors"
                                :class="
                                    item.is_current
                                        ? 'border-fuchsia-300 bg-fuchsia-50/70 dark:border-fuchsia-500/40 dark:bg-fuchsia-500/10'
                                        : 'border-gray-100 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-slate-800/50'
                                "
                                @click="selectTurn(item.id)"
                            >
                                <div class="min-w-0">
                                    <p class="font-mono text-[11px] text-gray-400" :title="formatAbsolute(item.created_at)">
                                        #{{ item.id }} · {{ formatWhen(item.created_at) }}
                                    </p>
                                    <p class="mt-0.5 line-clamp-2 text-sm text-gray-800 dark:text-gray-100">{{ item.text || "—" }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <StatusBadge :label="item.action || '?'" variant="neutral" format="none" />
                                    <p class="mt-1 text-[10px] text-gray-400">
                                        {{ item.latency_ms != null ? item.latency_ms + "ms" : "—" }}
                                    </p>
                                </div>
                            </button>
                            <EmptyState
                                v-if="!detail.thread.length"
                                title="No thread turns"
                                description="Missing conversation_id — cannot group related decides."
                            />
                        </div>

                        <div
                            v-else
                            class="min-w-0 overflow-hidden rounded-xl border border-gray-800 bg-slate-950"
                        >
                            <div class="flex items-center justify-between border-b border-gray-800 px-3 py-1.5">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400">
                                    {{ inspectorTab }} JSON
                                </span>
                                <button
                                    type="button"
                                    class="text-[10px] font-semibold text-emerald-400 hover:text-emerald-200"
                                    @click="copyText(activeInspectorJson, 'Copied')"
                                >
                                    Copy
                                </button>
                            </div>
                            <pre class="max-h-[28rem] overflow-auto p-3 font-mono text-[11px] leading-relaxed text-emerald-200">{{ activeInspectorJson }}</pre>
                        </div>

                        <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                            <Button
                                label="Copy pair"
                                icon="pi pi-copy"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="copyPair"
                            />
                            <Button
                                label="Download JSON"
                                icon="pi pi-download"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="downloadTurn"
                            />
                            <Button
                                label="Replay"
                                icon="pi pi-play"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="openReplay"
                            />
                            <Button
                                v-if="detail.turn.conversation_id"
                                label="Filter thread"
                                icon="pi pi-filter"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="filterThread(String(detail.turn.conversation_id))"
                            />
                        </div>
                    </div>
                </PageCard>
            </div>
        </div>

        <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";
import Button from "primevue/button";
import { useToast } from "primevue/usetoast";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import TurnReplayDialog from "./fragments/TurnReplayDialog.vue";

type ApiKeyOption = { id: number; name: string; key_prefix: string | null };
type HourOption = { value: number; label: string };

type TurnRow = {
    id: number;
    created_at: string | null;
    channel: string | null;
    conversation_id: string | null;
    text: string | null;
    action: string | null;
    intent: string | null;
    confidence: number | null;
    source: string | null;
    gap: boolean;
    status: string | null;
    latency_ms: number | null;
    suggested_reply: string | null;
    key_name: string | null;
    key_prefix: string | null;
    brain_version: string | null;
    has_context: boolean;
    context_keys: number;
    knowledge_id: number | null;
};

type LogDetail = {
    turn: {
        id: number;
        created_at: string | null;
        latency_ms: number | null;
        status: string | null;
        gap: boolean;
        channel: string | null;
        conversation_id: string | null;
        wise_api_key_id?: number | null;
        key_name: string | null;
        key_prefix: string | null;
        brain_version: string | null;
    };
    nav: { prev_id: number | null; next_id: number | null };
    highlights: {
        text: string | null;
        action: string | null;
        intent: string | null;
        confidence: number | null;
        source: string | null;
        suggested_reply: string | null;
        reason: string | null;
        knowledge_id: number | null;
        match_score: number | null;
        context_keys: string[];
        context_bytes: number;
    };
    trace_steps: { key: string; summary: string; raw: unknown }[];
    thread: {
        id: number;
        created_at: string | null;
        text: string | null;
        action: string | null;
        gap: boolean;
        latency_ms: number | null;
        is_current: boolean;
    }[];
    request: Record<string, unknown>;
    response: Record<string, unknown>;
    trace: Record<string, unknown> | null;
    config_snapshot: Record<string, unknown> | null;
};

const props = defineProps<{
    turns: TurnRow[];
    pagination: {
        page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
    stats: {
        matched: number;
        gaps: number;
        gap_rate: number;
        avg_latency_ms: number | null;
        p95_latency_ms: number | null;
        avg_confidence: number | null;
        action_mix: { action: string; count: number }[];
        channel_mix: { channel: string; count: number }[];
    };
    filters: {
        channel: string;
        q: string;
        key_id: number | null;
        gap: string;
        action: string;
        source: string;
        status: string;
        conversation_id: string;
        hours: number;
        per_page: number;
        page: number;
        turn: number | null;
    };
    channels: string[];
    sources: string[];
    actions: string[];
    statuses: string[];
    hour_options: HourOption[];
    api_keys: ApiKeyOption[];
    brain_version: string;
}>();

const toast = useToast();
const searchInput = ref<HTMLInputElement | null>(null);
const refreshing = ref(false);

const draft = reactive({
    q: props.filters.q || "",
    channel: props.filters.channel || "",
    key_id: props.filters.key_id,
    gap: props.filters.gap || "all",
    action: props.filters.action || "",
    source: props.filters.source || "",
    status: props.filters.status || "",
    conversation_id: props.filters.conversation_id || "",
    hours: props.filters.hours ?? 24,
    per_page: props.filters.per_page || 40,
});

const selectedId = ref<number | null>(props.filters.turn);
const detailLoading = ref(false);
const detailError = ref("");
const detail = ref<LogDetail | null>(null);
const inspectorTab = ref<"pair" | "request" | "response" | "trace" | "thread" | "config">("pair");

const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);

const inspectorTabs = [
    { id: "pair" as const, label: "Req ↔ Res", short: "Pair", icon: "PhColumns" as const },
    { id: "request" as const, label: "Request", short: "Req", icon: "PhArrowUpRight" as const },
    { id: "response" as const, label: "Response", short: "Res", icon: "PhArrowDownLeft" as const },
    { id: "trace" as const, label: "Trace", short: "Trace", icon: "PhPath" as const },
    { id: "thread" as const, label: "Thread", short: "Thread", icon: "PhChatTeardropText" as const },
    { id: "config" as const, label: "Config", short: "Config", icon: "PhSlidersHorizontal" as const },
];

const windowLabel = computed(() => {
    const hit = props.hour_options.find((o) => Number(o.value) === Number(props.filters.hours));
    return hit ? hit.label + " window" : "window";
});

const listRangeLabel = computed(() => {
    if (!props.pagination.total) return "0 turns";
    return (
        (props.pagination.from ?? 0) +
        "–" +
        (props.pagination.to ?? 0) +
        " of " +
        props.pagination.total
    );
});

const inspectorHint = computed(() => {
    if (!detail.value) return "Pick a sealed turn to open the analyzer";
    return "Sealed payload · decision · evidence · pipeline";
});

const activeInspectorJson = computed(() => {
    if (!detail.value) return "";
    if (inspectorTab.value === "request") return pretty(detail.value.request);
    if (inspectorTab.value === "response") return pretty(detail.value.response);
    if (inspectorTab.value === "config") return pretty(detail.value.config_snapshot ?? {});
    return "";
});

const maxLatency = computed(() => {
    const values = props.turns.map((t) => t.latency_ms || 0);
    return Math.max(250, ...values, 1);
});

function pretty(value: unknown): string {
    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return String(value);
    }
}

/** Truncate long ids (Messenger conversation_id) for display; full value stays in title/copy. */
function shortId(id: string, head = 18, tail = 10): string {
    if (!id || id.length <= head + tail + 1) return id;
    return `${id.slice(0, head)}…${id.slice(-tail)}`;
}

function latencyLabel(ms: number | null): string {
    return ms != null ? ms + " ms" : "—";
}

function formatWhen(iso: string | null): string {
    if (!iso) return "—";
    try {
        const then = new Date(iso).getTime();
        if (Number.isNaN(then)) return iso;
        const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
        if (seconds < 45) return "just now";
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return minutes === 1 ? "1 minute ago" : minutes + " minutes ago";
        const hours = Math.round(minutes / 60);
        if (hours < 24) return hours === 1 ? "1 hour ago" : hours + " hours ago";
        const days = Math.round(hours / 24);
        if (days < 30) return days === 1 ? "1 day ago" : days + " days ago";
        const months = Math.round(days / 30);
        if (months < 12) return months === 1 ? "1 month ago" : months + " months ago";
        const years = Math.round(days / 365);
        return years === 1 ? "1 year ago" : years + " years ago";
    } catch {
        return iso;
    }
}

function formatAbsolute(iso: string | null): string {
    if (!iso) return "";
    try {
        return new Date(iso).toLocaleString(undefined, {
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        });
    } catch {
        return iso;
    }
}

function actionVariant(action: string | null): "info" | "warning" | "danger" | "neutral" {
    if (action === "suggest_reply") return "info";
    if (action === "clarify") return "warning";
    if (action === "needs_human") return "danger";
    return "neutral";
}

function latencyBarWidth(ms: number | null): string {
    if (ms == null) return "0%";
    return Math.min(100, Math.round((ms / maxLatency.value) * 100)) + "%";
}

function latencyBarClass(ms: number | null): string {
    if (ms == null) return "bg-gray-300";
    if (ms < 200) return "bg-emerald-500";
    if (ms < 600) return "bg-amber-500";
    return "bg-rose-500";
}

function confidenceBarWidth(confidence: number | null): string {
    if (confidence == null) return "0%";
    return Math.max(0, Math.min(100, confidence)) + "%";
}

function filterParams(page = 1, turnId: number | null = selectedId.value) {
    const params: Record<string, string | number> = {
        hours: draft.hours,
        per_page: draft.per_page,
        page,
    };
    if (draft.q.trim()) params.q = draft.q.trim();
    if (draft.channel) params.channel = draft.channel;
    if (draft.key_id) params.key_id = draft.key_id;
    if (draft.gap && draft.gap !== "all") params.gap = draft.gap;
    if (draft.action) params.action = draft.action;
    if (draft.source) params.source = draft.source;
    if (draft.status) params.status = draft.status;
    if (draft.conversation_id) params.conversation_id = draft.conversation_id;
    if (turnId) params.turn = turnId;
    return params;
}

function applyFilters(page = 1, turnId: number | null = selectedId.value) {
    router.get(route("wiseAi.log"), filterParams(page, turnId), {
        preserveState: true,
        preserveScroll: true,
    });
}

function refresh() {
    refreshing.value = true;
    router.reload({
        onFinish: () => {
            refreshing.value = false;
            if (selectedId.value) void loadTurn(selectedId.value);
        },
    });
}

function clearFilters() {
    draft.q = "";
    draft.channel = "";
    draft.key_id = null;
    draft.gap = "all";
    draft.action = "";
    draft.source = "";
    draft.status = "";
    draft.conversation_id = "";
    draft.hours = 24;
    selectedId.value = null;
    detail.value = null;
    router.get(route("wiseAi.log"), { hours: 24 }, { preserveState: true, preserveScroll: true });
}

function clearThreadFilter() {
    draft.conversation_id = "";
    applyFilters(1);
}

function quickFilter(patch: Partial<typeof draft>) {
    Object.assign(draft, patch);
    applyFilters(1);
}

function filterThread(conversationId: string) {
    draft.conversation_id = conversationId;
    applyFilters(1);
}

async function selectTurn(turnId: number | null) {
    if (!turnId) return;
    selectedId.value = turnId;
    const url = new URL(window.location.href);
    url.searchParams.set("turn", String(turnId));
    window.history.replaceState({}, "", url.toString());
    await loadTurn(turnId);
}

async function loadTurn(turnId: number) {
    detailLoading.value = true;
    detailError.value = "";
    try {
        const { data } = await axios.get(route("wiseAi.log.turn", { turn: turnId }), {
            params: {
                channel: draft.channel || undefined,
                q: draft.q.trim() || undefined,
                key_id: draft.key_id || undefined,
                gap: draft.gap !== "all" ? draft.gap : undefined,
                action: draft.action || undefined,
                source: draft.source || undefined,
                status: draft.status || undefined,
                conversation_id: draft.conversation_id || undefined,
                hours: draft.hours,
            },
        });
        if (!data?.ok) throw new Error(data?.message || "Failed to load turn");
        detail.value = data as LogDetail;
        if (inspectorTab.value === "thread" && !(data.thread || []).length) {
            inspectorTab.value = "pair";
        }
    } catch (e: unknown) {
        detail.value = null;
        detailError.value = e instanceof Error ? e.message : "Failed to load turn";
    } finally {
        detailLoading.value = false;
    }
}

async function copyText(text: string, summary = "Copied") {
    try {
        await navigator.clipboard.writeText(text);
        toast.add({ severity: "success", summary, life: 1600 });
    } catch {
        toast.add({ severity: "error", summary: "Copy failed", life: 2200 });
    }
}

function copyPair() {
    if (!detail.value) return;
    void copyText(
        pretty({
            turn: detail.value.turn,
            request: detail.value.request,
            response: detail.value.response,
            trace: detail.value.trace,
            config_snapshot: detail.value.config_snapshot,
        }),
        "Pair copied",
    );
}

function downloadTurn() {
    if (!detail.value) return;
    const blob = new Blob(
        [
            pretty({
                turn: detail.value.turn,
                highlights: detail.value.highlights,
                request: detail.value.request,
                response: detail.value.response,
                trace: detail.value.trace,
                config_snapshot: detail.value.config_snapshot,
            }),
        ],
        { type: "application/json" },
    );
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "wise-turn-" + detail.value.turn.id + ".json";
    a.click();
    URL.revokeObjectURL(a.href);
}

function openReplay() {
    if (!detail.value) return;
    replayTurnId.value = detail.value.turn.id;
    replayOpen.value = true;
}

function onKeydown(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement | null)?.tagName;
    if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") {
        if (e.key === "Escape") (e.target as HTMLElement).blur();
        return;
    }
    if (e.key === "/") {
        e.preventDefault();
        searchInput.value?.focus();
        return;
    }
    if (e.key === "j" || e.key === "k") {
        e.preventDefault();
        const ids = props.turns.map((t) => t.id);
        if (!ids.length) return;
        const idx = selectedId.value ? ids.indexOf(selectedId.value) : -1;
        const next =
            e.key === "j"
                ? ids[Math.min(ids.length - 1, Math.max(0, idx + 1))]
                : ids[Math.max(0, idx <= 0 ? 0 : idx - 1)];
        void selectTurn(next);
        return;
    }
    if (e.key === "c" && detail.value) {
        e.preventDefault();
        copyPair();
        return;
    }
    if (e.key === "r" && detail.value) {
        e.preventDefault();
        openReplay();
    }
}

watch(
    () => props.filters.turn,
    (turnId) => {
        if (turnId && turnId !== selectedId.value) {
            selectedId.value = turnId;
            void loadTurn(turnId);
        }
    },
);

onMounted(() => {
    document.addEventListener("keydown", onKeydown);
    const initial = props.filters.turn || props.turns[0]?.id || null;
    if (initial) void selectTurn(initial);
});

onUnmounted(() => document.removeEventListener("keydown", onKeydown));
</script>
