<template>
    <AdminDialog
        v-model:visible="dialog"
        :header="`${userForm.id ? 'Edit' : 'Create'} Merchant`"
        :style="{ width: '35rem' }"
        draggable
        @hide="resetForm"
    >
        <div class="space-y-4 p-1">
            <div>
                <label for="name" class="mb-1 block text-sm font-semibold">
                    Name <span class="text-rose-500">*</span>
                </label>
                <InputText
                    v-model="userForm.name"
                    id="name"
                    placeholder="Enter name"
                    class="!w-full"
                />
                <span v-if="userForm.errors.name" class="text-sm text-rose-500">
                    {{ userForm.errors.name }}
                </span>
            </div>

            <div>
                <label for="phone" class="mb-1 block text-sm font-semibold">
                    Phone <span class="text-rose-500">*</span>
                </label>
                <InputText
                    v-model="userForm.phone"
                    id="phone"
                    placeholder="Enter phone number"
                    class="!w-full"
                />
                <span v-if="userForm.errors.phone" class="text-sm text-rose-500">
                    {{ userForm.errors.phone }}
                </span>
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-semibold">
                    Password
                    <span v-if="!userForm.id" class="text-rose-500">*</span>
                    <span
                        v-else
                        class="text-xs font-normal text-gray-500 dark:text-gray-400"
                    >
                        (leave blank to keep current)
                    </span>
                </label>
                <InputText
                    v-model="userForm.password"
                    id="password"
                    type="password"
                    placeholder="Enter password"
                    class="!w-full"
                />
                <span
                    v-if="userForm.errors.password"
                    class="text-sm text-rose-500"
                >
                    {{ userForm.errors.password }}
                </span>
            </div>

            <div
                class="flex cursor-pointer items-center gap-3"
                @click="userForm.status = !userForm.status"
            >
                <ToggleSwitch
                    v-model="userForm.status"
                    class="pointer-events-none"
                />
                <span class="text-sm font-medium">Active account</span>
            </div>

            <button
                type="button"
                class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300"
                @click="showAdvanced = !showAdvanced"
            >
                Advanced fields
                <i
                    :class="
                        showAdvanced ? 'pi pi-chevron-up' : 'pi pi-chevron-down'
                    "
                />
            </button>

            <div v-show="showAdvanced" class="space-y-4 border-t pt-4">
                <div>
                    <label for="email" class="mb-1 block text-sm font-semibold">
                        Email
                    </label>
                    <InputText
                        v-model="userForm.email"
                        id="email"
                        placeholder="Enter email"
                        class="!w-full"
                    />
                    <span
                        v-if="userForm.errors.email"
                        class="text-sm text-rose-500"
                    >
                        {{ userForm.errors.email }}
                    </span>
                </div>

                <div>
                    <label
                        for="whatsapp_phone"
                        class="mb-1 block text-sm font-semibold"
                    >
                        WhatsApp Phone
                    </label>
                    <InputText
                        v-model="userForm.whatsapp_phone"
                        id="whatsapp_phone"
                        placeholder="Enter WhatsApp phone"
                        class="!w-full"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Used for subscription renewal alerts when WhatsApp notifications are enabled.
                    </p>
                </div>

                <div>
                    <label
                        for="facebook_page_link"
                        class="mb-1 block text-sm font-semibold"
                    >
                        Facebook Page Link
                    </label>
                    <InputText
                        v-model="userForm.facebook_page_link"
                        id="facebook_page_link"
                        placeholder="Enter Facebook page link"
                        class="!w-full"
                    />
                </div>

                <div>
                    <label for="address" class="mb-1 block text-sm font-semibold">
                        Address
                    </label>
                    <InputText
                        v-model="userForm.address"
                        id="address"
                        placeholder="Enter address"
                        class="!w-full"
                    />
                </div>

                <div
                    v-if="isAdmin"
                    class="flex cursor-pointer items-center gap-3"
                    @click="userForm.is_test = !userForm.is_test"
                >
                    <ToggleSwitch
                        v-model="userForm.is_test"
                        class="pointer-events-none"
                    />
                    <span class="text-sm font-medium">Test user</span>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Button
                    label="Cancel"
                    severity="secondary"
                    outlined
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
    </AdminDialog>
</template>

<script setup lang="ts">
import AdminDialog from "./AdminDialog.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const props = defineProps<{
    selectedUser?: Record<string, any> | null;
}>();

const page = usePage();
const isAdmin = computed(
    () => (page.props.auth as any)?.user?.role === "admin",
);

const dialog = defineModel<boolean>({ type: Boolean });

const showAdvanced = ref(false);

const userForm = useForm({
    id: null as number | null,
    name: "",
    email: "",
    phone: "",
    password: "",
    address: "",
    whatsapp_phone: "",
    facebook_page_link: "",
    status: true,
    is_test: false,
});

const fillForm = (user?: Record<string, any> | null) => {
    userForm.reset();
    showAdvanced.value = false;

    if (!user) {
        return;
    }

    userForm.id = user.id;
    userForm.name = user.name ?? "";
    userForm.email = user.email ?? "";
    userForm.phone = user.phone ?? "";
    userForm.address = user.address ?? "";
    userForm.whatsapp_phone = user.whatsapp_phone ?? "";
    userForm.facebook_page_link = user.facebook_page_link ?? "";
    userForm.status = Boolean(user.status);
    userForm.is_test = Boolean(user.is_test);

    if (
        user.email ||
        user.whatsapp_phone ||
        user.facebook_page_link ||
        user.address ||
        user.is_test
    ) {
        showAdvanced.value = true;
    }
};

watch(
    () => [dialog.value, props.selectedUser] as const,
    ([isOpen, user]) => {
        if (isOpen) {
            fillForm(user);
        }
    },
    { immediate: true },
);

const resetForm = () => {
    userForm.reset();
    showAdvanced.value = false;
};

const closeForm = () => {
    resetForm();
    dialog.value = false;
};

const submitForm = () => {
    userForm.post(route("users.store"), {
        onSuccess: () => {
            closeForm();
        },
    });
};
</script>
