<template>
  <router-view />
</template>

<script setup>
import { onMounted } from "vue";
import { useThemeSettingsStore } from "@/store/themeSettings";
import { fetchPublicKey } from "@/composables/encryption";
import { cookieService } from "@sds/oneui-layout";
// Initialize store
//const themeSettingsStore = useThemeSettingsStore();
onMounted( async () => {
  await cookieService.handleLogout();
  const response = await fetchPublicKey();
  console.log(response);
});
</script>

<style></style>

