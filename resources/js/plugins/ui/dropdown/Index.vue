<template>
    <div class="relative z-10">
        <slot
            name="toggler"
            :state="isOpen"
            :toggle="toggle"
        >
            <div
                @click="toggle(!isOpen)"
                class="mt-2 select-none border inline-flex cursor-pointer items-center justify-between rounded-md bg-white dark:bg-black dark:hover:bg-slate-800 px-3 py-2"
                v-click-outside="()=>{toggle(false)}"
            >
                Dropdown
                <Icon 
                    name="PhCaretDown"
                    size="20"
                />
            </div>
        </slot>
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="mt-1 min-w-[200px] absolute w-full z-50 rounded-md border bg-white dark:bg-black py-1 shadow-xl shadow-black/5 dark:shadow-white/5"
            >
                <div class="px-3 py-2 hover:bg-primary-600 hover:text-white cursor-pointer">Wade Cooper</div>
                <div class="px-3 py-2 hover:bg-primary-600 hover:text-white cursor-pointer">Arlene Mccoy</div>
                <div class="px-3 py-2 hover:bg-primary-600 hover:text-white cursor-pointer">Tom Cook</div>
                <div class="px-3 py-2 hover:bg-primary-600 hover:text-white cursor-pointer">Devon Webb</div>
            </div>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { useDropdown } from "./useDropdown";

const { isOpen, toggleButtonRef, toggle } = useDropdown()

</script>

<style>
.d {
    background: #4f46e5;
}
</style>