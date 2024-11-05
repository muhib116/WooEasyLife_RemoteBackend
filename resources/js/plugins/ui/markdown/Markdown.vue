<template>
    <div v-html="getContent(content)" class="prose prose-stone"></div>
</template>

<script setup lang="ts">
import { markDown } from "@/composable/useMarkdown";

defineProps<{
    content: string;
}>();

const getContent = (_content: string) => {
    const html = markDown.render(_content);
    console.log(html);
    return html;
};
</script>
