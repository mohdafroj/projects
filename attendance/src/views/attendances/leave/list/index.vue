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
                    v-if="hasPermission([PERMISSIONS.ATTENDANCE.DOWNLOAD_LEAVE_REPORT])" 
                    @click="downloadExcel" class="p-2 rounded-md hover:bg-gray-100">
                    <Icon icon="heroicons-outline:arrow-down-tray" class="w-6 h-6" />
                </button>
                <button class="p-2 rounded-md hover:bg-gray-100" @click="toggleFullscreen">
                    <Icon v-if="fullscreenMode" icon="eva:collapse-outline" class="w-6 h-6 text-black" />
                    <Icon v-else icon="eva:expand-outline" class="w-6 h-6" />
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div v-if="showFilter" class="grid grid-cols-1 md:grid-cols-3 gap-3 flex-wrap gap-4 px-4 pb-2 pt-1 bg-white mb-4 items-center">
                <div class="flex flex-col">
                  <Multiselect
                    v-model="selectedSession"
                    selectLabel=""
                    deselectLabel=""
                    placeholder="Search and select a session..."
                    class="text-sm text-blue-500 rounded h-fit custom-multiselect"
                    openDirection="below"
                    track-by="id" 
                    label="name"
                    :showNoResults="false"
                    :showNoOptions="false"
                    :options="filterSessions"
                    :multiple="false"
                    :searchable="true"
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
                <option value="submitted_on">Submitted On</option>
                <option value="leave_no">Total No. of Leave</option>
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
        <thead class="bg-red-50 text-md text-gray-600 uppercase">
          <tr>
            <th class="px-4 py-5">Sr No.</th>
            <th class="px-4 py-5">Request Id</th>
            <th class="px-4 py-5">Division No.</th>
            <th class="px-4 py-5">Member's Name</th>
            <th class="px-4 py-5">Leave Date</th>
            <th class="px-4 py-5">Submitted On</th>
            <th class="px-4 py-5">Total Leave</th>
            <th class="px-4 py-5 text-center">Member's Request</th>
            <th class="px-4 py-5 text-center">Status</th>
            <th class="px-4 py-5 text-center">Action</th>
          </tr>
        </thead>
        <tbody v-if="pageData.length">
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
            <td class="px-4 py-4">{{ item.start_date + ' to ' + item.end_date }}</td>
            <td class="px-4 py-4">{{ item.created_at }}</td>
            <td class="px-4 py-4">{{ item.total_days }}</td>
            <td class="px-4 py-4 flex items-center justify-center">
              <div v-if="!!item.request_receipt" class="flex justify-center items-center cursor-pointer mr-1">
                <Icon @click="downloadFile({url:item.request_receipt, name:'request_receipt'})" icon="heroicons-outline:arrow-down-tray" class="w-6 h-6 text-black" />
              </div>
              <div v-if="!!item.document" class="flex justify-center items-center cursor-pointer ml-1">
                <Icon @click="downloadFile({url:item.document, name:'document'})" icon="jam:link" class="w-6 h-6 text-black" />
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
              <Badge v-else-if="item.status == 2" text="Progress" type="primary" />
              <Badge v-else text="Submitted" type="info" />
            </td>
            <td class="px-4 py-4 text-center">
              <span v-if="item.status == 1" >
                <Button :disabled="false" label="Approval" title="It is approved" size="xs" color="green-outline" @click="() => fetchLeaveRequestProcess(item)"></Button>
              </span>
              <span v-else-if="item.status == 2">
                <Button label="Approval" title="Click to Approve" size="xs" color="green-outline" @click="() => fetchLeaveRequestProcess(item)"></Button>
              </span>
              <span v-else>
                <Button :disabled="!hasPermission([PERMISSIONS.APPROVAL.LEAVE.INITIATE])" label="Approval" title="Click to Approve" size="xs" color="green-outline" @click="() => fetchLeaveRequestProcess(item)"></Button>
              </span>
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
      title="Leave Request Approval"
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
          <div class="pb-2">
            <QuillEditorWrapper
            v-model:content="editorContent"
            editorHeight="170px"
            contentType="html"
            ref="editorRef"
            theme="snow" />
          </div>
          <div class="pb-2 pt-2 flex">
            <div class="w-[50%] flex-item">
              <SelectInput type="select" label="Leave Reason" v-model="short_remark_select" :options="reasonOptions" @change="handleShortRemark" />
            </div>            
            <div v-show="short_remark_select == 'Other'" class="w-[50%] flex-item  pl-2">
              <TextInput class="pt-7" v-model="short_remark_text" placeholder="Enter short remark" />
            </div>          
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
      :title="filterSessions.length ? `Create Leave Request (Session - ${sessionDetails?.session_number || '0'})` : 'Create Leave Request'"
      size="lg"
      @close="handleCloseNewModal"
    >
      <AddNewLeave :handleCloseNewModal="handleCloseNewModal" :reasons="reasonOptions" :sessionDetails="sessionDetails"/>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed,onMounted, onUnmounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button, Badge, Loading, Modal, FileUploads, TextInput, SelectInput } from '@sds/oneui-common-ui';
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import QuillEditorWrapper from "@/components/QuillEditorWrapper.vue";
import { postMethod } from "@/composables/useApi";
import Swal from 'sweetalert2';
import AddNewLeave from "./AddNewLeave.vue";
import { PERMISSIONS, hasAnyPermission, hasPermission } from "@/utils/rbac";
import { fetchReasons, fetchSessions, fetchLeaveRequestFilters, fetchAttendanceLeaveRequest, leaveRequestProcess, approveLeaveRequest} from "@/services/attendanceService";
import { useI18n } from "vue-i18n";
import { exportToXlsx, downloadFile } from '@/utils/downloads';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import useLocalDate from "@/composables/useLocalDate";
import router from "@/router";

const { t, locale } = useI18n();

const uploadRef = ref(null);
const fileInput = ref(null);
const uploadedFiles = ref([]);
const editorContent = ref("");
const fileDate = ref("");
const fileNumber = ref("");
const editorRef = ref(null);
const selectedItem = ref({});
const submitAction = ref(false);
const showModal = ref(false);
const errorForm = ref({error: '', message: ''});
const short_remark_select = ref('');
const short_remark_text = ref('');
const reasonOptions = ref([]);

const oklabel = computed(() => t('btn_ok'));
const headingTitle = computed(() => t("menu.tab_1_3"));
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

const handleFilterOptions = () => {
    if ( showSort.value ) { showSort.value = false; }
    showFilter.value = !showFilter.value;
}
const handleSortOptions = () => {
    if ( showFilter.value ) { showFilter.value = false; }
    showSort.value = !showSort.value;
}

const fetchLeaveRequestProcess = async (item) => {
  let checkPermission = false;
  if ( item.status == 0 ) {
    if ( hasPermission(PERMISSIONS.APPROVAL.LEAVE.INITIATE) == false ) {
      checkPermission = true;
    }
  } else if ( item.status == 2 ) {
    if ( hasAnyPermission([PERMISSIONS.APPROVAL.LEAVE.REVIEW, PERMISSIONS.APPROVAL.LEAVE.APPROVE]) == false ) {
      //checkPermission = true;
    }
  }
  if ( checkPermission ) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'warning',
      title: "Sorry, you don’t have permission to perform this action.",
      showConfirmButton: false,
      timer: 5000,
      timerProgressBar: true
    });
    return false;
  }

  isLoading.value = true;
  const response = await leaveRequestProcess(item.id);
  isLoading.value = false;
  if ( response.isError == false && response.success_code == 200 ) {
    const moduleId = response.data?.approval_module_id;
    router.push({name:'Leave_Request_Approval', params:{id: moduleId}})
  } else {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: response.customMessage || "Something went wrong.",
      showConfirmButton: false,
      timer: 5000,
      timerProgressBar: true
    });
  }
}
 
const getAttendanceLeaveRequest = async () => {
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
  const response = await fetchAttendanceLeaveRequest(options);
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

const getLeaveRequestFiters = async () => {
  filterMembers.value = [];
  if ( sessionDetails.value?.id < 1 ) {
    return false;
  }
  const response = await fetchLeaveRequestFilters(sessionDetails.value?.id);
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
    getLeaveRequestFiters();
  } else {
    console.log("Session is not loaded");
  }
  getAttendanceLeaveRequest();  
};

watch(selectedSession, async (newVal, oldVal) => {
  options.params = {};
  sessionDetails.value = newVal;
  selectedMember.value = [];
  getLeaveRequestFiters();
  getAttendanceLeaveRequest();  
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
  const response = await fetchAttendanceLeaveRequest(options);
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
          'LeaveDate': row.start_date + ' to ' + row.end_date,
          'SubmittedOn': row.created_at,
          'TotalNoOfLeave': row.total_days,
          'Status': row.status == 1 ? 'Active' : 'Inactive'
        };
        customData.push(record);
      }
      exportToXlsx(customData, 'LeaveReport');
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
    if ( options.params?.name ) {
      const { name, ...remain } = options.params;
      options.params = { ...remain };
    }
  } 
  getAttendanceLeaveRequest();  
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
    getAttendanceLeaveRequest();
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
    getAttendanceLeaveRequest();  
  }
};

//count page
const handlePageChange = newPage => {
  options.params = {
    ...options.params, 
    page: newPage
  };
  getAttendanceLeaveRequest();  
};

const handlePageSizeChange = newSize => {
  options.params = {
    ...options.params, 
    pagelimit: newSize
  };
  getAttendanceLeaveRequest();  
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

//Start of add new leave modal popup
const showNewLeaveModal = ref(false);
const handleOpenNewModal = () => {
  showNewLeaveModal.value = true;
}

const handleCloseNewModal = (param='') => {
  if (param == 'added') {
    getAttendanceLeaveRequest();
  }
  showNewLeaveModal.value = false;
}
//End of add new leave modal popup

//Start for modal popup
const handleOpenModal = (item) => {
  showModal.value = true;
  selectedItem.value = item;
}

const handleCloseModal = (param='') => {
  showModal.value = false;
  selectedItem.value = {};
  if ( param == 'approved' ) {
    getAttendanceLeaveRequest();
  }
  handleReset();
}

const handleFileUpload = (e) => {
  uploadRef.value?.customUpload(e.target.files);
}
const handleFiles = (files) => {
  uploadedFiles.value = files;
}
const handleShortRemark = () => {
  short_remark_text.value = '';
  errorForm.value = {error: '', message: ''};
}

const handleReset = () => {
  editorRef.value?.clearEditor();
  uploadedFiles.value = [];
  editorContent.value = '';
  fileNumber.value = '';
  fileDate.value = '';
  fileInput.value = null;
  short_remark_text.value = '';
  errorForm.value = {error: '', message: ''};
}

const handleFormData = (data, type='') => {
  if ( type == 'fileNumber' ) {
    fileNumber.value = data;
  } else if ( type == 'fileDate' ) {
    fileDate.value = data;
  }
}

const handleSubmit = async () => {
  errorForm.value = {error: '', message: ''};
  if ( submitAction.value ) return;
  let shortRemark = '';
  if ( short_remark_select.value == '' ) {
    errorForm.value = {error:'error', message: 'Please select an option.'};
  } else if (short_remark_select.value == 'Other' && short_remark_text.value.trim() == '') {
    errorForm.value = {error:'error', message: 'Please enter short remark.'};
  } else {
    shortRemark = short_remark_select.value == 'Other' ? short_remark_text.value.trim() : short_remark_select.value;
  }

  // const editorText = editorContent.value.replace(/<\/?[^>]+(>|$)/g, "").trim();
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
    short_remark: shortRemark, 
    file_number: fileNumber.value,
    file_date: fileDate.value, 
    remark: editorContent.value.replace(/<\/?[^>]+(>|$)/g, "").trim()
  };
  let file = uploadedFiles.value.length ? uploadedFiles.value[0]['path'] : '';
  if ( file != '' ) {
    payload.file_path = file;
  } 

  //console.log(payload); return;
  submitAction.value = true;
  const response = await approveLeaveRequest(selectedItem.value.id, payload)
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
      });
      handleCloseModal('approved');
    } else {
      errorForm.value = {error:'error', message: response.message || 'Something went wrong.'};
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
  @apply text-neutral-800 cursor-pointer; /* Only some browsers respect this! */
}
</style>