<template>
  <div>

    <VueTailwindDatePicker v-model="dateRange" input-classes="form-control" :formatter="formatter" :timepicker="false"
      separator=" to " :range="true" as-single/>
  </div>
</template>

<script setup>
import VueTailwindDatePicker from "vue-tailwind-datepicker";
import { ref } from "vue";

//  date range value
const dateRange = ref(""); // Default empty range
// Formatter - only date (DD-MM-YYYY) 
const formatter = {
  date: "DD-MM-YYYY",
  month: "MMM",
  year: "YYYY",
};

</script>
