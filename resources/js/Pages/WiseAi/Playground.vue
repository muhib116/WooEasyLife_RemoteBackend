<template>
    <AuthenticatedLayout title="Wise AI — Playground">
        <div class="space-y-5">
            <PageHeader
                title="Playground"
                description="Talks to the real /api/wise/v1/decide endpoint — exactly like any external service would"
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
                subtitle="এখানে আসল পাবলিক API দিয়ে টেস্ট হয় — কোনো প্রাইভেট শর্টকাট নেই"
                badge="টেস্ট"
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
                    description="Send a message as a customer and watch how the brain responds"
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
                    title="Decision Trace"
                    description="The raw decision returned by the API for the last message"
                    class="xl:col-span-1"
                >
                    <div v-if="lastTrace" class="space-y-3">
                        <div
                            class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-slate-800/40"
                        >
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                Input
                            </p>
                            <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">
                                “{{ lastTrace.input }}”
                            </p>
                        </div>

                        <dl class="space-y-2.5 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Intent</dt>
                                <dd><StatusBadge :label="lastTrace.intent" variant="neutral" format="none" /></dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Confidence</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-100">
                                    {{ lastTrace.confidence }}%
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Action</dt>
                                <dd>
                                    <StatusBadge
                                        :label="lastTrace.action"
                                        :variant="lastTrace.action === 'needs_human' ? 'warning' : 'success'"
                                        format="none"
                                    />
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Source</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-100">
                                    {{ lastTrace.source }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Latency</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-100">
                                    {{ lastTrace.latency_ms }} ms
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Turn ID</dt>
                                <dd class="font-mono text-xs text-gray-600 dark:text-gray-300">
                                    #{{ lastTrace.turn_id }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Brain version</dt>
                                <dd class="font-mono text-xs text-gray-600 dark:text-gray-300">
                                    {{ lastTrace.brain_version }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">Gap</dt>
                                <dd>
                                    <StatusBadge
                                        :label="lastTrace.gap ? 'yes' : 'no'"
                                        :variant="lastTrace.gap ? 'danger' : 'success'"
                                        format="none"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                            <Button label="Approve" size="small" severity="success" outlined :loading="feedbackLoading" @click="sendFeedback('approved')" />
                            <Button label="Reject" size="small" severity="danger" outlined :loading="feedbackLoading" @click="sendFeedback('rejected')" />
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        icon="PhCircuitry"
                        title="No trace yet"
                        description="Send a test message — the full API decision will appear here"
                    />
                </PageCard>
            </div>
        </div>
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
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";

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
        title: "Approve বা Reject দিন",
        detail: "মানুষের ফিডব্যাক লগ হয়। ভুল উত্তর Reject করুন; পরে Knowledge ঠিক করে আবার টেস্ট করুন।",
    },
];

const howToTips = [
    "Action = needs_human / Gap = yes → Knowledge পেজে FAQ Publish করুন, তারপর আবার জিজ্ঞাসা করুন।",
    "Source = knowledge মানে published FAQ থেকে উত্তর; Source = pattern মানে সামাজিক টেমপ্লেট।",
    "ব্যবসায়িক উত্তর knowledge ছাড়া আসবে না — এটাই Trust First।",
];

type PlaygroundMessage = {
    id: number;
    role: "customer" | "brain";
    text: string;
    meta?: string;
};

type DecisionTrace = {
    input: string;
    intent: string;
    confidence: number;
    action: string;
    source: string;
    latency_ms: number;
    turn_id: number;
    brain_version: string;
    gap: boolean;
};

const STORAGE_KEY = "wise_ai_playground_key";

const toast = useToast();

const apiKey = ref(localStorage.getItem(STORAGE_KEY) || "");
const keyDraft = ref("");
const messages = ref<PlaygroundMessage[]>([]);
const draft = ref("");
const sending = ref(false);
const feedbackLoading = ref(false);
const lastTrace = ref<DecisionTrace | null>(null);
const threadEl = ref<HTMLElement | null>(null);
const conversationId = `playground-${Date.now()}`;
let nextId = 1;

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
        const { data } = await axios.post(
            "/api/wise/v1/decide",
            {
                text,
                channel: "playground",
                conversation_id: conversationId,
            },
            {
                headers: { Authorization: `Bearer ${apiKey.value}` },
            },
        );

        const decision = data.decision;

        lastTrace.value = {
            input: text,
            intent: decision.intent,
            confidence: decision.confidence,
            action: decision.action,
            source: decision.source,
            latency_ms: data.latency_ms,
            turn_id: data.turn_id,
            brain_version: decision.brain_version,
            gap: Boolean(data.gap ?? decision.gap),
        };

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
    feedbackLoading.value = true;
    try {
        await axios.post(
            "/api/wise/v1/feedback",
            {
                turn_id: lastTrace.value.turn_id,
                outcome,
                reason_code: outcome === "rejected" ? "playground_reject" : "playground_approve",
            },
            { headers: { Authorization: `Bearer ${apiKey.value}` } },
        );
        toast.add({
            severity: "success",
            summary: outcome === "approved" ? "Feedback: approved" : "Feedback: rejected",
            life: 2500,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Feedback failed", life: 3500, group: "br" });
    } finally {
        feedbackLoading.value = false;
    }
};

const resetConversation = () => {
    messages.value = [];
    lastTrace.value = null;
    draft.value = "";
};
</script>
