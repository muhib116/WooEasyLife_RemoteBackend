<template>
    <PageCard no-padding>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 dark:bg-violet-500/15"
                >
                    <Icon
                        name="PhArrowClockwise"
                        class="text-lg text-violet-600 dark:text-violet-400"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        {{ data?.title || "Courier Webhook Activity" }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Inbound courier webhooks and WordPress forward status
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
                {{ data?.link_text || "View All Activities" }}
                <Icon name="PhArrowRight" />
            </Link>
        </template>

        <div class="space-y-5 p-5 md:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Widget
                    title="Total Events"
                    :value="data?.total_events ?? 0"
                    icon="PhListBullets"
                    :right-text="lastEventText"
                />
                <Widget
                    title="Forwarded"
                    :value="data?.success_count ?? 0"
                    icon="PhCheckCircle"
                    :badge="`${data?.success_rate ?? 0}% success rate`"
                    badge-class="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"
                    right-text="Delivered to WordPress"
                />
                <Widget
                    title="Pending Retries"
                    :value="data?.pending_retries ?? 0"
                    icon="PhClock"
                    :badge="`${data?.retry_queued_count ?? 0} queued events`"
                    badge-class="bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
                    right-text="Waiting to forward"
                />
                <Widget
                    title="Failed / Orphan"
                    :value="(data?.failed_count ?? 0) + (data?.orphan_count ?? 0)"
                    icon="PhWarningCircle"
                    :badge="`${data?.failed_retries ?? 0} failed retries`"
                    badge-class="bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400"
                    right-text="Needs attention"
                />
            </div>

            <div
                v-if="data?.partners?.length"
                class="flex flex-wrap gap-2"
            >
                <span
                    v-for="partner in data.partners"
                    :key="partner.partner"
                    class="text-theme-xs inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 font-medium capitalize text-gray-700 dark:border-gray-700 dark:bg-slate-800 dark:text-gray-200"
                >
                    {{ partner.partner }}
                    <span class="text-gray-400">{{ partner.total }}</span>
                </span>
            </div>

            <EmptyState
                v-if="!data?.recent?.length"
                icon="PhArrowClockwise"
                title="No webhook events yet"
                description="Events will appear here when couriers send status updates."
            />

            <div
                v-else
                class="-mx-5 overflow-hidden border-y border-gray-100 dark:border-gray-700/80 md:-mx-6"
            >
                <div class="overflow-x-auto">
                    <table class="professional-table w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-200 bg-slate-50 dark:border-gray-700 dark:bg-slate-900/60"
                            >
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Received
                                </th>
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Partner
                                </th>
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Shipment
                                </th>
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Event
                                </th>
                                <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
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
                                        {{ row.created_at || "—" }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ row.received_ago }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium capitalize text-gray-900 dark:text-gray-100">
                                        {{ row.partner }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ row.environment }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ row.consignment_id || "—" }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        Order #{{ row.wc_order_id || "—" }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-200">
                                    {{ row.event_type || "—" }}
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        :class="
                                            twMerge(
                                                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                                                statusClass(row.forward_status),
                                            )
                                        "
                                    >
                                        {{ row.forward_status || "—" }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="border-t border-gray-200 bg-slate-50 px-4 py-2.5 text-xs text-gray-500 dark:border-gray-700 dark:bg-slate-900/60 dark:text-gray-400"
                >
                    Showing {{ data.recent.length }} most recent
                    {{ data.recent.length === 1 ? "event" : "events" }}
                    <span v-if="(data.total_events ?? 0) > data.recent.length">
                        of {{ data.total_events }} total
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

type WebhookEvent = {
    id: number;
    partner: string;
    environment: string;
    consignment_id: string | null;
    wc_order_id: number | null;
    site_url: string | null;
    event_type: string | null;
    forward_status: string;
    forward_message: string | null;
    created_at: string | null;
    received_ago: string | null;
};

type WebhookData = {
    title?: string;
    link?: string;
    link_text?: string;
    total_events?: number;
    success_count?: number;
    failed_count?: number;
    retry_queued_count?: number;
    orphan_count?: number;
    pending_retries?: number;
    failed_retries?: number;
    success_rate?: number;
    last_event_at?: string | null;
    last_forward_status?: string | null;
    recent?: WebhookEvent[];
    partners?: Array<{ partner: string; total: number }>;
};

const props = defineProps<{
    data: WebhookData;
}>();

const lastEventText = computed(() => {
    if (!props.data?.last_event_at) {
        return "No events received yet";
    }

    return `Last event: ${props.data.last_event_at}`;
});

const statusClass = (status: string) => {
    switch (status) {
        case "success":
            return "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300";
        case "retry_queued":
            return "bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300";
        case "failed":
        case "orphan":
            return "bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300";
        default:
            return "bg-slate-100 text-slate-600 dark:bg-slate-600 dark:text-slate-200";
    }
};
</script>
