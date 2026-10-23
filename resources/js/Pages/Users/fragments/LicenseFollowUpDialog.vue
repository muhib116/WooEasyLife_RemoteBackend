<template>
    <AdminDialog
        v-model:visible="visible"
        class="license-follow-up-dialog"
        :style="{ width: 'min(40rem, calc(100vw - 2rem))' }"
        @hide="license = null"
    >
        <template #header>
            <div class="min-w-0 flex-1 pr-2">
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ license?.merchant.name || "License" }}
                </p>
                <p class="mt-0.5 break-all text-sm text-gray-500 dark:text-gray-400">
                    {{ license?.domain || "No domain" }}
                </p>
                <div v-if="license" class="mt-2 flex flex-wrap items-center gap-1.5">
                    <StatusBadge
                        :label="stateLabel(license.state)"
                        :variant="stateVariant(license.state)"
                        format="none"
                    />
                    <StatusBadge
                        :label="license.merchant.status ? 'Account enabled' : 'Account disabled'"
                        :variant="license.merchant.status ? 'success' : 'danger'"
                        format="none"
                    />
                    <StatusBadge
                        v-if="license.merchant.attention"
                        :label="license.merchant.attention"
                        variant="warning"
                        format="none"
                    />
                </div>
            </div>
        </template>

        <div v-if="license" class="space-y-5">
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div
                    v-for="row in detailRows"
                    :key="row.label"
                    class="rounded-xl border border-gray-100 px-3 py-2.5 dark:border-gray-800"
                >
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ row.label }}
                    </dt>
                    <dd class="mt-1 break-words text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>

            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3 dark:border-gray-800 dark:bg-slate-900/60">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    Follow up
                </p>
                <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    Call or WhatsApp this merchant, then open billing if they renew.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a
                        v-if="license.merchant.phone"
                        :href="`tel:${license.merchant.phone}`"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-500 px-3.5 text-sm font-semibold text-white"
                    >
                        Call {{ license.merchant.phone }}
                    </a>
                    <a
                        v-if="whatsAppHref"
                        :href="whatsAppHref"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-3.5 text-sm font-semibold text-gray-800 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-100"
                    >
                        WhatsApp
                    </a>
                    <span
                        v-if="!license.merchant.phone"
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No phone on this account.
                    </span>
                </div>
            </div>
        </div>

        <template v-if="license" #footer>
            <div class="flex w-full flex-wrap justify-end gap-2">
                <Link
                    :href="route('users.view', license.merchant.id)"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 px-3.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                >
                    Merchant
                </Link>
                <Link
                    :href="route('users.websites', license.merchant.id)"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 px-3.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                >
                    Websites
                </Link>
                <Link
                    :href="route('users.billing', license.merchant.id)"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 px-3.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                >
                    Billing
                </Link>
            </div>
        </template>
    </AdminDialog>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { formatSubscriptionExpiry } from "@/utils/websiteSubscription";
import AdminDialog from "./AdminDialog.vue";
import StatusBadge from "./StatusBadge.vue";

export type LicensePlan = {
    title: string | null;
    is_active: boolean;
    remaining_order: number;
    total_order_can_handle: number;
    expires_at: string | null;
    domain: string | null;
};

export type LicenseRow = {
    id: number;
    title: string;
    domain: string | null;
    state: "active" | "inactive" | "expired";
    status: boolean;
    expires_at: string | null;
    last_used_at: string | null;
    last_used_ago: string | null;
    merchant: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        status: boolean;
        attention: string | null;
    };
    plan: LicensePlan | null;
};

const visible = defineModel<boolean>("visible", { required: true });
const license = defineModel<LicenseRow | null>("license", { required: true });

const stateLabel = (state: LicenseRow["state"]) => {
    if (state === "active") return "Active";
    if (state === "inactive") return "Inactive";
    return "Expired";
};

const stateVariant = (state: LicenseRow["state"]) => {
    if (state === "active") return "success" as const;
    if (state === "inactive") return "neutral" as const;
    return "danger" as const;
};

const whatsAppHref = computed(() => {
    const digits = String(license.value?.merchant.phone || "").replace(/\D/g, "");
    if (/^01\d{9}$/.test(digits)) {
        return `https://wa.me/88${digits}`;
    }
    if (/^8801\d{9}$/.test(digits)) {
        return `https://wa.me/${digits}`;
    }
    return null;
});

const detailRows = computed(() => {
    const row = license.value;
    if (!row) {
        return [];
    }

    const plan = row.plan;

    return [
        { label: "License", value: row.title || "License key" },
        { label: "Phone", value: row.merchant.phone || "—" },
        { label: "Email", value: row.merchant.email || "—" },
        { label: "Domain", value: row.domain || "—" },
        {
            label: "License ends",
            value: formatSubscriptionExpiry(row.expires_at) || "No end date",
        },
        { label: "Last used", value: row.last_used_ago || "Never" },
        { label: "Plan", value: plan?.title || "No plan linked" },
        {
            label: "Plan status",
            value: plan ? (plan.is_active ? "Active" : "Inactive") : "—",
        },
        {
            label: "Orders left",
            value: plan
                ? `${plan.remaining_order.toLocaleString()} of ${plan.total_order_can_handle.toLocaleString()}`
                : "—",
        },
        {
            label: "Plan ends",
            value: plan ? formatSubscriptionExpiry(plan.expires_at) || "No end date" : "—",
        },
    ];
});
</script>
