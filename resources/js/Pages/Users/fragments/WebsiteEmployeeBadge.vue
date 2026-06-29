<template>
    <div
        v-if="employees.length"
        class="relative"
        @mouseenter="open = true"
        @mouseleave="open = false"
    >
        <span
            class="inline-flex cursor-default items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            :aria-label="`${employees.length} linked employee${employees.length === 1 ? '' : 's'}`"
        >
            <Icon name="PhUsers" class="text-[0.8rem]" />
            {{ employees.length }}
        </span>

        <div
            v-show="open"
            class="absolute left-0 top-full z-30 mt-1.5 min-w-[14rem] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
        >
            <p
                class="border-b border-gray-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400"
            >
                Linked employees
            </p>
            <ul class="max-h-52 overflow-y-auto py-1">
                <li
                    v-for="employee in employees"
                    :key="employee.id"
                    class="flex items-center gap-2.5 px-3 py-2"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-gray-50 text-[10px] font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <img
                            v-if="employee.photo_url"
                            :src="employee.photo_url"
                            :alt="employee.name"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{ initials(employee.name) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ employee.name }}
                        </p>
                        <p
                            v-if="employee.role"
                            class="truncate text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ employee.role }}
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { ref } from "vue";

export type WebsiteLinkedEmployee = {
    id: number;
    name: string;
    photo_url?: string | null;
    role?: string | null;
};

defineProps<{
    employees: WebsiteLinkedEmployee[];
}>();

const open = ref(false);

const initials = (name?: string | null) => {
    const parts = (name ?? "").trim().split(/\s+/).filter(Boolean);

    if (!parts.length) {
        return "?";
    }

    return parts
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? "")
        .join("");
};
</script>
