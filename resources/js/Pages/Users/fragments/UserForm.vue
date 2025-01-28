<template>
    <Dialog
        v-model:visible="dialog"
        :header="`${userForm.id ? 'Edit' : 'Create'} User`"
        modal
        maximizable
        :style="{ width: '35rem' }"
        draggable
        dismissableMask
        @hide="userForm.reset()"
    >
        <div class="p-4">
            <div class="mb-4">
                <label for="name" class="block font-semibold mb-1">Name</label>
                <InputText
                    v-model="userForm.name"
                    id="name"
                    placeholder="Enter name"
                    class="!w-full"
                />
                <span v-if="userForm.errors.name" class="text-red-500 text-sm">
                    {{ userForm.errors.name }}
                </span>
            </div>

            <div class="mb-4">
                <label for="email" class="block font-semibold mb-1"
                    >Email (optional)</label
                >
                <InputText
                    v-model="userForm.email"
                    id="email"
                    placeholder="Enter email"
                    class="!w-full"
                />
                <span v-if="userForm.errors.email" class="text-red-500 text-sm">
                    {{ userForm.errors.email }}
                </span>
            </div>
            <div class="mb-4">
                <label for="password" class="block font-semibold mb-1"
                    >Password</label
                >
                <InputText
                    v-model="userForm.password"
                    id="password"
                    placeholder="Enter password"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.password"
                    class="text-red-500 text-sm"
                >
                    {{ userForm.errors.password }}
                </span>
            </div>

            <div class="mb-4">
                <label for="phone" class="block font-semibold mb-1"
                    >Phone</label
                >
                <InputText
                    v-model="userForm.phone"
                    id="phone"
                    placeholder="Enter phone number"
                    class="!w-full"
                />
                <span v-if="userForm.errors.phone" class="text-red-500 text-sm">
                    {{ userForm.errors.phone }}
                </span>
            </div>

            <div class="mb-4">
                <label for="whatsapp_phone" class="block font-semibold mb-1"
                    >WhatsApp Phone (optional)</label
                >
                <InputText
                    v-model="userForm.whatsapp_phone"
                    id="whatsapp_phone"
                    placeholder="Enter WhatsApp phone number"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.whatsapp_phone"
                    class="text-red-500 text-sm"
                >
                    {{ userForm.errors.whatsapp_phone }}
                </span>
            </div>

            <div class="mb-4">
                <label for="facebook_page_link" class="block font-semibold mb-1"
                    >Facebook Page Link (optional)</label
                >
                <InputText
                    v-model="userForm.facebook_page_link"
                    id="facebook_page_link"
                    placeholder="Enter Facebook page link"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.facebook_page_link"
                    class="text-red-500 text-sm"
                >
                    {{ userForm.errors.facebook_page_link }}
                </span>
            </div>
            <div class="mb-4">
                <label for="facebook_page_link" class="block font-semibold mb-1"
                    >Address (optional)</label
                >
                <InputText
                    v-model="userForm.address"
                    id="address"
                    placeholder="Enter address"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.address"
                    class="text-red-500 text-sm"
                >
                    {{ userForm.errors.address }}
                </span>
            </div>

            <div class="mb-4">
                <div
                    for="status"
                    @click="userForm.status = !userForm.status"
                    class="inline-flex gap-3 items-center mb-1 cursor-pointer select-none"
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
                    class="text-red-500 text-sm"
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
    selectedUser: object;
}>();

// Sync dialog state with modelValue
const dialog = defineModel({
    type: Boolean,
});

const userForm = useForm({
    id: null,
    name: "",
    email: "",
    phone: "",
    password: "",
    address: "",
    whatsapp_phone: "",
    facebook_page_link: "",
    status: true,
});

const closeForm = () => {
    userForm.reset();
    dialog.value = false;
};

const submitForm = () => {
    userForm.post(route("users.store"), {
        onSuccess: () => {
            userForm.reset();
            dialog.value = false;
        },
    });
};

onMounted(() => {
    if (props.selectedUser) {
        const user: any = props.selectedUser || {};
        userForm.id = user.id;
        userForm.name = user.name;
        userForm.email = user.email;
        userForm.phone = user.phone;
        userForm.address = user.address;
        userForm.whatsapp_phone = user.whatsapp_phone;
        userForm.facebook_page_link = user.facebook_page_link;
        userForm.status = user.status;
    }
});
</script>
