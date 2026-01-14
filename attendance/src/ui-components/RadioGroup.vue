<template>
  <div>
    <!-- Radio Group -->
    <div :class="gridClass" class="gap-2">
      <label
        v-for="option in options"
        :key="option.value"
        class="cursor-pointer"
      >
        <!-- Hidden Radio Button -->
        <input
          type="radio"
          :value="option.value"
          :checked="modelValue === option.value"
          @change="$emit('update:modelValue', option.value)"
          class="peer hidden"
          :name="name"
        />
        <!-- Custom  Button -- class="w-9 h-9 text-purple-600 invisible group-[.peer:checked+&]:visible"-->
        <div
          class="hover:bg-purple-50 flex items-center justify-between px-4 py-1 border-2 rounded-lg cursor-pointer text-sm group peer-checked:border-purple-500 peer-checked:bg-violet-100 dark:peer-checked:bg-slate-700"
          :class="[error ? 'border-red-500 border-[3px]' : 'border-gray-200']"
        >
          <h2 class="font-medium text-base text-gray-700">
            {{ option.label }}
          </h2>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-9 h-9 text-purple-600 invisible group-[.peer:checked+&]:visible"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
      </label>
    </div>

    <!-- Error Message & Validation Icon -->
    <div v-if="error" class="flex items-center mt-2 text-red-500 text-sm">
      <Icon
        icon="heroicons:exclamation-circle-16-solid"
        width="16"
        height="16"
        class="mr-1"
      />
      <span>{{ error }}</span>
    </div>
  </div>
</template>

<script setup>
import Icon from "@/ui-components/Icon.vue";

defineProps({
  modelValue: {
    type: String,
    required: true,
  },
  options: {
    type: Array,
    required: true,
  },
  name: {
    type: String,
    required: true,
  },
  gridClass: {
    type: String,
    default: "grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3",
  },
  error: {
    type: String,
    default: "",
  },
});

defineEmits(["update:modelValue"]);
</script>
