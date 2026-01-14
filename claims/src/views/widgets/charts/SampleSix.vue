<template>
  <div class="space-y-4">
    <div class="grid xl:grid-cols-6 lg:grid-cols-3 sm:grid-cols-2 grid-cols-1 gap-4">
      <div v-for="(chart, i) in barCharts" :key="i" class="bg-white dark:bg-slate-800 rounded p-4">
        <div class="text-sm text-slate-600 dark:text-slate-300 mb-[6px] font-medium">
          {{ chart.title }}
        </div>
        <div class="text-lg text-slate-900 dark:text-white font-medium mb-[6px]">
          {{ chart.count }}
        </div>
        <div class="font-normal text-xs text-slate-600 dark:text-slate-300">
          <span :class="chart.percentClass">{{ chart.percent }}</span>
          From last Week
        </div>
        <div class="mt-4">
          <apexchart type="bar" height="60" :options="chart.chartOptions" :series="chart.series" />
        </div>
      </div>
    </div>

    <div class="grid xl:grid-cols-3 lg:grid-cols-2 grid-cols-1 gap-4">
      <div v-for="(chart, i) in donutCharts" :key="i" class="bg-white dark:bg-slate-800 rounded py-3 px-4">
        <div class="md:flex items-center">
          <div class="flex-1">
            <div class="text-sm text-slate-600 dark:text-slate-300 mb-[6px] font-medium">
              {{ chart.title }}
            </div>
            <div class="text-lg text-slate-900 dark:text-white font-medium mb-[6px]">
              {{ chart.count }}
            </div>
            <div class="font-normal text-xs text-slate-600 dark:text-slate-300">
              <span class="text-primary-500">+08%</span>
              From last Week
            </div>
          </div>
          <div class="flex-none">
            <apexchart type="donut" height="200" :options="chart.chartOptions" :series="chart.series" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";

// Bar charts data
const barCharts = ref([
  {
    title: "Orders",
    count: "123k",
    percent: "-60%",
    percentClass: "text-warning-500",
    chartOptions: {
      chart: { sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: "40px" } },
      colors: ["#FA916B"],
      grid: { show: false },
    },
    series: [{ name: "Revenue", data: [70, 45, 60, 75, 85, 100] }],
  },
  {
    title: "Orders",
    count: "123k",
    percent: "-60%",
    percentClass: "text-warning-500",
    chartOptions: {
      chart: { sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: "40px" } },
      colors: ["#FC8DAA"],
      grid: { show: false },
    },
    series: [{ name: "Revenue", data: [70, 45, 60, 75, 85, 100] }],
  },
  {
    title: "Orders",
    count: "123k",
    percent: "-60%",
    percentClass: "text-warning-500",
    chartOptions: {
      chart: { sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: "40px" } },
      colors: ["#50C793"],
      grid: { show: false },
    },
    series: [{ name: "Revenue", data: [70, 45, 60, 75, 85, 100] }],
  },
  {
    title: "Profit",
    count: "654k",
    percent: "+02%",
    percentClass: "text-primary-500",
    chartOptions: {
      chart: { sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: "40px" } },
      colors: ["#4669FA"],
      grid: { show: false },
    },
    series: [{ name: "Revenue", data: [70, 45, 60, 75, 85, 100] }],
  },
  {
    title: "Profit",
    count: "654k",
    percent: "+02%",
    percentClass: "text-primary-500",
    chartOptions: {
      chart: { sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: "40px" } },
      colors: ["#0CE7FA"],
      grid: { show: false },
    },
    series: [{ name: "Revenue", data: [70, 45, 60, 75, 85, 100] }],
  },
  {
    title: "Profit",
    count: "654k",
    percent: "+02%",
    percentClass: "text-primary-500",
    chartOptions: {
      chart: { sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: "40px" } },
      colors: ["#F1595C"],
      grid: { show: false },
    },
    series: [{ name: "Revenue", data: [70, 45, 60, 75, 85, 100] }],
  },
]);

// Donut charts data
const donutCharts = ref([
  {
    title: "Earnings",
    count: "₹12,335.00",
    series: [44, 55],
    chartOptions: computed(() => ({
      labels: ["Success", "Return"],
      dataLabels: { enabled: false },
      colors: ["#0CE7FA", "#FA916B"],
      legend: {
        position: "bottom",
        fontSize: "14px",
        labels: { colors: "#112229" },
      },
      plotOptions: { pie: { donut: { size: "65%" } } },
    })),
  },
  {
    title: "Earnings",
    count: "₹12,335.00",
    series: [44, 55],
    chartOptions: computed(() => ({
      labels: ["Success", "Return"],
      dataLabels: { enabled: false },
      colors: ["#50C793", "#FA916B"],
      legend: {
        position: "bottom",
        fontSize: "14px",
        labels: { colors: "#112229" },
      },
      plotOptions: { pie: { donut: { size: "65%" } } },
    })),
  },
  {
    title: "Earnings",
    count: "₹12,335.00",
    series: [44, 55],
    chartOptions: computed(() => ({
      labels: ["Success", "Return"],
      dataLabels: { enabled: false },
      colors: ["#4669FA", "#F1595C"],
      legend: {
        position: "bottom",
        fontSize: "14px",
        labels: { colors: "#112229" },
      },
      plotOptions: { pie: { donut: { size: "65%" } } },
    })),
  },
]);
</script>


