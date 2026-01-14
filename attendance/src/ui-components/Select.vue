<template>
  <div
    class="fromGroup relative"
    :class="`${error ? 'has-error' : ''} ${horizontal ? 'flex' : ''} ${
      validate ? 'is-valid' : ''
    }`"
  >
    <!-- Label -->
    <label
      v-if="label"
      :class="`${classLabel} inline-block input-label text-sm font-semibold text-gray-500 mb-[6px]`"
      :for="name"
    >
      {{ label }}
    </label>

    <div class="relative">
      <!-- Select Dropdown -->
      <select
        :name="name"
        :id="name"
        :class="`${classInput} input-control block w-full focus:outline-none h-[40px] py-2`"
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)"
        :disabled="disabled"
        :multiple="multiple"
      >
        <option value="" disabled>{{ placeholder }}</option>
        <template v-if="!$slots.default && options">
          <option
            v-for="(item, index) in options"
            :key="index"
            :value="item.value"
          >
            {{ item.label }}
          </option>
        </template>
        <slot v-if="$slots.default"></slot>
      </select>

      <!-- Validation Icons -->
      <div class="flex text-xl absolute right-4 top-1/2 -translate-y-1/2">
        <span v-if="error" class="text-danger-500">
          <Icon
            icon="heroicons:exclamation-circle-16-solid"
            width="16"
            height="16"
          />
        </span>

        <span v-if="validate" class="text-success-500">
          <Icon icon="bi:check-lg" />
        </span>
      </div>
    </div>

    <!-- Error Message -->
    <span
      v-if="error"
      class="mt-2"
      :class="
        msgTooltip
          ? 'inline-block bg-danger-500 text-white text-[10px] px-2 py-1 rounded'
          : 'text-danger-500 block text-sm'
      "
    >
      {{ error }}
    </span>

    <!-- Success Message -->
    <span
      v-if="validate"
      class="mt-2"
      :class="
        msgTooltip
          ? 'inline-block bg-success-500 text-white text-[10px] px-2 py-1 rounded'
          : 'text-success-500 block text-sm'
      "
    >
      {{ validate }}
    </span>

    <!-- Description -->
    <span
      class="block text-secondary-500 font-light leading-4 text-xs mt-2"
      v-if="description"
    >
      {{ description }}
    </span>
  </div>
</template>

<script setup>

import Icon from "@/ui-components/Icon.vue";

defineProps({
  placeholder: {
    type: String,
    default: "Select Option",
  },
  label: String,
  classLabel: {
    type: String,
    default: " ",
  },
  classInput: {
    type: String,
    default: "classinput",
  },
  name: String,
  modelValue: {
    default: "",
  },
  error: String,
  validate: String,
  msgTooltip: {
    type: Boolean,
    default: false,
  },
  description: String,
  disabled: {
    type: Boolean,
    default: false,
  },
  multiple: {
    type: Boolean,
    default: false,
  },
  options: {
    type: Array,
    default: () => [
      { value: "", label: "Select Option" },
      { value: "option2", label: "Option 2" },
    ],
  },
});

// Emit event for two-way binding
defineEmits(["update:modelValue"]);
</script>

<style scoped>
select {
  @apply appearance-none bg-[url('https://api.iconify.design/fe/arrow-down.svg')] bg-no-repeat;
  background-position: calc(100% - 7px) center;
}

option {
  @apply capitalize;
}

.dark select {
  @apply bg-[url('https://api.iconify.design/heroicons/chevron-down-solid.svg?color=white')];
}
</style>
