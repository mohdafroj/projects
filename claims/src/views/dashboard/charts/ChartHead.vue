<template>
  <div>
    <Card>
      <div
        class="grid xl:grid-cols-4 lg:grid-cols-2 md:grid-cols-2 grid-cols-1 gap-5"
      >
        <div>
          <div class="flex items-center space-x-3 mt-4 ">
            <div class="flex-none">
              <div class="h-20 w-20 rounded-full">
                <img
                  src="/assets/images/users/pushpendra.jpg"
                  alt=""
                  class="block w-full h-full object-cover rounded-full"
                />
              </div>
            </div>
            <div class="flex-1">
              <h4 class="text-xl font-medium mb-2">
                <span class="block font-light">{{ greetingMessage }},</span>
                <span class="block">Mr. Pushpendra</span>
              </h4>
              <p class="text-sm font-light dark:text-slate-400">
                Welcome to Member Portal
              </p>
            </div>
          </div>
        </div>
        <div
          v-for="(item, i) in statistics"
          :key="i"
          class=" dark:bg-slate-900 rounded p-4"
          :class="item.bg"
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
            <!-- <apexchart
              type="bar"
              height="48"
              width="124"
              :options="item.name.chartOptions"
              :series="item.name.series"
            /> -->
            <apexchart
              :key="`chart-${i}-${item.count}`"  
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
import { ref, computed } from "vue";
import Card from "@/ui-components/Card.vue";
import { columnCharthome2, columnCharthome3, columnCharthome4 } from "./chart-data";

const statistics = ref([
  {
    name: columnCharthome3,
    title: "Total",
    count: "34564",
    // bg: "bg-[#E5F9FF] dark:bg-slate-900",
    bg: "bg-cyan-100 dark:bg-slate-900",
    text: "text-info-500",
    icon: "heroicons:shopping-cart",
  },
  {
    name: columnCharthome4,
    title: "Asdf",
    count: "3564",
    bg: "bg-purple-100 dark:bg-slate-900",
    text: "text-warning-500",
    icon: "heroicons:cube",
  },
  {
    name: columnCharthome2,
    title: "Xyz",
    count: "3564",
    bg: "bg-fuchsia-100 dark:bg-slate-900",
    text: "text-[#5743BE]",
    icon: "heroicons:arrow-trending-up-solid",
  },
]);
// Function to get the appropriate greeting based on the current time
const greetingMessage = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) {
    return "Good morning";
  } else if (hour < 18) {
    return "Good afternoon";
  } else {
    return "Good evening";
  }
});
</script>
