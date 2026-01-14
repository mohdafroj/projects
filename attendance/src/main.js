// import "animate.css";
// import 'ag-grid-community/styles/ag-grid.css';
// import 'ag-grid-community/styles/ag-theme-alpine.css';
import "simplebar/dist/simplebar.min.css";
import { createApp } from "vue";
import VueGoodTablePlugin from "vue-good-table-next";
import "vue-good-table-next/dist/vue-good-table-next.css";
import "@vueform/multiselect/themes/default.css";
import i18n from "./i18n";
import clickOutside from "@/utils/clickOutside.js";
import SDSLayouts from "@sds/oneui-layout";
import "@sds/oneui-layout/dist/style.css";
import "@sds/oneui-common-ui/dist/style.css"
import VueApexCharts from "vue3-apexcharts";
import VueClickAway from "vue3-click-away";
import App from "./App.vue";
import "./assets/styles/auth.css";
import "./assets/styles/tailwind.css";
import router from "./router";
import { VueQueryPlugin, QueryClient } from "@tanstack/vue-query";
// import VCalendar from "v-calendar";
// import { setupCalendar } from 'v-calendar';
import { createPinia } from "pinia";
// import "v-calendar/dist/style.css";
import "vue3-toastify/dist/index.css";
import Vue3Toastify from "vue3-toastify";
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";
import "quill/dist/quill.snow.css";

// import Quill from "quill";
// import ImageResize from "quill-image-resize-module";

// Quill.register("modules/imageResize", ImageResize);
const pinia = createPinia();
// Register the plugin globally
const queryClient = new QueryClient();

const app = createApp(App)
  .use(SDSLayouts, {
    BASE_URL_LOGIN: import.meta.env.VITE_BASE_URL_LOGIN,
    BASE_URL: import.meta.env.VITE_BASE_URL,
    REDIRECT_URL: import.meta.env.VITE_REDIRECT_URL
  })
  .use(VueQueryPlugin, { queryClient })
  .use(pinia)
  .use(i18n)
  .use(Vue3Toastify, {
    autoClose: 3000,
    position: "top-right",
    theme: "light",
    pauseOnFocusLoss: false,
    pauseOnHover: true,
    closeOnClick: true,
    draggable: true,
  })
  .use(router)
  .use(VueClickAway)
  .use(VueGoodTablePlugin)
  .use(VueApexCharts);

app.config.globalProperties.$store = {};
app.component("QuillEditor", QuillEditor);
app.directive("click-outside", clickOutside);
app.mount("#app");

import { useThemeSettingsStore } from "@/store/themeSettings";
const themeSettingsStore = useThemeSettingsStore();
// check localStorage theme for dark light bordered
if (localStorage.theme === "dark") {
  document.body.classList.add("dark");
  themeSettingsStore.theme = "dark";
  themeSettingsStore.isDark = true;
} else {
  document.body.classList.add("light");
  themeSettingsStore.theme = "light";
  themeSettingsStore.isDark = false;
}
if (localStorage.semiDark === "true") {
  document.body.classList.add("semi-dark");
  themeSettingsStore.semidark = true;
  themeSettingsStore.semiDarkTheme = "semi-dark";
} else {
  document.body.classList.add("semi-light");
  themeSettingsStore.semidark = false;
  themeSettingsStore.semiDarkTheme = "semi-light";
}
// check loacl storege for menuLayout
if (localStorage.menuLayout === "horizontal") {
  themeSettingsStore.menuLayout = "horizontal";
} else {
  themeSettingsStore.menuLayout = "vertical";
}

// check skin  for localstorage
if (localStorage.skin === "bordered") {
  themeSettingsStore.skin = "bordered";
  document.body.classList.add("skin--bordered");
} else {
  themeSettingsStore.skin = "default";
  document.body.classList.add("skin--default");
}
// check direction for localstorage
if (localStorage.direction === "true") {
  themeSettingsStore.direction = false;
  document.documentElement.setAttribute("dir", "ltr");
} else {
  themeSettingsStore.direction = false;
  document.documentElement.setAttribute("dir", "ltr");
}

// Check if the monochrome mode is set or not
if (localStorage.getItem("monochrome") !== null) {
  themeSettingsStore.monochrome = true;
  document.getElementsByTagName("html")[0].classList.add("grayscale");
}
localStorage.removeItem("direction");
themeSettingsStore.initEncrypted(); //Immediate initiate encryption
