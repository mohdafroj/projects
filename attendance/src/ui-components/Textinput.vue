<template>
  <div
    class="fromGroup relative"
    :class="`${error ? 'has-error' : ''} ${horizontal ? 'flex' : ''} ${
      validate ? 'is-valid' : ''
    }`"
  >
    <label
      v-if="label"
      :class="`${classLabel} ${
        horizontal ? 'flex-0 mr-6 md:w-[100px] w-[60px] break-words' : ''
      } inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold`"
      :for="name"
    >
      {{ label }}
    </label>
    <div class="relative" :class="horizontal ? 'flex-1' : ''">
      <input
        :type="types"
        :name="name"
        :placeholder="placeholder"
        :class="`${classInput} input-control w-full block outline-2 outline-violet-200 outline-offset-4 h-[40px] ${
          hasicon ? 'pr-10' : ''
        }`"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :error="error"
        :id="name"
        :readonly="isReadonly"
        :disabled="disabled"
        :validate="validate"
        v-if="!isMask"
        ref="dateInput"
        @click="handleDateClick"
      />
      <cleave
        :class="`${classInput} cleave input-control block w-full focus:outline-none h-[40px]`"
        :name="name"
        :placeholder="placeholder"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :error="error"
        :id="name"
        :readonly="isReadonly"
        :disabled="disabled"
        :validate="validate"
        :options="options"
        v-if="isMask"
        modelValue="modelValue"
      />

      <div
        class="flex text-xl absolute right-[14px] top-1/2 -translate-y-1/2"
      >
        <span
          v-if="hasicon"
          @click="toggleType"
          class="cursor-pointer text-gray-500"
        >
          <Icon icon="heroicons-outline:eye" v-if="types === 'password'" />
          <Icon icon="heroicons-outline:eye-off" v-else />
        </span>

        <span v-if="error" class="text-danger-500">
          <!-- <Icon icon="heroicons:x-circle-16-solid"  /> -->
          <!-- <Icon icon="heroicons:x-mark-16-solid" width="16" height="16" /> -->
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

    <span
      v-if="error"
      class="mt-2"
      :class="
        msgTooltip
          ? 'inline-block bg-danger-500 text-white text-[10px] px-2 py-1'
          : 'text-danger-500 block text-sm'
      "
    >
      {{ error }}
    </span>
    <span
      v-if="validate"
      class="mt-2"
      :class="
        msgTooltip
          ? 'inline-block bg-success-500 text-white text-[10px] px-2 py-1'
          : 'text-success-500 block text-sm'
      "
    >
      {{ validate }}
    </span>
    <span
      class="block text-secondary-500 font-light leading-4 text-xs mt-2"
      v-if="description"
    >
      {{ description }}
    </span>
  </div>
</template>

<script setup>
import { ref } from "vue";
import Icon from "@/ui-components/Icon.vue";
import Cleave from "vue-cleave-component";

const props = defineProps({
  placeholder: {
    type: String,
    default: "Search",
  },
  label: {
    type: String,
  },
  classLabel: {
    type: String,
    default: " ",
  },
  classInput: {
    type: String,
    default: "classinput",
  },
  type: {
    type: String,
    default: "text",
  },
  name: {
    type: String,
  },
  modelValue: {
    type: String,
    default: "",
  },
  error: {
    type: String,
  },
  hasicon: {
    type: Boolean,
    default: false,
  },
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
  validate: {
    type: String,
  },
  msgTooltip: {
    type: Boolean,
    default: false,
  },
  description: {
    type: String,
  },
  isMask: {
    type: Boolean,
    default: false,
  },
  options: {
    type: Object,
    default: () => ({
      creditCard: true,
      delimiter: "-",
    }),
  },
});

// Emit the event for updating model value
const emit = defineEmits(["update:modelValue"]);

// State for the input type
const types = ref(props.type);

// Toggle the input type between password and text
const toggleType = () => {
  types.value = types.value === "text" ? "password" : "text";
};

// Reference to input field
const dateInput = ref(null);

// Handle click on date input
const handleDateClick = () => {
  if (props.type === "date" && dateInput.value) {
    dateInput.value.showPicker(); // Open the date picker
  }
};
</script>
