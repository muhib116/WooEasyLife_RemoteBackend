<template>
    <UserLayout
        title="API Keys"
        section="API Keys"
        subtitle="Manage personal access tokens for this merchant"
        :user="user"
    >
        <template #actions>
            <Button
                label="Generate Token"
                icon="pi pi-plus"
                size="small"
                @click="showForm = true"
            />
        </template>

        <PageCard
            title="Access Tokens"
            :description="`${tokens.length} token${tokens.length === 1 ? '' : 's'} configured`"
            no-padding
        >
            <DataTable
                :value="tokens"
                paginator
                :rows="10"
                :rows-per-page-options="[10, 25, 50]"
                responsive-layout="scroll"
                class="professional-table text-sm"
            >
                <Column field="title" header="Title" />
                <Column field="last_used_ago" header="Last Used" />
                <Column field="domain" header="Domain">
                    <template #body="{ data }">
                        <span
                            class="inline-block max-w-[220px] break-all rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-gray-700 dark:bg-slate-800 dark:text-gray-300"
                        >
                            {{ data.domain || "—" }}
                        </span>
                    </template>
                </Column>
                <Column field="expires_at" header="Expires At">
                    <template #body="{ data }">
                        {{ formatExpiresAt(data?.expires_at) }}
                    </template>
                </Column>
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <StatusBadge
                            :label="data?.status ? 'Enabled' : 'Disabled'"
                            :variant="data?.status ? 'success' : 'neutral'"
                        />
                    </template>
                </Column>
                <Column header="Actions" header-class="text-right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                severity="secondary"
                                size="small"
                                icon="pi pi-copy"
                                outlined
                                v-tooltip.top="'Copy token'"
                                @click="handleCopy(data)"
                            />
                            <Button
                                severity="secondary"
                                size="small"
                                icon="pi pi-pencil"
                                outlined
                                @click="handleEdit(data)"
                            />
                            <Button
                                severity="danger"
                                size="small"
                                icon="pi pi-trash"
                                outlined
                                :loading="data?.loading"
                                @click="handleDeleteToken(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </PageCard>

        <Dialog
            v-model:visible="showForm"
            :header="`${tokenForm.id ? 'Edit' : 'Create'} API Token`"
            modal
            :style="{ width: '35rem' }"
            draggable
            dismissable-mask
            @hide="tokenForm.reset()"
        >
            <TokenForm
                :token-form="tokenForm"
                :user_packages="user_packages"
                @on-close="showForm = false"
                @handle-save="handleSave"
            />
        </Dialog>

        <Toast />
        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "./UserLayout.vue";
import PageCard from "./fragments/PageCard.vue";
import StatusBadge from "./fragments/StatusBadge.vue";
import TokenForm from "./fragments/TokenForm.vue";
import { format, parseISO } from "date-fns";
import { router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { useToast } from "primevue/usetoast";
import { useClipboard } from "@vueuse/core";
import { useConfirm } from "primevue";

const confirm = useConfirm();
const { copy } = useClipboard();
const toast = useToast();

defineOptions({
    name: "ApiKeys",
});

const props = defineProps<{
    user: any;
    tokens: any[];
    user_packages: any[];
}>();

const showForm = ref(false);
const tokenForm = useForm({
    id: null,
    title: null,
    package: null,
    tokenable_id: null,
    user_package_id: null,
    expires_at: null,
    abilities: null,
    description: null,
    domain: null,
    status: false,
    referred_by: null,
});

const handleEdit = (item: any) => {
    tokenForm.id = item.id;
    tokenForm.title = item.title;
    tokenForm.expires_at = item.expires_at;
    tokenForm.tokenable_id = item.tokenable_id;

    const selectedPackage = props.user_packages?.find(
        (_item) => _item.domain == item.domain,
    );

    if (selectedPackage?.domain) {
        tokenForm.user_package_id = selectedPackage.id;
    }

    if (item.expires_at) {
        tokenForm.expires_at = parseISO(item.expires_at);
    }

    tokenForm.abilities = item.abilities;
    tokenForm.description = item.description;
    tokenForm.domain = item.domain;
    tokenForm.status = Boolean(item?.status);
    showForm.value = true;
};

const handleSave = () => {
    if (!tokenForm.user_package_id) {
        tokenForm.errors.package = "Package is required";
        toast.add({
            severity: "error",
            summary: "Error",
            detail: "No Package Domain Selected",
            life: 3000,
        });
        return;
    }

    const selectedPackage = props.user_packages.find(
        (item) => item.id == tokenForm.user_package_id,
    );

    if (selectedPackage?.domain) {
        tokenForm.domain = selectedPackage.domain;
    }

    if (tokenForm.id) {
        tokenForm.post(route("apiKeys.update", tokenForm.id), {
            onSuccess(e) {
                if (!Object.keys(e.props?.errors || {}).length) {
                    tokenForm.reset();
                    showForm.value = false;
                }
            },
        });
    } else {
        tokenForm.tokenable_id = props.user.id;
        tokenForm.post(route("apiKeys.create"), {
            onSuccess(e) {
                if (!Object.keys(e.props?.errors || {}).length) {
                    tokenForm.reset();
                    showForm.value = false;
                }
            },
        });
    }
};

const handleCopy = (item: any) => {
    copy(item.bearer_token);
    toast.add({
        severity: "success",
        summary: "Copied",
        detail: "Token copied to clipboard",
        life: 3000,
    });
};

const handleDeleteToken = async (item: any) => {
    confirm.require({
        message: "Are you sure you want to delete this token?",
        header: "Delete Token",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            item.loading = true;
            router.post(route("apiKeys.delete", item.id));
        },
    });
};

function formatExpiresAt(expiresAt: string | null) {
    if (expiresAt === null) {
        return "No Expiration";
    }

    return format(new Date(expiresAt), "PPp");
}
</script>
