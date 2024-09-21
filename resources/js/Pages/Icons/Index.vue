<!-- src/components/BaseButton.vue -->
<template>
    <AuthenticatedLayout title="Phosphor Icons">
        <div class="bg-white overflow-y-auto h-[calc(100dvh-100px)] mt-5 dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-4 mt-5 mb-3 sticky z-10 top-0 bg-white dark:bg-slate-800 py-4">
                <input
                    v-model="inputText"
                    class="bg-transparent w-full rounded"
                    placeholder="Search icon"
                />
            </div>
            <div
                class="grid grid-cols-5 gap-3 p-4 rounded"
            >
                <div
                    v-for="(icon, index) in filteredIcons"
                    :key="index"
                    @click="handleCopy(icon)"
                    class="py-4 px-5 select-none group border cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 border-gray-200 grid place-content-center rounded"
                >
                    <div class="text-center">
                        <Icon
                            :name="icon"
                            size="27"
                            class="group-hover:scale-105 mx-auto"
                        />
                        <span class="text-xs break-words">
                            {{ icon }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from '@/layouts';
import { Icon } from '@/plugins';
import { computed, ref } from 'vue';
import * as AllIcons from '@phosphor-icons/vue';

const iconNames = Object.keys(AllIcons).map((iconName) => {
    const IconComponent = (AllIcons as any)[iconName];
    return IconComponent.name;
});

const inputText = ref('')

const filteredIcons = computed(() => {
    if(!inputText.value) return iconNames
    let filtered = iconNames.filter(icon => {
        return String(icon).toLowerCase().indexOf(String(inputText.value).toLowerCase()) > -1
    })
    console.log(filtered.length)
    return filtered
})

const handleCopy = (result: any) => {
    navigator.clipboard.writeText(result).then(() => {
        console.log('Copied to clipboard:', result);
    }).catch(err => {
        console.error('Could not copy text: ', err);
    });
}

</script>
