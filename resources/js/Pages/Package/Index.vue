<template>
    <AuthenticatedLayout title="Package Hub">
        <div class="space-y-5">
            <PageHeader
                title="Package Hub"
                description="Define billing packages merchants can purchase"
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
                    title="Avg. Order Rate"
                    :value="`${stats.avgRate} TK`"
                    icon="PhCurrencyCircleDollar"
                    subtitle="Across active packages"
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
                                    {{ data.title || "Untitled" }}
                                </p>
                                <p
                                    class="mt-0.5 line-clamp-2 max-w-md text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ data.description || "No description" }}
                                </p>
                            </div>
                        </template>
                    </Column>
                    <Column field="per_order_rate" header="Rate">
                        <template #body="{ data }">
                            <span
                                class="inline-flex rounded-md bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                            >
                                {{ data.per_order_rate }} TK
                            </span>
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
                </DataTable>
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="showForm"
            header="Create Package"
            :style="{ width: '40rem' }"
            draggable
            @hide="onClose"
        >
            <CreateForm
                :form="form"
                @on-close="onClose"
                @handle-submit="handleSubmit"
            />
        </AdminDialog>

        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { format } from "date-fns";
import { computed, ref } from "vue";
import CreateForm from "./fragments/CreateForm.vue";
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

const search = ref("");
const statusFilter = ref("all");
const showForm = ref(false);

const statusOptions = [
    { label: "All", value: "all" },
    { label: "Active", value: "active" },
    { label: "Disabled", value: "disabled" },
];

const form = useForm({
    title: "",
    description: "",
    per_order_rate: null as number | null,
    is_active: true,
});

const stats = computed(() => {
    const list = (props.packages || []).filter((p) => !p.deleted_at);
    const active = list.filter((p) => p.is_active);
    const avgRate =
        active.length > 0
            ? (
                  active.reduce(
                      (sum, p) => sum + (Number(p.per_order_rate) || 0),
                      0,
                  ) / active.length
              ).toFixed(2)
            : "0.00";

    return {
        total: list.length,
        active: active.length,
        avgRate,
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

const openCreateForm = () => {
    form.reset();
    form.is_active = true;
    showForm.value = true;
};

const onClose = () => {
    showForm.value = false;
    form.reset();
    form.clearErrors();
};

const handleSubmit = () => {
    form.post(route("packages.create"), {
        onFinish() {
            if (!form.hasErrors) {
                onClose();
            }
        },
    });
};
</script>
