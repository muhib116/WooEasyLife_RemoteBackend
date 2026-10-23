<template>
    <AuthenticatedLayout title="Licenses">
        <div class="space-y-5">
            <PageHeader
                title="Licenses"
                :description="pageDescription"
                icon="PhKey"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Link
                        :href="route('users.index')"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-200 px-3.5 text-sm font-medium text-gray-700 sm:w-auto dark:border-gray-700 dark:text-gray-200"
                    >
                        Back to merchants
                    </Link>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <Link
                    v-for="card in cards"
                    :key="card.state"
                    :href="route('users.licenses', { state: card.state })"
                    class="block h-full rounded-2xl text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :class="
                        state === card.state
                            ? 'ring-2 ring-primary-400 ring-offset-2 ring-offset-slate-100 dark:ring-offset-slate-950'
                            : ''
                    "
                >
                    <StatCard
                        :title="card.title"
                        :value="formatCount(card.value)"
                        :icon="card.icon"
                        compact
                        :subtitle="state === card.state ? 'Showing this list' : 'Open this list'"
                        :accent-class="card.accentClass"
                        :icon-bg-class="card.iconBgClass"
                        :icon-class="card.iconClass"
                    />
                </Link>
            </div>

            <PageCard :title="listTitle" :description="listCountLabel" no-padding>
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-700/80 sm:px-5 md:px-6">
                    <IconField class="w-full">
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="search"
                            placeholder="Search merchant, phone, email, or domain..."
                            class="w-full !min-h-11"
                        />
                    </IconField>
                </div>

                <EmptyState
                    v-if="!filtered.length"
                    class="m-5"
                    title="No licenses in this list"
                    description="Try another card, or clear the search."
                    icon="PhKey"
                />

                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="row in paged" :key="row.id">
                        <button
                            type="button"
                            class="flex w-full flex-col gap-2 px-4 py-4 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 sm:px-6 dark:hover:bg-slate-800/60 dark:focus-visible:bg-slate-800/60"
                            @click="openLicense(row)"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ row.merchant.name }}
                                </span>
                                <StatusBadge
                                    :label="stateLabel(row.state)"
                                    :variant="stateVariant(row.state)"
                                    format="none"
                                />
                            </div>
                            <p class="break-all text-sm text-gray-600 dark:text-gray-300">
                                {{ row.domain || "No domain" }}
                                <span v-if="row.merchant.phone"> · {{ row.merchant.phone }}</span>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ expiryLine(row) }}
                                <span v-if="row.plan?.title"> · {{ row.plan.title }}</span>
                            </p>
                        </button>
                    </li>
                </ul>

                <div
                    v-if="filtered.length > pageSize"
                    class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-6"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ pageStart }}–{{ pageEnd }} of {{ filtered.length }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200"
                            :disabled="page === 1"
                            @click="page -= 1"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200"
                            :disabled="page >= pageCount"
                            @click="page += 1"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </PageCard>
        </div>

        <LicenseFollowUpDialog v-model:visible="dialogOpen" v-model:license="selected" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Link } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { formatSubscriptionExpiry } from "@/utils/websiteSubscription";
import EmptyState from "./fragments/EmptyState.vue";
import LicenseFollowUpDialog, {
    type LicenseRow,
} from "./fragments/LicenseFollowUpDialog.vue";
import PageCard from "./fragments/PageCard.vue";
import PageHeader from "./fragments/PageHeader.vue";
import StatCard from "./fragments/StatCard.vue";
import StatusBadge from "./fragments/StatusBadge.vue";
import type { IconName } from "@/types";

const props = defineProps<{
    state: "all" | "active" | "inactive" | "expired";
    counts: {
        total: number;
        active: number;
        inactive: number;
        expired: number;
    };
    licenses: LicenseRow[];
}>();

const search = ref("");
const page = ref(1);
const pageSize = 25;
const dialogOpen = ref(false);
const selected = ref<LicenseRow | null>(null);

const cards = computed(() => [
    {
        state: "all" as const,
        title: "Total Licenses",
        value: props.counts.total,
        icon: "PhKey" as IconName,
        accentClass: "bg-primary-500",
        iconBgClass: "bg-primary-50 dark:bg-primary-500/15",
        iconClass: "text-primary-600 dark:text-primary-400",
    },
    {
        state: "active" as const,
        title: "Active Licenses",
        value: props.counts.active,
        icon: "PhCheckCircle" as IconName,
        accentClass: "bg-emerald-500",
        iconBgClass: "bg-emerald-50 dark:bg-emerald-500/15",
        iconClass: "text-emerald-600 dark:text-emerald-400",
    },
    {
        state: "inactive" as const,
        title: "Inactive Licenses",
        value: props.counts.inactive,
        icon: "PhProhibit" as IconName,
        accentClass: "bg-slate-500",
        iconBgClass: "bg-slate-100 dark:bg-slate-500/15",
        iconClass: "text-slate-600 dark:text-slate-300",
    },
    {
        state: "expired" as const,
        title: "Expired Licenses",
        value: props.counts.expired,
        icon: "PhClock" as IconName,
        accentClass: "bg-rose-500",
        iconBgClass: "bg-rose-50 dark:bg-rose-500/15",
        iconClass: "text-rose-600 dark:text-rose-400",
    },
]);

const titles: Record<typeof props.state, string> = {
    all: "All licenses",
    active: "Active licenses",
    inactive: "Inactive licenses",
    expired: "Expired licenses",
};

const pageDescription = computed(() => {
    if (props.state === "active") return "Enabled keys that have not passed their end date.";
    if (props.state === "inactive") return "Keys that are turned off and have not expired.";
    if (props.state === "expired") return "Keys past their end date. Call these merchants to renew.";
    return "Every plugin license on a merchant account.";
});

const listTitle = computed(() => titles[props.state] || "Licenses");

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (!needle) {
        return props.licenses;
    }

    return props.licenses.filter((row) => {
        const haystack = [
            row.merchant.name,
            row.merchant.email,
            row.merchant.phone,
            row.domain,
            row.title,
            row.plan?.title,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(needle);
    });
});

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));

const paged = computed(() => {
    const start = (page.value - 1) * pageSize;
    return filtered.value.slice(start, start + pageSize);
});

const pageStart = computed(() => (filtered.value.length ? (page.value - 1) * pageSize + 1 : 0));
const pageEnd = computed(() => Math.min(page.value * pageSize, filtered.value.length));

const listCountLabel = computed(() => {
    const total = filtered.value.length;
    return `${total} ${total === 1 ? "license" : "licenses"}`;
});

const formatCount = (value: number) =>
    new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(value || 0);

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

const expiryLine = (row: LicenseRow) => {
    const date = formatSubscriptionExpiry(row.expires_at);
    if (row.state === "expired") {
        return date ? `Expired ${date}` : "Expired";
    }
    return date ? `Ends ${date}` : "No end date";
};

const openLicense = (row: LicenseRow) => {
    selected.value = row;
    dialogOpen.value = true;
};

watch(search, () => {
    page.value = 1;
});

watch(pageCount, (count) => {
    if (page.value > count) {
        page.value = count;
    }
});
</script>
