<template>
  <Loading v-if="isLoading" />
  <!-- <Modal v-model="showViewModal" title="" size="sm" disable-backdrop="true"> -->
  <div v-if="showViewModal == true">
    <div class="min-h-screen p-6 bg-gray-100 flex items-center justify-center">
      <!-- Single White Box Container -->
      <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-6xl">
        <div @click="showViewModal = false" class="float-right">
          <Icon icon="heroicons:x-mark"></Icon>
        </div>
        <h6 class="font-semibold">Session {{ current_session }}</h6>
        <p class="mt-2">Budget Session ({{ current_session }} - budget- DD/MM/YY) </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 text-sm">
          <!-- Section 1 -->
          <div>
            <h3 class="text-sm ">Session Information</h3>
            <div class="space-y-2 mt-4">
              <div class="grid grid-cols-[150px_1fr]">
                <span>Session Number:</span>
                <span class="font-semibold">1</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>Session Type:</span>
                <span class="font-semibold">2</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>Session Mode:</span>
                <span class="font-semibold">3</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>Start Date:</span>
                <span class="font-semibold">4</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>End Date:</span>
                <span class="font-semibold">4</span>
              </div>
            </div>
          </div>


          <!-- Section 2-->
          <div>
            <h3 class="text-sm">Administrative Details</h3>
            <div class="space-y-2 mt-4">
              <div class="grid grid-cols-[150px_1fr]">
                <span>Reference Number:</span>
                <span class="font-semibold">1</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>Intimation Date:</span>
                <span class="font-semibold">2</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>President Approval:</span>
                <span class="font-semibold">3</span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span>Summon Issue Date:</span>
                <span class="font-semibold">4</span>
              </div>
            </div>
          </div>


          <!-- Section 3 -->
          <div>
            <h3 class="text-sm ">Attached Documents</h3>
            <div class="space-y-2 mt-4">
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
              <div class="grid grid-cols-[150px_1fr]">
                <span
                  class="inline-flex items-center space-x-2 px-4 py-2 rounded-full text-sm font-semibold text-custom-blue bg-custom-blue">
                  <Icon icon="heroicons:clipboard-document-check" class="w-5 h-5" />
                  <span class="font-light">attached file</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
  <!-- </Modal> -->
  <div v-else>
    <div>
      <h5><strong></strong> </h5>
      <!-- <p class="mt-3">current session {{ current_session }}</p> -->
    </div>
    <div class="table-container mt-4">
      <!-- Card Session Details -->
      <!-- <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <StatCard :title="sessionCountData.lables?.session_days" :value="sessionCountData.data?.session_days" icon="mdi:account-search-outline" color="default"
        />
      <StatCard :title="sessionCountData.lables?.sitting_days" :value="sessionCountData.data?.sitting_days" icon="mdi:checkbox-marked-outline" color="green"
        />
      <StatCard :title="sessionCountData.lables?.qr_generated" :value="sessionCountData.data?.qr_generated" icon="mdi:alert-octagon-outline" color="orange"
        />

    </div> -->

      <!-- Table List -->

      <CustomOptions :tableTitle="'Session List'" :addButtons="[
        {
          text: 'Create Session',
          icon: 'heroicons-outline:plus'
        },
      ]" :columns="columns" :isDownload="true" :data="sessionData" @addItems="onAddItems" @search="onSearch"
        :excludeSearch="['session_start_date', 'session_end_date']" @sort="onSort" :excludeSort="['session_type']"
        @filter="onFilter" @clearFilter="onClearFilter" @clearSearch="onClearSearch" @clearSort="onClearSort" :sessionNumberList="sessionNumber" :sessionOptions="sessionTypeOptions" :permission="[PERMISSIONS.SESSION.CREATE]"/>
      <!-- Main Table -->
      <CustomTable :data="sessionData" :customColumnOrder="columnOrder" @filtersChanged="handleFilterChange">
        <template #cell-session_number="{ cell }">
          {{ cell.value }}
        </template>

        <template #cell-session_type="{ cell }">
          <div>
            {{ cell.value }}
          </div>
        </template>

        <template #cell-session_start_date="{ cell }">
          <div>
            {{ formattedDate(cell.value) }}
          </div>
        </template>

        <template #cell-session_end_date="{ cell }">
          <div>
            {{ formattedDate(cell.value) }}
          </div>
        </template>

        <!-- Action -->
         <template #cell-after="{ row }">
          <div class="text-center">
            <div class="text-black font-semibold">
              <div class="py-3 px-4 flex items-center justify-start">
                <button class="p-1 mx-1 text-black-500 hover:text-blue-700" @click="manageSittingDates(row)" aria-label="calendar-view-button" title="View Sitting Calendar">
                  <Icon icon="heroicons-outline:eye" class="text-base" />
                </button>
              </div>
            </div>
          </div>
        </template> 
      </CustomTable>
      <!-- Error or Loading -->
      <div v-if="Object.keys(sessionData).length === 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span v-if="pagedDataLoader">{{ $t('loading') }}</span>
        <span v-else>{{ pagedDataMessage }}</span>
      </div>
      <!-- pagination -->
      <PaginationSelect :currentPage="currentPage" :pageSize="perPage" :totalPages="totalPages"
        @update:currentPage="handleCurrentPage" @update:pageSize="handlePageSize" />
    </div>
  </div>
</template>
<script setup>
import StatCard from "@/ui-components/StatCard.vue";
import { ref, reactive, watch, computed, onMounted } from "vue";
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import CustomTable from "./CustomTable.vue";
import CustomOptions from "./CustomOptions.vue";

import { useI18n } from "vue-i18n";
import { useRouter } from 'vue-router';
import { Icon } from "@iconify/vue";
import { Modal } from "@sds/oneui-common-ui";
import { fetchSessionList, fetchCurrentSessionNumber, fetchSessionTypes, fetchSessionNumber } from "@/services/rss/sessionService";
import { hasPermission, PERMISSIONS } from "@/utils/rbac";
import Loading from "@/components/Loding.vue"


const router = useRouter();
const { t, locale } = useI18n();

const rawData = ref({
  session_days: 23,
  sitting_days: 17,
  qr_generated: 4165,
});

const sessionCountData = computed(() => ({
  lables: {
    session_days: t("session.session_days"),
    sitting_days: t("session.sitting_days"),
    qr_generated: t("session.qr_generated"),
  },
  data: rawData.value
}));

const current_session = ref('');
const showViewModal = ref(false);

// // Pagination state
const perPage = ref(10);
const currentPage = ref(1);
const totalEntries = ref(0);
const totalPages = ref(0);
const currentsortPage = ref('');
const currentsortCol = ref('');
const currentsearch = ref('');
const currentsearchCol = ref('');
const currentFilter = ref({});
const startIndex = ref(0);
const endIndex = ref(0);
const pageSize = ref(10);

const sessionData = ref([]);

const pagedDataLoader = ref(true);
const pagedDataMessage = ref('');
const isLoading = ref(true);

const columns = [

  { key: "session_number", label: "Session Number", type: "String" },
  { key: "session_type", label: "Session Type", type: "String" },
  { key: "session_start_date", label: "Session Start Date", type: "Date" },
  // { key: "session_end_date", label: "Session End Date", type: "Date" },
];

//pagination new table
const handlePageSize = (size) => {
  perPage.value = size;
  currentPage.value = 1;
  getSessionList();
}

const handleCurrentPage = (page) => {
  currentPage.value = page;
  getSessionList();
}

const onSearch = (data) => {
  currentsearch.value = data.value;
  currentsearchCol.value = data.key;
  getSessionList();
};

const onSort = (data) => {
  currentsortPage.value = data.order;
  currentsortCol.value = data.key;
  getSessionList();
};

const onFilter = (data) => {
  if(Object.keys(data).length === 0){
    currentPage.value = 1;
  }
  const transformed = {};
  for (const key in data) {
    const val = data[key];

    if (Array.isArray(val)) {
      // If array of objects with id
      
        if (val.length > 0 && typeof val[0] === 'object' && 'id' in val[0]) {
          if('Session_type_name' in val[0]){
            transformed[key] = val.map(item => item.id).join('|');
          }
          if('session_number' in val[0]){
            transformed[key] = val.map(item => item.session_number).join('|');
          }
        } else {
          transformed[key] = val.join('|'); // fallback for plain array of values
        }
    } 
    else {
      transformed[key] = val;
    }
  }
  currentFilter.value = transformed;
  getSessionList();
}

const onClearSearch = () => {
  currentPage.value = 1;
  currentsearch.value = '';
  currentsearchCol.value = '';
  getSessionList();
}

const onClearSort = () => {
  currentPage.value = 1;
  currentsortPage.value = '';
  currentsortCol.value = '';
  getSessionList();
}

const onClearFilter = () => {
  currentFilter.value = '';
  currentPage.value = 1;
  getSessionList();
}

//column order
const columnOrder = ref([
  "session_number",
  "session_type",
  "session_start_date",
  "session_end_date"
]);

const getCurrentSessionNumber = async () => {
  const response = await fetchCurrentSessionNumber();
  if (response.success_code == 200) {
    current_session.value = response.data;
  }
  else {
    current_session.value = "Unable to find current Session, something went wrong"
  }
}
const innerChild = ref(false);
const getSessionList = async () => {
  isLoading.value = true;
  pagedDataLoader.value = true;
  const options = {
    order: currentsortPage.value,
    orderBy: currentsortCol.value,
    search_str: currentsearch.value,
    search_by: currentsearchCol.value,
    page: parseInt(currentPage.value),
    pagelimit: perPage.value,
    ...currentFilter.value,
  }
   const filteredParams = Object.fromEntries(
      Object.entries(options).filter(([, value]) => value !== null && value !== undefined && value !== '')
    );
  const response = await fetchSessionList({ params: filteredParams });
  pagedDataLoader.value = false;
  innerChild.value = true;
  isLoading.value = false;
  if (response.isError == false && response.success_code == 200 && response.data.length != 0) {
    sessionData.value = response.data;
    perPage.value = response.pagination.per_page
    currentPage.value = response.pagination.current_page
    totalEntries.value = response.pagination.total
    totalPages.value = response.pagination.last_page
    startIndex.value = (currentPage.value - 1) * perPage.value + 1
    endIndex.value = Math.min(currentPage.value * perPage.value, totalEntries.value)
  } else {
    sessionData.value = [];
    pagedDataMessage.value = computed(() => t("no_record"));

  }
}


// Computed property to format the date
const formattedDate = (date) => {
  return computed(() => {
    if (!date || isNaN(new Date(date).getTime())) return ''; // Handle null, undefined, or invalid date

    // Format the date using Intl.DateTimeFormat
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Intl.DateTimeFormat('en-US', options).format(new Date(date));
  }).value;
};

const onViewSession = () => {
  showViewModal.value = true;
}

const onAddItems = items => {
  router.push({
    name: 'Create-Session',
  });
};

const sessionTypeOptions = ref([]);
const sessionNumber = ref([]); 
const getSessionType = async () => {
    const response = await fetchSessionTypes();
    if (response.success_code == 200) {
        sessionTypeOptions.value = response.data;
    }
    else {
        console.log("Could not load the session type list");
        
    }
}

const getSessionNumber = async () => {
    const response = await fetchSessionNumber();
    if (response.success_code == 200) {
        sessionNumber.value = response.data;
    }
    else {
      console.log("Unable to load session number list");
    }
    //await getSessionType();
}

const manageSittingDates = async (row) => {
  console.log("checking row",row.id);
  const sessionId = row.id;
  router.push({ name: 'Manage-Sitting', query: { id: sessionId } });
}


onMounted(async () => {
    await getSessionList();
    await getSessionType();
    await getSessionNumber();
})


</script>
<style scoped>
.text-custom-blue {
  color: #238CCD;
  border-color: #238CCD !important;
}

.bg-custom-blue {
  background-color: #DFF1FC;
}

/* Pagination styles */
.overflow-x-auto {
  scrollbar-width: thin;
  scrollbar-color: #a0aec0 #edf2f7;
}

.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background-color: #a0aec0;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background-color: #edf2f7;
}
</style>
