<!-- <template>
  <router-link :to="{ name: 'home' }">
    <img
      src="/assets/images/logo/rs-logo-c.png"
      alt=""
      class="w-8 h-auto"
      v-if="!this.$store.themeSettingsStore.isDark"
    />

    <img
      src="/assets/images/logo/rs-logo.png"
      alt=""
      class="w-12 h-auto"
      v-if="this.$store.themeSettingsStore.isDark"
    />
  </router-link>
</template>
<script setup>
import { useThemeSettingsStore } from "@/store/themeSettings";
const themeSettingsStore = useThemeSettingsStore();
const { isDark } = storeToRefs(themeSettingsStore);
</script> -->
<template>
  <router-link :to="{ name: 'home' }">
    <img
      src="/assets/images/logo/rs-logo-c.png"
      alt=""
      class="w-8 h-auto"
      v-if="!isDark" 
    />

    <img
      src="/assets/images/logo/rs-logo.png"
      alt=""
      class="w-12 h-auto"
      v-if="isDark"
    />
  </router-link>
</template>

<script setup>
import { useThemeSettingsStore } from "@/store/themeSettings";
import { storeToRefs } from "pinia"; // Import storeToRefs from Pinia

const themeSettingsStore = useThemeSettingsStore();
const { isDark } = storeToRefs(themeSettingsStore); // Destructure isDark reactively
</script>