<template>
    <AuthenticatedLayout title="Phosphor Icons">
        <div class="space-y-5">
            <PageHeader
                title="Phosphor Icons"
                :description="`${filteredIcons.length} icons — click to copy name`"
                icon="PhSparkle"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <IconField class="w-full sm:w-72">
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="inputText"
                            placeholder="Search icons..."
                            class="w-full"
                        />
                    </IconField>
                </template>
            </PageHeader>

            <PageCard no-padding>
                <div
                    class="grid max-h-[calc(100dvh-220px)] grid-cols-2 gap-3 overflow-y-auto p-4 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8"
                >
                    <button
                        v-for="(icon, index) in filteredIcons"
                        :key="index"
                        type="button"
                        class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 px-3 py-4 text-center transition hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-700 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10"
                        @click="handleCopy(icon)"
                    >
                        <Icon
                            :name="icon"
                            class="text-2xl text-gray-700 transition group-hover:scale-110 dark:text-gray-200"
                        />
                        <span class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                            {{ icon }}
                        </span>
                    </button>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import { computed, ref } from "vue";
import * as AllIcons from "@phosphor-icons/vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { useToast } from "primevue/usetoast";

const toast = useToast();

const iconNames = Object.keys(AllIcons).map((iconName) => {
    const IconComponent = (AllIcons as any)[iconName];
    return IconComponent.name;
});

const inputText = ref("");

const filteredIcons = computed(() => {
    if (!inputText.value) return iconNames;
    const query = inputText.value.toLowerCase();
    return iconNames.filter((icon) =>
        String(icon).toLowerCase().includes(query),
    );
});

const handleCopy = (name: string) => {
    navigator.clipboard
        .writeText(name)
        .then(() => {
            toast.add({
                severity: "success",
                summary: "Copied",
                detail: name,
                life: 2000,
            });
        })
        .catch((err) => {
            console.error("Could not copy text:", err);
        });
};
</script>
