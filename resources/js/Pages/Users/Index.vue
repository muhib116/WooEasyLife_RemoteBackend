<template>
    <AuthenticatedLayout :title="trashed ? 'Trashed Users' : 'Users'">
        <div class="space-y-5">
            <PageHeader
                :title="trashed ? 'Trashed Users' : 'User Management'"
                :description="
                    trashed
                        ? 'Restore deleted accounts or remove them permanently'
                        : 'Manage merchant accounts, access, and billing'
                "
                icon="PhUsers"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Button
                        v-if="!trashed"
                        label="Create User"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreateForm"
                    />
                </template>
            </PageHeader>

            <div v-if="!trashed" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Total Accounts"
                    :value="stats.total"
                    icon="PhUsers"
                    accent-class="bg-primary-500"
                    icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                    icon-class="text-primary-600 dark:text-primary-400"
                />
                <StatCard
                    title="Active Users"
                    :value="stats.active"
                    icon="PhUserCheck"
                    subtitle="Merchant accounts enabled"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Orders Available"
                    :value="stats.remainingOrders"
                    icon="PhCoins"
                    subtitle="Across all active packages"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
            </div>

            <PageCard
                :title="trashed ? 'Trashed Users' : 'All Users'"
                :description="`${filteredUsers.length} accounts found`"
                no-padding
            >
                <div
                    class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700/80 md:flex-row md:items-center md:justify-between md:px-6"
                >
                    <IconField class="w-full md:max-w-sm">
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="search"
                            placeholder="Search by name, email, or phone..."
                            class="w-full"
                        />
                    </IconField>
                    <SelectButton
                        v-model="mode"
                        :options="roleOptions"
                        option-label="label"
                        option-value="value"
                        aria-labelledby="role-filter"
                    />
                </div>

                <EmptyState
                    v-if="!paginatedUsers.length"
                    :title="trashed ? 'No trashed users' : 'No users found'"
                    :description="
                        trashed
                            ? 'Deleted users will appear here.'
                            : 'Try adjusting your search or filter criteria.'
                    "
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
                                    User
                                </th>
                                <th
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Phone
                                </th>
                                <th
                                    v-if="trashed"
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Deleted At
                                </th>
                                <th
                                    v-if="!trashed"
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Status
                                </th>
                                <th
                                    v-if="!trashed"
                                    class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Orders Left
                                </th>
                                <th
                                    class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="user in paginatedUsers"
                                :key="user.id"
                                class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <UserAvatar :name="user.name" size="sm" />
                                        <div>
                                            <Link
                                                v-if="!trashed"
                                                :href="route('users.view', user.id)"
                                                class="font-medium text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400"
                                            >
                                                {{ user.name }}
                                            </Link>
                                            <span
                                                v-else
                                                class="font-medium text-gray-900 dark:text-gray-100"
                                            >
                                                {{ user.name }}
                                            </span>
                                            <div
                                                class="mt-1 flex flex-wrap items-center gap-1.5"
                                            >
                                                <StatusBadge
                                                    :label="user.role"
                                                    :variant="
                                                        user.role === 'admin'
                                                            ? 'primary'
                                                            : 'neutral'
                                                    "
                                                />
                                                <StatusBadge
                                                    v-if="user.is_test"
                                                    label="Test"
                                                    variant="info"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-gray-600 dark:text-gray-300"
                                >
                                    {{ user.phone || "—" }}
                                </td>
                                <td
                                    v-if="trashed"
                                    class="px-6 py-4 text-gray-600 dark:text-gray-300"
                                >
                                    {{ formatDeletedAt(user.deleted_at) }}
                                </td>
                                <td v-if="!trashed" class="px-6 py-4">
                                    <StatusBadge
                                        :label="user.status ? 'Active' : 'Disabled'"
                                        :variant="user.status ? 'success' : 'danger'"
                                    />
                                </td>
                                <td
                                    v-if="!trashed"
                                    class="px-6 py-4 font-semibold text-gray-800 dark:text-gray-200"
                                >
                                    {{ user.remaining_order ?? 0 }}
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <template v-if="trashed">
                                            <Button
                                                label="Restore"
                                                icon="pi pi-replay"
                                                size="small"
                                                severity="success"
                                                outlined
                                                :loading="restoringUserId === user.id"
                                                @click="handleRestore(user)"
                                            />
                                            <Button
                                                label="Delete Forever"
                                                icon="pi pi-trash"
                                                size="small"
                                                severity="danger"
                                                outlined
                                                :loading="deletingUserId === user.id"
                                                :disabled="
                                                    Number(user.id) ===
                                                    Number(currentUserId)
                                                "
                                                @click="handleForceDelete(user)"
                                            />
                                        </template>
                                        <template v-else>
                                            <Button
                                                v-if="user.role === 'user'"
                                                icon="pi pi-pencil"
                                                size="small"
                                                severity="secondary"
                                                outlined
                                                v-tooltip.top="'Edit user'"
                                                @click="handleEdit(user)"
                                            />
                                            <Button
                                                label="Delete"
                                                icon="pi pi-trash"
                                                size="small"
                                                severity="danger"
                                                outlined
                                                :loading="deletingUserId === user.id"
                                                :disabled="
                                                    Number(user.id) ===
                                                    Number(currentUserId)
                                                "
                                                @click="handleDelete(user)"
                                            />
                                            <Link
                                                :href="route('users.view', user.id)"
                                            >
                                                <Button
                                                    label="View"
                                                    size="small"
                                                    icon="pi pi-arrow-right"
                                                    icon-pos="right"
                                                    as="span"
                                                />
                                            </Link>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="filteredUsers.length"
                    class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 text-sm dark:border-gray-700/80 sm:flex-row"
                >
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ paginationLabel }}
                    </span>
                    <div class="flex items-center gap-2">
                        <Button
                            icon="pi pi-chevron-left"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="currentPage <= 1"
                            @click="currentPage--"
                        />
                        <span
                            class="min-w-[110px] text-center text-gray-700 dark:text-gray-300"
                        >
                            Page {{ currentPage }} of {{ totalPages }}
                        </span>
                        <Button
                            icon="pi pi-chevron-right"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="currentPage >= totalPages"
                            @click="currentPage++"
                        />
                        <Select
                            v-model="rowsPerPage"
                            :options="[10, 25, 50]"
                            class="w-20"
                        />
                    </div>
                </div>
            </PageCard>
        </div>

        <UserForm
            v-if="showForm"
            v-model="showForm"
            @update:model-value="!showForm && (selectedUser = null)"
            :selected-user="selectedUser"
        />

        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useConfirm } from "primevue";
import { computed, ref, watch } from "vue";
import { format, parseISO } from "date-fns";
import UserForm from "./fragments/UserForm.vue";
import PageHeader from "./fragments/PageHeader.vue";
import StatCard from "./fragments/StatCard.vue";
import PageCard from "./fragments/PageCard.vue";
import StatusBadge from "./fragments/StatusBadge.vue";
import EmptyState from "./fragments/EmptyState.vue";
import UserAvatar from "./fragments/UserAvatar.vue";

defineOptions({
    name: "Users",
});

const props = withDefaults(
    defineProps<{
        users: any[];
        trashed?: boolean;
    }>(),
    {
        trashed: false,
    },
);

const confirm = useConfirm();
const page = usePage();

const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const roleOptions = [
    { label: "All", value: "" },
    { label: "Users", value: "user" },
    { label: "Admins", value: "admin" },
];

const mode = ref("");
const search = ref("");
const currentPage = ref(1);
const rowsPerPage = ref(10);
const showForm = ref(false);
const selectedUser = ref<any>(null);
const deletingUserId = ref<number | null>(null);
const restoringUserId = ref<number | null>(null);

const stats = computed(() => {
    const merchants = (props.users || []).filter((u) => u.role === "user");

    return {
        total: props.users?.length ?? 0,
        active: merchants.filter((u) => u.status).length,
        remainingOrders: merchants.reduce(
            (sum, u) => sum + (Number(u.remaining_order) || 0),
            0,
        ),
    };
});

const filteredUsers = computed(() => {
    let list = props.users || [];

    if (mode.value === "admin") {
        list = list.filter((item) => item?.role === "admin");
    } else if (mode.value === "user") {
        list = list.filter((item) => item?.role === "user");
    }

    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return list;
    }

    return list.filter((user) => {
        const haystack = [user.name, user.email, user.phone]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(keyword);
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredUsers.value.length / rowsPerPage.value)),
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;

    return filteredUsers.value.slice(start, start + rowsPerPage.value);
});

const paginationLabel = computed(() => {
    const total = filteredUsers.value.length;

    if (!total) {
        return "0 users";
    }

    const start = (currentPage.value - 1) * rowsPerPage.value + 1;
    const end = Math.min(currentPage.value * rowsPerPage.value, total);

    return `Showing ${start}–${end} of ${total}`;
});

watch([mode, search, rowsPerPage], () => {
    currentPage.value = 1;
});

const formatDeletedAt = (value?: string | null) => {
    if (!value) {
        return "—";
    }

    try {
        return format(parseISO(value), "d MMM yyyy, h:mm a");
    } catch {
        return value;
    }
};

const openCreateForm = () => {
    selectedUser.value = null;
    showForm.value = true;
};

const handleEdit = (user: any) => {
    selectedUser.value = user;
    showForm.value = true;
};

const handleDelete = (user: any) => {
    if (Number(user.id) === Number(currentUserId.value)) {
        return;
    }

    confirm.require({
        header: "Move user to trash?",
        message: `${user.name} will be moved to trash. You can restore the account later from Trashed Users.`,
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Move to Trash",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            deletingUserId.value = user.id;
            router.delete(route("users.destroy", user.id), {
                onFinish: () => {
                    deletingUserId.value = null;
                },
            });
        },
    });
};

const handleRestore = (user: any) => {
    confirm.require({
        header: "Restore user?",
        message: `Restore ${user.name} and bring the account back to All Users.`,
        icon: "pi pi-replay",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Restore",
            severity: "success",
            size: "small",
        },
        accept: () => {
            restoringUserId.value = user.id;
            router.post(route("users.restore", user.id), {}, {
                onFinish: () => {
                    restoringUserId.value = null;
                },
            });
        },
    });
};

const handleForceDelete = (user: any) => {
    if (Number(user.id) === Number(currentUserId.value)) {
        return;
    }

    confirm.require({
        header: "Permanently delete user?",
        message: `This will permanently delete ${user.name} and all related packages, API keys, and SMS records. This cannot be undone.`,
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete Forever",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            deletingUserId.value = user.id;
            router.delete(route("users.forceDestroy", user.id), {
                onFinish: () => {
                    deletingUserId.value = null;
                },
            });
        },
    });
};
</script>
