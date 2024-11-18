<template>
    <div
      class="cursor-pointer p-2 bg-blue-500 text-white rounded-lg"
      draggable="true"
      @dragstart="onDragStart"
    >
      {{ name }}
    </div>
  </template>
  
  <script setup>
  import { defineProps } from 'vue';
  
  const props = defineProps({
    name: String,
    type: String,
  });
  
  const onDragStart = (event) => {
    event.dataTransfer.setData('componentType', props.type);
  };
  </script>
  