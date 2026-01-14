<template>
  <TabGroup>
    <TabList class="lg:space-x-8 md:space-x-4 space-x-0 ">
      <Tab
        v-slot="{ selected }"
        as="template"
        v-for="(item, i) in buttons"
        :key="i"
        class=""
      >
        <button
          :class="[ 
            selected
              ? 'text-violet-700 before:w-full'
              : 'text-slate-700 before:w-0 dark:text-slate-300',
          ]"
          class=" inline-flex flex-col items-center text-sm font-medium mb-0 capitalize bg-white dark:bg-slate-800 ring-0 focus:ring-0 focus:outline-none px-2 transition duration-150 before:transition-all before:duration-150 relative before:absolute before:left-1/2 before:bottom-[-6px] before:h-[3px] before:bg-violet-700 before:-translate-x-1/2"
        >
          <span class="text-base relative mb-1">
            <Icon :icon="item.icon" class="text-3xl"/>
          </span>
          <span class="text-center">{{ item.title }}</span>
        </button>
      </Tab>
    </TabList>
    <TabPanels class="mt-6">
      <TabPanel>
        <div class="text-slate-600 dark:text-slate-400 text-sm font-normal">
          Aliqua id fugiat nostrud irure ex duis ea quis id quis ad et. Sunt qui
          esse pariatur duis deserunt mollit dolore cillum minim tempor enim.
          Elit aute irure tempor cupidatat incididunt sint deserunt ut voluptate
          aute id deserunt nisi.
        </div>
      </TabPanel>
      <TabPanel>
        <div class="text-slate-600 dark:text-slate-400 text-sm font-normal">
          Aliqua id fugiat nostrud irure ex duis ea quis id quis ad et. Sunt qui
          esse pariatur duis deserunt mollit dolore cillum minim tempor enim.
        </div>
      </TabPanel>
      <TabPanel>
        <div class="text-slate-600 dark:text-slate-400 text-sm font-normal">
          Aliqua id fugiat nostrud irure ex duis ea quis id quis ad et. Sunt qui
        </div>
      </TabPanel>
      <TabPanel>
        <div class="text-slate-600 dark:text-slate-400 text-sm font-normal">
          Aliqua id fugiat nostrud irure ex duis ea quis id quis ad et. Sunt qui
          esse pariatur duis deserunt mollit dolore cillum minim tempor enim.
          Elit aute irure tempor cupidatat incididunt sint deserunt ut voluptate
          aute id deserunt nisi.
        </div>
      </TabPanel>
    </TabPanels>
  </TabGroup>
</template>

<script setup>
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from "@headlessui/vue";
import Icon from "@/ui-components/Icon.vue";

const buttons = [
  {
    title: "Home",
    icon: "heroicons-outline:home",
  },
  {
    title: "Profile",
    icon: "heroicons-outline:user",
  },
  {
    title: "Messages",
    icon: "heroicons-outline:chat-alt-2",
  },
  {
    title: "Settings",
    icon: "heroicons-outline:cog",
  },
];
</script>
