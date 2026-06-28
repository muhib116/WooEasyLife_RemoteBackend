<template>
    <PageHeader v-bind="$attrs">
        <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData ?? {}" />
        </template>
    </PageHeader>
</template>

<script setup lang="ts">
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
</script>
