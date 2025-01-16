<template>
    <div class="w-full min-h-[200px] bg-blue-300">
        <!-- <Codemirror
            v-model:value="modelValue"
            :options="options"
            placeholder="Code..."
            :height="250"
            border
        /> -->
    </div>
</template>

<script setup lang="ts">
// import Codemirror from "codemirror-editor-vue3";
// import "codemirror/theme/dracula.css";

const options = {
    mode: "json", // Language mode
    theme: "dracula", // Theme
};

const modelValue = defineModel();
</script>
