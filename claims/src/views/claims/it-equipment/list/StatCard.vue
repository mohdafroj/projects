<template>
  <Card
    className="!p-2 h-full cursor-pointer transition hover:shadow-md"
    noborder
    @click="handleClick"
  >
    <div class="flex justify-between items-start">
      <!-- Left Content -->
      <div>
        <div class="text-lg font-medium text-slate-900 dark:text-white">
          {{ title }}
        </div>
        <!-- <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          {{ subtitle || title }}
        </div> -->
        <div
          :class="[
            'text-2xl font-bold mt-1',
            color === 'green'
              ? 'text-green-500'
              : color === 'red'
              ? 'text-red-500'
              : color === 'orange'
              ? 'text-orange-500'
              : 'text-slate-800 dark:text-white',
          ]"
        >
          {{ value }}
        </div>
      </div>

      <!-- Icon Box -->
      <div
        class="w-[42px] h-[42px] rounded flex items-center justify-center mt-1"
        :class="[
          color === 'green'
            ? 'bg-green-100 text-green-600'
            : color === 'red'
            ? 'bg-red-100 text-red-600'
            : color === 'orange'
            ? 'bg-orange-100 text-orange-600'
            : 'bg-violet-100 text-violet-600',
        ]"
      >
        <Icon :icon="icon" class="text-xl" />
      </div>
    </div>
  </Card>
</template>

<script setup>
import { Card } from '@sds/oneui-common-ui';
import { Icon } from "@iconify/vue";

defineProps({
  title: String,
  subtitle: String,
  value: [String, Number],
  icon: String,
  color: {
    type: String,
    default: "default",
  },
});

const emit = defineEmits(["click"]);
const handleClick = () => emit("click");
</script>
