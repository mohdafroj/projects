<template>
    <Rbac v-if="themeSettingsStore.login == 'rbac'" />
    <Signin v-else :themeSettings="themeSettingsStore" :dashboardPage="dashboardPage" />
</template>
<script setup>
import { onMounted } from 'vue';
import { Signin } from '@sds/oneui-layout'
import { useRoute } from 'vue-router';
import Rbac from "./Rbac";
import { DS } from '@/constant/links';
import { useThemeSettingsStore } from '@/store/themeSettings.js';
const themeSettingsStore = useThemeSettingsStore();
const route = useRoute()

onMounted( async ()=> {
    const login = route.query?.login;
    if ( login == 'rbac' ) {
        themeSettingsStore.setRBAC(login);
    }
});
const base = import.meta.env.VITE_BASE_PATH || '';
const dashboardPage = base.replace(/\/$/, '') + DS + 'home';
</script>