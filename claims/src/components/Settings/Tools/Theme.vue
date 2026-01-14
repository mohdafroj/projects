<template>
  <div>
    <div class="col-span-12 text-slate-600 dark:text-slate-300 text-base mb-2">
      Theme
    </div>
    <div class="grid md:grid-cols-3 grid-cols-1 gap-3">
      <div v-for="(item, i) in thems" :key="i">
        <label
          :for="`theme_id${i}`"
          class="flex items-center text-sm text-slate-500 dark:text-slate-400 cursor-pointer"
        >
          <input
            class="hidden"
            type="radio"
            name="sidebar"
            :id="`theme_id${i}`"
            :value="item.value"
            v-model="theme"
          />

          <span
            :class="item.value === theme ? 'shadow-inset-4' : ''"
            class="flex-none h-4 w-4 bg-white dark:bg-transparent rounded-full border border-secondary-500 inline-block mr-3 transition-all duration-150"
          ></span>
          {{ item.label }}
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useThemeSettingsStore } from '@/store/themeSettings'; 

//  Pinia store
const themeSettingsStore = useThemeSettingsStore();

//  theme
const theme = ref(themeSettingsStore.theme);

const thems = [
  {
    value: "light",
    label: "Light",
  },
  {
    value: "dark",
    label: "Dark",
  },
];

// Watch for changes in theme value
watch(theme, (newTheme) => {
  themeSettingsStore.theme = newTheme;
  document.body.classList.remove("light", "dark");
  document.body.classList.add(newTheme);
  themeSettingsStore.isDark = newTheme === "dark";
  localStorage.setItem("theme", newTheme);
}, { immediate: true });
</script>
<style lang="css">
.shadow-inset-4 {
  box-shadow: inset 0 0 0 4px #111112;
}
.dark .shadow-inset-4 {
  box-shadow: inset 0 0 0 4px #ccc;
}
</style>
