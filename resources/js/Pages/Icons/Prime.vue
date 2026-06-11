<template>
    <AuthenticatedLayout title="Prime Icons">
        <div class="space-y-5">
            <PageHeader
                title="Prime Icons"
                :description="`${filteredIcons.length} icons — click to copy class name`"
                icon="PhPalette"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
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
                        @click="handleCopy(PrimeIcons[icon])"
                    >
                        <span
                            class="text-2xl text-gray-700 transition group-hover:scale-110 dark:text-gray-200"
                            :class="PrimeIcons[icon]"
                        />
                        <span class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                            {{ PrimeIcons[icon] }}
                        </span>
                    </button>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { computed, ref } from "vue";
import { PrimeIcons } from "@primevue/core/api";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { useToast } from "primevue/usetoast";

const toast = useToast();

const iconNames = Object.keys(PrimeIcons);

const inputText = ref("");

const filteredIcons = computed(() => {
    if (!inputText.value) return iconNames;
    const query = inputText.value.toLowerCase();
    return iconNames.filter((icon) =>
        String(icon).toLowerCase().includes(query),
    );
});

const handleCopy = (className: string) => {
    navigator.clipboard
        .writeText(className)
        .then(() => {
            toast.add({
                severity: "success",
                summary: "Copied",
                detail: className,
                life: 2000,
            });
        })
        .catch((err) => {
            console.error("Could not copy text:", err);
        });
};
</script>
