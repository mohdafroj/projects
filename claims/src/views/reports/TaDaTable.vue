<template>
  <div class=" flex flex-col mt-2 rounded-md">
    <!-- Data Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-fixed">
        <thead class="bg-gray-200">
          <tr>
            <th v-for="col in columnMeta" :key="col.accessorKey"
              class="px-2 text-transform: capitalize p-3  bg-gray-200 font-semibold text-left align-middle" style="background-color: #E2E8F0">
              <div class="flex items-left gap-1 cursor-pointer text-transform: capitalize"
                @click="sortByColumn(col.header, filters.sort.order)">
                <span class="text-transform: capitalize">{{ formatHeader(col.header) }}</span>
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
          <tr v-for="(row, idx) in tableData" :key="idx" class="relative border-t align-middle " :class="[props.showOpen ? 'bg-gray-100' : '']" style="height: 70px"
            @mouseenter="hoveredRow = idx" @mouseleave="hoveredRow = null">
            <td v-if="$slots['cell-before']" class="px-1 py-1 w-[80px] text-left align-left">
              <slot name="cell-before" :row="row" :index="row.id" />
            </td>

            <td v-for="col in columnMeta" :key="col.accessorKey" class="px-1 py-1 w-[250px] text-left align-left">
              <slot :name="`cell-${col.accessorKey}`" :cell="{
                row,
                column: col,
                value: getNestedValue(row, col.accessorKey),
              }" />

            </td>
            <button v-if="hoveredRow === idx && props.showOpen" @click="openRow(row)" class="absolute top-3 right-5 px-4 py-1 border-1 text-gray-700 bg-white rounded-full hover:bg-white">
              open</button>
            <td v-if="$slots['cell-after']" class="px-1 py-1 border-t text-left align-left">
              <slot name="cell-after" :row="row" :index="row.id" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";

// Props
// Props - add a loading prop
const props = defineProps({
  data: Array,
  customColumnOrder: Array,
  showOpen: {
    type: Boolean,
    default: false
  },
  loading: {
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
    .replace(/_/g, " ");
}

// Columns Metadata
const columnMeta = computed(() =>
  columnData.value
    .filter(field => columnFields.value.includes(field))
    .map(field => ({
      header: field,
      accessorKey: field,
    })),
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
th {
  text-align: left;
}
td {
  text-align: left;
  padding-left: 10px;

}
 td:first-child {
   text-align: left!important;
   padding-left: 14px;
}
th:first-child{
   text-align: left!important;
   padding-left: 14px;
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
::v-deep(.p-6, .filters-container) {
  padding: 0px !important;
  margin: 0px !important;
}
</style>
