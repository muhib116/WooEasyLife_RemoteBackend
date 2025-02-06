<template>
    <Dialog
        v-model:visible="dialog"
        :header="`${userForm.id ? 'Edit' : 'Create'} Business`"
        modal
        maximizable
        :style="{ width: '35rem' }"
        draggable
        dismissableMask
        @hide="userForm.reset()"
    >
        <div class="p-4">
            <div class="mb-4">
                <label for="name" class="mb-1 block font-semibold"
                    >Business Title / Name</label
                >
                <InputText
                    v-model="userForm.title"
                    id="name"
                    placeholder="Enter title"
                    class="!w-full"
                />
                <span v-if="userForm.errors.title" class="text-sm text-red-500">
                    {{ userForm.errors.title }}
                </span>
            </div>

            <div class="mb-4">
                <label for="description" class="mb-1 block font-semibold"
                    >Details</label
                >
                <InputText
                    v-model="userForm.description"
                    id="description"
                    placeholder="Enter Details"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.description"
                    class="text-sm text-red-500"
                >
                    {{ userForm.errors.description }}
                </span>
            </div>
            <div class="mb-4">
                <label for="domain" class="mb-1 block font-semibold"
                    >Domain</label
                >
                <InputText
                    v-model="userForm.domain"
                    id="domain"
                    placeholder="Enter domain"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.domain"
                    class="text-sm text-red-500"
                >
                    {{ userForm.errors.domain }}
                </span>
            </div>

            <div class="mb-4">
                <div
                    for="status"
                    @click="userForm.status = !userForm.status"
                    class="mb-1 inline-flex cursor-pointer select-none items-center gap-3"
                >
                    <ToggleSwitch
                        v-model="userForm.status"
                        class="pointer-events-none"
                        size="small"
                    />
                    Status
                </div>
                <span
                    v-if="userForm.errors.status"
                    class="text-sm text-red-500"
                >
                    {{ userForm.errors.status }}
                </span>
            </div>

            <div class="flex justify-end gap-2">
                <Button
                    label="Cancel"
                    class="p-button-secondary"
                    @click="closeForm"
                />
                <Button
                    :label="userForm.id ? 'Update' : 'Create'"
                    icon="pi pi-save"
                    :loading="userForm.processing"
                    @click="submitForm"
                />
            </div>
        </div>
    </Dialog>
</template>

<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { onMounted } from "vue";

const props = defineProps<{
    selectedBusiness: object;
    user: object
}>();

// Sync dialog state with modelValue
const dialog = defineModel({
    type: Boolean,
});

const userForm = useForm({
    id: null,
    title: null,
    description: null,
    domain: null,
    ip: null,
    status: true,
});

const closeForm = () => {
    userForm.reset();
    dialog.value = false;
};

const submitForm = () => {
    userForm.post(route("users.business.store", props.user.id), {
        onSuccess: () => {
            userForm.reset();
            dialog.value = false;
        },
    });
};

onMounted(() => {
    if (props.selectedBusiness) {
        const user: any = props.selectedBusiness || {};
        userForm.id = user.id;
        userForm.title = user.title;
        userForm.description = user.description;
        userForm.domain = user.domain;
        userForm.status = user.status;
    }
});
</script>
