<template>
    <div class="relative inline-block text-left">
      <div>
        <button
          @click="toggleDropdown"
          class="inline-flex justify-between w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-indigo-500"
          type="button"
        >
          <span>{{ selected }}</span>
          <svg
            class="-mr-1 ml-2 h-5 w-5"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
          >
            <path
              fill-rule="evenodd"
              d="M5.23 7.21a.75.75 0 011.06 0L10 10.293l3.71-3.08a.75.75 0 011.06 1.06l-4.25 3.5a.75.75 0 01-1.06 0l-4.25-3.5a.75.75 0 010-1.06z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
  
      <div
        v-if="isOpen"
        class="absolute right-0 z-10 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none"
        role="menu"
      >
        <div class="py-1" role="none">
          <template v-for="option in options" :key="option.value">
            <a
              @click="selectOption(option)"
              class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
              role="menuitem"
              >{{ option.label }}</a
            >
          </template>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, watch } from 'vue';
  
  const props = defineProps({
    options: {
      type: Array,
      required: true,
    },
    modelValue: {
      type: String,
      default: '',
    },
  });
  
  const emit = defineEmits(['update:modelValue']);
  
  const selected = ref(props.modelValue);
  const isOpen = ref(false);
  
  const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
  };
  
  const selectOption = (option) => {
    selected.value = option.label;
    emit('update:modelValue', option.value); 
    isOpen.value = false; 
  };
  
  // Watch for changes in modelValue prop
  watch(() => props.modelValue, (newValue) => {
    selected.value = newValue;
  });
  </script>
    