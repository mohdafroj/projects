<template>
  <div>
    <VueTailwindDatePicker
      v-model="dateValue"
      input-classes="form-control"
      as-single
      :formatter="formatter"
    />
  </div>
</template>
<script setup>
import VueTailwindDatePicker from "vue-tailwind-datepicker";
import { ref } from "vue";

const dateValue = ref("");

// Formatter to ensure only date (YYYY-MM-DD) is displayed
const formatter = {
  date: "YYYY-MM-DD",
  month: "MMM",
  year: "YYYY",
};
</script>
<style lang=""></style>
