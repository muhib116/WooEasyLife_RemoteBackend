<template>
    <label class="grid gap-1 mb-2">
        <div v-if="label">
            {{ label }}
        </div>
        <AutoComplete
            v-bind="$attrs"
            v-model="localModalValue"
            :suggestions="suggestions || []" 
            @complete="filter" 
            :optionLabel="optionKey" 
            @change="() => {
                modalValue = get(localModalValue, value)
            }"
            dropdown
        />
        <span v-if="error" class="text-red-500">{{ error }}</span>
    </label>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { get } from 'lodash'

defineOptions({
    inheritAttrs: false
})

const props = withDefaults(defineProps<{
    suggestions?: any[]
    error?: any
    optionKey?: any
    value?: any
    label?: any
    filter?: (e: any) => {}
}>(), {
    // suggestions: []
    optionKey: 'name',
    value: 'id'
})

const localModalValue = ref()

const modalValue = defineModel()


</script>