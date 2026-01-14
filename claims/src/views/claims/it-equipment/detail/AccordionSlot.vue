<template>
  <div ref="fullscreenElement" class="border rounded-md shadow-sm">
    <div class="w-full flex items-center justify-between p-4 bg-white font-semibold">
      <h3 @click="toggle" class="text-gray-700 cursor-pointer text-left font-semibold text-lg">{{ title }}</h3>
      <div class="flex items-center gap-2">
        <Icon
          v-if="isOpen"
          @click="toggleFullscreen"
          icon="mdi:fullscreen"
          class="w-5 h-5 cursor-pointer" />
        <Icon
          @click="toggle"
          icon="mdi:chevron-down"
          class="transition-transform duration-300 cursor-pointer"
          :class="{ 'rotate-180': isOpen }"
          width="24"
          height="24"
        />
      </div>
    </div>
    <transition name="fade">
      <div v-if="isOpen" class="p-4 border-t bg-white">
        <slot></slot>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  isOpen: {
    type: Boolean,
    default: true,
  },
});

const isOpen = ref(props.isOpen); // Open by default, change to false if you want collapsed by default

function toggle() {
  isOpen.value = !isOpen.value;
}

const fullscreenElement = ref(null)

const toggleFullscreen = () => {
  const el = fullscreenElement.value

  if (document.fullscreenElement) {
    document.exitFullscreen()
  } else if (el) {
    el.requestFullscreen()
  }
}

</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
