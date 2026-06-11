<template>
    <AuthenticatedLayout title="API Keys">
        <div class="space-y-5">
            <div
                class="box-bg box-color box-border rounded-2xl border px-5 py-4 shadow-sm md:px-6"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15"
                    >
                        <Icon
                            name="PhLockKeyOpen"
                            class="text-2xl text-primary-600 dark:text-primary-400"
                        />
                    </div>
                    <div>
                        <h1
                            class="text-xl font-semibold text-gray-900 dark:text-white"
                        >
                            API Keys
                        </h1>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            Manage bearer tokens and domain access for merchants
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Merchants"
                    :value="stats.merchants"
                    icon="PhUsers"
                    subtitle="User accounts with API access"
                />
                <StatCard
                    title="Total Tokens"
                    :value="stats.totalTokens"
                    icon="PhKey"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Expired Tokens"
                    :value="stats.expiredTokens"
                    icon="PhClockCountdown"
                    subtitle="Past expiry date"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                />
            </div>

            <PageCard
                title="Merchants & Tokens"
                :description="`${filteredUsers.length} merchant${filteredUsers.length === 1 ? '' : 's'}`"
                no-padding
            >
                <div
                    class="border-b border-gray-100 px-5 py-4 dark:border-gray-700/80 md:px-6"
                >
                    <IconField class="w-full md:max-w-sm">
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="search"
                            placeholder="Search name, email, phone..."
                            class="w-full"
                        />
                    </IconField>
                </div>

                <EmptyState
                    v-if="!filteredUsers.length"
                    title="No merchants found"
                    description="Try a different search term."
                    icon="PhUsers"
                />

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-100 bg-slate-50/80 dark:border-gray-700 dark:bg-slate-900/40"
                            >
                                <th
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Merchant
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Contact
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Tokens
                                </th>
                                <th
                                    class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="user in filteredUsers"
                                :key="user.id"
                                class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <UserAvatar
                                            :name="user.name"
                                            size="sm"
                                        />
                                        <div>
                                            <Link
                                                :href="route('users.view', user.id)"
                                                class="font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400"
                                            >
                                                {{ user.name }}
                                            </Link>
                                            <p
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                ID #{{ user.id }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-gray-700 dark:text-gray-300">
                                        {{ user.phone || "—" }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ user.email || "No email" }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <StatusBadge
                                        v-if="user.tokens?.length"
                                        :label="`${user.tokens.length} token${user.tokens.length === 1 ? '' : 's'}`"
                                        variant="info"
                                    />
                                    <span
                                        v-else
                                        class="text-sm text-gray-400"
                                    >
                                        None
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            label="Manage Keys"
                                            icon="pi pi-key"
                                            size="small"
                                            @click="showDetails(user)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="selectedUser"
            modal
            maximizable
            :style="{ width: '100%', maxWidth: '72rem' }"
            draggable
            dismissable-mask
        >
            <template #header>
                <div
                    class="flex w-full flex-col gap-3 pr-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <UserAvatar
                            v-if="selectedUser?.name"
                            :name="selectedUser.name"
                        />
                        <div>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                API tokens for
                            </p>
                            <p
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                {{ selectedUser?.name }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="selectedUser"
                            :href="route('users.apiKeys', selectedUser.id)"
                        >
                            <Button
                                label="Open in User"
                                icon="pi pi-external-link"
                                size="small"
                                severity="secondary"
                                outlined
                                as="span"
                            />
                        </Link>
                        <Button
                            label="Generate Token"
                            icon="pi pi-plus"
                            size="small"
                            @click="openCreateToken"
                        />
                    </div>
                </div>
            </template>

            <Details
                v-if="selectedUser"
                :user="selectedUser"
                @handle-copy="handleCopy"
                @handle-edit="handleEdit"
                @handle-delete-token="handleDeleteToken"
            />
        </Dialog>

        <Dialog
            v-model:visible="showForm"
            :header="tokenForm.id ? 'Edit API Token' : 'Generate API Token'"
            modal
            :style="{ width: '40rem' }"
            draggable
            dismissable-mask
            @hide="tokenForm.reset()"
        >
            <TokenForm
                :token-form="tokenForm"
                @on-close="showForm = false"
                @handle-save="handleSave"
            />
        </Dialog>

        <ConfirmDialog />
        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import { Link, router, useForm } from "@inertiajs/vue3";
import { parseISO, isPast } from "date-fns";
import { computed, ref } from "vue";
import { useClipboard } from "@vueuse/core";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue";
import Details from "./Details.vue";
import TokenForm from "./TokenForm.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import UserAvatar from "@/Pages/Users/fragments/UserAvatar.vue";

const { copy } = useClipboard();
const toast = useToast();
const confirm = useConfirm();

defineOptions({
    name: "ApiKeys",
});

const props = defineProps<{
    users: any[];
}>();

const search = ref("");
const showForm = ref(false);
const selectedUser = ref<any>(null);

const tokenForm = useForm({
    id: null as number | null,
    title: null as string | null,
    tokenable_id: null as number | null,
    expires_at: null as Date | null,
    abilities: null,
    description: null as string | null,
    domain: null as string | null,
    status: true,
});

const stats = computed(() => {
    const users = props.users || [];
    let totalTokens = 0;
    let expiredTokens = 0;

    users.forEach((user) => {
        const tokens = user.tokens || [];
        totalTokens += tokens.length;

        tokens.forEach((token: any) => {
            if (token.expires_at) {
                try {
                    const date = parseISO(token.expires_at);
                    if (isPast(date)) {
                        expiredTokens += 1;
                    }
                } catch {
                    if (isPast(new Date(token.expires_at))) {
                        expiredTokens += 1;
                    }
                }
            }
        });
    });

    return {
        merchants: users.length,
        totalTokens,
        expiredTokens,
    };
});

const filteredUsers = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return props.users || [];
    }

    return (props.users || []).filter((user) => {
        const haystack = [user.name, user.email, user.phone]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(keyword);
    });
});

const showDetails = (user: any) => {
    selectedUser.value = user;
};

const openCreateToken = () => {
    tokenForm.reset();
    tokenForm.status = true;
    showForm.value = true;
};

const reFindSelectedUser = () => {
    if (!selectedUser.value) {
        return;
    }

    const user = props.users?.find(
        (item) => item.id === selectedUser.value.id,
    );

    if (user) {
        selectedUser.value = user;
    }
};

const handleEdit = (item: any) => {
    tokenForm.id = item.id;
    tokenForm.title = item.title;
    tokenForm.expires_at = item.expires_at;
    tokenForm.tokenable_id = item.tokenable_id;
    tokenForm.abilities = item.abilities;
    tokenForm.description = item.description;
    tokenForm.domain = item.domain;
    tokenForm.status = Boolean(item.status);

    if (item.expires_at) {
        tokenForm.expires_at = parseISO(item.expires_at);
    }

    showForm.value = true;
};

const handleSave = () => {
    if (!selectedUser.value) {
        return;
    }

    if (tokenForm.id) {
        tokenForm.post(route("apiKeys.update", tokenForm.id), {
            onSuccess(e) {
                if (!Object.keys(e.props?.errors || {}).length) {
                    tokenForm.reset();
                    showForm.value = false;
                }
                reFindSelectedUser();
            },
        });
    } else {
        tokenForm.tokenable_id = selectedUser.value.id;
        tokenForm.post(route("apiKeys.create"), {
            onSuccess(e) {
                if (!Object.keys(e.props?.errors || {}).length) {
                    tokenForm.reset();
                    showForm.value = false;
                }
                reFindSelectedUser();
            },
        });
    }
};

const handleCopy = (item: any) => {
    copy(item.bearer_token);
    toast.add({
        severity: "success",
        summary: "Copied",
        detail: "Bearer token copied to clipboard",
        life: 3000,
    });
};

const handleDeleteToken = (item: any) => {
    confirm.require({
        header: "Delete token?",
        message: "This token will be permanently revoked.",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
        },
        accept: () => {
            item.loading = true;
            router.post(route("apiKeys.delete", item.id), {
                onSuccess: () => {
                    toast.add({
                        severity: "success",
                        summary: "Deleted",
                        detail: "API token removed successfully",
                        life: 3000,
                    });
                    reFindSelectedUser();
                },
                onFinish: () => {
                    item.loading = false;
                },
            });
        },
    });
};
</script>
