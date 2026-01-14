<template>
  <div class="w-full mt-4 rounded-md">
    <div
      class="flex items-center justify-between px-4 py-2.5 bg-white rounded-t-md shadow-sm"
    >
      <h2 class="text-xl font-semibold text-gray-800">{{ props.tableTitle }}</h2>
      <div class="flex justify-end items-center gap-2">
        <!-- Buttons Container -->
        <div v-if=checkPermission class="flex gap-2 items-center">
          <button
            v-for="(btn, index) in props.addButtons"
            :key="index"
            @click="$emit('addItems', index, btn.text)"
            :class="`${btn.bgColor} ${btn.textColor} ${btn.hoverColor} px-4 py-2 rounded-md text-sm flex items-center hover:opacity-90`" 
          >
            <Icon :icon="btn.icon" class="text-base mr-1" />
            {{ btn.text }}
          </button>
        </div>
        <!-- Filter Icon -->
        <button
          @click="togglePanel(SELECTED_FIELD)"
          class="p-2 rounded-md hover:bg-gray-100"
          aria-label="filter-button"
        >
          <Icon icon="cuida:filter-outline" class="text-xl font-bold" />
        </button>

        <!-- Sort Icon -->
        <button
          @click="togglePanel(SORT_FIELD)"
          class="p-2 rounded-md hover:bg-gray-100"
          aria-label="sort-button"
        >
          <Icon icon="tabler:sort-ascending" class="text-xl font-bold" />
        </button>

        <!-- Search UI -->
        <div class="relative flex items-center space-x-2">
          <button
            class="p-2 rounded-md hover:bg-gray-100"
            @click="toggleSearch"
            aria-label="search-button"
          >
            <Icon icon="tabler:search" class="text-xl font-bold" />
          </button>
          <transition name="slide-expand">
            <div class="flex items-center space-x-2" v-if="showSearch">
              <CustomSearch
                :searchableColumns="searchableColumns"
                @search="handleSearch"
                @reset="handleReset"
              />
            </div>
          </transition>
        </div>
      </div>
    </div>

    <div
      v-if="activePanel === SORT_FIELD || activePanel === SELECTED_FIELD"
      class="space-y-4 bg-white mt-0 px-2 py-0 w-full flex flex-row justify-start rounded-b-md"
    >
      <!-- Sort -->
      <div v-if="activePanel === SORT_FIELD">
        <CustomSort
          :sortableColumns="sortableColumns"
          @sort="handleSort"
          @reset="handleSortReset"
        />
      </div>

      <!-- Filters -->
      <div v-if="activePanel === SELECTED_FIELD">
        <div
          class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4 px-2 pb-2 min-w-[1100px]"
        >
          <!-- Add Filter Dropdown -->
          <div class="flex flex-row items-center gap-3 rounded-md">
            <div class="relative flex flex-col w-full">
              <label class="text-xs text-gray-600 font-semibold mb-1">Add filters</label>
              <select
                v-model="selectedField"
                class="border-[1.5px] border-[#e1e4e8] text-sm px-8 py-2 rounded appearance-none focus:outline-none w-full addFilterSelect"
                @change="onSelectHandle"
              >
                <option value="" disabled>Select a field to filter</option>
                <option
                  v-for="field in availableFilterFields"
                  :key="field.key"
                  :value="field.key"
                >
                  {{ field.label }}
                </option>
              </select>
              <Icon
                icon="mdi:plus"
                class="absolute left-2 top-[32px] text-lg pointer-events-none"
              />
            </div>
          </div>

          <!-- Active Filter Fields -->
          <div
            v-for="col in activeFilters"
            :key="col.key"
            class="flex flex-col w-full relative"
          >
            <label class="text-xs text-gray-600 font-semibold mb-1">
              {{ getFieldLabel(col.key) }}
            </label>
            <button
              class="absolute bg-transparent top-[12px] right-[-6px] border-1 rounded-md w-fit items-center gap-1 text-sm z-[999] bg-white cursor-pointer hover:text-red-400"
              @click="deleteFilter(col.key)"
            >
              <Icon
                icon="material-symbols-light:cancel"
                class="font-bold"
                width="20"
                height="20"
              />
            </button>
            <div class="flex flex-col gap-4">
              <!-- Date Filter Dropdown -->
              <template v-if="getFieldType(col.key) === 'Date'">
                <div class="relative" v-click-outside="handleCalendarClickOutside">
                  <button
                    @click="toggleCalendarOption('dateFilter')"
                    class="px-3 py-2.5 rounded-md border border-gray-300 text-sm bg-white flex justify-between items-center w-full text-gray-700 hover:bg-gray-50"
                  >
                    <div class="flex flex-row">
                      <Icon
                        icon="tabler:calendar-check"
                        class="text-xl font-bold mr-1"
                      />
                      {{ filters[col.key]?.[0] || 'From' }} - {{ filters[col.key]?.[1] || 'To' }}
                    </div>
                    <Icon
                      icon="heroicons-outline:chevron-down"
                      class="text-base ml-10"
                    />
                  </button>
                  <!-- Date Filter Dropdown -->
                  <div
                    v-if="activeCalendar === 'dateFilter'"
                    class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 space-y-2"
                  >
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">From:</label>
                      <input
                        v-model="filters[col.key][0]"
                        type="date"
                        class="border px-2 py-1 rounded-md text-sm w-40"
                        @change="emitFilter"
                      />
                    </div>
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">To:</label>
                      <input
                        v-model="filters[col.key][1]"
                        type="date"
                        class="border px-2 py-1 rounded-md text-sm w-40"
                        @change="emitFilter"
                      />
                    </div>
                  </div>
                </div>
              </template>
              <!-- Amount Filter Dropdown -->
              <template v-else-if="getAmountFieldType(col.key) === 'Amount'">
                <div class="relative" v-click-outside="handleAmountClickOutside">
                  <button
                    @click="toggleAmountOption(col.key)"
                    class="px-3 py-2.5 rounded-md border border-gray-300 text-sm bg-white flex justify-between items-center w-full text-gray-700 hover:bg-gray-50"
                  >
                    <div class="flex flex-row">
                      <Icon
                        icon="material-symbols:currency-rupee-rounded"
                        class="text-xl font-bold mr-1"
                      />
                      {{ filters[col.key]?.[0] ? filters[col.key][0] + " -" : "From -" }}
                      {{ filters[col.key]?.[1] || "To" }}
                    </div>
                    <Icon
                      icon="heroicons-outline:chevron-down"
                      class="text-base ml-10"
                    />
                  </button>

                  <div
                    v-if="activeAmount === col.key"
                    class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 space-y-2"
                  >
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">From:</label>
                      <input
                        v-model.number="filters[col.key][0]"
                        type="number"
                        min="0"
                        placeholder="From"
                        class="border px-2 py-1 rounded-md text-sm w-40"
                        @input="validateAndEmitAmount(col.key)"
                      />
                    </div>
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">To:</label>
                      <input
                        v-model.number="filters[col.key][1]"
                        type="number"
                        min="0"
                        placeholder="To"
                        class="border px-2 py-1 rounded-md text-sm w-40"
                        @input="validateAndEmitAmount(col.key)"
                      />
                    </div>
                  </div>
                </div>
              </template>
              <template v-else-if="col.key === 'session_type'">
                <div>
                  <Multiselect
                v-model="filters[col.key]"
                :options="props.sessionOptions"
                :multiple="true"
                :searchable="false"
                placeholder="Select or search..."
                class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                :close-on-select="false"
                :clear-on-select="false"
                selectLabel=""
                deselectLabel=""
                openDirection="below"
                track-by="id" 
                label="Session_type_name"
                :value="filters.session_type"
                :limit-text="limitText"
                @select="emitFilter"
                @remove="emitFilter"
                @input="val => filters.session_type = val.map(s => s.id)"
              /> 
                </div>
              </template>
              <!-- Multiselect for String field types -->
               <Multiselect
                v-else
                v-model="filters[col.key]"
                :options="props.sessionNumberList"
                :multiple="true"
                :searchable="true"
                placeholder="Select or search..."
                class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                :close-on-select="false"
                :clear-on-select="false"
                selectLabel=""
                deselectLabel=""
                :value="filters.session_number"
                openDirection="below"
                track-by="session_number"
                :limit-text="limitText"
                @select="emitFilter"
                @remove="emitFilter"
                label="session_number"
                
              /> 
            </div>
          </div>
        </div>
        <!-- Reset Filters -->
        <button
          v-if="activeFilters.length > 0"
          @click="resetFilter"
          class="flex text-sm w-fit text-red-500 items-center gap-1 my-4 ml-6 float-end"
        >
          <Icon icon="iconoir:cancel" width="18" height="18" />Clear all
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, nextTick, watch, onMounted } from "vue";
import CustomSearch from "./CustomSearch.vue";
import CustomSort from "./CustomSort.vue";
import { Icon } from "@iconify/vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import { debounce } from "@/utils/debounce";
import { fetchSessionTypes,fetchSessionNumber } from "@/services/rss/sessionService";
import Swal from "sweetalert2";
import { hasPermission } from "@/utils/rbac";

// Props
const props = defineProps({
  tableTitle: String,
  addButtons: {
    type: Array,
    default: () => [],
  },
  columns: Array,
  data: Array,
  excludeSearch: { type: Array, default: () => [] },
  excludeSort: { type: Array, default: () => [] },
  excludeFilter: { type: Array, default: () => [] },
  permission: { type: Array },
  sessionNumberList: {type:Array,default: () => []},
  sessionOptions:{ type:Array,default: () => []}
});
// Emits
const emit = defineEmits([
  "search",
  "sort",
  "filter",
  "clearSearch",
  "clearSort",
  "clearFilter",
  "addItems",
]);

const SORT_FIELD = "sort";
const SELECTED_FIELD = "filter";
const selectedField = ref("");
const filters = reactive({});
const activePanel = ref(null);
const showSearch = ref(false);

const activeCalendar = ref("");
const activeAmount = ref("");
const activeFilters = ref([]); // Track active filter fields

const sessionOptions = ref([]);
const sessionNumber = ref([]);
const checkPermission = ref(false);

const togglePanel = panel => {
  activePanel.value = activePanel.value === panel ? null : panel;
};

const toggleCalendarOption = data => {
  activeCalendar.value = activeCalendar.value === data ? null : data;
};

const toggleAmountOption = amount => {
  activeAmount.value = activeAmount.value === amount ? null : amount;
};

// Initialize filters for all columns
watch(
  () => props.columns,
  (cols) => {
    if (cols) {
      for (const col of cols) {
        if (!props.excludeFilter.includes(col.key)) {
          // Initialize filter arrays for different types
          if (col.type === 'Date' || col.type === 'Amount') {
            filters[col.key] = ['', ''];
          } else {
            filters[col.key] = [];
          }
        }
      }
    }
  },
  { immediate: true }
);

// Handle date filter validation
watch(
  () => ({ ...filters }),
  (newFilters) => {
    for (const key in newFilters) {
      const column = props.columns?.find(col => col.key === key);
      if (column?.type === "Date" && Array.isArray(newFilters[key])) {
        const [from, to] = newFilters[key];
        if (from && to && new Date(to) < new Date(from)) {
          filters[key][1] = from;
        }
      }
    }
  },
  { deep: true }
);

watch(
  () => props.permission,
  (newPerm) => {
 checkPermission.value = props.permission.some(p => hasPermission(p));
  },
  { immediate: true }  // run immediately on component mount
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
  props.columns?.filter(col => !props.excludeSearch.includes(col?.key)) || []
);

const sortableColumns = computed(() =>
  props.columns?.filter(col => !props.excludeSort.includes(col?.key)) || []
);

const filterableColumns = computed(() =>
  props.columns?.filter(col => !props.excludeFilter.includes(col?.key)) || []
);

// Available filter fields (excluding already active ones)
const availableFilterFields = computed(() =>
  filterableColumns.value.filter(col => 
    !activeFilters.value.some(active => active.key === col.key)
  )
);

function handleSearch({ key, value }) {
  emit("search", { key, value });
}

const handleAmountClickOutside = () => {
  activeAmount.value = "";
}

const handleCalendarClickOutside = () => {
  activeCalendar.value = "";
}

const limitText = count => `and ${count} more options selected`;

const handleReset = () => {
  showSearch.value = false;
  emit("clearSearch");
};

const handleSort = ({ key, order }) => {
  emit("sort", { key, order });
};

const handleSortReset = () => {
  emit("clearSort");
};

const handleSearchInput = debounce((key, term) => {
  console.log('🔍 [SEARCH DEBUG] Search input for key:', key, 'term:', term);
  
  if (term && term.trim()) {
    filters[key] = [term.trim()];
    console.log('✅ [SEARCH DEBUG] Set filter for', key, 'to:', [term.trim()]);
  } else {
    filters[key] = [];
    console.log('❌ [SEARCH DEBUG] Cleared filter for', key);
  }
  
  console.log('🔍 [SEARCH DEBUG] Current filters after search:', JSON.stringify(filters, null, 2));
  emitFilter();
}, 500);

const emitFilter = () => {
  console.log('🔍 [FILTER DEBUG] Raw filters object:', JSON.stringify(filters, null, 2));
  console.log('🔍 [FILTER DEBUG] Active filters:', activeFilters.value);
  console.log('🔍 [FILTER DEBUG] Available columns:', props.columns);
  
  // Create clean filter object with only non-empty values
  const cleanFilters = {};
  
  for (const [key, value] of Object.entries(filters)) {
    console.log(`🔍 [FILTER DEBUG] Processing filter key: ${key}, value:`, value, 'type:', typeof value);
    
    if (Array.isArray(value)) {
      console.log(`🔍 [FILTER DEBUG] ${key} is array with length:`, value.length);
      
      if (value.length > 0 && value.some(v => v !== null && v !== undefined && v !== '')) {
        // For date/amount filters, only include if both values exist
        const column = props.columns?.find(col => col.key === key);
        console.log(`🔍 [FILTER DEBUG] Column for ${key}:`, column);
        
        if (column?.type === 'Date' || column?.type === 'Amount') {
          console.log(`🔍 [FILTER DEBUG] ${key} is Date/Amount type. Values: [${value[0]}, ${value[1]}]`);
          if (value[0] !== '' && value[1] !== '' && value[0] !== null && value[1] !== null) {
            cleanFilters[key] = value;
            console.log(`✅ [FILTER DEBUG] Added ${key} to clean filters:`, value);
          } else {
            console.log(`❌ [FILTER DEBUG] Skipped ${key} - incomplete date/amount range`);
          }
        } else {
          // For string arrays, filter out empty values
          const nonEmptyValues = value.filter(v => v !== null && v !== undefined && v !== '');
          console.log(`🔍 [FILTER DEBUG] ${key} non-empty values:`, nonEmptyValues);
          
          if (nonEmptyValues.length > 0) {
            cleanFilters[key] = nonEmptyValues;
            console.log(`✅ [FILTER DEBUG] Added ${key} to clean filters:`, nonEmptyValues);
          } else {
            console.log(`❌ [FILTER DEBUG] Skipped ${key} - no non-empty values`);
          }
        }
      } else {
        console.log(`❌ [FILTER DEBUG] Skipped ${key} - empty array or all null values`);
      }
    } else if (value !== null && value !== undefined && value !== '') {
      cleanFilters[key] = value;
      console.log(`✅ [FILTER DEBUG] Added non-array ${key} to clean filters:`, value);
    } else {
      console.log(`❌ [FILTER DEBUG] Skipped ${key} - null, undefined, or empty string`);
    }
  }
  
  console.log('🚀 [FILTER DEBUG] Final clean filters being emitted:', JSON.stringify(cleanFilters, null, 2));
  console.log('🚀 [FILTER DEBUG] Clean filters object keys:', Object.keys(cleanFilters));
  console.log('🚀 [FILTER DEBUG] Clean filters size:', Object.keys(cleanFilters).length);
  
  emit("filter", cleanFilters);
};

const resetFilter = () => {
  // Reset all filter values
  for (const key of Object.keys(filters)) {
    const column = props.columns?.find(col => col.key === key);
    if (column?.type === 'Date' || column?.type === 'Amount') {
      filters[key] = ['', ''];
    } else {
      filters[key] = [];
    }
  }
  
  // Clear active filters
  activeFilters.value = [];
  selectedField.value = "";
  
  emit("clearFilter");
};

const getUniqueOptions = key => {
  if (!props.data || props.data.length === 0) return [];
  
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

const addFilter = (selectedData) => {
  console.log('🔍 [ADD FILTER DEBUG] Adding filter for:', selectedData);
  console.log('🔍 [ADD FILTER DEBUG] Current active filters:', activeFilters.value);
  
  if (!selectedData) {
    console.log('❌ [ADD FILTER DEBUG] No selected data provided');
    return;
  }
  
  const exists = activeFilters.value.some(s => s.key === selectedData);
  console.log('🔍 [ADD FILTER DEBUG] Filter exists check:', exists);
  
  if (!exists) {
    const column = props.columns?.find(col => col.key === selectedData);
    console.log('🔍 [ADD FILTER DEBUG] Found column:', column);
    
    const newFilter = {
      key: selectedData,
      type: column?.type || 'String'
    };
    
    activeFilters.value.push(newFilter);
    console.log('✅ [ADD FILTER DEBUG] Added new filter:', newFilter);
    console.log('🔍 [ADD FILTER DEBUG] Updated active filters:', activeFilters.value);
  } else {
    console.log('❌ [ADD FILTER DEBUG] Filter already exists for:', selectedData);
  }
  
  // Reset the dropdown
  selectedField.value = "";
  console.log('🔍 [ADD FILTER DEBUG] Reset selectedField');
};

const onSelectHandle = () => {
  if (selectedField.value) {
    addFilter(selectedField.value);
  }
};

const deleteFilter = (key) => {
  console.log('🗑️ [DELETE FILTER DEBUG] Deleting filter for key:', key);
  console.log('🔍 [DELETE FILTER DEBUG] Current active filters before delete:', activeFilters.value);
  
  const index = activeFilters.value.findIndex(f => f.key === key);
  console.log('🔍 [DELETE FILTER DEBUG] Filter index found:', index);
  
  if (index !== -1) {
    const removedFilter = activeFilters.value.splice(index, 1)[0];
    console.log('✅ [DELETE FILTER DEBUG] Removed filter:', removedFilter);
    console.log('🔍 [DELETE FILTER DEBUG] Active filters after removal:', activeFilters.value);
    
    // Reset the filter value
    const column = props.columns?.find(col => col.key === key);
    console.log('🔍 [DELETE FILTER DEBUG] Column for reset:', column);
    
    if (column?.type === 'Date' || column?.type === 'Amount') {
      filters[key] = ['', ''];
      console.log(`✅ [DELETE FILTER DEBUG] Reset ${key} filter to empty array for Date/Amount`);
    } else {
      filters[key] = [];
      console.log(`✅ [DELETE FILTER DEBUG] Reset ${key} filter to empty array for String`);
    }
    
    // Close any open dropdowns for this field
    if (activeAmount.value === key) {
      activeAmount.value = "";
      console.log('🔍 [DELETE FILTER DEBUG] Closed amount dropdown for', key);
    }
    if (activeCalendar.value === key) {
      activeCalendar.value = "";
      console.log('🔍 [DELETE FILTER DEBUG] Closed calendar dropdown for', key);
    }
    
    console.log('🔍 [DELETE FILTER DEBUG] Filters state after reset:', JSON.stringify(filters, null, 2));
    emitFilter();
  } else {
    console.log('❌ [DELETE FILTER DEBUG] Filter not found for key:', key);
  }
};

const getFieldLabel = key => {
  const field = filterableColumns.value.find(f => f.key === key);
  return field ? field.label : key;
};

const getFieldType = key => {
  const field = props.columns?.find(f => f.key === key);
  return field ? field.type : "String";
};

const getAmountFieldType = key => {
  const field = props.columns?.find(f => f.key === key);
  const type = field?.type?.toLowerCase();
  return type === "amount" ? "Amount" : "String";
};

const validateAndEmitAmount = debounce(key => {
  const [from, to] = filters[key];
  
  // Validate negative numbers
  if (from < 0) filters[key][0] = 0;
  if (to < 0) filters[key][1] = 0;

  // Ensure `To` >= `From`
  if (to < from && from !== '' && to !== '') {
    filters[key][1] = from;
  }

  // Emit when both values are present
  if (from !== '' && to !== '') {
    emitFilter();
  }
}, 500);


// const getSessionType = async () => {
//     const response = await fetchSessionTypes();
//     if (response.success_code == 200) {
//         sessionOptions.value = response.data;
//     }
//     else {
//         // Swal.fire({
//         //     toast: true,
//         //     position: "top-end",
//         //     icon: "error",
//         //     title: "Could not load the session type, something went wrong",
//         //     showConfirmButton: false,
//         //     timer: 3000,
//         //     timerProgressBar: true,
//         // });
//         console.log("Could not load the session type list");
        
//     }
// }
// const getSessionNumber = async () => {
//     const response = await fetchSessionNumber();
//     if (response.success_code == 200) {
//         sessionNumber.value = response.data;
//     }
//     else {
//       // Swal.fire({
//       //       toast: true,
//       //       position: "top-end",
//       //       icon: "error",
//       //       title: "Could not load the session number, something went wrong",
//       //       showConfirmButton: false,
//       //       timer: 3000,
//       //       timerProgressBar: true,
//       //   });
//       console.log("Unable to load session number list");
//     }
// }
// onMounted( async () => {
//   await getSessionType();
//   await getSessionNumber();
// })



</script>

<style scoped>
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

/* Avoid dropdown from overflowing screen */
:deep(.multiselect-dropdown) {
  max-height: 200px;
  overflow-y: auto;
  z-index: 50;
}

select {
  padding-left: 2.25rem;
}

/* Override vue-multiselect tag colors */
.custom-multiselect :deep(.multiselect__tag) {
  background: #f3f4f6 !important;
  color: #374151 !important;
  border: 1px solid #d1d5db !important;
}

.custom-multiselect :deep(.multiselect__tag-icon) {
  background: transparent !important;
  color: #6b7280 !important;
}

.custom-multiselect :deep(.multiselect__tag-icon:hover) {
  background: #ef4444 !important;
  color: white !important;
}

.custom-multiselect :deep(.multiselect__input) {
  background: transparent;
  color: #374151;
}

.custom-multiselect :deep(.multiselect__placeholder) {
  color: #9ca3af;
}

.custom-multiselect :deep(.multiselect__option--highlight) {
  background: #f3f4f6 !important;
  color: #374151 !important;
}

.custom-multiselect :deep(.multiselect__option--selected) {
  background: #e5e7eb !important;
  color: #374151 !important;
}
.addFilterSelect{
      padding: 10px 34px;
}
</style>