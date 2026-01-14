<script setup>
import { ref, nextTick, computed } from "vue";
import { useDeppartmentData } from "@/views/drag/departmentData.js";
import Icon from "@/ui-components/Icon";

const { data, toggleExpand, hasChildren } = useDeppartmentData();
// Get data and related functions from the separate component
// const { data, toggleExpand, hasChildren } = useDeppartmentData()

// Sorting state
const sortBy = ref("name");
const sortOrder = ref("asc"); // 'asc' or 'desc'

// Filter state
const filterText = ref("");
const filterColumn = ref("name"); // default filter column

// Active filter dropdown state
const activeFilter = ref(null); // null, 'filter', 'sort', or 'search'
const activeDropdown = ref(null); // null, 'name', 'submission date', 'billAmount', 'status'

// Filter values
const statusFilter = ref("");
// const teamSizeFilter = ref('')
const budgetRangeFilter = ref("");
const fromDateFilter = ref("");
const toDateFilter = ref("");

// Toggle dropdown
const toggleDropdown = dropdown => {
  activeDropdown.value = activeDropdown.value === dropdown ? null : dropdown;
};

// Reset all filters
const resetAllFilters = () => {
  filterText.value = "";
  statusFilter.value = "";
  // teamSizeFilter.value = ''
  budgetRangeFilter.value = "";
  activeFilter.value = null;
  toDateFilter.value = "";
  fromDateFilter.value = "";
  activeFilter.value = null;
  activeDropdown.value = null;
};

// Sort operations
const showSortSelector = ref(false);
const sortColumnOptions = [
  "name",
  "submissionDate",
  "billAmount",
  "claimStatus",
];

const deleteSort = () => {
  // This would typically remove a sort criterion
  // For demo purposes, we'll just reset to default
  sortBy.value = "name";
  sortOrder.value = "asc";
};

// Handle sorting
const sortData = data => {
  // Create a copy to avoid mutating the original data
  const sortedData = [...data];

  // Handle billAmount sorting (convert from string to number)
  if (sortBy.value === "billAmount") {
    sortedData.sort((a, b) => {
      const valueA = parseInt(a.billAmount.replace(/[^0-9]/g, ""));
      const valueB = parseInt(b.billAmount.replace(/[^0-9]/g, ""));
      return sortOrder.value === "asc" ? valueA - valueB : valueB - valueA;
    });
  }
  // Handle employee sorting (numeric)
  else if (sortBy.value === "employees") {
    sortedData.sort((a, b) => {
      return sortOrder.value === "asc"
        ? a.employees - b.employees
        : b.employees - a.employees;
    });
  } else if (sortBy.value === "claimStatus") {
    sortedData.sort((a, b) => {
      return sortOrder.value === "asc"
        ? a.claimStatus - b.claimStatus
        : b.claimStatus - a.claimStatus;
    });
  }
  // Handle name sorting (alphabetic)
  else {
    sortedData.sort((a, b) => {
      if (sortOrder.value === "asc") {
        return a.name.localeCompare(b.name);
      } else {
        return b.name.localeCompare(a.name);
      }
    });
  }

  // Sort children recursively
  for (const item of sortedData) {
    if (item.subRows && item.subRows.length > 0) {
      item.subRows = sortData(item.subRows);
    }
  }

  return sortedData;
};

// Toggle sort order
const toggleSort = column => {
  if (sortBy.value === column) {
    // Toggle order if clicking the same column
    sortOrder.value = sortOrder.value === "asc" ? "desc" : "asc";
  } else {
    // Set new column and default to ascending
    sortBy.value = column;
    sortOrder.value = "asc";
  }
};

// Flatten the data based on expanded state
const flattenData = (data, depth = 0) => {
  let result = [];

  for (const row of data) {
    row.depth = depth;
    result.push(row);

    if (row.expanded && hasChildren(row)) {
      result = [...result, ...flattenData(row.subRows, depth + 1)];
    }
  }

  return result;
};

// Computed sorted data
const sortedData = computed(() => {
  return sortData(data.value);
});

// Enhanced filter function for nested data
const filterData = (data, filters) => {
  if (
    !filters.text &&
    !filters.status &&
    !filters.teamSize &&
    !filters.budgetRange &&
    !filters.fromDate &&
    !filters.toDate
  ) {
    return data;
  }

  const filteredData = [];

  for (const row of data) {
    let rowMatches = true;

    //Date filter
    // Check date range filter
    // Check date range filter
    if (rowMatches && (filters.fromDate || filters.toDate)) {
      const rowDate = new Date(row.submissionDate);
      const fromDate = filters.fromDate ? new Date(filters.fromDate) : null;
      const toDate = filters.toDate ? new Date(filters.toDate) : null;

      if (fromDate && rowDate < fromDate) {
        rowMatches = false;
      }
      if (toDate && rowDate > toDate) {
        rowMatches = false;
      }
    }

    // Check text filter
    if (filters.text && filters.column) {
      const columnValue = String(row[filters.column]).toLowerCase();
      if (!columnValue.includes(filters.text.toLowerCase())) {
        rowMatches = false;
      }
    }

    // Check status filter
    if (rowMatches && filters.status && row.claimStatus) {
      if (
        !row.claimStatus.toLowerCase().includes(filters.status.toLowerCase())
      ) {
        rowMatches = false;
      }
    }

    // Check team size filter
    if (rowMatches && filters.teamSize) {
      if (filters.teamSize === "small" && row.employees > 20) {
        rowMatches = false;
      } else if (
        filters.teamSize === "medium" &&
        (row.employees <= 20 || row.employees > 50)
      ) {
        rowMatches = false;
      } else if (filters.teamSize === "large" && row.employees <= 50) {
        rowMatches = false;
      }
    }

    // Check billAmount range filter
    if (rowMatches && filters.budgetRange) {
      const billAmount = parseInt(row.billAmount.replace(/[^0-9]/g, ""));
      if (filters.budgetRange === "low" && billAmount > 25000) {
        rowMatches = false;
      } else if (
        filters.budgetRange === "medium" &&
        (billAmount <= 25000 || billAmount > 50000)
      ) {
        rowMatches = false;
      } else if (filters.budgetRange === "high" && billAmount <= 50000) {
        rowMatches = false;
      }
    }

    // Check if any child matches the filter
    const childrenMatch =
      row.subRows && filterData(row.subRows, filters).length > 0;

    // Include the row if it matches or if any of its children match
    if (rowMatches || childrenMatch) {
      // Create a copy of the row to avoid modifying the original
      const rowCopy = { ...row };

      // If the row has children, filter them too
      if (row.subRows) {
        rowCopy.subRows = filterData(row.subRows, filters);

        // If we're keeping the row because of matching children, ensure it's expanded
        if (!rowMatches && childrenMatch) {
          rowCopy.expanded = true;
        }
      }

      filteredData.push(rowCopy);
    }
  }

  return filteredData;
};

// Computed filtered and sorted data
const filteredData = computed(() => {
  const filters = {
    text: filterText.value,
    column: filterColumn.value,
    status: statusFilter.value,
    // teamSize: teamSizeFilter.value,
    budgetRange: budgetRangeFilter.value,
    fromDate: fromDateFilter.value,
    toDate: toDateFilter.value,
  };

  return filterData(data.value, filters);
});

// Computed flattened, filtered, and sorted data
const flattenedRows = computed(() => {
  // return flattenData(sortData(filteredData.value))
  return sortData(filteredData.value);
});

// Helpers for arrow indicators
const getSortIndicator = column => {
  if (sortBy.value !== column) return "";
  return sortOrder.value === "asc" ? "↑" : "↓";
};
// toogle search
const showSearch = ref(false);

const toggleSearch = () => {
  showSearch.value = !showSearch.value;
  if (showSearch.value) {
    nextTick(() => {
      document.getElementById("searchInput")?.focus();
    });
  }
};
</script>
<template>
  <div class="table-container">
    <div class="flex justify-between items-center mb-4">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Claims</h2>
        <p class="text-sm text-gray-600">
          Claims submitted under IT equipment, TA-DA and Medical categories
        </p>
      </div>

      <div class="flex items-center space-x-3">
        <!-- Filter Icon -->
        <button
          @click="activeFilter = activeFilter === 'filter' ? null : 'filter'"
          class="p-2 rounded-md hover:bg-gray-100"
        >
          <!-- <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg> -->
          <Icon icon="tabler:filter-plus" class="text-xl font-bold" />
        </button>

        <!-- Sort Icon -->
        <button
          @click="activeFilter = activeFilter === 'sort' ? null : 'sort'"
          class="p-2 rounded-md hover:bg-gray-100"
        >
          <!-- <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
            </svg> -->
          <Icon icon="tabler:sort-ascending" class="text-xl font-bold" />
        </button>

        <!-- Search Icon + Input with Transition -->
        <!-- <div class="relative flex items-center">
  <button @click="toggleSearch" class="p-2 rounded-md hover:bg-gray-100">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
  </button>

  <transition name="slide-expand">
    <input
      v-if="showSearch"
      id="searchInput"
      v-model="filterText"
      type="text"
      placeholder="Type to Search"
      class="ml-2 px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-48"
    />
  </transition>
</div> -->
        <!-- Search Icon + Input with Column Selector -->
        <div class="relative flex items-center space-x-2">
          <button
            @click="toggleSearch"
            class="p-2 rounded-md hover:bg-gray-100"
          >
            <!-- <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg> -->
            <Icon icon="tabler:search" class="text-xl font-bold" />
          </button>

          <!-- <transition name="slide-expand"> -->
          <transition
            name="slide-expand1"
            enter-active-class="transition-all duration-300 ease-in-out"
            enter-from-class="opacity-0 -translate-x-1/3"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-300 ease-in-out"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-1/3"
          >
            <div v-if="showSearch" class="flex items-center space-x-2">
              <!-- Dropdown to choose the search column -->
              <select
                v-model="filterColumn"
                class="text-sm border px-2 py-1 rounded-md bg-white"
              >
                <option value="name">Name</option>
                <option value="employees">Employees</option>
              </select>

              <!-- Search input -->
              <input
                id="searchInput"
                v-model="filterText"
                type="text"
                placeholder="Search..."
                class="px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white w-48 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          </transition>
        </div>

        <!-- Reset Button -->
        <button
          v-if="
            filterText ||
            statusFilter ||
            budgetRangeFilter ||
            fromDateFilter ||
            toDateFilter
          "
          @click="resetAllFilters"
          class="p-2 rounded-md hover:bg-gray-100 text-sm text-gray-600 flex items-center"
        >
          <!-- <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg> -->
          <Icon icon="heroicons-outline:x" class="text-base mr-1" />
          Reset
        </button>
      </div>
    </div>

    <!-- Filter Options Row - Shows when a filter is active -->
    <transition
      name="fade-slide"
      appear
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-300"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-30 -translate-y-2"
    >
      <div
        v-if="activeFilter"
        class="mb-4 px-2 py-3 bg-gray-50 border border-gray-200 rounded-md"
      >
        <!-- Filter Options -->
        <div
          v-if="activeFilter === 'filter'"
          class="flex flex-wrap items-center gap-2"
        >
          <!-- Status Filter -->
          <div class="relative">
            <button
              @click="toggleDropdown('status')"
              class="px-3 py-1.5 rounded-md border border-gray-300 text-sm bg-white flex items-center text-gray-700 hover:bg-gray-50"
              :class="{
                'bg-blue-50 border-blue-300 text-blue-700': statusFilter,
              }"
            >
              <Icon
                icon="tabler:circle-dashed-check"
                class="text-xl font-bold mr-1"
              />
              {{ statusFilter || "All Status" }}

              <Icon
                icon="heroicons-outline:chevron-down"
                class="text-base ml-1"
              />
            </button>

            <!-- Status Dropdown -->
            <div
              v-if="activeDropdown === 'status'"
              v-click-outside="() => toggleDropdown(null)"
              class="absolute z-10 mt-1 w-48 bg-white shadow-lg rounded-md border border-gray-200 py-1"
            >
              <div
                @click="
                  statusFilter = '';
                  toggleDropdown('status');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                All Status
              </div>
              <div
                @click="
                  statusFilter = 'Initiated';
                  toggleDropdown('status');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Initiated
              </div>
              <div
                @click="
                  statusFilter = 'Reviewed';
                  toggleDropdown('status');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Reviewed
              </div>
              <div
                @click="
                  statusFilter = 'Approved';
                  toggleDropdown('status');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Approved
              </div>
              <div
                @click="
                  statusFilter = 'Canceled';
                  toggleDropdown('status');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Canceled
              </div>
            </div>
          </div>

          <!-- Date Filter Dropdown -->
          <div class="relative">
            <button
              @click="toggleDropdown('dateFilter')"
              class="px-3 py-1.5 rounded-md border border-gray-300 text-sm bg-white flex items-center text-gray-700 hover:bg-gray-50"
              :class="{
                'bg-blue-50 border-blue-300 text-blue-700':
                  fromDateFilter || toDateFilter,
              }"
            >
              <Icon
                icon="tabler:calendar-check"
                class="text-xl font-bold mr-1"
              />
              {{
                fromDateFilter || toDateFilter
                  ? "Date Filter Applied"
                  : "Date Filter"
              }}
              <Icon
                icon="heroicons-outline:chevron-down"
                class="text-base ml-1"
              />
            </button>

            <!-- Date Filter Dropdown -->
            <div
              v-if="activeDropdown === 'dateFilter'"
              v-click-outside="() => toggleDropdown(null)"
              class="absolute z-10 mt-1 w-64 bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 space-y-2"
            >
              <div class="flex items-center justify-between">
                <label class="text-sm text-gray-700">From:</label>
                <input
                  type="date"
                  v-model="fromDateFilter"
                  class="border px-2 py-1 rounded-md text-sm w-40"
                />
              </div>
              <div class="flex items-center justify-between">
                <label class="text-sm text-gray-700">To:</label>
                <input
                  type="date"
                  v-model="toDateFilter"
                  class="border px-2 py-1 rounded-md text-sm w-40"
                />
              </div>
            </div>
          </div>

          <!-- billAmount Range Filter -->
          <div class="relative">
            <button
              @click="toggleDropdown('billAmount')"
              class="px-3 py-1.5 rounded-md border border-gray-300 text-sm bg-white flex items-center text-gray-700 hover:bg-gray-50"
              :class="{
                'bg-blue-50 border-blue-300 text-blue-700': budgetRangeFilter,
              }"
            >
              <Icon
                icon="tabler:currency-rupee"
                class="text-xl font-bold mr-1"
              />
              {{
                budgetRangeFilter
                  ? `Bill Amount: ${budgetRangeFilter}`
                  : "Bill Amount Range"
              }}
              <!-- <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg> -->
              <Icon
                icon="heroicons-outline:chevron-down"
                class="text-base ml-1"
              />
            </button>

            <!-- billAmount Range Dropdown -->
            <div
              v-if="activeDropdown === 'billAmount'"
              v-click-outside="() => toggleDropdown(null)"
              class="absolute z-10 mt-1 w-56 bg-white shadow-lg rounded-md border border-gray-200 py-1"
            >
              <div
                @click="
                  budgetRangeFilter = '';
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                All Budgets
              </div>
              <div
                @click="
                  budgetRangeFilter = 'low';
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Low (<₹25000)
              </div>
              <div
                @click="
                  budgetRangeFilter = 'medium';
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Medium (₹20000-₹50000)
              </div>
              <div
                @click="
                  budgetRangeFilter = 'high';
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                High (₹50000+)
              </div>
            </div>
          </div>
        </div>

        <!-- Sort Options -->
        <!-- <div v-if="activeFilter === 'sort'" class="flex items-center space-x-2">
          <button @click="addSort" class="px-3 py-1.5 rounded-md border border-gray-300 text-sm bg-white flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Sort
          </button>
          
          <button @click="deleteSort" class="px-3 py-1.5 rounded-md border border-gray-300 text-sm bg-white flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete Sort
          </button>
          
          <select 
            v-model="sortOrder" 
            class="px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white"
          >
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div> -->
        <!-- Sort Options -->
        <div v-if="activeFilter === 'sort'" class="flex items-center space-x-2">
          <!-- Add Sort Button -->
          <!-- Add Sort Button -->
          <div class="relative">
            <button
              @click="showSortSelector = !showSortSelector"
              class="px-3 py-1.5 rounded-md text-sm flex items-center"
              :class="[
                sortBy
                  ? 'bg-blue-50 border border-blue-300 text-blue-700 hover:bg-white'
                  : 'bg-white border-gray-300 text-gray-700',
              ]"
            >
              <Icon icon="heroicons-outline:plus" class="text-base mr-1" />
              {{
                sortBy
                  ? sortBy.charAt(0).toUpperCase() + sortBy.slice(1)
                  : "Add Sort"
              }}
            </button>

            <!-- Dropdown -->
            <div
              v-if="showSortSelector"
              class="absolute mt-1 w-40 bg-white shadow-md rounded-md border border-gray-200 z-10"
            >
              <div
                v-for="option in sortColumnOptions"
                :key="option"
                @click="
                  () => {
                    sortBy = option;
                    showSortSelector = false;
                  }
                "
                class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer capitalize"
              >
                {{ option }}
              </div>
            </div>
          </div>

          <!-- Asc/Desc -->
          <!-- Sort Order Dropdown Button -->
          <div class="relative">
            <button
              @click="
                activeDropdown =
                  activeDropdown === 'sortOrder' ? null : 'sortOrder'
              "
              class="px-3 py-1.5 rounded-md text-sm flex items-center"
              :class="[
                sortOrder
                  ? 'bg-blue-50 border border-blue-300 text-blue-700 hover:bg-white'
                  : 'bg-white border-gray-300 text-gray-700',
              ]"
            >
              {{ sortOrder === "asc" ? "Ascending" : "Descending" }}
              <Icon
                icon="heroicons-outline:chevron-down"
                class="text-base ml-2"
              />
            </button>

            <div
              v-if="activeDropdown === 'sortOrder'"
              class="absolute z-10 mt-1 w-40 bg-white shadow-md rounded-md border border-gray-200"
            >
              <div
                @click="
                  () => {
                    sortOrder = 'asc';
                    activeDropdown = null;
                  }
                "
                class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer"
              >
                Ascending
              </div>
              <div
                @click="
                  () => {
                    sortOrder = 'desc';
                    activeDropdown = null;
                  }
                "
                class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer"
              >
                Descending
              </div>
            </div>
          </div>
          <!-- Delete Sort -->
          <button
            @click="deleteSort"
            class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:border-blue-300 hover:text-blue-700 bg-white flex items-center"
          >
            <Icon icon="heroicons-outline:x" class="text-base mr-1" />
            Reset Sort
          </button>
        </div>

        <!-- Search Options -->
        <!-- <div v-if="activeFilter === 'search'" class="flex items-center space-x-2">
          <div class="relative">
            <input 
              v-model="filterText"
              type="text" 
              placeholder="Search1..." 
              class="pl-9 pr-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            />
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div> -->
      </div>
    </transition>
    <table class="w-full border-collapse shadow-lg rounded-md overflow-hidden">
      <thead>
        <tr class="bg-gray-100">
          <th>ID</th>
          <th>Claim ID</th>
          <th
            @click="toggleSort('name')"
            class="px-4 py-2 text-left font-semibold text-gray-700 cursor-pointer w-2/5 hover:bg-gray-200"
          >
            Name {{ getSortIndicator("name") }}
          </th>
          <th
            @click="toggleSort('submissionDate')"
            class="px-4 py-2 text-left font-semibold text-gray-700 cursor-pointer hover:bg-gray-200"
          >
            Submission {{ getSortIndicator("submissionDate") }}
          </th>
          <th
            @click="toggleSort('billAmount')"
            class="px-4 py-2 text-left font-semibold text-gray-700 cursor-pointer hover:bg-gray-200"
          >
            Bill Amount {{ getSortIndicator("billAmount") }}
          </th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in flattenedRows"
          :key="row.id"
          class="border-t border-gray-50 bg-gray-50 hover:bg-gray-100"
          :class="{ 'bg-gray-50': row.depth % 2 }"
        >
          <td>{{ row.id }}</td>
          <td>{{ row.claimId }}</td>
          <td class="px-4 py-2">
            <div
              class="flex items-center"
              :style="{ paddingLeft: `${row.depth * 1.5 + 0.5}rem` }"
            >
              <button
                v-if="hasChildren(row)"
                @click="toggleExpand(row)"
                class="cursor-pointer bg-transparent border-none text-xl font-bold mr-2 w-5 text-center focus:outline-none"
              >
                {{ row.expanded ? "−" : "+" }}
              </button>
              <span v-else class="w-5 inline-block"></span>
              <span :class="{ 'font-semibold': row.depth === 0 }">
                {{ row.name }}
              </span>
            </div>
          </td>
          <td class="px-4 py-2" :class="{ 'font-semibold': row.depth === 0 }">
            {{ row.submissionDate }}
          </td>
          <td class="px-4 py-2" :class="{ 'font-semibold': row.depth === 0 }">
            {{ row.billAmount }}
          </td>
          <td>
            <span
              class="px-2 py-1 rounded-full text-sm font-semibold"
              :class="{
                'bg-blue-100 text-blue-800': row.claimStatus === 'Submitted',
                'bg-green-100 text-green-800': row.claimStatus === 'Approved',
                'bg-red-100 text-red-800': row.claimStatus === 'Canceled',
                'bg-orange-100 text-orange-800':
                  row.claimStatus === 'Initiated',
              }"
            >
              {{ row.claimStatus }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
/* Add Tailwind classes to your HTML elements instead of these styles */
table {
  width: 100%;
  border-collapse: collapse;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
  border-radius: 5px;
  overflow: hidden;
}

th,
td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

th {
  background-color: #dfebfd;
  font-weight: bold;
  color: #555;
  transition: background-color 0.2s;
}

th:hover {
  background-color: #d5e5fd;
}

tr:hover {
  background-color: #f5f5f5;
}
/* search expend */
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
</style>
