<template>
  <div class="grid md:grid-cols-4 sm:grid-cols-2 grid-cols-1 gap-3">
    <div
      v-for="(item, i) in statistics"
      :key="i"
      :class="item.bg"
      class="rounded-md p-4 bg-opacity-[0.15] dark:bg-opacity-25 relative z-[1]"
    >
      <div class="overlay absolute left-0 top-0 w-full h-full z-[-1]">
        <img
          :src="item.img"
          alt=""
          draggable="false"
          class="w-full h-full object-contain"
        />
      </div>
      <span class="block mb-6 text-sm text-slate-900 dark:text-white font-medium">
        {{ item.title }}
      </span>
      <span class="block text-2xl text-slate-900 dark:text-white font-medium mb-6">
        {{ item.count }}
      </span>
      <div class="flex space-x-2 ">
        <div class="flex-none text-xl" :class="item.text">
          <Icon :icon="item.icon" />
        </div>
        <div class="flex-1">
          <span class="block mb-[2px]" :class="item.percentClass">
            {{ item.percent }}
          </span>
          <span class="block mb-1 text-slate-600 dark:text-slate-300">
            From last week
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import Icon from "@/ui-components/Icon.vue";


const statistics = ref([
  {
    title: "Sales",
    count: "354",
    bg: "bg-warning-500",
    text: "text-primary-500",
    percent: "25.67% ",
    icon: "heroicons:arrow-trending-up",
    img: "/assets/images/all-img/shade-1.png",
    percentClass: "text-primary-500",
  },
  {
    title: "Revenue",
    count: "₹86,954",
    bg: "bg-info-500",
    text: "text-primary-500",
    percent: "8.67%",
    icon: "heroicons:arrow-trending-up",
    img: "/assets/images/all-img/shade-2.png",
    percentClass: "text-primary-500",
  },
  {
    title: "Conversion",
    count: "15%",
    bg: "bg-primary-500",
    text: "text-danger-500",
    percent: "1.67%",
    icon: "heroicons:arrow-trending-down",
    img: "/assets/images/all-img/shade-3.png",
    percentClass: "text-danger-500",
  },
  {
    title: "Leads",
    count: "654",
    bg: "bg-success-500",
    text: "text-primary-500",
    percent: "11.67%",
    icon: "heroicons:arrow-trending-up",
    img: "/assets/images/all-img/shade-4.png",
    percentClass: "text-primary-500",
  },
]);
</script>

<style lang=""></style>
