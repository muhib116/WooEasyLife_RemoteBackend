<template>
    <div class="relative group flex items-center">
        <div class="toolBox hidden group-hover:block bg-black px-4 fs-10 font-medium bg-secondary-one text-white absolute top-full left-0 mt-2 text-center transform -translate-x-1/2 rounded">
            {{ text }}
        </div>
        <slot></slot>
    </div>
</template>

<script setup>
    defineProps({
        text: String
    })
</script>

<style scoped>
    .toolBox::before{
        content: '';
        border: 6px solid;
        border-color: transparent transparent #3D3D3D transparent;
        position: absolute;
        left: 50%;
        bottom: 100%;
    }
    .tooltipWrapper:hover .toolBox{
        display: block;
    }
</style>