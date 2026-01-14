<template>
  <Loading v-if="isLoading" />
    <div class="" ref="fullscreenElement">
      <h2 class="text-lg font-bold text-gray-800 pb-2">{{ headingTitle }}</h2>
    <!-- Header -->
    <div class="flex flex-col mb-0">
        <!-- Second row section: Button -->
        <div class="flex justify-between items-center px-4 py-1 bg-white w-full rounded-sm mt-2">
            <!-- Row: Session and Duration -->
            <div class="flex items-center">
              <div class="text-blue-800 mr-4 font-semibold text-base">Session No. {{ sessionDetails?.session_number || 0 }}<sup>th</sup></div>
                <div class="mr-4">
                <span class="inline-block text-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-semibold">
                      {{ sessionDetails?.session_type || '' }}
                </span>
                </div>
                <div class="px-4">|</div>
                <div class="text-sm text-neutral-600 text-right font-semibold">
                    
                  Duration : {{ useLocalDate(sessionDetails?.session_start_date, 'dd-mm-yyyy') }} to {{ useLocalDate(sessionDetails?.session_end_date, 'dd-mm-yyyy') }}
                </div>
            </div>

            <!-- ✅ Icon row aligned to right -->
            <div class="flex flex-row gap-2 mt-2">
                <button @click="() => handleOpenNewModal()" class="p-2 rounded-md text-sm flex items-center hover:bg-gray-100">
                    <Icon icon="mdi:plus" class="text-base" /> Add New
                </button>
                <button @click="() => handleFilterOptions()" class="p-2 rounded-md hover:bg-gray-100">
                    <Icon icon="cuida:filter-outline" class="w-6 h-6" />
                </button>
                <button @click="() => handleSortOptions()" class="p-2 rounded-md hover:bg-gray-100">
                    <Icon icon="tabler:sort-ascending" class="w-6 h-6" />
                </button>
                <button 
                    v-if="hasPermission([PERMISSIONS.ATTENDANCE.DOWNLOAD_REGULARIZATION])" 
                    @click="downloadExcel" class="p-2 rounded-md hover:bg-gray-100">
                    <Icon icon="heroicons-outline:arrow-down-tray" class="w-6 h-6" />
                </button>
                <button class="p-2 rounded-md hover:bg-gray-100" @click="toggleFullscreen">
                    <Icon v-if="fullscreenMode" icon="eva:collapse-outline" class="w-6 h-6" />
                    <Icon v-else icon="eva:expand-outline" class="w-6 h-6" />
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div v-if="showFilter" class="grid grid-cols-1 md:grid-cols-3 gap-3 flex-wrap gap-4 px-4 pb-2 pt-1 bg-white mb-4 items-center">
                <div class="flex flex-col">
                  <Multiselect
                    v-model="selectedSession"
                    openDirection="below"
                    track-by="id" 
                    label="name"
                    selectLabel=""
                    deselectLabel=""
                    placeholder="Search and select a session..."
                    class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                    :showNoResults="false"
                    :showNoOptions="false"
                    :searchable="true"
                    :options="filterSessions"
                    :multiple="false"
                    :close-on-select="true"
                    :clear-on-select="false"
                  /> 
                </div>
                <div class="flex flex-col">
                  <Multiselect
                    v-model="selectedMember"
                    selectLabel=""
                    deselectLabel=""
                    placeholder="Search and select member name..."
                    class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                    openDirection="below"
                    track-by="id" 
                    label="name"
                    :showNoResults="false"
                    :showNoOptions="false"
                    :options="filterMembers"
                    :multiple="true"
                    :searchable="true"
                    :close-on-select="true"
                    :clear-on-select="false"
                    @select="handleMember"
                    @remove="handleMember"
                  /> 
                </div>


            <button @click="handleClearFilter" class="flex text-sm w-fit text-red-500 items-center gap-1 ml-6 float-end">
                <Icon icon="iconoir:cancel" class="w-6 h-6" /> Clear Filter
            </button>
        </div>

        <!-- Sort options -->
        <div v-if="showSort" class="flex flex-wrap gap-4 p-2 bg-white mb-4 items-center">
            <select 
                v-model="selectedOrderBy"
                @change="handleOrderBy" 
                class="px-2 py-2 text-sm">
                <option value="">Sort By </option>
                <option value="request_id">Request Id</option>
                <option value="division_no">Division No.</option>
                <option value="member_name">Member's Name</option>
                <option value="missed_date">Missed Date</option>
                <option value="submitted_on">Submitted On</option>
                <option value="status">Status</option>
            </select>
            <select 
                v-model="selectedOrder"
                @change="handleOrderBy" 
                class="px-2 py-2 text-sm">
                <option value="asc">Ascending</option>
                <option value="desc">Descending</option>
            </select>
        </div>

    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white shadow rounded-md">
      <table class="min-w-full text-sm text-left text-gray-700">
        <thead class="bg-red-50 text-md text-gray-600">
          <tr>
            <th class="px-4 py-5">Sr No.</th>
            <th class="px-4 py-5">Request Id</th>
            <th class="px-4 py-5">Division No.</th>
            <th class="px-4 py-5">Member's Name</th>
            <th class="px-4 py-5">Missed Date</th>
            <th class="px-4 py-5">Submitted On</th>
            <!-- <th class="px-4 py-5 text-center">Documents</th> -->
            <th class="px-4 py-5 text-center">Member's Request</th>
            <th class="px-4 py-5 text-center">Status</th>
            <th class="px-4 py-5 text-center">e-Office File No.</th>
            <th class="px-4 py-5 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in pageData" :key="index">
            <td class="px-4 py-4">{{ index + Number(pagination.from) }}</td>
            <td class="px-4 py-4">
                {{ item.request_id }}
            </td>
            <td class="px-4 py-4">{{ item.division_no }}</td>
            <td class="px-4 py-4">
              <div class="flex items-center gap-2">
                <img v-if="!!item.profile_photo" :src="item.profile_photo" class="w-6 h-6 rounded-full" />
                <span>{{ item.name }}</span>
              </div>
            </td>
            <td class="px-4 py-4">{{ item.missed_date }}</td>
            <td class="px-4 py-4">{{ item.created_at }}</td>
            <!-- <td class="px-4 py-4">
              <div v-if="!!item.document" class="flex justify-center items-center cursor-pointer">
                <Icon @click="downloadFile({url:item.document, name:'document'})" icon="jam:link" class="w-6 h-6 text-black" />
              </div>
            </td> -->
            <td class="px-4 py-4">
              <div v-if="!!item.request_receipt" class="flex justify-center items-center cursor-pointer">
                <Icon @click="downloadFile({url:item.request_receipt, name:'request_receipt'})" icon="heroicons-outline:arrow-down-tray" class="w-6 h-6 text-black" />
              </div>
            </td>
            <!-- <td class="px-4 py-4 text-center">
              <span
                class="inline-block px-2 py-1 text-xs font-semibold rounded-full"
                :class="{
                  'bg-green-500 text-white': item.status == 1,
                  'bg-red-500 text-white': item.status == 0
                }"
              >
                {{ item.status == 1 ? 'Active' : 'Inactive' }}
              </span>
            </td> -->
            <td class="px-4 py-4 text-center">
              <Badge v-if="item.status == 1" text="Approved" type="success" />
              <Badge v-else text="Submitted" type="info" />
            </td>
            <td class="px-4 py-4 text-center">
              <!-- <div v-if="!!item.approved_receipt" class="flex justify-center items-center cursor-pointer">
                <Icon @click="downloadFile({url:item.approved_receipt, name:'approved_receipt'})" icon="heroicons-outline:arrow-down-tray" class="w-6 h-6 text-black" />
              </div> -->
              {{ item.file_number }}  
              <br />               
              <sup class="font-bold text-blue-500 cursor-pointer">{{ item.file_date }}</sup>
              <div v-show="item.remark" class="relative group inline-block">
                <sup class="underline italic font-bold text-blue-500 cursor-pointer"><Icon icon="ic:outline-info" width="12" height="12" /></sup>
                <!-- tooltip -->
                <div
                  class="absolute bottom-full right-0 mb-1 hidden group-hover:block bg-gray-100 text-xs rounded p-2 whitespace-nowrap z-10"
                >
                {{ item.remark }}
                </div>
              </div>

              
            </td>
            <td class="px-4 py-4 text-center">
              <Button :disabled="item.status == 1" label="Approval" :title="item.status == 1 ? 'It is approved' : 'Click to Approve'" size="xs" class="xs" color="green-outline" @click="() => handleOpenModal(item)"></Button>
            </td>
          </tr>
        
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 text-sm">
      <div class="bg-white">
        <PaginationSelect 
        :pagiShowLabel="pagiShowLabel"
        :pagiPrevLabel="pagiPrevLabel"
        :pagiNextLabel="pagiNextLabel"
        :pagiTotalLabel="pagiTotalLabel"
        :currentPage="pagination.current_page" 
        :totalPages="pagination.last_page" 
        :pageSize="pagination.per_page"
        @update:currentPage="handlePageChange" 
        @update:pageSize="handlePageSizeChange" />
      </div>
    </div>

    <Modal
      :modelValue="showModal"
      :disableBackdrop="true"
      title="Regularization Request Approval"
      size="lg"
      @close="handleCloseModal"
    >
      <form @submit.prevent="handleSubmit">
        <div class="bg-white rounded shadow-sm w-full max-w-max">
          <div class="pb-2 text-gray-500 font-semibold text-sm">Upload File: 
            <label
            for="fileUpload"
            class="inline-flex items-center cursor-pointer px-2 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50"
            >
              <input
                id="fileUpload"
                type="file"
                ref="fileInput"
                @change="handleFileUpload"
              />
              <FileUploads v-show="false" ref="uploadRef" :onFileUpload="postMethod" @update:files="handleFiles" />
            </label>
            <div class="inline-flex px-2 py-1 cursor-pointer" v-if="uploadedFiles.length && uploadedFiles[0]?.view_path" @click="downloadFile({url:uploadedFiles[0]?.view_path, name:'approved_receipt'})" >
              <Icon icon="heroicons-solid:eye" class="w-6 h-6 text-black" />
            </div>
          </div>
          <div class="pb-2 grid grid-cols-2 gap-4 text-gray-500 font-semibold text-sm">
            <div>
              <TextInput
                type="text"
                v-model="fileEmail"
                label="E-Mail Id of Recipients: "
                placeholder="Enter a valid email"
                @update:modelValue="(data) => handleFormData(data, 'fileEmail')"
              />
              <span class="text-sm text-red-500"></span>
            </div>
            <div>
              <TextInput
                type="text"
                label="e-Office File Number: "
                placeholder="Enter e-office file number"
                @update:modelValue="(data) => handleFormData(data, 'fileNumber')"
              />
              <span class="text-sm text-red-500"></span>
            </div>
            <div>
              <TextInput
                type="date"
                label="File Approval Date: "
                @update:modelValue="(data) => handleFormData(data, 'fileDate')"
              />
              <span class="text-sm text-red-500"></span>
            </div>
          </div>
          <div class="pb-2 text-gray-500 font-semibold text-sm">Remark: </div>
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
            <Button type="submit" label="Submit" size="sm" color="green-outline" />
            <Button type="reset" label="Clear" size="sm" color="red-outline" @click="handleReset" />
          </div>
        </div>  
      </form>
    </Modal>

    <Modal
      :modelValue="showNewLeaveModal"
      :disableBackdrop="true"
      :title="filterSessions.length ? 'Regularization Request (Session - ' + filterSessions[0]['session_number'] + ')' : 'Create Regurlization Request'"
      size="lg"
      @close="handleCloseNewModal"
    >
      <AddNewRegularization :handleCloseNewModal="handleCloseNewModal" :reasons="reasonOptions" :sessionDetails="filterSessions.length ? filterSessions[0] : {}"/>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed,onMounted, onUnmounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button, Badge, Loading, Modal, TextInput, FileUploads } from '@sds/oneui-common-ui';
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import Swal from 'sweetalert2';
import QuillEditorWrapper from "@/components/QuillEditorWrapper.vue";
import { postMethod } from "@/composables/useApi";
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { fetchReasons, fetchSessions, fetchRegularizationFilters, fetchRegularizationList, approveRegulizationRequest } from "@/services/attendanceService";
import { useI18n } from "vue-i18n";
import { exportToXlsx, downloadFile } from '@/utils/downloads';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import AddNewRegularization from "./AddNewRegularization.vue";
import useLocalDate from "@/composables/useLocalDate";

const { t, locale } = useI18n();

const uploadRef = ref(null);
const fileInput = ref(null);
const uploadedFiles = ref([]);
const fileDate = ref("");
const fileNumber = ref("");
const fileEmail = ref("rsaudit@sansad.nic.in,rsmsa@sansad.nic.in")
const editorContent = ref("");
const editorRef = ref(null);
const selectedItem = ref({});
const submitAction = ref(false);
const showModal = ref(false);
const errorForm = ref({error: '', message: ''});
const reasonOptions = ref([]);

const oklabel = computed(() => t('btn_ok'));
const headingTitle = computed(() => t("menu.tab_1") + " " + t("menu.tab_1_1"));
const downloadMessage = ref('');
const showFilter = ref(false);
const showSort = ref(false);

const sessionDetails = ref({});
const selectedSession = ref([]);
const filterSessions = ref([]);

const selectedMember = ref([]);
const filterMembers = ref([]);

const selectedOrderBy = ref('');
const selectedOrder = ref('asc');

const options = reactive({});
const pageData = ref([]);
const isLoading = ref(true);
const pageMessage = ref('');
const totalRecord = ref(0);
const pagination = ref({current_page:0, last_page:0});
const pagiShowLabel = computed( () => t("pagi_show_label"));
const pagiPrevLabel = computed( () => t("pagi_prev_label"));
const pagiNextLabel = computed( () => t("pagi_next_label"));
const pagiTotalLabel = computed(() => t("pagi_total_label", { current: pagination.value.current_page, total: pagination.value.last_page }));

//Start of add new leave modal popup
const showNewLeaveModal = ref(false);
const handleOpenNewModal = () => {
  showNewLeaveModal.value = true;
}

const handleCloseNewModal = (param='') => {
  if (param == 'added') {
    getRegularizationList();
  }
  showNewLeaveModal.value = false;
}
//End of add new leave modal popup

const handleFilterOptions = () => {
    if ( showSort.value ) { showSort.value = false; }
    showFilter.value = !showFilter.value;
}
const handleSortOptions = () => {
    if ( showFilter.value ) { showFilter.value = false; }
    showSort.value = !showSort.value;
}

const getRegularizationList = async () => {
  isLoading.value = true;
  pageData.value = [];
  pagination.value = {current_page:0, last_page:0};
  totalRecord.value = 0;
  if ( sessionDetails.value?.id < 1 ) {
    isLoading.value = false;
    pageMessage.value = computed( () =>t("no_record"));
    return false;
  }
  options.params = {...options.params, session_id: sessionDetails.value?.id};
  const response = await fetchRegularizationList(options);
  isLoading.value = false;
  if ( response.isError == false ) {
    if ( response.success_code == 200 ) {
      pageData.value = response.data;
      if ( pageData.value.length == 0) {
        pageMessage.value = computed( () =>t("no_record"));
      } else {
        pagination.value = response.pagination;
        totalRecord.value = response.pagination?.total;
      }
    } else {
      pageData.value = [];
      pageMessage.value = computed( () =>t("no_record"));
    }
  } else {
    pageMessage.value = computed( () =>t("something_wrong"));
  }
};

const getRegularizationFiters = async () => {
  filterMembers.value = [];
  if ( sessionDetails.value?.id < 1 ) {
    return false;
  }
  const response = await fetchRegularizationFilters(sessionDetails.value?.id);
  if ( response.isError == false ) {
    if ( response.success_code == 200 ) {
      const customData = response.data;
      if ( customData?.names && customData?.names.length ) {
        filterMembers.value = customData.names;
      }
    }
  } else {
    console.log("Leave Request filters is not loaded!");
  }
};

const getSessions = async () => {
  filterSessions.value = [];
  const response = await fetchSessions();
  if ( response.length ) {
    filterSessions.value = response;
    sessionDetails.value = response[0];
    getRegularizationFiters();
  } else {
    console.log("Session is not loaded");
  }
  getRegularizationList();
};

watch(selectedSession, async (newVal, oldVal) => {
  options.params = {};
  sessionDetails.value = newVal;
  selectedMember.value = [];
  getRegularizationFiters();
  getRegularizationList();  
});

onMounted( async () => {
  getSessions();
  reasonOptions.value = await fetchReasons('leave');
});

const downloadExcel = async () => {
  let index = 1;
  let customData = [];
  if ( options.params?.page ) {
    const {page, ...remain}  = options.params;
    options.params = {...remain};
  }
  options.params = {
    ...options.params, 
    pagelimit: totalRecord.value
  };
  const response = await fetchRegularizationList(options);
  if ( response.isError == false ) {
    if ( response.success_code == 200 && response.data.length ) {
      const apiDataLength = response.data.length;
      for(let i = 0; i < apiDataLength; i++ ) {
        let row = response.data[i];
        let record = {
          'SrNo': index++,
          'RequestId': row.request_id,
          'DivisionNo': row.division_no,
          'MemberName': row.name,
          'MissedDate': row.missed_date,
          'SubmittedOn': row.created_at,
          'Status': row.status == 1 ? 'Active' : 'Inactive'
          // 'Status': row.status == 1 ? 'Active' : 'Submitted'
        };
        customData.push(record);
      }
      exportToXlsx(customData, 'RegularizationReport');
      return true;
    } else {
      downloadMessage.value = t("no_record");
    }
  } else {
    downloadMessage.value = t("something_wrong");
  }

  Swal.fire({
    icon: 'error',
    title: headingTitle.value,
    text: downloadMessage.value,
    confirmButtonText: oklabel.value,
    confirmButtonColor: '#4bc66d'
  });

};

//End of download section

const handleMember = () => {
  let memberIds = selectedMember.value.map(item => item.id).join("|");
  if ( memberIds ) {
    options.params = {
      ...options.params, 
      name: memberIds
    };
  } else {
    if ( options.params.name ) {
      const { name, ...remain } = options.params;
      options.params = { ...remain };
    }
  } 
  getRegularizationList();  
};

const handleClearFilter = () => {
  selectedMember.value = [];
  if ( options.params?.name ) {
    const { name, ...remain } = options.params;
    options.params = {...remain};
  }
  if ( options.params?.date ) {
    const { date, ...remain } = options.params;
    options.params = {...remain};
  }
  if ( options.params?.page ) {
    const { page, ...remain } = options.params;
    options.params = {...remain};
  }
  if ( options.params?.pagelimit ) {
    const { pagelimit, ...remain } = options.params;
    options.params = {...remain};
  }
  if ( options.params && Object.keys(options.params).length ) {
    getRegularizationList();
  }
};

const handleOrderBy = () => {
  if ( selectedOrder.value != '' ) {
    options.params = {
      ...options.params, 
      order_by: selectedOrderBy.value,
      order: selectedOrder.value,
    };
  } else {
    if ( options.params?.order_by ) {
      const { order_by, ...remain } = options.params;
      options.params = { ...remain };
    }    
    if ( options.params?.order ) {
      const { order, ...remain } = options.params;
      options.params = { ...remain };
    }    
  } 
  if ( selectedOrderBy.value != '' ) {
    getRegularizationList();  
  }
};

//count page
const handlePageChange = newPage => {
  options.params = {
    ...options.params, 
    page: newPage
  };
  getRegularizationList();  
};

const handlePageSizeChange = newSize => {
  options.params = {
    ...options.params, 
    pagelimit: newSize
  };
  getRegularizationList();  
};

const fullscreenElement = ref(null)
const fullscreenMode = ref(false);

const toggleFullscreen = () => {
  fullscreenMode.value = ! fullscreenMode.value;
  const el = fullscreenElement.value

  if (document.fullscreenElement) {
    document.exitFullscreen()
  } else if (el) {
    el.requestFullscreen()
  }
}

//Start for modal popup
const handleOpenModal = (item) => {
  showModal.value = true;
  selectedItem.value = item;
}

const handleCloseModal = (param='') => {
  showModal.value = false;
  selectedItem.value = {};
  handleReset();
  if ( param == 'approved' ) {
    getRegularizationList();
  }
}

const handleFileUpload = (e) => {
  uploadRef.value?.customUpload(e.target.files);
}
const handleFiles = (files) => {
  uploadedFiles.value = files;
}

const handleReset = () => {
  editorRef.value?.clearEditor();
  uploadedFiles.value = [];
  fileNumber.value = '';
  fileDate.value = '';
  fileEmail.value = 'rsaudit@sansad.nic.in,rsmsa@sansad.nic.in';
  editorContent.value = '';
  fileInput.value = null;
  errorForm.value = {error: '', message: ''};
}

const handleFormData = (data, type='') => {
  if ( type == 'fileNumber' ) {
    fileNumber.value = data;
  } else if ( type == 'fileDate' ) {
    fileDate.value = data;
  } else if ( type == 'fileEmail' ) {
    fileEmail.value = data;
  }
}

const handleSubmit = async () => {
  errorForm.value = {error: '', message: ''};
  if ( submitAction.value ) return;
  const editorText = editorContent.value.replace(/<\/?[^>]+(>|$)/g, "").trim();
  // if ( editorText == '' ) {
  //   errorForm.value = {error:'error', message: 'Please add remark.'};
  // }

  if ( fileDate.value.trim() == '' ) {
    errorForm.value = {error:'error', message: 'File date is required.'};
  }

  if ( fileNumber.value.trim() == '' ) {
    errorForm.value = {error:'error', message: 'File number is required.'};
  } else if ( !/^[a-zA-Z0-9]+$/.test(fileNumber.value.trim()) ) {
    errorForm.value = {error:'error', message: 'File number should be alpha numeric only.'};
  }

  let testEmail = fileEmail.value.trim();
  if ( testEmail == '' ) {
    errorForm.value = {error:'error', message: 'Email Id is required.'};
  } else {
    let invalidEmails = false;
    const emails = testEmail.split(",").map(item => {
      if ( !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(item.trim()) ) {
        invalidEmails = true;
      }
      return item;
    })
    if (invalidEmails) {
      errorForm.value = {error:'error', message: emails.length > 1 ? 'Please enter valid Emails Id.' : 'Please enter a valid Email Id.'};
    }
  }

  // if ( uploadedFiles.value.length == 0 ) {
  //   errorForm.value = {error:'error', message: 'Please upload file.'};
  // }

  if ( uploadedFiles.value.length && uploadedFiles.value[0]['errors'].length ) {
    errorForm.value = {error:'error', message: uploadedFiles.value[0]['errors'][0]};
  }
  if ( errorForm.value.error != '' ) {
    return false;
  }
  let payload = {
    remark: editorText,
    file_number: fileNumber.value,
    to_address: fileEmail.value.split(","),
    file_date: fileDate.value
  };

  let file = uploadedFiles.value.length ? uploadedFiles.value[0]['path'] : '';
  if ( file != '' ) {
    payload.file_path = file;
  }
  //console.log(payload); return;
  submitAction.value = true;  
  const response = await approveRegulizationRequest(selectedItem.value.id, payload);
  submitAction.value = false;  
  if ( response.isError ) {
    errorForm.value = {error:'error', message: response.customMessage};
  } else {
    if ( response.success_code == 200 ) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: "Record save successfully",
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true
      });
      pageData.value = pageData.value.map(item => {
        if ( selectedItem.value.id == item.id ) {
          return {...item, status: 1,approved_receipt: uploadedFiles[0]?.view_path};
        } else {
          return item;
        }
      })
      handleCloseModal('approved');
    } else {
      errorForm.value = {error:'error', message: response.customMessage};
    }
  }
}

onUnmounted(() => {
  handleReset();
})
//End of modal popup


</script>
<style lang="css" scoped>
select {
  @apply rounded-sm text-neutral-500 cursor-pointer border-[1.5px] border-[#d1d5db] text-sm px-3 py-2 rounded focus:outline-none;
}

option {
  @apply text-neutral-800 cursor-pointer;
}
</style>