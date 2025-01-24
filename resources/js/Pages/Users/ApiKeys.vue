<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <UserNav :user="user">
                        <Button label="Generate" size="small" icon="pi pi-plus" />
                    </UserNav>
                    <div class="pt-4">
                        <DataTable
                            :value="user.tokens"
                            tableStyle="min-width: 50rem"
                        >
                            <Column field="name" header="Name" />
                            <Column field="email" header="Email" />
                            <Column
                                field="last_used_ago"
                                header="Last used ago"
                            />
                            <Column field="domain" header="Accessed Domain" />
                            <Column field="abilities" header="Abilities" />
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
                                            @click="$emit('handleCopy', data)"
                                            icon="pi pi-copy"
                                        />
                                        <Button
                                            severity="info"
                                            size="small"
                                            @click="$emit('handleEdit', data)"
                                            icon="pi pi-pencil"
                                        />
                                        <Button
                                            severity="danger"
                                            :loading="data?.loading"
                                            size="small"
                                            @click="
                                                $emit('handleDeleteToken', data)
                                            "
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
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import { format, parseISO } from "date-fns";
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import axios from "axios";
import { useToast } from "primevue/usetoast";
import { useClipboard } from "@vueuse/core";


const { copy } = useClipboard();
const toast = useToast();

defineOptions({
    name: "ApiKeys",
});

const props = defineProps<{
    user: any;
}>();

const showForm = ref(false)
const tokenForm = useForm({
    id: null,
    tokenable_id: null,
    expires_at: null,
    abilities: null,
    description: null,
    domain: null,
});

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
    if (!confirm("Are you sure?")) return;
    const { data } = await axios.post(route("apiKeys.delete"), {
        tokenId: item.id,
    });
};

function formatExpiresAt(expiresAt) {
    if (expiresAt === null) {
        return "No Expiration";
    }

    return format(new Date(expiresAt), "PPp"); // Example: Jan 18, 2025, 12:00 AM
}
</script>
