<template>
    <Loading v-if="isLoading" />
    <!-- <Modal v-model="showViewModal" title="View Agenda Details" size="md" disable-backdrop="true"
        @close="handleModalClose" >
     
        <div class="viewdatadiv">
            <div class="row">
       <div v-if="selectedRow && Object.keys(selectedRow).length" class="p-4 space-y-3 text-sm">
    <div class="grid md:grid-cols-2 grid-cols-2 gap-3">
      <div class="viewdatahead">Meeting No:</div>
      <div class="viewdataval text-right">{{ selectedRow.meeting_no || 'N/A' }}</div>

      <div class="viewdatahead">House:</div>
      <div class="viewdataval text-right">{{ selectedRow.house || 'N/A' }}</div>

      <div class="viewdatahead">Committee Name:</div>
      <div class="viewdataval text-right">{{ selectedRow.committee_name || 'N/A' }}</div>

      <div class="viewdatahead">Venue:</div>
      <div class="viewdataval text-right">{{ selectedRow.venue || 'N/A' }}</div>

      <div class="viewdatahead">Date & Time:</div>
      <div class="viewdataval text-right">
        {{ selectedRow.date_time ? new Date(selectedRow.date_time).toLocaleString() : 'N/A' }}
      </div>

      <div class="viewdatahead">Agenda:</div>
      <div class="viewdataval text-right whitespace-pre-line">
        {{ selectedRow.agenda || 'N/A' }}
      </div>
    </div>
  </div>
            
            </div>
        </div>
    </Modal> -->
    <AddMeeting />
  <div class="table-container">
    <!-- Table List -->
    <CustomOptions :tableTitle="'Meeting List'" :addButtons="[
      {
        text: 'Add New Meeting',
        color: 'text-blue-800',
        icon: 'heroicons-outline:plus',
      },
   
    ]" :columns="columns"  :data="committeeListData"
     @addItems="onAddNew" @search="onSearch" @sort="onSort" 
     :excludeFilter="['house','agenda',  'id']"
       :excludeSort="['house', 'id', 'agenda', 'venue','date_time']"
       :excludeSearch="['house', 'id', 'date_time']"
     
      @filter="onFilter" @clearFilter="onClearFilter" @clearSearch="onClearSearch" @clearSort="onClearSort"  />
    <!-- Main Table -->
    <div class="">
      <CustomTable :data="committeeListData" :customColumnOrder="columnOrder"  @filtersChanged="handleFilterChange"
       :rowClass="'hover:bg-gray-100 transition'">
        <!-- Custom cell: note number -->
  <template #cell-id="{ cell  }">
  {{ (currentPage - 1) * perPage + cell.index + 1 }}
</template>


  <template #cell-meeting_no="{ cell }">
    {{ cell.value }}
  </template>

  <template #cell-house="{ cell }">
    <span :class="{
      'text-green-700': cell.value === 2,
      'text-red-700': cell.value === 1
    }">
      {{ cell.value }}
    </span>
  </template>

  <template #cell-committee_name="{ cell }">
    {{ cell.value }}
  </template>

  <template #cell-venue="{ cell }">
    {{ cell.value }}
  </template>

  <!-- <template #cell-date_time="{ cell }">
    {{ new Date(cell.value).toLocaleDateString('en-US') }}
  </template> -->
    <template #cell-date_time="{ cell }">
  <div class="whitespace-pre">
    {{ formatDateTime(cell.value) }}
  </div>
</template>
    <template #cell-agenda="{ row, cell }">
  <div class="group relative w-full">
    <!-- Agenda Text -->
    <div class="text-sm text-gray-800 max-w-[80%]">
      {{truncatedContent (cell.value) }}
    </div>

    <!-- Buttons: Show only when agenda text is hovered -->
    <div class="absolute top-0 right-0 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
      <!-- <button
        class="px-4 py-1 text-sm border border-gray-300 rounded-full bg-white hover:bg-gray-100"
        @click="onViewCommitteeAgenda (row)"
      >
        Open
      </button> -->
      <button v-if="cell.row.canSubmit"
        class="px-4 py-1 text-sm border border-gray-300 rounded-full bg-white hover:bg-gray-100"
        @click="handleEditMeeting(cell.row)"
      >
        Edit
      </button>
    </div>
  </div>
    </template>
      </CustomTable>

      <!-- Error or Loading -->
      <div v-if="Object.keys(committeeListData).length === 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span>{{ pagedDataMessage }}</span>
      </div>

      <!-- Pagination -->
      <div class="bg-white" v-if="Object.keys(committeeListData).length !== 0">
        <PaginationSelect   
  :currentPage="currentPage"
  :pageSize="perPage"
  :totalPages="totalPages"
  @update:currentPage="handleCurrentPage"
  @update:pageSize="handlePageSize"
/>

<!-- <p class="text-sm text-center text-gray-500 mt-2">
  Showing {{ startIndex }} to {{ endIndex }} of {{ totalEntries }} entries
</p> -->
       
      </div>
    </div>
  </div>
</template>
<script setup>
import StatCard from "@/ui-components/StatCard.vue";
import {onMounted, ref, computed,watch } from "vue";
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import CustomTable from "./CustomTable.vue";
import CustomOptions from "./CustomOptions.vue";
import Card from "@/ui-components/Card.vue";
import Icon from "@/ui-components/Icon.vue";
import RadioGroup from "@/ui-components/RadioGroup.vue";
import Select from "@/ui-components/Select.vue";
import {  TextInput, Button, Modal, Switch, Pagination, Badge } from '@sds/oneui-common-ui';
import { useRouter } from 'vue-router'
import { fetchCommitteeList} from '@/services/committeeServices';
import Loading from "@/components/Loding.vue";
import { useI18n } from "vue-i18n";


const router = useRouter()
const { t, locale } = useI18n();
const hasRecords = ref(0);
const form = ref({
  category: '',
  committee: '',
  meetingNo: '',
  date: '',
  time: '',
  venue: '',
  agenda: ''
})

const committeeListData = ref({
    id:'',
    meeting_no:'',
    house:'',
    committee_name:'',
    venue:'',
    date_time:'',
    agenda:'',
    canSubmit:''
});

const perPage = ref(10);
const currentPage = ref(1);
const totalEntries = ref(0);
const totalPages = ref(0);
const currentsortPage = ref('desc');
const currentsortCol = ref('id');
const currentsearch = ref('');
const currentsearchCol = ref('');
const currentFilter = ref('');
const currentFilterCol = ref('');
const startIndex = ref(0);
const endIndex = ref(0);
const showModal = ref(false);
const showViewModal = ref(false);
const selectedRow = ref({});
const isPopupOpen = ref(false);
const pagedDataMessage = ref('');

const isDataLoading = ref(true);
const isLoading = ref(true);
const columnOrder = [
    { key: "id", label: "S.No", type: "Number" },
    { key: "meeting_no", label: "Meeting Number", type: "String" },
    { key: "house", label: "House", type: "String" },
    { key: "committee_name", label: "Committee Name", type: "String" },
    { key: "venue", label: "Venue & Place", type: "String" },
    { key: "date_time", label: "Date & Time", type: "Date" },
    { key: "agenda", label: "Agenda", type: "String" }
];
//NEW DATA TABLE
const customColumnOrder = ref([
    "id",
    "meeting_no",
    "house",
    "committee_name",
    "venue",
    "date_time",
    "agenda",
]);


const columns = [
    { key: "id", label: "S.No", type: "Number" },
    { key: "meeting_no", label: "Meeting Number", type: "String" },
    { key: "house", label: "House", type: "String" },
    { key: "committee_name", label: "Committee Name", type: "String" },
    { key: "venue", label: "Venue & Place", type: "String" },
    { key: "date_time", label: "Date", type: "Date" },
    { key: "agenda", label: "Agenda", type: "String" }
];
const customColumn = computed(() => {
    return customColumnOrder.value;
})

const truncateLength = 30;

// Computed property to truncate cell values
const truncatedContent = (content) => {
    return computed(() => {
        if (!content) return ''; // Handle null or undefined content
        return content.length > truncateLength ? content.substring(0, truncateLength) + '...' : content;
    }).value;
};
const errors = ref({})
const formatDateTime = (value) => {
  if (!value) return 'N/A';

  const parts = value.split(' ');
  if (parts.length !== 3) return value; // fallback if unexpected format

  const [datePart, timePart, ampm] = parts;
  const [month, day, year] = datePart.split('/');

  if (!month || !day || !year) return value;

  // Return date on first line, time + AM/PM on second line
  return `${day}/${month}/${year}\n${timePart} ${ampm}`;
};

function isTodayOrFuture(dateString) {
  if (!dateString) return false

  const [datePart, timePart, ampm] = dateString.split(' ')
  if (!datePart || !timePart || !ampm) return false

  const [month, day, year] = datePart.split('/').map(Number)
  let [hour, minute] = timePart.split(':').map(Number)

  if (ampm === 'PM' && hour !== 12) hour += 12
  if (ampm === 'AM' && hour === 12) hour = 0

  const apiDate = new Date(year, month - 1, day, hour, minute)

  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const apiDateOnly = new Date(apiDate)
  apiDateOnly.setHours(0, 0, 0, 0)

  return apiDateOnly.getTime() >= today.getTime()
}

const onAddNew = (data) => {
  router.push({name:"AddMeeting"}) 
  //resetForm();
  // selectedMeeting.value = meeting;
  // showCommitteeModal.value = true;
};
const filters = ref({
  meeting_no: '',
  committee_name: [],
  date: '',
  time: ''
});
const meetingNoOptions = ref([]); // From API
const committeeOptions = ref([]); // From table data
const venueOptions = ref([]); 

const onViewCommitteeAgenda = (row) => {
    if (row) {
    selectedRow.value = row;
    //console.log(row);
    showViewModal.value = true;
  }
  
    //ViewCommitteeAgenda(data.id);
};
const handleModalClose = () => {
  showViewModal.value = false;
};
// Pagination handlers

//pagination new table
const handlePageSize = (size) => {
  console.log("size",size);
  
    perPage.value = size;
    currentPage.value = 1;
    fetchcommitteeListData();
}

const handleCurrentPage = (page) => {
    currentPage.value = page;
    fetchcommitteeListData();
}
const fetchcommitteeListData = async () => {
  try {

    isLoading.value = true;
    const options = {
     page: parseInt(currentPage.value),
    // per_page: perPage.value,
      order: currentsortPage.value,
      orderBy: currentsortCol.value,
      search_str: currentsearch.value,
      search_by: currentsearchCol.value,
      // filter_by: currentFilterCol.value,
      // filter_val: currentFilter.value,
      pagelimit:perPage.value,
       ...currentFilter.value
     
    };
    
    
    if (currentsearch.value && currentsearchCol.value) {
      options.search_str = currentsearch.value;
      options.search_by = currentsearchCol.value;
    }
    if (currentFilter.value && currentFilterCol.value) {
      options.filter_by = currentFilterCol.value;
      options.filter_val = currentFilter.value;
    }   
     //console.log("Sending options to API:", options);

    const response = await fetchCommitteeList({params: options});   
    //console.log("API response:", response);
    if ( response.isError ) {
      isDataLoading.value = false;
      isLoading.value = false;
    }
    if (response && response.data.length != 0) {
      // committeeListData.value = response.data || []; // if paginated
      committeeListData.value = response.data.map(item => ({
        ...item,
        canSubmit: isTodayOrFuture(item.date_time)
      }))
      //console.log("checkingggg",committeeListData);
      //totalItems.value = response.data.total || committeeListData.value.length;
    }  else {
        committeeListData.value = [];
        pagedDataMessage.value = computed(() => t("no_record"));
      }
     // ✅ Update pagination data
    perPage.value = response.pagination.per_page;
    currentPage.value = response.pagination.current_page;
    totalEntries.value = response.pagination.total;
    totalPages.value = response.pagination.last_page;
    startIndex.value = response.pagination.from;
    endIndex.value = response.pagination.to;

    isDataLoading.value = false;
    isLoading.value = false;
    //console.log("Fetched Committee List:", committeeListData.value);

  } catch (error) {
    console.error("Failed to load committee list:", error);
  }
};
const handleEditMeeting = (row) => {
  router.push({
    name: 'EditMeeting',
    params: { id: row.id },
 
  //    state: {
  //   formData: row // pass full row data for editing
  // }
  })
}
const handleFilterChange = (data) => {
   currentsortPage.value = data.sort.order;
  currentsortCol.value = data.sort.by;
  currentsearch.value = data.search.term;
  currentsearchCol.value = data.search.column;
  fetchcommitteeListData();
}
// const onFilter = (data) => {
//   currentFilter.value = data.value;
//   currentFilterCol.value = data.key;
//   fetchcommitteeListData();
// }
const meetingNumbers = ref([]);



const onFilter = (data) => {
   if(Object.keys(data).length === 0){
    currentPage.value = 1;
  }

 const transformed = {};
  for (const key in data) {
    const val = data[key];
    console.log("key name",data[key]);
    if (Array.isArray(val)) {
      // If array of objects with id
        if (val.length > 0 && typeof val[0] === 'object' && 'id' in val[0]) {
          if('name' in val[0]){
            transformed['committee_id']  = val.map(item => item.id).join('|');
           }
          if('venue_name' in val[0]){
            transformed['venue_id'] = val.map(item => item.id).join('|');
          }
        } else {
          transformed['commence_time'] = val.join('|'); // fallback for plain array of values
          //console.log("transsss",val);
        }
    }
    else {
      transformed[key] = val;
    }
  }

  currentFilter.value = transformed;
    //  data.key= data.value
  //console.log("sssssss", transformed );

  fetchcommitteeListData();
};


const onSort = (data) => {
    currentsortPage.value = data.order;
  currentsortCol.value = data.key;
  fetchcommitteeListData(); 
};
const toggleSort = (() => {
    currentsortPage.value = currentsortPage.value === 'desc' ? "asc" : "desc"
   fetchcommitteeListData ();
})
const onSearch = (data) => {
   //console.log('Search triggered:', data);
    currentsearch.value = data.value;
    currentsearchCol.value = data.key;
    fetchcommitteeListData();
  
};
const onClearSearch = () => {
  currentPage.value = 1;
  currentsearch.value = '';
  currentsearchCol.value = '';
  fetchcommitteeListData();
}

const onClearSort = () => {
  currentPage.value = 1;
  currentsortPage.value = '';
  currentsortCol.value = '';
  fetchcommitteeListData();
}

const onClearFilter = () => {
  currentPage.value = 1;
  currentFilter.value = '';
  fetchcommitteeListData();
} 
onMounted(() => {
  fetchcommitteeListData(); 
})

</script>
<style>

</style>