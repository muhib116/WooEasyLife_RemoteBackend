<script setup lang="ts">
const props = defineProps(['cardTitle'])
</script>

<template>
  <div
    class="mb-10 rounded-sm border border-stroke bg-white shadow-default"
  >
    <div class="border-b border-stroke px-7 py-4">
      <h3 class="font-medium text-black">{{ props.cardTitle }}</h3>
    </div>

    <div class="p-4 md:p-6 xl:p-9">
      <slot></slot>
    </div>
  </div>
</template>
