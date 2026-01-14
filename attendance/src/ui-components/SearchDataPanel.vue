<!-- components/SearchPanel.vue -->
<template>
  <transition name="slide-expand">
    <div class="flex items-center space-x-2" v-if="modelValue.show">
      <!-- Column Select -->
      <select
        v-model="modelValue.filters.search.column"
        class="border px-2 py-1 text-sm rounded-md"
      >
        <option disabled value="">Search by...</option>
        <option
          v-for="opt in columnOptions"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>

      <!-- Search Input -->
      <input
        id="searchInput"
        v-model.trim="inputValue"
        type="text"
        placeholder="Search..."
        class="px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white w-48 focus:outline-none focus:ring-1 focus:ring-blue-500"
      />

      <!-- Reset -->
      <button
        v-if="inputValue || modelValue.filters.search.column"
        @click="resetSearch"
        class="p-2 rounded-md hover:bg-gray-100 text-sm text-gray-600 flex items-center"
      >
        <Icon icon="heroicons-outline:x" class="text-base mr-1" />
        Reset
      </button>
    </div>
  </transition>
</template>

<script setup>
import { ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { debounce } from "@/utils/debounce";

const props = defineProps({
  modelValue: Object,
  columnOptions: Array,
});
const emit = defineEmits(["update:modelValue"]);

const inputValue = ref(props.modelValue.filters.search.term);

// Debounced search input update
const debouncedSearch = debounce(val => {
  emit("update:modelValue", {
    ...props.modelValue,
    filters: {
      ...props.modelValue.filters,
      search: {
        ...props.modelValue.filters.search,
        term: val,
      },
    },
  });
}, 500);

watch(inputValue, val => debouncedSearch(val));

const resetSearch = () => {
  emit("update:modelValue", {
    ...props.modelValue,
    filters: {
      ...props.modelValue.filters,
      search: {
        column: "",
        term: "",
      },
    },
  });
  inputValue.value = "";
};
</script>

<style>
.slide-expand-enter-active,
.slide-expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}
.slide-expand-enter-from {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: left;
}
.slide-expand-enter-to {
  transform: scaleX(1);
  opacity: 1;
}
.slide-expand-leave-from {
  transform: scaleX(1);
  opacity: 1;
}
.slide-expand-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: left;
}
</style>
