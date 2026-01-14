<template>
  <div>
    <span
      v-if="!themeSettingsStore.isOpenSettings"
      @click="themeSettingsStore.isOpenSettings = !themeSettingsStore.isOpenSettings"
      class="h-[28px] w-[28px] lg:h-[32px] lg:w-[32px] lg:bg-gray-500-f7 bg-slate-50 dark:bg-slate-900 lg:dark:bg-slate-900 dark:text-white text-slate-900 cursor-pointer rounded-full text-[24px] flex flex-col items-center justify-center transform rotate-90"
    >
      <Icon icon="heroicons:cog-6-tooth" class="text-slate-900 text-xl animate-spin dark:text-slate-100" />
    </span>
    
    <Transition name="lefttranslate">
      <div
        v-show="themeSettingsStore.isOpenSettings"
        class="setting-wrapper fixed right-0 top-0 md:w-[400px] w-[300px] bg-white dark:bg-slate-800 h-screen z-[9999] px-6 md:pb-6 pb-[100px] shadow-base2 dark:shadow-base3 border border-gray-5002 dark:border-slate-700"
     
      >  <!-- data-simplebar -->
       
        <header
          class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 -mx-6 px-6 py-[15px] mb-6"
        >
          <div>
            <span class="block text-xl text-slate-900 font-medium dark:text-[#eee]">
              Theme Settings
            </span>
          </div>
          <div
            class="cursor-pointer text-2xl text-slate-800 dark:text-slate-200"
            @click="themeSettingsStore.isOpenSettings = false"
          >
            <Icon icon="heroicons-outline:x" />
          </div>
        </header>
        
        <div class="space-y-4">
          <Skin />
          <Theme />
          <hr class="-mx-6 border-slate-200 dark:border-slate-700" />
          <Semidark />
          <Monochrome />
          <hr class="-mx-6 border-slate-200 dark:border-slate-700" />
          <div class="xl:block hidden">
            <MenuLayout />
          </div>
          <hr class="-mx-6 border-slate-200 dark:border-slate-700" />
          <Navbar />
          <Footer v-if="windowWidth > 768" />
        </div>
      </div>
    </Transition>
    
    <Transition name="overlay-fade">
      <div
        v-if="themeSettingsStore.isOpenSettings"
        class="overlay bg-white bg-opacity-0 fixed inset-0 z-[999]"
        @click="themeSettingsStore.isOpenSettings = false"
      ></div>
    </Transition>
  </div>
</template>

<script setup>
import { useThemeSettingsStore } from '@/store/themeSettings';
import { useWindow } from '@/mixins/window'; 
import Icon from "@/ui-components/Icon.vue";
import Skin from "./Tools/Skin.vue";
import Theme from "./Tools/Theme.vue";
import Semidark from "./Tools/Semidark.vue";
import Monochrome from "./Tools/Monochrome.vue";
import MenuLayout from "./Tools/MenuLayout.vue";
import Navbar from "./Tools/Navbar.vue";
import Footer from "./Tools/Footer.vue";

// Pinia store
const themeSettingsStore = useThemeSettingsStore();

//  window width from the custom hook
const windowWidth = useWindow();


</script>

<style scoped>
@keyframes lefttranslate {
  0% {
    opacity: 0;
    transform: translateX(20px);
  }
  100% {
    opacity: 1;
    transform: translateX(0px);
  }
}

@keyframes overlay-fade {
  0% {
    opacity: 0;
  }
  100% {
    opacity: 1;
  }
}

.overlay-fade-enter-active {
  animation: overlay-fade 0.3s;
}
.overlay-fade-leave-active {
  animation: overlay-fade 0.3s reverse;
}

.lefttranslate-enter-active {
  animation: lefttranslate 0.24s;
}

.lefttranslate-leave-active {
  animation: lefttranslate 0.24s reverse;
}
</style>
