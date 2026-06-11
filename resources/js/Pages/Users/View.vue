<template>
    <UserLayout
        title="User Overview"
        section="Overview"
        subtitle="Account summary and quick actions"
        :user="user"
    >
        <div class="space-y-5">
            <PageCard no-padding>
                <div class="p-5 md:p-6">
                    <div
                        class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start"
                    >
                        <div class="flex items-start gap-4">
                            <UserAvatar :name="user?.name" size="lg" />
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="text-xl font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ user?.name }}
                                    </h2>
                                    <StatusBadge
                                        :label="user?.status ? 'Active' : 'Disabled'"
                                        :variant="user?.status ? 'success' : 'danger'"
                                    />
                                    <StatusBadge
                                        v-if="user?.is_test"
                                        label="Test User"
                                        variant="info"
                                    />
                                </div>
                                <p
                                    class="mt-1 text-sm capitalize text-gray-500 dark:text-gray-400"
                                >
                                    {{ user?.role }} account
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="user?.role === 'user'"
                                label="Edit Profile"
                                icon="pi pi-pencil"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="showForm = true"
                            />
                            <Link :href="route('users.apiKeys', user.id)">
                                <Button
                                    label="API Keys"
                                    icon="pi pi-key"
                                    size="small"
                                    as="span"
                                />
                            </Link>
                            <Link :href="route('users.packages', user.id)">
                                <Button
                                    label="Add Package"
                                    icon="pi pi-plus"
                                    size="small"
                                    severity="success"
                                    as="span"
                                />
                            </Link>
                        </div>
                    </div>

                    <div
                        class="mt-6 grid grid-cols-1 gap-3 border-t border-gray-100 pt-6 dark:border-gray-700/80 sm:grid-cols-2"
                    >
                        <div
                            v-for="item in contactItems"
                            :key="item.label"
                            class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-900/40"
                        >
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-white dark:bg-slate-800"
                            >
                                <Icon
                                    :name="item.icon"
                                    class="text-gray-500 dark:text-gray-400"
                                />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-gray-400"
                                >
                                    {{ item.label }}
                                </p>
                                <p
                                    class="truncate text-sm font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{ item.value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </PageCard>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="API Keys"
                    :value="report.active_api_key"
                    icon="PhKey"
                    subtitle="Personal access tokens"
                />
                <StatCard
                    title="SMS Balance"
                    :value="`${Number(report.sms_balance || 0).toFixed(2)} TK`"
                    icon="PhWallet"
                    subtitle="Available SMS credit"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Active Packages"
                    :value="report.active_package"
                    icon="PhPackage"
                    subtitle="Currently enabled"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Orders Remaining"
                    :value="report.remaining_orders"
                    icon="PhShoppingCart"
                    subtitle="Unused order quota"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
            </div>
        </div>

        <UserForm
            v-if="showForm"
            v-model="showForm"
            :selected-user="user"
        />
    </UserLayout>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import type { IconName } from "@/types";
import UserLayout from "./UserLayout.vue";
import PageCard from "./fragments/PageCard.vue";
import StatCard from "./fragments/StatCard.vue";
import StatusBadge from "./fragments/StatusBadge.vue";
import UserAvatar from "./fragments/UserAvatar.vue";
import UserForm from "./fragments/UserForm.vue";

defineOptions({
    name: "UserView",
});

const props = defineProps<{
    user: any;
    report: any;
}>();

const showForm = ref(false);

const contactItems = computed(() => {
    const items: { label: string; value: string; icon: IconName }[] = [];

    if (props.user?.phone) {
        items.push({
            label: "Phone",
            value: props.user.phone,
            icon: "PhPhone",
        });
    }

    if (props.user?.whatsapp_phone) {
        items.push({
            label: "WhatsApp",
            value: props.user.whatsapp_phone,
            icon: "PhWhatsappLogo",
        });
    }

    if (props.user?.email) {
        items.push({
            label: "Email",
            value: props.user.email,
            icon: "PhEnvelope",
        });
    }

    if (props.user?.facebook_page_link) {
        items.push({
            label: "Facebook",
            value: props.user.facebook_page_link,
            icon: "PhFacebookLogo",
        });
    }

    if (!items.length) {
        items.push({
            label: "Contact",
            value: "No contact details added",
            icon: "PhUser",
        });
    }

    return items;
});
</script>
