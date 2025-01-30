<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <UserNav :user="user">
                        <button
                            @click="showForm = true"
                            class="py-1 px-4 bg-indigo-500 text-white flex items-center gap-2"
                        >
                            <span class="pi pi-plus"></span>
                            Generate
                        </button>
                    </UserNav>
                    <div class="pt-4">
                        <DataTable
                            :value="tokens"
                            tableStyle="min-width: 50rem"
                            showGridlines
                        >
                            <Column field="title" header="Title" />
                            <Column
                                field="last_used_ago"
                                header="Last used ago"
                            />
                            <Column field="domain" header="Accessed Domain" />
                            <Column field="expires_at" header="Expires At">
                                <template #body="{ data }">
                                    {{ formatExpiresAt(data?.expires_at) }}
                                </template>
                            </Column>
                            <Column
                                header="Action"
                                headerClass="text-right w-[12rem]"
                            >
                                <template #body="{ data }">
                                    <div class="flex gap-2">
                                        <Button
                                            severity="info"
                                            size="small"
                                            @click="handleCopy(data)"
                                            icon="pi pi-copy"
                                        />
                                        <Button
                                            severity="info"
                                            size="small"
                                            @click="handleEdit(data)"
                                            icon="pi pi-pencil"
                                        />
                                        <Button
                                            severity="danger"
                                            :loading="data?.loading"
                                            size="small"
                                            @click="handleDeleteToken(data)"
                                            icon="pi pi-trash"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </template>
        </Card>
        <Dialog
            v-model:visible="showForm"
            :header="`${tokenForm.id ? 'Edit' : 'Create'} Api Token`"
            modal
            maximizable
            :style="{ width: '35rem' }"
            draggable
            @hide="tokenForm.reset()"
        >

            <TokenForm
                :tokenForm="tokenForm"
                :user_packages="user_packages"
                @onClose="showForm = false"
                @handleSave="handleSave"
            />
        </Dialog>

        <Toast />
        <ConfirmDialog id="confirm" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
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
});

const handleEdit = (item) => {
    tokenForm.id = item.id;
    tokenForm.title = item.title;
    tokenForm.expires_at = item.expires_at;
    tokenForm.tokenable_id = item.tokenable_id;

    const selectedPackage = props.user_packages.find(
        (_item) => (_item.domain == item.domain)
    );
    if (selectedPackage && selectedPackage?.domain) {
        // tokenForm.domain = selectedPackage.domain;
        tokenForm.user_package_id = selectedPackage.id;
    }

    if (item.expires_at) {
        const parsedDate = parseISO(item.expires_at);
        tokenForm.expires_at = parsedDate; // format(parsedDate, "MM/dd/yyyy hh:mm a");
    }
    // MM/dd/yyyy hh:mm a
    tokenForm.abilities = item.abilities;
    tokenForm.description = item.description;
    tokenForm.domain = item.domain;
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
        (item) => item.id == tokenForm.user_package_id
    );
    if (selectedPackage && selectedPackage?.domain) {
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

const handleCopy = (item) => {
    copy(item.bearer_token);
    toast.add({
        severity: "success",
        summary: "Success",
        detail: "Token created successfully",
        life: 3000,
    });
};

const handleDeleteToken = async (item) => {
    confirm.require({
        message: "Are you sure you want to delete this?",
        header: "Confirmation",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "danger",
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            size: "small",
        },
        accept: () => {
            item.loading = true;
            router.post(route("apiKeys.delete", item.id));
        },
        reject: () => {},
    });
};

function formatExpiresAt(expiresAt) {
    if (expiresAt === null) {
        return "No Expiration";
    }

    return format(new Date(expiresAt), "PPp"); // Example: Jan 18, 2025, 12:00 AM
}
</script>
