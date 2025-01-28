<template>
    <div class="grid gap-5">
        <label class="grid gap-1 mb-2">
            <div>Title</div>
            <InputText
                v-model="form.title"
                id="title"
                placeholder="Package Title"
                class="flex-auto w-full"
                autocomplete="off"
            />
            <span v-if="form.errors.title" class="text-red-500">{{
                form.errors.title
            }}</span>
        </label>
        <label class="grid gap-1 mb-2">
            <div>Description</div>
            <Textarea
                v-model="form.description"
                placeholder="Description"
                class="w-full"
                autoResize
                rows="3"
            />
            <span v-if="form.errors.description" class="text-red-500">{{
                form.errors.description
            }}</span>
        </label>
        <label class="grid gap-1 mb-2">
            <div>Per Order Rate</div>
            <InputNumber
                v-model="form.per_order_rate"
                placeholder="Order Rate"
                class="w-full"
                :maxFractionDigits="5"
                :useGrouping="false"
                :min="0"
            />
            <span v-if="form.errors.per_order_rate" class="text-red-500">{{
                form.errors.per_order_rate
            }}</span>
        </label>
        <label class="flex items-center gap-5">
            <ToggleSwitch v-model="form.is_active" />
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
                label="Create"
                :loading="form.processing"
                @click="$emit('handleSubmit')"
            ></Button>
        </div>
    </div>
</template>

<script setup lang="ts">
const props = defineProps<{
    form: any;
}>();
</script>
