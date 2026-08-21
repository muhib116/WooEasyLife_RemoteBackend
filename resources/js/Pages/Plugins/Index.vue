<template>
    <AuthenticatedLayout title="Manage Plugins">
        <div class="space-y-5">
            <PageHeader
                title="Plugin Versions"
                description="Publish and manage WooCommerce plugin releases"
                icon="PhPlugsConnected"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Button
                        label="New Version"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreateForm"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Total Versions"
                    :value="stats.total"
                    icon="PhPackage"
                />
                <StatCard
                    title="Total Downloads"
                    :value="stats.downloads"
                    icon="PhCloudArrowDown"
                    subtitle="Across all published versions"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Latest Version"
                    :value="stats.latest || '—'"
                    icon="PhRocketLaunch"
                    :subtitle="
                        stats.latest
                            ? `${stats.latestDownloads} downloads`
                            : 'No versions published yet'
                    "
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
            </div>

            <PageCard
                title="Published Versions"
                :description="`${filteredVersions.length} version${filteredVersions.length === 1 ? '' : 's'} available`"
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
                            placeholder="Search by version..."
                            class="w-full"
                        />
                    </IconField>
                </div>

                <EmptyState
                    v-if="!filteredVersions.length"
                    title="No plugin versions"
                    description="Publish your first plugin ZIP to enable client auto-updates."
                    icon="PhPlugsConnected"
                >
                    <Button
                        label="Create Version"
                        icon="pi pi-plus"
                        @click="openCreateForm"
                    />
                </EmptyState>

                <DataTable
                    v-else
                    :value="filteredVersions"
                    paginator
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column field="version" header="Version">
                        <template #body="{ data, index }">
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ data.version }}
                                </span>
                                <StatusBadge
                                    v-if="index === 0 && !search"
                                    label="Latest"
                                    variant="success"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column field="download_count" header="Downloads">
                        <template #body="{ data }">
                            <span class="font-medium">
                                {{ data.download_count ?? 0 }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Created">
                        <template #body="{ data }">
                            <div>
                                {{ formatDate(data.created_at) }}
                            </div>
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
                    <Column header="Actions" header-class="text-right">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="download"
                                    :href="route('plugins.downloadVersion', data.version)"
                                    download
                                    tooltip="Download ZIP"
                                />
                                <TableActionButton
                                    action="edit"
                                    tooltip="Edit version"
                                    @click="handleEdit(data)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Delete version"
                                    @click="handleDelete(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="showForm"
            :header="form.id ? 'Edit Plugin Version' : 'Publish New Version'"
            :style="{ width: '40rem' }"
            draggable
            @hide="resetForm"
        >
            <VersionForm
                :form="form"
                :file-name="fileName"
                @submit="handleSubmit"
                @cancel="closeForm"
                @file-select="handleFileSelect"
            />
        </AdminDialog>

        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { PluginsVersion } from "@/types";
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { get } from "lodash";
import { format } from "date-fns";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import AdminDialog from "@/Pages/Users/fragments/AdminDialog.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";
import VersionForm from "./fragments/VersionForm.vue";

defineOptions({
    name: "Plugins",
});

const props = defineProps<{
    plugins_link: string;
    versions: PluginsVersion[];
}>();

const confirm = useConfirm();
const toast = useToast();
const search = ref("");
const showForm = ref(false);

const form = useForm({
    id: null as number | null,
    version: "",
    file: null as File | null,
    settings: "" as string,
});

const fileName = computed(() => get(form, "file.name") as string | undefined);

const stats = computed(() => {
    const list = props.versions || [];
    const latest = list[0];

    return {
        total: list.length,
        downloads: list.reduce(
            (sum, item) => sum + (Number(item.download_count) || 0),
            0,
        ),
        latest: latest?.version ?? null,
        latestDownloads: latest?.download_count ?? 0,
    };
});

const filteredVersions = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return props.versions || [];
    }

    return (props.versions || []).filter((item) =>
        String(item.version || "")
            .toLowerCase()
            .includes(keyword),
    );
});

const formatDate = (value?: string | null) => {
    if (!value) {
        return "—";
    }

    return format(new Date(value), "do MMM yyyy, h:mm a");
};

const stringifySettings = (settings: PluginsVersion["settings"]) => {
    if (!settings) {
        return "";
    }

    if (typeof settings === "string") {
        return settings;
    }

    return JSON.stringify(settings, null, 2);
};

const openCreateForm = () => {
    resetForm();
    showForm.value = true;
};

const handleEdit = (item: PluginsVersion) => {
    form.id = item.id;
    form.version = item.version || "";
    form.settings = stringifySettings(item.settings);
    form.file = null;
    showForm.value = true;
};

const handleFileSelect = (event: Event) => {
    const file = get(event, "target.files[0]") as File | undefined;

    if (file) {
        form.file = file;
        form.errors.file = "";
    }
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
};

const closeForm = () => {
    resetForm();
    showForm.value = false;
};

const handleDelete = (item: PluginsVersion) => {
    confirm.require({
        header: "Delete version?",
        message: `This will remove version ${item.version} from the release list.`,
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
            router.post(route("plugins.deleteVersion", item.id), {
                onSuccess: () => {
                    toast.add({
                        severity: "success",
                        summary: "Deleted",
                        detail: "Plugin version removed successfully",
                        life: 3000,
                    });
                },
            });
        },
    });
};

const handleSubmit = () => {
    const isUpdate = Boolean(form.id);
    const url = isUpdate
        ? route("plugins.updateVersion", form.id)
        : route("plugins.createVersion");

    form.transform((data) => {
        const payload: Record<string, unknown> = {
            version: data.version,
            settings: data.settings,
        };

        if (data.file) {
            payload.file = data.file;
        }

        return payload;
    }).post(url, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess() {
            if (Object.keys(form.errors).length) {
                return;
            }

            toast.add({
                severity: "success",
                summary: isUpdate ? "Updated" : "Published",
                detail: isUpdate
                    ? "Plugin version updated successfully"
                    : "Plugin version published successfully",
                life: 3000,
            });
            closeForm();
        },
    });
};
</script>
