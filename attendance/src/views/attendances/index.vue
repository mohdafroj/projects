<template>
  <Loading v-if="isLoading" />
  <div class="mx-auto">
    <!-- Session and Date Selector Section -->
    <div class="grid grid-col-1 md:grid-cols-2 flex items-center gap-4 mb-1 mt-[-10px]">
      <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 item-center">
        Session {{ selectedSession?.session_number }}<sup>th</sup>
        <span class="text-center bg-blue-100 text-blue-800 text-xs px-2 py-1 mr-2 rounded font-semibold">
            {{ selectedSession?.session_type || '' }}
        </span>
        <span class="text-sm font-semibold">| Duration: {{ sessionDuration }}</span>
      </h1>
      <div class="flex gap-4 md:justify-end">
        <!-- Session dropdown -->
        <div class="items-center gap-2 p-0">
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
                :options="sessionsList"
                :multiple="false"
                :searchable="true"
                :close-on-select="true"
                :clear-on-select="false"
              /> 
        </div>
        <div class="p-2">
          <!-- <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
            Select Date
          </label> -->
          <input 
            ref="inputDateRef"
            type="date" 
            v-model="selectedDate"
            :max="maxDate"
            :min="minDate"
            @change="onDateChange"
            @click="() => handleInputDateClick()"
            class="cursor-pointer text-sm w-40 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            aria-label="attendance-date"
          />
        </div>
      </div>
    </div>

    <div class="grid grid-col-1 md:grid-cols-2 gap-4">
      <!-- Left Half: Attendance Report -->
      <Card>
        <div class="flex flex-col items-center">
          <h2 class="text-2xl font-semibold">Present {{ selectedDate == today ? 'today' : ''}} </h2>
          <div class="relative w-56 h-56 mx-auto mt-4">
            <svg viewBox="0 0 200 200" class="w-full h-full">
              <path
                d="M55 165 A80 80 0 1 1 145 165"
                fill="none"
                stroke="green"
                stroke-width="25"
                stroke-linecap="butt"
              />
            </svg>
            <!-- Center number -->
            <div class="absolute inset-0 flex items-center justify-center text-6xl font-extrabold text-gray-800 dark:text-gray-200">
              {{ totalSigned }}
            </div>
          </div>
          <div class="text-center text-sm mt-2 flex justify-center gap-4">
            <p class="text-sm text-gray-700 dark:text-gray-300">
              As On: {{ formatDisplayDate(selectedDate) }} | {{ lastUpdatedTime || formatCurrentTime() }}
            </p>
          </div>
        </div>
      </Card>

      <!-- Right Half: Party Wise Attendance -->
      <Card>
        <div
          ref="elementRef"
          class="max-h-[325px] overflow-auto bg-white dark:bg-slate-800"
        >
          <div class="flex items-center justify-between px-4 pt-4">
            <h2 class="text-lg font-semibold">Party Wise Attendance Report</h2>
            <div class="flex items-center gap-2">
              <Icon
                icon="lucide:maximize-2"
                @click="toggleFullscreen"
                width="20"
                height="20"
                class="font-bold cursor-pointer"
              />
            </div>
          </div>
          <div class="mt-4 overflow-x-auto max-w-full">
            <div v-if="partyData && partyData.length > 0">
              <table class="min-w-full table-auto text-sm">
                <thead>
                  <tr class="text-gray-500 dark:text-gray-300 text-left">
                    <th class="px-4 py-2">Party Name</th>
                    <th class="px-2 py-2 text-center">Signed</th>
                    <th class="px-2 py-2 text-center">Not Signed</th>
                    <th class="px-2 py-2 text-center">Not Required</th>
                  </tr>
                </thead>
                <tbody class="text-gray-700 dark:text-gray-300 font-medium">
                  <tr
                    v-for="party in partyData"
                    :key="party.name"
                    class="border-t dark:border-gray-700"
                  >
                    <td class="px-4 py-3 align-top">
                      <div class="flex items-start gap-2 items-center">
                        <div
                          class="mt-1 w-5 h-5 rounded-full shrink-0"
                          :style="{ backgroundColor: `rgb(${party.party_color})` }"
                        ></div>
                        <div class="break-words">{{ party.name }}</div>
                      </div>
                    </td>
                    <td class="text-center">
                      <div
                        @click="openModal(party, 'signed')"
                        class="cursor-pointer bg-green-700 text-white px-2 py-0.5 rounded w-8 h-6 mx-auto"
                      >{{ party.signed }}</div>
                    </td>
                    <td class="text-center">
                      
                      <div class="bg-red-600 text-white px-2 py-0.5 rounded w-8 h-6 mx-auto cursor-pointer"
                      @click="openModal(party, 'notSigned')">
                        {{ party.calculatedDifference }}
                      </div>
                    </td>
                    <td class="text-center">
                      <div
                        @click="openModal(party, 'notRequired')"
                        class="cursor-pointer bg-pink-200 text-pink-800 px-2 py-0.5 rounded font-semibold w-8 h-6 mx-auto"
                      >{{ party.notRequired }}</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <!-- No data state for party table -->
            <div v-else class="flex items-center justify-center py-8">
              <div class="text-center">
                <div class="text-gray-400 mb-2">
                  <Icon icon="solar:users-group-two-rounded-broken" width="40" height="40" class="mx-auto" />
                </div>
                <p class="text-gray-500 text-base font-medium">No Record Found</p>
                <p class="text-gray-400 text-xs mt-1">No party attendance data available for the selected date or network error</p>
              </div>
            </div>
          </div>
        </div>
      </Card>
    </div>

    <!-- Dashboard Cards -->
    <div class="grid grid-col-1 md:grid-cols-5 gap-4 my-4">
      <DashboardCard
        subTitle="Total Members"
        :amount="totalMembers"
        description="Total Members of Rajya Sabha"
        iconName="solar:users-group-rounded-line-duotone"
        iconColor="text-violet-600"
        iconBgColor="bg-violet-100"
        @click="() => openSummaryModal('All', 'All Members')"
      />
      <DashboardCard
        subTitle="Signed"
        :amount="totalSigned"
        description="Members who have marked attendance"
        iconName="solar:user-check-broken"
        iconColor="text-green-600"
        iconBgColor="bg-green-100"
        @click="() => openSummaryModal('Signed', 'Members who have marked attendance')"
      />
      <!-- <DashboardCard
        subTitle="Scanned QR"
        :amount="totalNotSigned"
        description="Members who have scanned QR but not marked attendance"
        iconName="carbon:qr-code"
        iconColor="text-cyan-600"
        iconBgColor="bg-cyan-100"
        @click="() => openSummaryModal('Not Signed', 'Members Who scanned QR but not marked attendance')"
      /> -->
      <!-- iconName="solar:qr-code-line-duotone" -->
      
      <DashboardCard
        subTitle="Not Required"
        :amount="totalNotRequired"
        description="Members exempted from signing"
        iconName="solar:shield-user-outline"
        iconColor="text-blue-500"
        iconBgColor="bg-blue-100"
        @click="() => openSummaryModal('Not Required', 'Members exempted from signing')"
      />
      
      <DashboardCard
        subTitle="Not Signed"
        :amount="totalMembers - totalSigned - totalNotRequired - totalPaperSigned"
        description="Members who have not marked attendance"
        iconName="pepicons-pop:pen-circle-off"
        iconColor="text-rose-500"
        iconBgColor="bg-rose-100"
        @click="() => openSummaryModal('Not Signed', 'Members who have not marked attendance')"
      />
      
      <DashboardCard
        subTitle="Manual Signed"
        :amount="totalPaperSigned"
        description="Approval is needed for members who provide a manual signature"
        iconName="streamline-freehand:cash-payment-pen-signature"
        iconColor="text-teal-500"
        iconBgColor="bg-teal-100"
        @click="() => openSummaryModal('Paper Signed', 'Approval is needed for members who provide a manual signature')"
      />
      
      <DashboardCard
        v-if="requestCount.leave"
        subTitle="Leave Request"
        :amount="requestCount.leave"
        description="Leave request applied by members."
        iconName="codicon:git-pull-request-new-changes"
        iconColor="text-green-600"
        iconBgColor="bg-green-100"
      />
      
      <DashboardCard
        v-if="requestCount.regularization"
        subTitle="Regularization Request"
        :amount="requestCount.regularization"
        description="Regularization request applied by members."
        iconName="codicon:git-pull-request-new-changes"
        iconColor="text-green-600"
        iconBgColor="bg-green-100"
      />
      
    </div>

    <!-- Today Attendance Section -->
     <Attendences-report :date="selectedDate" :isPublished="isPublished" :parentLoading="isLoading"></Attendences-report>
    <!-- <div class="p-4 mt-0">
      <TableOptions
        tableTitle="Today's Attendance"
        :addButtons="[]"
        :columns="columnsList"
        :data="members"
        :excludeSearch="['srNo', 'submitted', 'status']"
        :excludeSort="['srNo', 'submitted']"
        :excludeFilter="['srNo', 'submitted']"
        @filter="handleFilter"
        @search="handleSearch"          
        @clearSearch="handleSearchReset" 
        @sort="handleSort"
        @clearSort="handleSortReset"
      />
      <div class="mt-0 overflow-x-auto">
        <div v-if="paginatedData && paginatedData.length > 0">
          <DataTable
            :data="paginatedData"
            :customColumnOrder="columnsList"
            :show-open="false"
            :show-actions="false"
            :currentSort="currentSort"
            @sort="handleSort"
          >
            <template #cell-srNo="{ cell }">
              <div class="text-center">{{ cell.value }}</div>
            </template>
            <template #cell-divisionNo="{ cell }">
              <div class="text-center">{{ cell.value }}</div>
            </template>
            <template #cell-name="{ cell }">
              <div class="flex items-center justify-start w-full text-left">
                <img
                  :src="cell.row.imageUrl"
                  alt="Member Photo"
                  class="w-8 h-8 rounded-full mr-2 object-cover"
                />
                <span>{{ cell.value }}</span>
              </div>
            </template>
            <template #cell-partyName="{ cell }">
              <div class="text-left">
                <span>{{ cell.value }}</span>
              </div>
            </template>
            <template #cell-state="{ cell }">
              <div class="text-center">{{ cell.value }}</div>
            </template>
            <template #cell-submitted="{ cell }">
              <div class="text-center">{{ cell.value }}</div>
            </template>
            <template #cell-status="{ cell }">
              <div class="text-center">
                <span
                  v-if="cell.value === 'Signed'"
                  class="inline-flex items-center px-3 py-0-5 text-md font-semibold mr-2"
                >
                 
                  <span class=" bg-green-100 text-green-700 px-3 py-1 rounded-xl">{{ cell.value }}</span>

                </span>
                <span
                  v-else-if="cell.value === 'Not Signed'"
                  class="inline-flex items-center px-3 py-0-5 text-md font-semibold mr-2"
                >
                 
                  <span class=" bg-red-100 text-red-700 px-3 py-1 rounded-xl">{{ cell.value }}</span>

                </span>
                <span
                  v-else-if="cell.value === 'Not Required'"
                  class="inline-flex items-center px-3 py-0-5 text-md font-semibold mr-2"
                >
                  
                  <span class=" bg-pink-100 text-pink-700 px-3 py-1 rounded-xl">{{ cell.value }}</span>
                </span>
              </div>
            </template>
          </DataTable>
        </div>
        
        <div v-else class="flex items-center justify-center py-12 bg-white rounded-lg border">
          <div class="text-center">
            <div class="text-gray-400 mb-4">
              <Icon icon="solar:file-text-broken" width="64" height="64" class="mx-auto" />
            </div>
            <p class="text-gray-500 text-xl font-medium">No Record Found</p>
            <p class="text-gray-400 text-sm mt-2">No attendance records found for the selected date or network error</p>
          </div>
        </div>
      </div>
      <PaginationSelect
        :currentPage="currentPage"
        :totalPages="totalPages"
        :pageSize="pageSize"
        @update:currentPage="handlePageChange"
        @update:pageSize="handlePageSizeChange"
      />
    </div> -->
  </div>

  <!-- Modals -->
  <PartyAttendanceModal
    v-model="modalVisible"
    :members="selectedMembers"
    :modalTitle="modalTitle"
    :modalSubtitle="modalSubtitle"
  />
  <AttendanceSummaryModal
    v-model="showSummaryModal"
    :members="summaryMembers"
    :modalTitle="summaryModalTitle"
    :modalSubtitle="summaryModalSubtitle"
    :totalsSummary="totalsSummary"
    :selectedDate="selectedDate"
    :sessionNumber="selectedSession?.session_number"
  />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { Card, DashboardCard } from "@sds/oneui-common-ui";
// import TableOptions from "../../components/DatatableDashboard/TableOptions.vue";
// import DataTable from "../../components/DatatableDashboard/DataTable.vue";
// import PaginationSelect from "../../components/DatatableDashboard/PaginationSelect.vue";
import { Loading } from '@sds/oneui-common-ui'; 
import { fetchDailyAttendance, fetchRecordCount, fetchDailyPartywiseAttendance, fetchSessions } from "@/services/attendanceService.js";
import { Icon } from "@iconify/vue";
import PartyAttendanceModal from "./PartyAttendanceModal.vue";
import AttendanceSummaryModal from './AttendanceSummaryModal.vue';
import { useFullscreen } from "@/composables/useFullscreen";
import Swal from 'sweetalert2';
import AttendencesReport from "./report/list/attendences-report.vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import { useRoute, useRouter } from 'vue-router';
import useLocalDate from "@/composables/useLocalDate";
import { useApiStore } from "@/store/apiData";
import { useThemeSettingsStore } from "@/store/themeSettings";

const themeSettingsStore = useThemeSettingsStore();
const apiStore = useApiStore();


const { elementRef, toggleFullscreen } = useFullscreen();
const router = useRouter();
const route = useRoute();

// Socket related refs
const socket = ref(null);
const inputDateRef = ref(null)
// Search and sort refs
const currentSearch = ref({ key: "", value: "" });
const currentSort = ref({ key: "", order: "asc" });

// Loading state
const isLoading = ref(true);
const notifyMessage = ref('');
// Session and Date selection
const sessionsList = ref([]);
const selectedSession = ref([]);
const selectedSessionId = ref(apiStore.session?.id);
const today = new Date().toISOString().split('T')[0];
const selectedDate = ref(today);
const sessionDuration = ref('');

const maxDate = computed(() => {
  let d = new Date(selectedSession.value?.session_end_date);
  if (isNaN(d)) { // handle invalid date
    d = new Date();
  } 
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const minDate = computed(() => {
  let d = new Date(selectedSession.value?.session_start_date);
  if (isNaN(d)) { // handle invalid date
    d = new Date();
    d.setFullYear(d.getFullYear() - 6); // Subtract 6 years
  } 
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

// Data refs
const totalAttendance = ref(0);
const totalSigned = ref(0);
const totalNotSigned = ref(0);
const totalNotRequired = ref(0);
const totalPaperSigned = ref(0);
const totalMembers = ref(0);
const lastUpdatedTime = ref("");
const members = ref([]);
const partyData = ref([]);
const isPublished = ref(0);
const totalsSummary = computed(() => {
  return {
    manual: totalPaperSigned.value,
    notSigned: totalMembers.value - totalSigned.value - totalNotRequired.value - totalPaperSigned.value,
    notRequired: totalNotRequired.value, 
    signed: totalSigned.value, 
    all: totalMembers.value
  }
})
// Modal states
const modalVisible = ref(false);
const selectedMembers = ref([]);
const modalTitle = ref("");
const modalSubtitle = ref("");

const showSummaryModal = ref(false);
const summaryModalTitle = ref("");
const summaryModalSubtitle = ref("");
const summaryMembers = ref([]);

// Pagination
const currentPage = ref(1);
const pageSize = ref(10);
const currentFilters = ref({});

const handleInputDateClick = () => {
  inputDateRef.value.showPicker();
}

watch(selectedSession, async (newVal, oldVal) => {
  const currentDate = new Date();
  selectedDate.value = currentDate.toISOString().split('T')[0];
  selectedSessionId.value = newVal?.id || 0;

  if ( newVal?.session_start_date && newVal?.session_end_date ) {
    sessionDuration.value = `${ useLocalDate(newVal?.session_start_date, 'dd-mm-yyyy') } to ${useLocalDate(newVal?.session_end_date, 'dd-mm-yyyy')}`;
    const end = new Date(newVal.session_end_date);
    if ( currentDate > end ) {
      selectedDate.value = end.toISOString().split("T")[0];
    }
  }
  loadAttendanceData();
});

watch(themeSettingsStore.notification, (newData) => {
  notifyMessage.value = '';
  if ( newData.message?.type == 'attendance_marked' ) {
    notifyMessage.value = newData.message?.data.name || 'Anonymous user';
    showAttendanceToast();
  }
}, {immediate: true})

// Refresh attendance data and show toast
const refreshAttendanceData = async () => {
  try {
    // Fetch fresh data
    const response = await fetchDailyAttendance({
      session_id: selectedSessionId.value,
      date: selectedDate.value
    });
    
    if (response.success_code === 200 && response.data) {
      const apiData = response.data;
      
      // Update all the reactive data
      totalAttendance.value = apiData.attendanceTotals?.totalAttendance || 0;
      totalSigned.value = apiData.attendanceTotals?.totalSigned || 0;
      totalNotSigned.value = apiData.attendanceTotals?.totalNotSigned || 0;
      totalNotRequired.value = apiData.attendanceTotals?.totalNotRequired || 0;
      totalPaperSigned.value = apiData.attendanceTotals?.papersigncount || 0;
      lastUpdatedTime.value = apiData.attendanceTotals?.last_updated_at_time || "";      
      isPublished.value = apiData.is_published || 0;

      // Update members data
      members.value = (apiData.members || []).map((member, index) => ({
        srNo: index + 1,
        divisionNo: member.division_no,
        name: member.name,
        state: member.state,
        submitted: member.attendance_time,
        status: member.is_signed,
        partyName: member.party_name,
        imageUrl: member.profile_photo || "https://via.placeholder.com/40",
      }));
      
      // Update party data
      partyData.value = (apiData.party_attendance_data || [])
        .map((party, index) => ({
          name: party.party_name,
          code: party.party_code,
          party_color: party.party_color,
          signed: party.signed,
          notSigned: party.not_signed,
          notRequired: party.not_required,
          totalPartyMembers: party.total_members,
          // calculatedDifference: (party.total_members || 0) - (party.signed || 0) - (party.not_signed || 0) - (party.not_required || 0),
          calculatedDifference: party.not_signed,
          total: party.signed + party.not_signed + party.not_required,
        }))
        .sort((a, b) => b.total - a.total);
      
      // Update total members
      totalMembers.value = (apiData.party_attendance_data || []).reduce((sum, party) => {
        return sum + (party.total_members || 0);
      }, 0);
      // Show toast notification with attendance totals data
    }
    
  } catch (error) {
    console.error('Error refreshing attendance data:', error);
  }
};

// Show SweetAlert2 toast for attendance updates
const showAttendanceToast = () => {
  // Extract data from attendanceTotals
  const currentTime = new Intl.DateTimeFormat("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: true,
    timeZone: "Asia/Kolkata"
  }).format(new Date());
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: 'Attendance Marked!',
    text: `${notifyMessage.value} marked his/her attendance at ${currentTime}`,
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });
  refreshAttendanceData();
};

// Party attendance modal
const openModal = async (party, statusKey) => {
  const statusMap = {
    signed: "Signed",
    notSigned: "Not Signed",
    notRequired: "Not Required",
  };
  const apiStatus = statusMap[statusKey];

  modalTitle.value = party.name;
  modalSubtitle.value = `${apiStatus} - ${party[statusKey]}`;

  try {
    isLoading.value = true;
    const response = await fetchDailyPartywiseAttendance({
      session_id: selectedSessionId.value,
      date: selectedDate.value,
      party_code: party.code,
    });

    if (response.success_code === 200 && response.data) {
      selectedMembers.value = response.data
        .filter((member) => member.is_signed === apiStatus)
        .map((member, index) => ({
          srNo: index + 1,
          divisionNo: member.division_no,
          name: member.name,
          state: member.state,
          submitted: member.attendance_time,
          status: member.is_signed,
          imageUrl: member.profile_photo || "https://via.placeholder.com/40",
        }));
    } else {
      selectedMembers.value = [];
    }
  } catch (error) {
    console.error("Error fetching party-wise attendance:", error);
    selectedMembers.value = [];
  } finally {
    isLoading.value = false;
    modalVisible.value = true;
  }
};

// Summary modal
const openSummaryModal = (filter, title) => {
  if ( filter == 'Paper Signed' ) {
    router.push({
      name: 'PaperSigned'
    });
    return true;
  }
  summaryModalTitle.value = title;
  if (filter === "All") {
    summaryMembers.value = members.value.sort((a, b) => a.divisionNo - b.divisionNo); //Asc sort by divisionNo
    summaryModalSubtitle.value = `Total: ${members.value.length} - Date: ${useLocalDate(selectedDate.value, 'dd-mm-yyyy')}`;
  } else {
    summaryMembers.value = members.value.filter((m) => m.status === filter).sort((a, b) => a.divisionNo - b.divisionNo);
    summaryModalSubtitle.value = `Total ${filter}: ${summaryMembers.value.length} - Date: ${useLocalDate(selectedDate.value, 'dd-mm-yyyy')}`;
  }
  showSummaryModal.value = true;
};

// Load sessions
const loadSessions = async () => {
  try {
    const sessions = await fetchSessions();
    sessionsList.value = sessions;
    selectedSession.value = sessions[0] || {};
  } catch (error) {
    console.error("Error loading sessions:", error);
  }
};

const requestCount = ref({leave:0, regularization:0});
const getRecordCount = async () => {
    requestCount.value = {leave:0, regularization:0};
    const response = await fetchRecordCount({
      date: selectedDate.value
    });

    if (response.success_code === 200 && response.data) {
      requestCount.value = {leave: response.data.leaves, regularization:response.data.regularization};
    }
}

// Load attendance data
const loadAttendanceData = async () => {
  getRecordCount();
  try {
    isLoading.value = true;
    const response = await fetchDailyAttendance({
      session_id: selectedSessionId.value,
      date: selectedDate.value
    });

    if (response.success_code === 200 && response.data) {
      const apiData = response.data;

      // Set totals
      totalAttendance.value = apiData.attendanceTotals?.totalAttendance || 0;
      totalSigned.value = apiData.attendanceTotals?.totalSigned || 0;
      totalNotSigned.value = apiData.attendanceTotals?.totalNotSigned || 0;
      totalNotRequired.value = apiData.attendanceTotals?.totalNotRequired || 0;
      totalPaperSigned.value = apiData.attendanceTotals?.papersigncount || 0;
      lastUpdatedTime.value = apiData.attendanceTotals?.last_updated_at_time || "";
      isPublished.value = apiData.is_published || 0;

      // Map members data
      members.value = (apiData.members || []).map((member, index) => ({
        srNo: index + 1,
        divisionNo: member.division_no,
        name: member.name,
        state: member.state,
        submitted: member.attendance_time,
        status: member.is_signed,
        partyName: member.party_name,
        imageUrl: member.profile_photo || "https://via.placeholder.com/40",
      }));

      const colors = [
        "bg-orange-500", "bg-sky-400", "bg-indigo-300",
        "bg-pink-200", "bg-red-500", "bg-amber-500", "bg-teal-400",
        "bg-purple-400", "bg-lime-500", "bg-cyan-500", "bg-fuchsia-400",
      ];

      // Map party data
      partyData.value = (apiData.party_attendance_data || [])
        .map((party, index) => ({
          name: party.party_name,
          code: party.party_code,
          party_color: party.party_color,
          signed: party.signed,
          notSigned: party.not_signed,
          notRequired: party.not_required,
          totalPartyMembers: party.total_members,
          // calculatedDifference: (party.total_members || 0) - (party.signed || 0) - (party.not_signed || 0) - (party.not_required || 0),
          calculatedDifference: party.not_signed,
          total: party.signed + party.not_signed + party.not_required,
          color: colors[index % colors.length],
        }))
        .sort((a, b) => b.total - a.total);

      // Calculate total members from all parties
      totalMembers.value = (apiData.party_attendance_data || []).reduce((sum, party) => {
        return sum + (party.total_members || 0);
      }, 0);
    } else {
      resetData();
    }
  } catch (error) {
    console.error("Error fetching attendance data:", error);
    resetData();
  } finally {
    isLoading.value = false;
  }
};

// Reset data helper
const resetData = () => {
  totalAttendance.value = 0;
  totalSigned.value = 0;
  totalNotSigned.value = 0;
  totalNotRequired.value = 0;
  totalPaperSigned.value = 0;
  totalMembers.value = 0;
  lastUpdatedTime.value = "";
  members.value = [];
  partyData.value = [];
};

// Event handlers
const onDateChange = () => {
  loadAttendanceData();

  // selectedDate.value = 
  //    if (selectedDate.value) {
  //       attendanceReportlist(selectedDate.value);
  //     }
};

// Computed properties
const columnsList = computed(() => [
  { key: "srNo", label: "Sr No.", type: "Number" },
  { key: "divisionNo", label: "Division No.", type: "Number" },
  { key: "name", label: "Member's Name", type: "String" },
  { key: "partyName", label: "Party Name", type: "String" },
  { key: "state", label: "State", type: "String" },
  { key: "submitted", label: "Attendance Time", type: "Date" },
  { key: "status", label: "Attendance Status", type: "String" },
]);

const filteredMembers = computed(() => {
  let result = [...members.value];
  
  // Apply search filter
  if (currentSearch.value.key && currentSearch.value.value) {
    const searchKey = currentSearch.value.key;
    const searchValue = currentSearch.value.value.toLowerCase();
    
    result = result.filter((member) => {
      const memberValue = getNestedValue(member, searchKey);
      if (memberValue === null || memberValue === undefined) return false;
      
      const stringValue = String(memberValue).toLowerCase();
      return stringValue.includes(searchValue);
    });
  }
  
  // Apply column filters
  Object.keys(currentFilters.value).forEach((key) => {
    const filterValue = currentFilters.value[key];
    if (Array.isArray(filterValue) && filterValue.length > 0) {
      result = result.filter((member) => filterValue.includes(member[key]));
    }
  });
  
  // Apply sorting
  if (currentSort.value.key) {
    const sortKey = currentSort.value.key;
    const sortOrder = currentSort.value.order;

    result = result.sort((a, b) => {
      const aValue = getNestedValue(a, sortKey);
      const bValue = getNestedValue(b, sortKey);

      // Handle null/undefined values
      if (aValue === null || aValue === undefined) return 1;
      if (bValue === null || bValue === undefined) return -1;

      // Convert to strings for comparison if they're not numbers
      let aCompare = aValue;
      let bCompare = bValue;

      // Check if both values are numbers
      const aIsNumber = !isNaN(Number(aValue)) && isFinite(aValue);
      const bIsNumber = !isNaN(Number(bValue)) && isFinite(bValue);

      if (aIsNumber && bIsNumber) {
        aCompare = Number(aValue);
        bCompare = Number(bValue);
      } else {
        // Convert to lowercase strings for case-insensitive comparison
        aCompare = String(aValue).toLowerCase();
        bCompare = String(bValue).toLowerCase();
      }

      if (sortOrder === 'asc') {
        return aCompare < bCompare ? -1 : aCompare > bCompare ? 1 : 0;
      } else {
        return aCompare > bCompare ? -1 : aCompare < bCompare ? 1 : 0;
      }
    });
  }
  
  return result;
});

const totalPages = computed(() => {
  return Math.ceil(filteredMembers.value.length / pageSize.value);
});

// const handleSearch = ({ key, value }) => {
//   currentSearch.value = { key, value };
//   currentPage.value = 1; // Reset to first page when searching
// };

// Handle search reset events
// const handleSearchReset = () => {
//   currentSearch.value = { key: "", value: "" };
//   currentPage.value = 1;
// };

// const handleSort = ({ key, order }) => {
//   currentSort.value = { key, order };
//   currentPage.value = 1; // Reset to first page when sorting
// };

// const handleSortReset = () => {
//   currentSort.value = { key: "", order: "asc" };
//   currentPage.value = 1;
// };

// const getNestedValue = (obj, path) => {
//   return path.split('.').reduce((acc, key) => acc?.[key], obj);
// };

// const paginatedData = computed(() => {
//   const start = (currentPage.value - 1) * pageSize.value;
//   const end = start + pageSize.value;
//   return filteredMembers.value.slice(start, end);
// });

// Pagination handlers
// const handlePageChange = (newPage) => {
//   currentPage.value = newPage;
// };

const handlePageSizeChange = (newPageSize) => {
  pageSize.value = newPageSize;
  currentPage.value = 1;
};

const handleFilter = (filters) => {
  currentFilters.value = filters;
  currentPage.value = 1;
};

// Helper functions
const formatDisplayDate = (dateStr) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: '2-digit' 
  });
};

const formatCurrentTime = () => {
  const now = new Date();
  let hours = now.getHours();
  const minutes = String(now.getMinutes()).padStart(2, "0");
  const ampm = hours >= 12 ? "PM" : "AM";
  hours = hours % 12;
  hours = hours ? hours : 12;
  return `${String(hours).padStart(2, "0")}:${minutes} ${ampm}`;
};

// Lifecycle
onMounted(async () => {
  await loadSessions();
  await loadAttendanceData();
});

onUnmounted(() => {
  // Clean up socket connection
  if (socket.value) {
    socket.value.close();
  }
});
</script>