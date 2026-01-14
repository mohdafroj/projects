<template>
  <div>
    <!-- if options exist, render as a checkbox group -->
    <div v-if="options.length">
      <div class="flex flex-wrap gap-4">
        <label
          v-for="option in options"
          :key="option.value"
          class="flex items-center cursor-pointer"
          :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
        >
          <!-- Hidden Checkbox -->
          <input
            type="checkbox"
            class="hidden"
            :disabled="disabled"
            :name="name"
            :value="option.value"
            v-model="localValue"
          />

          <!-- Custom Checkbox Styling -->
          <span
            class="h-5 w-5 border flex-none border-slate-100 dark:border-slate-800 rounded inline-flex mr-3 relative transition-all duration-150"
            :class="
              localValue.includes(option.value)
                ? activeClass +
                  ' ring-2 ring-offset-2 dark:ring-offset-slate-800'
                : 'bg-slate-100 dark:bg-slate-600 dark:border-slate-600'
            "
          >
            <img
              src="/assets/images/icon/ck-white.svg"
              alt=""
              class="h-[10px] w-[10px] block m-auto"
              v-if="localValue.includes(option.value)"
            />
          </span>

          <span class="text-slate-500 dark:text-slate-400 text-sm leading-6">
            {{ option.label }}
          </span>
        </label>
      </div>

      <p v-if="error" class="text-red-500 text-sm mt-2">
        {{ error }}
      </p>
    </div>

    <!--  options are NOT provided, thn render a single checkbox -->
    <div v-else>
      <label
        class="flex items-center cursor-pointer"
        :class="disabled ? 'cursor-not-allowed opacity-50' : ''"
      >
        <input
          type="checkbox"
          class="hidden"
          :name="name"
          :disabled="disabled"
          v-model="singleValue"
        />

        <span
          class="h-5 w-5 border flex-none border-slate-100 dark:border-slate-800 rounded inline-flex mr-3 relative transition-all duration-150"
          :class="
            singleValue
              ? activeClass + ' ring-2 ring-offset-2 dark:ring-offset-slate-800'
              : 'bg-slate-100 dark:bg-slate-600 dark:border-slate-600'
          "
        >
          <img
            src="/assets/images/icon/ck-white.svg"
            alt=""
            class="h-[10px] w-[10px] block m-auto"
            v-if="singleValue"
          />
        </span>

        <span class="text-slate-500 dark:text-slate-400 text-sm leading-6">
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
  disabled: {
    type: Boolean,
    default: false,
  },
  checked: {
    type: Boolean,
    default: false,
  },
  name: {
    type: String,
    required: true,
  },
  activeClass: {
    type: String,
    default:
      " ring-violet-700 bg-violet-700 dark:bg-slate-700 dark:ring-slate-700 ",
  },
  value: {
    type: String,
    default: "",
  },
  modelValue: {
    type: [Array, Boolean],
    // default: () => [],
    default: false,
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

//  Handle multiple checkboxes as an array
const localValue = computed({
  get: () => (Array.isArray(props.modelValue) ? props.modelValue : []),
  set: (newValue) => emit("update:modelValue", newValue),
});

// Handle single checkbox as a boolean (Fix for issue)
const singleValue = computed({
  get: () => {
    console.log("Computed singleValue GET:", props.modelValue);
    return typeof props.modelValue === "boolean"
      ? props.modelValue
      : props.checked;
  },
  set: (newValue) => {
    console.log("Computed singleValue SET:", newValue);
    emit("update:modelValue", newValue);
  },
});
</script>
