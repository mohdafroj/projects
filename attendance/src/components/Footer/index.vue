<template>
  <footer :class="footerClass">
    <div class="site-footer px-6 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-300 py-4">
      <div class="grid md:grid-cols-2 grid-cols-1 md:gap-5">
        <div class="text-center md:text-start text-sm">
          COPYRIGHT &copy; 2025 Rajyasabha, All rights Reserved
        </div>
        <div class="md:text-right text-center text-sm">
          <span v-html="$t('footer.crafted_by', { team: teamLink })"></span>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue';
import { useThemeSettingsStore } from '@/store/themeSettings'; 
import { useI18n } from "vue-i18n";
const { t } = useI18n();

// Define the clickable team name as an HTML string

const teamLink = computed(() =>
  `<a href="#" target="_blank" class="text-violet-600 font-semibold">${t('team')}</a>`
);



// Use Pinia store
const themeSettingsStore = useThemeSettingsStore();

// Computed property for footer class
const footerClass = computed(() => {
  switch (themeSettingsStore.footerType) {
    case "sticky":
      return "sticky bottom-0 z-[999]";
    case "static":
      return "static";
    case "hidden":
      return "hidden";
    default:
      return "";
  }
});
</script>


