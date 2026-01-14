<template>
 <!-- <Loading v-if="isLoading" /> -->
 <div ref="fullscreenElement">
        <div class="flex justify-between gap-2">
          <h2 class="w-[20%] text-lg font-bold text-gray-800 pb-2">Signed Report</h2>
          <div class="w-[80%] flex justify-end gap-2">
            <Tabs />
          </div>
        </div> 
        <div class="flex gap-2 items-center px-4 py-1 bg-white w-full rounded-sm mt-2">
<div class="col-span-2 flex gap-1">
    <Multiselect 
    v-model="selectedToSession" 
    selectLabel="" 
    deselectLabel="" 
    placeholder="Select To Session" 
    class="text-sm text-blue-500 rounded h-fit custom-multiselect" 
    openDirection="below" 
    track-by="id" 
    label="name" 
    :showNoResults="false" 
    :showNoOptions="false" 
    :options="filterToSessions" 
    :multiple="false" 
    :searchable="true" 
    :close-on-select="true" 
    :clear-on-select="false" 
    />
</div>
<div class="p-2">
          <input 
            ref="inputDateRef"
            type="date" 
            v-model="selectedDate"
            :max="maxDate"
            :min="minDate"
            @click="() => handleInputDateClick()"
            class="cursor-pointer text-sm w-40 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            aria-label="attendance-date"
          />

</div>
        </div>
    <!-- Table -->
    <div class="overflow-x-auto bg-white shadow rounded-md mt-2">
      <PageLoading v-show="loadingData" customClasses="absolute top-auto bottom-0" />
      <!-- Table Options -->
      <TableOptions :columns="columns" :data="[]"
        :addButtons="[{icon: 'cuida:refresh-outline', text: 'Refresh', bgColor:'', textColor:'', hoverColor:'hover:text-blue-500'}]"
        :excludeSearch="['id', 'division_no', 'party_name', 'state_name', 'gender', 'is_signed', 'signature_file', 'attendance_source']" :excludeSort="['id', 'signature_file']"
        :excludeFilter="['id', 'division_no', 'signature_file']" @search="handleSearch" @sort="handleSort"
        @filter="handleFilter" @clearSearch="handleClearSearch" @clearSort="handleClearSort"
        @addItems="refreshReport"
        @clearFilter="handleClearFilter" />

      <!-- Data Table -->
      <DataTable :data="filteredData" :customColumnOrder="columns" :showOpen="false">
        <!-- Division Number Cell -->
        <template #cell-division_no="{ cell }">
          <div class="ms-6 font-medium text-gray-900">
            {{ cell.value }}
          </div>
        </template>

        <!-- Member Name Cell -->
        <template #cell-name="{ cell }">
          <div class="text-left px-4">
            <div class="font-medium text-gray-900">{{ cell.row.name_hn }}</div>
            <div class="text-gray-600 text-sm">{{ cell.value }}</div>
          </div>
        </template>
       
        <template #cell-signature_file="{ cell }">
          <div class="px-4 flex items-center gap-2">
            <!-- Show MINISTER badge if is_minister === 1 and no signature img -->
            <div v-if="cell.row.minister_flag == 'M'" class="border border-gray-400 px-6 py-2 text-center font-medium min-w-[170px]">
              MINISTER
            </div>

            <!-- Show Digitally Signed badge if is_hdc === 1 and no signature img -->
            <div v-else-if="cell.row.minister_flag == 'HDC'" class="border border-gray-400 px-6 py-2 text-center font-medium min-w-[170px]">
              HDC
            </div>

            <!-- Show LOP badge if is_lop === 1 and no signature img -->
            <div v-else-if="cell.row.minister_flag == 'LOP'" class="border border-gray-400 px-6 py-2 text-center font-medium min-w-[170px]">
              LOP
            </div>

            <!-- Show signature image if signature_file is present -->
            <div v-if="cell.row.is_signed == 'S' && cell.row.signature_file == 'Digitally Signed'" class="px-6 py-2 text-center italic font-medium min-w-[170px]">
              {{ cell.row.signature_file.toUpperCase() }}
            </div>
            <img
              v-else-if="cell.row.is_signed == `S`"
              :src="cell.row.signature_file"
              alt="Signature"
              class=" object-contain py-2 text-center w-30 h-20"
            />

          </div>
        </template>

        <!-- Attendance Status Cell -->
        <template #cell-is_signed="{ cell }">
          <div class="flex items-center justify-start gap-2 pl-8">
            <span
              :class="[
                'inline-flex items-center justify-center w-14 h-6 rounded-full font-semibold text-sm',
                (cell.value && cell.value.toUpperCase()) === 'S' ? 'bg-green-100 text-green-700 hover:brightness-110 cursor-pointer' :
                (cell.value && cell.value.toUpperCase()) === 'NS' ? 'bg-red-100 text-red-700 ' :
                'bg-pink-100 text-pink-700 cursor-default'
              ]"
              @click="(cell.value && cell.value.toUpperCase()) === 'S' ? openPdfModal(cell.row.media_id) : null"
      :title="(cell.value && cell.value.toUpperCase()) === 'S' ? 'Click to view signed PDF' : null"
            >
              {{ cell.value }}
            </span>
            <div v-if="cell.row.qr_file"
                class="w-12 h-12 border-2 border-black flex items-center justify-center bg-white">
              <img
                :src="getQrImageSrc(cell.row.qr_file)"
                alt="QR Code"
                class="w-full h-full object-contain"
              />
            </div>
            <div v-else class="w-12 h-12"></div>
          </div>
        </template>
        <template #cell-attendance_source="{ cell }">
          {{ cell.value }}
        </template>

      </DataTable>
      <div v-if="filteredData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span> {{ loadingData ? "Loading Data... Please wait" : "Data not found" }}</span>
       </div>
      <!-- Pagination -->
      <div class="mt-0" id="paginationDiv">
        <PaginationSelect :currentPage="totalPages > 0 ? currentPage : 0" :totalPages="totalPages" :pageSize="pageSize"
          @update:currentPage="handleCurrentPageChange" @update:pageSize="handlePageSizeChange" />
      </div>
      
    </div>


<div class="mt-6 flex justify-start">      
    <Button 
    v-if="hasAnyPermission(PERMISSIONS.ATTENDANCE.LOBBY_OFFICE)" 
    label="Digitally Sign and Download" 
    icon="si:lock-line" 
    size="sm" 
    class="ms-4 bg-green-800"
      @click="downloadReportfile" />
      
      <Button
  v-if="hasAnyPermission(PERMISSIONS.ATTENDANCE.LOBBY_OFFICE)"
  label="Share"
  icon="tabler:share"
  size="sm"
  class="ms-4 bg-white"
  color="green-outline"
  :disabled="!reportUrl"
  @click="showShareEmailModal = true"
/>

      <Button
  v-if="hasAnyPermission(PERMISSIONS.ATTENDANCE.LOBBY_OFFICE) || hasAnyPermission(PERMISSIONS.ATTENDANCE.MSA_BRANCH)"
  label="Download Report"
  icon="solar:download-outline"
  size="sm"
  class="ms-4 bg-white"
  color="green-outline"
  :disabled="!reportUrl"
  @click="openReport"
/>

      <Button
  v-if="hasAnyPermission(PERMISSIONS.ATTENDANCE.LOBBY_OFFICE)"
  :label="publishedData ? 'Unpublish' : 'Publish'"
  :icon="publishedData ? 'material-symbols-light:unpublished-rounded' : 'vscode-icons:file-type-publisher'"
  size="sm"
  class="ms-4 bg-white"
  :disabled="!isNight"
  :color="publishedData ? 'red-outline':'green-outline'"
  @click="publishReport"
/>

  </div>

    <div v-if="errorMsg" class="text-red-600 mt-4 text-center font-semibold">
      {{ errorMsg }}
    </div>
    <div v-if="isLoadingReport" class="fixed inset-0 h-screen w-screen z-[9999] flex items-center justify-center bg-white dark:bg-slate-900">
      <div class="flex flex-col items-center space-y-6 px-4">
 
        <img
          src="/assets/images/logo/loading-esign.gif"
          alt="Signing Loader"
          class="w-28 h-28 animate-pulse rounded-xl"
        />
  
      
        <p class="text-center text-gray-700 dark:text-gray-200 text-lg max-w-sm font-semibold">
          A notification has been sent to your linked phone number.
        </p>
        <p class="text-center text-gray-700 dark:text-gray-300 text-base">
          Please open your Sanchalan Setu app and use your biometric authentication to digitally self-sign the document.
        </p>
  
        <p class="text-sm text-red-500 text-center">
          Did not receive notification?
          <button
            @click="resendApi()"
            class="underline hover:text-red-600 transition-colors duration-200"
          >
            Resend
          </button>
        </p>
      </div>
    </div>


  </div>

<PdfModal 
    v-model="showPdfModal"
    :pdfUrl="currentPdfUrl"
    :isLoading="isLoadingPdfUrl"
    @close="closePdfModal"
  />

  <ShareEmailModal 
    v-model="showShareEmailModal"
    :selectedDate="selectedDate"
    @close="closeShareEmailModal"
  />


</template>

<script setup>
import { ref, reactive, computed,onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Loading, Button } from '@sds/oneui-common-ui';
import Swal from 'sweetalert2';
import { PERMISSIONS, hasAnyPermission, hasPermission } from "@/utils/rbac";
import { downloadReport, fetchSessions, getFilterReportDashboard, getReportDashboard, msaDownloadReport, publishReportDashboard, resendNotificationApi, updateEsign, } from "@/services/attendanceService";
import { useI18n } from "vue-i18n";
import "vue-multiselect/dist/vue-multiselect.min.css";
import useLocalDate from "@/composables/useLocalDate";
import Tabs from "./Tabs.vue";
import Multiselect from "vue-multiselect";
import PageLoading from "@/components/Loader/pageLoading.vue";
import DataTable from "@/components/Datatable/DataTable.vue"
import TableOptions from "@/components/Datatable/TableOptions.vue"
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue'
import PdfModal from "../../report/PdfModal.vue";
import ShareEmailModal from "../../report/ShareEmailModal.vue";

const { t, locale } = useI18n();
const isLoading = ref(true);
const inputDateRef = ref(null)
const sessionDetails = ref({});
const selectedToSession = ref([]);
const filterToSessions = ref([]);

const today = new Date().toISOString().split('T')[0];
const selectedDate = ref(today);

const maxDate = computed(() => {
  let d = new Date(sessionDetails.value?.session_end_date);
  if (isNaN(d)) { // handle invalid date
    d = new Date();
  } 
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const minDate = computed(() => {
  let d = new Date(sessionDetails.value?.session_start_date);
  if (isNaN(d)) { // handle invalid date
    d = new Date();
    d.setFullYear(d.getFullYear() - 6); // Subtract 6 years
  } 
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const handleInputDateClick = () => {
  inputDateRef.value.showPicker();
}

const getSessions = async () => {
  const response = await fetchSessions();
  if ( response.length ) {
    filterToSessions.value = response;
    selectedToSession.value = sessionDetails.value = response[0];
  } else {
    console.log("Session is not loaded");
  }
  attendanceReportlist();
  fetchFilterReportDashboard();
  checkReportAvailability();
};

const isLoadingReport = ref(false);
const publishedData = ref(false);
const showShareEmailModal = ref(false)
const showPdfModal = ref(false)
const currentPdfUrl = ref('')
const isLoadingPdfUrl = ref(false)
const openPdfModal = async (media_id) => {
  try {
    isLoadingPdfUrl.value = true
    
    // Get the PDF URL from your API
    const response = await downloadsignedFile(media_id)
    
    if (response && response.data) {
      currentPdfUrl.value = response.data
      showPdfModal.value = true
    } else {
      throw new Error('No PDF URL received')
    }
  } catch (error) {
    console.error('Error loading PDF:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load the PDF document. Please try again.',
      confirmButtonColor: '#3085d6'
    })
  } finally {
    isLoadingPdfUrl.value = false
  }
}

const closePdfModal = () => {
  showPdfModal.value = false
  currentPdfUrl.value = ''
}

const closeShareEmailModal = () => {
  showShareEmailModal.value = false
}
const getQrImageSrc = (qrCode) => {
  if (!qrCode) return '';
  return qrCode.startsWith('data:') ? qrCode : `data:image/png;base64,${qrCode}`;
};
const mediaId = ref();
const errorMsg = ref('');
let currentCallId = 0;
const reportUrl = ref('');
const loadingData = ref(false);
const filtereOptions = ref({members:[], sources:[], status:[], parties:[], states:[], genders:[]});
const filteredData = ref([])
const queryParams = ref({});
const currentPage = ref(1)
const pageSize = ref(10)
const totalRecords = ref(0)
const totalPages = computed(() => Math.ceil(totalRecords.value / pageSize.value));
const columns = ref([
  { multiple: false, key: 'division_no', label: 'क्रम संख्या\nDivision No.' },
  { multiple: true, key: 'name', label: 'सदस्य का नाम\nName Of Member' },
  { multiple: true, key: 'party_name', label: 'पार्टी का नाम\nName Of Party' },
  { multiple: true, key: 'state_name', label: 'राज्य का नाम\nName Of State' },
  { multiple: false, key: 'gender', label: 'लिंग\nGender' },
  { multiple: false, key: 'signature_file', label: 'हस्ताक्षर\nSignature' },
  { multiple: false, key: 'is_signed', label: 'उपस्थिति स्थिति\nAttendance Status' },
  { multiple: false, key: 'attendance_source', label: 'उपस्थिति स्रोत\nAttendance Source' }
])

const handleFilter = (filters) => {
  if ( filters.name ) {
    queryParams.value.member_name = filtereOptions.value.members.filter(item => filters.name.includes(item.display_name)).map(item => item.full_name).join("|"); 
  } else {
    const { member_name, ...remain} = queryParams.value;
    queryParams.value = {...remain};
  } 

  if ( filters.is_signed ) { 
    queryParams.value.attendance_status = filtereOptions.value.status.filter(item => filters.is_signed.includes(item.value)).map(item => item.key).join("|"); 
  } else {
    const { attendance_status, ...remain} = queryParams.value;
    queryParams.value = {...remain};
  }

  if ( filters.attendance_source ) {
    queryParams.value.attendance_source = filtereOptions.value.sources.filter(item => filters.attendance_source.includes(item.value)).map(item => item.key).join("|"); 
  } else {
    const { attendance_source, ...remain} = queryParams.value;
    queryParams.value = {...remain};
  }

  if ( filters.party_name ) {
    queryParams.value.party_name = filtereOptions.value.parties.filter(item => filters.party_name.includes(item.value)).map(item => item.key).join("|"); 
  } else {
    const { party_name, ...remain} = queryParams.value;
    queryParams.value = {...remain};
  }

  if ( filters.state_name ) {
    queryParams.value.state_name = filtereOptions.value.states.filter(item => filters.state_name.includes(item.value)).map(item => item.key).join("|"); 
  } else {
    const { state_name, ...remain} = queryParams.value;
    queryParams.value = {...remain};
  }

  if ( filters.gender ) {
    queryParams.value.gender = filtereOptions.value.genders.filter(item => filters.gender.includes(item.value)).map(item => item.key).join("|"); 
  } else {
    const { gender, ...remain} = queryParams.value;
    queryParams.value = {...remain};
  }
  currentPage.value = 1;
  attendanceReportlist();
}

const handleClearFilter = () => {
  currentPage.value = 1;
  const {member_name, attendance_status, attendance_source, party_name, state_name, gender, ...remain} = queryParams.value;
  queryParams.value = {...remain};
  attendanceReportlist();
}

const handleSearch = ({ key, value }) => {
  currentPage.value = 1;
  if (!value || value.trim() === '') return;
  let keyName = key;
  switch (key) {
    case 'name' : keyName = 'member_name'; break;
    case 'is_signed' : keyName = 'attendance_status'; break;
    default: 
  }
  queryParams.value.search_by = keyName
  queryParams.value.search = value
  attendanceReportlist();
}

const handleClearSearch = () => {
  currentPage.value = 1;
  const {search_by, search, ...remain} = queryParams.value;
  queryParams.value = {...remain};
  attendanceReportlist();
}

const handleSort = config => {
  if (!config.key || !config.order) return;
  let keyName = config.key;
  switch (config.key) {
    case 'name' : keyName = 'member_name'; break;
    case 'is_signed' : keyName = 'attendance_status'; break;
    default: 
  }
  queryParams.value.order_by = keyName
  queryParams.value.order = config.order
  attendanceReportlist();
}

const handleClearSort = () => {
  currentPage.value = 1;
  const {order_by, order, ...remain} = queryParams.value;
  queryParams.value = {...remain};
  attendanceReportlist();
}

const handleCurrentPageChange = (page) => {
  currentPage.value = page;
  attendanceReportlist();
}

// Pagination handlers
const handlePageSizeChange = (newSize) => {
  currentPage.value = 1;
  pageSize.value = newSize,
  attendanceReportlist();
}

const refreshReport = async () => {
  const el = document.getElementById("paginationDiv");
  if (el) {
    el.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }
  await attendanceReportlist();
  await checkReportAvailability();
  fetchFilterReportDashboard();
}; 

const checkReportAvailability = async () => {
  reportUrl.value = '';
  try {
    const response = await msaDownloadReport(selectedDate.value);
    if (response.success_code === 200 && response.data) {
      reportUrl.value = response.data;
    }
  } catch (error) {
    console.error("Error checking report availability:", error);
  }
};

const fetchFilterReportDashboard = async () => {
  const options = {params:{date: selectedDate.value}}
  const response = await getFilterReportDashboard(options);
  if ( !response.isError && response.success_code == 200) {    
    filtereOptions.value.members = !(response.data.members) ? [] : response.data.members;
    filtereOptions.value.status  = !(response.data.attendance_status) ? [] : Object.keys(response.data.attendance_status).map(key => ({key:key, value:response.data.attendance_status[key]}));
    filtereOptions.value.sources = !(response.data.attendance_source) ? [] : Object.keys(response.data.attendance_source).map(key => ({key:key, value:response.data.attendance_source[key]}));
    filtereOptions.value.parties = !(response.data.party_name) ? [] : (response.data.party_name).map(item => ({key:item.party_code, value:item.party_name}));
    filtereOptions.value.states  = !(response.data.state_name) ? [] : (response.data.state_name).map(item => ({key:item.state_code, value:item.state_name}));
    filtereOptions.value.genders = !(response.data.gender) ? [] : Object.keys(response.data.gender).map(key => ({key:key, value:response.data.gender[key]}));
    columns.value = columns.value.map(item => {
      let response = {...item};
      switch (item.key) {
        case 'name': 
          let members = filtereOptions.value.members.map(item => item.display_name);
          response = {...item, values:members};
          break;
        case 'is_signed': 
          let statuses = filtereOptions.value.status.map(item => item.value);
          response = {...item, values: statuses};
          break;
        case 'attendance_source': 
          let sources = filtereOptions.value.sources.map(item => item.value);
          response = {...item, values: sources};
          break;
        case 'party_name': 
          let parties = filtereOptions.value.parties.map(item => item.value);
          response = {...item, values: parties};
          break;
        case 'state_name': 
          let states = filtereOptions.value.states.map(item => item.value);
          response = {...item, values: states};
          break;
        case 'gender': 
          let genders = filtereOptions.value.genders.map(item => item.value);
          response = {...item, values: genders};
          break;
      }
      return response;
    });
  }     
}

const attendanceReportlist = async () => {
  try {
    const options = {
      params: { 
        ...queryParams.value,
        page: currentPage.value,
        pagelimit: pageSize.value,
        session_id: sessionDetails.value.id,
        date: selectedDate.value
      }
    }
    loadingData.value = true;
    let response = await getReportDashboard(options);
    loadingData.value = false;
    const pagination = response.pagination ? response.pagination : {};
    response = response.data ? response.data : [];
    if ( response.length ) {
      totalRecords.value = pagination.total;
      filteredData.value = response.map(item => ({
        ...item,
        date:item.selectedDate
      }))
    } else {
      totalRecords.value = 0;
      filteredData.value = [];
    }     
    //console.log("filter",filteredData.value);

  } catch (err) {
    console.error('Failed loading data:', err)
  }

}

//watch(() => selectedDate.value, fetchPreviousDayAttendance, {immediate:true})
watch(
  [selectedToSession, selectedDate], 
  ([toSession, newDate]) => {
  if (toSession) {
    sessionDetails.value = toSession;
    attendanceReportlist();
    checkReportAvailability();
  }
  if ( newDate ) {
    attendanceReportlist();
    checkReportAvailability();
  }
});

onMounted( async () => {
  await getSessions();
});

const downloadReportfile = async () => {
     isLoadingReport.value = true;     
    const payload = {
      session_id: sessionDetails.value?.id,
      date: selectedDate.value,
    };
   
    // Step 1: 
    const resDownload = await downloadReport(payload);
    //console.log("Download response:", resDownload);

    if (resDownload.success_code !== 200) {
      throw new Error(resDownload?.data?.message || "Download preparation failed.");
    }

    if(resDownload.success_code == 200){
    
       if (resDownload.data.data.esign_request_id) {
        
        // Initial API call
        mediaId.value = resDownload.data.data.media_id;
        callEsign(resDownload.data.data.esign_request_id);
      }
    }
};

const callEsign = async (payloadId) => {
  
   // Generate a unique call ID for this 
  const callId = ++currentCallId;
  
  let filePath = null;
  let attempts = 0;
  let cancelled = false;

  while (!filePath && attempts < 10) { // Optional max 
    
    // Check if this call is still the latest
    if (callId !== currentCallId) {
      console.log('Old call detected, stopping loop');
       cancelled = true;
      break;
    }
    attempts++;

    try {

       isLoadingReport.value = true;
       // startCountdown()
      const resEsign = await updateEsign(payloadId);
      //console.log(`Attempt ${attempts}`, resEsign);

        // Check again after await to see if call is still latest
      if (callId !== currentCallId) {
        console.log('Old call detected after await, stopping loop');
         cancelled = true;
        break;
      }


      // filePath = resEsign?.data?.file?.file_path;
      //console.log("filePath", resEsign);
      if (resEsign.success_code == 200 && resEsign.data != '') {
        //console.log("File ready:", resEsign.data);
         isLoadingReport.value = false;
        checkReportAvailability();
        const filePath = resEsign.data;
       
       // Proceed with download or next step
          const isAbsolute = filePath.startsWith('http');
          const url = isAbsolute ? filePath : `${window.location.origin}${filePath}`;

          const response = await fetch(url);
          const blob = await response.blob();

          const downloadUrl = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = downloadUrl;
          link.download = 'document.pdf'; // filename
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(downloadUrl);
        break;
      } else {
         console.log("File not ready yet, retrying in 10s...");
          await new Promise(resolve => setTimeout(resolve, 10000));
        // Check again after wait
        if (callId !== currentCallId) {
          console.log('Old call detected after wait, stopping loop');
          cancelled = true;
          break;
        }
      }
      
    } catch (error) {
      console.error("Error while polling for file path:", error);
      await new Promise(resolve => setTimeout(resolve, 10000)); // Wait before retry
      // Check again after error delay
      if (callId !== currentCallId) {
        //console.log('Old call detected after error wait, stopping loop');
        cancelled = true;
        break;
      }
    }
  }

    if (!filePath && !cancelled) {
      isLoadingReport.value = false;
  }
};

const resendApi = async () => {
  try {
    const resresend = await resendNotificationApi(mediaId.value); 
    //console.log("resresend",resresend.data);   
    callEsign(resresend.data);
  } catch (error) {
    console.error("Error in resendApi:", error);
  }
};

const openReport = () => {
  if (reportUrl.value) {
    window.open(reportUrl.value, "_blank");
  } else {
    Swal.fire({
      icon: "warning",
      title: "Report not available",
      text: "Please try again later.",
      confirmButtonColor: "#3085d6",
    });
  }
};

const now = ref(new Date());
const isNight = computed(() => {
  const hours = now.value.getHours();
  return hours >= 18 || hours < 8; //Time between 6:00 PM to 8:00 AM
});

const publishReport = async () => {
  if ( loadingData.value ) return;
  loadingData.value = true;
  const payload = {
    date: selectedDate.value,
    publish_status: publishedData.value ? 0 : 1
  }
  const response = await publishReportDashboard(payload);
  loadingData.value = false;
  let action = false;
  if ( !response.isError && response.success_code == 200) {
    action = true;
    publishedData.value = !publishedData.value;
  }
  Swal.fire({
    toast: true,
    position: 'top-end',
    timer: 5000,
    timerProgressBar: true,
    icon: action ? "success": "error",
    text: action ? (publishedData.value ? `We have successfully published data.` : `We have successfully unpublished data.`): response?.customMessage,
    showConfirmButton: false
  });
};

</script>
