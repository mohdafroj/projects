<template>
  <div>
    <div class="mx-auto flex z-[5] items-center relative justify-center">
      <div
        class="relative z-[1] items-center item flex flex-start flex-1 last:flex-none"
        v-for="(item, i) in steps"
        :key="i"
      >
        <div
          :class="`   ${
            stepNumber >= i
              ? 'bg-green-600 text-white ring-2 ring-green-600 ring-offset-2'
              : 'bg-white ring-2 ring-violet-700 ring-opacity-70  text-violet-700 text-opacity-70'
          }`"
          class="icon-box h-12 w-12 rounded-full flex flex-col items-center justify-center relative z-[66] ring-1 text-lg font-medium"
        >
          <span v-if="stepNumber <= i"> {{ i + 1 }}</span>
          <span v-else class="text-3xl">
            <Icon icon="bx:check-double" />
          </span>
        </div>

        <div
          class="absolute top-1/2 h-[2px] w-full"
          :class="stepNumber >= i ? 'bg-green-500' : 'bg-[#E0EAFF]'"
        ></div>

        <div
          class="text-sm mt-[10px] leading-[16px] font-medium capitalize text-slate-500-500 text-center"
        ></div>
      </div>
    </div>

    <div class="flex justify-between mt-10">
      <Button
        @click.prevent="prev"
        text="prev"
        :isDisabled="stepNumber === 0"
      />
      <Button
        @click.prevent="next"
        text="next"
        :isDisabled="stepNumber === steps.length - 1"
      />
    </div>
  </div>
</template>
<script setup>
import { ref } from "vue";
import Button from "@/ui-components/Button.vue";
import Icon from "@/ui-components/Icon.vue";

// Reactive state for steps and current step number
const steps = ref([{ id: 1 }, { id: 2 }, { id: 3 }]);

const stepNumber = ref(0);

// Methods
const next = () => {
  const totalSteps = steps.value.length;
  const isLastStep = stepNumber.value === totalSteps - 1;
  if (!isLastStep) {
    stepNumber.value++;
  }
};

const prev = () => {
  if (stepNumber.value > 0) {
    stepNumber.value--;
  }
};
</script>
