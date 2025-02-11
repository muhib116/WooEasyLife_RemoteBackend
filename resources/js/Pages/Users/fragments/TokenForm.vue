<template>
    <div>
        <div class="flex flex-col gap-1 mb-4">
            <div for="title" class="font-semibold w-24">Title</div>
            <div class="flex-auto relative">
                <InputText
                    v-model="tokenForm.title"
                    id="title"
                    placeholder="Title"
                    class="!w-full"
                />
                <span
                    v-if="tokenForm.errors.title"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ tokenForm.errors.title }}</span
                >
            </div>
        </div>
        <div class="flex flex-col gap-1 mb-2">
            <div for="version" class="font-semibold w-24">Description</div>
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
            <div for="domain" class="font-semibold">
                Select Package for Domain
            </div>
            <div class="flex-auto relative">
                <!-- used ${item.total_order_can_handle} of ${item.total_order_handled} -->
                <Select
                    class="w-full"
                    v-model="tokenForm.user_package_id"
                    :options="user_packages"
                    :optionLabel="(item) => `(${item.domain})`"
                    optionValue="id"
                    placeholder="Package"
                />
                <span
                    v-if="tokenForm.errors.user_package_id"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ tokenForm.errors.user_package_id }}</span
                >
            </div>
        </div>
        <div class="flex flex-col gap-1 mb-4">
            <div for="expires_at" class="font-semibold w-24">Expires At</div>
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
        <label class="flex items-center gap-5">
            <ToggleSwitch v-model="tokenForm.status" />
            Is Active
        </label>
        <div class="flex justify-end gap-2">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                @click="$emit('onClose')"
            ></Button>
            <Button
                type="submit"
                :label="tokenForm.id ? 'Update' : 'Create'"
                :loading="tokenForm.processing"
                @click="$emit('handleSave')"
            ></Button>
        </div>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    tokenForm: any;
    user_packages: any[];
}>();
</script>
