<template>
  <div>
    <div class="col-span-12 text-slate-600 dark:text-slate-300 text-base mb-2">
      Menu layout
    </div>
    <div class="grid grid-cols-3 gap-3">
      <div v-for="(item, i) in layouts" :key="i">
        <label
          :for="`menu_layout_id${i}`"
          class="flex items-center text-sm text-slate-500 dark:text-slate-400 cursor-pointer"
        >
          <input
            class="hidden"
            type="radio"
            name="menulayout"
            :id="`menu_layout_id${i}`"
            :value="item.value"
            v-model="layout"
          />
          <span
            :class="item.value === layout ? 'shadow-inset-4' : ''"
            class="h-4 w-4 bg-white rounded-full dark:bg-transparent border border-secondary-500 inline-block mr-3  transition-all duration-150"
          ></span>
          {{ item.label }}
        </label>
      </div>
    </div>
    <div
      class="flex justify-between mt-6 items-center"
      v-if="themeSettingsStore.menuLayout === 'vertical' && !themeSettingsStore.sidebarHidden"
    >
      <div class="text-slate-600 text-base dark:text-slate-300">
        Menu Collapsed
      </div>
      <div>
        <label
          :class="menucollaspse ? 'bg-primary-700' : 'bg-secondary-700'"
          class="relative inline-flex h-6 w-[46px] items-center rounded-full transition-all duration-150 cursor-pointer"
        >
          <input type="checkbox" v-model="menucollaspse" class="hidden" />
          <span
            :class="menucollaspse ? 'translate-x-6 ' : 'translate-x-[2px]'"
            class="inline-block h-5 w-5 transform rounded-full bg-white transition-all duration-150"
          />
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useThemeSettingsStore } from '@/store/themeSettings'; // Pinia Store

//  Pinia store
const themeSettingsStore = useThemeSettingsStore();

// Local state
const layout = ref(themeSettingsStore.menuLayout);
const menucollaspse = ref(themeSettingsStore.sidebarCollaspe);
const menuHideen = ref(themeSettingsStore.sidebarHidden);
const layouts = [
  { value: 'vertical', label: 'Vertical' },
  { value: 'horizontal', label: 'Horizontal' },
];

// Watchers
watch(layout, (newLayout) => {
  themeSettingsStore.menuLayout = newLayout;
  document.documentElement.setAttribute('menu-layout', newLayout);
  localStorage.setItem('menuLayout', newLayout);
}, { immediate: true });

watch(menuHideen, (newHidden) => {
  themeSettingsStore.sidebarHidden = newHidden;
}, { immediate: true });

watch(menucollaspse, (newCollapse) => {
  themeSettingsStore.sidebarCollaspe = newCollapse;
}, { immediate: true });
</script>


