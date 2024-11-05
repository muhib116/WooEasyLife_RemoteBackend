<script setup lang="ts">
const props = defineProps(['cardTitle'])
</script>

<template>
  <div
    class="rounded-sm border border-stroke bg-white shadow-default"
  >
    <div class="border-b border-stroke py-4 px-6.5">
      <h3 class="font-medium text-black">{{ props.cardTitle }}</h3>
    </div>
    <slot></slot>
  </div>
</template>
