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
          <Icon icon="cuida:filter-outline" class="text-xl font-bold" />
        </button>

        <!-- Sort Icon -->
        <button
          @click="activeFilter = activeFilter === 'sort' ? null : 'sort'"
          class="p-2 rounded-md hover:bg-gray-100"
        >
          <Icon icon="tabler:sort-ascending" class="text-xl font-bold" />
        </button>

        <!-- Search Icon + Input with Column Selector -->
        <div class="relative flex items-center space-x-2">
          <button
            @click="toggleSearch"
            class="p-2 rounded-md hover:bg-gray-100"
          >
            <Icon icon="tabler:search" class="text-xl font-bold" />
          </button>

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
                v-model="searchColumn"
                class="text-sm border px-2 py-1 rounded-md bg-white"
              >
                <option value="name">Name</option>
                <option value="claimId">Claim ID</option>
              </select>

              <!-- Search input -->
              <input
                id="searchInput"
                v-model="globalFilter"
                type="text"
                placeholder="Search..."
                class="px-3 py-1.5 border border-gray-300 rounded-md text-sm bg-white w-48 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          </transition>
        </div>

        <!-- Reset Button -->
        <button
          v-if="hasActiveFilters"
          @click="resetAllFilters"
          class="p-2 rounded-md hover:bg-gray-100 text-sm text-gray-600 flex items-center"
        >
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
              class="absolute z-10 mt-1 w-48 bg-white shadow-lg rounded-md border border-gray-200 py-1"
            >
              <div
                @click="
                  setStatusFilter('');
                  toggleDropdown(null);
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                All Status
              </div>
              <div
                @click="
                  setStatusFilter('Initiated');
                  toggleDropdown(null);
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Initiated
              </div>
              <div
                @click="
                  setStatusFilter('Reviewed');
                  toggleDropdown(null);
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Reviewed
              </div>
              <div
                @click="
                  setStatusFilter('Approved');
                  toggleDropdown(null);
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Approved
              </div>
              <div
                @click="
                  setStatusFilter('Canceled');
                  toggleDropdown(null);
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
              <Icon
                icon="heroicons-outline:chevron-down"
                class="text-base ml-1"
              />
            </button>

            <!-- billAmount Range Dropdown -->
            <div
              v-if="activeDropdown === 'billAmount'"
              class="absolute z-10 mt-1 w-56 bg-white shadow-lg rounded-md border border-gray-200 py-1"
            >
              <div
                @click="
                  setBudgetRangeFilter('');
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                All Budgets
              </div>
              <div
                @click="
                  setBudgetRangeFilter('low');
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Low (<₹25000)
              </div>
              <div
                @click="
                  setBudgetRangeFilter('medium');
                  toggleDropdown('billAmount');
                "
                class="px-3 py-1.5 hover:bg-gray-100 cursor-pointer text-sm"
              >
                Medium (₹20000-₹50000)
              </div>
              <div
                @click="
                  setBudgetRangeFilter('high');
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
        <div v-if="activeFilter === 'sort'" class="flex items-center space-x-2">
          <!-- Add Sort Button -->
          <div class="relative">
            <button
              @click="showSortSelector = !showSortSelector"
              class="px-3 py-1.5 rounded-md text-sm flex items-center"
              :class="[
                sorting.length > 0
                  ? 'bg-blue-50 border border-blue-300 text-blue-700 hover:bg-white'
                  : 'bg-white border-gray-300 text-gray-700',
              ]"
            >
              <Icon icon="heroicons-outline:plus" class="text-base mr-1" />
              {{
                sorting.length > 0
                  ? formatSortColumn(sorting[0]?.id)
                  : "Add Sort"
              }}
            </button>

            <!-- Dropdown -->
            <div
              v-if="showSortSelector"
              class="absolute mt-1 w-40 bg-white shadow-md rounded-md border border-gray-200 z-10"
            >
              <div
                v-for="column in sortableColumns"
                :key="column.id"
                @click="
                  () => {
                    setSortColumn(column.id);
                    showSortSelector = false;
                  }
                "
                class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer capitalize"
              >
                {{ formatSortColumn(column.id) }}
              </div>
            </div>
          </div>

          <!-- Asc/Desc -->
          <div class="relative">
            <button
              @click="
                activeDropdown =
                  activeDropdown === 'sortOrder' ? null : 'sortOrder'
              "
              class="px-3 py-1.5 rounded-md text-sm flex items-center"
              :class="[
                sorting.length > 0
                  ? 'bg-blue-50 border border-blue-300 text-blue-700 hover:bg-white'
                  : 'bg-white border-gray-300 text-gray-700',
              ]"
            >
              {{
                sorting.length > 0 && sorting[0].desc
                  ? "Descending"
                  : "Ascending"
              }}
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
                    setSortDirection(false);
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
                    setSortDirection(true);
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
            @click="resetSort"
            class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:border-blue-300 hover:text-blue-700 bg-white flex items-center"
          >
            <Icon icon="heroicons-outline:x" class="text-base mr-1" />
            Reset Sort
          </button>
        </div>
      </div>
    </transition>

    <table class="w-full border-collapse shadow-lg rounded-md overflow-hidden">
      <thead>
        <tr class="bg-gray-100">
          <th class="px-4 py-2 text-left font-semibold text-gray-700">ID</th>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">
            Claim ID
          </th>
          <th
            @click="toggleColumn('name')"
            class="px-4 py-2 text-left font-semibold text-gray-700 cursor-pointer w-2/5 hover:bg-gray-200"
          >
            Name {{ getSortIndicator("name") }}
          </th>
          <th
            @click="toggleColumn('submissionDate')"
            class="px-4 py-2 text-left font-semibold text-gray-700 cursor-pointer hover:bg-gray-200"
          >
            Submission {{ getSortIndicator("submissionDate") }}
          </th>
          <th
            @click="toggleColumn('billAmount')"
            class="px-4 py-2 text-left font-semibold text-gray-700 cursor-pointer hover:bg-gray-200"
          >
            Bill Amount {{ getSortIndicator("billAmount") }}
          </th>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">
            Status
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-for="row in table.getRowModel().rows" :key="row.id">
          <tr
            class="border-t border-gray-50 bg-gray-50 hover:bg-gray-100"
            :class="{ 'bg-gray-50': row.depth % 2 }"
          >
            <td class="px-4 py-2">{{ row.original.id }}</td>
            <td class="px-4 py-2">{{ row.original.claimId }}</td>
            <td class="px-4 py-2">
              <div
                class="flex items-center"
                :style="{ paddingLeft: `${row.original.depth * 1.5 + 0.5}rem` }"
              >
                <button
                  v-if="hasChildren(row.original)"
                  @click="toggleExpand(row.original)"
                  class="cursor-pointer bg-transparent border-none text-xl font-bold mr-2 w-5 text-center focus:outline-none"
                >
                  {{ row.original.expanded ? "−" : "+" }}
                </button>
                <span v-else class="w-5 inline-block"></span>
                <span :class="{ 'font-semibold': row.original.depth === 0 }">
                  {{ row.original.name }}
                </span>
              </div>
            </td>
            <td
              class="px-4 py-2"
              :class="{ 'font-semibold': row.original.depth === 0 }"
            >
              {{ row.original.submissionDate }}
            </td>
            <td
              class="px-4 py-2"
              :class="{ 'font-semibold': row.original.depth === 0 }"
            >
              {{ row.original.billAmount }}
            </td>
            <td class="px-4 py-2">
              <span
                class="px-2 py-1 rounded-full text-sm font-semibold"
                :class="{
                  'bg-blue-100 text-blue-800':
                    row.original.claimStatus === 'Submitted',
                  'bg-green-100 text-green-800':
                    row.original.claimStatus === 'Approved',
                  'bg-red-100 text-red-800':
                    row.original.claimStatus === 'Canceled',
                  'bg-orange-100 text-orange-800':
                    row.original.claimStatus === 'Initiated',
                }"
              >
                {{ row.original.claimStatus }}
              </span>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, nextTick, computed, watch, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import {
  useVueTable,
  getCoreRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  getExpandedRowModel,
  createColumnHelper,
} from "@tanstack/vue-table";

// Sample data
const useDeppartmentData = () => {
  const data = ref([
    {
      id: 1,
      claimId: "CLM-0001",
      name: "John Doe",
      submissionDate: "2023-04-15",
      billAmount: "₹35000",
      claimStatus: "Approved",
      depth: 0,
      expanded: true,
      subRows: [
        {
          id: 11,
          claimId: "CLM-0011",
          name: "Computer Repair",
          submissionDate: "2023-04-12",
          billAmount: "₹15000",
          claimStatus: "Approved",
          depth: 1,
        },
        {
          id: 12,
          claimId: "CLM-0012",
          name: "Software License",
          submissionDate: "2023-04-10",
          billAmount: "₹20000",
          claimStatus: "Approved",
          depth: 1,
        },
      ],
    },
    {
      id: 2,
      claimId: "CLM-0002",
      name: "Jane Smith",
      submissionDate: "2023-05-20",
      billAmount: "₹78000",
      claimStatus: "Initiated",
      depth: 0,
      expanded: false,
      subRows: [
        {
          id: 21,
          claimId: "CLM-0021",
          name: "Conference Travel",
          submissionDate: "2023-05-18",
          billAmount: "₹45000",
          claimStatus: "Initiated",
          depth: 1,
        },
        {
          id: 22,
          claimId: "CLM-0022",
          name: "Accommodation",
          submissionDate: "2023-05-19",
          billAmount: "₹33000",
          claimStatus: "Initiated",
          depth: 1,
        },
      ],
    },
    {
      id: 3,
      claimId: "CLM-0003",
      name: "Robert Johnson",
      submissionDate: "2023-06-05",
      billAmount: "₹12500",
      claimStatus: "Canceled",
      depth: 0,
      expanded: false,
      subRows: [
        {
          id: 31,
          claimId: "CLM-0031",
          name: "Medical Checkup",
          submissionDate: "2023-06-03",
          billAmount: "₹12500",
          claimStatus: "Canceled",
          depth: 1,
        },
      ],
    },
  ]);

  const toggleExpand = row => {
    row.expanded = !row.expanded;
  };

  const hasChildren = row => {
    return row.subRows && row.subRows.length > 0;
  };

  return { data, toggleExpand, hasChildren };
};

const { data, toggleExpand, hasChildren } = useDeppartmentData();

// TanStack Table setup
const columnHelper = createColumnHelper();

const columns = [
  columnHelper.accessor("id", {
    header: "ID",
    cell: info => info.getValue(),
    enableSorting: false,
  }),
  columnHelper.accessor("claimId", {
    header: "Claim ID",
    cell: info => info.getValue(),
    enableSorting: false,
  }),
  columnHelper.accessor("name", {
    header: "Name",
    cell: info => info.getValue(),
    sortingFn: "alphanumeric",
  }),
  columnHelper.accessor("submissionDate", {
    header: "Submission",
    cell: info => info.getValue(),
    sortingFn: "alphanumeric",
  }),
  columnHelper.accessor("billAmount", {
    header: "Bill Amount",
    cell: info => info.getValue(),
    sortingFn: (rowA, rowB, columnId) => {
      const valueA = parseInt(rowA.original.billAmount.replace(/[^0-9]/g, ""));
      const valueB = parseInt(rowB.original.billAmount.replace(/[^0-9]/g, ""));
      return valueA - valueB;
    },
  }),
  columnHelper.accessor("claimStatus", {
    header: "Status",
    cell: info => info.getValue(),
    sortingFn: "alphanumeric",
  }),
];

// For flattening hierarchical data to work with tanstack/table
const flattenData = data => {
  let result = [];

  for (const row of data) {
    result.push(row);

    if (row.expanded && hasChildren(row)) {
      result = [...result, ...flattenData(row.subRows)];
    }
  }

  return result;
};

// Flat data for table
const flatData = computed(() => flattenData(data.value));

// Filter state
const activeFilter = ref(null);
const activeDropdown = ref(null);
const showSearch = ref(false);
const globalFilter = ref("");
const columnFilters = ref([]);
const statusFilter = ref("");
const budgetRangeFilter = ref("");
const fromDateFilter = ref("");
const toDateFilter = ref("");
const showSortSelector = ref(false);
const sorting = ref([]);

// Table instance
const table = useVueTable({
  get data() {
    return flatData.value;
  },
  columns,
  state: {
    get sorting() {
      return sorting.value;
    },
    get globalFilter() {
      return globalFilter.value;
    },
    get columnFilters() {
      return columnFilters.value;
    },
  },
  _onSortingChange: updater => {
    sorting.value = updater(sorting.value);
  },
  get onSortingChange() {
    return this._onSortingChange;
  },
  set onSortingChange(value) {
    this._onSortingChange = value;
  },
  onGlobalFilterChange: updater => {
    globalFilter.value = updater(globalFilter.value);
  },
  onColumnFiltersChange: updater => {
    columnFilters.value = updater(columnFilters.value);
  },
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getExpandedRowModel: getExpandedRowModel(),
  getRowCanExpand: () => true,
  globalFilterFn: (row, columnId, filterValue) => {
    const value = String(row.getValue(columnId));
    return value.toLowerCase().includes(String(filterValue).toLowerCase());
  },
  filterFns: {
    dateRange: (row, columnId, filterValue) => {
      if (!filterValue.from && !filterValue.to) return true;

      const rowDate = new Date(row.getValue(columnId));
      const fromDate = filterValue.from ? new Date(filterValue.from) : null;
      const toDate = filterValue.to ? new Date(filterValue.to) : null;

      if (fromDate && rowDate < fromDate) return false;
      if (toDate && rowDate > toDate) return false;

      return true;
    },
    billAmountRange: (row, columnId, filterValue) => {
      if (!filterValue) return true;

      const billAmount = parseInt(
        row.getValue(columnId).replace(/[^0-9]/g, ""),
      );

      if (filterValue === "low" && billAmount > 25000) return false;
      if (
        filterValue === "medium" &&
        (billAmount <= 25000 || billAmount > 50000)
      )
        return false;
      if (filterValue === "high" && billAmount <= 50000) return false;

      return true;
    },
    status: (row, columnId, filterValue) => {
      if (!filterValue) return true;
      return row.getValue(columnId).toLowerCase() === filterValue.toLowerCase();
    },
  },
});

// Get sortable columns
const sortableColumns = computed(() => {
  return table.getAllColumns().filter(column => column.getCanSort());
});

// Format sort column name for display
const formatSortColumn = columnId => {
  if (columnId === "submissionDate") return "Submission";
  if (columnId === "billAmount") return "Bill Amount";
  if (columnId === "claimStatus") return "Status";
  return columnId.charAt(0).toUpperCase() + columnId.slice(1);
};

// Toggle search
const toggleSearch = () => {
  showSearch.value = !showSearch.value;
  if (showSearch.value) {
    nextTick(() => {
      document.getElementById("searchInput")?.focus();
    });
  }
};

// Toggle dropdown
const toggleDropdown = dropdown => {
  activeDropdown.value = activeDropdown.value === dropdown ? null : dropdown;
};

// Set status filter
const setStatusFilter = status => {
  statusFilter.value = status;

  if (status) {
    columnFilters.value = [
      ...columnFilters.value.filter(f => f.id !== "claimStatus"),
      { id: "claimStatus", value: status },
    ];
  } else {
    columnFilters.value = columnFilters.value.filter(
      f => f.id !== "claimStatus",
    );
  }
};

// Set budget range filter
const setBudgetRangeFilter = range => {
  budgetRangeFilter.value = range;

  if (range) {
    columnFilters.value = [
      ...columnFilters.value.filter(f => f.id !== "billAmount"),
      { id: "billAmount", value: range },
    ];
  } else {
    columnFilters.value = columnFilters.value.filter(
      f => f.id !== "billAmount",
    );
  }
};

// Set sort column
const setSortColumn = columnId => {
  sorting.value = [{ id: columnId, desc: sorting.value[0]?.desc || false }];
};

// Set sort direction
const setSortDirection = isDesc => {
  if (sorting.value.length > 0) {
    sorting.value = [{ ...sorting.value[0], desc: isDesc }];
  }
};

// Reset sort
const resetSort = () => {
  sorting.value = [];
};

// Toggle column sort
const toggleColumn = columnId => {
  if (sorting.value.length > 0 && sorting.value[0].id === columnId) {
    sorting.value = [{ id: columnId, desc: !sorting.value[0].desc }];
  } else {
    sorting.value = [{ id: columnId, desc: false }];
  }
};

// Get sort indicator
const getSortIndicator = columnId => {
  if (sorting.value.length === 0 || sorting.value[0].id !== columnId) return "";
  return sorting.value[0].desc ? "↓" : "↑";
};

// Watch date filter changes
watch([fromDateFilter, toDateFilter], ([newFrom, newTo]) => {
  if (newFrom || newTo) {
    columnFilters.value = [
      ...columnFilters.value.filter(f => f.id !== "submissionDate"),
      { id: "submissionDate", value: { from: newFrom, to: newTo } },
    ];
  } else {
    columnFilters.value = columnFilters.value.filter(
      f => f.id !== "submissionDate",
    );
  }
});

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return (
    globalFilter.value ||
    columnFilters.value.length > 0 ||
    statusFilter.value ||
    budgetRangeFilter.value ||
    fromDateFilter.value ||
    toDateFilter.value
  );
});

// Reset all filters
const resetAllFilters = () => {
  globalFilter.value = "";
  columnFilters.value = [];
  statusFilter.value = "";
  budgetRangeFilter.value = "";
  fromDateFilter.value = "";
  toDateFilter.value = "";
  activeFilter.value = null;
  activeDropdown.value = null;
};
</script>

<style scoped>
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

/* search expand */
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
