<template>
  <div
    class="fromGroup relative"
    :class="`${error ? 'has-error' : ''}  ${horizontal ? 'flex' : ''}  ${
      validate ? 'is-valid' : ''
    } `"
  >
    <label
      v-if="label"
      :class="`${classLabel} inline-block input-label text-sm font-semibold text-gray-500 mb-[6px]`"
      :for="name"
    >
      {{ label }}</label
    >

    <div class="relative">
      <div v-if="!$slots.default">
        <vSelect
          :name="name"
          :error="error"
          :id="name"
          :readonly="isReadonly"
          :disabled="disabled"
          :validate="validate"
          :multiple="multiple"
          :options="options"
          :placeholder="placeholder"
          @update:modelValue="$emit('update:modelValue', typeof $event === 'object' ? $event.value : $event)"
        >
        </vSelect>
      </div>
      <slot></slot>
      <div class="flex text-xl absolute right-[14px] top-1/2 -translate-y-1/2">
        <span v-if="error" class="text-danger-500">
          <Icon icon="heroicons-outline:information-circle" />
        </span>

        <span v-if="validate" class="text-success-500">
          <Icon icon="bi:check-lg" />
        </span>
      </div>
    </div>

    <span
      v-if="error"
      class="mt-2"
      :class="
        msgTooltip
          ? ' inline-block bg-danger-500 text-white text-[10px] px-2 py-1 rounded'
          : ' text-danger-500 block text-sm'
      "
      >{{ error }}</span
    >
    <span
      v-if="validate"
      class="mt-2"
      :class="
        msgTooltip
          ? ' inline-block bg-success-500 text-white text-[10px] px-2 py-1 rounded'
          : ' text-success-500 block text-sm'
      "
      >{{ validate }}</span
    >
    <span
      class="block text-secondary-500 font-light leading-4 text-xs mt-2"
      v-if="description"
      >{{ description }}</span
    >
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from "vue";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Icon from "@/ui-components/Icon.vue";

const props = defineProps({
  placeholder: {
    type: String,
    default: "Select Option",
  },
  label: String,
  classLabel: {
    type: String,
    default: " ",
  },
  name: String,
  modelValue: [String, Array], // Accept both single and multiple values
  error: String,
  isReadonly: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  horizontal: {
    type: Boolean,
    default: false,
  },
  validate: String,
  msgTooltip: {
    type: Boolean,
    default: false,
  },
  description: String,
  multiple: {
    type: Boolean,
    default: false,
  },
  options: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue"]);
</script>
<style lang="scss">
.fromGroup {
  .vs__dropdown-toggle {
    @apply bg-white  dark:bg-slate-900 border-gray-300 rounded-none dark:border-slate-700 dark:text-white min-h-[40px] text-gray-600 text-base;
  }
  .v-select {
    @apply dark:text-slate-300;
  }
  &.has-error {
    .vs__dropdown-toggle {
      @apply border-danger-500;
    }
  }
  .vs__dropdown-option {
    @apply dark:text-slate-100;
  }
  .vs__dropdown-option--highlight {
    @apply bg-purple-900 dark:bg-slate-600 dark:bg-opacity-20 py-2 text-base;
  }
  .vs__dropdown-menu {
    li {
      @apply capitalize;
    }
  }
  .vs__dropdown-menu {
    @apply shadow-dropdown bg-white dark:bg-slate-800  text-base  border-[0px] dark:border-[1px] dark:border-slate-700;
  }
  .vs__search::placeholder {
    @apply text-gray-600;
  }
  .vs__actions svg {
    @apply fill-gray-500 w-[15px] h-[15px] mt-[6px] scale-[.8];
  }

  .vs--multiple {
    .vs__selected {
      
      @apply text-base  dark:text-slate-300 font-light bg-white dark:bg-slate-700 border-slate-200 dark:border-slate-700 border  h-fit;
      padding: 4px 8px !important;
      color: #0b3578 !important;
   
    }
    .vs__deselect {
      @apply dark:fill-slate-300;
    }

    .vs__selected-options {
      @apply items-center capitalize text-gray-600;
      svg {
        @apply scale-[0.8];
      }
      
    }
  }
  .vs--single .vs__selected {
    @apply dark:text-slate-300 text-gray-600;
  }
  .vs__dropdown-option--disabled {
    @apply bg-slate-50 dark:bg-slate-700;
  }
}
</style>
