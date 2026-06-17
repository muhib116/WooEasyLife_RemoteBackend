<template>
    <AuthenticatedLayout title="Dashboard">
        <div class="space-y-6">
            <div
                class="box-bg box-color box-border flex flex-col justify-between gap-4 rounded-2xl border p-5 md:flex-row md:items-center md:p-6"
            >
                <div>
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ greeting }}
                    </p>
                    <h1
                        class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90"
                    >
                        {{ authUser?.name || "Admin" }}
                    </h1>
                    <p class="mt-1 text-theme-sm text-gray-400 dark:text-gray-500">
                        {{ formattedDate }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="action in quickActions"
                        :key="action.name"
                        :href="route(action.name)"
                        class="text-theme-sm inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2 font-medium text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-gray-700 dark:bg-slate-800 dark:text-gray-200 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10 dark:hover:text-primary-300"
                    >
                        <Icon :name="action.icon" />
                        {{ action.label }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Total Users"
                    :value="findStat(usersStats, 'Total User')"
                    icon="PhUsers"
                    subtitle="Registered merchant accounts"
                />
                <StatCard
                    title="New Users"
                    :value="findStat(usersStats, 'New User Of This Month')"
                    icon="PhUserPlus"
                    :badge="`${findStat(usersStats, 'Increase / Decrease')}%`"
                    badge-label="vs last month"
                    :badge-positive="userGrowthPositive"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="SMS Balance"
                    :value="`${sms.total_balance} TK`"
                    icon="PhChatCircleText"
                    subtitle="Total balance across users"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Webhook Events"
                    :value="webhooks.total_events ?? 0"
                    icon="PhArrowClockwise"
                    :subtitle="webhookSubtitle"
                    :badge="`${webhooks.success_rate ?? 0}%`"
                    badge-label="forward success"
                    :badge-positive="(webhooks.pending_retries ?? 0) === 0"
                    accent-class="bg-violet-500"
                    icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                    icon-class="text-violet-600 dark:text-violet-400"
                />
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <GroupListBox
                    :data="getData('users')"
                    icon="PhUsersThree"
                    description="User growth and registration overview"
                />
                <GroupListBox
                    :data="getData('tokens')"
                    icon="PhCoins"
                    description="Token sales, usage, and revenue"
                    show-progress
                    :progress-percent="tokenUsagePercent"
                />
            </div>

            <ExpiredTokensPanel :data="expiredTokens" />

            <WebhookActivityPanel :data="webhooks" />

            <div>
                <div class="mb-4 flex items-center gap-2">
                    <Icon
                        name="PhDeviceMobile"
                        class="text-lg text-gray-500 dark:text-gray-400"
                    />
                    <h2
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        SMS Overview
                    </h2>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Widget
                        title="User SMS Balance"
                        :value="`${sms.total_balance} TK`"
                        icon="PhWallet"
                        right-text="Held by users"
                    />
                    <Widget
                        title="Total Recharged"
                        :value="`${sms.total_sms_recharge} TK`"
                        icon="PhArrowCircleUp"
                        right-text="All-time recharge"
                    />
                    <Widget
                        title="SMS Sent"
                        :value="sms.total_sms_sent"
                        icon="PhPaperPlaneTilt"
                        right-text="Messages delivered"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import ExpiredTokensPanel from "./fragments/ExpiredTokensPanel.vue";
import WebhookActivityPanel from "./fragments/WebhookActivityPanel.vue";
import GroupListBox from "./fragments/GroupListBox.vue";
import StatCard from "./fragments/StatCard.vue";
import Widget from "./fragments/Widget.vue";
import { Icon } from "@/plugins";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { get } from "lodash";
import type { IconName } from "@/types";

type StatItem = {
    title: string;
    value: string | number;
};

type ExpiredTokenData = {
    title?: string;
    link?: string;
    link_text?: string;
    total?: number;
    expired?: number;
    active?: number;
    expiring_soon?: number;
    recent?: Array<{
        id: number;
        title: string;
        domain: string | null;
        user_name: string | null;
        expires_at: string | null;
        expired_ago: string | null;
        status: boolean;
    }>;
};

type DashboardData = {
    users: {
        data: StatItem[];
    };
    tokens: {
        data: StatItem[];
    };
    sms: {
        total_balance: string;
        total_sms_sent: string;
        total_sms_recharge: string;
    };
    expired_tokens: ExpiredTokenData;
    webhooks: WebhookData;
};

const props = defineProps<{
    data: DashboardData;
}>();

const page = usePage();
const authUser = computed(() => page.props.auth?.user as { name?: string } | null);

const getData = (key: string) => get(props.data, key);

const usersStats = computed(() => get(props.data, "users.data", []) as StatItem[]);
const tokensStats = computed(() => get(props.data, "tokens.data", []) as StatItem[]);
const sms = computed(() => get(props.data, "sms", {
    total_balance: "0",
    total_sms_sent: "0",
    total_sms_recharge: "0",
}));

const expiredTokens = computed(() => get(props.data, "expired_tokens", {
    total: 0,
    expired: 0,
    active: 0,
    expiring_soon: 0,
    recent: [],
}) as ExpiredTokenData);

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
    recent?: Array<{
        id: number;
        partner: string;
        environment: string;
        consignment_id: string | null;
        wc_order_id: number | null;
        event_type: string | null;
        forward_status: string;
        forward_message: string | null;
        created_at: string | null;
        received_ago: string | null;
    }>;
    partners?: Array<{ partner: string; total: number }>;
};

const webhooks = computed(() => get(props.data, "webhooks", {
    total_events: 0,
    success_count: 0,
    failed_count: 0,
    retry_queued_count: 0,
    orphan_count: 0,
    pending_retries: 0,
    failed_retries: 0,
    success_rate: 0,
    recent: [],
    partners: [],
}) as WebhookData);

const webhookSubtitle = computed(() => {
    const pending = webhooks.value.pending_retries ?? 0;

    if (pending > 0) {
        return `${pending} pending ${pending === 1 ? "retry" : "retries"}`;
    }

    if (webhooks.value.last_event_at) {
        return `Last event: ${webhooks.value.last_event_at}`;
    }

    return "No webhook events yet";
});

const expiredTokenBadge = computed(() => {
    const total = expiredTokens.value.total ?? 0;
    const expired = expiredTokens.value.expired ?? 0;

    if (total <= 0) {
        return "0%";
    }

    return `${Math.round((expired / total) * 100)}%`;
});

const findStat = (stats: StatItem[], title: string) => {
    const item = stats.find((stat) => stat.title === title);
    return item?.value ?? "0";
};

const parseStatNumber = (stats: StatItem[], title: string) => {
    const value = String(findStat(stats, title)).replace(/,/g, "");
    return Number.parseFloat(value) || 0;
};

const tokenSell = computed(() => parseStatNumber(tokensStats.value, "Token Sell"));
const tokenUsed = computed(() => parseStatNumber(tokensStats.value, "Token Used"));

const tokenUsagePercent = computed(() => {
    if (tokenSell.value <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((tokenUsed.value / tokenSell.value) * 100));
});

const userGrowthPositive = computed(() => {
    const growth = Number.parseFloat(
        String(findStat(usersStats.value, "Increase / Decrease")),
    );

    return Number.isNaN(growth) ? true : growth >= 0;
});

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return "Good morning";
    }

    if (hour < 17) {
        return "Good afternoon";
    }

    return "Good evening";
});

const formattedDate = computed(() => {
    return new Intl.DateTimeFormat("en-US", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
    }).format(new Date());
});

const quickActions: { label: string; name: string; icon: IconName }[] = [
    { label: "Users", name: "users.index", icon: "PhUsers" },
    { label: "Webhooks", name: "webhooks.index", icon: "PhArrowClockwise" },
    { label: "Token Ledger", name: "tokenLedger", icon: "PhCoins" },
    { label: "Packages", name: "packages.index", icon: "PhPackage" },
    { label: "API Keys", name: "apiKeys.index", icon: "PhLockKeyOpen" },
];
</script>
