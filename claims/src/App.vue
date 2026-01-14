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
  //console.log(response);
  document.addEventListener("click", (e) => {
    const tag = e.target.tagName;
    if (["A", "BUTTON"].includes(tag)) {
      if (window.getSelection) {
        window.getSelection().removeAllRanges();
      } else if (document.selection) {
        document.selection.empty();
      }
    }
  });
});
</script>

<style></style>

