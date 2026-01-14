 <template>
  <ul>
    <!-- <li
      v-for="(item, i) in items"
      :key="i"
      :class="`
      ${item.child ? 'item-has-children' : ''}
      ${activeSubmenu === i ? 'open' : ''}
      ${this.$route.name === item.link ? 'menu-item-active' : ''}
      
      `"
      class="single-sidebar-menu"
    > -->
    <li
      v-for="(item, i) in items"
      :key="i"
      :class="[
        item.child ? 'item-has-children' : '',
        activeSubmenu === i ? 'open' : '',
        route.name === item.link ? 'menu-item-active' : ''
      ]"
      class="single-sidebar-menu"
    >
      <!--  ?? single menu with no childred !! -->  

      <router-link
        :to="`${item.link}`"
        class="menu-link"
        v-if="!item.child && !item.isHeadr"
      >
        <span class="menu-icon flex-grow-0" v-if="item.icon">
          <Icon :icon="item.icon"
        /></span>
        <div class="text-box flex-grow" v-if="item.title">
          {{ item.title }}
        </div>
        <span class="menu-badge" v-if="item.badge">{{ item.badge }}</span>
      </router-link>

      <!-- -- ?? only for menulabel ??   -->
      <div v-else-if="item.isHeadr && !item.child" class="menulabel">
        {{ item.title }}
      </div>
      <!-- -- !!sub menu parent li !!  -->
      <div
        class="menu-link"
        v-else
        :class="
          activeSubmenu === i ? 'parent_active not-collapsed' : 'collapsed'
        "
        @click="toggleSubmenu(i)"
      >
        <div class="flex-1 flex items-start">
          <span class="menu-icon" v-show="item.icon">
            <Icon :icon="item.icon"
          /></span>
          <div class="text-box" v-if="item.title">{{ item.title }}</div>
        </div>
        <div class="flex-0">
          <div
            class="menu-arrow transform transition-all duration-300"
            :class="
              activeSubmenu === i
                ? ' rotate-90 '
                : ''
            "
          >
            <Icon icon="heroicons-outline:chevron-right" />
          </div>
        </div>
      </div>
      <Transition
        enter-active-class="submenu_enter-active"
        leave-active-class="submenu_leave-active"
        @before-enter="beforeEnter"
        @enter="enter"
        @after-enter="afterEnter"
        @before-leave="beforeLeave"
        @leave="leave"
        @after-leave="afterLeave"
      >
        <!-- -- !! SubMenu !! -- -->
        <ul calss="sub-menu " v-if="i === activeSubmenu">
          <li
            v-for="(ci, index) in item.child"
            :key="index"
            class="block pl-4 pr-1  mb-4 first:mt-4"
          >
            <router-link :to="ci.childlink" v-slot="{ isActive }">
              <span
                class="text-sm flex space-x-3  items-center transition-all duration-150"
                :class="
                  isActive
                    ? ' text-slate-900 dark:text-white font-medium'
                    : 'text-slate-600 dark:text-slate-300'
                "
              >
                <span
                  class="h-2 w-2 rounded-full border border-slate-600 dark:border-slate-300 inline-block flex-none"
                  :class="
                    isActive
                      ? ' bg-slate-900 dark:bg-slate-300 ring-4 ring-opacity-[15%] ring-black-500 dark:ring-slate-300 dark:ring-opacity-20'
                      : ''
                  "
                ></span>
                <span class="flex-1">
                  {{ ci.childtitle }}
                </span>
              </span>
            </router-link>
          </li>
        </ul>
      </Transition>
    </li>
    <!-- <li class="single-sidebar-menu">
      <a
        href="https://www.rajyasabha.digital/rsip/"
        target="_blank"
        class="menu-link"
      >
        <span class="menu-icon"><Icon icon="heroicons:arrow-top-right-on-square-solid" /></span>
        <div class="text-box">{{ $t('external-link') }}</div>
      </a>
    </li> -->
  </ul>
</template> 

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import Icon from '@/ui-components/Icon.vue';

const props = defineProps({
  title: { type: String, default: '' },
  icon: { type: String, default: '' },
  link: { type: String, default: '' },
  items: { type: Array, required: true },
  childrenLinks: { type: Array, default: null },
});

const route = useRoute();
const activeSubmenu = ref(null);

const toggleSubmenu = (index) => {
  activeSubmenu.value = activeSubmenu.value === index ? null : index;
};

const beforeEnter = (el) => {
  el.style.height = '0px';
};

const enter = (el) => {
  el.style.height = `${el.scrollHeight}px`;
};

const afterEnter = (el) => {
  el.style.height = null;
};

const beforeLeave = (el) => {
  el.style.height = `${el.offsetHeight}px`;
};

const leave = (el) => {
  el.style.height = '0px';
};

const afterLeave = (el) => {
  el.style.height = null;
};

onMounted(() => {
  props.items.forEach((item, i) => {
    item.child?.forEach((ci) => {
      if (ci.childlink === route.name) {
        activeSubmenu.value = i;
      }
    });
  });
});

watch(route, () => {
  activeSubmenu.value = props.items.findIndex(item =>
    item.child?.some(ci => ci.childlink === route.name)
  );
});
</script>


 <style lang="scss" scoped>
.submenu_enter-active,
.submenu_leave-active {
  overflow: hidden;
  transition: all 0.34s linear;
}

.not-collapsed .has-icon {
  transition: all 0.34s linear;
}
.not-collapsed .has-icon {
  @apply transform rotate-180;
}

// single sidebar menu css
.single-sidebar-menu {
  @apply relative;
  .menulabel {
    @apply text-slate-800 dark:text-slate-300 text-xs font-semibold uppercase mb-4 mt-4;
  }
  > .menu-link {
    @apply flex text-slate-600 font-medium dark:text-slate-300 text-base capitalize px-[10px] py-3 rounded-[4px] cursor-pointer;
  }
  .menu-icon {
    @apply icon-box inline-flex items-center text-slate-600 dark:text-slate-300 text-lg mr-3 ;
  }
}
// menu item has chilren
.item-has-children {
  .menu-arrow {
    @apply h-5 w-5 text-base text-slate-500 bg-slate-200 dark:bg-[#334155] dark:text-slate-300 rounded-full flex justify-center items-center;
  }
}

// close sidebar css
.close_sidebar .menulabel {
  @apply hidden;
}

.close_sidebar:not(.sidebar-hovered) {
  .menu-arrow {
    @apply hidden;
  }
  .single-sidebar-menu {
    .text-box {
      @apply absolute  left-full ml-5 w-[180px] top-0 px-4 py-3 bg-white shadow-dropdown rounded-[4px] dark:bg-slate-800 z-[999] invisible opacity-0 transition-all duration-150;
    }
    &:hover {
      .text-box {
        @apply visible opacity-100;
      }
    }
  }
  .item-has-children {
    .text-box {
      @apply hidden;
    }

    > ul {
      @apply ml-4 absolute left-full top-0 w-[230px] bg-white shadow-dropdown rounded-[4px] dark:bg-slate-800 z-[999] px-4 pt-3 transition-all duration-150 invisible opacity-0;
      display: block !important;
    }
    &:hover {
      > ul {
        @apply visible opacity-100;
      }
    }
  }
}
.menu-badge {
  @apply py-1 px-2 text-xs capitalize font-semibold rounded-[.358rem] whitespace-nowrap align-baseline inline-flex bg-slate-900 text-slate-50 dark:bg-slate-700 dark:text-slate-300;
}
// active menu
.item-has-children {
  .parent_active {
    @apply bg-violet-500;
    @apply bg-opacity-20;
    .icon-box,
    .menu-icon,
    .text-box {
      @apply text-slate-700 dark:text-slate-200;
    }
    .menu-arrow {
      @apply bg-violet-500 text-slate-600 text-opacity-70 bg-opacity-30 dark:text-white;
    }
  }
}
.menu-item-active {
  .menu-link {
    @apply bg-violet-500 bg-opacity-20 dark:bg-slate-700;
    .icon-box,
    .menu-icon,
    .text-box {
      @apply text-slate-700 dark:text-slate-300;
    }
  }
  .menu-badge {
    @apply bg-slate-100  text-slate-900;
  }
}
</style> 
