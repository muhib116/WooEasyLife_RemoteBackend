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
                            v-if="canManageMaintenance"
                            label="SEO & Learning"
                            icon="pi pi-chart-line"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="openSeoLearningDialog"
                        />
                        <Button
                            label="New Post"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.create'))"
                        />
                        <Button
                            v-if="canManageAi"
                            label="Smart One-Click"
                            icon="pi pi-bolt"
                            size="small"
                            @click="scrollToSmart"
                        />
                        <Button
                            label="AI Auto Create"
                            icon="pi pi-sparkles"
                            size="small"
                            severity="secondary"
                            outlined
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

            <div id="blog-ai-intelligence">
            <PageCard
                title="Blog AI intelligence"
                description="Live readiness of GSC, self-learning, analytics, and competitor analysis."
            >
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                    <div class="space-y-4">
                        <BlogIntelligenceRing :data="intelligence" />
                        <div
                            v-if="canManageAi"
                            class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10"
                        >
                            <SmartOneClickPanel
                                ref="smartOneClickRef"
                                @intelligence="onIntelligenceUpdate"
                                @updated="onCompetitorUpdated"
                            />
                        </div>
                        <div
                            v-if="canManageAi"
                            class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600"
                        >
                            <BlogMemoryPanel
                                :initial-items="learning?.memories || []"
                                :initial-stats="learning?.memory_stats || {}"
                                :clusters="clusterMap"
                                @intelligence="onIntelligenceUpdate"
                                @updated="onCompetitorUpdated"
                            />
                        </div>
                    </div>
                    <div
                        v-if="canManageAi"
                        class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600"
                    >
                        <CompetitorAnalyzerPanel
                            :initial-items="learning?.competitors || []"
                            :clusters="clusterMap"
                            @intelligence="onIntelligenceUpdate"
                            @updated="onCompetitorUpdated"
                        />
                    </div>
                    <p
                        v-else
                        class="text-sm text-slate-500 dark:text-slate-400"
                    >
                        Competitor analyzer needs billing.manage permission.
                        Open SEO &amp; Learning for GSC sync and learning insights.
                    </p>
                </div>
            </PageCard>
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
                    No insight snapshot yet. Traffic on /blog will build one
                    <template v-if="canManageMaintenance">
                        — or open
                        <button
                            type="button"
                            class="font-medium text-sky-600 underline dark:text-sky-400"
                            @click="openSeoLearningDialog"
                        >
                            SEO &amp; Learning
                        </button>
                        to run blog learning insights
                    </template>.
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
                v-if="showRankOpportunitiesCard"
                title="Google rank opportunities"
                description="Query×page gaps from Search Console — fix CTR, striking distance, and cannibalization."
            >
                <RankOpportunitiesPanel
                    :data="learning.rank_opportunities"
                    :can-draft="canManageAi"
                    :draft-busy="smartDraftBusy"
                    @draft-for-query="onDraftForQuery"
                />
                <div
                    v-if="canManageMaintenance"
                    class="mt-3"
                >
                    <Button
                        label="Open SEO & Learning"
                        icon="pi pi-chart-line"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="openSeoLearningDialog"
                    />
                </div>
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
                    <Column header="Facebook" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag
                                v-if="data.facebook_post_id"
                                value="Shared"
                                severity="info"
                                v-tooltip.top="formatDate(data.facebook_shared_at)"
                            />
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="Keyword" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ data.focus_keyword || '—' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="AI score" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <span
                                    v-if="data.ai_quality_score != null"
                                    class="inline-flex w-fit items-center rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                                    :class="aiScoreClass(data.ai_quality_score)"
                                >
                                    {{ data.ai_quality_score }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                                <Tag
                                    v-if="data.needs_seo_fix || data.seo_soft_pass"
                                    value="Needs SEO fix"
                                    severity="warn"
                                    class="!text-[10px]"
                                />
                            </div>
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
                    <Column header="Actions" headerStyle="width:12rem">
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
                                    v-if="facebookSharing.enabled && data.status === 'published'"
                                    icon="pi pi-facebook"
                                    :severity="data.facebook_post_id ? 'info' : 'help'"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="
                                        data.facebook_post_id
                                            ? 'Share again to Facebook Page'
                                            : 'Share to Facebook Page'
                                    "
                                    @click="openShareDialog(data)"
                                />
                                <Button
                                    v-if="data.facebook_permalink"
                                    icon="pi pi-eye"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    v-tooltip.top="'View Facebook post'"
                                    as="a"
                                    :href="data.facebook_permalink"
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

        <Dialog
            v-model:visible="seoLearningDialogVisible"
            modal
            header="Blog SEO & learning"
            :style="{ width: '48rem' }"
            :breakpoints="{ '640px': '95vw' }"
            @show="onSeoLearningDialogShow"
        >
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                SEO reports and AI learning jobs for the blog writer — same tools as System Maintenance.
            </p>
            <BlogSeoLearningPanel
                v-if="seoLearningDialogVisible"
                ref="seoLearningPanelRef"
                @updated="onSeoLearningUpdated"
            />
            <template #footer>
                <Button
                    label="Close"
                    severity="secondary"
                    text
                    @click="seoLearningDialogVisible = false"
                />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="shareDialogVisible"
            modal
            header="Share to Facebook Page"
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '95vw' }"
        >
            <div v-if="sharePost" class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Posts the cover/OG image to your Facebook Page (uploaded directly), with the blog URL in the caption.
                </p>
                <div
                    v-if="!facebookSharing.public_links"
                    class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    APP_URL is local, so Facebook won’t scrape a link preview. Image + caption still post.
                    For clickable production links, set
                    <code class="text-[11px]">FACEBOOK_SHARE_BASE_URL</code>
                    (e.g. https://wooeasylife.com).
                </div>
                <div v-if="sharePost.facebook_post_id" class="rounded-md bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-500/10 dark:text-sky-200">
                    Already shared
                    <span v-if="sharePost.facebook_shared_at">
                        ({{ formatDate(sharePost.facebook_shared_at) }})</span
                    >. Check “Post again” to publish another Page post.
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-200" for="fb-caption">
                        Caption
                    </label>
                    <Textarea
                        id="fb-caption"
                        v-model="shareMessage"
                        rows="6"
                        class="w-full"
                        autoResize
                        maxlength="2000"
                    />
                </div>
                <div
                    v-if="sharePost.facebook_post_id"
                    class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"
                >
                    <Checkbox v-model="shareForce" :binary="true" inputId="fb-force" />
                    <label for="fb-force">Post again (create another Page post)</label>
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    :disabled="shareSubmitting"
                    @click="shareDialogVisible = false"
                />
                <Button
                    label="Share to Page"
                    icon="pi pi-facebook"
                    :loading="shareSubmitting"
                    :disabled="shareSubmitting || (!!sharePost?.facebook_post_id && !shareForce)"
                    @click="submitShare"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import StatCard from '@/Pages/Users/fragments/StatCard.vue';
import BlogSeoLearningPanel from '@/components/blog/BlogSeoLearningPanel.vue';
import RankOpportunitiesPanel from '@/components/blog/RankOpportunitiesPanel.vue';
import BlogIntelligenceRing from '@/components/blog/BlogIntelligenceRing.vue';
import CompetitorAnalyzerPanel from '@/components/blog/CompetitorAnalyzerPanel.vue';
import SmartOneClickPanel from '@/components/blog/SmartOneClickPanel.vue';
import BlogMemoryPanel from '@/components/blog/BlogMemoryPanel.vue';
import { usePermissions } from '@/composables/usePermissions';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    learning: { type: Object, default: null },
    facebook_sharing: { type: Object, default: () => ({ enabled: false }) },
});

const { can } = usePermissions();
const canManageMaintenance = computed(() => can('roles.manage'));
const canManageAi = computed(() => can('billing.manage'));

const intelligence = ref(props.learning?.intelligence || null);

watch(
    () => props.learning?.intelligence,
    (next) => {
        if (next) intelligence.value = next;
    },
);

const clusterMap = computed(() => props.learning?.clusters || {});

const onIntelligenceUpdate = (next) => {
    if (next) intelligence.value = next;
};

const onCompetitorUpdated = () => {
    router.reload({ only: ['learning'], preserveScroll: true });
};

const scrollToSmart = () => {
    document.getElementById('blog-ai-intelligence')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const showRankOpportunitiesCard = computed(() => {
    const ops = props.learning?.rank_opportunities;
    if (!ops) return false;
    if (ops.configured === false) return true;
    return (ops.items || []).length > 0 || Object.keys(ops.summary || {}).length > 0;
});

const facebookSharing = computed(() => ({
    enabled: false,
    public_links: false,
    share_base_url: '',
    ...(props.facebook_sharing || {}),
}));

const publishedCount = computed(
    () => props.posts.filter((p) => p.status === 'published').length,
);
const draftCount = computed(
    () => props.posts.filter((p) => p.status === 'draft').length,
);
const enCount = computed(
    () => props.posts.filter((p) => p.locale === 'en').length,
);

const shareDialogVisible = ref(false);
const sharePost = ref(null);
const shareMessage = ref('');
const shareForce = ref(false);
const shareSubmitting = ref(false);

const seoLearningDialogVisible = ref(false);
const seoLearningPanelRef = ref(null);
const smartOneClickRef = ref(null);
const smartDraftBusy = ref(false);

const onDraftForQuery = async (item) => {
    smartDraftBusy.value = true;
    try {
        document.getElementById('blog-ai-intelligence')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        await smartOneClickRef.value?.startForOpportunity?.(item);
    } finally {
        smartDraftBusy.value = false;
    }
};

const openSeoLearningDialog = () => {
    seoLearningDialogVisible.value = true;
};

const onSeoLearningDialogShow = async () => {
    await nextTick();
    seoLearningPanelRef.value?.loadStatus?.();
};

const onSeoLearningUpdated = () => {
    router.reload({ only: ['learning'], preserveScroll: true });
};

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

const shareUrlFor = (post) => {
    const base = (facebookSharing.value.share_base_url || '').replace(/\/$/, '');
    if (!base || !post?.slug) return `/blog/${post?.slug || ''}`;
    return `${base}/blog/${post.slug}`;
};

const defaultCaption = (post) => {
    const title = (post?.title || '').trim();
    const excerpt = (post?.excerpt || '').trim();
    const lines = [title];
    if (excerpt) {
        lines.push(excerpt.length > 180 ? `${excerpt.slice(0, 180)}…` : excerpt);
    }
    lines.push('👉 বিস্তারিত পড়ুন 👇');
    lines.push(shareUrlFor(post));
    return lines.filter(Boolean).join('\n\n');
};

const openShareDialog = (post) => {
    sharePost.value = post;
    shareMessage.value = defaultCaption(post);
    shareForce.value = !post.facebook_post_id;
    shareDialogVisible.value = true;
};

const submitShare = () => {
    if (!sharePost.value) return;
    if (sharePost.value.facebook_post_id && !shareForce.value) return;

    shareSubmitting.value = true;
    router.post(
        route('blogPosts.shareFacebook', sharePost.value.id),
        {
            message: shareMessage.value,
            force: !!shareForce.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                shareSubmitting.value = false;
            },
            onSuccess: () => {
                shareDialogVisible.value = false;
            },
        },
    );
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
