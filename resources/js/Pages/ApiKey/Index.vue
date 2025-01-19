<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex justify-between items-center gap-5">
                    User list
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <DataTable :value="users" tableStyle="min-width: 50rem">
                        <Column field="name" header="Name" />
                        <Column field="email" header="Email" />
                        <Column header="Token Length">
                            <template #body="{ data }">
                                <div v-if="data?.tokens?.length">
                                    <span
                                        class="text-purple-800pr-2 font-bold"
                                        >{{ data?.tokens?.length }}</span
                                    >
                                </div>
                                <span v-else> No token available </span>
                            </template>
                        </Column>
                        <Column
                            header="Action"
                            headerClass="text-right w-[12rem]"
                        >
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Button
                                        severity="help"
                                        size="small"
                                        @click="() => showDetails(data)"
                                        icon="pi pi-eye"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="selectedUser"
            modal
            maximizable
            :style="{ width: '100%', maxWidth: '80rem' }"
            :draggable="true"
        >
            <template #header>
                <div class="flex items-center justify-between flex-1 pr-5">
                    <div>
                        Api Keys of
                        <span class="font-bold text-purple-500">{{
                            selectedUser?.name
                        }}</span>
                    </div>
                    <Button
                        label="Generate Token"
                        icon="pi pi-plus"
                        size="small"
                        @click="showForm = true"
                    />
                </div>
            </template>
            <Details
                v-if="selectedUser"
                :user="selectedUser"
                @handleCopy="(_token) => handleCopy(_token)"
                @handleEdit="(_token) => handleEdit(_token)"
                @handleDeleteToken="(_token) => handleDeleteToken(_token)"
            />
        </Dialog>
        <Dialog
            v-model:visible="showForm"
            :header="`${tokenForm.id ? 'Edit' : 'Create'} Api Token`"
            modal
            maximizable
            :style="{ width: '35rem' }"
            :draggable="true"
            @hide="tokenForm.reset()"
        >
            <TokenForm
                :tokenForm="tokenForm"
                @onClose="showForm = false"
                @handleSave="handleSave"
            />
        </Dialog>
        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { parseISO } from "date-fns";
import { ref } from "vue";
import axios from "axios";
import { useClipboard } from "@vueuse/core";
import { useToast } from "primevue/usetoast";
import Details from "./Details.vue";
import TokenForm from "./TokenForm.vue";

const { copy } = useClipboard();
const toast = useToast();

defineOptions({
    name: "FraudCheck",
});

const props = defineProps<{
    users: any[];
}>();

const showForm = ref(false);
const isLoading = ref(false);
const response = ref();

const selectedUser = ref();

const tokenForm = useForm({
    id: null,
    tokenable_id: null,
    expires_at: null,
    abilities: null,
    description: null,
    domain: null,
});

const showDetails = (item) => {
    selectedUser.value = item;
};

const handleEdit = (item) => {
    tokenForm.id = item.id;
    tokenForm.expires_at = item.expires_at;
    tokenForm.tokenable_id = item.tokenable_id;
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

const reFindSelectedUser = () => {
    const _user = props.users?.find((item) => item.id == selectedUser.value.id);
    if (_user) {
        selectedUser.value = _user;
    }
};

const handleSave = () => {
    if (!selectedUser.value) return;
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
