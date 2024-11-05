<template>
    <label 
        class="flex items-center gap-4 mb-4"
        :class="wrapperClass"
    >
        <div v-if="label" class="font-semibold w-24">
            {{ label }}
        </div>
        <InputText 
            v-model="modelValue" 
            id="username" 
            class="flex-auto" 
            autocomplete="off" 
            v-bind="$attrs"
        />
        <div v-if="error" class="font-light text-xs text-red-500">
            {{ error }}
        </div>
    </label>
</template>

<script setup lang="ts">
import { get } from 'lodash'

defineOptions({
    inheritAttrs: false
})

defineProps<{
    label?: string
    error?: string
    wrapperClass?: string
}>()

const modelValue = defineModel<string>()

</script>