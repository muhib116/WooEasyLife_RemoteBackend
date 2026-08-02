<template>
    <AuthenticatedLayout title="Wise AI — Config">
        <div class="space-y-5">
            <PageHeader
                title="Config"
                description="প্রথমে API key তৈরি করুন — তারপর Knowledge / Playground / WEL-এ ব্যবহার করুন"
                icon="PhSlidersHorizontal"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            />

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Config কীভাবে ব্যবহার করবেন"
                subtitle="প্রথমে এখানে key তৈরি করুন — এরপর Knowledge → Playground"
                badge="ধাপ ১"
                storage-key="config"
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
                    <Column header="Mode">
                        <template #body="{ data }">
                            <StatusBadge :label="data.mode || 'assist'" variant="neutral" format="none" />
                            <span v-if="data.sandbox" class="ml-1 text-[10px] text-amber-600">sandbox</span>
                        </template>
                    </Column>
                    <Column header="Policy">
                        <template #body="{ data }">
                            <span class="font-mono text-[10px] text-gray-500">{{ data.policy_version }}</span>
                        </template>
                    </Column>
                    <Column header="Last used">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-500">{{ data.last_used_at || "never" }}</span>
                        </template>
                    </Column>
                    <Column header="">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Button
                                    v-if="data.status === 'active'"
                                    label="Policy"
                                    icon="pi pi-shield"
                                    size="small"
                                    severity="secondary"
                                    text
                                    @click="openGov(data)"
                                />
                                <Button
                                    v-if="data.status === 'active'"
                                    label="Revoke"
                                    icon="pi pi-ban"
                                    size="small"
                                    severity="danger"
                                    text
                                    @click="revokeKey(data)"
                                />
                            </div>
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

            <PageCard
                title="LLM Language (optional wording)"
                description="Optional — Judge still owns meaning. Fail-open when no key. Never invents prices."
            >
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input v-model="llmForm.enabled" type="checkbox" class="rounded border-gray-300" />
                        Enable LLM Language Specialist
                    </label>
                    <label class="block text-xs font-medium text-gray-500">
                        Model
                        <Select
                            v-model="llmForm.model"
                            :options="llmModelOptions"
                            class="mt-1 w-full"
                        />
                    </label>
                    <label class="block text-xs font-medium text-gray-500">
                        OpenAI API key (Wise-only)
                        <input
                            v-model="llmForm.api_key"
                            type="password"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-slate-900"
                            :placeholder="llm.api_key_set ? llm.api_key_hint || 'Key saved — enter to replace' : 'sk-… or leave empty for WISE_OPENAI_API_KEY'"
                        />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="Save LLM settings"
                            size="small"
                            :loading="savingLlm"
                            @click="saveLlm"
                        />
                        <Button
                            v-if="llm.api_key_set"
                            label="Clear key"
                            size="small"
                            severity="secondary"
                            outlined
                            :loading="savingLlm"
                            @click="clearLlmKey"
                        />
                    </div>
                </div>
            </PageCard>

            <PageCard
                title="AI Constitution (platform)"
                description="Versions sealed on every turn — for Replay honesty"
            >
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-gray-500">Constitution</dt>
                        <dd class="font-mono text-xs">{{ governance.constitution_version }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Policy pack</dt>
                        <dd class="font-mono text-xs">{{ governance.policy_pack_version }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">
                    Principles: {{ governance.principles.join(" · ") }}
                </p>
            </PageCard>

            <Dialog
                v-model:visible="showGov"
                header="Merchant policy overlay"
                modal
                :style="{ width: '28rem' }"
                dismissable-mask
            >
                <div v-if="govForm" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Mode</label>
                        <Select
                            v-model="govForm.mode"
                            :options="modeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                        <p class="mt-1 text-[11px] text-gray-400">
                            Auto requires allow_auto — otherwise coerced to Assist (constitution).
                        </p>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="govForm.allow_auto" type="checkbox" />
                        Allow Auto mode (opt-in)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="govForm.sandbox" type="checkbox" />
                        Sandbox key (exclude from Merchant BI / revenue)
                    </label>
                </div>
                <template #footer>
                    <Button label="Cancel" severity="secondary" outlined size="small" @click="showGov = false" />
                    <Button label="Save policy" size="small" :loading="savingGov" @click="saveGov" />
                </template>
            </Dialog>

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
                            "brain_version": "0.3.3"
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
import { reactive, ref } from "vue";
import axios from "axios";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import Select from "primevue/select";
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
    "Policy = merchant overlay (mode/sandbox) — প্রতি turn-এ version seal হয়।",
    "Sandbox key-এর turns Merchant BI / Founder revenue-এ মিশবে না।",
];

type ApiKeyRow = {
    id: number;
    name: string;
    key_prefix: string;
    status: string;
    turns_count: number;
    last_used_at: string | null;
    created_at: string | null;
    mode?: string;
    allow_auto?: boolean;
    sandbox?: boolean;
    policy_version?: string;
    feature_flags?: Record<string, boolean>;
};

const props = defineProps<{
    apiKeys: ApiKeyRow[];
    governance: {
        constitution_version: string;
        policy_pack_version: string;
        principles: string[];
        default_mode: string;
        allowed_modes: string[];
    };
    llm: {
        enabled: boolean;
        model: string;
        api_key_set: boolean;
        api_key_hint: string;
        allowed_models: string[];
    };
}>();

const llm = reactive({ ...props.llm });
const llmForm = reactive({
    enabled: props.llm.enabled,
    model: props.llm.model,
    api_key: "",
});
const llmModelOptions = props.llm.allowed_models || ["gpt-4o-mini"];
const savingLlm = ref(false);

const saveLlm = async () => {
    if (savingLlm.value) return;
    savingLlm.value = true;
    try {
        const payload: Record<string, unknown> = {
            enabled: llmForm.enabled,
            model: llmForm.model,
        };
        if (llmForm.api_key.trim()) {
            payload.api_key = llmForm.api_key.trim();
        }
        const { data } = await axios.post(route("wiseAi.config.llm"), payload);
        if (data?.llm) {
            Object.assign(llm, data.llm);
            llmForm.enabled = data.llm.enabled;
            llmForm.model = data.llm.model;
            llmForm.api_key = "";
        }
        toast.add({ severity: "success", summary: "LLM settings saved", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Could not save LLM settings", life: 3500, group: "br" });
    } finally {
        savingLlm.value = false;
    }
};

const clearLlmKey = async () => {
    if (savingLlm.value) return;
    savingLlm.value = true;
    try {
        const { data } = await axios.post(route("wiseAi.config.llm"), {
            enabled: llmForm.enabled,
            model: llmForm.model,
            api_key: "__clear__",
        });
        if (data?.llm) {
            Object.assign(llm, data.llm);
            llmForm.api_key = "";
        }
        toast.add({ severity: "success", summary: "LLM key cleared", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Could not clear key", life: 3500, group: "br" });
    } finally {
        savingLlm.value = false;
    }
};

const modeOptions = [
    { label: "Shadow", value: "shadow" },
    { label: "Assist", value: "assist" },
    { label: "Auto (gated)", value: "auto" },
];

const toast = useToast();
const confirm = useConfirm();

const keys = ref<ApiKeyRow[]>([...props.apiKeys]);
const newKeyName = ref("");
const creating = ref(false);
const freshKey = ref("");
const showGov = ref(false);
const savingGov = ref(false);
const govKeyId = ref<number | null>(null);
const govForm = reactive({
    mode: "assist",
    allow_auto: false,
    sandbox: false,
});

const openGov = (row: ApiKeyRow) => {
    govKeyId.value = row.id;
    govForm.mode = row.mode || "assist";
    govForm.allow_auto = Boolean(row.allow_auto);
    govForm.sandbox = Boolean(row.sandbox);
    showGov.value = true;
};

const saveGov = async () => {
    if (!govKeyId.value || savingGov.value) return;
    savingGov.value = true;
    try {
        const { data } = await axios.post(route("wiseAi.keys.governance", { key: govKeyId.value }), {
            mode: govForm.mode,
            allow_auto: govForm.allow_auto,
            sandbox: govForm.sandbox,
        });
        const idx = keys.value.findIndex((k) => k.id === govKeyId.value);
        if (idx >= 0) keys.value[idx] = data.key;
        showGov.value = false;
        toast.add({ severity: "success", summary: "Policy saved", detail: data.key.policy_version, life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Could not save policy", life: 3500, group: "br" });
    } finally {
        savingGov.value = false;
    }
};
const copied = ref(false);
const baseUrl = window.location.origin;

const requestFields = [
    { name: "text", desc: "Required. The customer message to decide on." },
    { name: "channel", desc: "Optional. Where it came from — messenger, website_bubble, instagram…" },
    { name: "conversation_id", desc: "Recommended. Thread/visitor id — Memory + product subject carry." },
    { name: "context.product_id", desc: "Catalog offer id (physical/digital/service…) → external_id. Aliases: external_id, offer_id." },
    { name: "context.offer_kind", desc: "Optional: physical | digital | service | subscription | other." },
    { name: "context.platform", desc: "Optional store engine: woocommerce | shopify | custom | …" },
    { name: "context.product_sku", desc: "Optional SKU → meta.sku." },
    { name: "context", desc: "Optional. Brain is not bound to one platform or one offer kind." },
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
