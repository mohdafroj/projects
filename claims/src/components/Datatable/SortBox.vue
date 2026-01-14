<template>
  <div class="flex items-center w-max gap-4 px-2 py-3">
    <select
      v-model="selectedSortKey"
      class="border rounded px-1 py-1 text-md text-gray-700 focus:outline-none"
    >
      <option disabled value="">Add Sort</option>
      <option v-for="col in sortableColumns" :key="col.key" :value="col.key">
        {{ col.label }}
      </option>
    </select>

    <select
      v-model="sortOrder"
      class="border rounded px-1 py-1 text-md text-blue-600 bg-blue-50 focus:outline-none"
      :disabled="!selectedSortKey"
    >
      <option value="asc">Ascending</option>
      <option value="desc">Descending</option>
    </select>

    <button
      v-if="selectedSortKey"
      @click="resetSort"
      class="text-sm text-blue-500 cursor-pointer"
    >
      ❌ Reset Sort
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  sortableColumns: {
    type: Array,
    required: true, // [{ key: 'name', label: 'Name' }, ...]
  },
});

const emit = defineEmits(["sort", "reset"]);

const selectedSortKey = ref("");
const sortOrder = ref("asc");

watch([selectedSortKey, sortOrder], ([key, order]) => {
  if (key) {
    emit("sort", { key, order });
  }
});

function resetSort() {
  selectedSortKey.value = "";
  sortOrder.value = "asc";
  emit("reset");
}
</script>
