<template>
    <div class="flex bg-gray-100 border rounded-lg overflow-hidden">
      <!-- Sidebar for Draggable Components -->
      <aside class="w-1/4 p-4 bg-gray-800 text-white">
        <h2 class="text-xl font-semibold mb-4">Components</h2>
        <div class="space-y-2">
          <DraggableComponent name="Text Box" type="text-box" />
          <DraggableComponent name="Image Box" type="image-box" />
          <DraggableComponent name="Button" type="button" />
        </div>
      </aside>
  
      <!-- Main Canvas for Dropping Components -->
      <main class="flex-1 p-4 border-l relative">
        <h2 class="text-xl font-semibold mb-4">Drop Area</h2>
        <div
          class="w-full h-full border-2 border-dashed border-gray-400 rounded-lg p-4 relative"
          @dragover.prevent
          @drop="onDrop"
        >
          <div
            v-for="(component, index) in components"
            :key="index"
            class="absolute"
            :style="{ top: `${component.y}px`, left: `${component.x}px` }"
          >
            <DynamicComponent :type="component.type" />
          </div>
        </div>
      </main>
    </div>
  </template>
  
  <script setup>
  import { ref } from 'vue';
  import DraggableComponent from './DraggableComponent.vue';
  import DynamicComponent from './DynamicComponent.vue';
  
  const components = ref([]);
  
  const onDrop = (event) => {
    const componentType = event.dataTransfer.getData('componentType');
    const x = event.offsetX;
    const y = event.offsetY;
    components.value.push({ type: componentType, x, y });
  };
  </script>
  
  <style scoped>
  .relative {
    position: relative;
  }
  </style>
  