<template>
  <div>
    <Card>
      <div
        class="grid xl:grid-cols-4 lg:grid-cols-2 md:grid-cols-2 grid-cols-1 gap-5"
      >
        <div>
          <div class="flex space-x-4 ">
            <div class="flex-none">
              <div class="h-20 w-20 rounded-full">
                <img
                  src="/assets/images/users/profile.png"
                  alt=""
                  class="w-full h-full"
                />
              </div>
            </div>
            <div class="flex-1">
              <h4 class="text-xl font-medium mb-2">
                <span class="block font-light">Good evening,</span>
                <span class="block">Mr. Amit Kumar</span>
              </h4>
              <p class="text-sm font-light dark:text-slate-400">
                Welcome to Rajya Sabha Secretariat
              </p>
            </div>
          </div>
        </div>
        <div
          v-for="(item, i) in statistics"
          :key="i"
          class="bg-slate-50 dark:bg-slate-900 rounded p-4"
        >
          <div
            class="text-slate-600 dark:text-slate-300 text-sm mb-1 font-medium"
          >
            {{ item.title }}
          </div>
          <div class="text-slate-900 dark:text-slate-200 text-lg font-medium">
            {{ item.count }}
          </div>
          <div class="ml-auto  max-w-[124px]">
            <apexchart
              type="bar"
              height="48"
              width="124"
              :options="item.name.chartOptions"
              :series="item.name.series"
            />
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>
<script setup>
import { ref } from "vue";
import Card from "@/ui-components/Card.vue";
import { columnCharthome2, columnCharthome3, columnCharthome4 } from "./data";

const statistics = ref([
  {
    name: columnCharthome3,
    title: "Current balance",
    count: "₹34,564",
    bg: "bg-[#E5F9FF] dark:bg-slate-900",
    text: "text-info-500",
    icon: "heroicons:shopping-cart",
  },
  {
    name: columnCharthome4,
    title: "Credit",
    count: "₹3,564",
    bg: "bg-[#E5F9FF] dark:bg-slate-900",
    text: "text-warning-500",
    icon: "heroicons:cube",
  },
  {
    name: columnCharthome2,
    title: "Debit",
    count: "₹3,564",
    bg: "bg-[#E5F9FF] dark:bg-slate-900",
    text: "text-[#5743BE]",
    icon: "heroicons:arrow-trending-up-solid",
  },
]);
</script>
