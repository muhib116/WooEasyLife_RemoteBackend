<template>
    <PageCard no-padding>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-500/15"
                >
                    <Icon
                        name="PhClockCountdown"
                        class="text-lg text-rose-600 dark:text-rose-400"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        {{ data?.title || "Expired API Tokens" }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Tokens past their expiry date for merchant accounts
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
                {{ data?.link_text || "Manage API Keys" }}
                <Icon name="PhArrowRight" />
            </Link>
        </template>

        <div class="space-y-5 p-5 md:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Widget
                    title="Expired Tokens"
                    :value="data?.expired ?? 0"
                    icon="PhWarningCircle"
                    :badge="expiredPercent"
                    badge-class="bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400"
                    right-text="No longer valid"
                />
                <Widget
                    title="Expiring Soon"
                    :value="data?.expiring_soon ?? 0"
                    icon="PhHourglassMedium"
                    right-text="Within 7 days"
                />
                <Widget
                    title="Active Tokens"
                    :value="data?.active ?? 0"
                    icon="PhCheckCircle"
                    right-text="Currently valid"
                />
                <Widget
                    title="Total API Tokens"
                    :value="data?.total ?? 0"
                    icon="PhKey"
                    right-text="All merchant tokens"
                />
            </div>

            <EmptyState
                v-if="!data?.recent?.length"
                icon="PhCheckCircle"
                title="No expired tokens"
                description="All API tokens are currently valid or have no expiry set."
            />

            <div
                v-else
                class="-mx-5 overflow-hidden border-y border-gray-100 dark:border-gray-700/80 md:-mx-6"
            >
                <div class="overflow-x-auto">
                    <table class="professional-table w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-200 bg-slate-50 dark:border-gray-700 dark:bg-slate-900/60"
                            >
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Token
                                </th>
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Domain
                                </th>
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Expired At
                                </th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="row in data.recent"
                                :key="row.id"
                                class="bg-white transition-colors hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700/60"
                            >
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ row.title || "Untitled" }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ row.user_name || "Unknown user" }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="inline-block max-w-[220px] break-all rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-gray-700 dark:bg-slate-700 dark:text-gray-200"
                                    >
                                        {{ row.domain || "—" }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ row.expires_at || "—" }}
                                    </div>
                                    <div class="mt-0.5 text-xs font-medium text-rose-600 dark:text-rose-400">
                                        {{ row.expired_ago }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <span
                                        :class="
                                            twMerge(
                                                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                                row.status
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                    : 'bg-slate-200 text-slate-600 dark:bg-slate-600 dark:text-slate-200',
                                            )
                                        "
                                    >
                                        {{ row.status ? "Enabled" : "Disabled" }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="border-t border-gray-200 bg-slate-50 px-4 py-2.5 text-xs text-gray-500 dark:border-gray-700 dark:bg-slate-900/60 dark:text-gray-400"
                >
                    Showing {{ data.recent.length }} most recent expired
                    {{ data.recent.length === 1 ? "token" : "tokens" }}
                    <span v-if="(data.expired ?? 0) > data.recent.length">
                        of {{ data.expired }} total
                    </span>
                </div>
            </div>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { twMerge } from "tailwind-merge";
import { Icon } from "@/plugins";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import Widget from "./Widget.vue";

type ExpiredToken = {
    id: number;
    title: string;
    domain: string | null;
    user_name: string | null;
    user_email: string | null;
    expires_at: string | null;
    expired_ago: string | null;
    status: boolean;
};

type ExpiredTokenData = {
    title?: string;
    link?: string;
    link_text?: string;
    total?: number;
    expired?: number;
    active?: number;
    expiring_soon?: number;
    recent?: ExpiredToken[];
};

const props = defineProps<{
    data: ExpiredTokenData;
}>();

const expiredPercent = computed(() => {
    const total = props.data?.total ?? 0;
    const expired = props.data?.expired ?? 0;

    if (total <= 0) {
        return "0% of total";
    }

    return `${Math.round((expired / total) * 100)}% of total`;
});
</script>
