<template>
    <AuthenticatedLayout title="Blog SEO & Learning">
        <div class="space-y-5">
            <PageHeader
                title="SEO & Learning"
                description="GSC sync, rank opportunities, and learning jobs for the blog writer."
                icon="PhChartLine"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="All posts"
                            icon="pi pi-list"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.index'))"
                        />
                        <Button
                            v-if="canManageAi"
                            label="Blog AI"
                            icon="pi pi-sparkles"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.ai'))"
                        />
                    </div>
                </template>
            </PageHeader>

            <PageCard title="Tools" description="GSC, learning jobs, and competitor tools (by permission).">
                <BlogSeoLearningPanel
                    ref="seoLearningPanelRef"
                    :initial-learning="learning"
                    @updated="onUpdated"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import BlogSeoLearningPanel from '@/components/blog/BlogSeoLearningPanel.vue';
import Button from 'primevue/button';
import { usePermissions } from '@/composables/usePermissions';

defineProps({
    learning: { type: Object, default: null },
});

const { can } = usePermissions();
const canManageAi = computed(() => can('billing.manage'));

const onUpdated = () => {
    router.reload({ only: ['learning'], preserveScroll: true });
};
</script>
