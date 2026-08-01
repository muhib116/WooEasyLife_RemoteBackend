<template>
    <AuthenticatedLayout title="Wise AI — Config">
        <div class="space-y-5">
            <PageHeader
                title="Config"
                description="API keys and the payload contract — everything a service needs to use Wise AI"
                icon="PhSlidersHorizontal"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            />

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Config কীভাবে ব্যবহার করবেন"
                subtitle="প্রথমে এখানে key তৈরি করুন — এরপর Knowledge → Playground"
                badge="ধাপ ১"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard
                title="API Keys"
                description="যেকোনো সার্ভিস (Playground, WEL, ওয়েবসাইট চ্যাট) শুধু এই key দিয়ে Wise AI ব্যবহার করে"
            >
                <template #actions>
                    <form class="flex items-center gap-2" @submit.prevent="createKey">
                        <input
                            v-model="newKeyName"
                            type="text"
                            placeholder="Key name (e.g. WEL Plugin)"
                            class="h-9 w-52 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-800 outline-none transition focus:border-fuchsia-400 focus:ring-2 focus:ring-fuchsia-100 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-100 dark:focus:ring-fuchsia-500/20"
                        />
                        <Button
                            type="submit"
                            label="Generate Key"
                            icon="pi pi-plus"
                            size="small"
                            :loading="creating"
                            :disabled="!newKeyName.trim()"
                        />
                    </form>
                </template>

                <div
                    v-if="freshKey"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                >
                    <p class="flex items-center gap-1.5 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                        <Icon name="PhKey" class="text-base" />
                        নতুন key তৈরি হয়েছে — এখনই Copy করুন, পরে আর দেখাবে না
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <code
                            class="min-w-0 flex-1 truncate rounded-lg bg-white px-3 py-2 font-mono text-sm text-gray-800 shadow-sm dark:bg-slate-900 dark:text-gray-100"
                        >{{ freshKey }}</code>
                        <Button
                            :label="copied ? 'Copied' : 'Copy'"
                            :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="copyFreshKey"
                        />
                    </div>
                </div>

                <DataTable :value="keys" size="small" striped-rows class="professional-table">
                    <Column field="name" header="Name" />
                    <Column header="Key">
                        <template #body="{ data }">
                            <code class="font-mono text-xs text-gray-600 dark:text-gray-300">
                                {{ data.key_prefix }}…
                            </code>
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.status"
                                :variant="data.status === 'active' ? 'success' : 'danger'"
                                format="none"
                            />
                        </template>
                    </Column>
                    <Column field="turns_count" header="Turns" />
                    <Column header="Last used">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-500">{{ data.last_used_at || "never" }}</span>
                        </template>
                    </Column>
                    <Column header="">
                        <template #body="{ data }">
                            <Button
                                v-if="data.status === 'active'"
                                label="Revoke"
                                icon="pi pi-ban"
                                size="small"
                                severity="danger"
                                text
                                @click="revokeKey(data)"
                            />
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-if="!keys.length"
                    icon="PhKey"
                    title="এখনো কোনো API key নেই"
                    description="উপরে নাম দিয়ে Generate Key চাপুন — Playground Connect করতে এই key লাগবে"
                />
            </PageCard>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <PageCard
                    title="Endpoint"
                    description="One endpoint, one payload — same for every consumer"
                >
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                POST
                            </span>
                            <code class="min-w-0 flex-1 truncate rounded-lg bg-gray-100 px-3 py-1.5 font-mono text-sm text-gray-800 dark:bg-slate-800 dark:text-gray-100">
                                {{ baseUrl }}/api/wise/v1/decide
                            </code>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Auth header: <code class="font-mono">Authorization: Bearer &lt;api key&gt;</code>
                        </p>
                        <pre class="overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-relaxed text-slate-200"><code>curl -X POST {{ baseUrl }}/api/wise/v1/decide \
  -H "Authorization: Bearer wise_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "text": "দাম কত?",
    "channel": "website_bubble",
    "conversation_id": "visitor-123"
  }'</code></pre>
                    </div>
                </PageCard>

                <PageCard
                    title="Payload Contract"
                    description="Request fields and the decision every call returns"
                >
                    <div class="space-y-3">
                        <div>
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                Request
                            </p>
                            <dl class="space-y-1.5 text-sm">
                                <div v-for="field in requestFields" :key="field.name" class="flex gap-3">
                                    <dt class="w-36 shrink-0 font-mono text-xs text-fuchsia-600 dark:text-fuchsia-400">
                                        {{ field.name }}
                                    </dt>
                                    <dd class="min-w-0 text-xs text-gray-600 dark:text-gray-300">
                                        {{ field.desc }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                Response
                            </p>
                            <pre class="overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-relaxed text-slate-200"><code>{
  "ok": true,
  "turn_id": 42,
  "latency_ms": 3,
  "decision": {
    "intent": "price",
    "confidence": 80,
    "action": "suggest_reply",
    "suggested_reply": "…",
    "source": "pattern",
                            "brain_version": "0.2.1"
                          }
                        }</code></pre>
                        </div>
                    </div>
                </PageCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref } from "vue";
import axios from "axios";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import { useConfirm } from "primevue";
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
        title: "Key-এর নাম দিন (যেমন Playground বা WEL Plugin)",
        detail: "Generate Key চাপুন। সবুজ বক্সে পুরো key (wise_…) একবারই দেখাবে — Copy করে নিরাপদে রাখুন।",
    },
    {
        title: "Playground-এ সেই key Paste → Connect",
        detail: "Config শুধু key দেয়; কথোপকথন টেস্ট হয় Playground-এ।",
    },
    {
        title: "Knowledge সেই key-এর অধীনে Publish করুন",
        detail: "প্রতিটি knowledge item একটি API key (tenant) এর সাথে বাঁধা। ভুল key বেছে নিলে Playground উত্তর পাবে না।",
    },
    {
        title: "নিচের Endpoint / Payload দেখে ইন্টিগ্রেট করুন",
        detail: "বাইরের অ্যাপ একই POST /api/wise/v1/decide + Bearer header ব্যবহার করবে।",
    },
];

const howToTips = [
    "Revoke = key তৎক্ষণাৎ মারা যায়; সব ক্লায়েন্ট থেমে যাবে।",
    "Plain key DB-তে থাকে না (hash) — হারালে নতুন key তৈরি করতে হবে।",
    "একাধিক প্রোডাক্ট হলে আলাদা key রাখুন যাতে turns / knowledge আলাদা থাকে।",
];

type ApiKeyRow = {
    id: number;
    name: string;
    key_prefix: string;
    status: string;
    turns_count: number;
    last_used_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    apiKeys: ApiKeyRow[];
}>();

const toast = useToast();
const confirm = useConfirm();

const keys = ref<ApiKeyRow[]>([...props.apiKeys]);
const newKeyName = ref("");
const creating = ref(false);
const freshKey = ref("");
const copied = ref(false);
const baseUrl = window.location.origin;

const requestFields = [
    { name: "text", desc: "Required. The customer message to decide on." },
    { name: "channel", desc: "Optional. Where it came from — messenger, website_bubble, instagram…" },
    { name: "conversation_id", desc: "Optional. Your thread/visitor id, echoed in logs for tracing." },
    { name: "context", desc: "Optional object. Extra context (recent messages, customer, products)." },
];

const createKey = async () => {
    if (!newKeyName.value.trim() || creating.value) return;
    creating.value = true;

    try {
        const { data } = await axios.post(route("wiseAi.keys.store"), {
            name: newKeyName.value.trim(),
        });
        keys.value.unshift(data.key);
        freshKey.value = data.plain_key;
        copied.value = false;
        newKeyName.value = "";
    } catch {
        toast.add({
            severity: "error",
            summary: "Could not create key",
            detail: "Please try again.",
            life: 3500,
            group: "br",
        });
    } finally {
        creating.value = false;
    }
};

const copyFreshKey = async () => {
    try {
        await navigator.clipboard.writeText(freshKey.value);
        copied.value = true;
    } catch {
        // Clipboard may be blocked; key stays visible for manual copy.
    }
};

const revokeKey = (key: ApiKeyRow) => {
    confirm.require({
        header: "Revoke this key?",
        message: `“${key.name}” will stop working immediately for every service using it.`,
        icon: "pi pi-ban",
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true, size: "small" },
        acceptProps: { label: "Revoke", severity: "danger", size: "small" },
        accept: async () => {
            try {
                await axios.post(route("wiseAi.keys.revoke", { key: key.id }));
                key.status = "revoked";
                toast.add({
                    severity: "success",
                    summary: "Key revoked",
                    life: 2500,
                    group: "br",
                });
            } catch {
                toast.add({
                    severity: "error",
                    summary: "Could not revoke key",
                    life: 3500,
                    group: "br",
                });
            }
        },
    });
};
</script>
