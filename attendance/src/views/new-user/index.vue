<template>
  <div class="p-4">
    <!-- Table Options -->
    <TableOptions
      :tableTitle="$t('lobbyOffice.user_list')"
      :addButtons="[
        {
          text: $t('lobbyOffice.add_user'),
          bgColor: 'bg-transparent',
          textColor: 'text-gray-700',
          hoverColor: 'hover:bg-gray-100',
          icon: 'heroicons-outline:plus',
        },
      ]"
      :columns="columnsList"
      :data="users"
      :excludeSearch="['action']"
      :excludeSort="['action']"
      :excludeFilter="['action']"
      @addItems="openAddUserModal"
      @search="onSearch"
      @sort="onSort"
      @filter="onFilter"
      @clearSearch="onClearSearch"
      @clearSort="onClearSort"
      @clearFilter="onClearFilter"
    />

    <!-- Main Table -->
    <div class="mt-0 overflow-x-auto">
      <!-- {{ columnsList }} -->
      <DataTable
        :data="sortedAndFilteredUsers"
        :customColumnOrder="columnsList"
        @filtersChanged="handleFilterChange"
        :show-open="false"
      >
        <!-- Custom cell for S.No -->
        <template #cell-s_no="{ cell }">
          <div class="text-center">{{ cell.row.originalIndex + 1 }}</div>
        </template>

        <!-- Custom cell for Employee ID -->
        <template #cell-employee_id="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Designation -->
        <template #cell-designation="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Full Name -->
        <template #cell-full_name="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Official Email ID -->
        <template #cell-official_email_id="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Division/Branch -->
        <template #cell-division_branch="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Mobile Number -->
        <template #cell-mobile_number="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Action -->
        <template #cell-action="{ row }">
          <div class="text-center flex justify-center space-x-2">
            <button
              class="p-1 text-gray-500 hover:text-blue-600 focus:outline-none"
              @click="openEditUserModal(row)"
              :title="$t('lobbyOffice.edit')"
            >
              <Icon icon="heroicons-outline:pencil" class="text-base" />
            </button>
            <button
              class="p-1 text-gray-500 hover:text-red-600 focus:outline-none"
              @click="confirmDeleteUser(row)"
              :title="$t('lobbyOffice.delete')"
            >
              <Icon icon="heroicons-outline:trash" class="text-base" />
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Pagination -->
    <PaginationSelect
      :currentPage="currentPage"
      :totalPages="totalPages"
      :pageSize="pageSize"
      @update:currentPage="handlePageChange"
      @update:pageSize="handlePageSizeChange"
    />

    <!-- Add User Modal -->
    <Modal
      v-model="showAddUserModal"
      :title="$t('lobbyOffice.add_user')"
      size="lg"
    >
      <AddUser v-if="showAddUserModal" v-model="showAddUserModal" @save="addUser" />
    </Modal>

    <!-- Edit User Modal -->
    <Modal
      v-model="showEditUserModal"
      :title="$t('lobbyOffice.edit_user')"
      size="lg"
    >
      <EditUser
        v-model="showEditUserModal"
        :userData="selectedUser"
        @save="updateUser"
      />
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { Icon } from "@iconify/vue";
import Swal from "sweetalert2";
import DataTable from "../../components/Datatable/DataTable.vue";
import PaginationSelect from "../../components/Datatable/PaginationSelect.vue";
import TableOptions from "../../components/Datatable/TableOptions.vue";
import { Modal } from "@sds/oneui-common-ui";
import AddUser from "./AddUser.vue";
import EditUser from "./EditUser.vue";
import { fetchUser, deleteUser as deleteUserApi, updateUser as updateUserApi } from "@/services/lobbyService";
import { useI18n } from 'vue-i18n';

const { t } = useI18n(); // Access translation function

// Define columns with localized labels
const columnsList = computed(() => [
  { key: "s_no", label: t('lobbyOffice.s_no'), type: "Number" },
  { key: "employee_id", label: t('lobbyOffice.employee_id'), type: "label" },
  { key: "designation", label: t('lobbyOffice.designation'), type: "String" },
  { key: "full_name", label: t('lobbyOffice.full_name'), type: "String" },
  { key: "official_email_id", label: t('lobbyOffice.official_email_id'), type: "String" },
  { key: "division_branch", label: t('lobbyOffice.division_branch'), type: "String" },
  { key: "mobile_number", label: t('lobbyOffice.mobile_number'), type: "String" },
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
const pageSize = ref(50); // Default to 50 records per page
const pageSizes = ref([10, 25, 50, 100]); // Available page size options

// Compute total pages based on filtered data
const totalPages = computed(() =>
  Math.ceil(sortedAndFilteredUsers.value.length / pageSize.value)
);

// Compute paginated data for the current page
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return sortedAndFilteredUsers.value.slice(start, end);
});

// Computed property for sorted and filtered users
const sortedAndFilteredUsers = computed(() => {
  let result = [...users.value];

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
      return (
        fieldValue && fieldValue.toString().toLowerCase().includes(searchValue)
      );
    });
  }

  // Apply other filters
  Object.keys(currentFilters.value).forEach((filterKey) => {
    const filterValue = currentFilters.value[filterKey];
    if (Array.isArray(filterValue) && filterValue.length > 0) {
      result = result.filter((user) =>
        filterValue.includes(user[filterKey])
      );
    }
  });

  // Apply sorting
  if (currentSort.value.by) {
    const sortKey = getColumnKeyFromLabel(currentSort.value.by);
    if (sortKey) {
      result.sort((a, b) => {
        let aVal = a[sortKey];
        let bVal = b[sortKey];
        aVal = aVal ?? "";
        bVal = bVal ?? "";
        aVal = aVal.toString().toLowerCase();
        bVal = bVal.toString().toLowerCase();
        return currentSort.value.order === "asc"
          ? aVal.localeCompare(bVal)
          : bVal.localeCompare(aVal);
      });
    }
  }

  return result;
});

// Helper function to get column key from label
const getColumnKeyFromLabel = (label) => {
  const column = columnsList.value.find((col) => col.label === label);
  return column ? column.key : null;
};

// Fetch users on mount
onMounted(async () => {
  const response = await fetchUser();
  if (!response.isError && Array.isArray(response.data)) {
    users.value = response.data.map((item) => ({
      employee_id: item.employee_id || "N/A",
      designation: item.designation || "N/A",
      full_name: item.displayname || item.name || "N/A",
      official_email_id: item.email || "N/A",
      division_branch: item.division || "N/A",
      mobile_number: item.mobile || "N/A",
      core_user_id: item.core_user_id || "N/A",
      action:''
    }));
  } else {
    console.error("Failed to fetch users:", response.message || response.statusText);
  }
});

// Modal states
const showAddUserModal = ref(false);
const showEditUserModal = ref(false);
const selectedUser = ref(null);

// Open Add User Modal
const openAddUserModal = () => {
  showAddUserModal.value = true;
};

// Open Edit User Modal
const openEditUserModal = (row) => {
  selectedUser.value = { ...row };
  showEditUserModal.value = true;
};

// Add User
const addUser = (newUser) => {
  users.value.push(newUser);
  currentPage.value = 1; // Reset to first page
};

// Update User
const updateUser = async (updatedUser) => {
  try {
    const core_user_id = updatedUser.core_user_id || selectedUser.value?.core_user_id;
    if (!core_user_id) {
      throw new Error(t('lobbyOffice.user_update_error_message'));
    }

    const payload = {
      employee_id: updatedUser.employee_id,
      full_name: updatedUser.full_name,
      email: updatedUser.official_email_id,
      mobile: parseInt(updatedUser.mobile_number),
      designation: parseInt(updatedUser.designation),
      division: parseInt(updatedUser.division_branch),
      ou_id: 61,
    };

    const response = await updateUserApi(core_user_id, payload);

    if (!response.isError && response.success_code === 200) {
      const index = users.value.findIndex(u => u.core_user_id === core_user_id);
      if (index !== -1) {
        users.value.splice(index, 1, {
          ...updatedUser,
          core_user_id,
        });
      }

      Swal.fire({
        title: t('lobbyOffice.success'),
        text: response.message || t('lobbyOffice.user_update_success_message'),
        icon: "success",
        timer: 1000,
        showConfirmButton: false,
      });
    } else {
      throw new Error(response.message || t('lobbyOffice.user_update_error_message'));
    }
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.user_update_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
  }
};

// Confirm Delete User
const confirmDeleteUser = (row) => {
  Swal.fire({
    title: t('lobbyOffice.confirm_delete'),
    text: t('lobbyOffice.confirm_delete_user_message', { employee_id: row.employee_id }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: t('lobbyOffice.delete'),
    cancelButtonText: t('lobbyOffice.cancel'),
    reverseButtons: true,
    customClass: {
      confirmButton: "bg-red-600 text-white rounded-full hover:bg-red-700",
      cancelButton: "bg-blue-600 text-white rounded-full hover:bg-blue-700",
    },
  }).then((result) => {
    if (result.isConfirmed) {
      deleteUser(row);
      Swal.fire({
        title: t('lobbyOffice.deleted'),
        text: t('lobbyOffice.delete_user_success_message'),
        icon: "success",
        timer: 1000,
        showConfirmButton: false,
      });
    }
  });
};

// Delete User
const deleteUser = async (row) => {
  try {
    const response = await deleteUserApi(row.core_user_id);
    if (!response.isError && response.success_code === 200) {
      const index = users.value.findIndex(user => user.core_user_id === row.core_user_id);
      if (index !== -1) {
        users.value.splice(index, 1);
        if (paginatedUsers.value.length === 0 && currentPage.value > 1) {
          currentPage.value--;
        }
      }
    } else {
      throw new Error(response.message || t('lobbyOffice.delete_user_error_message'));
    }
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.delete_user_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
  }
};

// Pagination event handlers
const handlePageChange = (newPage) => {
  currentPage.value = newPage;
};

const handlePageSizeChange = (newPageSize) => {
  pageSize.value = newPageSize;
  currentPage.value = 1; // Reset to first page
};

// Table event handlers
const onSearch = (searchData) => {
  currentSearch.value = searchData;
  currentPage.value = 1; // Reset to first page on search
};

const onSort = (sortData) => {
  currentSort.value = { by: sortData.key, order: sortData.order };
  currentPage.value = 1; // Reset to first page on sort
};

const onFilter = (filterData) => {
  currentFilters.value = filterData;
  currentPage.value = 1; // Reset to first page on filter
};

const onClearSearch = () => {
  currentSearch.value = { key: "", value: "" };
  currentPage.value = 1;
};

const onClearSort = () => {
  currentSort.value = { by: "", order: "asc" };
  currentPage.value = 1;
};

const onClearFilter = () => {
  currentFilters.value = {};
  currentPage.value = 1;
};

const handleFilterChange = (filters) => {
  if (filters.sort) {
    currentSort.value = filters.sort;
    currentPage.value = 1;
  }
};
</script>

<style scoped>
/* Match the table header color and style from the screenshot */
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

/* Style the action buttons */
button {
  background: none;
  border: none;
  cursor: pointer;
  transition: color 0.2s;
}

button:hover {
  color: inherit; /* Inherits the hover color from text classes */
}

/* Ensure the table is responsive */
.table-container {
  width: 100%;
  overflow-x: auto;
}

/* Fix icon visibility */
.icon {
  display: inline-block;
  vertical-align: middle;
}
</style>