<template>
    <div
        class="rounded-xl border border-gray-200 dark:border-gray-700"
        :id="endpoint.id"
    >
        <button
            type="button"
            class="flex w-full items-start gap-3 px-4 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
            @click="expanded = !expanded"
        >
            <span
                class="mt-0.5 shrink-0 rounded-md px-2 py-1 text-xs font-bold uppercase tracking-wide"
                :class="methodClass"
            >
                {{ endpoint.method }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        {{ endpoint.name }}
                    </h3>
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                        :class="authClass"
                    >
                        {{ authLabels[endpoint.auth] }}
                    </span>
                </div>
                <code
                    class="mt-1 block break-all text-sm text-primary-600 dark:text-primary-400"
                >
                    {{ fullPath }}
                </code>
                <p
                    v-if="!expanded"
                    class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ endpoint.description }}
                </p>
            </div>
            <Icon
                :name="expanded ? 'PhCaretUp' : 'PhCaretDown'"
                class="mt-1 shrink-0 text-gray-400"
            />
        </button>

        <div
            v-if="expanded"
            class="space-y-4 border-t border-gray-200 px-4 py-4 dark:border-gray-700"
        >
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ endpoint.description }}
            </p>

            <p
                v-if="endpoint.notes"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                {{ endpoint.notes }}
            </p>

            <div v-if="endpoint.params?.length">
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Body Parameters
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="professional-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="param in endpoint.params" :key="param.name">
                                <td><code>{{ param.name }}</code></td>
                                <td class="text-gray-500">{{ param.type }}</td>
                                <td>
                                    <StatusBadge
                                        :label="param.required ? 'Yes' : 'No'"
                                        :variant="param.required ? 'warning' : 'neutral'"
                                    />
                                </td>
                                <td class="text-gray-600 dark:text-gray-400">
                                    {{ param.description }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="endpoint.queryParams?.length">
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Query Parameters
                </h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="professional-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="param in endpoint.queryParams" :key="param.name">
                                <td><code>{{ param.name }}</code></td>
                                <td class="text-gray-500">{{ param.type }}</td>
                                <td>
                                    <StatusBadge
                                        :label="param.required ? 'Yes' : 'No'"
                                        :variant="param.required ? 'warning' : 'neutral'"
                                    />
                                </td>
                                <td class="text-gray-600 dark:text-gray-400">
                                    {{ param.description }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="curlExample">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        cURL Example
                    </h4>
                    <Button
                        label="Copy"
                        icon="pi pi-copy"
                        size="small"
                        severity="secondary"
                        text
                        @click="copy(curlExample)"
                    />
                </div>
                <pre
                    class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100"
                ><code>{{ curlExample }}</code></pre>
            </div>

            <div v-if="endpoint.requestExample">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Request Body
                    </h4>
                    <Button
                        label="Copy"
                        icon="pi pi-copy"
                        size="small"
                        severity="secondary"
                        text
                        @click="copy(endpoint.requestExample!)"
                    />
                </div>
                <pre
                    class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100"
                ><code>{{ endpoint.requestExample }}</code></pre>
            </div>

            <div v-if="endpoint.responseExample">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Response Example
                    </h4>
                    <Button
                        label="Copy"
                        icon="pi pi-copy"
                        size="small"
                        severity="secondary"
                        text
                        @click="copy(endpoint.responseExample!)"
                    />
                </div>
                <pre
                    class="overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs leading-relaxed text-emerald-100"
                ><code>{{ endpoint.responseExample }}</code></pre>
            </div>

            <ApiRequestPanel :endpoint="endpoint" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { Icon } from "@/plugins";
import { useToast } from "primevue/usetoast";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import ApiRequestPanel from "./ApiRequestPanel.vue";
import {
    authLabels,
    type ApiEndpoint,
    type AuthLevel,
} from "@/data/apiDocumentation";

const props = defineProps<{
    endpoint: ApiEndpoint;
    apiBaseUrl: string;
}>();

const toast = useToast();
const expanded = ref(false);

const fullPath = computed(() => `${props.apiBaseUrl}${props.endpoint.path}`);

const methodClass = computed(() => {
    const map: Record<string, string> = {
        GET: "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300",
        POST: "bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300",
        ANY: "bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300",
    };
    return map[props.endpoint.method] || map.ANY;
});

const authClass = computed(() => {
    const map: Record<AuthLevel, string> = {
        public: "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300",
        token: "bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300",
        full: "bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300",
    };
    return map[props.endpoint.auth];
});

const curlExample = computed(() => {
    if (props.endpoint.auth === "public" && props.endpoint.method === "GET") {
        return `curl -X GET "${fullPath.value}"`;
    }

    const headers = [
        '  -H "Accept: application/json"',
        '  -H "Content-Type: application/json"',
    ];

    if (props.endpoint.auth !== "public") {
        headers.push('  -H "Authorization: Bearer YOUR_API_TOKEN"');
        headers.push('  -H "Origin: https://your-store.com"');
    }

    const method =
        props.endpoint.method === "ANY" ? "POST" : props.endpoint.method;

    let cmd = `curl -X ${method} "${fullPath.value}" \\\n${headers.join(" \\\n")}`;

    if (
        props.endpoint.requestExample &&
        (method === "POST" || props.endpoint.method === "ANY")
    ) {
        const body = props.endpoint.requestExample.replace(/\n/g, "").replace(/"/g, '\\"');
        cmd += ` \\\n  -d "${body}"`;
    }

    return cmd;
});

const copy = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        toast.add({
            severity: "success",
            summary: "Copied",
            life: 2000,
        });
    });
};
</script>
