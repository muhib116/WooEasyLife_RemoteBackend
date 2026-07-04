<template>
    <PageCard no-padding>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 dark:bg-violet-500/15"
                >
                    <Icon
                        name="PhMegaphone"
                        class="text-lg text-violet-600 dark:text-violet-400"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        {{ data?.title || "Customer Notices" }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ data?.summary?.live ?? 0 }} live · {{ data?.summary?.scheduled ?? 0 }} scheduled
                    </p>
                </div>
            </div>
        </template>

        <template #actions>
            <Link
                v-if="data?.link"
                :href="data.link"
                class="text-theme-sm inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 py-2 font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-slate-800 dark:text-gray-200 dark:hover:bg-slate-700"
            >
                {{ data?.link_text || "Manage" }}
                <Icon name="PhArrowRight" />
            </Link>
        </template>

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <div
                v-for="notice in liveNotices"
                :key="notice.id"
                class="flex items-start gap-3 px-5 py-4 md:px-6"
            >
                <StatusBadge
                    :label="notice.status"
                    :variant="statusVariant(notice.status)"
                    format="severity"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ notice.title }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ notice.audience_label }} · {{ notice.type_label }}
                    </p>
                </div>
            </div>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

type NoticeSummary = {
    total: number;
    live: number;
    scheduled: number;
    inactive: number;
};

type NoticeRow = {
    id: number;
    title: string;
    type: string;
    type_label: string;
    audience: string;
    audience_label: string;
    severity: string;
    status: string;
};

type PanelData = {
    title?: string;
    link?: string;
    link_text?: string;
    summary?: NoticeSummary;
    recent?: NoticeRow[];
};

const props = defineProps<{
    data?: PanelData;
}>();

const liveNotices = computed(() =>
    (props.data?.recent ?? []).filter((notice) => notice.status === "live" || notice.status === "scheduled"),
);

const statusVariant = (status: string) => {
    if (status === "live") {
        return "success";
    }

    if (status === "scheduled") {
        return "warning";
    }

    return "info";
};
</script>
