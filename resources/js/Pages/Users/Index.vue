<template>
    <AuthenticatedLayout :title="trashed ? 'Trashed Merchants' : 'Merchants'">
        <div class="space-y-5">
            <PageHeader
                :title="trashed ? 'Trashed Merchants' : 'Merchant Management'"
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
                        label="Create Merchant"
                        icon="pi pi-plus"
                        size="small"
                        class="w-full !min-h-11 justify-center sm:w-auto"
                        @click="openCreateForm"
                    />
                </template>
            </PageHeader>

            <div v-if="!trashed" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <StatCard
                    title="Total Accounts"
                    :value="stats.total"
                    icon="PhUsers"
                    compact
                    :subtitle="`${stats.active} enabled`"
                    accent-class="bg-primary-500"
                    icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                    icon-class="text-primary-600 dark:text-primary-400"
                />
                <StatCard
                    title="Active Merchants"
                    :value="stats.active"
                    icon="PhUserCheck"
                    compact
                    :subtitle="activeSubtitle"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <button
                    type="button"
                    class="h-full rounded-2xl text-left"
                    :class="
                        healthFilter === 'attention'
                            ? 'ring-2 ring-rose-400 ring-offset-2 ring-offset-slate-100 dark:ring-offset-slate-950'
                            : ''
                    "
                    @click="toggleAttentionFilter"
                >
                    <StatCard
                        title="Needs Attention"
                        :value="stats.needsAttention"
                        icon="PhWarningCircle"
                        compact
                        subtitle="Tap to filter"
                        accent-class="bg-rose-500"
                        icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                        icon-class="text-rose-600 dark:text-rose-400"
                    />
                </button>
                <StatCard
                    title="Orders Available"
                    :value="formatCount(stats.remainingOrders)"
                    icon="PhCoins"
                    compact
                    subtitle="On active plans"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
            </div>

            <PageCard
                :title="trashed ? 'Trashed Merchants' : 'All Merchants'"
                :description="accountCountLabel"
                no-padding
            >
                <div
                    class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-700/80 sm:px-5 md:px-6"
                >
                    <IconField class="w-full">
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="search"
                            placeholder="Search name, email, phone, or domain..."
                            class="w-full !min-h-11"
                        />
                    </IconField>
                    <div class="merchant-filters flex flex-wrap items-center gap-2">
                        <SelectButton
                            v-model="mode"
                            :options="roleOptions"
                            option-label="label"
                            option-value="value"
                            aria-labelledby="role-filter"
                        />
                        <SelectButton
                            v-if="!trashed"
                            v-model="healthFilter"
                            :options="healthOptions"
                            option-label="label"
                            option-value="value"
                            aria-labelledby="health-filter"
                        />
                    </div>
                </div>

                <div v-if="tableLoading" class="space-y-3 p-4 lg:hidden">
                    <div
                        v-for="row in 4"
                        :key="row"
                        class="h-32 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800"
                    />
                </div>
                <TableSkeletonLoader
                    v-if="tableLoading"
                    class="hidden lg:block"
                    :columns="merchantTableSkeletonColumns"
                    :rows="8"
                />
                <EmptyState
                    v-else-if="!paginatedUsers.length"
                    :title="trashed ? 'No trashed users' : 'No users found'"
                    :description="
                        trashed
                            ? 'Deleted users will appear here.'
                            : 'Try adjusting your search or filter criteria.'
                    "
                    icon="PhUsers"
                />

                <div
                    v-if="!tableLoading && paginatedUsers.length"
                    class="space-y-3 p-3 sm:p-4 lg:hidden"
                >
                    <article
                        v-for="user in paginatedUsers"
                        :key="`card-${user.id}`"
                        class="space-y-3 rounded-2xl border border-gray-100 bg-white p-4 dark:border-gray-800 dark:bg-slate-900/40"
                    >
                        <div class="flex items-start gap-3">
                            <UserAvatar :name="user.name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="!trashed"
                                    :href="route('users.view', user.id)"
                                    class="text-base font-semibold leading-snug text-gray-900 hover:text-primary-600 dark:text-gray-100 dark:hover:text-primary-400"
                                >
                                    {{ user.name }}
                                </Link>
                                <span
                                    v-else
                                    class="text-base font-semibold leading-snug text-gray-900 dark:text-gray-100"
                                >
                                    {{ user.name }}
                                </span>
                                <p class="mt-1 break-all text-sm leading-5 text-gray-600 dark:text-gray-300">
                                    {{ user.email || "No email" }}
                                </p>
                                <p
                                    v-if="user.phone"
                                    class="mt-0.5 text-sm leading-5 text-gray-500 dark:text-gray-400"
                                >
                                    {{ user.phone }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                v-if="!trashed"
                                :label="user.status ? 'Active' : 'Disabled'"
                                :variant="user.status ? 'success' : 'danger'"
                            />
                            <StatusBadge
                                :label="user.role"
                                :variant="user.role === 'admin' ? 'primary' : 'neutral'"
                            />
                            <StatusBadge
                                v-if="user.is_test"
                                label="Test"
                                variant="info"
                            />
                            <StatusBadge
                                v-if="!trashed && user.attention"
                                :label="attentionLabel(user.attention)"
                                :variant="
                                    user.attention.severity === 'danger'
                                        ? 'danger'
                                        : 'warning'
                                "
                                format="none"
                            />
                        </div>

                        <dl
                            v-if="!trashed && user.role === 'user'"
                            class="grid grid-cols-3 gap-2 text-center"
                        >
                            <div class="rounded-xl bg-slate-50 px-2 py-2.5 dark:bg-slate-800/70">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">
                                    Websites
                                </dt>
                                <dd class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ user.websites_count ?? 0 }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-2 py-2.5 dark:bg-slate-800/70">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">
                                    Employees
                                </dt>
                                <dd class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ user.merchant_employees_count ?? 0 }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-2 py-2.5 dark:bg-slate-800/70">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">
                                    Orders left
                                </dt>
                                <dd class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ formatCount(user.remaining_order) }}
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="!trashed && user.role === 'user'"
                            class="break-all text-sm leading-5 text-gray-600 dark:text-gray-300"
                        >
                            {{ domainSummary(user) }}
                        </p>
                        <p
                            v-if="trashed"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            Deleted {{ formatDeletedAt(user.deleted_at) }}
                        </p>

                        <MerchantRowActions
                            :user="user"
                            :trashed="trashed"
                            :current-user-id="currentUserId"
                            :deleting="deletingUserId === user.id"
                            :restoring="restoringUserId === user.id"
                            labeled
                            align="left"
                            @edit="handleEdit(user)"
                            @delete="handleDelete(user)"
                            @restore="handleRestore(user)"
                            @force-delete="handleForceDelete(user)"
                        />
                    </article>
                </div>

                <div
                    v-if="!tableLoading && paginatedUsers.length"
                    class="hidden overflow-x-auto lg:block"
                >
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-100 bg-slate-50/80 dark:border-gray-700 dark:bg-slate-900/40"
                            >
                                <th
                                    class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Merchant
                                </th>
                                <th
                                    v-if="trashed"
                                    class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Deleted
                                </th>
                                <th
                                    v-if="!trashed"
                                    class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Status
                                </th>
                                <th
                                    v-if="!trashed"
                                    class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Store
                                </th>
                                <th
                                    v-if="!trashed"
                                    class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Domain
                                </th>
                                <th
                                    v-if="!trashed"
                                    class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    Attention
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
                                <td class="px-5 py-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <UserAvatar :name="user.name" size="sm" />
                                        <div class="min-w-0">
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
                                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                                {{ user.email || "No email" }}
                                            </p>
                                            <p
                                                v-if="user.phone"
                                                class="text-xs text-gray-400 dark:text-gray-500"
                                            >
                                                {{ user.phone }}
                                            </p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
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
                                    v-if="trashed"
                                    class="px-5 py-4 text-gray-600 dark:text-gray-300"
                                >
                                    {{ formatDeletedAt(user.deleted_at) }}
                                </td>
                                <td v-if="!trashed" class="px-5 py-4">
                                    <StatusBadge
                                        :label="user.status ? 'Active' : 'Disabled'"
                                        :variant="user.status ? 'success' : 'danger'"
                                    />
                                </td>
                                <td v-if="!trashed" class="px-5 py-4">
                                    <div
                                        v-if="user.role === 'user'"
                                        class="space-y-1 text-xs text-gray-600 dark:text-gray-300"
                                    >
                                        <p>{{ user.websites_count ?? 0 }} websites</p>
                                        <p>{{ user.merchant_employees_count ?? 0 }} employees</p>
                                        <p class="font-medium text-gray-800 dark:text-gray-100">
                                            {{ formatCount(user.remaining_order) }} orders left
                                        </p>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-gray-400 dark:text-gray-500"
                                    >
                                        Admin account
                                    </span>
                                </td>
                                <td v-if="!trashed" class="px-5 py-4">
                                    <span
                                        class="block max-w-[14rem] break-all font-mono text-xs text-gray-600 dark:text-gray-300"
                                        :title="domainList(user)"
                                    >
                                        {{ domainSummary(user) }}
                                    </span>
                                </td>
                                <td v-if="!trashed" class="px-5 py-4">
                                    <StatusBadge
                                        v-if="user.attention"
                                        :label="attentionLabel(user.attention)"
                                        :variant="
                                            user.attention.severity === 'danger'
                                                ? 'danger'
                                                : 'warning'
                                        "
                                        format="none"
                                    />
                                    <span
                                        v-else
                                        class="text-xs text-gray-400 dark:text-gray-500"
                                    >
                                        Clear
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <MerchantRowActions
                                        :user="user"
                                        :trashed="trashed"
                                        :current-user-id="currentUserId"
                                        :deleting="deletingUserId === user.id"
                                        :restoring="restoringUserId === user.id"
                                        @edit="handleEdit(user)"
                                        @delete="handleDelete(user)"
                                        @restore="handleRestore(user)"
                                        @force-delete="handleForceDelete(user)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="!tableLoading && filteredUsers.length"
                    class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-4 py-4 text-sm dark:border-gray-700/80 sm:flex-row sm:px-6"
                >
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ paginationLabel }}
                    </span>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <Button
                            icon="pi pi-chevron-left"
                            size="small"
                            severity="secondary"
                            outlined
                            class="!min-h-11 !min-w-11"
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
                            class="!min-h-11 !min-w-11"
                            :disabled="currentPage >= totalPages"
                            @click="currentPage++"
                        />
                        <Select
                            v-model="rowsPerPage"
                            :options="[10, 25, 50, 100, 200, 300]"
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

    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useConfirm } from "primevue";
import { computed, ref, watch } from "vue";
import { watchDebounced } from "@vueuse/core";
import { format, parseISO } from "date-fns";
import UserForm from "./fragments/UserForm.vue";
import PageHeader from "./fragments/PageHeader.vue";
import StatCard from "./fragments/StatCard.vue";
import PageCard from "./fragments/PageCard.vue";
import StatusBadge from "./fragments/StatusBadge.vue";
import EmptyState from "./fragments/EmptyState.vue";
import UserAvatar from "./fragments/UserAvatar.vue";
import MerchantRowActions from "./fragments/MerchantRowActions.vue";
import TableSkeletonLoader from "./fragments/TableSkeletonLoader.vue";

defineOptions({
    name: "Users",
});

const props = withDefaults(
    defineProps<{
        users: any[];
        trashed?: boolean;
        filters?: { search?: string };
        stats?: {
            total: number;
            active: number;
            disabled: number;
            needsAttention: number;
            remainingOrders: number;
        } | null;
    }>(),
    {
        trashed: false,
        filters: () => ({ search: "" }),
        stats: null,
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

const healthOptions = [
    { label: "All health", value: "" },
    { label: "Needs attention", value: "attention" },
];

const mode = ref("");
const healthFilter = ref("");
const search = ref(props.filters?.search ?? "");
const currentPage = ref(1);
const rowsPerPage = ref(25);
const showForm = ref(false);
const selectedUser = ref<any>(null);
const deletingUserId = ref<number | null>(null);
const restoringUserId = ref<number | null>(null);
const tableLoading = ref(false);
let searchRequestId = 0;

const merchantTableSkeletonColumns = [
    { width: "16rem", headerWidth: "5rem", variant: "stack" as const },
    { width: "5rem", headerWidth: "3.5rem", variant: "badge" as const },
    { width: "8rem", headerWidth: "3rem" },
    { width: "9rem", headerWidth: "4rem" },
    { width: "8rem", headerWidth: "5rem" },
    { width: "7rem", headerWidth: "4.5rem", variant: "actions" as const },
];

const stats = computed(() => {
    if (props.stats) {
        return props.stats;
    }

    const merchants = (props.users || []).filter((u) => u.role === "user");

    return {
        total: props.users?.length ?? 0,
        active: merchants.filter((u) => u.status).length,
        disabled: merchants.filter((u) => !u.status).length,
        needsAttention: merchants.filter((u) => u.attention).length,
        remainingOrders: merchants.reduce(
            (sum, u) => sum + (Number(u.remaining_order) || 0),
            0,
        ),
    };
});

const activeSubtitle = computed(() => {
    const disabled = stats.value.disabled ?? 0;

    if (disabled > 0) {
        return `${disabled} disabled`;
    }

    return "None disabled";
});

const toggleAttentionFilter = () => {
    healthFilter.value = healthFilter.value === "attention" ? "" : "attention";
};

const formatCount = (value: string | number | null | undefined) => {
    const parsed = Number.parseFloat(String(value ?? 0).replace(/,/g, ""));

    if (!Number.isFinite(parsed)) {
        return "0";
    }

    return new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 }).format(
        parsed,
    );
};

const filteredUsers = computed(() => {
    let list = props.users || [];

    if (mode.value === "admin") {
        list = list.filter((item) => item?.role === "admin");
    } else if (mode.value === "user") {
        list = list.filter((item) => item?.role === "user");
    }

    if (healthFilter.value === "attention") {
        list = list.filter((item) => Boolean(item?.attention));
    }

    return list;
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredUsers.value.length / rowsPerPage.value)),
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;

    return filteredUsers.value.slice(start, start + rowsPerPage.value);
});

const accountCountLabel = computed(() => {
    const total = filteredUsers.value.length;

    return `${total} ${total === 1 ? "account" : "accounts"} found`;
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

watch([mode, healthFilter, rowsPerPage], () => {
    currentPage.value = 1;
});

watchDebounced(
    search,
    (value) => {
        const next = value.trim();
        const current = (props.filters?.search ?? "").trim();

        if (next === current) {
            return;
        }

        currentPage.value = 1;

        const requestId = ++searchRequestId;

        router.get(
            route(props.trashed ? "users.trashed" : "users.index"),
            next ? { search: next } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ["users", "filters", "stats"],
                onStart: () => {
                    tableLoading.value = true;
                },
                onFinish: () => {
                    if (requestId === searchRequestId) {
                        tableLoading.value = false;
                    }
                },
                onError: () => {
                    if (requestId === searchRequestId) {
                        tableLoading.value = false;
                    }
                },
            },
        );
    },
    { debounce: 400 },
);

const domainSummary = (user: any) => {
    const domains = Array.isArray(user?.domains) ? user.domains : [];

    if (!domains.length) {
        return "No website";
    }

    if (domains.length === 1) {
        return domains[0];
    }

    return `${domains[0]} +${domains.length - 1} more`;
};

const domainList = (user: any) => {
    if (!Array.isArray(user?.domains) || !user.domains.length) {
        return "";
    }

    return user.domains.join(", ");
};

const attentionLabel = (attention: { label?: string; domain?: string | null }) => {
    if (!attention?.label) {
        return "";
    }

    return attention.domain
        ? `${attention.label} · ${attention.domain}`
        : attention.label;
};

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

<style scoped>
.merchant-filters :deep(.p-selectbutton) {
    display: flex;
    width: 100%;
    max-width: 100%;
    flex-wrap: wrap;
}

.merchant-filters :deep(.p-togglebutton) {
    flex: 1 1 auto;
    justify-content: center;
    min-height: 2.75rem;
}

@media (min-width: 640px) {
    .merchant-filters :deep(.p-selectbutton) {
        width: auto;
    }
}
</style>
