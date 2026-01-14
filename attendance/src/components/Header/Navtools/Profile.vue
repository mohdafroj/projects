<template>
  <Dropdown classMenuItems=" w-[180px] top-[54px] ">
    <div class="flex items-center">
      <div class="flex-1 mr-[10px] ">
        <div class="lg:h-8 lg:w-8 h-7 w-7 rounded-full">
          <img
            src= "/assets/images/users/pushpendra.jpg"
            alt=""
            class="block w-full h-full object-cover rounded-full"
          />
        </div>
      </div>
      <!-- <div
        class="flex-none text-slate-600 dark:text-white text-sm font-normal items-center lg:flex hidden overflow-hidden text-ellipsis whitespace-nowrap"
      >
        <span
          class="overflow-hidden text-ellipsis whitespace-nowrap w-[85px] block"
          >Pushpendra</span
        >
        <span class="text-base inline-block ml-[10px]"
          ><Icon icon="heroicons-outline:chevron-down"></Icon
        ></span>
      </div> -->
    </div>
    <template #menus>
      <MenuItem v-slot="{ active }" v-for="(item, i) in ProfileMenu" :key="i">
        <div
          type="button"
          :class="`${
            active
              ? 'bg-slate-100 dark:bg-slate-700 dark:bg-opacity-70 text-slate-900 dark:text-slate-300'
              : 'text-slate-600 dark:text-slate-300'
          } `"
          class="inline-flex items-center space-x-2  w-full px-4 py-2 first:rounded-t last:rounded-b font-normal cursor-pointer"
          @click="item.link()"
        >
          <div class="flex-none text-lg">
            <Icon :icon="item.icon" />
          </div>
          <div class="flex-1 text-sm">
            {{ item.label }}
          </div>
        </div>
      </MenuItem>
    </template>
  </Dropdown>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { MenuItem } from "@headlessui/vue";
import Dropdown from "@/ui-components/Dropdown.vue";
import Icon from "@/ui-components/Icon.vue";

const router = useRouter();

const ProfileMenu = ref([
  {
    label: "Profile",
    icon: "heroicons-outline:user",
    link: () => router.push("profile"),
  },
  {
    label: "Settings",
    icon: "heroicons-outline:cog",
    link: () => router.push("settings"),
  },
  {
    label: "Faq",
    icon: "heroicons-outline:information-circle",
    link: () => router.push("faq"),
  },
  {
    label: "Logout",
    icon: "heroicons-outline:login",
    link: () => {
      router.push("/");
      localStorage.removeItem("activeUser");
    },
  },
]);
</script>
