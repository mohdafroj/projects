<template>
  <div class="flex justify-between items-center mb-4 w-full max-w-5xl">
    <div class="text-2xl text-gray-600 dark:text-slate-200 font-medium">Attendance</div>
    <div class="md:mr-5">
      <Drop 
        :options="dropdownOptions" 
        v-model="selectedYear" 
        @change="updateChartData"
      />
    </div>
  </div>
  <div class="flex justify-center">
    <div class="grid md:grid-cols-4 gap-4 w-full max-w-5xl">
      <div class="col-span-2 text-left">
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div class="bg-emerald-50 dark:bg-slate-900 border px-2 py-2 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
            <Icon icon="heroicons-outline:user" class="text-green-500 text-2xl mr-3" />
            <div>
              <div class="text-sm font-semibold text-gray-700">Present Days</div>
              <div class="text-xl font-bold text-green-600">{{ chartSessionData.attendance_till_date }}</div>
            </div>
          </div>
          <div class="bg-red-50 dark:bg-slate-900 border px-2 py-2 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
            <Icon icon="heroicons-outline:user-remove" class="text-red-500 text-2xl mr-3" />
            <div>
              <div class="text-sm font-semibold text-gray-700">Absent Days</div>
              <div class="text-xl font-bold text-red-600">{{ chartSessionData.absence }}</div>
            </div>
          </div>
          <div class="bg-cyan-50 dark:bg-slate-900 border px-2 py-2 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
            <Icon icon="heroicons-outline:calendar" class="text-blue-500 text-2xl mr-3" />
            <div>
              <div class="text-sm font-semibold text-gray-700">Attendance Rate</div>
              <div class="text-xl font-bold text-blue-600">{{ Math.round((chartSessionData.attendance_till_date / chartSessionData.total_days) * 100) }}%</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-span-2 flex justify-end">
        <apexchart 
          type="donut" 
          :options="chartOptions" 
          :series="chartData" 
          class="w-[300px] h-[200px]"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import ApexCharts from 'vue3-apexcharts';
import Drop from '@/ui-components/Drop.vue';
import Icon from '@/ui-components/Icon.vue';

const sessionData = {
  2025: { session_no: 267, session_start: "2025-01-31", session_end: "2025-04-04", total_days: 30, attendance_till_date: 18, absence: 4, leave: 3, remaining_days: 5 },
  2024: { session_no: 266, session_start: "2024-02-01", session_end: "2024-04-05", total_days: 28, attendance_till_date: 16, absence: 5, leave: 4, remaining_days: 3 },
  2023: { session_no: 265, session_start: "2023-01-29", session_end: "2023-04-02", total_days: 32, attendance_till_date: 20, absence: 3, leave: 6, remaining_days: 3 },
  2022: { session_no: 264, session_start: "2022-01-27", session_end: "2022-04-03", total_days: 31, attendance_till_date: 22, absence: 2, leave: 5, remaining_days: 2 }
};

const dropdownOptions = ref([
  { value: '2025', label: 'Session 267' },
  { value: '2024', label: 'Session 266' },
  { value: '2023', label: 'Session 265' },
  { value: '2022', label: 'Session 264' }
]);

const selectedYear = ref('2025');
const chartSessionData = ref(sessionData[selectedYear.value]);
const chartData = ref([]);

const chartOptions = ref({
  chart: {
    type: 'donut'
  },
  responsive: [{
    breakpoint: 480,
    options: {
      chart: {
        width: 200
      },
      legend: {
        position: 'bottom'
      }
    }
  }],
  labels: ['Attendance', 'Absence', 'Leave', 'Remaining Days'],
  colors: ['#50C793', '#F1595C', '#FA916B', '#0CE7FA'],
  legend: {
    show: false,
    markers: {
      width: 10,
      height: 10,
      radius: 50
    }
  },
  dataLabels: {
    enabled: false
  }
});

const updateChartData = () => {
  chartSessionData.value = sessionData[selectedYear.value];
  chartData.value = [
    chartSessionData.value.attendance_till_date,
    chartSessionData.value.absence,
    chartSessionData.value.leave,
    chartSessionData.value.remaining_days
  ];
};

watch(selectedYear, updateChartData);

onMounted(updateChartData);
</script>

<style scoped>
.dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 8px;
}
</style>
