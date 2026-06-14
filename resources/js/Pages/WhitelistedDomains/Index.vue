<template>
    <AuthenticatedLayout title="Whitelisted Domains">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div>Fraud Check — Whitelisted Domains</div>
                        <p class="mt-1 text-sm font-normal text-slate-500">
                            Only these domains can call the fraud check API.
                        </p>
                    </div>
                    <Button icon="pi pi-plus" label="Add Domain" @click="openCreate" />
                </div>
            </template>
            <template #content>
                <DataTable :value="domains" paginator :rows="10" tableStyle="min-width: 40rem">
                    <Column header="#" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="domain" header="Domain" />
                    <Column field="notes" header="Notes">
                        <template #body="{ data }">
                            {{ data.notes || "—" }}
                        </template>
                    </Column>
                    <Column field="is_active" header="Status">
                        <template #body="{ data }">
                            <Badge
                                :value="data.is_active ? 'Active' : 'Inactive'"
                                :severity="data.is_active ? 'success' : 'secondary'"
                            />
                        </template>
                    </Column>
                    <Column header="Actions" headerStyle="width:10rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    icon="pi pi-pencil"
                                    size="small"
                                    severity="secondary"
                                    @click="openEdit(data)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    @click="removeDomain(data.id)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="showForm"
            modal
            :header="editing ? 'Edit Domain' : 'Add Domain'"
            :style="{ width: '32rem' }"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium">Domain</label>
                    <InputText
                        v-model="form.domain"
                        class="w-full"
                        placeholder="example.com"
                    />
                    <small v-if="form.errors.domain" class="text-red-500">
                        {{ form.errors.domain }}
                    </small>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <Textarea v-model="form.notes" class="w-full" rows="3" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.is_active" binary inputId="is_active" />
                    <label for="is_active">Active</label>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" label="Cancel" severity="secondary" @click="showForm = false" />
                    <Button type="submit" label="Save" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

defineOptions({ name: "WhitelistedDomainsIndex" });

const props = defineProps<{
    domains: Array<{
        id: number;
        domain: string;
        notes: string | null;
        is_active: boolean;
    }>;
}>();

const domains = computed(() => props.domains);
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

const removeDomain = (id: number) => {
    if (!confirm("Remove this domain from the whitelist?")) {
        return;
    }

    useForm({}).delete(route("whitelistedDomains.destroy", id));
};
</script>
