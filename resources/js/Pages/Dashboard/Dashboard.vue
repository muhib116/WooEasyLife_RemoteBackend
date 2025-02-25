<template>
    <AuthenticatedLayout title="Dashboard">
        <div class="mb-4 grid grid-cols-4 gap-4">
            <GroupListBox :data="getData('users')" />
            <GroupListBox :data="getData('tokens')" />
            <div class="space-y-4">
                <Widget
                    title="SMS Balance Of User"
                    :value="`${getData('sms.total_balance')}TK`"
                    rightText="In User Hand"
                />
                <Widget
                    title="SMS Recharge"
                    :value="`${getData('sms.total_sms_recharge')}TK`"
                    rightText="Recharged"
                />
                <Widget
                    title="SMS Sent"
                    :value="`${getData('sms.total_sms_sent')}`"
                    rightText="Sms was sent"
                />
            </div>
        </div>

        <div
            v-if="false"
            class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    Total User
                </p>

                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <h4
                            class="text-2xl font-bold text-gray-800 dark:text-white/90"
                        >
                            {{ getData("user.current") }}
                        </h4>
                    </div>

                    <div class="flex items-center gap-1">
                        <span
                            class="text-theme-xs flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500"
                        >
                            {{ getData("user.percent") }}%
                        </span>

                        <span
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            Vs last month
                        </span>
                    </div>
                </div>
            </div>
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    New User Of This Month
                </p>

                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <h4
                            class="text-2xl font-bold text-gray-800 dark:text-white/90"
                        >
                            {{ getData("user.current") }}
                        </h4>
                    </div>

                    <div class="flex items-center gap-1">
                        <span
                            class="text-theme-xs flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500"
                        >
                            {{ getData("user.percent") }}%
                        </span>

                        <span
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            Vs last month
                        </span>
                    </div>
                </div>
            </div>
            <!-- Metric Item End -->

            <!-- Metric Item Start -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    Total Pageviews
                </p>

                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <h4
                            class="text-2xl font-bold text-gray-800 dark:text-white/90"
                        >
                            55.9K
                        </h4>
                    </div>

                    <div class="flex items-center gap-1">
                        <span
                            class="text-theme-xs flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500"
                        >
                            +4%
                        </span>

                        <span
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            Vs last month
                        </span>
                    </div>
                </div>
            </div>
            <!-- Metric Item End -->

            <!-- Metric Item Start -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    Bounce Rate
                </p>

                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <h4
                            class="text-2xl font-bold text-gray-800 dark:text-white/90"
                        >
                            54%
                        </h4>
                    </div>

                    <div class="flex items-center gap-1">
                        <span
                            class="bg-error-50 text-theme-xs text-error-600 dark:bg-error-500/15 dark:text-error-500 flex items-center gap-1 rounded-full px-2 py-0.5 font-medium"
                        >
                            -1.59%
                        </span>

                        <span
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            Vs last month
                        </span>
                    </div>
                </div>
            </div>
            <!-- Metric Item End -->

            <!-- Metric Item Start -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    Visit Duration
                </p>

                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <h4
                            class="text-2xl font-bold text-gray-800 dark:text-white/90"
                        >
                            2m 56s
                        </h4>
                    </div>

                    <div class="flex items-center gap-1">
                        <span
                            class="text-theme-xs flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500"
                        >
                            +7%
                        </span>

                        <span
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            Vs last month
                        </span>
                    </div>
                </div>
            </div>
            <!-- Metric Item End -->
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import GroupListBox from "./fragments/GroupListBox.vue";
import Widget from "./fragments/Widget.vue";
import { get } from "lodash";

const props = defineProps<{
    data: any;
}>();

const getData = (key: string) => {
    return get(props.data, key);
};
</script>
