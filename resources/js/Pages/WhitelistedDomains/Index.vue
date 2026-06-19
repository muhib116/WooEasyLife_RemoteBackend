<template>
    <AuthenticatedLayout title="Whitelisted Domains">
        <div class="space-y-5">
            <PageHeader
                title="Whitelisted Domains"
                description="Only these domains can call the fraud check API"
                icon="PhGlobe"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            >
                <template #actions>
                    <Button
                        label="Add Domain"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Total Domains"
                    :value="domains.length"
                    icon="PhGlobe"
                />
                <StatCard
                    title="Active"
                    :value="activeCount"
                    icon="PhCheckCircle"
                    subtitle="Allowed to call fraud API"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Inactive"
                    :value="inactiveCount"
                    icon="PhProhibit"
                    subtitle="Blocked from fraud API"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                />
            </div>

            <PageCard
                title="Domain Whitelist"
                :description="`${domains.length} domain${domains.length === 1 ? '' : 's'} configured`"
                no-padding
            >
                <DataTable
                    v-if="domains.length"
                    :value="domains"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 25, 50]"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="domain" header="Domain" style="min-width: 12rem">
                        <template #body="{ data }">
                            <span class="font-medium">{{ data.domain }}</span>
                        </template>
                    </Column>
                    <Column field="notes" header="Notes" style="min-width: 14rem">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-600 dark:text-gray-300">
                                {{ data.notes || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.is_active ? 'Active' : 'Inactive'"
                                :severity="data.is_active ? 'success' : 'secondary'"
                            />
                        </template>
                    </Column>
                    <Column header="Actions" header-class="text-right" headerStyle="width:9rem">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="edit"
                                    tooltip="Edit domain"
                                    @click="openEdit(data)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Remove domain"
                                    @click="confirmRemove(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhGlobe"
                    title="No whitelisted domains"
                    description="Add a domain to allow fraud check API access from that site"
                >
                    <Button
                        label="Add Domain"
                        icon="pi pi-plus"
                        size="small"
                        class="mt-4"
                        @click="openCreate"
                    />
                </EmptyState>
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="showForm"
            :header="editing ? 'Edit Domain' : 'Add Domain'"
            :style="{ width: '32rem' }"
            @hide="resetForm"
        >
            <form class="space-y-5 p-1" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Domain
                    </label>
                    <InputText
                        v-model="form.domain"
                        class="w-full"
                        placeholder="example.com"
                    />
                    <small v-if="form.errors.domain" class="mt-1 block text-rose-500">
                        {{ form.errors.domain }}
                    </small>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Notes
                    </label>
                    <Textarea v-model="form.notes" class="w-full" rows="3" />
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox v-model="form.is_active" binary inputId="is_active" />
                    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-200">
                        Active
                    </label>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2 mt-3">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        text
                        @click="showForm = false"
                    />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        severity="info"
                        :loading="form.processing"
                        @click="submit"
                    />
                </div>
            </template>
        </AdminDialog>

        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import AdminDialog from "@/Pages/Users/fragments/AdminDialog.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineOptions({ name: "WhitelistedDomainsIndex" });

const props = defineProps<{
    domains: Array<{
        id: number;
        domain: string;
        notes: string | null;
        is_active: boolean;
    }>;
}>();

const confirm = useConfirm();
const domains = computed(() => props.domains);
const activeCount = computed(() => domains.value.filter((item) => item.is_active).length);
const inactiveCount = computed(() => domains.value.length - activeCount.value);
const showForm = ref(false);
const editing = ref<{ id: number } | null>(null);

const form = useForm({
    domain: "",
    notes: "",
    is_active: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.is_active = true;
    editing.value = null;
};

const openCreate = () => {
    resetForm();
    showForm.value = true;
};

const openEdit = (item: (typeof props.domains)[number]) => {
    editing.value = { id: item.id };
    form.domain = item.domain;
    form.notes = item.notes || "";
    form.is_active = item.is_active;
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route("whitelistedDomains.update", editing.value.id), {
            onSuccess: () => {
                showForm.value = false;
                resetForm();
            },
        });
        return;
    }

    form.post(route("whitelistedDomains.store"), {
        onSuccess: () => {
            showForm.value = false;
            resetForm();
        },
    });
};

const confirmRemove = (item: (typeof props.domains)[number]) => {
    confirm.require({
        header: "Remove domain?",
        message: `Remove ${item.domain} from the whitelist? Fraud checks from this domain will be blocked.`,
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Cancel",
        acceptLabel: "Remove",
        acceptClass: "p-button-danger",
        accept: () => {
            useForm({}).delete(route("whitelistedDomains.destroy", item.id));
        },
    });
};
</script>
