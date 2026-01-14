<template>
  <div class="p-4">
    <!-- Table Options -->
    <TableOptions
      :tableTitle="$t('lobbyOffice.device_management')"
      :addButtons="[
        {
          text: $t('lobbyOffice.register_tab'),
          bgColor: 'bg-transparent',
          textColor: 'text-gray-700',
          hoverColor: 'hover:bg-gray-100',
          icon: 'heroicons-outline:plus',
        },
      ]"
      :columns="columnsList"
      :data="users"
      :excludeSearch="[]"
      :excludeSort="[]"
      :excludeFilter="['s_no']"
      @addItems="openAddUserModal"
      @search="onSearch"
      @sort="onSort"
      @filter="onFilter"
      @clearSearch="onClearSearch"
      @clearSort="onClearSort"
      @clearFilter="onClearFilter"
    />

    <!-- Main Table -->
    <div class="mt-4 overflow-x-auto">
      <DataTable
        :data="paginatedUsers"
        :customColumnOrder="columnsList"
        @filtersChanged="handleFilterChange"
        :show-open="false"
      >
        <!-- Custom cell for S.No -->
        <template #cell-s_no="{ cell }">
          <div class="text-center">{{ cell.row.originalIndex + 1 }}</div>
        </template>

        <!-- Custom cell for Device ID -->
        <template #cell-device_id="{ cell }">
          <div class="ps-4">{{ cell.value || 'N/A' }}</div>
        </template>

        <!-- Custom cell for Tab ID -->
        <template #cell-device_code="{ cell }">
          <div class="ps-4">{{ cell.value || 'N/A' }}</div>
        </template>

        <!-- Custom cell for Action -->
        <template #cell-after="{ row }">
          <div class="text-right flex space-x-2 ps-4">
            <button
              class="p-1 text-blue-500 hover:text-blue-700 focus:outline-none"
              @click="openEditUserModal(row)"
            >
              <Icon icon="heroicons-outline:pencil" class="text-base" />
            </button>
            <button
              class="p-1 text-red-500 hover:text-red-700 focus:outline-none"
              @click="confirmDeleteUser(row)"
            >
              <Icon icon="heroicons-outline:trash" class="text-base" />
            </button>
          </div>
        </template>
      </DataTable>
      <!-- Empty State -->
      <div v-if="paginatedUsers.length === 0" class="text-center py-4 text-gray-500">
        No data available. Try clearing filters or checking the data source.
      </div>
    </div>

    <!-- Pagination -->
    <PaginationSelect
      :currentPage="currentPage"
      :totalPages="totalPages"
      :pageSize="pageSize"
      :pageSizes="pageSizes"
      @update:currentPage="handlePageChange"
      @update:pageSize="handlePageSizeChange"
      class="mt-4"
    />

    <!-- Add User Modal -->
    <Modal
      v-model="showAddTabModal"
      :title="$t('lobbyOffice.add_tab')"
      size="lg"
    >
      <AddTab
        v-model="showAddTabModal"
        @save="addUser"
      />
    </Modal>

    <!-- Edit User Modal -->
    <Modal
      v-model="showEditTabModal"
      :title="$t('lobbyOffice.edit_tab')"
      size="lg"
    >
      <EditTab
        v-model="showEditTabModal"
        :userData="selectedUser"
        @save="updateUser"
      />
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import Swal from "sweetalert2";
import DataTable from "../../components/Datatable/DataTable.vue";
import PaginationSelect from "../../components/Datatable/PaginationSelect.vue";
import TableOptions from "../../components/Datatable/TableOptions.vue";
import { Modal } from "@sds/oneui-common-ui";
import AddTab from "./AddTab.vue";
import EditTab from "./EditTab.vue";
import { useI18n } from 'vue-i18n';
import { fetchTabdata, deleteTab, updateTab, createTab } from "@/services/deviceService";

const { t } = useI18n();

// Define columns with localized labels
const columnsList = computed(() => [
  { key: "s_no", label: t('lobbyOffice.s_no'), type: "Number" },
  { key: "device_id", label: t('lobbyOffice.device_id'), type: "String" },
  { key: "device_code", label: t('lobbyOffice.tab_id'), type: "String" },
  { key: "action", label: t('lobbyOffice.action'), type: "String" },
]);

// Initial user data
const users = ref([]);

// Sorting and filtering state
const currentSort = ref({ by: "", order: "asc" });
const currentSearch = ref({ key: "", value: "" });
const currentFilters = ref({});

// Pagination state
const currentPage = ref(1);
const pageSize = ref(10);
const pageSizes = ref([10, 25, 50, 100]);

// Compute total pages based on filtered data
const totalPages = computed(() => {
  const total = Math.ceil(sortedAndFilteredUsers.value.length / pageSize.value);
  console.log("Total pages:", total, "Filtered users length:", sortedAndFilteredUsers.value.length); // Debug total pages
  return total || 1; // Ensure at least 1 page
});

// Compute paginated data for the current page
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  const paginated = sortedAndFilteredUsers.value.slice(start, end);
  console.log("Paginated users:", paginated); // Debug paginated data
  return paginated;
});

// Computed property for sorted and filtered users
const sortedAndFilteredUsers = computed(() => {
  let result = [...users.value];
  console.log("Input users:", result); // Debug input data

  // Add original index for S.No display
  result = result.map((user, index) => ({
    ...user,
    originalIndex: index,
  }));

  // Apply search filter
  if (currentSearch.value.key && currentSearch.value.value) {
    const searchKey = currentSearch.value.key;
    const searchValue = currentSearch.value.value.toLowerCase();
    result = result.filter((user) => {
      const fieldValue = user[searchKey];
      return fieldValue && fieldValue.toString().toLowerCase().includes(searchValue);
    });
    console.log("After search filter:", result, "Search key:", searchKey, "Search value:", searchValue); // Debug search
  }

  // Apply other filters
  let hasValidFilters = false;
  Object.keys(currentFilters.value).forEach((filterKey) => {
    const filterValue = currentFilters.value[filterKey];
    if (Array.isArray(filterValue) && filterValue.length > 0) {
      hasValidFilters = true;
      result = result.filter((user) => {
        const userValue = user[filterKey];
        return userValue && filterValue.includes(userValue.toString());
      });
    }
  });
  console.log("After additional filters:", result, "Filters:", currentFilters.value); // Debug filters

  // Fallback to unfiltered data if filters result in empty array
  if (hasValidFilters && result.length === 0) {
    console.warn("Filters resulted in empty data, returning unfiltered data");
    result = users.value.map((user, index) => ({
      ...user,
      originalIndex: index,
    }));
  }

  // Apply sorting
  if (currentSort.value.by) {
    const sortKey = getColumnKeyFromLabel(currentSort.value.by);
    if (sortKey) {
      result.sort((a, b) => {
        let aVal = a[sortKey] ?? "";
        let bVal = b[sortKey] ?? "";
        aVal = aVal.toString().toLowerCase();
        bVal = bVal.toString().toLowerCase();
        return currentSort.value.order === "asc"
          ? aVal.localeCompare(bVal)
          : bVal.localeCompare(aVal);
      });
    }
    console.log("After sorting:", result, "Sort key:", sortKey, "Sort order:", currentSort.value.order); // Debug sorting
  }

  return result;
});

// Helper function to get column key from label
const getColumnKeyFromLabel = (label) => {
  const column = columnsList.value.find((col) => col.label === label);
  const key = column ? column.key : null;
  console.log("getColumnKeyFromLabel:", label, "->", key); // Debug column key mapping
  return key;
};

// Fetch tab data on mount
onMounted(async () => {
  try {
    const response = await fetchTabdata();
    console.log("API response:", response); // Debug API response
    if (!response.isError && Array.isArray(response.data)) {
      users.value = response.data.map((item) => {
        const mapped = {
          tab_id: item.tab_id || "N/A",
          device_id: item.device_id || "N/A",
          device_code: item.device_code || "N/A",
        };
        console.log("Mapped item:", mapped); // Debug mapped data
        return mapped;
      });
      console.log("Fetched users:", users.value); // Debug final users array
    } else {
      console.error("Failed to fetch tab data:", response.message || "Unknown error");
      Swal.fire({
        title: t('lobbyOffice.error'),
        text: t('lobbyOffice.error_fetching_data'),
        icon: "error",
        timer: 3000,
        showConfirmButton: false,
      });
    }
  } catch (error) {
    console.error("Error fetching tab data:", error.message);
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: t('lobbyOffice.error_fetching_data'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
});

// Modal states
const showAddTabModal = ref(false);
const showEditTabModal = ref(false);
const selectedUser = ref(null);

// Open Add Tab Modal
const openAddUserModal = () => {
  showAddTabModal.value = true;
};

// Open Edit Tab Modal
const openEditUserModal = (row) => {
  selectedUser.value = { ...row };
  showEditTabModal.value = true;
};

// Add Tab
const addUser = async (newTab) => {
  try {
    const payload = {
      device_id: newTab.device_id,
      device_code: newTab.tab_id,
    };
    console.log("Adding tab:", payload); // Debug add payload

    const response = await createTab(payload);
    console.log("Add tab response:", response); // Debug response

    if (!response.isError && response.success_code === 200) {
      users.value.push({
        tab_id: newTab.tab_id,
        device_id: newTab.device_id,
        device_code: newTab.device_code,
      });
      console.log("Users after add:", users.value); // Debug users after add

      Swal.fire({
        title: t('lobbyOffice.success'),
        text: t('lobbyOffice.tab_registered_successfully'),
        icon: "success",
        timer: 1500,
        showConfirmButton: false,
      });
    } else {
      throw new Error(response.message || "Tab creation failed");
    }
  } catch (error) {
    console.error("Error adding tab:", error.message);
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.error_message'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
};

// Update Tab
const updateUser = async (updatedTab) => {
  try {
    const payload = {
      device_id: updatedTab.device_id,
      device_code: updatedTab.device_code,
    };
    console.log("Updating tab:", updatedTab.tab_id, payload); // Debug update payload

    const response = await updateTab(updatedTab.tab_id, payload);
    console.log("Update tab response:", response); // Debug response

    if (!response.isError && response.success_code === 200) {
      const index = users.value.findIndex(u => u.tab_id === updatedTab.tab_id);
      if (index !== -1) {
        users.value.splice(index, 1, {
          tab_id: updatedTab.tab_id,
          device_id: updatedTab.device_id,
          device_code: updatedTab.device_code,
        });
        console.log("Users after update:", users.value); // Debug users after update
      }

      Swal.fire({
        title: t('lobbyOffice.success'),
        text: t('lobbyOffice.update_success_message'),
        icon: "success",
        timer: 1500,
        showConfirmButton: false,
      });
    } else {
      throw new Error(response.message || "Update failed");
    }
  } catch (error) {
    console.error("Error updating tab:", error.message);
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.error_message'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
};

// Confirm Delete Tab
const confirmDeleteUser = (row) => {
  Swal.fire({
    title: t('lobbyOffice.confirm_delete'),
    text: t('lobbyOffice.confirm_delete_message', { device_id: row.device_id }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: t('lobbyOffice.delete'),
    cancelButtonText: t('lobbyOffice.cancel'),
    reverseButtons: true,
    customClass: {
      confirmButton: "bg-red-500 rounded-full text-white hover:bg-red-700",
      cancelButton: "bg-blue-500 rounded-full text-white hover:bg-blue-700",
    },
  }).then((result) => {
    if (result.isConfirmed) {
      deleteUser(row);
      Swal.fire({
        title: t('lobbyOffice.deleted'),
        text: t('lobbyOffice.delete_success_message'),
        icon: "success",
        timer: 2000,
        showConfirmButton: false,
      });
    }
  });
};

// Delete Tab
const deleteUser = async (row) => {
  try {
    console.log("Deleting tab:", row.tab_id); // Debug delete
    const response = await deleteTab(row.tab_id);
    console.log("Delete tab response:", response); // Debug response

    if (!response.isError && response.success_code === 200) {
      const index = users.value.findIndex(user => user.tab_id === row.tab_id);
      if (index !== -1) {
        users.value.splice(index, 1);
        console.log("Users after delete:", users.value); // Debug users after delete
        if (paginatedUsers.value.length === 0 && currentPage.value > 1) {
          currentPage.value--;
        }
      }
    } else {
      throw new Error(response.message || "Failed to delete tab.");
    }
  } catch (error) {
    console.error("Error deleting tab:", error.message);
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.delete_error_message'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
};

// Event handlers
const handlePageChange = (newPage) => {
  currentPage.value = newPage;
  console.log("Page changed to:", newPage); // Debug page change
};

const handlePageSizeChange = (newPageSize) => {
  pageSize.value = newPageSize;
  currentPage.value = 1;
  console.log("Page size changed to:", newPageSize); // Debug page size
};

const onSearch = (searchData) => {
  currentSearch.value = searchData;
  currentPage.value = 1;
  console.log("Search applied:", searchData); // Debug search
};

const onSort = (sortData) => {
  currentSort.value = { by: sortData.key, order: sortData.order };
  currentPage.value = 1;
  console.log("Sort applied:", currentSort.value); // Debug sort
};

const onFilter = (filterData) => {
  currentFilters.value = filterData;
  currentPage.value = 1;
  console.log("Filters applied:", filterData); // Debug filters
};

const onClearSearch = () => {
  currentSearch.value = { key: "", value: "" };
  currentPage.value = 1;
  console.log("Search cleared"); // Debug clear search
};

const onClearSort = () => {
  currentSort.value = { by: "", order: "asc" };
  currentPage.value = 1;
  console.log("Sort cleared"); // Debug clear sort
};

const onClearFilter = () => {
  currentFilters.value = {};
  currentPage.value = 1;
  console.log("Filters cleared"); // Debug clear filters
};

const handleFilterChange = (filters) => {
  if (filters.sort) {
    currentSort.value = filters.sort;
    currentPage.value = 1;
    console.log("Filter change (sort):", filters.sort); // Debug filter change
  }
};
</script>

<style scoped>
th {
  background-color: #dfebfd;
  font-weight: 600;
  text-align: center;
  padding: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

td {
  text-align: center;
  padding: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}

tr:hover {
  background-color: #f9fafb;
}

button {
  background: none;
  border: none;
  cursor: personally;
  transition: color 0.2s;
}

button:hover {
  color: inherit;
}

.table-container {
  width: 100%;
  overflow-x: auto;
}

.icon {
  display: inline-block;
  vertical-align: middle;
}
</style>