<template>
  <div
    class="relative rounded-full overflow-hidden"
    :style="{ width: size + 'px', height: size + 'px' }"
  >
    <div class="grid place-content-center absolute inset-0" v-if="validSrc && isLoading && !imageError">
      <PhCircleNotch class="animate-spin" />
    </div>
    <img
      v-if="validSrc && !imageError"
      :src="validSrc"
      alt="Avatar"
      class="object-cover w-full h-full"
      @error="imageError = true"
      @load="isLoading = false"
    />
    <svg
      v-else
      :width="size"
      :height="size"
      viewBox="0 0 100 100"
      xmlns="http://www.w3.org/2000/svg"
      class="w-full h-full"
    >
      <circle cx="50" cy="50" r="50" :fill="bgFill" />
      <text
        x="50"
        y="50"
        dominant-baseline="central"
        text-anchor="middle"
        dy=".1em"
        font-size="40"
        :fill="textColor"
        font-family="Arial, sans-serif"
        font-weight="bold"
      >
        {{ initials }}
      </text>
    </svg>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { PhCircleNotch } from "@phosphor-icons/vue";

const props = defineProps<{
  src: any;
  text?: string;
  size?: number | string;
  bgColor?: string;
}>();

function stringToColor(str: string): string {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }

  const hue = Math.abs(hash) % 360;
  const saturation = 65 + (Math.abs(hash) % 10); // 65–74%
  const lightness = 45 + (Math.abs(hash) % 10); // 45–54%

  return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
}

function getContrastYIQ(hexcolor: string): string {
  hexcolor = hexcolor.replace("#", "");
  const r = parseInt(hexcolor.slice(0, 2), 16);
  const g = parseInt(hexcolor.slice(2, 4), 16);
  const b = parseInt(hexcolor.slice(4, 6), 16);
  const yiq = (r * 299 + g * 587 + b * 114) / 1000;
  return yiq >= 128 ? "#000000" : "#ffffff";
}


const imageError = ref(false);
const isLoading = ref(true);

const validSrc = computed(() => {
  return typeof props.src === "string" && props.src.trim() !== ""
    ? props.src.trim()
    : null;
});

watch(validSrc, () => {
  isLoading.value = true;
  imageError.value = false;
});

const initials = computed(() => {
  const text = props.text?.trim() || "U";
  const words = text.split(/\s+/);
  let chars = words[0]?.charAt(0) ?? "";
  if (words.length > 1) {
    chars += words[1]?.charAt(0) ?? "";
  } else if (words[0]?.length > 1) {
    chars += words[0]?.charAt(1) ?? "";
  }
  return chars.toUpperCase();
});

const bgFill = computed(() => {
  return props.bgColor || stringToColor(props.text || "U");
});

const textColor = computed(() => {
  return getContrastYIQ(bgFill.value);
});
</script>
