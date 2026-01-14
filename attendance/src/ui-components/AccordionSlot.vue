<template>
  <div class="border rounded-md shadow-sm">
    <button
      @click="toggle"
      class="w-full flex items-center justify-between p-4 bg-white text-left font-semibold"
    >
      <span>{{ title }}</span>
      <!-- <Icon
        :icon="isOpen ? 'mdi:chevron-up' : 'mdi:chevron-down'"
        class="w-5 h-5" -->
      <Icon
        icon="mdi:chevron-down"
        class="transition-transform duration-300"
        :class="{ 'rotate-180': isOpen }"
        width="24"
        height="24"
      />
    </button>
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

defineProps({
  title: {
    type: String,
    required: true,
  },
});

const isOpen = ref(true); // Open by default, change to false if you want collapsed by default

function toggle() {
  isOpen.value = !isOpen.value;
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
