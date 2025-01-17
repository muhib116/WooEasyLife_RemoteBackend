<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title> Api Tokens </template>
            <template #content>
                <div class="min-h-[400px]">
                    <Button @click="handleGenerate" :loading="isLoading">
                        Generate Token
                    </Button>
                    <DataTable :value="tokens" tableStyle="min-width: 50rem">
                        <Column field="name" header="Name"></Column>
                        <Column field="abilities" header="Abilities"></Column>
                        <Column
                            field="last_used_ago"
                            header="Last used ago"
                        ></Column>
                        <Column header="Action" headerClass="text-right">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Button
                                        severity="danger"
                                        :loading="data?.loading"
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
        <Toast />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { router, useForm } from "@inertiajs/vue3";
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

const isLoading = ref(false);
const response = ref();

const form = useForm({
    phone: "",
});

const handleCopy = (item) => {
    copy(item.token);
    toast.add({
        severity: "success",
        summary: "Success",
        detail: "Token created successfully",
        life: 3000,
    });
};

const handleDeleteToken = async (item) => {
    if (!confirm("Are you sure?")) return;
    set(item, "loading", true);
    const { data } = await axios.post(route("apiKeys.delete"), {
        tokenId: item.id,
    });
    toast.add({
        severity: "success",
        summary: "Success",
        detail: "Token deleted successfully",
        life: 3000,
    });
    set(item, "loading", false);
    router.reload({
        only: ["tokens"],
    });
};

const handleGenerate = async () => {
    // isLoading.value = true;
    form.post(route("apiKeys.create"));
    // await axios.post(route("apiKeys.create"));
    // toast.add({
    //     severity: "success",
    //     summary: "Success",
    //     detail: "Token created successfully",
    //     life: 3000,
    // });
    // isLoading.value = false;
    // router.reload({
    //     only: ["tokens"],
    // });
};
</script>
