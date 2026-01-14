<template>
  <div :class="themeSettingsStore.semidark ? 'dark' : ''">
    <div
      :class="`sidebar-wrapper bg-white dark:bg-slate-800 ${themeSettingsStore.skin === 'bordered' ? 'border-r border-gray-5002 dark:border-slate-700' : 'shadow-base'} ${themeSettingsStore.sidebarCollaspe ? closeClass : openClass} ${themeSettingsStore.isMouseHovered ? 'sidebar-hovered' : ''}`"
      @mouseenter="handleMouseEnter"
      @mouseleave="handleMouseLeave"
    >
      <div
        :class="`logo-segment flex justify-between items-center bg-white dark:bg-slate-800 z-[9] py-6 sticky top-0 px-4 ${themeSettingsStore.sidebarCollaspe ? closeClass : openClass} ${themeSettingsStore.skin === 'bordered' ? 'border-b border-r border-gray-5002 dark:border-slate-700' : 'border-none'} ${themeSettingsStore.isMouseHovered ? 'logo-hovered' : ''}`"
      >
        <router-link :to="{ name: 'home' }" v-if="!themeSettingsStore.sidebarCollaspe || themeSettingsStore.isMouseHovered">
          <img
            src="/assets/images/logo/rs-logo.png"
            alt=""
            class="w-36"
            v-if="!themeSettingsStore.isDark && !themeSettingsStore.semidark"
          />
          <img
            src="/assets/images/logo/rs-logo-white.png"
            class="w-36"
            alt=""
            v-if="themeSettingsStore.isDark || themeSettingsStore.semidark"
          />
        </router-link>
        <router-link :to="{ name: 'home' }" v-if="themeSettingsStore.sidebarCollaspe && !themeSettingsStore.isMouseHovered">
          <img
            src="/assets/images/logo/rs-logo-c.png"
            class="w-8 h-auto"
            alt=""
            v-if="!themeSettingsStore.isDark && !themeSettingsStore.semidark"
          />
          <img
            src="/assets/images/logo/rs-logo-white.png"
            class="w-8 h-auto"
            alt=""
            v-if="themeSettingsStore.isDark || themeSettingsStore.semidark"
          />
        </router-link>
        <span
          class="cursor-pointer text-slate-900 dark:text-white text-2xl"
          v-if="!themeSettingsStore.sidebarCollaspe || themeSettingsStore.isMouseHovered"
          @click="toggleSidebarCollapse"
        >
          <Icon icon="heroicons-outline:menu-alt-3" />
        </span>
      </div>
      <div class="h-[60px] absolute top-[80px] nav-shadow z-[1] w-full transition-all duration-200 pointer-events-none" :class="[shadowbase ? 'opacity-100' : 'opacity-0']"></div>
      <SimpleBar
  ref="simplebarInstance"
  class="sidebar-menu px-4 h-[calc(100%-80px)]"
>
  <Navmenu :items="menuItems" />
</SimpleBar>
      <!-- <SimpleBar
        class="sidebar-menu px-4 h-[calc(100%-80px)]"
        @created="instance => simplebarInstance = instance"
      > -->
        <!-- <Navmenu :items="menuItems" />
        <div class="mb-20 "></div>
      </SimpleBar> -->
    </div>
  </div>
</template>

 <script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { useThemeSettingsStore } from '@/store/themeSettings'; // Pinia Store
import { getMenuItems } from "../../constant/menu-items.js";
import Navmenu from "./Navmenu";
import { gsap } from "gsap";
import SimpleBar from "simplebar-vue";
import Icon from "@/ui-components/Icon.vue";
import { useI18n } from "vue-i18n";
// import { ref } from "vue";

// Vue I18n
const { t, locale } = useI18n();  // Get translation function and current locale

const menuItems = computed(() => getMenuItems(t));
 // Initial menu items

// Watch for language changes and update menu dynamically
watch(locale, () => {
  menuItems.value = getMenuItems(t); // Re-fetch menu items when language changes
});



//  Pinia store
const themeSettingsStore = useThemeSettingsStore();

// Local state
const openClass = "w-[248px]";
const closeClass = "w-[72px] close_sidebar";
const shadowbase = ref(false);
const simplebarInstance = ref(null);


// Lifecycle hooks
onMounted(() => {
  if (simplebarInstance.value) {
    const scrollElement = simplebarInstance.value.$el?.querySelector('.simplebar-content-wrapper');
    if (scrollElement) {
      scrollElement.addEventListener("scroll", () => {
        if (scrollElement.scrollTop > 50) {
          scrollElement.classList.add("scroll");
          shadowbase.value = true;
        } else {
          scrollElement.classList.remove("scroll");
          shadowbase.value = false;
        }
      });
    }
  }
});


// Methods to handle mouse events
const handleMouseEnter = () => {
  themeSettingsStore.isMouseHovered = true;
};

const handleMouseLeave = () => {
  themeSettingsStore.isMouseHovered = false;
};

const toggleSidebarCollapse = () => {
  themeSettingsStore.sidebarCollaspe = !themeSettingsStore.sidebarCollaspe;
};

// Animation functions
const enterWidget = (el) => {
  gsap.fromTo(
    el,
    { x: 0, opacity: 0, scale: 0.5 },
    { x: 0, opacity: 1, duration: 0.3, scale: 1 }
  );
};

const leaveWidget = (el) => {
  gsap.fromTo(
    el,
    { x: 0, opacity: 1, scale: 1 },
    { x: 0, opacity: 0, duration: 0.3, scale: 0.5 }
  );
};
</script> 




<style lang="scss" scoped>
.sidebar-wrapper {
  @apply fixed left-0 right-0 top-0 h-screen z-[999];
  transition: width 0.2s cubic-bezier(0.39, 0.575, 0.565, 1);
  will-change: width;
}

.nav-shadow {
  background: linear-gradient(
    rgb(255, 255, 255) 5%,
    rgba(255, 255, 255, 75%) 45%,
    rgba(255, 255, 255, 20%) 80%,
    transparent
  );
}

.dark {
  .nav-shadow {
    background: linear-gradient(
      rgba(#1e293b, 100%) 5%,
      rgba(#1e293b, 75%) 45%,
      rgba(#1e293b, 20%) 80%,
      transparent
    );
  }
}

.sidebar-wrapper.sidebar-hovered {
  width: 248px !important;
}

.logo-segment.logo-hovered {
  width: 248px !important;
}
</style>
