<template>
     
  <div class="min-h-screen  ">
    <!-- Parliament Header -->
   <!-- <Loading v-if="isPdfLoading" /> -->

    <div class=" mx-auto p-0">
      <!-- Table Options -->
      <TableOptions :tableTitle="'Manual Signed'" :columns="columns" :data="allData"
        :excludeSearch="['id', 'division_no', 'action']" :excludeSort="['id', 'action']"
        :excludeFilter="['id', 'division_no', 'action']" @search="handleSearch" @sort="handleSort"
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
          <Button label="Approval" title="Click to Approve" size="xs" color="green-outline" @click="() => handleOpenPaperSignModal(cell.row)" />
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

</div>

  <Modal
      :modelValue="openPaperSignedModal"
      :disableBackdrop="true"
      title="Manual Signature Request Approval"
      size="lg"
      @close="handleClosePaperSignModal"
    >
      <form @submit.prevent="handleSubmitPaperSignModal">
      <div class="bg-white rounded shadow-sm w-full max-w-max">        
        <div class="pb-2">Remark: </div>
        <div class="border border-gray-300 shadow-sm">
          <QuillEditorWrapper
          v-model:content="editorContent"
          editorHeight="170px"
          contentType="html"
          ref="editorRef"
          theme="snow" />
        </div>
        <div class="flex justify-center space-x-2 px-4 py-4"
        :class="{
          'text-red-500 ': errorForm.error == 'error', 
          'text-green-500 ': errorForm.error == 'success', 
        }"
        >
          <div v-html="errorForm.message"></div>
        </div>
        <div class="flex items-center justify-center space-x-2 px-4 py-4">
          <Button type="submit" label="Submit" size="xs" color="green-outline" @click="() => handleSubmitPaperSignModal()" />
          <Button type="reset" label="Clear" size="xs" color="red-outline" @click="() => handleResetPaperSignModal()" />
        </div>
      </div>
      </form>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import DataTable from "@/components/Datatable/DataTable.vue"
import TableOptions from "@/components/Datatable/TableOptions.vue"
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue'
import QuillEditorWrapper from "@/components/QuillEditorWrapper.vue";
import Swal from 'sweetalert2'
import { fetchPaperSigned, approvePaperSigned } from '@/services/attendanceService'
import { Button, Modal } from '@sds/oneui-common-ui';
import { useApiStore } from "@/store/apiData";

const apiStore = useApiStore();
// Reactive state
const loadingApiData = ref(false)
const allData = ref([])
const filteredData = ref([])
const currentPage = ref(1)
const pageSize = ref(10)
const sessionId = ref(apiStore.session?.id)
const selectedDate = ref(new Date().toISOString().split('T')[0])

const props = defineProps({
  date:Date
});

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

const openPaperSignedModal = ref(false);
const fileInput = ref(null);
const uploadedFiles = ref([]);
const editorContent = ref("");
const editorRef = ref(null);
const selectedItem = ref({});
const submitAction = ref(false);
const errorForm = ref({error: '', message: ''});

const handleOpenPaperSignModal = (item) => {
  openPaperSignedModal.value = true;
  selectedItem.value = item;
}

const handleClosePaperSignModal = () => {
  handleResetPaperSignModal();
  openPaperSignedModal.value = false;
  selectedItem.value = {};
}

const handleResetPaperSignModal = () => {
  editorRef.value?.clearEditor();
  uploadedFiles.value = [];
  editorContent.value = '';
  fileInput.value = null;
  errorForm.value = {error: '', message: ''};
}

const handleSubmitPaperSignModal = async () => {
  errorForm.value = {error: '', message: ''};
  if ( submitAction.value ) return;
  const editorText = editorContent.value.replace(/<\/?[^>]+(>|$)/g, "").trim();
  if ( editorText == '' ) {
    errorForm.value = {error:'error', message: 'Please add remark.'};
  }

  if ( errorForm.value.error != '' ) {
    return false;
  }
  submitAction.value = true;  
  const response = await approvePaperSigned(selectedItem.value.id, {file_path: '', remark: editorContent.value})
  submitAction.value = false;  
  if ( response.isError ) {
    let message = '';
    if ( Array.isArray(response.description) && response.description.length ) {
      message += '<ol>'; 
      response.description.map((item, index) => {
        message += '<li key="error-'+index+'">'+item+'</li>'; 
      });
      message += '</ol>'; 
    } else if ( typeof response.description == 'object' && Object.keys(response.description).length ) {
      message = response.description.error || 'Record is not save, please try again.';
    } else {
      message = response.description;
    }
    errorForm.value = {error:'error', message};
  } else {
    if ( response.success_code == 200 ) {
      Swal.fire({
        toast: true,
        icon: 'success',
        title: 'The manual signature has been approved successfully.',
        position: 'top-end',  // can be 'top', 'top-start', 'bottom-end', etc.
        showConfirmButton: false,
        timer: 10000,          // auto-close after 10 seconds
        timerProgressBar: true,
      })
      handleClosePaperSignModal();
    } else {
      errorForm.value = {error:'error', message: response.message || 'Something went wrong.'};
    }
  }
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

const fetchPaperSignedList = async () => {
  try {
    const options = {
      params: {
        session_id: sessionId.value,
        date: selectedDate.value,
        pagelimit: 10000
      }
    }
   
    loadingApiData.value = true;
    const res = await fetchPaperSigned(options);
    loadingApiData.value = false;
    const apiData = res.data ? res.data : [];
    
     
    allData.value = apiData.map(item => ({
      division_no: item.division_no,
      id: item.id,
      name: item.name,
      attendance_date: item.attendance_date,
      party_name: item.party_name,
      profile_photo: item.profile_photo,
      action:'',
    }))

    filteredData.value = [...allData.value]
  } catch (err) {
    console.error('Failed loading data:', err)
  }

}

watch(() => props.date, async (newDate) => {
  if ( newDate ) {
    selectedDate.value = newDate
  }
  await fetchPaperSignedList();
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