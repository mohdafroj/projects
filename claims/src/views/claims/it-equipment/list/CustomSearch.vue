<template>
  <div class="flex items-center gap-2">
    <!-- <span class="text-gray-500">
      <Icon icon="mdi:magnify" class="text-xl" />
    </span> -->

    <select
      v-model="selectedColumn"
      class="border rounded cursor-pointer px-3 py-1 text-sm text-gray-700 focus:outline-none"
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
      placeholder="Search..."
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

// Debounced emit
const debouncedEmit = debounce(() => {
  if (inputValue.value != "") {
    emit("search", {
      key: selectedColumn.value,
      value: inputValue.value,
    });
  }
}, 500);

function onInput(e) {
  const filterData = props.searchableColumns.filter(item => item.type == 'Amount' && item.key == selectedColumn.value);
  if ( filterData.length ) {
    const regex = /^\d*\.?\d*$/;
    if (!regex.test(e.target.value)) {
      e.target.value = e.target.value.slice(0, -1);
    }
    inputValue.value = e.target.value;
  }

  debouncedEmit();
}

function reset() {
  selectedColumn.value = "";
  inputValue.value = "";
  emit("reset");
}

watch(selectedColumn, () => {
  const filterData = props.searchableColumns.filter(item => item.type == 'Amount' && item.key == selectedColumn.value);
  if ( filterData.length ) {
    inputValue.value = "";
  }
  debouncedEmit();
})
</script>
