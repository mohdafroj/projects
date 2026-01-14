<template>
  <div>
    <!-- Render as a Radio Group if options exist -->
    <div v-if="options.length">
      <div class="flex flex-wrap gap-4">
        <label
          v-for="option in options"
          :key="option.value"
          class="flex items-center cursor-pointer"
          :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
        >
          <input
            type="radio"
            class="hidden"
            :disabled="disabled"
            :name="name"
            :value="option.value"
            v-model="localValue"
            v-bind="$attrs"
          />

          <span
            :class="
              localValue === option.value
                ? activeClass +
                  ' ring-[10px]  ring-inset ring-offset-2 dark:ring-offset-slate-600  dark:ring-offset-4 border-gray-700'
                : 'border-gray-600 dark:border-slate-600 dark:ring-slate-700'
            "
            class="h-5 w-5 rounded-full border inline-flex bg-white dark:bg-slate-500 mr-3 relative transition-all duration-150"
          >
          </span>

          <span class="text-slate-500 dark:text-slate-400 text-sm ml-2">
            {{ option.label }}
          </span>
        </label>
      </div>

      <p v-if="error" class="text-red-500 text-sm mt-2">
        {{ error }}
      </p>
    </div>
    <!-- for single radio button -->
    <div v-else>
      <label class="flex items-center cursor-pointer">
        <input
          type="radio"
          class="hidden"
          :name="name"
          :value="value"
          v-model="localValue"
        />
        <span
          :class="
            localValue === value
              ? activeClass +
                ' ring-[10px]  ring-inset ring-offset-2 dark:ring-offset-slate-600  dark:ring-offset-4 border-gray-700'
              : 'border-gray-600 dark:border-slate-600 dark:ring-slate-700'
          "
          class="h-5 w-5 rounded-full border inline-flex bg-white dark:bg-slate-500 mr-3 relative transition-all duration-150"
        >
        </span>

        <span class="text-slate-500 dark:text-slate-400 text-sm ml-2">
          {{ label }}
        </span>
      </label>

      <p v-if="error" class="text-red-500 text-sm mt-2 min-h-[20px]">
        {{ error }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  label: String,
  name: {
    type: String,
    default: "radio",
  },
  value: {
    type: [String, Number, Boolean],
    default: "",
  },
  checked: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  activeClass: {
    type: String,
    default: "ring-violet-700 dark:ring-slate-400 border-violet-700",
  },
  modelValue: {
    type: [String, Number, Boolean],
    default: "",
  },
  error: {
    type: String,
    default: "",
  },
  options: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue"]);

const localValue = computed({
  get: () => props.modelValue,
  set: (newValue) => emit("update:modelValue", newValue),
});
</script>
