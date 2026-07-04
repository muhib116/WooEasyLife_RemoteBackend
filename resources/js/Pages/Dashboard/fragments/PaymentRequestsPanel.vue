<template>
    <PageCard no-padding>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-500/15"
                >
                    <Icon
                        name="PhCreditCard"
                        class="text-lg text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        {{ data?.title || "Payment Requests" }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ pendingCount }} pending · {{ data?.summary?.pending_amount ?? "0.00" }} TK awaiting approval
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
                {{ data?.link_text || "Review All" }}
                <Icon name="PhArrowRight" />
            </Link>
        </template>

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <div
                v-for="payment in recent"
                :key="payment.id"
                class="flex items-center gap-4 px-5 py-4 md:px-6"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/15"
                >
                    <Icon name="PhHourglassMedium" class="text-lg text-amber-600 dark:text-amber-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ payment.user_name || "Unknown merchant" }}
                    </p>
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ payment.package_title || "Package" }}
                        · {{ payment.domain || "—" }}
                        · {{ payment.submitted_ago }}
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ payment.total_amount }} TK
                    </p>
                    <p class="text-theme-xs text-amber-600 dark:text-amber-400">
                        Pending
                    </p>
                </div>
            </div>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

type PaymentSummary = {
    total: number;
    pending: number;
    approved: number;
    cancelled: number;
    pending_amount?: string;
};

type PaymentRow = {
    id: number;
    user_name: string | null;
    domain: string | null;
    package_title: string | null;
    total_amount: string;
    submitted_ago: string | null;
};

type PanelData = {
    title?: string;
    link?: string;
    link_text?: string;
    summary?: PaymentSummary;
    recent?: PaymentRow[];
};

const props = defineProps<{
    data?: PanelData;
}>();

const recent = computed(() => props.data?.recent ?? []);
const pendingCount = computed(() => props.data?.summary?.pending ?? 0);
</script>
