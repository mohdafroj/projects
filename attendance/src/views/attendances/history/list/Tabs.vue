<template>  
  <div class="bg-white py-2 dark:bg-gray-700 shadow-sm">
    <div class="flex mx-2 gap-1">
      <button
        class="p-2 rounded-lg font-medium text-sm"
        :class="{
          'bg-gray-300 text-gray-700': 'Attendance_History' == route.name,
          'bg-gray-100 text-gray-500': 'Attendance_History' != route.name,
        }"
        @click="() => router.push({name:'Attendance_History'})"
      >
        Report
      </button>
      
      <button
        class="p-2 rounded-lg font-medium text-sm"
        :class="{
          'bg-gray-300 text-gray-700': 'DailyReport' == route.name,
          'bg-gray-100 text-gray-500': 'DailyReport' != route.name,
        }"
        @click="() => router.push({name:'DailyReport'})"
      >
        Daily Report
      </button>
      
      <button
        class="p-2 rounded-lg font-medium text-sm"
        :class="{
          'bg-gray-300 text-gray-700': 'SignedReport' == route.name,
          'bg-gray-100 text-gray-500': 'SignedReport' != route.name,
        }"
        @click="() => router.push({name:'SignedReport'})"
      >
        Signed Report
      </button>
      
      <button
        class="p-2 rounded-lg font-medium text-sm"
        :class="{
          'bg-gray-300 text-gray-700': 'AbsentMember' == route.name,
          'bg-gray-100 text-gray-500': 'AbsentMember' != route.name,
        }"
        @click="() => router.push({name:'AbsentMember'})"
      >
        Long Absentee Report
      </button>      
    </div>
  </div>

</template>
  
<script setup>
import { useRoute, useRouter } from 'vue-router';
const route = useRoute();
const router = useRouter();
</script>
