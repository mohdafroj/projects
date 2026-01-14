<template>
  <ul>
    <li
      v-for="(item, i) in newMenulist"
      :key="i"
      :class="{
        'menu-item-has-children': item.child,
        'menu-item-has-children has-megamenu': item.megamenu
      }"
    >
      <!-- If no submenu or mega menu -->
      <router-link :to="item.link" v-if="!item.child && !item.megamenu" v-slot="{ isActive }">
        <div class="flex flex-1 items-center space-x-[6px] ">
          <span class="icon-box" v-if="item.icon">
            <Icon :icon="item.icon" />
          </span>
          <div class="text-box" v-if="item.title">{{ item.title }}</div>
          <!-- Apply active class if the link is active -->
          <span :class="isActive ? 'text-primary-500' : ''"></span>
        </div>
      </router-link>

      <!-- If there are child items or mega menu -->
      <a href="javascript:void(0);" v-if="item.child || item.megamenu">
        <div class="flex flex-1 items-center space-x-[6px]">
          <span class="icon-box" v-if="item.icon">
            <Icon :icon="item.icon" />
          </span>
          <div class="text-box" v-if="item.title">{{ item.title }}</div>
        </div>
        <div class="flex-none text-sm ml-3 leading-[1]" v-if="item.child || item.megamenu">
          <Icon icon="heroicons-outline:chevron-down" />
        </div>
      </a>

      <!-- Submenu or megamenu sections -->
      <ul class="sub-menu" v-if="item.child">
        <li v-for="(childitem, index) in item.child" :key="index" :class="childitem.submenu ? 'menu-item-has-children' : ''">
          <router-link :to="childitem.childlink" v-if="!childitem.submenu">
            <div class="flex space-x-2 items-start">
              <Icon :icon="childitem.childicon" class="leading-[1] text-base" />
              <span class="leading-[1]">{{ childitem.childtitle }}</span>
            </div>
          </router-link>
          <a href="javascript:void(0);" v-if="childitem.submenu">{{ childitem.childtitle }}</a>
          <ul class="sub-menu" v-if="childitem.submenu">
            <li v-for="(subitem, subindex) in childitem.submenu" :key="subindex">
              <router-link :to="subitem.subMenuLink">{{ subitem.submenutitle }}</router-link>
            </li>
          </ul>
        </li>
      </ul>

      <!-- Mega menu section -->
      <div class="rt-mega-menu" v-if="item.megamenu">
        <div class="flex flex-wrap space-x-8 justify-between">
          <div v-for="(m_item, m_i) in item.megamenu" :key="m_i">
            <div class="text-sm font-medium text-slate-900 dark:text-white mb-2 flex space-x-1 items-center">
              <Icon :icon="m_item.megamenuicon" />
              <span>{{ m_item.megamenutitle }}</span>
            </div>
            <router-link
              v-for="(ms_item, ms_i) in m_item.singleMegamenu"
              :to="ms_item.m_childlink"
              :key="ms_i"
              class="flex items-center space-x-2 text-[15px] leading-6"
              v-slot="{ isActive }"
            >
              <span class="h-[6px] w-[6px] rounded-full border border-slate-600 dark:border-white inline-block flex-none" />
              <span :class="isActive ? ' text-slate-900 dark:text-white font-medium' : 'text-gray-800 dark:text-slate-300'">
                {{ ms_item.m_childtitle }}
              </span>
            </router-link>
          </div>
        </div>
      </div>
    </li>
  </ul>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { topMenu as importedTopMenu } from "../../../constant/data.js"; 
import Icon from "@/ui-components/Icon.vue";


const route = useRoute();

const localTopMenu = ref(importedTopMenu);

// Compute filtered menu list
const newMenulist = computed(() => {
  if (!Array.isArray(localTopMenu.value)) {
    console.error('localTopMenu is not an array');
    return [];
  }
  return localTopMenu.value.filter(item => !item.isHeadr); 
});
</script>




<style lang="scss" scoped>
.main-menu {
  > ul {
    > li {
      @apply inline-block relative;
      > a {
        @apply relative flex capitalize items-start text-sm font-medium leading-6 text-gray-800 dark:text-slate-300 2xl:px-6 xl:px-5 py-6  transition-all duration-150;
        .icon-box {
          @apply text-slate-500 dark:text-slate-300 transition-all duration-150 text-lg;
        }
      }
      &:hover {
        > a {
          @apply text-primary-500;
          .icon-box {
            @apply text-primary-500;
          }
        }
      }
      &.has-megamenu {
        @apply static;
      }
    }
  }
}

.main-menu > ul > li.menu-item-has-children > ul.sub-menu,
.main-menu > ul > li.menu-item-has-children > .rt-mega-menu {
  @apply absolute  left-0 min-w-[178px] w-max top-[110%] px-4 py-3  bg-white  
  rounded-[4px] dark:bg-slate-800 z-[999] invisible opacity-0 transition-all duration-150
  shadow-base2;
}
.main-menu > ul > li.menu-item-has-children > .rt-mega-menu {
  @apply max-w-[1170px]  left-1/2  -translate-x-1/2;
}
.main-menu > ul > li.menu-item-has-children > .rt-mega-menu {
  @apply w-full;
}
.main-menu > ul > li.menu-item-has-children:hover > ul.sub-menu,
.main-menu > ul > li.menu-item-has-children:hover > .rt-mega-menu {
  @apply top-full visible opacity-100;
}
.main-menu > ul > li.menu-item-has-children > ul.sub-menu li {
  @apply relative pb-2 last:pb-0;
}
.main-menu > ul > li.menu-item-has-children > ul.sub-menu li a {
  @apply text-sm  font-normal   text-gray-800 dark:text-slate-300 dark:hover:text-primary-500 capitalize py-1 last:pb-0 block hover:text-primary-500;
}
.rt-mega-menu {
  a {
    @apply dark:text-slate-300 dark:hover:text-primary-500 text-sm  py-[6px];
  }
}
</style>
