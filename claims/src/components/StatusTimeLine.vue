<template>
  <div class="p-4 w-full h-full mb-5">
    <div class="flex items-center justify-between mb-4">
      <h5 class="font-medium">{{ title }}</h5>

      <div class="flex space-x-2">
        <button
          class="flex items-center border border-green-500 text-green-600 px-3 py-1 rounded-full hover:bg-green-50 transition"
          @click="$emit('viewHistoryDetails', $event)"
        >
          <Icon
            icon="heroicons:arrow-path-solid"
            width="16"
            height="16"
            class="mr-2"
            style="color: #8bea60"
          />
          <span class="text-sm">{{ history }}</span>
        </button>

        <button
          class="flex items-center border border-green-500 text-green-600 px-3 py-1 rounded-full hover:bg-green-50 transition"
          @click="$emit('refresh', $event)"
        >
          <Icon
            icon="heroicons:arrow-path-solid"
            width="16"
            height="16"
            class="mr-2"
            style="color: #8bea60"
          />
          <span class="text-sm">{{ refresh }}</span>
        </button>
      </div>
    </div>

    <!-- Timeline Steps -->
    <div class="flex justify-between items-start relative pt-4 w-full">
      <div
        v-for="(stage, index) in stages"
        :key="index"
        class="relative flex flex-col items-center text-center w-1/4"
      >
        <div
          v-if="stage.delay"
          class="absolute top-16 text-xs text-gray-500 -left-12"
        >
          {{ stage.delay }}
        </div>
        <!-- Connector -->
        <div
          v-if="index < stages.length - 1"
          class="absolute top-20 z-0 h-0.5"
          :style="{
            left: 'calc(50% + 24px)',
            width: 'calc(100% - 48px)',
          }"
          :class="[
            stages[index].status === 'success'
              ? 'bg-green-500'
              : 'border-t border-dashed border-gray-400',
          ]"
        />

        <!-- Step Title -->
        <div class="font-semibold text-gray-700 mb-1">{{ stage.name }}</div>

        <!-- Status Badge -->
        <div
          class="text-xs px-2 py-0.5 rounded-full mb-2"
          :class="{
            'bg-green-100 text-green-700': stage.status === 'success',
            'bg-orange-100 text-orange-700': stage.status === 'in-process',
            'bg-gray-200 text-gray-500': stage.status === 'pending',
          }"
        >
          {{ formatStatus(stage.status) }}
        </div>

        <!-- Step Circle -->
        <div
          class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold z-10 border-4 shadow-md"
          :class="{
            'border-green-500 text-green-500 shadow-green-400': stage.status === 'success',
            'border-orange-400 text-orange-400 shadow-orange-300': stage.status === 'in-process',
            'bg-gray-200 text-gray-500 border-gray-100': stage.status === 'pending',
          }"
        >
          <template v-if="stage.status === 'success'">
            <Icon
              icon="weui:done-outlined"
              width="24"
              height="24"
              style="color: #50cd1b"
            />
          </template>
          <template v-else>
            {{ index + 1 }}
          </template>
        </div>

        <!-- Date -->
        <div v-if="stage.date" class="text-xs text-gray-500 mt-1">
          {{ formatDate(stage.date) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { defineProps } from "vue";

const props = defineProps({
  title: String,
  stages: Array,
  successLabel: {
    type:String,
    default: 'Success',
  },
  pendingLabel: {
    type:String,
    default: 'Pending',
  },
  refresh: {
    type:String,
    required: true
  },
  history: {
    type:String,
    default: 'View Status History',
  }
});

const formatStatus = status => {
  switch (status) {
    case "success":
      return "Success";
    case "in-process":
      return "In-Process";
    default:
      return "Pending";
  }
};

const formatDate = date => {
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(date));
};
</script>
