<template>
    <ul>
        <li
            v-for="(item, index) in items || []"
            :key="index"
            class="pb-3 sm:pb-4"
        >
            <div class="flex items-start space-x-4 rtl:space-x-reverse">
                <div class="shrink-0">
                    <Avatar
                        :src="item?.image"
                        :text="item?.name || ''"
                        :size="32"
                    />
                </div>
                <div class="min-w-0 flex-1 px-4 py-2 bg-red-500 rounded-[12px] border border-red-400">
                    <p
                        class="text-sm font-medium text-white dark:text-white"
                    >
                        Name: {{ item?.name || "" }}
                        <br>
                        <span v-if="item?.user_id" class="text-orange-200"
                            >UserId: {{ item?.user_id }}</span
                        >
                        <br>
                        <span v-if="item?.consignment_id" class="text-orange-200"
                            >Consignment: {{ item?.consignment_id }}</span
                        >
                    </p>
                    <p
                        class="text-sm text-gray-300"
                    >
                        {{ formatReadable(item?.created_at) }}
                    </p>
                    <p
                        class="text-lg text-red-50 dark:text-red-50"
                    >
                        {{ item?.details || '' }}
                    </p>
                </div>
                <div
                    class="inline-flex items-center text-base font-semibold text-gray-900 dark:text-white"
                ></div>
            </div>
        </li>
    </ul>
</template>

<script setup>
import Avatar from "./Avatar.vue";
import { parseISO, format } from "date-fns";

defineProps({
    items: Array,
});

function formatReadable(dateString) {
    try {
        if (!dateString) return "";

        // Some backends send microseconds -> JS Date ignores them anyway.
        // parseISO works fine with ".000000Z"
        const date = parseISO(dateString);

        // You can customize this format however you want.
        return format(date, "MMMM d, yyyy h:mm a");
    } catch (e) {
        // Safe fallback: empty string on error
        return "";
    }
}
</script>
