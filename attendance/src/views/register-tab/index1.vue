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
      :excludeFilter="[]"
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
        :data="filteredUsers"
        :customColumnOrder="columnsList"
        @filtersChanged="handleFilterChange"
        :show-open="false"
      >
        <!-- Custom cell for S.No -->
        <template #cell-s_no="{ cell }">
          <div class="text-center">{{ cell.row.index + 1 }}</div>
        </template>

        <!-- Custom cell for Device ID -->
        <template #cell-device_id="{ cell }">
          <div class="ps-4">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Tab ID -->
        <template #cell-device_code="{ cell }">
          <div class="ps-4">{{ cell.value }}</div>
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
    </div>

    <!-- Pagination -->
    <PaginationSelect
      :currentPage="currentPage"
      :totalPages="totalPages"
      :pageSize="pageSize"
      @update:currentPage="handlePageChange"
      @update:pageSize="handlePageSizeChange"
      class="mt-4"
    />

    <!-- Add User Modal -->
    <Modal
      v-model="showAddTabModal"
      :title= "$t('lobbyOffice.add_tab')"
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
// import DataTable from "./DataTable.vue";
// import PaginationSelect from "./PaginationSelect.vue";
// import TableOptions from "./TableOptions.vue";
import DataTable from "../../components/Datatable/DataTable.vue";
import PaginationSelect from "../../components/Datatable/PaginationSelect.vue";
import TableOptions from "../../components/Datatable/TableOptions.vue";

import { Modal } from "@sds/oneui-common-ui";
import AddTab from "./AddTab.vue";
import EditTab from "./EditTab.vue";
import { useI18n } from 'vue-i18n';
import { fetchTabdata, deleteTab, updateTab, createTab} from "@/services/deviceService";

const { t } = useI18n();

// Define columns
// const columnsList = [
//   { key: "s_no", label: "S.No", type: "Number" },
//   { key: "device_id", label: "Device ID", type: "String" },
//   { key: "tab_id", label: "Tab Id", type: "String" },
//   { key: "action", label: "Action", type: "String" },
// ];

// Define columns with localized labels
const columnsList = computed(() => [
  { key: "s_no", label: t('lobbyOffice.s_no'), type: "Number" },
  { key: "device_id", label: t('lobbyOffice.device_id'), type: "String" },
  { key: "device_code", label: t('lobbyOffice.tab_id'), type: "String" },
  { key: "action", label: t('lobbyOffice.action'), type: "String" },
]);
// Initial user data
// const users = ref([
//   { device_id: "8af3c2b1-4d29-45c0-9a1b-7266ea1d3c50", tab_id: "TAB12456" },
//   { device_id: "7af3c2b1-5d29-45c0-9a1b-7266ea1d3c51", tab_id: "TAB12457" },
//   { device_id: "5af3c2b1-3d29-45c0-9a1b-7266ea1d3c53", tab_id: "TAB12458" },
//   { device_id: "6af3c2b1-4d29-45c0-9a1b-7266ea1d3c54", tab_id: "TAB12459" },
//   { device_id: "8af3c2b1-1d29-45c0-9a1b-7266ea1d3c55", tab_id: "TAB12460" },
// ]);
const users = ref([]);

onMounted(async () => {
  try {
    const response = await fetchTabdata();
    if (!response.isError && Array.isArray(response.data)) {
      users.value = response.data.map((item) => ({
        tab_id: item.tab_id,   
        device_id: item.device_id || "N/A",
        device_code: item.device_code || "N/A", 
      }));
      updateTotalPages();
    } else {
      console.error("Failed to fetch tab data:", response.message || "Unknown error");
    }
  } catch (error) {
    console.error("Error fetching tab data:", error.message);
  }
}); 


// Pagination and search/sort state
const currentPage = ref(1);
const pageSize = ref(10);
const totalPages = ref(Math.ceil(users.value.length / pageSize.value));
const searchQuery = ref("");
const sortConfig = ref({ key: "", order: "asc" });

// Computed filtered users based on search
const filteredUsers = computed(() => {
  let result = [...users.value];
  if (searchQuery.value) {
    result = result.filter(user =>
      Object.values(user).some(value =>
        value.toString().toLowerCase().includes(searchQuery.value.toLowerCase())
      )
    );
  }
  // Apply pagination
  return result.slice((currentPage.value - 1) * pageSize.value, currentPage.value * pageSize.value);
});

// Modal states
const showAddTabModal = ref(false);
const showEditTabModal = ref(false);
const selectedUser = ref(null);

// Open Add User Modal
const openAddUserModal = () => {
  showAddTabModal.value = true;
};

// Open Edit User Modal
const openEditUserModal = (row) => {
  selectedUser.value = { ...row }; // Clone the row to avoid direct mutation
  showEditTabModal.value = true;
};

// Add User
const addUser = async (newTab) => {
  try {
    const payload = {
      device_id: newTab.device_id,
      device_code: newTab.tab_id,
    };

    const response = await createTab(payload);

    if (!response.isError && response.success_code === 200) {
      users.value.push({
        device_id: newTab.device_id,
        tab_id: newTab.tab_id,
      });

      Swal.fire({
        title: t('lobbyOffice.success'),
        text: t('lobbyOffice.tab_registered_successfully'),
        icon: "success",
        timer: 1500,
        showConfirmButton: false,
      });

      updateTotalPages();
    } else {
      throw new Error(response.message || "Tab creation failed");
    }
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.error_message'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
};


// Update User
const updateUser = async (updatedTab) => {
  try {
    const payload = {
      device_id: updatedTab.device_id,
      device_code: updatedTab.device_code, 
    };

    const response = await updateTab(updatedTab.tab_id, payload); 

    // console.log("update response_________________________________", payload);

    if (!response.isError && response.success_code === 200) {
      const index = users.value.findIndex(u => u.tab_id === updatedTab.tab_id);
      if (index !== -1) {
        users.value.splice(index, 1, {
          tab_id: updatedTab.tab_id,
          device_id: updatedTab.device_id,
          device_code: updatedTab.device_code,
        });
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
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.error_message'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
};




// Confirm Delete User with SweetAlert2
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
      deleteUser(row); // Proceed with deletion if confirmed
      Swal.fire({
        title: t('lobbyOffice.deleted'),
        text: t('lobbyOffice.delete_success_message'),
        icon: "success",
        timer: 2000, // Auto-close after 2 seconds
        showConfirmButton: false, // Remove the OK button
      });
    }
  });
};


// Delete User
const deleteUser = async (row) => {
  try {
    const response = await deleteTab(row.tab_id); // Call API with tab_id

    if (!response.isError && response.success_code === 200) {
      const index = users.value.findIndex(user => user.device_id === row.device_id);
      if (index !== -1) {
        users.value.splice(index, 1);
        updateTotalPages();
      }
    } else {
      throw new Error(response.message || "Failed to delete tab.");
    }
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.delete_error_message'),
      icon: "error",
      timer: 3000,
      showConfirmButton: false,
    });
  }
};


// Update total pages based on filtered data length
const updateTotalPages = () => {
  totalPages.value = Math.ceil(users.value.length / pageSize.value);
};

// Event handlers
const handlePageChange = (newPage) => { currentPage.value = newPage; };
const handlePageSizeChange = (newPageSize) => {
  pageSize.value = newPageSize;
  currentPage.value = 1;
  updateTotalPages();
};
const onSearch = (query) => { searchQuery.value = query; updateTotalPages(); };
const onSort = (config) => { sortConfig.value = config; };
const onFilter = (filterData) => { console.log("Filtering by:", filterData); };
const onClearSearch = () => { searchQuery.value = ""; updateTotalPages(); };
const onClearSort = () => { sortConfig.value = { key: "", order: "asc" }; };
const onClearFilter = () => { console.log("Clear filter"); };
const handleFilterChange = (filters) => { console.log("Filters changed:", filters); };
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