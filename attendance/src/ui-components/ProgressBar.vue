<template>
  <div class="relative">
    <!-- Title (Optional) -->
    <span
      v-if="title"
      class="block text-slate-500 font-medium text-sm tracking-[0.01em] mb-2"
    >
      {{ title }}
    </span>
    <!-- Progress Bar Container -->
    <div
      class="w-full rounded-[999px] overflow-hidden bg-opacity-50 bg-gray-300"
      :class="height"
    >
      <!-- Progress Bar -->
      <div
        class="h-full flex items-center justify-center text-white text-[12px]"
        :class="[barColor, { 'animated-strip': animate }]"
        :style="barStyle"
      >
        <!-- Show  progress value inside the bar if showValue is true -->
        <span v-if="showValue">{{ animatedValue + "%" }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";

const props = defineProps({
  value: {
    type: Number,
    default: 0,
    validator: (v) => v >= 0 && v <= 100,
  },
  barColor: {
    type: String,
    default: "bg-blue-500",
  },
  title: {
    type: String,
    default: "",
  },
  height: {
    type: String,
    default: "h-3",
  },
  animate: {
    type: Boolean,
    default: false,
  },
  showValue: {
    type: Boolean,
    default: false,
  },
  striped: {
    type: Boolean,
    default: false,
  },
});

const animatedValue = ref(0);

const barStyle = computed(() => {
  const style = {
    width: `${animatedValue.value}%`,
    transition: "width 1s ease-in-out",
  };

  if (props.striped) {
    //  striped background if 'striped' is true
    style.backgroundImage =
      "linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent)";
    style.backgroundSize = "1rem 1rem";
  }

  return style;
});

// Animate the progress bar value on mount
onMounted(() => {
  setTimeout(() => {
    animatedValue.value = props.value;
  }, 200); 
});
</script>

<style scoped>
/* Define keyframes for striped animation */
@keyframes progress-bar-stripes {
  0% {
    background-position: 1rem 0;
  }

  100% {
    background-position: 0 0;
  }
}

/* Apply the striped animation class */
.animated-strip {
  animation: progress-bar-stripes 1s linear infinite;
}
</style>
