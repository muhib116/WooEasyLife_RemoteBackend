<template>
    <MerchantPortalLayout title="Saved batches">
        <div class="space-y-5">
            <PageHeader
                title="Saved batches"
                description="Open a batch on its own page. Numbers already checked today are not sent to the couriers again."
                icon="PhPackage"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <PageCard title="Batches">
                <p v-if="batches.data.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                    No saved batches yet.
                    <Link :href="route('portal.fraud-check')" class="font-medium text-[#8d6a00] dark:text-[#ffd54f]">
                        Start a new batch
                    </Link>
                </p>
                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="saved in batches.data" :key="saved.id" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ saved.name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ saved.created_at }} · {{ saved.unique_count }} numbers · {{ saved.status }}
                            </p>
                        </div>
                        <Link :href="route('portal.fraud-check.batches.show', saved.id)">
                            <Button type="button" label="Open" size="small" severity="secondary" outlined as="span" />
                        </Link>
                    </li>
                </ul>

                <div v-if="batches.prev_page_url || batches.next_page_url" class="mt-4 flex items-center justify-between gap-3">
                    <Link
                        v-if="batches.prev_page_url"
                        :href="batches.prev_page_url"
                        class="text-sm font-medium text-[#8d6a00] dark:text-[#ffd54f]"
                    >
                        Previous
                    </Link>
                    <span v-else />
                    <Link
                        v-if="batches.next_page_url"
                        :href="batches.next_page_url"
                        class="text-sm font-medium text-[#8d6a00] dark:text-[#ffd54f]"
                    >
                        Next
                    </Link>
                </div>
            </PageCard>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { Link } from "@inertiajs/vue3";
import Button from "primevue/button";

defineProps({
    batches: {
        type: Object,
        default: () => ({ data: [], prev_page_url: null, next_page_url: null }),
    },
});
</script>
