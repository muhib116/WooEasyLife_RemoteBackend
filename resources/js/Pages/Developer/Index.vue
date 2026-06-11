<template>
    <AuthenticatedLayout title="API Documentation">
        <div class="space-y-5">
            <PageHeader
                title="Developer API"
                description="Reference for all WooEasyLife / Natural Care merchant APIs"
                icon="PhCode"
                icon-bg-class="bg-indigo-50 dark:bg-indigo-500/15"
                icon-class="text-indigo-600 dark:text-indigo-400"
            >
                <template #actions>
                    <IconField class="w-full sm:w-80">
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="search"
                            placeholder="Search endpoints..."
                            class="w-full"
                        />
                    </IconField>
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <StatCard
                    title="Total Endpoints"
                    :value="allEndpoints.length"
                    icon="PhBracketsCurly"
                    subtitle="Across all categories"
                />
                <StatCard
                    title="Categories"
                    :value="apiCategories.length"
                    icon="PhFolders"
                    accent-class="bg-violet-500"
                    icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                    icon-class="text-violet-600 dark:text-violet-400"
                />
                <StatCard
                    title="Base URL"
                    :value="apiBaseUrl.replace(/^https?:\/\//, '')"
                    icon="PhGlobe"
                    subtitle="API host"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Filtered"
                    :value="filteredCount"
                    icon="PhFunnel"
                    subtitle="Matching search & category"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
            </div>

            <PageCard
                title="Request Credentials"
                description="Used when sending test requests from endpoint panels below"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                            Bearer Token
                        </label>
                        <Password
                            v-model="token"
                            class="w-full"
                            input-class="w-full"
                            :feedback="false"
                            toggle-mask
                            placeholder="Paste API token from Api Keys"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                            Origin URL
                        </label>
                        <InputText
                            v-model="origin"
                            class="w-full"
                            placeholder="https://your-store.com"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Must match the domain registered on the API token.
                        </p>
                    </div>
                </div>
            </PageCard>

            <PageCard title="Authentication" description="Required headers for protected endpoints">
                <div class="grid gap-4 lg:grid-cols-3">
                    <div
                        class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <StatusBadge label="Public" variant="neutral" />
                            <span class="text-sm font-medium">No auth</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Plugin download and metadata endpoints. No headers required.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <StatusBadge label="Token" variant="warning" />
                            <span class="text-sm font-medium">Bearer + Origin</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <code class="text-xs">Authorization: Bearer {token}</code>
                            and
                            <code class="text-xs">Origin: https://your-domain.com</code>
                            must match the token's registered domain.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <StatusBadge label="Full" variant="danger" />
                            <span class="text-sm font-medium">Authenticated API</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Most endpoints require a valid, non-expired API token with matching domain and active user account.
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900/40">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Standard JSON response format
                        </span>
                        <Button
                            label="Copy"
                            icon="pi pi-copy"
                            size="small"
                            severity="secondary"
                            text
                            @click="copyResponseFormat"
                        />
                    </div>
                    <pre class="overflow-x-auto text-xs text-slate-700 dark:text-slate-300"><code>{{ responseFormat }}</code></pre>
                </div>
            </PageCard>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                    :class="
                        activeCategory === null
                            ? 'bg-primary-500 text-white'
                            : 'bg-slate-100 text-gray-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-gray-300'
                    "
                    @click="activeCategory = null"
                >
                    All ({{ allEndpoints.length }})
                </button>
                <button
                    v-for="category in apiCategories"
                    :key="category.id"
                    type="button"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                    :class="
                        activeCategory === category.id
                            ? 'bg-primary-500 text-white'
                            : 'bg-slate-100 text-gray-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-gray-300'
                    "
                    @click="activeCategory = category.id"
                >
                    {{ category.title }} ({{ category.endpoints.length }})
                </button>
            </div>

            <div class="space-y-6">
                <section
                    v-for="category in visibleCategories"
                    :key="category.id"
                    class="scroll-mt-24"
                    :id="category.id"
                >
                    <div class="mb-3 flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15"
                        >
                            <Icon
                                :name="category.icon"
                                class="text-xl text-primary-600 dark:text-primary-400"
                            />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ category.title }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ category.description }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <ApiEndpointCard
                            v-for="endpoint in filterEndpoints(category.endpoints)"
                            :key="endpoint.id"
                            :endpoint="endpoint"
                            :api-base-url="apiBaseUrl"
                        />
                    </div>

                    <EmptyState
                        v-if="!filterEndpoints(category.endpoints).length"
                        icon="PhMagnifyingGlass"
                        title="No matching endpoints"
                        description="Try a different search term or category"
                        class="mt-2"
                    />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, provide, ref } from "vue";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import { useToast } from "primevue/usetoast";
import { useApiPlayground } from "@/composables/useApiPlayground";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import ApiEndpointCard from "./fragments/ApiEndpointCard.vue";
import {
    allEndpoints,
    apiCategories,
    type ApiEndpoint,
} from "@/data/apiDocumentation";

defineOptions({
    name: "DeveloperApiDocs",
});

defineProps<{
    apiBaseUrl: string;
}>();

const toast = useToast();
const { token, origin } = useApiPlayground();

provide("apiPlayground", { token, origin });

const search = ref("");
const activeCategory = ref<string | null>(null);

const responseFormat = `{
  "status": true,
  "message": "Success",
  "data": { ... }
}

// Error response
{
  "status": false,
  "message": "Error description",
  "data": null,
  "errors": "validation details"
}`;

const normalizedSearch = computed(() => search.value.trim().toLowerCase());

const filterEndpoints = (endpoints: ApiEndpoint[]) => {
    if (!normalizedSearch.value) {
        return endpoints;
    }

    return endpoints.filter((endpoint) => {
        const haystack = [
            endpoint.name,
            endpoint.path,
            endpoint.description,
            endpoint.method,
            endpoint.notes || "",
            ...(endpoint.params?.map((p) => `${p.name} ${p.description}`) || []),
        ]
            .join(" ")
            .toLowerCase();

        return haystack.includes(normalizedSearch.value);
    });
};

const visibleCategories = computed(() => {
    if (activeCategory.value) {
        return apiCategories.filter((c) => c.id === activeCategory.value);
    }
    return apiCategories;
});

const filteredCount = computed(() => {
    return visibleCategories.value.reduce(
        (sum, cat) => sum + filterEndpoints(cat.endpoints).length,
        0,
    );
});

const copyResponseFormat = () => {
    navigator.clipboard.writeText(responseFormat).then(() => {
        toast.add({ severity: "success", summary: "Copied", life: 2000 });
    });
};
</script>
