<!-- <template>
    <div class="flex justify-between items-center mb-4 w-full max-w-5xl">
    <div class="text-2xl font-semibold">Attendance</div>
    <div class="md:mr-5">
        <Drop 
        :options="dropdownOptions" 
        v-model="selectedYear" 
      />

</div>
    </div>
    <div class=" flex justify-center">
      
      <div class="grid md:grid-cols-4 gap-4 w-full max-w-5xl">
        -- <div class=" col-span-2 text-left">
            <div class="text-2xl font-semibold">Attendance</div>
          <ul class="space-y-2">
            <li class="flex items-center"><span class="dot success"></span> Attendance: {{ sessionData.attendance_till_date }}</li>
            <li class="flex items-center"><span class="dot danger"></span> Absence: {{ sessionData.absence }}</li>
            <li class="flex items-center"><span class="dot warning"></span> Leave: {{ sessionData.leave }}</li>
            <li class="flex items-center"><span class="dot info"></span> Remaining Days: {{ sessionData.remaining_days }}</li>
          </ul> 
        </div> --
        <div class="col-span-2 text-left">
            
          
          <div class="grid grid-cols-2 gap-2 mt-4">
            <div class="border px-3 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot success"></span>
              <span class="ml-1">Attendance: {{ sessionData.attendance_till_date }}</span>
            </div>
            <div class="border px-2 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot danger"></span>
              <span class="ml-1">Absence: {{ sessionData.absence }}</span>
            </div>
            <div class="border px-2 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot warning"></span>
              <span class="ml-1">Leave: {{ sessionData.leave }}</span>
            </div>
            <div class="border px-2 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot info"></span>
              <span class="ml-1">Remaining Days: {{ sessionData.remaining_days }}</span>
            </div>
          </div>
        </div>
        <div class=" col-span-2 flex justify-end ">
          <apexchart 
            type="donut" 
            :options="chartOptions" 
            :series="chartData" 
            class="w-[300px] h-[150px]"
          />
          
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted } from 'vue';
  import ApexCharts from 'vue3-apexcharts';
  import sessionData from '@/constant/session-data.json'; // Adjust path as needed
  import Drop from '@/ui-components/Drop.vue';
  
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
    // labels: ['Attendance', 'Absence', 'Leave', 'Remaining Days'],
    labels: ['', '', '', ''],
    colors: ['#50C793', '#F1595C', '#FA916B', '#0CE7FA'],
    legend: {
    show: false,
    markers: {
      width: 10,
      height: 10,
      radius: 50
    },
    labels: {
      colors: 'transparent' // Hide labels
    }
  },
  dataLabels: {
    enabled: false // Remove % inside donut
  } 
  });
  
  onMounted(() => {
    chartData.value = [
      sessionData.attendance_till_date,
      sessionData.absence,
      sessionData.leave,
      sessionData.remaining_days
    ];
  });
//   dropdown
const dropdownOptions = ref([
  { value: '2024', label: '2024' },
  { value: '2023', label: '2023' },
  { value: '2022', label: '2022' },
]);

const selectedYear = ref(dropdownOptions.value[0].value);
  </script>
  
  <style scoped>
  .dot {
    width: 20px;
    height: 20px;
   border-radius: 50%; 
    display: inline-block;
    margin-right: 8px;
  }
  
  .blue { background-color: #4669FA; }
  .red { background-color: #F1595C; }
  .yellow { background-color: #FA916B; }
  .green { background-color: #28a745; }
   .primary{ background-color: #4669FA; }
   .secondary{ background-color: #A0AEC0; }
   .danger{ background-color: #F1595C; }
   .black{ background-color: #111112; }
   .warning{ background-color: #FA916B; }
   .info{ background-color: #0CE7FA; }
   .light{ background-color: #475569; }
   .success{ background-color: #50C793; }
   .gray-f7{ background-color: #F7F8FC; }
   .dark{ background-color: #1E293B; }
   .dark-gray{ background-color: #0F172A; }
   .gray{ background-color: #68768A; }
   .gray2{ background-color: #EEF1F9; }
  
  </style>
   -->

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
            <div class="bg-emerald-50 dark:bg-slate-900 border px-3 py-3 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot success"></span>
              <span class="ml-1">Attendance: {{ chartSessionData.attendance_till_date }}</span>
            </div>
            <div class="bg-red-50 dark:bg-slate-900 border px-2 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot danger"></span>
              <span class="ml-1">Absence: {{ chartSessionData.absence }}</span>
            </div>
            <div class="bg-orange-50 dark:bg-slate-900 border px-2 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot warning"></span>
              <span class="ml-1">Leave: {{ chartSessionData.leave }}</span>
            </div>
            <div class="bg-cyan-50 dark:bg-slate-900 border px-2 py-4 rounded-lg shadow-sm flex items-center dark:border-slate-700 dark:text-slate-100">
              <span class="dot info"></span>
              <span class="ml-1">Remaining Days: {{ chartSessionData.remaining_days }}</span>
            </div>
          </div>
          <div class="text-base italic text-gray-600 mt-6">⭐ Better than 85% of Members</div>
        </div>
        <div class="col-span-2 flex justify-end">
          <apexchart 
            type="donut" 
            :options="chartOptions" 
            :series="chartData" 
            class="w-[300px] h-[150px]"
          />
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted, watch } from 'vue';
  import ApexCharts from 'vue3-apexcharts';
  import Drop from '@/ui-components/Drop.vue';
  
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
    labels: ['', '', '', ''],
    colors: ['#50C793', '#F1595C', '#FA916B', '#0CE7FA'],
    legend: {
      show: false,
      markers: {
        width: 10,
        height: 10,
        radius: 50
      },
      labels: {
        colors: 'transparent'
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
  .blue { background-color: #4669FA; }
  .red { background-color: #F1595C; }
  .yellow { background-color: #FA916B; }
  .green { background-color: #28a745; }
   .primary{ background-color: #4669FA; }
   .secondary{ background-color: #A0AEC0; }
   .danger{ background-color: #F1595C; }
   .black{ background-color: #111112; }
   .warning{ background-color: #FA916B; }
   .info{ background-color: #0CE7FA; }
   .light{ background-color: #475569; }
   .success{ background-color: #50C793; }
   .gray-f7{ background-color: #F7F8FC; }
   .dark{ background-color: #1E293B; }
   .dark-gray{ background-color: #0F172A; }
   .gray{ background-color: #68768A; }
   .gray2{ background-color: #EEF1F9; }
  </style>
