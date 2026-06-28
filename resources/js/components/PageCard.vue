<template>
    <PageCard v-bind="$attrs">
        <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData ?? {}" />
        </template>
    </PageCard>
</template>

<script setup lang="ts">
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
</script>
