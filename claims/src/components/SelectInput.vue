<template>
  <div>
    <label
      v-if="label"
      class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300 pr-1"
    >
      {{ label }}
    </label>
    <span v-if="isRequired" class="text-gray-500 dark:text-slate-300">*</span>

    <Multiselect
      label="label"
      trackBy="value"
      class="single-select"
      :options="options"
      :multiple="multiple"
      :modelValue="selectedOption"
      :searchable="search"
      :loading="loading"
      :placeholder="placeholder"
      :showLabels="false"
      :closeOnSelect="!multiple"
      :allowEmpty="true"
      :disabled="disabled"
      :class="[error ? 'border-red-500' : '']"
      @update:modelValue="onSelect"
      @search-change="onSearch"
    >
      <template #noOptions> No record(s) </template>
      <template #noResult> No search record(s) </template>
    </Multiselect>

    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, toRefs } from 'vue';
import Multiselect from 'vue-multiselect';

const props = defineProps({
  modelValue: {
    type: [Object, String, Number, Array, null],
    default: null,
  },
  options: {
    type: Array,
    required: true, // [{ label, value }]
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  search: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
  multiple: {
    type: Boolean,
    default: false, // ✅ allow both single & multi
  },
  isRequired: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'change', 'search']);

const { modelValue, options, multiple } = toRefs(props);

// Handle selected option(s)
const selectedOption = computed(() => {
  if (multiple.value) {
    // ensure it's always an array
    if (Array.isArray(modelValue.value)) {
      return options.value.filter(opt => modelValue.value.includes(opt.value));
    }
    return [];
  } else {
    if (typeof modelValue.value === 'object' && modelValue.value !== null)
      return modelValue.value;
    return options.value.find(opt => opt.value == modelValue.value) || null;
  }
});

const onSearch = (query = '') => {
  if (!query) return false;
  emit('search', query);
  return true;
};

// Emit on selection
function onSelect(option) {
  if (multiple.value) {
    // send array of values
    const values = option.map(o => o.value);
    emit('update:modelValue', values);
    emit('change', values);
  } else {
    emit('update:modelValue', option ? option.value : null);
    emit('change', option ? option.value : null);
  }
}
</script>

<style src="vue-multiselect/dist/vue-multiselect.min.css"></style>
<style scoped>
:deep(.multiselect) {
  border-radius: 0.4rem !important;
  min-height: 38px !important;
}

.single-select :deep(.multiselect__tags) {
  min-height: 38px !important;
  border: 0px solid #d1d5db;
  font-size: 13px;
  @apply bg-white dark:bg-gray-900;
}

/* Remove background for all multiselect options */
.single-select :deep(.multiselect__element),
.single-select :deep(.multiselect__option) {
  background-color: transparent !important;
  font-size: 14px;
}

/* Prevent highlight on hover */
.single-select :deep(.multiselect__option--highlight) {
  background-color: #f4f5f7 !important;
  color: inherit !important;
  font-size: 14px;
}

/* Prevent selected option styling */
.single-select :deep(.multiselect__option--selected) {
  background-color: #f4f5f7 !important;
  color: inherit !important;
  font-size: 14px;
}

/* Enable vertical scroll when options overflow */
.single-select :deep(.multiselect__content-wrapper) {
  max-height: 200px; /* or any height you want */
  overflow-y: auto;
  overflow-x: hidden; /* Hide horizontal scroll */
  font-size: 14px;
}

/* Make sure option text wraps and shows fully */
.single-select :deep(.multiselect__option) {
  white-space: normal !important;
  overflow: visible !important;
  text-overflow: unset !important;
  word-break: break-word;
  font-size: 14px;
}

.single-select :deep(.multiselect__single) {
  font-size: 14px !important;
  background: transparent !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  display: block;
}

.single-select :deep(.multiselect__tag) {
  color: inherit;
  background: #e3efff;
  font-size: 1rem;
}

.single-select :deep(.multiselect__input) {
  background: transparent !important;
}

:deep(.multiselect--disabled .multiselect__select) {
  background: transparent !important;
}

:deep(.multiselect--disabled .multiselect__tags) {
  background: #f1f5f9 !important;
}

.single-select :deep(::-webkit-scrollbar) {
  width: 2px;
}

.single-select :deep(::-webkit-scrollbar-track) {
  background: transparent;
}

.single-select :deep(::-webkit-scrollbar-thumb) {
  background-color: #a0aec0;
  border-radius: 9999px;
}

.single-select :deep(:hover::-webkit-scrollbar-thumb) {
  background-color: #718096;
}

.single-select :deep(.multiselect__option--disabled) {
  background-color: #f1f5f9 !important;
  color: #6b7280 !important;
  font-weight: 500;
  padding-left: 5px;
  font-size: 1.1rem;
}
</style>
