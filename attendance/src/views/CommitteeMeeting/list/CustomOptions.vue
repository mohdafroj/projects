<template>
  <div class="w-full mt-4 rounded-md">
    <div
      class="flex items-center justify-between px-4 py-2.5 bg-white rounded-t-md shadow-sm"
    >
      <h2 class="text-xl font-semibold">{{ props.tableTitle }}</h2>
      <div class="flex justify-end items-center gap-2">
        <!-- Buttons Container -->
        <div class="flex gap-2 items-center">
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
          aria-label="filter-icon"
        >
          <Icon icon="cuida:filter-outline" class="text-xl font-bold" />
        </button>

        <!-- Sort Icon -->
        <button
          @click="togglePanel(SORT_FIELD)"
          class="p-2 rounded-md hover:bg-gray-100"
          aria-label="sort-icon"
        >
          <Icon icon="tabler:sort-ascending" class="text-xl font-bold" />
        </button>

        <!-- Search UI -->
        <div class="relative flex items-center space-x-2">
          <button
            class="p-2 rounded-md hover:bg-gray-100"
            @click="toggleSearch"
            aria-label="search-icon"
          >
            <Icon icon="tabler:search" class="text-xl font-bold" />
          </button>
          <transition name="slide-expand">
            <div class="flex items-center space-x-2" v-if="showSearch">
              <SearchBox
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
        <SortBox
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
               <!-- select for input type -->
                <!-- <template v-else-if="col.key === 'meeting_no'">
                  <select
                    v-model="filters.meeting_no"
                    class="border p-2.5 text-sm rounded w-full"
                    @change="emitFilter('meeting_no')"
                  >
                    <option value="" disabled selected>Select Meeting No</option>
                    <option
                      v-for="option in meetingNoOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                   
                </template> -->
              
                <template v-else-if="col.key === 'meeting_no'">
                    <Multiselect
                      v-model="filters[col.key]"
                      :options="meetingNoOptions"
                      :close-on-select="true"
                      :clear-on-select="false"
                      :searchable="true"
                      :multiple="false"
                      placeholder="Select Committee"
                      class="text-sm custom-multiselect"
                      track-by="meeting_no"
                      label="meeting_no"
                      @blur="emitFilter('meeting_no')"
                      openDirection="below"
                      @select="emitFilter('meeting_no')"
                      @remove="emitFilter('meeting_no')"
                      @input="emitFilter('meeting_no')"
                    />
                </template>
                <!-- Committee Dropdown -->
                    <!-- <template v-if="col.key === 'committee_name'">
                      <select
                        v-model="filters.committee_name"
                        class="border p-2 rounded w-full text-sm"
                        @change="emitFilter('committee_name')"
                      >
                        <option value="">Select Committee</option>
                        <option v-for="item in committeeOptions" :key="item.id"
                      :value="item.id">
                          {{ item.label }}
                        </option>
                      </select>
                    </template> -->
              <!-- Multiselect for String field types -->
               <!-- <Multiselect
                v-else
                v-model="filters[col.key]"
                :options="getUniqueOptions(col.key)"
                :multiple="true"
                :searchable="true"
                placeholder="Select or search..."
                class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                :close-on-select="false"
                :clear-on-select="false"
                selectLabel=""
                deselectLabel=""
                :showLabels="false"
                :optionsLimit="10"
                openDirection="below"
                :limit-text="limitText"
                @select="() => false"
                @remove="emitFilter"
                @search-change="handleSearchInput(col.key, $event)"
                @focusout="emitFilter"
              />  -->
              
              <!-- Committee Multiselect -->
               <template v-else-if="col.key === 'committee_name'">
                 <Multiselect
                  v-model="filters[col.key]"
                  :options="committeeOptions"
                  :close-on-select="false"
                  :clear-on-select="false"
                  :searchable="true"
                  :multiple="true"
                  placeholder="Select Committee"
                  class="text-sm custom-multiselect"
                  track-by="id"
                  label="name"
                  :value="filters.name"
                  @blur="emitFilter('name')"
                   openDirection="below"
                   @select="emitFilter('name')"
                @remove="emitFilter"
                @input= "emitFilter('name')"
                />
              </template>
               <template v-else-if="col.key === 'venue'">
                 <Multiselect
                  v-model="filters[col.key]"
                  :options="venueOptions"
                  :close-on-select="false"
                  :clear-on-select="false"
                  :searchable="true"
                  :multiple="true"
                  placeholder="Select venue"
                  class="text-sm custom-multiselect"
                  track-by="id"
                  label="venue_name"
                  :value="filters.venue_name"
                 
                  @blur="emitFilter('venue_name')"
                   openDirection="below"
                   @select="emitFilter"
                @remove="emitFilter"
                @input="val => filters.venue_name = val.map(s => s.venue_name)"
                />
              </template>
              <!-- Venue Multiselect -->
              <!-- <template v-else-if="col.key === 'venue'">
                <Multiselect
                  v-model="filters.venue"
                  :options="venueOptions"
                  :searchable="true"
                  :multiple="false"
                  placeholder="Select Venue"
                  class="text-sm"
                  @input="emitFilter('venue')"
                  @blur="emitFilter('venue')"
                />
              </template> -->
               <!-- <template v-else>
                  <Multiselect
                    v-model="filters[col.key]"
                    :options="getUniqueOptions(col.key)"
                    :multiple="true"
                    :searchable="true"
                    placeholder="Select or search..."
                    class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                    :close-on-select="false"
                    :clear-on-select="false"
                    selectLabel=""
                    deselectLabel=""
                    track-by="committee_name"
                    :showLabels="false"
                    :optionsLimit="10"
                    openDirection="below"
                    :limit-text="limitText"
                    @select="() => false"
                    @remove="emitFilter"
                    @search-change="handleSearchInput(col.key, $event)"
                    @focusout="emitFilter"
                  />
                </template> -->
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
import { computed, reactive, ref, nextTick, watch,onMounted } from "vue";
import SearchBox from "./CustomSearch.vue";
import SortBox from "./CustomSort.vue";

import { Icon } from "@iconify/vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import { debounce } from "@/utils/debounce";
import { fetchmeetingNo,fetchCommitteeOption,fetchVenueList} from '@/services/committeeServices';

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
 // From API
 // From table data

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
  if (term && term.trim()) {
    filters[key] = [term.trim()];
  } else {
    filters[key] = [];
  }
  //emitFilter();
}, 500);

const emitFilter = () => {
  // Create clean filter object with only non-empty values
  const cleanFilters = {};
  
  for (const [key, value] of Object.entries(filters)) {
    if (Array.isArray(value)) {
      if (value.length > 0 && value.some(v => v !== null && v !== undefined && v !== '')) {
        // For date/amount filters, only include if both values exist
        const column = props.columns?.find(col => col.key === key);
        if (column?.type === 'Date' || column?.type === 'Amount') {
          if (value[0] !== '' && value[1] !== '') {
            cleanFilters[key] = value;
          }
        } else {
          // For string arrays, filter out empty values
          const nonEmptyValues = value.filter(v => v !== null && v !== undefined && v !== '');
          if (nonEmptyValues.length > 0) {
            cleanFilters[key] = nonEmptyValues;
          }
        }
      }
    } else if (value !== null && value !== undefined && value !== '') {
      cleanFilters[key] = value;
    }
  }
  
  //console.log('Emitting clean filters:', cleanFilters);
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

const addFilter = async (selectedData) => {
  if (!selectedData) return;
  switch(selectedData) {
    case 'committee_name' : 
      await fetchCommitteeNames();
      break;
    case 'meeting_no' : 
      await fetchMeetingNumbers();
     break;
    case 'date_time': 
     break;
    case 'venue':
      await fetchVenueoption();
      break;
    default:
  }  
  const exists = activeFilters.value.some(s => s.key === selectedData);
  if (!exists) {
    const column = props.columns?.find(col => col.key === selectedData);
    activeFilters.value.push({
      key: selectedData,
      type: column?.type || 'String'
    });
  }
  
  // Reset the dropdown
  selectedField.value = "";
};

const onSelectHandle = () => {
  if (selectedField.value) {
    addFilter(selectedField.value);
   
  }
};

const deleteFilter = (key) => {
  const index = activeFilters.value.findIndex(f => f.key === key);
  if (index !== -1) {
    activeFilters.value.splice(index, 1);
    
    // Reset the filter value
    const column = props.columns?.find(col => col.key === key);
    if (column?.type === 'Date' || column?.type === 'Amount') {
      filters[key] = ['', ''];
    } else {
      filters[key] = [];
    }
    
    // Close any open dropdowns for this field
    if (activeAmount.value === key) {
      activeAmount.value = "";
    }
    if (activeCalendar.value === key) {
      activeCalendar.value = "";
    }
    
    emitFilter();
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
const meetingNoOptions = ref([]);
//const committeelistOptions = ref([])
const committeeOptions = ref([]);
const venueOptions = ref([])
const fetchMeetingNumbers = async () => {
  try {
    const response = await fetchmeetingNo();
    //meetingNoOptions.value = response.data || [];
   
 if (response?.data) {
      meetingNoOptions.value = response.data;
    }
  } catch (error) {
    console.error("Failed to fetch meeting numbers:", error);
  }
};

const fetchCommitteeNames = async () => {
  try {
    const response = await fetchCommitteeOption(); 
    if (response?.data) {
       committeeOptions.value = response.data;
    }
  } catch (error) {
    console.error("Failed to fetch committee names:", error);
  }
};
const fetchVenueoption = async () => {
  try {
    const response = await fetchVenueList()
    if (response?.data) {
       venueOptions.value = response.data;
    } 
  }
    catch (error) {
    console.error("Failed to fetch venue names:", error);
  }

}
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
onMounted(  () => {
  //await fetchMeetingNumbers();
  //await fetchVenueoption();
 //await fetchCommitteeNames();
});
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