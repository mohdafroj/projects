<template>
    <button
      :disabled="disabled || loading"
      @click="handleClick"
      :class="[
        'flex items-center justify-center font-medium rounded-full shadow transition-colors duration-200 focus:outline-none',
        sizeClass,
        colorClass,
        (disabled || loading) && 'opacity-50 cursor-not-allowed',
      ]"
    >
      <!-- Loader spinner if loading -->
      <Icon
        v-if="loading"
        icon="mdi:loading"
        class="animate-spin mr-2"
        :class="iconSizeClass"
      />
      
      <!-- Optional Icon (not shown if no icon or loading) -->
      <Icon
        v-else-if="icon"
        :icon="icon"
        class="mr-2"
        :class="iconSizeClass"
      />
  
      <!-- Button Text -->
      <span>{{ label }}</span>
    </button>
  </template>
  
  <script setup>
  import { computed } from "vue";
  import { Icon } from "@iconify/vue";
  
  const emit = defineEmits(["click"]);
  
  const props = defineProps({
    label: { type: String, required: true },
    icon: { type: String, default: null },
    size: {
      type: String,
      default: "md",
      validator: (val) => ["sm", "md", "xl"].includes(val),
    },
    color: {
      type: String,
      default: "green",
      validator: (val) =>
        [
          "green",
          "green-outline",
          "gray",
          "gray-outline",
          "blue",
          "blue-outline",
          "red",
          "red-outline",
        ].includes(val),
    },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
  });
  
  const handleClick = (e) => {
    if (!props.disabled && !props.loading) emit("click", e);
  };
  
  const sizeClass = computed(() => ({
    sm: "px-4 py-2 text-sm",
    md: "px-6 py-3 text-base",
    xl: "px-8 py-4 text-lg",
  }[props.size]));
  
  const iconSizeClass = computed(() => ({
    sm: "text-base",
    md: "text-xl",
    xl: "text-2xl",
  }[props.size]));
  
  const colorClass = computed(() => ({
    green: "bg-green-600 hover:bg-green-700 text-white",
    "green-outline": "border border-green-600 text-green-600 hover:bg-green-100",
    gray: "bg-gray-600 hover:bg-gray-700 text-white",
    "gray-outline": "border border-gray-600 text-gray-600 hover:bg-gray-100",
    blue: "bg-blue-600 hover:bg-blue-700 text-white",
    "blue-outline": "border border-blue-600 text-blue-600 hover:bg-blue-100",
    red: "bg-red-600 hover:bg-red-700 text-white",
    "red-outline": "border border-red-600 text-red-600 hover:bg-red-100",
  }[props.color]));
  </script>
  