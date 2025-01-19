<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex justify-between items-center gap-5">
                    Api Tokens
                    <Button
                        label="Generate Token"
                        icon="pi pi-plus"
                        size="small"
                        @click="showForm = true"
                    />
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <DataTable :value="tokens" tableStyle="min-width: 50rem">
                        <Column field="name" header="Name" />
                        <Column field="description" header="Description" />
                        <!-- <Column field="abilities" header="Abilities" /> -->
                        <Column field="last_used_ago" header="Last used ago" />
                        <Column field="domain" header="Accessed Domain" />
                        <Column field="expires_at" header="Expires At">
                            <template #body="{ data }">
                                {{ formatExpiresAt(data?.expires_at) }}
                            </template>
                        </Column>
                        <Column header="Action" headerClass="text-right">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Button
                                        severity="primary"
                                        size="small"
                                        @click="() => handleCopy(data)"
                                        icon="pi pi-copy"
                                    />
                                    <Button
                                        severity="info"
                                        size="small"
                                        @click="() => handleEdit(data)"
                                        icon="pi pi-pencil"
                                    />
                                    <Button
                                        severity="danger"
                                        :loading="data?.loading"
                                        size="small"
                                        @click="() => handleDeleteToken(data)"
                                        icon="pi pi-trash"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>
        <Dialog
            v-model:visible="showForm"
            :header="`${tokenForm.id ? 'Edit' : 'Create'} Api Token`"
            modal
            maximizable
            :style="{ width: '35rem' }"
            :draggable="true"
        >
            <form @submit.prevent="handleSave">
                <div class="flex flex-col gap-1 mb-2">
                    <div for="version" class="font-semibold w-24">
                        Description
                    </div>
                    <div class="flex-auto relative">
                        <Textarea
                            v-model="tokenForm.description"
                            placeholder="Description"
                            class="w-full"
                            autoResize
                            rows="3"
                        />
                        <span
                            v-if="tokenForm.errors.description"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ tokenForm.errors.description }}</span
                        >
                    </div>
                </div>
                <div class="flex flex-col gap-1 mb-4">
                    <div for="domain" class="font-semibold w-24">Domain</div>
                    <div class="flex-auto relative">
                        <InputText
                            v-model="tokenForm.domain"
                            id="domain"
                            placeholder="domain"
                            class="!w-full"
                        />
                        <span
                            v-if="tokenForm.errors.domain"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ tokenForm.errors.domain }}</span
                        >
                    </div>
                </div>
                <div class="flex flex-col gap-1 mb-4">
                    <div for="expires_at" class="font-semibold w-24">
                        Expires At
                    </div>
                    <div class="flex-auto relative">
                        <div class="flex-auto">
                            <DatePicker
                                id="datepicker-12h"
                                v-model="tokenForm.expires_at"
                                showTime
                                hourFormat="12"
                                dateFormat="dd/mm/yy"
                                fluid
                            />
                        </div>
                        <span
                            v-if="tokenForm.errors.expires_at"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ tokenForm.errors.expires_at }}</span
                        >
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        @click="showForm = false"
                    ></Button>
                    <Button
                        type="submit"
                        :label="tokenForm.id ? 'Update' : 'Create'"
                        :loading="tokenForm.processing"
                        @click="handleSave"
                    ></Button>
                </div>
            </form>
        </Dialog>
        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { router, useForm } from "@inertiajs/vue3";
import { parseISO, formatISO, format } from "date-fns";
import { ref } from "vue";
import axios from "axios";
import { useClipboard } from "@vueuse/core";
import { set } from "lodash";
import { useToast } from "primevue/usetoast";

const { text, copy, copied, isSupported } = useClipboard();
const toast = useToast();

defineOptions({
    name: "FraudCheck",
});

defineProps<{
    tokens: any[];
}>();

const showForm = ref(false);
const isLoading = ref(false);
const response = ref();

const tokenForm = useForm({
    id: null,
    expires_at: null,
    abilities: null,
    description: null,
    domain: null,
});

const handleEdit = (item) => {
    tokenForm.id = item.id;
    tokenForm.expires_at = item.expires_at;
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

function formatExpiresAt(expiresAt) {
    if (expiresAt === null) {
        return "No Expiration";
    }

    return format(new Date(expiresAt), "PPp"); // Example: Jan 18, 2025, 12:00 AM
}

const form = useForm({
    phone: "",
});

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
    if (!confirm("Are you sure?")) return;
    const { data } = await axios.post(route("apiKeys.delete"), {
        tokenId: item.id,
    });
};

const handleGenerate = async () => {
    form.post(route("apiKeys.create"));
};
</script>
