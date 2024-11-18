<template>
    <component :is="componentType" />
  </template>
  
  <script setup>
  import { computed } from 'vue';
  import TextBox from './elements/TextBox.vue';
  import ImageBox from './elements/ImageBox.vue';
  import ButtonBox from './elements/ButtonBox.vue';
  
  const props = defineProps({
    type: String,
  });
  
  const componentType = computed(() => {
    switch (props.type) {
      case 'text-box':
        return TextBox;
      case 'image-box':
        return ImageBox;
      case 'button':
        return ButtonBox;
      default:
        return null;
    }
  });
  </script>
  