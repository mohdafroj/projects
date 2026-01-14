<template>
  <div>
    <div class="flex justify-between mt-6 items-center">
      <div class="text-slate-600 text-base dark:text-slate-300">Monochrome Mode</div>
      <div>
        <label
          :class="monochrome ? 'bg-primary-700' : 'bg-secondary-700'"
          class="relative inline-flex h-6 w-[46px] items-center rounded-full transition-all duration-150 cursor-pointer"
        >
          <input type="checkbox" @change="toggleMonochrome" v-model="monochrome" class="hidden" />
          <span
            :class="monochrome ? 'translate-x-6' : 'translate-x-[2px]'"
            class="inline-block h-5 w-5 transform rounded-full bg-white transition-all duration-150"
          />
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useThemeSettingsStore } from '@/store/themeSettings'; // Pinia store

//  store
const themeSettingsStore = useThemeSettingsStore();

// Initialize the monochrome state from the store
const monochrome = ref(themeSettingsStore.monochrome);

// Watch for changes in the monochrome value and call the store's method
watch(monochrome, (newValue) => {
  themeSettingsStore.toggleMonochrome(); 
});
</script>
