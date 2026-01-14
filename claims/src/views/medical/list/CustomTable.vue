<template>
  <div class="space-y-4 flex flex-col mt-4 rounded-md">
    <!-- Data Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-fixed">
        <thead>
          <tr>
            <th v-for="col in columnMeta" :key="col.accessorKey"
              class="px-4 py-3 font-semibold align-middle bg-blue-100">
              <div class="flex items-center gap-1 cursor-pointer"
                @click="sortByColumn(col.header, filters.sort.order)">
                <span>{{ col.header }}</span>
                <!-- <Icon icon="prime:arrow-up" v-if="
                  filters.sort.order == 'asc' && col.header == filters.sort.by
                " width="16" height="16" class="text-black" />
                <Icon icon="prime:arrow-down" v-if="
                  filters.sort.order == 'desc' &&
                  col.header == filters.sort.by
                " width="16" height="16" class="text-black" /> -->
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-for="(row, idx) in tableData" :key="idx" class="relative border-t align-middle " :class="[props.showOpen ? 'hover:bg-gray-100' : '']"
            @mouseenter="hoveredRow = idx" @mouseleave="hoveredRow = null">
            <td v-if="$slots['cell-before']" class="px-4 py-3 text-center align-middle">
              <slot name="cell-before" :row="row" :index="row.id" />
            </td>

            <td v-for="col in columnMeta" :key="col.accessorKey" class="px-4 py-3 align-middle">
              <slot :name="`cell-${col.accessorKey}`" :cell="{
                row,
                column: col,
                value: getNestedValue(row, col.accessorKey),
              }" />

            </td>
              <router-link :to="{ path: 'medical-claim-detail/125' }" v-if="hoveredRow === idx && props.showOpen">
              <button class="absolute top-2 right-5 px-5 py-1 border-1 text-gray-700 bg-white rounded-full hover:bg-white">
              Open</button>
              </router-link>
            
            <td v-if="$slots['cell-after']" class="px-4 py-3 border-t text-center align-middle">
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
  showOpen:{
    type:Boolean,
    default:false
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
  text-align: left;
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
