<template>
    <PageCard no-padding>
        <div
            class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700/80"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <Icon name="PhGlobe" class="text-primary-500" />
                    <h3 class="truncate font-semibold text-gray-900 dark:text-white">
                        {{ website.domain }}
                    </h3>
                    <StatusBadge
                        :label="healthStatusLabel(website.health?.status)"
                        :variant="healthStatusVariant(website.health?.status)"
                        format="none"
                    />
                    <StatusBadge
                        v-if="website.is_primary"
                        label="Primary"
                        variant="info"
                        format="none"
                    />
                </div>
                <a
                    :href="website.display_url"
                    target="_blank"
                    rel="noopener"
                    class="mt-1 inline-flex items-center gap-1 text-xs text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
                >
                    {{ website.display_url }}
                    <Icon name="PhArrowSquareOut" class="text-[0.7rem]" />
                </a>
            </div>
            <Button
                icon="pi pi-ellipsis-h"
                size="small"
                severity="secondary"
                outlined
                rounded
                aria-label="More actions"
                v-tooltip.top="'More actions'"
                @click="$emit('menu', $event)"
            />
        </div>

        <div class="space-y-4 p-5">
            <div
                v-if="primaryIssue"
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                <Icon name="PhWarning" class="mt-0.5 shrink-0 text-base" />
                <p>{{ primaryIssue }}</p>
            </div>

            <section v-if="website.subscription" class="rounded-xl border border-gray-100 dark:border-gray-800">
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Subscription
                        </p>
                        <p class="truncate font-semibold text-gray-900 dark:text-white">
                            {{ website.subscription.title }}
                        </p>
                    </div>
                    <StatusBadge
                        :label="subscriptionPlanBadge(website.subscription)"
                        :variant="isCatalogSubscription(website.subscription) ? 'info' : 'neutral'"
                        format="none"
                    />
                </div>

                <div class="space-y-3 px-4 py-3">
                    <div
                        v-if="expiryWarning.showWarning"
                        class="flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm"
                        :class="expiryBannerClass"
                    >
                        <Icon
                            :name="expiryWarning.status === 'expired' ? 'PhWarningCircle' : 'PhClock'"
                            class="mt-0.5 shrink-0 text-base"
                        />
                        <div class="min-w-0">
                            <p class="font-semibold">{{ expiryWarning.title }}</p>
                            <p class="mt-0.5 font-mono text-xs tabular-nums tracking-wide">
                                {{ expiryWarning.countdown }}
                            </p>
                            <p class="mt-1 text-xs opacity-90">
                                {{
                                    expiryWarning.status === "expired"
                                        ? "Renew the plan to restore plugin access."
                                        : "Renew before expiry to avoid interruption."
                                }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <span>{{ subscriptionUsageSummary(website.subscription) }}</span>
                            <span>{{ subscriptionUsagePercent(website.subscription) }}% used</span>
                        </div>
                        <ProgressBar
                            :value="subscriptionUsagePercent(website.subscription)"
                            :show-value="false"
                            style="height: 0.45rem"
                        />
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-gray-500">Plan price</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">
                                {{ formatSubscriptionPrice(website.subscription) }}
                            </dd>
                        </div>
                        <div v-if="durationLabel">
                            <dt class="text-xs text-gray-500">Duration</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">
                                {{ durationLabel }}
                            </dd>
                        </div>
                        <div v-if="expiryLabel">
                            <dt class="text-xs text-gray-500">Expires</dt>
                            <dd
                                class="font-medium"
                                :class="expiryDateClass"
                            >
                                {{ expiryLabel }}
                            </dd>
                        </div>
                        <div
                            v-if="!isCatalogSubscription(website.subscription) && website.subscription.per_order_rate"
                            class="sm:col-span-2"
                        >
                            <dt class="text-xs text-gray-500">Billing model</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">
                                Pay per order (legacy)
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                v-else
                class="rounded-xl border border-dashed border-gray-200 px-4 py-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300"
            >
                <p class="font-medium text-gray-900 dark:text-white">No subscription yet</p>
                <p class="mt-1 text-xs">
                    Assign a catalog or legacy plan to activate this website.
                </p>
            </section>

            <section class="rounded-xl border border-gray-100 dark:border-gray-800">
                <div
                    class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        License key
                    </p>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ website.licenses?.length ?? 0 }} total
                    </span>
                </div>

                <div v-if="website.licenses?.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="license in website.licenses"
                        :key="license.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ license.title || "License key" }}
                                </span>
                                <StatusBadge
                                    :label="license.status ? 'Active' : 'Disabled'"
                                    :variant="license.status ? 'success' : 'neutral'"
                                    format="none"
                                />
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Last used: {{ license.last_used_ago || "Never" }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center">
                            <Button
                                v-if="license.has_token"
                                v-tooltip.top="'Copy key'"
                                icon="pi pi-copy"
                                size="small"
                                severity="secondary"
                                text
                                rounded
                                :loading="isRevealing(license.id)"
                                @click="$emit('copy-license', license.id)"
                            />
                            <Button
                                v-tooltip.top="'Edit license'"
                                icon="pi pi-pencil"
                                size="small"
                                severity="secondary"
                                text
                                rounded
                                @click="$emit('edit-license', license)"
                            />
                            <Button
                                v-tooltip.top="'Delete license'"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                text
                                rounded
                                @click="$emit('delete-license', license)"
                            />
                        </div>
                    </div>
                </div>
                <p v-else class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                    Generate a license key so the WooCommerce plugin can connect.
                </p>
            </section>

            <div class="flex flex-wrap gap-2">
                <Button
                    :label="primaryAction.label"
                    :icon="primaryAction.icon"
                    size="small"
                    @click="primaryAction.run()"
                />
                <template v-if="website.subscription">
                    <Button
                        v-if="isCatalogSubscription(website.subscription)"
                        label="Renew plan"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        v-tooltip.top="'Reset tokens and extend expiry for a new plan period'"
                        @click="$emit('renew-plan')"
                    />
                    <Button
                        v-else
                        label="Renew via billing"
                        icon="pi pi-credit-card"
                        size="small"
                        severity="secondary"
                        outlined
                        v-tooltip.top="'Legacy plans renew through Billing — add order quota there'"
                        @click="$emit('renew-via-billing')"
                    />
                    <Button
                        label="Change plan"
                        icon="pi pi-sync"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="$emit('change-plan')"
                    />
                    <Button
                        label="Adjust subscription"
                        icon="pi pi-sliders-h"
                        size="small"
                        severity="secondary"
                        outlined
                        v-tooltip.top="'Override quota, expiry, or active status'"
                        @click="$emit('adjust-subscription')"
                    />
                </template>
            </div>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import PageCard from "./PageCard.vue";
import StatusBadge from "./StatusBadge.vue";
import { Icon } from "@/plugins";
import {
    formatSubscriptionExpiry,
    formatSubscriptionPrice,
    healthStatusLabel,
    healthStatusVariant,
    isCatalogSubscription,
    primaryWebsiteIssue,
    subscriptionDurationLabel,
    subscriptionPlanBadge,
    subscriptionUsagePercent,
    subscriptionUsageSummary,
} from "@/utils/websiteSubscription";
import { useSubscriptionExpiryCountdown } from "@/composables/useSubscriptionExpiryCountdown";
import { computed, toRef } from "vue";

const props = defineProps<{
    website: any;
    isRevealing: (id: number) => boolean;
    primaryAction: { label: string; icon: string; run: () => void };
}>();

defineEmits<{
    menu: [event: Event];
    "renew-plan": [];
    "renew-via-billing": [];
    "change-plan": [];
    "adjust-subscription": [];
    "copy-license": [id: number];
    "edit-license": [license: any];
    "delete-license": [license: any];
}>();

const expiryWarning = useSubscriptionExpiryCountdown(
    toRef(() => props.website.subscription?.expires_at),
);

const primaryIssue = computed(() => {
    const issues = props.website.health?.issues ?? [];
    const filtered = expiryWarning.showWarning.value
        ? issues.filter((issue) => !/expir/i.test(issue))
        : issues;

    return primaryWebsiteIssue(filtered);
});

const durationLabel = computed(() =>
    subscriptionDurationLabel(props.website.subscription),
);

const expiryLabel = computed(() =>
    formatSubscriptionExpiry(props.website.subscription?.expires_at),
);

const expiryBannerClass = computed(() => {
    if (expiryWarning.status.value === "expired") {
        return "border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100";
    }

    return "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100";
});

const expiryDateClass = computed(() => {
    if (expiryWarning.status.value === "expired") {
        return "text-rose-600 dark:text-rose-400";
    }

    if (expiryWarning.status.value === "expiring") {
        return "text-amber-600 dark:text-amber-400";
    }

    return "text-gray-900 dark:text-gray-100";
});
</script>
