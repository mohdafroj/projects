<template>
  <Loading v-if="isLoading" />
  <div class="mx-auto ">
    <div class="flex justify-between items-center gap-4 mb-1 mt-[-10px]">
      <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Committee</h1>
      <div class="flex items-center gap-4 ml-auto">
      <div>
        <router-link :to="{name: 'CommitteeAttendance'}" class="text-blue-500 hover:underline">
          <Badge text="Committee" type="success" />
        </router-link>
        <router-link :to="{name: 'attendance'}" class="text-blue-500 hover:underline">
          <Badge text="Attendance" type="info" />
        </router-link>
      </div>
      <div class="flex flex-col">
        <Multiselect
          v-model="selectedCommittees"
          selectLabel=""
          deselectLabel=""
          placeholder="Search and select committee..."
          class="text-sm text-blue-500 rounded h-fit custom-multiselect"
          openDirection="below"
          track-by="id" 
          label="name"
          :showNoResults="false"
          :showNoOptions="false"
          :options="committeeData"
          :multiple="false"
          :searchable="true"
          :close-on-select="true"
          :clear-on-select="false"
        />
      </div>

      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <!-- Left Half: Attendance Report -->
      <Card >
        <div class="flex flex-col items-center">
        <h3 class="text-2xl font-semibold ">Present today</h3>
        <div class="relative w-56 h-56 mx-auto mt-4">
    <svg viewBox="0 0 200 200" class="w-full h-full">
      <!-- Circular green arc from ~135deg to 45deg -->
      <path
        d="M55 165 A80 80 0 1 1 145 165"
        fill="none"
        stroke="green"
        stroke-width="25"
        stroke-linecap="butt"
      />
    </svg>

    <!-- Center number -->
    <div
      class="absolute inset-0 flex items-center justify-center text-6xl font-extrabold text-gray-800 dark:text-gray-200"
    >
      {{ totalAttendance }}
    </div>
  </div>



        
        <div class="text-center text-sm mt-2 flex justify-center gap-4">
          <p class="text-sm text-gray-700 dark:text-gray-300">As On: {{ formatDisplayDate(selectedDate) }} | 11:00 AM</p>
         
        </div>

      </div>
      </Card>

      <!-- Right Half: party -->
      <div>
        <div class="dark:bg-slate-800">          
          <div class="grid grid-cols-2 gap-4 my-0">
            
      <DashboardCard
        subTitle="Total Member"
        :amount="totalAttendance"
        description="Total Members of Rajya Sabha "
        iconName="solar:users-group-rounded-line-duotone"
        iconColor="text-violet-600"
        iconBgColor="bg-violet-100"
        @click="() => openSummaryModal('All', 'All Members')"
        
      />
      <!-- :onClick="totalMembers" -->
      <DashboardCard
        subTitle="Signed"
        :amount="totalSigned"
        description="Members who have marked attandance"
        iconName="solar:user-check-broken"
        iconColor="text-green-600"
        iconBgColor="bg-green-100"
        @click="() => openSummaryModal('Signed', 'Members Who Signed')"
      />
      <DashboardCard
        subTitle="Not Signed"
        :amount="totalNotSigned"
        description="Members who have notmarhed attandance"
        iconName="solar:user-cross-broken"
        iconColor="text-red-500"
        iconBgColor="bg-red-100"
        @click="() => openSummaryModal('Not Signed', 'Members Who Did Not Sign')"
      />
      <DashboardCard
        subTitle="Not Required"
        :amount="totalNotRequired"
        description="Members who do not required to sign"
        iconName="solar:shield-user-outline"
        iconColor="text-blue-500"
        iconBgColor="bg-blue-100"
        @click="() => openSummaryModal('Not Required', 'Members Not Required to Sign')"
      />
          </div>
        </div>
      </div>
    </div>
    <!-- Today Attendance Section -->
    <div class="p-4 mt-0">
      <TableOptions
        tableTitle="Today's Attendance"
        :addButtons="[]"
        :columns="columnsList"
        :data="members"
        :excludeSearch="[]"
        :excludeSort="[]"
        :excludeFilter="[]"
        @filter="handleFilter"
        @sort="handleSort"
        @clearSort="handleClearSort"
        @search="handleSearch"
        @clearSearch="handleClearSearch"
      />
      <div class="mt-0 overflow-x-auto">
        <DataTable
          :data="paginatedData"
          :customColumnOrder="columnsList"
          :show-open="false"
          :show-actions="false"
        >
          <template #cell-srNo="{ cell }">
            <div class="text-center">{{ cell.value }}</div>
          </template>
          <template #cell-divisionNo="{ cell }">
            <div class="text-center">{{ cell.value }}</div>
          </template>
          <!-- <div class="flex items-center justify-center"> -->
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
                class="inline-flex items-center border border-gray-200 rounded-full px-3 py-0.5 text-xs font-medium text-gray-700 bg-gray-50 mr-2"
                ><span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span
                ><span class="text-green-500">{{ cell.value }}</span></span
              >
              <span
                v-else-if="cell.value === 'Not Signed'"
                class="inline-flex items-center border border-gray-200 rounded-full px-3 py-0.5 text-xs font-medium text-gray-700 bg-gray-50 mr-2"
                ><span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span
                ><span class="text-red-500">{{ cell.value }}</span></span
              >
              <span
                v-else-if="cell.value === 'Not Required'"
                class="inline-flex items-center border border-gray-200 rounded-full px-3 py-0.5 text-xs font-medium text-gray-700 bg-gray-50 mr-2"
                ><span class="w-2 h-2 bg-blue-500 rounded-full mr-1"></span
                ><span class="text-blue-500">{{ cell.value }}</span></span
              >
            </div>
          </template>
          <!-- <template #cell-after="{row}">
              <div>{{ row }}</div>
            </template> -->
        </DataTable>
      </div>
      <PaginationSelect
        :currentPage="currentPage"
        :totalPages="totalPages"
        :pageSize="pageSize"
        @update:currentPage="handlePageChange"
        @update:pageSize="handlePageSizeChange"
      />
    </div>
  </div>
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
/>

</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Card, DashboardCard, Badge } from "@sds/oneui-common-ui";
import TableOptions from "../../components/Datatable/TableOptions.vue";
import DataTable from "../../components/Datatable/DataTable.vue";
import PaginationSelect from "../../components/Datatable/PaginationSelect.vue";
import VueApexCharts from "vue3-apexcharts";
import Loading from "@/components/Loding.vue" 

// import { fetchDailyAttendance } from "@/services/attendanceService.js";
import { fetchDailyPartywiseAttendance } from "@/services/attendanceService.js";
import { fetchCommitteeAttendances } from "@/services/committeeServices";
import { Icon } from "@iconify/vue";
import PartyAttendanceModal from "./PartyAttendanceModal.vue";
import AttendanceSummaryModal from './AttendanceSummaryModal.vue'
import { useFullscreen } from "@/composables/useFullscreen";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import { useApiStore } from "@/store/apiData";

const apiStore = useApiStore();

const { elementRef, toggleFullscreen } = useFullscreen();

// Loading state
const isLoading = ref(true);
const committeeId = ref(20);
const selectedCommittees = ref([]);
const committeeData = ref([{id:20, name:"Selected Committee"}]);

// Session and Date selection
const selectedSessionId = ref(apiStore.session?.id);
const selectedDate = ref(new Date().toISOString().split('T')[0]);

// @ partyattendance modal
const modalVisible = ref(false);
const selectedMembers = ref([]);
const modalTitle = ref("");
const modalSubtitle = ref("");

const openModal = async (party, statusKey) => {
  // Map statusKey to API status values
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
    // Call fetchDailyPartywiseAttendance with parameters
    const response = await fetchDailyPartywiseAttendance({
      session_id: selectedSessionId.value,
      date: selectedDate.value,
      party_code: party.code,
    });

    if (response.success_code === 200 && response.data) {
      selectedMembers.value = response.data
        .filter((member) => member.status === apiStatus) // Match exact status
        .map((member, index) => ({
          srNo: index + 1,
          divisionNo: member.division_no,
          name: member.name,
          state: member.state,
          submitted: member.attendance_time,
          status: member.status,
          imageUrl: member.profile_photo || "https://via.placeholder.com/40",
        }));
    } else {
      selectedMembers.value = [];
      console.error("No data returned from fetchDailyPartywiseAttendance");
    }
  } catch (error) {
    console.error("Error fetching party-wise attendance:", error);
    selectedMembers.value = [];
  } finally {
    isLoading.value = false;
    modalVisible.value = true;
  }
};
// partyattendance modal


// @ AttendanceSummaryModal


const showSummaryModal = ref(false);
const summaryModalTitle = ref("");
const summaryModalSubtitle = ref("");
const summaryMembers = ref([]);

const openSummaryModal = (filter, title) => {
  summaryModalTitle.value = title;
  if (filter === "All") {
    summaryMembers.value = members.value;
    summaryModalSubtitle.value = `Total: ${members.value.length}`;
  } else {
    summaryMembers.value = members.value.filter((m) => m.status === filter);
    summaryModalSubtitle.value = `Total ${filter}: ${summaryMembers.value.length}`;
  }
  showSummaryModal.value = true;
};

// AttendanceSummaryModal

const ApexChart = VueApexCharts;

defineExpose({ ApexChart });

const totalAttendance = ref(0);
const totalSigned = ref(0);
const totalNotSigned = ref(0);
const totalNotRequired = ref(0);
const circumference = 2 * Math.PI * 40;
const allMembers = ref([]);
const members = ref([]);

// Load attendance data
const loadAttendanceData = async () => {
  try {
    allMembers.value = [];
    members.value = [];
    isLoading.value = true;
    let options = {params:{committee_id:committeeId.value}}
    const response = await fetchCommitteeAttendances(options);
    if ( response.isError ) {
      return false;
    } 
    const apiData = response.data;

    totalAttendance.value = apiData.totals.attendance;
    totalSigned.value = apiData.totals.signed;
    totalNotSigned.value = apiData.totals.notSigned;
    totalNotRequired.value = apiData.totals.notRequired;

    members.value = apiData.members.map((member, index) => ({
      srNo: index + 1,
      divisionNo: member.division_no,
      name: member.name,
      state: member.state,
      submitted: member.attendance_time,
      status: member.status,
      imageUrl: member.profile_photo || "https://via.placeholder.com/40",
    }));
    allMembers.value = members.value;
  } catch (error) {
    console.error("Error fetching attendance data:", error);
  } finally {
    isLoading.value = false;
  }
};

const handleSort = ({ key, order }) => {
  members.value = [...members.value].sort((a, b) => {
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

const handleClearSort = () => {
  members.value = [...allMembers.value]
}

const handleSearch = ({ key, value }) => {
  if (!value || value.trim() === '') {
    members.value = allMembers.value
    currentPage.value = 1
    return
  }

  const searchValue = value.toLowerCase()
  members.value = allMembers.value.filter(item => {
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

const handleClearSearch = () => {
  members.value = allMembers.value
  currentPage.value = 1
}

onMounted(async () => {
  await loadAttendanceData();
});

const dashOffset = computed(() => {
  return (
    circumference - (totalSigned.value / totalAttendance.value) * circumference
  );
});

const notSignedOffset = computed(() => {
  return (
    circumference -
    ((totalSigned.value + totalNotSigned.value) / totalAttendance.value) *
      circumference
  );
});

const notRequiredOffset = computed(() => {
  return (
    circumference -
    ((totalSigned.value + totalNotSigned.value + totalNotRequired.value) /
      totalAttendance.value) *
      circumference
  );
});

const columnsList = computed(() => [
  { key: "srNo", label: "Sr No.", type: "Number" },
  { key: "divisionNo", label: "Division No.", type: "Number" },
  { key: "name", label: "Member's Name", type: "String" },
  { key: "state", label: "State", type: "String" },
  { key: "submitted", label: "Attendance Time", type: "Date" },
  { key: "status", label: "Attendance Status", type: "String" },

]);

const currentPage = ref(1);
const pageSize = ref(10000000);
const currentFilters = ref({});

const totalPages = computed(() => {
  const pages = Math.ceil(filteredMembers.value.length / pageSize.value);
  return pages;
});

const filteredMembers = computed(() => {
  let result = [...members.value];
  Object.keys(currentFilters.value).forEach((key) => {
    const filterValue = currentFilters.value[key];
    if (Array.isArray(filterValue) && filterValue.length > 0) {
      result = result.filter((member) => filterValue.includes(member[key]));
    }
  });
  return result;
});

const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return filteredMembers.value.slice(start, end);
});

const handlePageChange = (newPage) => {
  currentPage.value = newPage;
};

const handlePageSizeChange = (newPageSize) => {
  pageSize.value = newPageSize;
  currentPage.value = 1;
};

const handleFilter = (filters) => {
  currentFilters.value = filters;
  currentPage.value = 1;
};

//   chart
const chartSeries = computed(() => [
  totalSigned.value,
  totalNotSigned.value,
  totalNotRequired.value,
]);

const semiCircleOptions = {
  chart: {
    type: 'radialBar',
    offsetY: -20,
    sparkline: {
      enabled: true
    }
  },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: {
        size: '70%',
        background: 'transparent'
      },
      track: {
        background: '#e0e0e0',
        strokeWidth: '100%',
      },
      dataLabels: {
        name: {
          show: false
        },
        value: {
          offsetY: 10,
          fontSize: '36px',
          fontWeight: 700,
          color: '#111827',
          formatter: () => `${totalAttendance.value}`
        }
      }
    }
  },
  fill: {
    type: 'solid',
    colors: ['#047857'] // green
  },
  stroke: {
    lineCap: 'round'
  },
};

// Helper function to format display date
const formatDisplayDate = (dateStr) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: '2-digit' 
  });
};

function formatDate(dateStr, timeStr) {
  const date = new Date(`${dateStr}T${timeStr}`);
  return isNaN(date)
    ? ""
    : date.toLocaleString("en-IN", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
      });
}
function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
</script>
<style scoped>
.apexcharts-datalabels-group text {
  font-weight: 700 !important;
  font-size: 24px !important;
}
.apexcharts-text .apexcharts-datalabel-value {
  font-size: 24px; /* Value: 236 */
  font-weight: 700;
}
</style>