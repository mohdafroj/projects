<template>
  <div class="space-y-4 flex flex-col mt-0 rounded-md">
    <!-- Data Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm table-fixed">
        <thead>
          <tr>
            <th
              v-for="col in columnMeta"
              :key="col.accessorKey"
              class="px-5 py-4 font-semibold  align-middle bg-[#dfebfd] dark:bg-slate-900 dark:text-gray-300"
          
            >
              <div
                class="flex  items-center gap-1 cursor-pointer text-left"
                @click="sortByColumn(col.accessorKey)"
              >
                <span>{{ col.header }}</span>
                <!-- MODIFICATION: Updated to use currentSort prop instead of internal filters -->
                <Icon
                  icon="prime:arrow-up"
                  v-if="currentSort.order === 'asc' && col.accessorKey === currentSort.key"
                  width="16"
                  height="16"
                  class="text-black"
                />
                <Icon
                  icon="prime:arrow-down"
                  v-if="currentSort.order === 'desc' && col.accessorKey === currentSort.key"
                  width="16"
                  height="16"
                  class="text-black"
                />
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800">
          <tr
            v-for="(row, idx) in tableData"
            :key="idx"
            class="relative border-t dark:border-gray-700 dark:text-gray-300 align-middle"
            :class="[props.showOpen ? 'hover:bg-gray-100' : '']"
            style="height: 70px"
            @mouseenter="hoveredRow = idx"
            @mouseleave="hoveredRow = null"
          >
            <td
              v-if="$slots['cell-before']"
              class="px-1 py-1 w-[80px]  align-middle"
            >
              <slot name="cell-before" :row="row" :index="row.id" />
            </td>

            <td
              v-for="col in columnMeta"
              :key="col.accessorKey"
              class="px-1 py-1   w-auto text-left align-middle"
            >
              <slot
                :name="`cell-${col.accessorKey}`"
                :cell="{ row, column: col, value: getNestedValue(row, col.accessorKey) }"
              />
            </td>

            <button
              v-if="hoveredRow === idx && props.showOpen"
              @click="openRow(row)"
              class="absolute top-3 right-5 px-4 py-1 border text-gray-700 bg-white rounded-full hover:bg-gray-100"
            >
              Open
            </button>

            <td
              v-if="$slots['cell-after']"
              class="px-1 py-1 border-t  align-middle"
            >
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
const props = defineProps({
  data: Array,
  customColumnOrder: Array,
  showOpen: {
    type: Boolean,
    default: false,
  },
  showActions: {
    type: Boolean,
    default: true,
  },
  // MODIFICATION: Added currentSort prop to receive sort state from parent
  currentSort: {
    type: Object,
    default: () => ({ key: "", order: "asc" })
  }
});

// MODIFICATION: Updated emits to include sort event
const emit = defineEmits(["rowOpened", "sort"]);
const hoveredRow = ref(null);

const tableData = computed(() => props.data || []);
const columnFields = computed(() =>
  Array.isArray(tableData.value) && tableData.value.length > 0
    ? Object.keys(tableData.value[0])
    : []
);
const columnData = computed(() => props.customColumnOrder || []);

// Columns Metadata
const columnMeta = computed(() =>
  columnData.value
    .filter((field) => columnFields.value.includes(field.key))
    .map((field) => ({
      header: field.label,
      accessorKey: field.key,
    }))
);

// MODIFICATION: Removed internal filters and sortedTableData since sorting is now handled by parent

// MODIFICATION: Updated sortByColumn to emit to parent instead of handling internally
const sortByColumn = (columnKey) => {
  let newOrder = "asc";
  
  // If clicking the same column, toggle the order
  if (props.currentSort.key === columnKey) {
    newOrder = props.currentSort.order === "asc" ? "desc" : "asc";
  }
  
  // Emit sort event to parent
  emit("sort", { key: columnKey, order: newOrder });
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
  /* text-align: center; */
}

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

/* Ensure action icons are visible */
td[slot="cell-after"] {
  display: flex;
  /* justify-content: center; */
  align-items: center;
}

button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

button:hover {
  opacity: 0.8;
}
</style>