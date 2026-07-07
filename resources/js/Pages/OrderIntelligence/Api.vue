<template>
    <AuthenticatedLayout title="Intelligence API">
        <div class="space-y-5">
            <PageHeader
                title="API & Integration Guide"
                description="How plugins connect to Order Intelligence — endpoints, payloads, and lifecycle"
                icon="PhBookOpen"
                icon-bg-class="bg-indigo-50 dark:bg-indigo-500/15"
                icon-class="text-indigo-600 dark:text-indigo-400"
            >
                <template #actions>
                    <Link :href="route('developer.index')">
                        <Button
                            label="Full Developer API"
                            icon="pi pi-external-link"
                            size="small"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                </template>
            </PageHeader>

            <IntelSubNav />

            <PageCard title="Quick Start" description="Three steps to get full order lifecycle tracking">
                <div class="space-y-4">
                    <div class="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-500/30 dark:bg-violet-500/10">
                        <h3 class="font-semibold text-violet-900 dark:text-violet-100">Required plugin payload on fraud check</h3>
                        <p class="mt-1 text-sm text-violet-800 dark:text-violet-200">
                            Without <code class="text-xs">wc_order_id</code>, only courier snapshots are stored. Webhooks cannot update order status.
                        </p>
                        <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100"><code>{{ fraudCheckPayload }}</code></pre>
                        <Button
                            label="Copy payload"
                            icon="pi pi-copy"
                            size="small"
                            class="mt-3"
                            severity="secondary"
                            @click="copyText(fraudCheckPayload)"
                        />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <h3 class="mb-2 font-semibold">Authentication</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                All <code class="text-xs">/api/intel/*</code> endpoints require:
                            </p>
                            <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <li><code class="text-xs">Authorization: Bearer {token}</code></li>
                                <li><code class="text-xs">Origin: https://your-store.com</code></li>
                            </ul>
                            <p class="mt-2 text-xs text-gray-500">Base URL: {{ apiBaseUrl }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <h3 class="mb-2 font-semibold">Order lifecycle</h3>
                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <div>new_order → courier_entry → courier_handover</div>
                                <div>→ delivered | partially_delivered | returned | canceled</div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                Fraud check mode: <strong>{{ config.fraud_check_mode }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </PageCard>

            <PageCard title="Request Credentials" description="For testing endpoints below">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Bearer Token</label>
                        <Password
                            v-model="token"
                            class="w-full"
                            input-class="w-full"
                            :feedback="false"
                            toggle-mask
                            placeholder="Paste API token from merchant Api Keys"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Origin URL</label>
                        <InputText v-model="origin" class="w-full" placeholder="https://your-store.com" />
                    </div>
                </div>
            </PageCard>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="category in categories"
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
                <section v-for="category in visibleCategories" :key="category.id">
                    <div class="mb-3 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15">
                            <Icon :name="category.icon" class="text-xl text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ category.title }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ category.description }}</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <ApiEndpointCard
                            v-for="endpoint in category.endpoints"
                            :key="endpoint.id"
                            :endpoint="endpoint"
                            :api-base-url="apiBaseUrl"
                        />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, provide, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import { useToast } from "primevue/usetoast";
import { useApiPlayground } from "@/composables/useApiPlayground";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import ApiEndpointCard from "@/Pages/Developer/fragments/ApiEndpointCard.vue";
import IntelSubNav from "./fragments/IntelSubNav.vue";
import { orderIntelligenceCategories } from "@/data/apiDocumentation";

const props = defineProps<{
    apiBaseUrl: string;
    config: Record<string, unknown>;
}>();

const toast = useToast();
const { token, origin } = useApiPlayground();
provide("apiPlayground", { token, origin });

const activeCategory = ref<string | null>(null);
const categories = orderIntelligenceCategories;

const visibleCategories = computed(() =>
    activeCategory.value
        ? categories.filter((c) => c.id === activeCategory.value)
        : categories,
);

const fraudCheckPayload = computed(
    () => `POST ${props.apiBaseUrl}/api/fraud-check
{
  "phone": "01712345678",
  "wc_order_id": "12345",
  "name": "Customer Name",
  "address": "House 12, Road 5, Dhaka",
  "product": "Herbal Supplement 500g",
  "price": 1250
}`,
);

const copyText = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        toast.add({ severity: "success", summary: "Copied", life: 2000 });
    });
};
</script>
