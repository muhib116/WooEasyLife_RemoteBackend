<template>
    <div
        class="rounded-xl border border-indigo-200 bg-indigo-50/50 dark:border-indigo-500/30 dark:bg-indigo-500/5"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-200/80 px-4 py-3 dark:border-indigo-500/20"
        >
            <div class="flex items-center gap-2">
                <Icon name="PhPaperPlaneTilt" class="text-lg text-indigo-600 dark:text-indigo-400" />
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    Try Request
                </span>
            </div>
            <Button
                label="Send Request"
                icon="pi pi-play"
                size="small"
                :loading="loading"
                :disabled="!canSend"
                @click="sendRequest"
            />
        </div>

        <div class="space-y-4 p-4">
            <div
                v-if="endpoint.auth !== 'public' && (!playground.token || !playground.origin)"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Set your API token and Origin URL in the credentials section above before sending.
            </div>

            <div
                v-if="endpoint.queryParams?.length"
                class="grid gap-3 sm:grid-cols-2"
            >
                <div
                    v-for="param in endpoint.queryParams"
                    :key="param.name"
                >
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ param.name }}
                        <span v-if="param.required" class="text-rose-500">*</span>
                    </label>
                    <InputText
                        v-model="queryValues[param.name]"
                        class="w-full"
                        :placeholder="param.description"
                    />
                </div>
            </div>

            <div v-if="showBodyEditor">
                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                    Request Body (JSON)
                </label>
                <Textarea
                    v-model="bodyText"
                    class="w-full font-mono text-sm"
                    rows="8"
                    :invalid="bodyInvalid"
                />
                <p
                    v-if="bodyInvalid"
                    class="mt-1 text-xs text-rose-600 dark:text-rose-400"
                >
                    Invalid JSON — fix the body before sending.
                </p>
            </div>

            <div v-if="response" class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge
                        :label="`${response.status_code || 'Error'}`"
                        :variant="statusVariant"
                    />
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ response.duration_ms }}ms
                    </span>
                    <span
                        v-if="response.content_type"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ response.content_type }}
                    </span>
                </div>

                <div v-if="response.is_binary" class="text-sm text-gray-600 dark:text-gray-400">
                    Binary response received ({{ formatBytes(response.body_base64) }}).
                </div>

                <pre
                    v-else
                    class="max-h-80 overflow-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100"
                ><code>{{ formattedResponse }}</code></pre>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, inject, reactive, ref, watch, type Ref } from "vue";
import axios from "axios";
import { Icon } from "@/plugins";
import { useToast } from "primevue/usetoast";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import type { ApiEndpoint } from "@/data/apiDocumentation";

interface PlaygroundCredentials {
    token: Ref<string>;
    origin: Ref<string>;
}

interface ProxyResponse {
    status_code: number;
    content_type?: string;
    body: unknown;
    body_raw?: string | null;
    body_base64?: string | null;
    is_json: boolean;
    is_binary: boolean;
    duration_ms: number;
}

const props = defineProps<{
    endpoint: ApiEndpoint;
}>();

const playground = inject<PlaygroundCredentials>("apiPlayground")!;
const toast = useToast();

const loading = ref(false);
const bodyText = ref(props.endpoint.requestExample || "");
const bodyInvalid = ref(false);
const response = ref<ProxyResponse | null>(null);
const queryValues = reactive<Record<string, string>>({});

const effectiveMethod = computed(() =>
    props.endpoint.method === "ANY" ? "POST" : props.endpoint.method,
);

const showBodyEditor = computed(
    () =>
        effectiveMethod.value === "POST" ||
        effectiveMethod.value === "PUT" ||
        effectiveMethod.value === "PATCH",
);

const canSend = computed(() => {
    if (showBodyEditor.value && bodyInvalid.value) {
        return false;
    }
    if (props.endpoint.auth !== "public") {
        return Boolean(playground.token.value && playground.origin.value);
    }
    return true;
});

const statusVariant = computed(() => {
    const code = response.value?.status_code ?? 0;
    if (code >= 200 && code < 300) return "success";
    if (code >= 400 && code < 500) return "warning";
    if (code >= 500 || code === 0) return "danger";
    return "info";
});

const formattedResponse = computed(() => {
    if (!response.value) return "";
    const body = response.value.body ?? response.value.body_raw;
    if (typeof body === "string") return body;
    try {
        return JSON.stringify(body, null, 2);
    } catch {
        return String(body);
    }
});

watch(bodyText, (val) => {
    if (!showBodyEditor.value || !val.trim()) {
        bodyInvalid.value = false;
        return;
    }
    try {
        JSON.parse(val);
        bodyInvalid.value = false;
    } catch {
        bodyInvalid.value = true;
    }
});

watch(
    () => props.endpoint.id,
    () => {
        bodyText.value = props.endpoint.requestExample || "";
        bodyInvalid.value = false;
        response.value = null;
        Object.keys(queryValues).forEach((k) => delete queryValues[k]);
    },
);

const formatBytes = (base64?: string | null) => {
    if (!base64) return "0 B";
    const bytes = Math.ceil((base64.length * 3) / 4);
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(1)} KB`;
};

const sendRequest = async () => {
    let body: unknown = null;

    if (showBodyEditor.value && bodyText.value.trim()) {
        try {
            body = JSON.parse(bodyText.value);
        } catch {
            bodyInvalid.value = true;
            return;
        }
    }

    const query: Record<string, string> = {};
    for (const [key, value] of Object.entries(queryValues)) {
        if (value?.trim()) {
            query[key] = value.trim();
        }
    }

    loading.value = true;
    response.value = null;

    try {
        const { data } = await axios.post(route("developer.proxy"), {
            method: effectiveMethod.value,
            path: props.endpoint.path,
            query: Object.keys(query).length ? query : undefined,
            body: body ?? undefined,
            token: playground.token.value || undefined,
            origin: playground.origin.value || undefined,
        });
        response.value = data;
    } catch (error: any) {
        const data = error?.response?.data;
        response.value = data ?? {
            status_code: error?.response?.status ?? 0,
            body: { error: error?.message || "Request failed" },
            is_json: true,
            is_binary: false,
            duration_ms: 0,
        };
        toast.add({
            severity: "error",
            summary: "Request failed",
            detail: error?.message,
            life: 4000,
        });
    } finally {
        loading.value = false;
    }
};
</script>
