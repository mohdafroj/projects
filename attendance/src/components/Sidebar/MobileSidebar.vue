<template>
  <div
    :class="`mobile-sidebar bg-white dark:bg-slate-800 ${
      themeSettingsStore.skin === 'bordered'
        ? 'border border-gray-5002'
        : 'shadow-base'
    }`"
  >
    <div class="logo-segment flex justify-between items-center px-4 py-6">
      <router-link :to="{ name: 'home' }">
        <img
          src="/assets/images/logo/rs-logo.png"
          alt=""
          v-if="!themeSettingsStore.isDark"
        />
        <img
          src="/assets/images/logo/rs-logo-white.png"
          alt=""
          v-if="themeSettingsStore.isDark"
        />
      </router-link>
      <span
        class="cursor-pointer text-slate-900 dark:text-white text-2xl"
        @click="toggleMsidebar"
      >
        <Icon icon="heroicons:x-mark" />
      </span>
    </div>

    <div class="sidebar-menu px-4 h-[calc(100%-100px)]" data-simplebar>
      <Navmenu :items="menuItems" />
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { getMenuItems } from "../../constant/menu-items.js";
import Navmenu from "./Navmenu";
import { useThemeSettingsStore } from "@/store/themeSettings";
import { Icon } from "@iconify/vue";

// Vue I18n instance
const { t } = useI18n();

// Computed property to dynamically update the menu when the language changes
const menuItems = computed(() => getMenuItems(t));

// Theme settings store (Pinia)
const themeSettingsStore = useThemeSettingsStore();

// Method to toggle mobile sidebar
const toggleMsidebar = () => {
  themeSettingsStore.toggleMsidebar();
};
</script>

<style lang="css" scoped>
.mobile-sidebar {
  @apply fixed left-0 top-0 h-full z-[9999] w-[280px];
}
</style>
