   <template>
    <header :class="navbarTypeClass">
      <div
        :class="`app-header md:px-4 px-[15px] shadow-base dark:shadow-base3 bg-gradient-to-r from-blue-50/90 to-blue-500/90 dark:bg-gradient-to-r dark:from-slate-900/90 dark:to-slate-700/90 backdrop-blur-sm ${borderSwicthClass} ${themeSettingsStore.navbarColor} ${themeSettingsStore.menuLayout === 'horizontal' && windowWidth > 1280 ? 'py-1' : 'md:py-4 py-3'}`"
      >
      <!-- bg-gradient-to-r from-violet-50/90 to-gray-200/90 -->
        <div class="flex justify-between items-center h-full">
          <div
            v-if="themeSettingsStore.menuLayout === 'vertical'"
            class="flex items-center md:space-x-4 space-x-2"
          >
            <button
              class="mr-5 text-xl text-slate-900 dark:text-white"
              v-if="themeSettingsStore.sidebarCollaspe && windowWidth > 1280"
              @click="themeSettingsStore.sidebarCollaspe = false"
            >
              <Icon icon="akar-icons:arrow-right" v-if="!themeSettingsStore.direction" />
              <Icon icon="akar-icons:arrow-left" v-if="themeSettingsStore.direction" />
            </button>
            <MobileLogo v-if="windowWidth < 1280" />
            <HandleMobileMenu v-if="windowWidth < 1280 && windowWidth > 768" />
            <SearchModal />
          </div>
          <div v-if="themeSettingsStore.menuLayout === 'horizontal'" class="flex items-center space-x-4">
            <Logo v-if="windowWidth > 1280" />
            <MobileLogo v-else />
            <HandleMobileMenu v-if="windowWidth < 1280" />
          </div>
          <Mainnav v-if="themeSettingsStore.menuLayout === 'horizontal' && windowWidth > 1280" />
          <div class="nav-tools flex items-center lg:space-x-5 space-x-3">
            <LanguageVue />
            <SwitchDark />
            <Message v-if="windowWidth > 768" />
            <Notification v-if="windowWidth > 768" />
            <Settings />
            <Profile v-if="windowWidth > 768" />
            <HandleMobileMenu v-if="windowWidth < 768" />
          </div>
        </div>
      </div>
    </header>
  </template>
  
  <script setup>
  import { computed } from 'vue';
  import { useThemeSettingsStore } from '@/store/themeSettings'; 
  import { useWindow } from '@/mixins/window'; 
  import Icon from "@/ui-components/Icon.vue";
  import MobileLogo from "./Navtools/MobileLogo.vue";
  import HandleMobileMenu from "./Navtools/HandleMobileMenu.vue";
  import SearchModal from "./Navtools/SearchModal.vue";
  import LanguageVue from "./Navtools/Language.vue";
  import Logo from "./Navtools/Logo.vue";
  import Mainnav from "./horizental-nav.vue";
  import Profile from "./Navtools/Profile.vue";
  import Notification from "./Navtools/Notification.vue";
  import Message from "./Navtools/Message.vue";
  import SwitchDark from "./Navtools/SwitchDark.vue";
  import Settings from "../Settings/index.vue";
  
  //  Pinia 
  const themeSettingsStore = useThemeSettingsStore();
  
  // Get the window width from the custom hook
  const windowWidth = useWindow();
  console.log("windowWidth:", windowWidth.value);
console.log("themeSettingsStore:", themeSettingsStore);
console.log("Window Width:", windowWidth.value);
console.log("Menu Layout:", themeSettingsStore.menuLayout);
  // Computed properties for navbar type and border style
  const navbarTypeClass = computed(() => {
    switch (themeSettingsStore.navbarType) {
      case "floating":
        return "floating";
      case "sticky":
        return "sticky top-0 z-[999]";
      case "static":
        return "static";
      case "hidden":
        return "hidden";
      default:
        return "sticky top-0";
    }
  });
  
  const borderSwicthClass = computed(() => {
    if (
      themeSettingsStore.skin === "bordered" &&
      themeSettingsStore.navbarType !== "floating"
    ) {
      return "border-b border-gray-5002 dark:border-slate-700";
    } else if (
      themeSettingsStore.skin === "bordered" &&
      themeSettingsStore.navbarType === "floating"
    ) {
      return "border border-gray-5002 dark:border-slate-700";
    } else {
      return "dark:border-b dark:border-slate-700 dark:border-opacity-60";
    }
  });
  </script>
  
  <style lang="scss" scoped>
  .floating .app-header {
    @apply md:mx-6 md:my-8 mx-[15px] my-[15px] rounded-md opacity-50 backdrop-blur-sm;
  }
  
  .dark.app-header {
    background: #1e293b !important;
  }
  </style>
  