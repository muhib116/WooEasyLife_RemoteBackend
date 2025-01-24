<template>
    <form @submit.prevent="$emit('handleSave')">
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
    </form>
</template>

<script setup lang="ts">
defineProps<{
    tokenForm: any;
}>();
</script>
