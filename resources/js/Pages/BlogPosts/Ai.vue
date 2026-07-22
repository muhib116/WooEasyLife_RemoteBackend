<template>
    <AuthenticatedLayout title="Blog AI">
        <div class="space-y-5">
            <PageHeader
                title="Blog AI intelligence"
                description="Smart Post, standing memory, and competitor gaps — separate from the post list."
                icon="PhSparkle"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
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
                            label="Topic Clusters"
                            icon="pi pi-sitemap"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.clusters.index'))"
                        />
                        <Button
                            label="AI Settings"
                            icon="pi pi-cog"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.settings'))"
                        />
                        <Button
                            label="AI Auto Create"
                            icon="pi pi-bolt"
                            size="small"
                            @click="router.visit(route('blogPosts.create') + '?ai=1')"
                        />
                    </div>
                </template>
            </PageHeader>

            <PageCard
                title="System intelligence"
                description="Live readiness of GSC, self-learning, analytics, and competitor analysis."
            >
                <div
                    v-if="gscFocusBanner"
                    class="mb-4 rounded-xl border border-emerald-200/80 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
                >
                    <p class="font-semibold">Smart Post → Search Console first</p>
                    <p class="mt-1 text-xs opacity-90">
                        {{ gscFocusBanner }}
                    </p>
                </div>
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                    <div class="space-y-4">
                        <BlogIntelligenceRing :data="intelligence" />
                        <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                            <SmartOneClickPanel
                                ref="smartOneClickRef"
                                @intelligence="onIntelligenceUpdate"
                                @updated="reloadLearning"
                            />
                        </div>
                        <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
                            <BlogMemoryPanel
                                :initial-items="learning?.memories || []"
                                :initial-stats="learning?.memory_stats || {}"
                                :clusters="clusterMap"
                                @intelligence="onIntelligenceUpdate"
                                @updated="reloadLearning"
                            />
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600">
                        <CompetitorAnalyzerPanel
                            :initial-items="learning?.competitors || []"
                            :clusters="clusterMap"
                            :discovery-enabled="competitorsDiscoveryEnabled"
                            @intelligence="onIntelligenceUpdate"
                            @updated="reloadLearning"
                        />
                    </div>
                </div>
            </PageCard>

            <PageCard
                v-if="learning?.insight || (learning?.top_posts || []).length"
                title="Content learning"
                description="Engagement + GSC feed the AI writer so future drafts get more precise."
            >
                <p v-if="learning?.insight?.summary_bn" class="text-sm text-gray-700 dark:text-gray-200">
                    {{ learning.insight.summary_bn }}
                </p>
                <p v-else class="text-sm text-gray-500">
                    No insight snapshot yet.
                    <button
                        type="button"
                        class="font-medium text-sky-600 underline dark:text-sky-400"
                        @click="router.visit(route('blogPosts.seo'))"
                    >
                        Open SEO &amp; Learning
                    </button>
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
                description="Free real demand — striking distance & CTR gaps Smart Post will prefer."
            >
                <RankOpportunitiesPanel
                    :data="learning.rank_opportunities"
                    :can-draft="true"
                    :draft-busy="smartDraftBusy"
                    @draft-for-query="onDraftForQuery"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import RankOpportunitiesPanel from '@/components/blog/RankOpportunitiesPanel.vue';
import BlogIntelligenceRing from '@/components/blog/BlogIntelligenceRing.vue';
import CompetitorAnalyzerPanel from '@/components/blog/CompetitorAnalyzerPanel.vue';
import SmartOneClickPanel from '@/components/blog/SmartOneClickPanel.vue';
import BlogMemoryPanel from '@/components/blog/BlogMemoryPanel.vue';
import Button from 'primevue/button';

const props = defineProps({
    learning: { type: Object, default: null },
});

const intelligence = ref(props.learning?.intelligence || null);
const smartOneClickRef = ref(null);
const smartDraftBusy = ref(false);

watch(
    () => props.learning?.intelligence,
    (next) => {
        if (next) intelligence.value = next;
    },
);

const clusterMap = computed(() => props.learning?.clusters || {});
const competitorsDiscoveryEnabled = computed(
    () => props.learning?.competitors_discovery_enabled !== false,
);

const showRankOpportunitiesCard = computed(() => {
    const ops = props.learning?.rank_opportunities;
    if (!ops) return false;
    if (ops.configured === false) return true;
    return (ops.items || []).length > 0 || Object.keys(ops.summary || {}).length > 0;
});

const gscFocusBanner = computed(() => {
    const focus = props.learning?.gsc_focus;
    if (!focus?.prefer_gsc) {
        return null;
    }
    const count = Number(focus.count || 0);
    if (count > 0) {
        return `${count} GSC opportunities ready — Smart Post will pick from these instead of cluster seed guesses.`;
    }
    return 'Connect Search Console + run Blog learning insights so Smart Post can use real query demand (free).';
});

const onIntelligenceUpdate = (next) => {
    if (next) intelligence.value = next;
};

const reloadLearning = () => {
    router.reload({ only: ['learning'], preserveScroll: true });
};

const onDraftForQuery = async (payload) => {
    smartDraftBusy.value = true;
    try {
        await nextTick();
        await smartOneClickRef.value?.startForOpportunity?.(payload);
    } finally {
        smartDraftBusy.value = false;
    }
};
</script>
