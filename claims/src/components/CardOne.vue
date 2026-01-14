<!-- CardOne.vue -->
<template>
  <div
    class="bg-white rounded-lg shadow p-4 flex flex-row justify-between sm:flex-row items-start sm:items-center gap-4 h-full">

    <!-- Text Content -->
    <div class="flex-1">
      <div flex flex-row>
        <div class="text-sm text-gray-500" v-if="year">{{ year }}</div>
        <div class="text-sm text-gray-600 font-semibold leading-tight">
          {{ title }}
        </div>
      </div>
      <div class="text-2xl font-bold mt-3 mb-3">
        {{ currency }}{{ formatAmount(amount) }}
      </div>
      <div class="text-sm text-gray-500">{{ description }}</div>
    </div>
    <!-- Icon -->
    <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="iconBackground">
      <img :src="iconSrc" alt="icon" class="w-12 h-12" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  amount: [String, Number],
  currency: {
    type: String,
    default: '',
  },
  year: String,
  description: String,
  iconSrc: String,
  iconBackground: {
    type: String,
    default: 'bg-gray-100', // overrideable background color
  },
})

function formatAmount(value) {
  if (isNaN(value)) return value
  return Number(value).toLocaleString('en-IN')
}
</script>
