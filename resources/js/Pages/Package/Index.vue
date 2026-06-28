<template>
    <AuthenticatedLayout title="Pricing Plans">
        <div class="space-y-5">
            <PageHeader
                title="Pricing Plans"
                description="Define subscription pricing merchants can purchase"
                icon="PhPackage"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Button
                        label="New Package"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreateForm"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Total Packages"
                    :value="stats.total"
                    icon="PhPackage"
                />
                <StatCard
                    title="Active Packages"
                    :value="stats.active"
                    icon="PhCheckCircle"
                    subtitle="Available for assignment"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Avg. Tokens"
                    :value="stats.avgTokens"
                    icon="PhCurrencyCircleDollar"
                    subtitle="Across active catalog packages"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
            </div>

            <PageCard
                title="Package Catalog"
                :description="`${filteredPackages.length} package${filteredPackages.length === 1 ? '' : 's'} in catalog`"
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
                            placeholder="Search title or description..."
                            class="w-full"
                        />
                    </IconField>
                    <SelectButton
                        v-model="statusFilter"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                    />
                </div>

                <EmptyState
                    v-if="!filteredPackages.length"
                    title="No packages found"
                    :description="
                        packages.length
                            ? 'Try adjusting your search or filter.'
                            : 'Create your first package to start selling order quotas.'
                    "
                    icon="PhPackage"
                >
                    <Button
                        v-if="!packages.length"
                        label="Create Package"
                        icon="pi pi-plus"
                        @click="openCreateForm"
                    />
                </EmptyState>

                <DataTable
                    v-else
                    :value="filteredPackages"
                    paginator
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column field="title" header="Package">
                        <template #body="{ data }">
                            <div>
                                <p
                                    class="font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ data.title || "Untitled" }}
                                        <span
                                            v-if="data.is_special"
                                            class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"
                                            title="Special package"
                                        >
                                            <i class="pi pi-star-fill text-[9px]" />
                                            Special
                                        </span>
                                    </span>
                                </p>
                                <p
                                    v-if="packageDescriptionPreview(data)"
                                    class="mt-0.5 line-clamp-2 max-w-md text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ packageDescriptionPreview(data) }}
                                </p>
                            </div>
                        </template>
                    </Column>
                    <Column header="Duration">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ formatDuration(data.package_duration) }}
                            </span>
                            <p
                                v-if="data.package_duration === 'free_trial' && data.trial_days"
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ data.trial_days }} days
                            </p>
                        </template>
                    </Column>
                    <Column header="Pricing">
                        <template #body="{ data }">
                            <div v-if="data.package_price != null">
                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ formatPackagePrice(data.package_price) }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    package price
                                </p>
                            </div>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="Tokens">
                        <template #body="{ data }">
                            <div v-if="data.order_rate_token != null">
                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ data.order_rate_token }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    order rate tokens
                                </p>
                            </div>
                            <div v-else-if="data.per_order_rate">
                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ data.per_order_rate }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    TK per order
                                </p>
                            </div>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="App">
                        <template #body="{ data }">
                            <StatusBadge
                                v-if="data.app_connect"
                                label="On"
                                variant="success"
                            />
                            <StatusBadge
                                v-else
                                label="Off"
                                variant="neutral"
                            />
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusBadge
                                v-if="data.deleted_at"
                                label="Deleted"
                                variant="danger"
                            />
                            <StatusBadge
                                v-else
                                :label="data.is_active ? 'Active' : 'Disabled'"
                                :variant="data.is_active ? 'success' : 'neutral'"
                            />
                        </template>
                    </Column>
                    <Column header="Created">
                        <template #body="{ data }">
                            <div>{{ formatDate(data.created_at) }}</div>
                            <div
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ data.creator?.name || "System" }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Updated">
                        <template #body="{ data }">
                            {{ formatDate(data.updated_at) }}
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Button
                                    icon="pi pi-eye"
                                    size="small"
                                    severity="secondary"
                                    text
                                    rounded
                                    v-tooltip.top="'View package'"
                                    @click="openView(data)"
                                />
                                <Button
                                    v-if="isCatalogPackage(data) && !data.deleted_at"
                                    icon="pi pi-pencil"
                                    size="small"
                                    severity="secondary"
                                    text
                                    rounded
                                    v-tooltip.top="'Edit package'"
                                    @click="openEdit(data)"
                                />
                                <Button
                                    v-if="!data.deleted_at"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    text
                                    rounded
                                    v-tooltip.top="'Delete package'"
                                    @click="confirmDelete(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="showForm"
            :header="formMode === 'edit' ? 'Edit Package' : 'Create Package'"
            :style="{ width: '52rem' }"
            draggable
            @hide="onCloseForm"
        >
            <CreateForm
                :draft="packageDraft"
                :package-id="editingPackageId"
                @on-close="onCloseForm"
            />
        </AdminDialog>

        <AdminDialog
            v-model:visible="showView"
            header="Package Overview"
            :style="{ width: '52rem' }"
            maximizable
            draggable
            @hide="onCloseView"
        >
            <PackageViewCard
                v-if="viewingPackage"
                :pkg="viewingPackage"
                @close="onCloseView"
                @edit="openEditFromView"
            />
        </AdminDialog>

        <ConfirmDialog />
        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import {
    buildDefaultPackageDraft,
    buildDraftFromPackageHub,
    isCatalogPackage,
    packageDurationLabel,
} from "@/data/packageCatalogDraft";
import { AuthenticatedLayout } from "@/layouts";
import { router, usePage } from "@inertiajs/vue3";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import { format } from "date-fns";
import { computed, reactive, ref, watch } from "vue";
import CreateForm from "./fragments/CreateForm.vue";
import PackageViewCard from "./fragments/PackageViewCard.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import AdminDialog from "@/Pages/Users/fragments/AdminDialog.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

defineOptions({
    name: "Package",
});

const props = defineProps<{
    packages: any[];
}>();

const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const search = ref("");
const statusFilter = ref("all");
const showForm = ref(false);
const showView = ref(false);
const formMode = ref<"create" | "edit">("create");
const editingPackageId = ref<number | null>(null);
const viewingPackage = ref<Record<string, any> | null>(null);
const packageDraft = reactive(buildDefaultPackageDraft());

const statusOptions = [
    { label: "All", value: "all" },
    { label: "Active", value: "active" },
    { label: "Disabled", value: "disabled" },
];

const stats = computed(() => {
    const list = (props.packages || []).filter((p) => !p.deleted_at);
    const active = list.filter((p) => p.is_active);
    const catalogActive = active.filter((p) => p.order_rate_token != null);
    const avgTokens =
        catalogActive.length > 0
            ? Math.round(
                  catalogActive.reduce(
                      (sum, p) => sum + (Number(p.order_rate_token) || 0),
                      0,
                  ) / catalogActive.length,
              )
            : 0;

    return {
        total: list.length,
        active: active.length,
        avgTokens,
    };
});

const filteredPackages = computed(() => {
    let list = props.packages || [];

    if (statusFilter.value === "active") {
        list = list.filter((p) => p.is_active && !p.deleted_at);
    } else if (statusFilter.value === "disabled") {
        list = list.filter((p) => !p.is_active && !p.deleted_at);
    }

    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return list;
    }

    return list.filter((pkg) => {
        const haystack = [pkg.title, pkg.description]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(keyword);
    });
});

const formatDate = (value?: string | null) => {
    if (!value) {
        return "—";
    }

    return format(new Date(value), "do MMM yyyy, h:mm a");
};

const resetPackageDraft = () => {
    Object.assign(packageDraft, buildDefaultPackageDraft());
};

const openCreateForm = () => {
    formMode.value = "create";
    editingPackageId.value = null;
    resetPackageDraft();
    showForm.value = true;
};

const openEdit = (pkg: Record<string, any>) => {
    formMode.value = "edit";
    editingPackageId.value = pkg.id;
    Object.assign(packageDraft, buildDraftFromPackageHub(pkg));
    showView.value = false;
    showForm.value = true;
};

const openEditFromView = () => {
    if (!viewingPackage.value) {
        return;
    }

    openEdit(viewingPackage.value);
};

const openView = (pkg: Record<string, any>) => {
    viewingPackage.value = pkg;
    showView.value = true;
};

const onCloseView = () => {
    showView.value = false;
    viewingPackage.value = null;
};

const onCloseForm = () => {
    showForm.value = false;
    formMode.value = "create";
    editingPackageId.value = null;
    resetPackageDraft();
};

const confirmDelete = (pkg: Record<string, any>) => {
    const assignedWarning = isCatalogPackage(pkg)
        ? "This catalog package will be soft-deleted and hidden from assignment."
        : "This legacy package will be soft-deleted. Merchants already assigned keep their existing subscriptions.";

    confirm.require({
        header: "Delete package?",
        message: `Delete "${pkg.title || "Untitled"}"? ${assignedWarning}`,
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
            router.post(
                route("packages.delete", pkg.id),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (viewingPackage.value?.id === pkg.id) {
                            onCloseView();
                        }
                    },
                },
            );
        },
    });
};

const formatDuration = (value?: string | null) => packageDurationLabel(value);

const formatPackagePrice = (value?: number | string | null) => {
    if (value == null || value === "") {
        return "—";
    }

    const amount = Number(value);

    return `${amount.toLocaleString("en-BD", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })} TK`;
};

const packageDescriptionPreview = (pkg: { description?: string | null }) => {
    if (!pkg.description?.trim()) {
        return "";
    }

    const text = pkg.description.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();

    return text.length > 120 ? `${text.slice(0, 120)}…` : text;
};

watch(
    () => page.props.flash?.success,
    (message) => {
        if (!message) {
            return;
        }

        toast.add({
            severity: "success",
            summary: "Success",
            detail: String(message),
            life: 4000,
        });
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (!message) {
            return;
        }

        toast.add({
            severity: "error",
            summary: "Action failed",
            detail: String(message),
            life: 6000,
        });
    },
    { immediate: true },
);
</script>
