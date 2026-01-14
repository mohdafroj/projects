<template>
  <div class="w-full mt-4 rounded-md">
    <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded-t-md shadow-sm">
      <h2 class="text-xl font-semibold">{{ props.tableTitle }}</h2>
      <div class="flex justify-end items-center gap-2">
        <!-- Add New Button -->
        <!-- <button
          @click="$emit('addItems', true)"
          variant="primary"
          class="text-white px-4 bg-blue-500 hover:bg-blue-600 py-2 rounded-md text-sm flex items-center"
        >
          <Icon icon="heroicons-outline:plus" class="text-base mr-1" />
          {{ props.addFieldtext }}
        </button> -->
        <!-- Buttons Container -->
        <div class="flex gap-2 items-center">
          <button v-for="(btn, index) in props.addButtons" :key="index" @click="$emit('addItems', index, btn.text)"
            :class="`${btn.bgColor} ${btn.textColor} px-4 py-2 rounded-md text-sm flex items-center hover:opacity-90`">
            <Icon :icon="btn.icon" class="text-base mr-1" />
            {{ btn.text }}
          </button>
        </div>
        <!-- Filter Icon -->
        <button @click="togglePanel(SELECTED_FIELD)" class="p-2 rounded-md hover:bg-gray-100">
          <Icon icon="cuida:filter-outline" class="text-xl font-bold" />
        </button>

        <!-- Sort Icon -->
        <button @click="togglePanel(SORT_FIELD)" class="p-2 rounded-md hover:bg-gray-100">
          <Icon icon="tabler:sort-ascending" class="text-xl font-bold" />
        </button>

        <!-- Search UI -->
        <div class="relative flex items-center space-x-2">
          <button class="p-2 rounded-md hover:bg-gray-100" @click="toggleSearch">
            <Icon icon="tabler:search" class="text-xl font-bold" />
          </button>
          <transition name="slide-expand">
            <div class="flex items-center space-x-2" v-if="showSearch">
              <CustomSearch :searchableColumns="searchableColumns" @search="handleSearch" @reset="handleReset" />
            </div>
          </transition>
        </div>
        <!-- download file-->
        <button @click="$emit('exportDownloadFile')" v-if="props.isDownload" class="p-2 hover:bg-gray-100 rounded-md">
          <Icon icon="tabler:download" class="text-lg" />
        </button>
      </div>
    </div>

    <div v-if="activePanel === SORT_FIELD || activePanel === SELECTED_FIELD"
      class="space-y-4 bg-white mt-0 px-2 py-0 w-full flex flex-row justify-start rounded-b-md">
      <!-- Sort -->
      <div v-if="activePanel === SORT_FIELD">
        <CustomSort :sortableColumns="sortableColumns" @sort="handleSort" @reset="handleSortReset" />
      </div>

      <!-- Filters -->

      <div v-if="activePanel === SELECTED_FIELD">
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4 px-2 pb-2 min-w-[1100px]">
          <!-- Filters -->
          <div class="flex flex-row -center gap-3 rounded-md">
            <!-- Sort Field Dropdown -->
            <div class="relative flex flex-col w-full">
              <label class="text-xs text-gray-600 font-semibold mb-1">Add filters</label>
              <select v-model="selectedField"
                class="border-[1.5px] border-[#d1d5db] text-sm px-3 py-2 rounded appearance-none focus:outline-none w-full"
                @change="onSelectHandle($event)">
                <option v-for="field in filterableColumns" :key="field.key" :value="field.key">
                  {{ field.label }}
                </option>
              </select>
              <Icon icon="mdi:plus" class="absolute left-2 top-[30px] text-lg pointer-events-none" />
            </div>

            <!-- Add Filter -->
            <!-- Delete Sort -->
          </div>
          <!-- Inside the filter loop -->
          <div v-for="col in activeFilters" :key="col.key" class="flex flex-col w-full relative">
            <label class="text-xs text-gray-600 font-semibold mb-1">
              {{ getFieldLabel(col.key) }}
            </label>
            <button
              class="absolute bg-transparent top-[12px] right-[-6px] border-1 rounded-md w-fit items-center gap-1 text-sm z-[20] bg-white cursor-pointer hover:text-red-400"
              @click="deleteFilter(col.key)">
              <Icon icon="material-symbols-light:cancel" class="font-bold" width="20" height="20" />
            </button>
            <div class="flex flex-col gap-4">
              <!-- Date Filter Dropdown -->
              <template v-if="getFieldType(col.key) === 'Date'">
                <div class="relative" v-click-outside="handleCalendarClickOutside">
                  <button @click="toggleCalendarOption('dateFilter')"
                    class="px-3 py-2.5 rounded-md border border-gray-300 text-sm bg-white flex justify-between items-center w-full text-gray-700 ">
                    <div class="flex flex-row">
                      <Icon icon="tabler:calendar-check" class="text-xl font-bold mr-1" />
                      {{ filters[col.key][0] }} - {{ filters[col.key][1] }}
                    </div>
                    <Icon icon="heroicons-outline:chevron-down" class="text-base ml-10" />
                  </button>
                  <!-- Date Filter Dropdown -->
                  <div v-if="activeCalendar === 'dateFilter'"
                    class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 space-y-2">
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">From:</label>
                      <input v-model="filters[col.key][0]" type="date" class="border px-2 py-1 rounded-md text-sm w-40"
                        @keydown.prevent />
                    </div>
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">To:</label>
                      <input v-model="filters[col.key][1]" type="date" class="border px-2 py-1 rounded-md text-sm w-40"
                        @keydown.prevent />
                    </div>
                  </div>
                </div>
              </template>
              <!-- Amount Filter Dropdown -->
              <template v-else-if="getAmountFieldType(col.key) === 'Amount'">
                <div class="relative" v-click-outside="handleAmountClickOutside">
                  <button @click="toggleAmountOption(col.key)"
                    class="px-3 py-2.5 rounded-md border border-gray-300 text-sm bg-white flex justify-between items-center w-full text-gray-700 ">
                    <div class="flex flex-row">
                      <Icon icon="material-symbols:currency-rupee-rounded" class="text-xl font-bold mr-1" />
                      {{
                        filters[col.key][0] ? filters[col.key][0] + " -" : ""
                      }}
                      {{ filters[col.key][1] }}
                    </div>
                    <Icon icon="heroicons-outline:chevron-down" class="text-base ml-10" />
                  </button>

                  <div v-if="activeAmount === col.key"
                    class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 space-y-2">
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">From:</label>
                      <input v-model.number="filters[col.key][0]" type="number" min="0" placeholder="From"
                        class="border px-2 py-1 rounded-md text-sm w-40" @input="validateAndEmitAmount(col.key)" />
                    </div>
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">To:</label>
                      <input v-model.number.trim="filters[col.key][1]" type="number" min="0" placeholder="To"
                        class="border px-2 py-1 rounded-md text-sm w-40" @input="validateAndEmitAmount(col.key)" />
                    </div>
                  </div>
                </div>
              </template>
              <!-- Multiselect for Sting field types -->
              <Multiselect v-else v-model="filters[col.key]" :options="getUniqueOptions(col.key)" :multiple="true"
                :searchable="true" placeholder="Select or search..."
                class="text-sm text-blue-500 rounded h-fit custom-multiselect" :close-on-select="false"
                :clear-on-select="false" selectLabel="" deselectLabel="" :showLabels="false" :optionsLimit="10"
                openDirection="below" :limit-text="limitText" @select="emitFilter" @remove="emitFilter"
                @search-change="handleSearchInput(col.key, $event)" />
            </div>
          </div>

        </div>
        <!-- Reset Filters -->
        <button v-if="activeFilters.length" @click="resetFilter"
          class="flex text-sm w-fit text-red-500 items-center gap-1 my-4 ml-6 float-end">
          <Icon icon="iconoir:cancel" width="18" height="18" />Clear all
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, nextTick, watch } from "vue";
import CustomSearch from "./CustomSearch.vue";
import CustomSort from "./CustomSort.vue";
import { Icon } from "@iconify/vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import { debounce } from "@/utils/debounce";

// Props
const props = defineProps({
  tableTitle: String,
  addButtons: {
    type: Array,
    default: () => [],
  },
  props: Boolean,
  columns: Array,
  data: Array,
  excludeSearch: { type: Array, default: () => [] },
  excludeSort: { type: Array, default: () => [] },
  excludeFilter: { type: Array, default: () => [] },
});

// Emits
const emit = defineEmits([
  "search",
  "sort",
  "filter",
  "clearSearch",
  "clearSort",
  "addItems",
  "exportDownloadFile"
]);

const SORT_FIELD = "sort";
const SELECTED_FIELD = "filter";
const selectedField = ref("");
const filters = reactive({});
const activePanel = ref(null);
const showSearch = ref(false);

const activeCalendar = ref("");
const activeAmount = ref("");
const activeFilters = ref([]); // 🔥 Track added sorts

const togglePanel = panel => {
  activePanel.value = activePanel.value === panel ? null : panel;
};

const toggleCalendarOption = data => {
  activeCalendar.value = activeCalendar.value === data ? null : data;
};

const toggleAmountOption = amount => {
  activeAmount.value = activeAmount.value === amount ? null : amount;
};

watch(
  () => ({ ...filters }), // watch all filter changes
  newFilters => {
    for (const key in newFilters) {
      const column = props.columns.find(col => col.key === key);

      if (column?.type === "Date" && Array.isArray(newFilters[key])) {
        const [from, to] = newFilters[key];

        if (from && to && new Date(to) < new Date(from)) {
          // Auto-fix or reset `to` date if it's invalid
          filters[key][1] = from;
        }
        if (filters[key].length > 1) emitFilter();
      }
    }
  },
  { deep: true },
);

watch(
  () => props.columns,
  cols => {
    for (const col of cols) {
      if (!props.excludeFilter.includes(col.key)) {
        filters[col.key] = [];
      }
    }
  },
  { immediate: true },
);

const toggleSearch = () => {
  showSearch.value = !showSearch.value;
  if (showSearch.value) {
    nextTick(() => {
      document.getElementById("searchInput")?.focus();
    });
  }
};

const searchableColumns = computed(() =>
  props.columns.filter(col => !props.excludeSearch.includes(col?.key)),
);
const sortableColumns = computed(() =>
  props.columns.filter(col => !props.excludeSort.includes(col?.key)),
);
const filterableColumns = computed(() =>
  props.columns.filter(col => !props.excludeFilter.includes(col?.key)),
);
watch(
  filterableColumns,
  cols => {
    selectedField.value = cols.length > 0 ? cols[0].key : "";
    activeFilters.value.push({ key: selectedField.value, type: cols[0].type });
  },
  { deep: true },
);

function handleSearch({ key, value }) {
  emit("search", { key, value });
}

const handleAmountClickOutside = () => {
  activeAmount.value = null;
}

const handleCalendarClickOutside = () => {
  activeCalendar.value = null;
}

const limitText = count => `and ${count} more options selected`;

const handleReset = () => {
  showSearch.value = false;
  emit("clearSearch", null);
};

const handleSort = ({ key, order }) => {
  emit("sort", { key, order });
};

const handleSortReset = () => {
  activeFilters.value = [];
  emit("clearSort");
};

const handleSearchInput = debounce((key, term) => {
  // searchQuery.value = term;
  emit("filter", { ...filters, [key]: term });
}, 500);

const emitFilter = () => {
  emit("filter", { ...filters });
};

const resetFilter = () => {
  for (const key of Object.keys(filters)) {
    filters[key] = [];
  }
  emit("filter", { ...filters });
};

const getUniqueOptions = key => {
  const set = new Set();
  for (const row of props.data) {
    const value = getNestedValue(row, key);
    if (value != null && value !== "") {
      set.add(value);
    }
  }
  return Array.from(set);
};

const getNestedValue = (obj, path) => {
  return path
    .split(".")
    .reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), obj);
};

const addFilter = selectedData => {
  const exists = activeFilters.value.some(s => s.key === selectedData);
  console.log("exists", exists);

  if (!exists) {
    activeFilters.value.push({
      key: selectedData,
    });
    //emit("sort", activeSorts.value);
  }
};

const onSelectHandle = event => {
  addFilter(event.target.value);
};

// const deleteFilter = () => {
//   if (activeFilters.value.length > 0) {
//     const index = activeFilters.value.findIndex(
//       s => s.key === selectedField.value,
//     );
//     if (index !== -1) {
//       activeFilters.value.splice(index, 1); // remove only matching sort
//       filters[selectedField.value] = [];

//       emit("filter", { ...filters });
//     }
//   }
// };

const deleteFilter = key => {
  const index = activeFilters.value.findIndex(f => f.key === key);
  if (index !== -1) {
    activeFilters.value.splice(index, 1);
    filters[key] = [];
    emit("filter", { ...filters });
  }
};

const getFieldLabel = key => {
  const field = filterableColumns.value.find(f => f.key === key);
  return field ? field.label : key;
};

const getFieldType = key => {
  const field = props.columns.find(f => f.key === key);

  return field ? field.type : "String";
};

//check whether the column type is 'Amount' or not.
const getAmountFieldType = key => {
  const field = props.columns.find(f => f.key === key);
  const type = field?.type?.toLowerCase();

  if (type === "amount") return "Amount";
  return "String";
};

function preventInvalidInput(event) {
  // Prevent `-`, `e`, etc.
  if (["e", "E", "+", "-"].includes(event.key)) {
    event.preventDefault();
  }
}

//filter Amount input validation
const validateAndEmitAmount = debounce(key => {
  const [from, to] = filters[key];
  if (from < 0) filters[key][0] = 0;
  if (to < 0) filters[key][1] = 0;

  // Ensure `To` >= `From`
  if (to < from) {
    filters[key][1] = from;
  }

  // Emit only when both are valid numbers
  if (typeof from === "number" && typeof to === "number") {
    emitFilter();
  }
}, 500);
</script>

<style scoped>
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

/* Avoid dropdown from overflowing screen */
:deep(.multiselect-dropdown) {
  max-height: 200px;
  overflow-y: auto;
  z-index: 50;
}

select {
  padding-left: 2.25rem;
  /* leave space for icon if needed */
}

/* Override vue-multiselect tag colors */
.custom-multiselect :deep(.multiselect__tag) {
  background: #f3f4f6 !important;
  /* Light gray background */
  color: #374151 !important;
  /* Dark gray text */
  border: 1px solid #d1d5db !important;
  /* Light gray border */
}

/* Override tag close button */
.custom-multiselect :deep(.multiselect__tag-icon) {
  background: transparent !important;
  color: #6b7280 !important;
  /* Medium gray */
}

.custom-multiselect :deep(.multiselect__tag-icon:hover) {
  background: #ef4444 !important;
  /* Red on hover for remove action */
  color: white !important;
}

/* Optional: Style the multiselect input */
.custom-multiselect :deep(.multiselect__input) {
  background: transparent;
  color: #374151;
}

/* Optional: Style the placeholder */
.custom-multiselect :deep(.multiselect__placeholder) {
  color: #9ca3af;
}

/* Optional: Style the dropdown options */
.custom-multiselect :deep(.multiselect__option--highlight) {
  background: #f3f4f6 !important;
  color: #374151 !important;
}

.custom-multiselect :deep(.multiselect__option--selected) {
  background: #e5e7eb !important;
  color: #374151 !important;
}
</style>
