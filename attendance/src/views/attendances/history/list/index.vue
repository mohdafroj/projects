<template>
  <Loading v-if="isLoading" />
    <div class="" ref="fullscreenElement">
        <div class="flex justify-between gap-2">
          <h2 class="w-[20%] text-lg font-bold text-gray-800 pb-2">{{ headingTitle }}</h2>
          <div class="w-[80%] flex justify-end gap-2">
            <Tabs />
            <!-- <Button
              title="Download consolidated report for the current session only"
              size="xs"
              color="green"
              icon="vscode-icons:file-type-publisher"
              label="Consolidated Report"
              @click="consolidatedReportExcel"
            /> -->
          </div>
        </div>      
        <!-- Second row section: Button -->
        <div class="flex justify-between items-center px-4 py-1 bg-white w-full rounded-sm mt-2">
            <!-- Row: Session and Duration -->
            <div v-if="selectedFromSession?.session_number === selectedToSession?.session_number" class="flex items-center">
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
            
            <div v-else class="flex items-center">
              <div class="text-blue-800 mr-4 font-semibold text-base">Session Number: {{ selectedFromSession?.session_number || 0 }} To {{ selectedToSession?.session_number || 0 }}</div>
              <div class="px-4">|</div>
              <div class="text-sm text-neutral-600 text-right font-semibold">                  
                Duration : {{ useLocalDate(selectedFromSession?.session_start_date, 'dd-mm-yyyy') }} To {{ useLocalDate(sessionDetails?.session_end_date, 'dd-mm-yyyy') }}
              </div>
            </div>
            
            <!-- ✅ Icon row aligned to right -->
            <div class="flex flex-row gap-2 mt-2">
                <button @click="() => {
                    if ( showSort ) { showSort = false; }
                    showFilter = !showFilter;
                  }" 
                  class="p-2 rounded-md hover:bg-gray-100">
                    <Icon icon="cuida:filter-outline" class="w-6 h-6" />
                </button>
                <button @click="() => {
                      if ( showFilter ) { showFilter = false; }
                      showSort = !showSort;
                  }" 
                  class="p-2 rounded-md hover:bg-gray-100">
                    <Icon icon="tabler:sort-ascending" class="w-6 h-6" />
                </button>
                <button
                    v-if="hasPermission([PERMISSIONS.ATTENDANCE.DOWNLOAD_HISTORY])" 
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
        <div v-if="showFilter" class="grid grid-cols-1 md:grid-cols-5 flex-wrap gap-2 px-4 pb-2 bg-white items-center">
<div class="col-span-2 flex gap-1">
  <div class="flex flex-col w-1/2">
    <Multiselect
    v-model="selectedFromSession" 
    selectLabel="" 
    deselectLabel="" 
    placeholder="Select From Session" 
    class="text-sm text-blue-500 rounded h-fit custom-multiselect" 
    openDirection="below" 
    track-by="id" 
    label="name" 
    :showNoResults="false" 
    :showNoOptions="false" 
    :options="filterFromSessions" 
    :multiple="false" 
    :searchable="true" 
    :close-on-select="true" 
    :clear-on-select="false" 
    />
  </div>

  <!-- To Session -->
  <div class="flex flex-col w-1/2">
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
</div>
                <div class="flex flex-col">
                  <Multiselect
                    v-model="selectedMember"
                    selectLabel=""
                    deselectLabel=""
                    placeholder="Search member name..."
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

                <div class="flex flex-col relative" v-click-outside="handleCalendarClickOutside">
                  <button
                    @click="toggleCalendar"
                    class="px-3 py-2.5 rounded-md border border-gray-300 text-sm bg-white flex justify-between items-center w-full text-gray-700 hover:bg-gray-50"
                  >
                    <div class="flex flex-row">
                      <Icon
                        icon="tabler:calendar-check"
                        class="text-xl font-bold mr-1"
                      />
                      {{ selectedFromDate || 'From' }} - {{ selectedToDate || 'To' }}
                    </div>
                    <Icon
                      icon="heroicons-outline:chevron-down"
                      class="text-base ml-10"
                    />
                  </button>
                  <!-- Date Filter Dropdown -->
                  <div
                    v-if="openCalendar"
                    class="z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 py-2 px-3 space-y-2"
                  >
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">From:</label>
                      <input
                        v-model="selectedFromDate"
                        :min="minFromDate"
                        :max="maxToDate"
                        type="date"
                        class="border px-2 py-1 rounded-md text-sm w-40"
                      />
                    </div>
                    <div class="flex items-center justify-between">
                      <label class="text-sm text-gray-700">To:</label>
                      <input
                        v-model="selectedToDate"
                        :min="minFromDate"
                        :max="maxToDate"
                        type="date"
                        class="border px-2 py-1 rounded-md text-sm w-40"
                      />
                    </div>
                  </div>
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
                <option value="division_no">Division No.</option>
                <option value="member_name">Member's Name</option>
                <option value="state">State</option>
                <option value="party_name">Party Name</option>
                <option value="days">No. of Days Signed</option>
            </select>
            <select 
                v-model="selectedOrder"
                @change="handleOrderBy" 
                class="px-2 py-2 text-sm">
                <option value="asc">Ascending</option>
                <option value="desc">Descending</option>
            </select>
        </div>



    <!-- Table -->
    <div class="overflow-x-auto bg-white shadow rounded-md">
      <table class="min-w-full text-sm text-left text-gray-700">
        <thead class="bg-red-50 text-md text-gray-600">
          <tr>
            <th class="px-4 py-5">Sr No.</th>
            <th class="px-4 py-5"></th>
            <th class="px-4 py-5">Division No.</th>
            <th class="px-4 py-5">Member's Name</th>
            <th class="px-4 py-5">State</th>
            <th class="px-4 py-5">Party Name</th>
            <th class="px-4 py-5">Count of Signed Days</th>
          </tr>
        </thead>
        <tbody v-if="pageData.length">
          <template v-for="(row, index) in pageData" :key="index">
            <tr class="cursor-pointer" @click="toggleDetails(index)">
              <td class="px-4 py-4">{{ index + Number(pagination.from) }}</td>
              <td class="px-4 py-4">
                  <Icon v-if="expandedRow == index" icon="bx:caret-down" class="w-6 h-6 text-black" />
                  <Icon v-else icon="bx:caret-right" class="w-6 h-6 text-black" />
              </td>
              <td class="px-4 py-4">{{ row.division_no }}</td>
              <td class="px-4 py-4">
                <div class="flex items-center gap-1">
                  <img v-if="!!row.profile_photo" :src="row.profile_photo" class="w-6 h-6 rounded-full" />
                  <span>{{ row.name }}</span>
                </div>
              </td>
              <td class="px-4 py-4">{{ row.state_shortname }}</td>
              <td class="px-4 py-4">{{ row.party_code }}</td>
              <td class="px-4 py-4">{{ row.attendance_count }}</td>
            </tr>
            <!-- Expanded rows -->
            <tr v-if="expandedRow === index" class="bg-white border-t">
              <td colspan="7" class="p-0">
                <div class="max-h-64 overflow-y-auto">
                  <table class="w-full text-xs border rounded">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="p-2 w-20">&nbsp;</th>
                      <th class="p-2 flex justify-center items-center">S.No</th>
                      <th class="p-2">Division No.</th>
                      <th class="p-2">Session No.</th>
                      <th class="p-2">Date | Time</th>
                      <th class="p-2">Day</th>
                      <th class="p-2 justify-center items-center">Mode</th>
                      <th class="p-2 flex justify-center items-center">Status</th>
                      <th class="p-2">Tab ID</th>
                      <th class="p-2">Device ID</th>
                      <th class="p-2">In Distance (m)</th>
                      <th class="p-2 flex justify-center items-center">Gate No.</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="border-b border-gray-200" v-for="(item, i) in row.attendance" :key="i">
                      <td class="p-2"></td>
                      <td class="p-2 flex justify-center items-center">{{ i + 1 }}</td>
                      <td class="p-2">{{ item.division_no || row.division_no }}</td>
                      <td class="p-2">{{ item.session_number }}</td>
                      <td class="p-2">{{ item.date }}</td>
                      <td class="p-2">{{ item.day }}</td>
                      <td class="p-2 justify-center items-center">{{ item.mode }}</td>
                      <td class="p-2 text-green-600 font-bold flex justify-center items-center">{{ item.status }}</td>
                      <td class="p-2">{{ item.tab_id }}</td>
                      <td class="p-2">{{ item.device_id }}</td>
                      <td class="p-2">{{ item.distance }}</td>
                      <td class="p-2 flex justify-center items-center">{{ item.gate_no }}</td>
                    </tr>
                  </tbody>
                  </table>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
        <tbody v-else class="bg-white text-xs">
          <tr class="cursor-pointer">
            <td class="px-4 py-2 text-center" colspan="7">
              <span>{{ pageMessage }}</span>              
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

  </div>
</template>

<script setup>
import { ref, reactive, computed,onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Loading, Button } from '@sds/oneui-common-ui';
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import Swal from 'sweetalert2';
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { fetchSessions, fetchHistoryFilters, fetchAttendanceHistory, fetchConsolidatedReport} from "@/services/attendanceService";
import { useI18n } from "vue-i18n";
import { exportToXlsx } from '@/utils/downloads';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import useLocalDate from "@/composables/useLocalDate";
import Tabs from "./Tabs.vue";

const { t, locale } = useI18n();
const isLoading = ref(true);
const oklabel = computed(() => t('btn_ok'));
const headingTitle = computed(() => t("menu.tab_1") + " " + t("menu.tab_1_2"));
const downloadMessage = ref('');
const showFilter = ref(true);
const showSort = ref(false);
const expandedRow = ref(null)

const sessionDetails = ref({});
const selectedFromSession = ref([]);
const selectedToSession = ref([]);

const filterFromSessions = ref([]);
const filterToSessions = ref([]);

const selectedMember = ref([]);
const filterMembers = ref([]);

const openCalendar = ref(false);
const selectedFromDate = ref('');
const selectedToDate = ref('');

const minFromDate = ref('');
const maxToDate = ref('');

const selectedOrderBy = ref('');
const selectedOrder = ref('asc');

const options = reactive({});
const pageData = ref([]);
const pageMessage = ref('');
const totalRecord = ref(0);
const pagination = ref({current_page:0, last_page:0});
const pagiShowLabel = computed( () => t("pagi_show_label"));
const pagiPrevLabel = computed( () => t("pagi_prev_label"));
const pagiNextLabel = computed( () => t("pagi_next_label"));
const pagiTotalLabel = computed(() => t("pagi_total_label", { current: pagination.value.current_page, total: pagination.value.last_page }));

function toggleDetails(rowId) {
  expandedRow.value = expandedRow.value === rowId ? null : rowId
}

const getAttendanceHistory = async () => {
  isLoading.value = true;
  pageData.value = [];
  pagination.value = {current_page:0, last_page:0};
  totalRecord.value = 0;
  if ( sessionDetails.value?.id < 1 ) {
    isLoading.value = false;
    pageMessage.value = computed( () =>t("no_record"));
    return false;
  }
  options.params = {...options.params, session_id: `${selectedFromSession.value.id}|${selectedToSession.value.id}`}
  const response = await fetchAttendanceHistory(sessionDetails.value?.id, options);
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

const getHistoryFiters = async () => {
  filterMembers.value = [];
  if ( sessionDetails.value?.id < 1 ) {
    return false;
  }
  const response = await fetchHistoryFilters(sessionDetails.value?.id);
  if ( response.isError == false ) {
    if ( response.success_code == 200 ) {
      const customData = response.data;
      if ( customData?.member_details && customData?.member_details.length ) {
        filterMembers.value = customData.member_details;
      }
    }
  } else {
    console.log("History filters is not loaded!");
  }
};

const getSessions = async () => {
  const response = await fetchSessions();
  if ( response.length ) {
    filterFromSessions.value = filterToSessions.value = response;
    selectedFromSession.value = selectedToSession.value = sessionDetails.value = response[0];
    minFromDate.value = selectedToSession.session_start_date;
    maxToDate.value = selectedToSession.session_end_date;
    getHistoryFiters();
  } else {
    console.log("Session is not loaded");
  }
  getAttendanceHistory();
};

watch(
  [selectedToSession,selectedFromSession], 
  ([toSession, fromSession], [oldToSession, oldFromSession]) => {
  options.params = {};
  if (toSession) {
    sessionDetails.value = toSession;
    filterFromSessions.value = filterToSessions.value.filter(item => item.session_number <= toSession.session_number);
    if ( selectedFromSession.value.session_number > toSession.session_number ) {
      selectedFromSession.value = toSession;
      minFromDate.value = toSession.session_start_date;
    }
    maxToDate.value = toSession.session_end_date;
  }
  if ( fromSession ) {
    minFromDate.value = fromSession.session_start_date;
  }
  selectedMember.value = [];
  selectedFromDate.value = '';
  selectedToDate.value = '';
  getHistoryFiters();
  getAttendanceHistory();  
});

onMounted( () => {
  getSessions();
});

watch([selectedFromDate, selectedToDate], ([newFrom, newTo], [oldFrom, oldTo]) => {
  handleDate();
});

const toggleCalendar = () => openCalendar.value = !openCalendar.value;
const handleCalendarClickOutside = () => {
  openCalendar.value = false;  
};

const downloadExcel = async () => {
  let index = 1;
  let customData = [];
  if ( options.params?.page ) {
    const {page, ...remain} = options.params;
    options.params = {...remain};
  }
  options.params = {
    ...options.params, 
    pagelimit: totalRecord.value
  };
  options.params = {...options.params, session_id: `${selectedFromSession.value.id}|${selectedToSession.value.id}`}
  const response = await fetchAttendanceHistory(sessionDetails.value?.id, options);
  if ( response.isError == false ) {
    if ( response.success_code == 200 && response.data.length ) {
      const apiDataLength = response.data.length;
      for(let i = 0; i < apiDataLength; i++ ) {
        let rowI = response.data[i];
        let attendance = rowI.attendance;
        for (let j=0; j < attendance.length; j++) {
          let rowJ = attendance[j];
          let record = {
            'SrNo': index++,
            'DivisionNo': rowJ.division_no || rowI.division_no,
            'SessionNo': rowJ.session_number,
            'MemberName': rowI.name,
            'StateCode': rowI.state_shortname,
            'PartyName': rowI.party_code,
            'NoOfDaysSigned': rowI.attendance_count,
            'DateTime': rowJ.date,
            'Day': rowJ.day,
            'Status': rowJ.status,
            'TabId': rowJ.tab_id,
            'DeviceId': rowJ.device_id,
            'DistanceInMeter': rowJ.distance,
            'GateNo': rowJ.gate_no
          };
          customData.push(record);
        }
      }
      exportToXlsx(customData, 'AttendanceHistory');
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

const consolidatedReportExcel = async () => {
  let index = 1;
  let customData = [];
  const options = {params: {session_number: sessionDetails.value?.session_number}};
  const response = await fetchConsolidatedReport(options);
  if ( response.isError == false ) {
    if ( response.success_code == 200 && response.data.length ) {
      const apiDataLength = response.data.length;
      for(let i = 0; i < apiDataLength; i++ ) {
        let row = response.data[i];
        let record = {
          'SrNo': index++,
          'SessionNumber': row.session_number,
          'DivisionNo': row.division_no,
          'MemberName': row.full_name,
          'PartyName': row.party_name,
          'StateName': row.state_name,
          'SittingDays': row.sitting_days,
          'DATE': row.DATE,
          'DAY': row.DAY,
          'STATUS': row.STATUS
        };
        customData.push(record);
      }
      exportToXlsx(customData, 'ConsolidatedReport');
      return true;
    }
  }
};

//End of download section

const handleMember = () => {
  let memberIds = selectedMember.value.map(item => item.id).join("|");
  if ( memberIds ) {
    options.params = {
      ...options.params, 
      member_id: memberIds
    };
  } else {
    if ( options.params?.member_id ) {
      const { member_id, ...remain } = options.params;
      options.params = { ...remain };
    }
  } 
  getAttendanceHistory();  
};

const handleDate = () => {
  let fromDate = selectedFromDate.value;
  let todate = selectedToDate.value;
  if ( fromDate && todate ) {    
    options.params = {
      ...options.params, 
      date: fromDate + '|' + todate
    };
    getAttendanceHistory();
  } 
};

const handleClearFilter = () => {
  selectedMember.value = [];
  selectedFromDate.value = '';
  selectedToDate.value = '';
  if ( options.params?.member_id ) {
    const { member_id, ...remain } = options.params;
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
    getAttendanceHistory();
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
  if ( selectedOrderBy.value ) {
    getAttendanceHistory();  
  }
};

//count page
const handlePageChange = newPage => {
  options.params = {
    ...options.params, 
    page: newPage
  };
  getAttendanceHistory();  
};

const handlePageSizeChange = newSize => {
  options.params = {
    ...options.params, 
    pagelimit: newSize
  };
  getAttendanceHistory();  
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


</script>
<style lang="css" scoped>
select {
  @apply rounded-sm text-neutral-500 cursor-pointer border-[1.5px] border-[#d1d5db] text-sm px-3 py-2 rounded focus:outline-none;
}

option {
  @apply text-neutral-800 cursor-pointer;
}

.multiselect__option {
  background-color: #f9f9f9;
}

.multiselect__option--highlight {
  background-color: #e0f2fe !important;
}

.multiselect__option--selected {
  background-color: #bae6fd !important;
}
</style>