<template>
  <div class="flex items-center gap-2">
    <!-- <span class="text-gray-500">
      <Icon icon="mdi:magnify" class="text-xl" />
    </span> -->

    <select
      v-model="selectedColumn"
      class="border rounded px-3 py-1 text-sm text-gray-700 focus:outline-none"
    >
      <option disabled value="">-- Select column --</option>
      <option v-for="col in searchableColumns" :key="col.key" :value="col.key">
        {{ col.label }}
      </option>
    </select>

    <input
      type="text"
      v-model="inputValue"
      @input="onInput"
      class="border rounded px-3 py-1 text-sm w-40 focus:outline-none"
      :placeholder="selectedColumn ? 'Search...' : 'Select column first'"
      :disabled="!selectedColumn"
    />

    <button
      @click="reset"
      v-if="selectedColumn"
      class="text-sm text-blue-500 cursor-pointer"
    >
      ❌ Reset
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { debounce } from "@/utils/debounce";

// Props
const props = defineProps({
  searchableColumns: {
    type: Array,
    required: true, // [{ key: 'clientId', label: 'CLIENT ID' }, ...]
  },
});

// Emits
const emit = defineEmits(["search", "reset"]);

// State
const selectedColumn = ref("");
const inputValue = ref("");

watch(props.searchableColumns, (newValue) => {
  console.log(newValue)
  if ( Array.isArray(newValue) && newValue.length == 1 ) {
    selectedColumn.value = newValue[0]['key'];
  } else {
    selectedColumn.value = "";
  }
},{immediate:true})

// Debounced emit
const debouncedEmit = debounce(() => {
  if (selectedColumn.value) {
    emit("search", {
      key: selectedColumn.value,
      value: inputValue.value,
    });
  }
}, 500);

function onInput() {
  debouncedEmit();
}

function reset() {
  selectedColumn.value = "";
  inputValue.value = "";
  emit("reset");
}
</script>
