<template>
  <div
    class="cursor-pointer text-slate-900 dark:text-white text-2xl"
    @click="toggleMsidebar"
  >
    <Icon icon="heroicons-outline:menu-alt-3" />
  </div>
</template>
<script setup>
import Icon from "@/ui-components/Icon.vue";
import { useThemeSettingsStore } from "@/store/themeSettings";

const themeSettingsStore = useThemeSettingsStore()

const toggleMsidebar = () => {
  themeSettingsStore.toggleMsidebar()
};
</script>

