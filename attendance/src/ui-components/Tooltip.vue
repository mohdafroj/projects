<template>
  <div class="relative inline-block">
    
    <div
      @mouseenter="show = true"
      @mouseleave="show = false"
      @focus="show = true"
      @blur="show = false"
      class="inline-flex items-center"
    >
      <slot name="trigger"></slot>
    </div>

    <!-- Tooltip Content -->
    <transition
      name="tooltip"
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100 duration-500"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="show"
        :class="[
          'absolute z-50 p-3 rounded-none shadow-lg text-sm font-medium',
          tooltipClass,
          positionClass,
        ]"
        role="tooltip"
        aria-hidden="true"
        :style="{ width }"
      >
        <slot></slot>
        <!-- Arrow -->
        <div
          :class="arrowClass"
          class="absolute w-4 h-4 bg-inherit"
          style="clip-path: polygon(50% 0%, 0% 100%, 100% 100%)"
        ></div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";

const { position, tooltipClass, width } = defineProps({
  position: {
    type: String,
    default: "top", // Can be 'top', 'bottom', 'left', 'right'
  },
  tooltipClass: {
    type: String,
    default: "bg-gray-800 text-white",
  },
  width: {
    type: String,
    default: "200px", // Tooltip width
  },
});

// Reactive state
const show = ref(false);

// Computed properties
const positionClass = computed(() => {
  switch (position) {
    case "top":
      return "bottom-full left-1/2 transform -translate-x-1/2 -mb-2";
    case "bottom":
      return "top-full left-1/2 transform -translate-x-1/2 -mt-2";
    case "left":
      return "right-full top-1/2 transform -translate-y-1/2 -mr-1";
    case "right":
      return "left-full top-1/2 transform -translate-y-1/2 -ml-1";
    default:
      return "bottom-full left-1/2 transform -translate-x-1/2 mb-2";
  }
});

const arrowClass = computed(() => {
  switch (position) {
    case "top":
      return "top-full left-1/2 transform -translate-x-1/2 rotate-180";
    case "bottom":
      return "bottom-full left-1/2 transform -translate-x-1/2 -rotate-360";
    case "left":
      return "left-full top-1/2 transform -translate-y-1/2 rotate-90";
    case "right":
      return "right-full top-1/2 transform -translate-y-1/2 -rotate-90";
    default:
      return "top-full left-1/2 transform -translate-x-1/2 rotate-180";
  }
});
</script>

<style scoped>
/* Additional transitions if required */
.tooltip-enter-active,
.tooltip-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.tooltip-enter-from,
.tooltip-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
.tooltip-enter-to,
.tooltip-leave-from {
  opacity: 1;
  transform: scale(1);
}
.w-4.h-4 {
  clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
}
</style>
