<template>
  <div class="min-h-screen  ">
    <div class=" mx-auto p-0">
      <!-- Table Options -->
      <TableOptions :tableTitle="t('menu.tab_1_8')" :columns="columns" :data="allData"
        :excludeSearch="['id', 'division_no', 'attendance_date', 'action']" :excludeSort="['id', 'action']"
        :excludeFilter="['id', 'division_no', 'attendance_date', 'action']" @search="handleSearch" @sort="handleSort"
        @filter="handleFilter" @clearSearch="handleClearSearch" @clearSort="handleClearSort"
        @clearFilter="handleClearFilter" />

      <!-- Data Table -->
      <DataTable :data="paginatedData" :customColumnOrder="columns" :showOpen="false"
        @filtersChanged="handleTableFiltersChanged">
        <!-- Division Number Cell -->
        <template #cell-division_no="{ cell }">
          <div class="ms-6 font-medium text-gray-900">
            {{ cell.value }}
          </div>
        </template>

        <!-- Member Name Cell -->
        <template #cell-name="{ cell }">
          <div class="text-left px-4">
            <div class="text-gray-600 text-sm">{{ cell.value }}</div>
          </div>
        </template>
       
        <template #cell-party_name="{ cell }">
          <div class="text-left px-4">
            <div class="text-gray-600 text-sm">{{ cell.value }}</div>
          </div>
        </template>
       
        <template #cell-attendance_date="{ cell }">
          <div class="text-left px-4">
            <div class="text-gray-600 text-sm">{{ cell.value }}</div>
          </div>
        </template>
       

        <template #cell-action="{ cell }">
          <div class="flex text-center gap-1">
            <Button label="Reset" title="Click to reset sign" size="xs" color="green-outline" @click="() => handleResetConfirm(cell.row.id)"></Button>
            <Button v-if="Array.isArray(cell.row.update_logs) && cell.row.update_logs.length" label="Logs" title="Click to view logs" size="xs" color="gray-outline" @click="() => viewLogs(cell.row)"></Button>
          </div>
        </template>

      </DataTable>
      <div v-if="paginatedData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span v-if="loadingApiData">Loading Data... Please wait</span>
        <span v-else>Data not found</span>
       </div>
      <!-- Pagination -->
      <div class="mt-0">
        <PaginationSelect :currentPage="totalPages > 0 ? currentPage : 0" :totalPages="totalPages" :pageSize="pageSize"
          @update:currentPage="currentPage = $event" @update:pageSize="handlePageSizeChange" />
      </div>
    </div>
  </div>
<div>
 <Modal
      :modelValue="viewLogModal"
      :disableBackdrop="true"
      :title="'View logs of attendance - ' + selectLogs.name"
      size="lg"
      @close="handleCloseViewLogModal"
    >
      <div class="bg-white rounded shadow-sm w-full">        

        <table class="w-full border border-gray-300 text-sm text-left">
          <thead class="bg-red-50">
            <tr>
              <th class="border border-gray-300 px-4 py-2">Signature</th>
              <th class="border border-gray-300 px-4 py-2">Date and Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in selectLogs.update_logs">
              <td class="border border-gray-300 px-4 py-2">
                <img :src="item.signature_url" class="w-20" />
              </td>
              <td class="border border-gray-300 px-4 py-2">{{ useLocalDate(item.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Modal>
</div>

</template>

<script setup>
import { ref, computed, watch, createApp, h } from 'vue'
import DataTable from "@/components/Datatable/DataTable.vue"
import TableOptions from "@/components/Datatable/TableOptions.vue"
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue'
import Swal from 'sweetalert2'
import { fetchStylusSign, resetAttendance } from '@/services/attendanceService'
import { Modal, Button } from '@sds/oneui-common-ui';
import { useI18n } from "vue-i18n";
import useLocalDate from '@/composables/useLocalDate'
const { t, locale } = useI18n();
// Reactive state
const loadingApiData = ref(false)
const allData = ref([])
const filteredData = ref([])
const currentPage = ref(1)
const pageSize = ref(10)
const selectedDate = ref(new Date().toISOString().split('T')[0])

const viewLogModal = ref(false);
const selectLogs = ref([]);


// Column configuration that matches your DataTable component structure
const columns = ref([
  { key: 'division_no', label: 'क्रम संख्या\nDivision No.' },
  { key: 'name', label: 'सदस्य का नाम\nName Of Member' },
  { key: 'party_name', label: 'पार्टी का नाम\nParty Name' },
  { key: 'attendance_date', label: 'उपस्थिति तिथि\nAttendance Date' },
  { key: 'action', label: 'कार्रवाई\nAction' },
])

// Computed properties
const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredData.value.slice(start, start + pageSize.value)
})

const totalPages = computed(() => Math.ceil(filteredData.value.length / pageSize.value))

const handleResetConfirm = async (id) => {
  let mountedApp = null;
  let action = null; // will be 'confirm' | 'cancel' | null
  await Swal.fire({
    title: 'Are you sure?',
    text: "Are you want to proceed this action!",
    icon: 'warning',
    html: `<div id="vue-buttons" class="flex justify-center gap-3 mt-4"></div>`,
    showConfirmButton: false,
    showCancelButton: false,
    buttonsStyling: false,
    didOpen: (popup) => {
      const container = popup.querySelector('#vue-buttons');
      if (!container) return;
      // create a small Vue app that uses your Button component      
      mountedApp = createApp({
        methods: {
          onConfirm() {
            action = 'confirm';
            Swal.close();
          },
          onCancel() {
            action = 'cancel';
            Swal.close();
          },
        },
        render () {
          return h('div', {class: 'flex gap-3 justify-center'}, [
            h(Button, {
              label: 'Yes, confirm it!',
              size: 'sm',
              color: 'green-outline',
              onClick: this.onConfirm
            }),
            h(Button, {
              label: 'Cancel',
              size: 'sm',
              color: 'red-outline',
              onClick: this.onCancel
            })
          ])
        }
      });
      // mount into the swal container
      mountedApp.mount(container);
    },
    // ensure we always unmount the tiny app when the swal closes
    didClose: () => {
      if (mountedApp) {
        mountedApp.unmount();
        mountedApp = null;
      }
    },
  });
  if ( action != 'confirm' ) {
    return false;
  }
  
  const response = await resetAttendance(id);
  if ( response?.isError == false && response?.success_code == 200 ) {
    fetchStylusSignList();
  }
  Swal.fire({
    toast: true,
    position: 'top-end',
    timer: 5000,
    timerProgressBar: true,
    icon: response?.isError == false && response?.success_code == 200 ? 'success' : 'error',
    title: response?.isError == false && response?.success_code == 200 ? "The signed attendance was cleared successfully.": "Something went wrong, try later!",
    showConfirmButton: false,
  })
}

const viewLogs = (data) => {
  viewLogModal.value = true;
  selectLogs.value = data;
}

const handleCloseViewLogModal = () => {
  viewLogModal.value = false;
  selectLogs.value = [];
}

// Event handlers for TableOptions component
const handleSearch = ({ key, value }) => {
  if (!value || value.trim() === '') {
    filteredData.value = allData.value
    currentPage.value = 1
    return
  }

  const searchValue = value.toLowerCase()
  filteredData.value = allData.value.filter(item => {
    const fieldValue = item[key]?.toString().toLowerCase() || ''
    // Also search in Hindi names if searching memberName
    if (key === 'memberName') {
      const hindiName = item.memberNameHindi?.toString().toLowerCase() || ''
      return fieldValue.includes(searchValue) || hindiName.includes(searchValue)
    }
    return fieldValue.includes(searchValue)
  })
  currentPage.value = 1
}

const handleSort = ({ key, order }) => {
  filteredData.value = [...filteredData.value].sort((a, b) => {
    let aVal = a[key]
    let bVal = b[key]

    // Handle numeric sorting for divisionNo
    if (key === 'divisionNo') {
      aVal = Number(aVal) || 0
      bVal = Number(bVal) || 0
    }

    if (order === 'asc') {
      return aVal > bVal ? 1 : -1
    } else {
      return aVal < bVal ? 1 : -1
    }
  })
}

const handleFilter = (filters) => {
  let filtered = [...allData.value]

  Object.entries(filters).forEach(([key, filterValue]) => {
    if (Array.isArray(filterValue) && filterValue.length > 0) {
      filtered = filtered.filter(item => {
        const itemValue = item[key]?.toString() || ''
        return filterValue.some(fv => itemValue.includes(fv))
      })
    }
  })

  filteredData.value = filtered
  currentPage.value = 1
}

const handleClearSearch = () => {
  filteredData.value = allData.value
  currentPage.value = 1
}

const handleClearSort = () => {
  // Reset to original order
  filteredData.value = [...allData.value]
  currentPage.value = 1
}

const handleClearFilter = () => {
  filteredData.value = allData.value
  currentPage.value = 1
}

// Handle DataTable internal filter changes (from your existing component)
const handleTableFiltersChanged = (filters) => {
  // Handle any internal table filtering from your DataTable component
}

// Pagination handlers
const handlePageSizeChange = (newSize) => {
  pageSize.value = newSize
  currentPage.value = 1
}

const fetchStylusSignList = async () => {
  try {
    const options = {
      params: {
        date: selectedDate.value,
        pagelimit: 10000
      }
    }
    loadingApiData.value = true;
    const res = await fetchStylusSign(options);
    loadingApiData.value = false;
    const apiData = res.data ? res.data : [];
    
     
    allData.value = apiData.map(item => ({
      division_no: item.division_no,
      id: item.id,
      name: item.name,
      attendance_date: item.attendance_date,
      party_name: item.party_name,
      profile_photo: item.profile_photo,
      update_logs: item.update_logs,
      action:''
    }))

    filteredData.value = [...allData.value]
  } catch (err) {
    console.error('Failed loading data:', err)
  }

}

// watch for changes on selectedDate prop
watch(() => selectedDate.value, async (newDate) => {
  selectedDate.value = newDate
  await fetchStylusSignList();
}, { immediate: true }); 

</script>

<style scoped>
/*  table headers display properly with line breaks */
:deep(.min-w-full th span) {
  white-space: pre-line;
  line-height: 1.3;
}

/* Custom styling for Parliament header */
h1,
h2,
h3 {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
</style>