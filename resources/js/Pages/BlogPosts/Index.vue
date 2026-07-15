<template>
    <AuthenticatedLayout title="Blog Posts">
        <div class="space-y-5">
            <PageHeader
                title="Blog Posts"
                description="Write SEO-friendly posts with the rich editor. Published posts appear on /blog alongside existing guides."
                icon="PhNewspaper"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="New Post"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.create'))"
                        />
                        <Button
                            label="AI Auto Create"
                            icon="pi pi-sparkles"
                            size="small"
                            @click="router.visit(route('blogPosts.create') + '?ai=1')"
                        />
                    </div>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard title="Total" :value="posts.length" icon="PhNewspaper" />
                <StatCard
                    title="Published"
                    :value="publishedCount"
                    icon="PhCheckCircle"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Drafts"
                    :value="draftCount"
                    icon="PhNote"
                    accent-class="bg-slate-500"
                    icon-bg-class="bg-slate-100 dark:bg-slate-500/15"
                    icon-class="text-slate-600 dark:text-slate-300"
                />
                <StatCard
                    title="English"
                    :value="enCount"
                    icon="PhGlobe"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
            </div>

            <PageCard
                v-if="learning?.insight || (learning?.top_posts || []).length"
                title="Content learning"
                description="Day-by-day engagement + GSC feed the AI writer so future drafts get more precise."
            >
                <p v-if="learning?.insight?.summary_bn" class="text-sm text-gray-700 dark:text-gray-200">
                    {{ learning.insight.summary_bn }}
                </p>
                <p v-else class="text-sm text-gray-500">
                    No insight snapshot yet. Traffic on /blog will build one — or run
                    <code class="text-xs">System Maintenance → Blog learning insights</code>
                    (or <code class="text-xs">php artisan blog:build-learning-insights</code>).
                </p>
                <p v-if="learning?.insight?.generated_at" class="mt-2 text-xs text-gray-500">
                    Last learning build: {{ formatDate(learning.insight.generated_at) }}
                    · events 28d: {{ learning.insight.events_analyzed ?? 0 }}
                </p>
                <ul
                    v-if="(learning?.insight?.payload?.recommended_clusters || []).length"
                    class="mt-3 flex flex-wrap gap-2 text-xs"
                >
                    <li
                        v-for="c in learning.insight.payload.recommended_clusters"
                        :key="c"
                        class="rounded-full bg-amber-500/15 px-2.5 py-1 font-medium text-amber-800 dark:text-amber-200"
                    >
                        {{ c }}
                    </li>
                </ul>
            </PageCard>

            <PageCard
                title="All posts"
                :description="`${posts.length} post${posts.length === 1 ? '' : 's'} in the CMS`"
                no-padding
            >
                <DataTable
                    v-if="posts.length"
                    :value="posts"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 25, 50]"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="title" header="Title" style="min-width: 16rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ data.title }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    /blog/{{ data.slug }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Locale" style="min-width: 5rem">
                        <template #body="{ data }">
                            <Tag :value="data.locale.toUpperCase()" severity="secondary" />
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.status"
                                :severity="data.status === 'published' ? 'success' : 'warn'"
                            />
                        </template>
                    </Column>
                    <Column header="Keyword" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ data.focus_keyword || '—' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="AI score" style="min-width: 7rem">
                        <template #body="{ data }">
                            <span
                                v-if="data.ai_quality_score != null"
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                                :class="aiScoreClass(data.ai_quality_score)"
                            >
                                {{ data.ai_quality_score }}
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="28d score" style="min-width: 8rem">
                        <template #body="{ data }">
                            <div class="text-xs text-gray-600 dark:text-gray-300">
                                <div>{{ data.analytics?.engagement_score ?? '—' }}</div>
                                <div class="text-[11px] text-gray-400">
                                    v {{ data.analytics?.views_28d ?? 0 }}
                                    · cta {{ data.analytics?.cta_clicks_28d ?? 0 }}
                                    · gsc {{ data.analytics?.gsc_clicks_28d ?? 0 }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column header="Published" style="min-width: 9rem">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ formatDate(data.published_at) }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Actions" headerStyle="width:10rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    icon="pi pi-pencil"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'Edit'"
                                    @click="router.visit(route('blogPosts.edit', data.id))"
                                />
                                <Button
                                    v-if="data.public_url || data.public_path"
                                    icon="pi pi-external-link"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'View post'"
                                    as="a"
                                    :href="data.public_url || data.public_path"
                                    target="_blank"
                                    rel="noopener"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'Delete'"
                                    @click="confirmDelete(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No CMS posts yet. Markdown guides on /blog still work — create a post here to publish new SEO content.
                    </p>
                    <Button
                        label="Write first post"
                        icon="pi pi-plus"
                        @click="router.visit(route('blogPosts.create'))"
                    />
                </div>
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
import StatCard from '@/Pages/Users/fragments/StatCard.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    learning: { type: Object, default: null },
});

const publishedCount = computed(
    () => props.posts.filter((p) => p.status === 'published').length,
);
const draftCount = computed(
    () => props.posts.filter((p) => p.status === 'draft').length,
);
const enCount = computed(
    () => props.posts.filter((p) => p.locale === 'en').length,
);

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const aiScoreClass = (score) => {
    const n = Number(score);
    if (n >= 80) return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (n >= 60) return 'bg-amber-500/15 text-amber-800 dark:text-amber-200';
    return 'bg-rose-500/15 text-rose-700 dark:text-rose-300';
};

const confirmDelete = (post) => {
    if (!window.confirm(`Delete “${post.title}”? This can be restored from soft-deletes only by a developer.`)) {
        return;
    }

    router.delete(route('blogPosts.destroy', post.id), {
        preserveScroll: true,
    });
};
</script>
