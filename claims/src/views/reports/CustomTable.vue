<template>
  <div class="space-y-4 flex flex-col rounded-md">
    <!-- Data Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-fixed">
        <thead>
          <tr>
            <th v-for="col in columnMeta" :key="col.accessorKey"
              class="px-5 py-4 font-semibold text-center align-middle" style="background-color: #dfebfd">
              <div class="flex justify-center items-center gap-1 cursor-pointer"
                @click="sortByColumn(col.header, filters.sort.order)">
                <span>{{ col.header }}</span>
                <Icon icon="prime:arrow-up" v-if="
                  filters.sort.order == 'asc' && col.header == filters.sort.by
                " width="16" height="16" class="text-black" />
                <Icon icon="prime:arrow-down" v-if="
                  filters.sort.order == 'desc' &&
                  col.header == filters.sort.by
                " width="16" height="16" class="text-black" />
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-for="(row, idx) in tableData" :key="idx" class="relative border-t align-middle "
            :class="[props.showOpen ? 'hover:bg-gray-100' : '']" style="height: 70px" @mouseenter="hoveredRow = idx"
            @mouseleave="hoveredRow = null">
            <td v-if="$slots['cell-before']" class="px-1 py-1 w-[80px] text-center align-middle">
              <slot name="cell-before" :row="row" :index="row.id" />
            </td>

            <td v-for="col in columnMeta" :key="col.accessorKey" class="px-1 py-1 w-[250px] text-center align-middle">
              <slot :name="`cell-${col.accessorKey}`" :cell="{
                row,
                column: col,
                value: getNestedValue(row, col.accessorKey),
                index: idx
              }" />

            </td>
            <button v-if="hoveredRow === idx && props.showOpen" @click="openRow(row)"
              class="absolute top-3 right-5 px-4 py-1 border-1 text-gray-700 bg-white rounded-full hover:bg-white">
              open</button>
            <td v-if="$slots['cell-after']" class="px-1 py-1 border-t text-center align-middle">
              <slot name="cell-after" :row="row" :index="row.id" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";

// Props
const props = defineProps({
  data: Array,
  customColumnOrder: Array,
  showOpen: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(["filtersChanged"]);
const hoveredRow = ref(null);

const tableData = computed(() => props.data || []);
const columnFields = computed(() =>
  Array.isArray(tableData.value) && tableData.value.length > 0
    ? Object.keys(tableData.value[0])
    : [],
);
const columnData = computed(() => props.customColumnOrder || []);

// Format Headers
function formatHeader(key) {
  return key
    .replace(/([A-Z])/g, " $1")
    .replace(/_/g, " ")
    .toUpperCase();
}

//Columns Metadata
// const columnMeta = computed(() =>
//   columnData.value
//     .filter(field => columnFields.value.includes(field))
//     .map(field => ({
//       header: field,
//       accessorKey: field,
//     })),
// );

const columnMeta = computed(() =>
  columnData.value
    .filter(field => columnFields.value.includes(field.key))
    .map(field => ({
      header: field.label,
      accessorKey: field.key,
    }))
);

// Filters & Panels
const filters = ref({
  sort: { by: "", order: "asc" },
});

//column wise sorting
const sortByColumn = (colHeader, order) => {
  if (order == "asc") {
    filters.value.sort.order = "desc";
    filters.value.sort.by = colHeader;
  } else {
    filters.value.sort.order = "asc";
    filters.value.sort.by = colHeader;
  }
  emit("filtersChanged", { ...filters.value });
};

// Helper: Nested Path Access
function getNestedValue(obj, path) {
  return path.split(".").reduce((acc, key) => acc?.[key], obj);
}

function openRow(row) {
  emit("rowOpened", row);
}
</script>

<style scoped>
th,
td {
  text-align: center;
}

.slide-expand-enter-active,
.slide-expand-leave-active {
  transition: all 0.3ms ease;
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
