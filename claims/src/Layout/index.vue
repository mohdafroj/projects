<template>
  <main class="app-wrapper">
    <!-- start header -->
    <Header :class="windowWidth > 1270 ? switchHeaderClass() : ''" v-bind="headerData" />
    <!-- end header -->
    <!-- Show Sidebar if 'vertical' layout and other conditions -->
    <Sidebar :menuItems="menuItems" :themeSettings="themeSettingsStore" v-if="
      themeSettingsStore.menuLayout === 'vertical' &&
      !themeSettingsStore.sidebarHidden &&
      windowWidth > 1270
    " />

    <!-- main sidebar end -->
    <Transition name="mobilemenu">
      <MobileSidebar :menuItems="menuItems" :themeSettings="themeSettingsStore"
        v-if="windowWidth < 1270 && themeSettingsStore.mobielSidebar" />
    </Transition>
    <Transition name="overlay-fade">
      <div v-if="windowWidth < 1270 && themeSettingsStore.mobielSidebar"
        class="overlay bg-slate-900 bg-opacity-70 backdrop-filter backdrop-blur-[3px] backdrop-brightness-10 fixed inset-0 z-[999]"
        @click="themeSettingsStore.mobielSidebar = false"></div>
    </Transition>
    <!-- mobile sidebar -->

    <div class="content-wrapper transition-all duration-150" :class="windowWidth > 1270 ? switchHeaderClass() : ''">
      <div class="page-content" :class="pageClass">
        <div :class="` transition-all duration-150 ${themeSettingsStore.cWidth === 'boxed'
          ? 'container mx-auto'
          : 'container-fluid'
          }`">
          <Breadcrumbs v-if="!$route.meta.hide" :bcData="bcData" />
          <router-view v-slot="{ Component }">
            <transition name="router-animation" mode="out-in" appear>
              <div v-if="Component" :key="$route.fullPath">
                <component :is="Component" />
              </div>
            </transition>
          </router-view>
        </div>
      </div>
    </div>
    <!-- end page content -->

    <FooterMenu v-if="windowWidth < 768" v-bind="footerMenuData" />
    <Footer v-else v-bind="footerData" :class="windowWidth > 1270 ? switchHeaderClass() : ''" />
  </main>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from "vue";
import { useThemeSettingsStore } from "@/store/themeSettings";
import { useWindow } from "@/mixins/window";
import {
  Header,
  FooterMenu,
  Footer,
  Breadcrumbs,
  Sidebar,
  MobileSidebar,
} from "@sds/oneui-layout";
//import Breadcrumbs from "@/components/Breadcrumbs/index.vue"
import { useI18n } from "vue-i18n";
import { useRoute } from "vue-router";
import { getMenuItems } from "@/constant/menu-items";
import { topMenu } from "@/constant/data";
import { useApiStore } from "@/store/apiData";

const route = useRoute();
const { t, locale } = useI18n();
//  Pinia store
const apiStore = useApiStore();
const themeSettingsStore = useThemeSettingsStore();

const bcData = reactive({siteTitle:'', data:[]});
const bcDataFun = () => {
  if ( route.meta && route.meta.title ) {
    let siteTitle = '';
    if ( Array.isArray(route.meta.title) ) {
      siteTitle = route.meta.title.map(item => {
        return t(item);
      }).join(" ");
    } else {
      siteTitle = t(route.meta.title);
    }
    bcData.siteTitle = t('oneui') + ' - ' + siteTitle;
    bcData.data = route.matched.map(item => {
      const title = item.meta && item.meta.title ? item.meta.title : '';
      let metaTitle = '';
      if ( Array.isArray(title) ) {
        metaTitle = title.map(item => {
          return t(item);
        }).join(' ');
      } else {
        metaTitle = (title == '') ? '' : t(title);
      }
      return {name:item.name, title:metaTitle};
    }).filter((item, i) => i == 0 || item.title != ''); //console.log(bcData)
  } else {
    bcData.siteTitle = ''
    bcData.data = []
  }
};
bcDataFun();
watch(
  [locale, () => route.fullPath],
  ([newLocale, newPath], [oldLocale, oldPath]) => {
    bcDataFun();
  }
);
// Get window size from custom mixin or utility function
const windowWidth = useWindow();

watch(windowWidth, newWidth => {
  windowWidth.value = newWidth;
  //headerData.value.windowWidth.value = newWidth;
});
// Initial menu items
let menuItems = computed(() => getMenuItems(t));

// Methods
const switchHeaderClass = () => {
  if (
    themeSettingsStore.menuLayout === "horizontal" ||
    themeSettingsStore.sidebarHidden
  ) {
    return "ml-0";
  } else if (themeSettingsStore.sidebarCollaspe) {
    return "ml-[72px] ";
  } else {
    return "ml-[248px] ";
  }
};

// Computed property for page content height
const pageClass = computed(() => {
  return themeSettingsStore.menuLayout === "vertical" && windowWidth > 1270
    ? "md:pt-6 md:pb-[37px] md:px-6 pt-[15px] px-[15px] pb-24"
    : "page-min-height";
});

// Start for sds-layouts package
const languages = [
  { name: "English", code: "en", image: "/assets/images/all-img/flag.png" },
  { name: "हिन्दी", code: "hi", image: "/assets/images/all-img/flag.png" },
];
//header data binding
const headerData = computed(() => {
  return {
    locale: locale,
    languages: languages,
    themeSettings: themeSettingsStore,
    windowWidth: windowWidth,
    topMenu: topMenu,
    profile: { path: "dashboard" },
    notifications: ref([]),
    messages: ref([]),
  };
});

const fetchedMessage = [];
watch(fetchedMessage, updatedData => {
  headerData.value.messages.value = updatedData;
});

const fetcNotification = [];
watch(fetcNotification, updatedData => {
  headerData.value.notifications.value = updatedData;
});

//Start of FooterMenu and Footer Component
const footerMenuData = computed(() => {
  return {
    profile: { path: "dashboard" },
    messages: {
      total: headerData.value.messages.value.length,
      path: "messages",
      title: t("menu.messages"),
    },
    notifications: {
      total: headerData.value.notifications.value.length,
      path: "notifications",
      title: t("menu.notifications"),
    },
  };
});

const footerData = computed(() => {
  return {
    themeSettings: themeSettingsStore,
    leftMessage: t("footer.left_message"),
    rightMessage: t("footer.crafted_by", {
      team: `<a href="#" target="_blank" class="text-violet-600 font-semibold">${t(
        "team",
      )}</a>`,
    }),
  };
});

onMounted(() => {
  if (route?.query.perms == 1) {
    console.log(apiStore.rbac);
  }
})
</script>

<style lang="scss">
.router-animation-enter-active,
.router-animation-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.router-animation-enter-from {
  opacity: 0;
  transform: translateY(100px);
  /* Move from bottom */
}

.router-animation-leave-to {
  opacity: 0;
  transform: translateY(-10px);
  /* Move upwards */
}

@keyframes going {
  from {
    transform: translate3d(0, 0, 0) scale(1);
  }

  to {
    transform: translate3d(0, 4%, 0) scale(0.93);
    opacity: 0;
  }
}

@keyframes coming {
  from {
    transform: translate3d(0, 4%, 0) scale(0.93);
    opacity: 0;
  }

  to {
    transform: translate3d(0, 0, 0) scale(1);
    opacity: 1;
  }
}

@keyframes slideLeftTransition {
  0% {
    opacity: 0;
    transform: translateX(-20px);
  }

  100% {
    opacity: 1;
    transform: translateX(0px);
  }
}

.mobilemenu-enter-active {
  animation: slideLeftTransition 0.24s;
}

.mobilemenu-leave-active {
  animation: slideLeftTransition 0.24s reverse;
}

.page-content {
  @apply md:pt-6 md:pb-[37px] md:px-6 pt-[15px] px-[15px] pb-24;
}

.page-min-height {
  min-height: calc(var(--vh, 1vh) * 100 - 118px);
}
</style>
