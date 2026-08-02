<template>
    <AuthenticatedLayout title="Wise AI — Playground">
        <div class="space-y-5">
            <PageHeader
                title="Playground"
                description="আসল /decide API দিয়ে টেস্ট — বাইরের অ্যাপ যেমন কল করে, একইভাবে"
                icon="PhFlask"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            >
                <template #actions>
                    <Button
                        label="Reset"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :disabled="!messages.length"
                        @click="resetConversation"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Playground কীভাবে ব্যবহার করবেন"
                subtitle="Key connect → মেসেজ পাঠান → Decision দেখুন"
                badge="টেস্ট"
                storage-key="playground"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard v-if="!apiKey" title="Connect with an API key">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <p class="mb-1.5 text-sm text-gray-600 dark:text-gray-300">
                            Config থেকে তৈরি করা Wise AI API key এখানে পেস্ট করুন — বাইরের অ্যাপ যেমন করে auth করে, একইভাবে।
                            Key নেই?
                            <Link :href="route('wiseAi.config')" class="font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400">
                                Config → Generate Key
                            </Link>.
                        </p>
                        <input
                            v-model="keyDraft"
                            type="password"
                            placeholder="wise_…"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-4 font-mono text-sm text-gray-800 outline-none transition focus:border-fuchsia-400 focus:ring-2 focus:ring-fuchsia-100 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-100 dark:focus:ring-fuchsia-500/20"
                            @keyup.enter="saveKey"
                        />
                    </div>
                    <Button
                        label="Connect"
                        icon="pi pi-link"
                        :disabled="!keyDraft.trim()"
                        @click="saveKey"
                    />
                </div>
            </PageCard>

            <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <PageCard
                    title="Test Conversation"
                    description="কাস্টমারের মতো মেসেজ পাঠান — brain কী সাজেস্ট করে দেখুন"
                    class="xl:col-span-2"
                >
                    <template #actions>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {{ keyPreview }}
                        </span>
                        <Button
                            icon="pi pi-sign-out"
                            size="small"
                            severity="secondary"
                            text
                            aria-label="Disconnect key"
                            title="Disconnect key"
                            @click="disconnectKey"
                        />
                    </template>

                    <div class="flex h-[480px] flex-col">
                        <div
                            ref="threadEl"
                            class="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-xl border border-gray-100 bg-gray-50/60 p-4 dark:border-gray-800 dark:bg-slate-800/40"
                        >
                            <div
                                v-if="!messages.length"
                                class="flex h-full flex-col items-center justify-center gap-2 text-center"
                            >
                                <span
                                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-fuchsia-50 text-fuchsia-500 dark:bg-fuchsia-500/15 dark:text-fuchsia-400"
                                >
                                    <Icon name="PhChatCircleDots" class="text-2xl" />
                                </span>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    টেস্ট কথোপকথন শুরু করুন
                                </p>
                                <p class="max-w-sm text-xs text-gray-500 dark:text-gray-400">
                                    প্রথমে “hi” বা “ধন্যবাদ” (সামাজিক) — তারপর “ডেলিভারি চার্জ কত?” (প্রকাশিত knowledge থাকলে উত্তর)।
                                    Knowledge না থাকলে Action = needs_human দেখাবে।
                                </p>
                            </div>

                            <template v-for="msg in messages" :key="msg.id">
                                <div
                                    class="flex"
                                    :class="msg.role === 'customer' ? 'justify-end' : 'justify-start'"
                                >
                                    <div
                                        class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm"
                                        :class="
                                            msg.role === 'customer'
                                                ? 'rounded-br-md bg-fuchsia-600 text-white'
                                                : 'rounded-bl-md border border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200'
                                        "
                                    >
                                        <p
                                            v-if="msg.role === 'brain'"
                                            class="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-fuchsia-500 dark:text-fuchsia-400"
                                        >
                                            <Icon name="PhBrain" class="text-xs" />
                                            Wise AI
                                            <span v-if="msg.meta" class="normal-case tracking-normal text-gray-400">
                                                · {{ msg.meta }}
                                            </span>
                                        </p>
                                        {{ msg.text }}
                                    </div>
                                </div>
                            </template>

                            <div v-if="sending" class="flex justify-start">
                                <div class="rounded-2xl rounded-bl-md border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-slate-900">
                                    <span class="flex gap-1">
                                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-fuchsia-400" />
                                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-fuchsia-400 [animation-delay:120ms]" />
                                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-fuchsia-400 [animation-delay:240ms]" />
                                    </span>
                                </div>
                            </div>
                        </div>

                        <form class="mt-3 flex items-center gap-2" @submit.prevent="sendMessage">
                            <input
                                v-model="draft"
                                type="text"
                                placeholder="Type a customer message…"
                                class="h-11 min-w-0 flex-1 rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-800 outline-none transition focus:border-fuchsia-400 focus:ring-2 focus:ring-fuchsia-100 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-100 dark:focus:ring-fuchsia-500/20"
                                :disabled="sending"
                            />
                            <Button
                                type="submit"
                                icon="pi pi-send"
                                :disabled="!draft.trim() || sending"
                                aria-label="Send"
                            />
                        </form>
                    </div>
                </PageCard>

                <PageCard
                    title="Advanced test context"
                    description="Product page simulation — খালি রাখলে bare price → clarify"
                    class="xl:col-span-3 order-last xl:order-none"
                >
                    <button
                        type="button"
                        class="mb-2 text-xs font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                        @click="showAdvancedContext = !showAdvancedContext"
                    >
                        {{ showAdvancedContext ? "Hide context fields" : "Show product / offer context" }}
                    </button>
                    <div v-if="showAdvancedContext" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">product_id</label>
                            <input
                                v-model="ctxProductId"
                                type="text"
                                class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 font-mono text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                placeholder="45 or svc-1"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">offer_kind</label>
                            <Select
                                v-model="ctxOfferKind"
                                :options="offerKindOptions"
                                option-label="label"
                                option-value="value"
                                show-clear
                                placeholder="optional"
                                class="w-full"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">platform</label>
                            <input
                                v-model="ctxPlatform"
                                type="text"
                                class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                placeholder="woocommerce"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">product_name</label>
                            <input
                                v-model="ctxProductName"
                                type="text"
                                class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                placeholder="optional"
                            />
                        </div>
                    </div>
                </PageCard>

                <PageCard
                    title="Playground suite"
                    description="Decision · Language · Explain · Memory · Knowledge — all via public API"
                    class="xl:col-span-1"
                >
                    <div class="mb-3 flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-50/80 p-1 dark:border-gray-700 dark:bg-slate-800/50">
                        <button
                            v-for="tab in suiteTabs"
                            :key="tab.id"
                            type="button"
                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition-colors"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-white text-fuchsia-700 shadow-sm dark:bg-slate-900 dark:text-fuchsia-300'
                                    : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'
                            "
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div v-if="!lastTrace && activeTab !== 'knowledge'" class="py-6">
                        <EmptyState
                            icon="PhCircuitry"
                            title="No turn yet"
                            description="Send a chat message — tabs fill from the public decide/explain APIs"
                        />
                    </div>

                    <div v-else-if="activeTab === 'decision' && lastTrace" class="space-y-3">
                        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-slate-800/40">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Input</p>
                            <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">“{{ lastTrace.input }}”</p>
                        </div>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Intent</dt>
                                <dd><StatusBadge :label="lastTrace.intent" variant="neutral" format="none" /></dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Confidence</dt>
                                <dd class="font-medium">{{ lastTrace.confidence }}%</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Action</dt>
                                <dd>
                                    <StatusBadge :label="lastTrace.action" :variant="actionVariant(lastTrace.action)" format="none" />
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Source</dt>
                                <dd class="font-medium">{{ lastTrace.source }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Latency</dt>
                                <dd class="font-medium">{{ lastTrace.latency_ms }} ms</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Turn / Brain</dt>
                                <dd class="font-mono text-xs">#{{ lastTrace.turn_id }} · {{ lastTrace.brain_version }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Gap</dt>
                                <dd>
                                    <StatusBadge :label="lastTrace.gap ? 'yes' : 'no'" :variant="lastTrace.gap ? 'danger' : 'success'" format="none" />
                                </dd>
                            </div>
                            <div v-if="lastTrace.missing_context" class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Missing</dt>
                                <dd class="font-medium text-amber-700 dark:text-amber-300">{{ lastTrace.missing_context }}</dd>
                            </div>
                            <div v-if="lastEvidence" class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-2.5 text-[11px] dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                <p class="font-semibold text-emerald-700 dark:text-emerald-300">Evidence</p>
                                <p class="mt-0.5 text-gray-600 dark:text-gray-300">
                                    {{ lastEvidence.title || 'knowledge' }}
                                    <span v-if="lastEvidence.knowledge_scope"> · {{ lastEvidence.knowledge_scope }}</span>
                                    <span v-if="lastEvidence.match_score != null"> · score {{ lastEvidence.match_score }}</span>
                                </p>
                            </div>
                            <div
                                v-if="lastTrace.psych"
                                class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-2.5 text-[11px] dark:border-indigo-500/20 dark:bg-indigo-500/10"
                            >
                                <p class="font-semibold text-indigo-700 dark:text-indigo-300">Assist side-channel</p>
                                <p class="mt-0.5 text-gray-600 dark:text-gray-300">
                                    {{ lastTrace.psych.emotion }} · {{ lastTrace.psych.journey }} ·
                                    priority {{ lastTrace.psych.priority }} · {{ lastTrace.psych.style_hint }}
                                </p>
                                <ul v-if="lastTrace.opportunities?.length" class="mt-1 list-inside list-disc text-indigo-700 dark:text-indigo-300">
                                    <li v-for="op in lastTrace.opportunities" :key="op.id">{{ op.title }}</li>
                                </ul>
                                <p class="mt-1 text-[10px] text-gray-400">Never changes sealed facts or Auto-send</p>
                            </div>
                        </dl>
                        <div class="space-y-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                            <label class="mb-1 block text-[11px] text-gray-500">Reject reason</label>
                            <Select v-model="rejectReason" :options="rejectReasonOptions" option-label="label" option-value="value" class="w-full" />
                            <div class="flex flex-wrap gap-2">
                                <Button label="Approve" size="small" severity="success" outlined :loading="feedbackLoading" @click="sendFeedback('approved')" />
                                <Button label="Reject" size="small" severity="danger" outlined :loading="feedbackLoading" @click="sendFeedback('rejected')" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'language' && lastTrace" class="space-y-3 text-sm">
                        <div class="rounded-xl border border-fuchsia-100 bg-fuchsia-50/50 p-3 dark:border-fuchsia-500/20 dark:bg-fuchsia-500/10">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-fuchsia-500">Canonical</p>
                            <p class="mt-1 font-medium">“{{ lastTrace.canonical || lastTrace.input }}”</p>
                            <p v-if="lastTrace.dict_version" class="mt-1 font-mono text-[10px] text-gray-400">{{ lastTrace.dict_version }}</p>
                        </div>
                        <p v-if="lastTrace.language_rules" class="text-xs text-gray-600 dark:text-gray-300">
                            Rules: {{ lastTrace.language_rules }}
                        </p>
                        <div v-if="lastLanguage?.unknown_tokens?.length" class="text-xs">
                            <p class="font-semibold text-amber-700 dark:text-amber-300">Unknown tokens</p>
                            <p class="mt-1 font-mono text-gray-600">{{ lastLanguage.unknown_tokens.join(', ') }}</p>
                        </div>
                        <div v-if="lastLanguage?.ambiguous?.length" class="text-xs">
                            <p class="font-semibold text-gray-500">Ambiguous (left untouched)</p>
                            <p class="mt-1 font-mono">{{ lastLanguage.ambiguous.join(', ') }}</p>
                        </div>
                        <p v-if="!lastTrace.language_rules && !lastLanguage?.unknown_tokens?.length" class="text-xs text-gray-400">
                            No language transforms on this turn.
                        </p>
                    </div>

                    <div v-else-if="activeTab === 'explain' && lastTrace" class="space-y-3">
                        <template v-if="explain">
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge label="replay-safe" variant="success" format="none" />
                                <Button
                                    v-if="lastTrace.turn_id"
                                    label="Open Replay"
                                    size="small"
                                    text
                                    severity="help"
                                    @click="openReplay(lastTrace.turn_id)"
                                />
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-300">{{ explain.summary }}</p>
                            <ol class="space-y-2">
                                <li
                                    v-for="(step, idx) in explain.timeline"
                                    :key="step.step"
                                    class="rounded-lg border border-gray-100 px-2.5 py-2 dark:border-gray-800"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-[10px] text-gray-400">{{ idx + 1 }}</span>
                                        <span class="text-xs font-semibold">{{ step.title }}</span>
                                        <StatusBadge :label="step.status" variant="neutral" format="none" class="ml-auto" />
                                    </div>
                                    <p class="mt-1 text-[11px] text-gray-500">{{ step.detail }}</p>
                                </li>
                            </ol>
                            <div
                                v-if="explainCorpusPacks.length || explain.answers?.why_corpus"
                                class="rounded-lg border border-fuchsia-100 bg-fuchsia-50/50 p-2.5 dark:border-fuchsia-900/40 dark:bg-fuchsia-950/20"
                            >
                                <p class="text-[11px] font-semibold text-fuchsia-700 dark:text-fuchsia-300">Sealed language corpus</p>
                                <ul v-if="explainCorpusPacks.length" class="mt-1 space-y-0.5 font-mono text-[10px] text-gray-600 dark:text-gray-300">
                                    <li v-for="(pack, i) in explainCorpusPacks" :key="i">
                                        {{ pack.slug }}@{{ pack.version }} · {{ String(pack.artifact_hash || "").slice(0, 8) }}
                                    </li>
                                </ul>
                                <p v-if="explain.answers?.why_corpus" class="mt-1 text-[11px] text-gray-500">{{ explain.answers.why_corpus }}</p>
                            </div>
                        </template>
                        <EmptyState v-else icon="PhPath" title="Explain unavailable" description="GET /turns/{id}/explain failed for this turn" />
                    </div>

                    <div v-else-if="activeTab === 'memory'" class="space-y-3 text-sm">
                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-500">conversation_id</dt>
                                <dd class="max-w-[60%] truncate font-mono" :title="conversationId">{{ conversationId }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-500">Thread messages</dt>
                                <dd class="font-medium">{{ messages.length }}</dd>
                            </div>
                            <div v-if="lastTrace" class="flex justify-between gap-2">
                                <dt class="text-gray-500">Last memory_used</dt>
                                <dd>
                                    <StatusBadge
                                        :label="lastTrace.memory_used ? 'yes' : 'no'"
                                        :variant="lastTrace.memory_used ? 'success' : 'neutral'"
                                        format="none"
                                    />
                                </dd>
                            </div>
                            <div v-if="lastTrace?.product_subject" class="flex justify-between gap-2">
                                <dt class="text-gray-500">Active offer</dt>
                                <dd class="truncate font-medium" :title="lastTrace.product_subject">{{ lastTrace.product_subject }}</dd>
                            </div>
                        </dl>
                        <p class="text-[11px] text-gray-400">
                            Memory is sealed on the turn (same conversation_id). Follow-ups reuse prior business intent — never invents facts.
                        </p>
                        <ol class="max-h-56 space-y-1.5 overflow-y-auto text-[11px]">
                            <li v-for="msg in messages" :key="'mem-'+msg.id" class="rounded-lg border border-gray-100 px-2 py-1.5 dark:border-gray-800">
                                <span class="font-semibold uppercase text-gray-400">{{ msg.role }}</span>
                                — {{ msg.text }}
                            </li>
                        </ol>
                    </div>

                    <div v-else-if="activeTab === 'knowledge'" class="space-y-3">
                        <p class="text-[11px] text-gray-500">
                            Sandbox probe — calls the same public decide API (separate conversation so chat memory stays clean).
                        </p>
                        <textarea
                            v-model="probeText"
                            rows="2"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="e.g. delivery charge koto?"
                        />
                        <Button
                            label="Probe knowledge"
                            icon="pi pi-search"
                            size="small"
                            class="w-full"
                            :loading="probeLoading"
                            :disabled="!probeText.trim()"
                            @click="runKnowledgeProbe"
                        />
                        <div v-if="probeResult" class="space-y-2 rounded-xl border border-gray-100 p-3 text-xs dark:border-gray-800">
                            <div class="flex flex-wrap gap-2">
                                <StatusBadge :label="probeResult.intent" variant="neutral" format="none" />
                                <StatusBadge :label="probeResult.action" :variant="actionVariant(probeResult.action)" format="none" />
                                <StatusBadge :label="probeResult.source" variant="info" format="none" />
                            </div>
                            <p v-if="probeResult.reply" class="text-sm text-gray-800 dark:text-gray-100">{{ probeResult.reply }}</p>
                            <p v-if="probeResult.evidence_title" class="text-gray-500">
                                Hit: {{ probeResult.evidence_title }}
                                <span v-if="probeResult.score != null"> · score {{ probeResult.score }}</span>
                            </p>
                            <p v-if="probeResult.gap" class="font-medium text-rose-600">Knowledge gap</p>
                        </div>
                    </div>
                </PageCard>
            </div>
        </div>

        <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import Button from "primevue/button";
import { useToast } from "primevue/usetoast";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import Select from "primevue/select";
import TurnReplayDialog from "./fragments/TurnReplayDialog.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";

const offerKindOptions = [
    { label: "physical", value: "physical" },
    { label: "digital", value: "digital" },
    { label: "service", value: "service" },
    { label: "subscription", value: "subscription" },
    { label: "other", value: "other" },
];

const rejectReasonOptions = [
    { value: "wrong_fact", label: "Wrong fact / answer" },
    { value: "wrong_offer", label: "Wrong offer / product" },
    { value: "missing_knowledge", label: "Missing knowledge" },
    { value: "outdated", label: "Outdated knowledge" },
    { value: "tone", label: "Tone / voice wrong" },
    { value: "language", label: "Language / wording wrong" },
    { value: "policy", label: "Policy / safety" },
    { value: "other", label: "Other" },
];

const howToSteps = [
    {
        title: "API key Connect করুন",
        detail: "Config-এ Generate Key → Copy → এখানে Paste → Connect। Key ব্রাউজার localStorage-এ থাকে।",
    },
    {
        title: "কাস্টমারের মতো মেসেজ লিখুন",
        detail: "উদাহরণ: hi · দাম কত? · delivery charge koto? · অর্ডার কই?",
    },
    {
        title: "ডান পাশে Decision Trace পড়ুন",
        detail: "Intent, Confidence, Action, Source, Gap — brain কেন এমন সিদ্ধান্ত নিল তা এখানে।",
    },
    {
        title: "Approve বা Reject (reason সহ) দিন",
        detail: "Reject-এ Learning taxonomy reason বাছুন — silent reject নেই।",
    },
];

const howToTips = [
    "শুধু ‘price koto?’ → clarify: নাম বা ছবি চায়।",
    "উপরের context.product_id দিয়ে S4/S6 টেস্ট: id আছে + knowledge → দাম বা gap (ভুল FAQ নয়)।",
    "Offer knowledge Publish করে একই external_id দিন।",
];

type PlaygroundMessage = {
    id: number;
    role: "customer" | "brain";
    text: string;
    meta?: string;
};

type DecisionTrace = {
    input: string;
    canonical: string | null;
    dict_version: string | null;
    language_rules: string | null;
    intent: string;
    confidence: number;
    action: string;
    source: string;
    latency_ms: number;
    turn_id: number;
    brain_version: string;
    gap: boolean;
    memory_used: boolean;
    missing_context: string | null;
    product_subject: string | null;
    psych?: {
        emotion?: string;
        journey?: string;
        priority?: string;
        style_hint?: string;
    } | null;
    opportunities?: { id: string; title: string }[];
};

type ExplainPayload = {
    summary: string;
    timeline: Array<{ step: string; title: string; detail: string; status: string }>;
    answers: Record<string, string>;
    sealed?: {
        language_corpus_snapshot?: {
            packs?: { slug?: string; version?: string; artifact_hash?: string }[];
        };
    };
};

type LanguageSnap = {
    unknown_tokens?: string[];
    ambiguous?: string[];
    dict_version?: string | null;
};

type EvidenceSnap = {
    title?: string;
    knowledge_scope?: string;
    match_score?: number;
    knowledge_id?: number;
};

const STORAGE_KEY = "wise_ai_playground_key";

const toast = useToast();

const apiKey = ref(localStorage.getItem(STORAGE_KEY) || "");
const keyDraft = ref("");
const messages = ref<PlaygroundMessage[]>([]);
const draft = ref("");
const sending = ref(false);
const feedbackLoading = ref(false);
const rejectReason = ref("wrong_fact");
const lastTrace = ref<DecisionTrace | null>(null);
const lastLanguage = ref<LanguageSnap | null>(null);
const lastEvidence = ref<EvidenceSnap | null>(null);
const explain = ref<ExplainPayload | null>(null);
const explainCorpusPacks = computed(
    () => explain.value?.sealed?.language_corpus_snapshot?.packs ?? [],
);
const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);
const openReplay = (turnId: number) => {
    replayTurnId.value = turnId;
    replayOpen.value = true;
};
const threadEl = ref<HTMLElement | null>(null);
const conversationId = `playground-${Date.now()}`;
const showAdvancedContext = ref(false);
const ctxProductId = ref("");
const ctxOfferKind = ref<string | null>(null);
const ctxPlatform = ref("");
const ctxProductName = ref("");
const activeTab = ref<"decision" | "language" | "explain" | "memory" | "knowledge">("decision");
const probeText = ref("");
const probeLoading = ref(false);
const probeResult = ref<{
    intent: string;
    action: string;
    source: string;
    reply: string | null;
    evidence_title: string | null;
    score: number | null;
    gap: boolean;
} | null>(null);
let nextId = 1;

const suiteTabs = [
    { id: "decision" as const, label: "Decision" },
    { id: "language" as const, label: "Language" },
    { id: "explain" as const, label: "Explain" },
    { id: "memory" as const, label: "Memory" },
    { id: "knowledge" as const, label: "Knowledge" },
];

const buildContext = (): Record<string, string> => {
    const context: Record<string, string> = {};
    if (ctxProductId.value.trim()) context.product_id = ctxProductId.value.trim();
    if (ctxOfferKind.value) context.offer_kind = ctxOfferKind.value;
    if (ctxPlatform.value.trim()) context.platform = ctxPlatform.value.trim();
    if (ctxProductName.value.trim()) context.product_name = ctxProductName.value.trim();
    return context;
};

const keyPreview = computed(() => `${apiKey.value.slice(0, 13)}…`);

const saveKey = () => {
    const key = keyDraft.value.trim();
    if (!key) return;
    apiKey.value = key;
    localStorage.setItem(STORAGE_KEY, key);
    keyDraft.value = "";
};

const disconnectKey = () => {
    apiKey.value = "";
    localStorage.removeItem(STORAGE_KEY);
    resetConversation();
};

const scrollToBottom = async () => {
    await nextTick();
    threadEl.value?.scrollTo({ top: threadEl.value.scrollHeight, behavior: "smooth" });
};

const sendMessage = async () => {
    const text = draft.value.trim();
    if (!text || sending.value) return;

    messages.value.push({ id: nextId++, role: "customer", text });
    draft.value = "";
    sending.value = true;
    await scrollToBottom();

    try {
        const context = buildContext();

        const { data } = await axios.post(
            "/api/wise/v1/decide",
            {
                text,
                channel: "playground",
                conversation_id: conversationId,
                ...(Object.keys(context).length ? { context } : {}),
            },
            {
                headers: { Authorization: `Bearer ${apiKey.value}` },
            },
        );

        const decision = data.decision;

        const lang = decision.language || {};
        const rules = Array.isArray(lang.rules_applied)
            ? lang.rules_applied.map((r: { from: string; to: string }) => `${r.from}→${r.to}`).join(" · ")
            : null;

        lastLanguage.value = {
            unknown_tokens: lang.unknown_tokens || [],
            ambiguous: lang.ambiguous || [],
            dict_version: lang.dict_version || null,
        };
        lastEvidence.value = data.evidence || decision.evidence || null;

        lastTrace.value = {
            input: text,
            canonical: lang.canonical || null,
            dict_version: lang.dict_version || null,
            language_rules: rules,
            intent: decision.intent,
            confidence: decision.confidence,
            action: decision.action,
            source: decision.source,
            latency_ms: data.latency_ms,
            turn_id: data.turn_id,
            brain_version: decision.brain_version,
            gap: Boolean(data.gap ?? decision.gap),
            memory_used: Boolean(decision.memory_used),
            missing_context: decision.missing_context || null,
            product_subject: decision.product_subject?.title || null,
            psych: decision.psych || null,
            opportunities: Array.isArray(decision.opportunities?.items)
                ? decision.opportunities.items
                : [],
        };
        activeTab.value = "decision";

        try {
            const { data: expl } = await axios.get(`/api/wise/v1/turns/${data.turn_id}/explain`, {
                headers: { Authorization: `Bearer ${apiKey.value}` },
            });
            explain.value = expl.explain || null;
        } catch {
            explain.value = null;
        }

        const replyText = decision.suggested_reply
            || (decision.gap
                ? `Knowledge gap — intent “${decision.intent}”. Publish knowledge, then try again.`
                : `No suggested reply — intent “${decision.intent}” → ${decision.action}.`);

        messages.value.push({
            id: nextId++,
            role: "brain",
            text: replyText,
            meta: `${decision.intent} · ${decision.confidence}% · ${decision.source}`,
        });
    } catch (error: unknown) {
        const status = axios.isAxiosError(error) ? error.response?.status : null;

        if (status === 401) {
            toast.add({
                severity: "error",
                summary: "Invalid API key",
                detail: "The key was rejected — it may be revoked. Reconnect with a valid key.",
                life: 4500,
                group: "br",
            });
            disconnectKey();
        } else {
            messages.value.push({
                id: nextId++,
                role: "brain",
                text: "Request failed — check that the hub API is reachable and try again.",
            });
        }
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const sendFeedback = async (outcome: "approved" | "rejected") => {
    if (!lastTrace.value || !apiKey.value || feedbackLoading.value) return;
    if (outcome === "rejected" && !rejectReason.value) {
        toast.add({ severity: "warn", summary: "Pick a reject reason", life: 2500, group: "br" });
        return;
    }
    feedbackLoading.value = true;
    try {
        await axios.post(
            "/api/wise/v1/feedback",
            {
                turn_id: lastTrace.value.turn_id,
                outcome,
                reason_code: outcome === "rejected" ? rejectReason.value : "playground_approve",
            },
            { headers: { Authorization: `Bearer ${apiKey.value}` } },
        );
        toast.add({
            severity: "success",
            summary: outcome === "approved" ? "Feedback: approved" : `Rejected · ${rejectReason.value}`,
            life: 2500,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Feedback failed", life: 3500, group: "br" });
    } finally {
        feedbackLoading.value = false;
    }
};

const runKnowledgeProbe = async () => {
    const text = probeText.value.trim();
    if (!text || !apiKey.value || probeLoading.value) return;
    probeLoading.value = true;
    probeResult.value = null;
    try {
        const context = buildContext();
        const { data } = await axios.post(
            "/api/wise/v1/decide",
            {
                text,
                channel: "playground_probe",
                conversation_id: `probe-${Date.now()}`,
                ...(Object.keys(context).length ? { context } : {}),
            },
            { headers: { Authorization: `Bearer ${apiKey.value}` } },
        );
        const decision = data.decision || {};
        const evidence = data.evidence || {};
        probeResult.value = {
            intent: decision.intent || "unknown",
            action: decision.action || "—",
            source: decision.source || "—",
            reply: decision.suggested_reply || null,
            evidence_title: evidence.title || null,
            score: evidence.match_score ?? null,
            gap: Boolean(data.gap ?? decision.gap),
        };
    } catch {
        toast.add({ severity: "error", summary: "Probe failed", life: 3000, group: "br" });
    } finally {
        probeLoading.value = false;
    }
};

const resetConversation = () => {
    messages.value = [];
    lastTrace.value = null;
    lastLanguage.value = null;
    lastEvidence.value = null;
    explain.value = null;
    probeResult.value = null;
    draft.value = "";
    activeTab.value = "decision";
};

const actionVariant = (action: string) => {
    if (action === "needs_human") return "warning";
    if (action === "clarify") return "info";
    return "success";
};
</script>
