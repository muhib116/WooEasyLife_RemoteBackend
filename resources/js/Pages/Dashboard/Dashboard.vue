<template>
    <AuthenticatedLayout title="Dashboard">
        <div class="space-y-8">
            <PageHeader
                title="Dashboard"
                :description="`${greeting}, ${authUser?.name || 'Admin'} · ${formattedDate}`"
                icon="PhChartBar"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Link
                        v-for="action in quickActions"
                        :key="action.name"
                        :href="route(action.name)"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 sm:gap-2 sm:px-3.5 sm:text-sm dark:border-gray-700 dark:bg-slate-800 dark:text-gray-200 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10 dark:hover:text-primary-300"
                    >
                        <Icon :name="action.icon" class="text-base" />
                        <span>{{ action.label }}</span>
                    </Link>
                </template>
            </PageHeader>

            <section v-if="healthAlerts.length" class="space-y-3">
                <DashboardSectionHeading title="Action Required" />
                <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr))]">
                    <div
                        v-for="alert in healthAlerts"
                        :key="alert.label"
                        class="flex items-center gap-3 rounded-2xl border px-4 py-3.5"
                        :class="alert.className"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                            :class="alert.iconBg"
                        >
                            <Icon :name="alert.icon" class="text-lg" :class="alert.iconClass" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ alert.label }}
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-300/80">
                                {{ alert.detail }}
                            </p>
                        </div>
                        <Link
                            v-if="alert.href"
                            :href="alert.href"
                            class="text-theme-xs shrink-0 rounded-lg border border-current/20 px-2.5 py-1.5 font-medium transition hover:bg-white/40 dark:hover:bg-black/20"
                        >
                            {{ alert.actionLabel }}
                        </Link>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <DashboardSectionHeading
                    title="Overview"
                    :href="route('users.index')"
                    link-text="All merchants"
                />
                <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <Link :href="route('users.index')" class="block h-full min-w-0">
                        <StatCard
                            title="Merchants"
                            :value="overview.merchants_total"
                            icon="PhUsers"
                            :subtitle="merchantSubtitle"
                            :badge="merchantBadge"
                            badge-label="vs last month"
                            :badge-positive="overview.merchants_growth_positive"
                        />
                    </Link>
                    <Link :href="route('packagePayments.index')" class="block h-full min-w-0">
                        <StatCard
                            title="Pending Payments"
                            :value="overview.pending_payments"
                            icon="PhCreditCard"
                            :subtitle="`${overview.pending_payments_amount} TK awaiting review`"
                            :highlight="overview.pending_payments > 0"
                            accent-class="bg-emerald-500"
                            icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                            icon-class="text-emerald-600 dark:text-emerald-400"
                        />
                    </Link>
                    <Link :href="route('tokenLedger')" class="block h-full min-w-0">
                        <StatCard
                            title="Platform Revenue"
                            :value="overview.platform_revenue"
                            icon="PhCoins"
                            subtitle="All-time plan sales"
                            accent-class="bg-amber-500"
                            icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                            icon-class="text-amber-600 dark:text-amber-400"
                        />
                    </Link>
                    <Link :href="route('subscriptionAlerts.index')" class="block h-full min-w-0">
                        <StatCard
                            title="Active Plans"
                            :value="overview.active_subscriptions"
                            icon="PhPackage"
                            :subtitle="subscriptionsSubtitle"
                            accent-class="bg-violet-500"
                            icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                            icon-class="text-violet-600 dark:text-violet-400"
                        />
                    </Link>
                </div>
            </section>

            <section class="space-y-4">
                <DashboardSectionHeading title="At a glance" />
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <component
                        :is="item.href ? Link : 'div'"
                        v-for="item in glanceItems"
                        :key="item.label"
                        :href="item.href || undefined"
                        class="box-bg box-color box-border flex min-w-0 flex-col rounded-2xl border p-3.5 sm:p-4"
                        :class="item.href ? 'transition hover:border-primary-300 hover:shadow-sm dark:hover:border-primary-500/40' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ item.label }}
                            </p>
                            <Icon :name="item.icon" class="shrink-0 text-base text-gray-400 dark:text-gray-500" />
                        </div>
                        <p class="mt-2 break-words text-lg font-bold leading-tight text-gray-900 sm:text-xl dark:text-white">
                            {{ item.value }}
                        </p>
                        <p class="mt-1 text-xs leading-snug text-gray-500 dark:text-gray-400">
                            {{ item.detail }}
                        </p>
                    </component>
                </div>
            </section>

            <section class="space-y-4">
                <DashboardSectionHeading
                    title="Live site traffic"
                    :href="route('siteVisitors.index')"
                    link-text="Visitors"
                />
                <GaRealtimePanel />
            </section>

            <section class="space-y-4">
                <DashboardSectionHeading
                    title="Needs Attention"
                    :href="hasAttentionItems ? route('packagePayments.index') : undefined"
                    :link-text="hasAttentionItems ? 'Review items' : undefined"
                />
                <div v-if="hasAttentionItems" class="grid grid-cols-1 gap-4 xl:grid-cols-2 [&>*]:min-w-0">
                    <PaymentRequestsPanel
                        v-if="(paymentRequests.summary?.pending ?? 0) > 0"
                        :data="paymentRequests"
                    />
                    <SubscriptionAlertsPanel
                        v-if="(subscriptionAlerts.summary?.total ?? 0) > 0"
                        :data="subscriptionAlerts"
                    />
                    <ExpiredTokensPanel
                        v-if="showTokenPanel"
                        :data="expiredTokens"
                    />
                </div>
                <AllClearCard v-else />
            </section>

            <section class="space-y-4">
                <DashboardSectionHeading
                    title="Revenue & Usage"
                    :href="route('tokenLedger')"
                    link-text="Token ledger"
                />
                <div class="grid grid-cols-1 gap-4">
                    <GroupListBox
                        :data="getData('tokens')"
                        icon="PhCoins"
                        description="Order capacity sold, consumed, and remaining across all merchants"
                        show-progress
                        progress-label="Token utilization"
                        :progress-percent="overview.token_usage_percent"
                    />
                </div>
            </section>

            <section v-if="showActivitySection" class="space-y-4">
                <DashboardSectionHeading
                    title="Platform Activity"
                    :href="route('webhooks.index')"
                    link-text="Webhook log"
                />
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 [&>*]:min-w-0">
                    <WebhookActivityPanel
                        v-if="showWebhookPanel"
                        :data="webhooks"
                    />
                    <CustomerNoticesPanel
                        v-if="(customerNotices.summary?.total ?? 0) > 0"
                        :data="customerNotices"
                    />
                </div>
            </section>

            <section v-if="showSmsSection" class="space-y-4">
                <DashboardSectionHeading title="SMS" />
                <div class="grid grid-cols-1 gap-3 min-[480px]:grid-cols-3">
                    <Widget
                        title="User Balance"
                        :value="`${sms.total_balance} TK`"
                        icon="PhWallet"
                        right-text="Held by merchants"
                    />
                    <Widget
                        title="Total Recharged"
                        :value="`${sms.total_sms_recharge} TK`"
                        icon="PhArrowCircleUp"
                        right-text="All-time top-ups"
                    />
                    <Widget
                        title="Messages Sent"
                        :value="sms.total_sms_sent"
                        icon="PhPaperPlaneTilt"
                        right-text="Delivered messages"
                    />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import ExpiredTokensPanel from "./fragments/ExpiredTokensPanel.vue";
import SubscriptionAlertsPanel from "./fragments/SubscriptionAlertsPanel.vue";
import PaymentRequestsPanel from "./fragments/PaymentRequestsPanel.vue";
import CustomerNoticesPanel from "./fragments/CustomerNoticesPanel.vue";
import WebhookActivityPanel from "./fragments/WebhookActivityPanel.vue";
import GroupListBox from "./fragments/GroupListBox.vue";
import StatCard from "./fragments/StatCard.vue";
import Widget from "./fragments/Widget.vue";
import DashboardSectionHeading from "./fragments/DashboardSectionHeading.vue";
import AllClearCard from "./fragments/AllClearCard.vue";
import GaRealtimePanel from "@/components/analytics/GaRealtimePanel.vue";
import { Icon } from "@/plugins";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { get } from "lodash";
import type { IconName } from "@/types";

type OverviewData = {
    merchants_total: number;
    merchants_new_month: number;
    merchants_previous_month: number;
    merchants_growth_pct: string;
    merchants_growth_positive: boolean;
    pending_payments: number;
    pending_payments_amount: string;
    platform_revenue: string;
    token_usage_percent: number;
    token_remaining: string;
    active_subscriptions: number;
    expiring_subscriptions: number;
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

type SubscriptionAlertData = {
    title?: string;
    link?: string;
    link_text?: string;
    summary?: {
        total: number;
        danger: number;
        warning: number;
        info: number;
    };
    recent?: Array<{
        type: string;
        severity: string;
        message: string;
        user_id: number;
        user_name: string;
        domain: string;
    }>;
};

type CustomerNoticeData = {
    title?: string;
    link?: string;
    link_text?: string;
    summary?: {
        total: number;
        live: number;
        scheduled: number;
        inactive: number;
    };
    recent?: Array<{
        id: number;
        title: string;
        type: string;
        type_label: string;
        audience: string;
        audience_label: string;
        severity: string;
        status: string;
    }>;
};

type PaymentRequestData = {
    title?: string;
    link?: string;
    link_text?: string;
    summary?: {
        total: number;
        pending: number;
        approved: number;
        cancelled: number;
        pending_amount?: string;
    };
    recent?: Array<{
        id: number;
        user_name: string | null;
        domain: string | null;
        package_title: string | null;
        total_amount: string;
        submitted_ago: string | null;
    }>;
};

type DashboardData = {
    overview: OverviewData;
    tokens: {
        data: Array<{ title: string; value: string | number }>;
    };
    sms: {
        total_balance: string;
        total_sms_sent: string;
        total_sms_recharge: string;
    };
    expired_tokens: ExpiredTokenData;
    subscription_alerts: SubscriptionAlertData;
    webhooks: WebhookData;
    customer_notices: CustomerNoticeData;
    payment_requests: PaymentRequestData;
};

const props = defineProps<{
    data: DashboardData;
}>();

const page = usePage();
const authUser = computed(() => page.props.auth?.user as { name?: string } | null);

const getData = (key: string) => get(props.data, key);

const overview = computed(() => get(props.data, "overview", {
    merchants_total: 0,
    merchants_new_month: 0,
    merchants_previous_month: 0,
    merchants_growth_pct: "0.00",
    merchants_growth_positive: true,
    pending_payments: 0,
    pending_payments_amount: "0.00",
    platform_revenue: "0 TK",
    token_usage_percent: 0,
    token_remaining: "0",
    active_subscriptions: 0,
    expiring_subscriptions: 0,
}) as OverviewData);

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

const subscriptionAlerts = computed(() => get(props.data, "subscription_alerts", {
    summary: { total: 0, danger: 0, warning: 0, info: 0 },
    recent: [],
}) as SubscriptionAlertData);

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

const customerNotices = computed(() => get(props.data, "customer_notices", {
    summary: { total: 0, live: 0, scheduled: 0, inactive: 0 },
    recent: [],
}) as CustomerNoticeData);

const paymentRequests = computed(() => get(props.data, "payment_requests", {
    summary: { total: 0, pending: 0, approved: 0, cancelled: 0, pending_amount: "0.00" },
    recent: [],
}) as PaymentRequestData);

const merchantSubtitle = computed(() => {
    const current = overview.value.merchants_new_month;
    const previous = overview.value.merchants_previous_month ?? 0;

    return `${current} new this month · ${previous} last month`;
});

const merchantBadge = computed(() => {
    const current = overview.value.merchants_new_month;
    const previous = overview.value.merchants_previous_month ?? 0;

    if (previous === 0 && current === 0) {
        return undefined;
    }

    const pct = Number.parseFloat(overview.value.merchants_growth_pct);

    if (!Number.isFinite(pct)) {
        return undefined;
    }

    const rounded = Math.round(pct);
    const sign = rounded > 0 ? "+" : "";

    return `${sign}${rounded}%`;
});

const wholeNumber = (value: string | number) => {
    const parsed = Number.parseFloat(String(value).replace(/,/g, ""));

    if (!Number.isFinite(parsed)) {
        return String(value);
    }

    return new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(parsed);
};

const glanceItems = computed(() => {
    const failedWebhooks =
        (webhooks.value.failed_count ?? 0) + (webhooks.value.orphan_count ?? 0);
    const webhookTotal = webhooks.value.total_events ?? 0;
    const notices = customerNotices.value.summary;

    return [
        {
            label: "Orders left",
            value: overview.value.token_remaining,
            detail: `${overview.value.token_usage_percent}% of sold capacity used`,
            icon: "PhPackage" as IconName,
            href: route("tokenLedger"),
        },
        {
            label: "API tokens",
            value: `${expiredTokens.value.active ?? 0} active`,
            detail: `${expiredTokens.value.expired ?? 0} expired · ${expiredTokens.value.expiring_soon ?? 0} soon`,
            icon: "PhKey" as IconName,
            href: route("users.index"),
        },
        {
            label: "Webhooks",
            value: webhookTotal > 0 ? `${webhooks.value.success_rate ?? 0}% delivered` : "No events",
            detail: webhookTotal > 0
                ? `${failedWebhooks} failed · ${webhooks.value.pending_retries ?? 0} retries waiting`
                : "Courier updates will show here",
            icon: "PhArrowClockwise" as IconName,
            href: route("webhooks.index"),
        },
        {
            label: "SMS balance",
            value: `${sms.value.total_balance} TK`,
            detail: `${wholeNumber(sms.value.total_sms_sent)} messages sent · ${sms.value.total_sms_recharge} TK recharged`,
            icon: "PhPaperPlaneTilt" as IconName,
            href: "",
        },
        {
            label: "Notices",
            value: `${notices?.live ?? 0} live`,
            detail: `${notices?.scheduled ?? 0} scheduled · ${notices?.inactive ?? 0} inactive`,
            icon: "PhMegaphone" as IconName,
            href: route("customerNotices.index"),
        },
        {
            label: "Plans expiring",
            value: overview.value.expiring_subscriptions,
            detail: overview.value.expiring_subscriptions > 0
                ? "Within the next 7 days"
                : "None due in the next 7 days",
            icon: "PhHourglassMedium" as IconName,
            href: route("subscriptionAlerts.index"),
        },
    ];
});

const subscriptionsSubtitle = computed(() => {
    const expiring = overview.value.expiring_subscriptions;

    if (expiring > 0) {
        return `${expiring} expiring within 7 days`;
    }

    return "Active merchant subscriptions";
});

const showTokenPanel = computed(() => {
    return (expiredTokens.value.expired ?? 0) > 0 || (expiredTokens.value.expiring_soon ?? 0) > 0;
});

const hasAttentionItems = computed(() => {
    return (paymentRequests.value.summary?.pending ?? 0) > 0
        || (subscriptionAlerts.value.summary?.total ?? 0) > 0
        || showTokenPanel.value;
});

const showWebhookPanel = computed(() => {
    const wh = webhooks.value;

    return (wh.total_events ?? 0) > 0
        || (wh.pending_retries ?? 0) > 0
        || (wh.failed_count ?? 0) > 0
        || (wh.orphan_count ?? 0) > 0;
});

const showActivitySection = computed(() => {
    return showWebhookPanel.value || (customerNotices.value.summary?.total ?? 0) > 0;
});

const showSmsSection = computed(() => {
    const balance = Number.parseFloat(String(sms.value.total_balance).replace(/,/g, ""));
    const sent = Number.parseFloat(String(sms.value.total_sms_sent).replace(/,/g, ""));
    const recharged = Number.parseFloat(String(sms.value.total_sms_recharge).replace(/,/g, ""));

    return balance > 0 || sent > 0 || recharged > 0;
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
        weekday: "short",
        month: "short",
        day: "numeric",
        year: "numeric",
    }).format(new Date());
});

const quickActions: { label: string; name: string; icon: IconName }[] = [
    { label: "Merchants", name: "users.index", icon: "PhUsers" },
    { label: "Payments", name: "packagePayments.index", icon: "PhCreditCard" },
    { label: "Alerts", name: "subscriptionAlerts.index", icon: "PhBellRinging" },
    { label: "Token Ledger", name: "tokenLedger", icon: "PhCoins" },
];

type HealthAlert = {
    label: string;
    detail: string;
    icon: IconName;
    href?: string;
    actionLabel: string;
    priority: number;
    className: string;
    iconBg: string;
    iconClass: string;
};

const healthAlerts = computed<HealthAlert[]>(() => {
    const alerts: HealthAlert[] = [];

    const pendingPayments = paymentRequests.value.summary?.pending ?? 0;
    const criticalSubscriptions = subscriptionAlerts.value.summary?.danger ?? 0;
    const warningSubscriptions = subscriptionAlerts.value.summary?.warning ?? 0;
    const pendingRetries = webhooks.value.pending_retries ?? 0;
    const failedWebhooks =
        (webhooks.value.failed_count ?? 0) + (webhooks.value.orphan_count ?? 0);
    const expiredCount = expiredTokens.value.expired ?? 0;
    const expiringSoon = expiredTokens.value.expiring_soon ?? 0;

    if (pendingPayments > 0) {
        alerts.push({
            label: "Payment requests pending",
            detail: `${pendingPayments} manual ${pendingPayments === 1 ? "payment" : "payments"} · ${paymentRequests.value.summary?.pending_amount ?? "0.00"} TK`,
            icon: "PhCreditCard",
            href: route("packagePayments.index"),
            actionLabel: "Review",
            priority: 1,
            className:
                "border-emerald-200 bg-emerald-50/80 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100",
            iconBg: "bg-emerald-100 dark:bg-emerald-500/20",
            iconClass: "text-emerald-600 dark:text-emerald-300",
        });
    }

    if (criticalSubscriptions > 0) {
        alerts.push({
            label: "Critical subscription alerts",
            detail: `${criticalSubscriptions} merchants need immediate attention`,
            icon: "PhBellRinging",
            href: route("subscriptionAlerts.index"),
            actionLabel: "View",
            priority: 2,
            className:
                "border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100",
            iconBg: "bg-rose-100 dark:bg-rose-500/20",
            iconClass: "text-rose-600 dark:text-rose-300",
        });
    } else if (warningSubscriptions > 0) {
        alerts.push({
            label: "Subscription warnings",
            detail: `${warningSubscriptions} merchants have expiring plans or low quota`,
            icon: "PhBell",
            href: route("subscriptionAlerts.index"),
            actionLabel: "View",
            priority: 3,
            className:
                "border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100",
            iconBg: "bg-amber-100 dark:bg-amber-500/20",
            iconClass: "text-amber-600 dark:text-amber-300",
        });
    }

    if (failedWebhooks > 0) {
        alerts.push({
            label: "Webhook delivery issues",
            detail: `${failedWebhooks} failed or orphan events need review`,
            icon: "PhWarningCircle",
            href: route("webhooks.index"),
            actionLabel: "Inspect",
            priority: 4,
            className:
                "border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100",
            iconBg: "bg-rose-100 dark:bg-rose-500/20",
            iconClass: "text-rose-600 dark:text-rose-300",
        });
    } else if (pendingRetries > 0) {
        alerts.push({
            label: "Webhook retries pending",
            detail: `${pendingRetries} forward ${pendingRetries === 1 ? "retry" : "retries"} waiting in queue`,
            icon: "PhClock",
            href: route("webhooks.index"),
            actionLabel: "Inspect",
            priority: 5,
            className:
                "border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100",
            iconBg: "bg-amber-100 dark:bg-amber-500/20",
            iconClass: "text-amber-600 dark:text-amber-300",
        });
    }

    if (expiredCount > 0) {
        alerts.push({
            label: "Expired API tokens",
            detail: `${expiredCount} merchant tokens are no longer valid`,
            icon: "PhKey",
            href: route("users.index"),
            actionLabel: "Manage",
            priority: 6,
            className:
                "border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100",
            iconBg: "bg-rose-100 dark:bg-rose-500/20",
            iconClass: "text-rose-600 dark:text-rose-300",
        });
    } else if (expiringSoon > 0) {
        alerts.push({
            label: "Tokens expiring soon",
            detail: `${expiringSoon} tokens expire within 7 days`,
            icon: "PhHourglassMedium",
            href: route("users.index"),
            actionLabel: "Manage",
            priority: 7,
            className:
                "border-sky-200 bg-sky-50/80 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100",
            iconBg: "bg-sky-100 dark:bg-sky-500/20",
            iconClass: "text-sky-600 dark:text-sky-300",
        });
    }

    return alerts
        .sort((left, right) => left.priority - right.priority)
        .slice(0, 3);
});
</script>
