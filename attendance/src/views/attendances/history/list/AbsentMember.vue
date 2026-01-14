<template>
  <div class="min-h-screen">
    <div class="flex justify-between gap-2">
          <h2 class="w-[20%] text-lg font-bold text-gray-800 pb-2">Long Absentee Report</h2>
          <div class="w-[80%] flex justify-end gap-2">
            <Tabs />
          </div>
        </div> 
    <div class=" mx-auto p-0">
      <select 
          v-model="selectedDays"
          class="px-2 py-2 mb-2 text-sm cursor-pointer">
          <option v-for="item in [60,50,40,30,20,10]" :value="item">
            Absent since {{ item }} Days
          </option>
      </select>
      <!-- Table Options -->
      <!-- <TableOptions :tableTitle="t('menu.tab_1_11')" :columns="columns" :data="allData"
        :excludeSearch="['id', 'division_no', 'attendance_date', 'action']" :excludeSort="['id', 'action']"
        :excludeFilter="['id', 'division_no', 'attendance_date', 'action']" @search="handleSearch" @sort="handleSort"
        @filter="handleFilter" @clearSearch="handleClearSearch" @clearSort="handleClearSort"
        @clearFilter="handleClearFilter" /> -->

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
          <div class="flex items-center gap-1">
            <img v-if="!!cell.row.profile_photo" :src="cell.row.profile_photo" class="w-6 h-6 rounded-full" />
            <span>{{ cell.value }}</span>
          </div>
        </template>
       
        <template #cell-party_name="{ cell }">
          <div class="text-left px-4">
            <div class="text-gray-600 text-sm">{{ cell.value }}</div>
          </div>
        </template>
       
        <template #cell-state="{ cell }">
          <div class="text-left px-4">
            <div class="text-gray-600 text-sm">{{ cell.value }}</div>
          </div>
        </template>
       

        <template #cell-term_start_date="{ cell }">
          <div class="flex text-center gap-1">
            {{ cell.value }} to {{ cell.row.term_end_date }}
          </div>
        </template>

      </DataTable>
      <div v-if="paginatedData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span v-if="loadingApiData" class="text-gray-500 text-lg font-medium">Loading Data... Please wait</span>
        <!-- <span v-else>Data not found</span> -->
        <div v-else class="flex items-center justify-center py-12">
          <div class="text-center">
            <div class="text-gray-400 mb-2">
              <Icon icon="solar:file-text-broken" width="48" height="48" class="mx-auto" />
            </div>
            <p class="text-gray-500 text-lg font-medium">No Record Found</p>
            <p class="text-gray-400 text-sm mt-1">No members found for the selected criteria</p>
          </div>
        </div>
       </div>
      <!-- Pagination -->
      <!-- <div class="mt-0">
        <PaginationSelect :currentPage="totalPages > 0 ? currentPage : 0" :totalPages="totalPages" :pageSize="pageSize"
          @update:currentPage="currentPage = $event" @update:pageSize="handlePageSizeChange" />
      </div> -->
    </div>
  </div>

</template>

<script setup>
import { ref, computed, watch, createApp, h } from 'vue'
import DataTable from "@/components/Datatable/DataTable.vue"
import TableOptions from "@/components/Datatable/TableOptions.vue"
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue'
import Swal from 'sweetalert2'
import { memberAbsentlist } from '@/services/attendanceService'
import { Modal, Button, Badge } from '@sds/oneui-common-ui';
import { useI18n } from "vue-i18n";
import useLocalDate from '@/composables/useLocalDate'
import Tabs from './Tabs.vue'
import { Icon } from '@iconify/vue'
const { t, locale } = useI18n();
// Reactive state
const loadingApiData = ref(false)
const allData = ref([])
const filteredData = ref([])
const currentPage = ref(1)
const pageSize = ref(50)
const selectedDays = ref(60)

// Column configuration that matches your DataTable component structure
const columns = ref([
  { key: 'division_no', label: 'Division No' },
  { key: 'name', label: 'User Name' },
  { key: 'party_name', label: 'Party Name' },
  { key: 'state', label: 'State' },
  { key: 'term_start_date', label: 'Terms' }
])

// Computed properties
const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredData.value.slice(start, start + pageSize.value)
})

const totalPages = computed(() => Math.ceil(filteredData.value.length / pageSize.value))

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

const fetchAbsenteeList = async () => {
  try {
    const options = {
      params: {
        days_count: selectedDays.value,
        pagelimit: 50
      }
    }
    loadingApiData.value = true;
    const res = await memberAbsentlist(options);
    loadingApiData.value = false;
    filteredData.value = res.data ? res.data : [];
  } catch (err) {
    console.error('Failed loading data:', err)
  }

}

watch(() => selectedDays.value, async (param) => {
  selectedDays.value = param
  await fetchAbsenteeList();
}, { immediate: true }); 

</script>
