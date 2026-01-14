<template>
    <Rbac v-if="themeSettingsStore.login == 'rbac'" />
    <Signin v-else :themeSettings="themeSettingsStore" :dashboardPage="dashboardPage" />
</template>
<script setup>
import { computed, onMounted } from 'vue';
import { Signin } from '@sds/oneui-layout'
import { useRoute } from 'vue-router';
import Rbac from "./Rbac";
import { useThemeSettingsStore } from '@/store/themeSettings.js';
const themeSettingsStore = useThemeSettingsStore();
const route = useRoute()

onMounted( async ()=> {
    const login = route.query?.login;
    if ( login == 'rbac' ) {
        themeSettingsStore.setRBAC(login);
    }
});
//const dashboardPage = "/dashboard";
const normalizeBase = (b) => {
  if (!b) return '';                 // no base
  b = b.trim();
  b = b.replace(/^\/+|\/+$/g, '');   // strip leading/trailing slashes
  return b ? `/${b}` : '';
}
const BASE = normalizeBase(import.meta.env.VITE_BASE_PATH);
const dashboardPath = computed(() => `${BASE}/dashboard`);
const dashboardPage = computed(() => `${window.location.origin}${dashboardPath.value}`);
</script>