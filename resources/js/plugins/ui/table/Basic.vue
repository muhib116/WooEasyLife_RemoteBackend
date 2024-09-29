<template>
    <div class="bg-white dark:bg-gray-700 shadow-box rounded-md">
        <div
            class="py-3 px-5 flex justify-between items-center font-semibold border-b dark:border-gray-500 mih-h-[60px]"
        >
            <slot name="headerBefore"></slot>
            <div>
                {{ title }}
            </div>
            <slot name="headerMiddle"></slot>
            <div class="relative">
                <div
                    class="absolute inset-y-0 left-0 rtl:inset-r-0 rtl:right-0 flex items-center ps-3 pointer-events-none"
                >
                    <Icon name="PhMagnifyingGlass" />
                </div>
                <input
                    type="text"
                    id="table-search"
                    class="block p-2 ps-10 text-sm text-gray-900 border border-gray-100 rounded-full w-80 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="Search for items"
                />
            </div>
            <slot name="headerAfter"></slot>
        </div>
        <table
            class="table w-full"
        >
            <thead>
                <tr>
                    <th
                        v-for="(header, index) in options"
                        :key="index"
                        :class="{
                            '!text-left': header.align == 'left',
                            '!text-right': header.align == 'right'
                        }"
                    >
                        {{ header.title }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in items"
                    :key="index"
                >
                    <template
                        v-for="(header, index) in options"
                        :key="index"
                    >
                        <td
                            :class="{
                                '!text-left': header.align == 'left',
                                '!text-right': header.align == 'right'
                            }"
                        >
                            <slot
                                :name="header.key"
                                :item="item"
                            >
                                {{ item[header.key] }}
                            </slot>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
        <!-- <div class="w-full text-center bg-gray-50 dark:bg-gray-700 py-2 cursor-pointer select-none">
            Load Older Entries
        </div> -->
        <div
            v-if="isEmpty(items)"
            class="min-h-[100px] bg-[#f3f4f6] py-10 grid place-content-center"
        >
            <Icon name="PhCookie" size="100" />
            <div>
                No record found
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Icon } from "@/plugins";
import { isEmpty } from 'lodash'

defineOptions({
    name: "Table",
});

const props = defineProps<{
    items?: any[]
    options?: any[]
    title?: string
}>();

</script>
