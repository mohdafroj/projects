<template>
  <Loading v-if="isLoading" />
  <!-- Main Table -->
  <div class="mt-4 overflow-x-auto">
    <TableOptions 
    :tableTitle="'Cghs Card Request'" 
    :columns="columnsList" 
    :data="users"
    :excludeSearch="['slno', 'term_start_date', 'applicant_name','term_end_date', 'valid_upto', 'relation']"
    :excludeSort="['slno', 'cghs_card_request_no','applicant_name', 'term_start_date', 'term_end_date', 'valid_upto', 'relation']"
    :excludeFilter="['slno', 'term_start_date','applicant_name', 'term_end_date', 'valid_upto', 'relation']" 
    @search="handleSearch"
    @sort="onSort" 
    @filter="handleFilter" 
    @clearSearch="handleClearSearch" 
    @clearSort="onClearSort"
    @clearFilter="onClearFilter"
          
      />
    <div class="overflow-x-auto">
      <DataTable :data="userList" :customColumnOrder="columnsList" @filtersChanged="handleFilterChange"
        :showOpen="true" :show-actions="false"
         @rowOpened="handleRowOpened"
        >
        <!-- Custom cell for S.No -->
        <template #cell-slno="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Employee ID -->
        <!-- <template #cell-ic_no="{ cell }">
          <div class="p-4">{{ cell?.value??'-' }}</div>
        </template> -->

        <template #cell-cghs_card_request_no="{ cell }">
          <div class="p-4">{{ cell?.value ?? '-' }}</div>
        </template>

        <template #cell-applicant_name="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>


        <!-- Custom cell for Designation -->
        <template #cell-member_names="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Full Name -->
        <template #cell-term_start_date="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Official Email ID -->
        <template #cell-term_end_date="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Division/Branch -->
        <template #cell-relation="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Mobile Number -->
        <template #cell-valid_upto="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>
        <template #cell-status="{ cell }">
          <Badge :text="cell.value" :type="badgeClass(cell.value)" />
        </template>

         <template #cell-open_detail="{ cell }">
          <div class="p-4">{{ cell.value }}</div>
        </template>

      </DataTable>
    </div>
    <PaginationSelect v-if="Object.keys(currentPage).length === 0"  
      :currentPage="currentPage" 
      :totalPages="totalPages" 
      :pageSize="perPage"
      @update:currentPage="handlePageChange" @update:pageSize="handlePageSizeChange" />
  </div>
</template>

<script setup>
import { Badge } from '@sds/oneui-common-ui';
import { ref, onMounted, computed } from "vue";
import DataTable from "@/components/Datatable/DataTable.vue";
import PaginationSelect from "@/components/Datatable/PaginationSelect.vue";
import TableOptions from "@/components/Datatable/TableOptions.vue";
import { getAllCardsList } from '@/services/rss/cghsServices.js';
import { useI18n } from 'vue-i18n';
import { dateFormated } from "@/utils/dateFormat"; 
import { useRouter } from 'vue-router';
const { t } = useI18n();
const showModal = ref(false);
const selectedRequest = ref(null);
const showVerificationModal = ref(false);
const selectedVerificationRequest = ref(null);
import { Loading } from '@sds/oneui-common-ui';
const isLoading = ref(true);
const router = useRouter();

const userList = ref([]);

//Opem button for detail page//
const handleRowOpened = (data) => {
 // console.log('all DTTT', data)
  router.push({ 
    name: 'get-member-preview-details',         // use your route's name
    params: { id: data.cghs_card_request_no }    // pass id as a route parameter
  });
}

// Define columns with localized labels
const columnsList = computed(() => [
  { key: "slno", label: t('cghsCard.slno'), type: "label" },
  { key: "cghs_card_request_no", label: t('cghsCard.cghs_card_request_no'), type: "label" },
   { key: "applicant_name", label: t('cghsCard.applicant_name'), type: "label" },
  { key: "member_names", label: t('cghsCard.member_name'), type: "label" },
  { key: "term_start_date", label: t('cghsCard.term_start_date'), type: "label" },
  { key: "term_end_date", label: t('cghsCard.term_end_date'), type: "label" },
  { key: "relation", label: t('cghsCard.relation'), type: "label" },
  { key: "valid_upto", label: t('cghsCard.valid_upto'), type: "label" },
  { key: "status", label: t('cghsCard.status'), type: "label" },
  
]);
// Initial user data
const users = ref([]);
const response = ref([]);
// const filteredData = ref([]);

const currentPage = ref(1);
const pageSize = ref(10); // Default to 50 records per page
const perPage = ref(10);
const totalPages = ref(0);
const totalEntries = ref(0);
const currentSortPage = ref('asc')
const currentSortCol = ref('created_at')
const currentSearch = ref('')
const currentSearchCol = ref('')
const currentFilter = ref({});
const params={}
const memberNamesFilter = ref('');  
const statusFilter = ref(''); 
const cghsCardNoFilter = ref('');
const handleFilter = (filters) => { 
  memberNamesFilter.value = filters.member_names || '';
  cghsCardNoFilter.value = filters.cghs_card_request_no || '';
  statusFilter.value = filters.status || '';
  currentPage.value = 1;  // reset page on filter change
  fetchListingData();
}

const handleClearSearch = () => {
//  console.log("onClearSearch ========>")
  currentSearch.value = '';
  currentSearchCol.value = '';
  currentPage.value = 1;
  fetchListingData();
}

const handleFilterChange = (res) => {
 // console.log('On Filter=====', res);
}

const onClearSort = () => {
  currentSortPage.value = 'asc';
  currentSortCol.value = 'created_at';
  currentPage.value = 1;
  fetchListingData();
  
}

const onClearFilter = () => {
  currentFilter.value = '';
  currentPage.value = 1;
  fetchListingData();
}

const handleSearch = (data) => {
  console.log("onSearch ========>", data.key,'++++++',data.value);
   
  currentSearch.value = data.value;
  currentSearchCol.value = data.key;
   
  // }else{
  //   data.key = data.value;
  // }
  //console.log("onSearch ========>", data.key,'++++++',data.value);
   data.key = data.value;
  fetchListingData();
}

const onSort = (data) => {
  currentSortPage.value = data.order;
  currentSortCol.value = data.key;
  fetchListingData();
}
// Compute paginated data for the current page
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return sortedAndFilteredUsers.value.slice(start, end);
});
const handlePageChange = (page) => {
  currentPage.value = page;
  fetchListingData();
  console.log("handlePageChange ========>", page)
}

const handlePageSizeChange = (size) => {
  perPage.value = size;
  currentPage.value = 1;
  fetchListingData();
  console.log("handlePageChange ========>", size)
}
// Helper function to get column key from label
const getColumnKeyFromLabel = (label) => {
  const column = columnsList.value.find((col) => col.label === label);
  return column ? column.key : null;
};
//=================================//

// Method to open the detail modal
const openModal = (request) => {
  selectedRequest.value = request;
  showModal.value = true;
};

// Method to open the verification modal
const openVerificationModal = (request) => {
  selectedVerificationRequest.value = request;
  showVerificationModal.value = true;
};

// Method to handle forward action from detail modal
const handleForward = (request) => {
  console.log('Forwarded to approver:', request);
  showModal.value = false;
};

// Method to handle verify and forward action
const handleVerifyAndForward = (data) => {
  console.log('Verified and forwarded:', data);
  showVerificationModal.value = false;
  // Update request status or handle the verification logic here
};

// Method to handle reject action
const handleReject = (data) => {
  console.log('Request rejected:', data);
  showVerificationModal.value = false;
  // Handle rejection logic here
};
//const allData = ref({});



const fetchListingData = async () => {
  try { 

    const options = {
      sort_order: currentSortPage.value,
      sort_by: currentSortCol.value,
      page: parseInt(currentPage.value),
      per_page: parseInt(perPage.value),
    };

    //console.log('kaakaaa', memberNamesFilter.value);
    // Add member_names if set
    if (memberNamesFilter.value) {
      options.member_names = memberNamesFilter.value;
    }

    // Add status if set
    if (statusFilter.value) {
      options.status = statusFilter.value;
    }

    // Add CGHS Card request No. if set
    if (cghsCardNoFilter.value) {
      options.cghs_card_request_no = cghsCardNoFilter.value;
    }

    

    // Add search if needed
    if (currentSearch.value && currentSearchCol.value) {
      options[currentSearchCol.value] = currentSearch.value;
    }
    //console.log('data fetched ===>>', options);
    const response = await getAllCardsList(options);
    //console.log('ddddd',response);

    if (response.isError === false && response.success_code == 200) {
      userList.value = response.data.get_card_list.map((item, index) => ({
        slno: (currentPage.value - 1) * perPage.value + index + 1,
        cghs_card_request_no: item.cghs_card_request_no || "N/A",
         applicant_name: item.applicant_name || "N/A",
        member_names: item.member_name || "N/A",
        term_start_date: (item.term_start_date) || "N/A",
        term_end_date: (item.term_end_date) || "N/A",
        relation: item.relation,
        valid_upto: dateFormated(item.valid_upto) || "N/A",
        status: item.status || "N/A",
      }));
      currentPage.value = response.data.paginate.current_page;
      perPage.value = response.data.paginate.per_page;
      totalEntries.value = response.data.paginate.total;
      totalPages.value = response.data.paginate.last_page;
     // console.log('all dataItem====>>>>>>', users.value);
    } else {
      console.error("Failed to fetch users:", response.message || response.statusText);
    }
    isLoading.value = false;
  } catch (e) {
    console.log("listing Data Error");
  }
}

// Fetch users on mount
onMounted(async () => {
  await fetchListingData();
});

//status badge change
const badgeClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'approved':
      return 'success';
    case 'pending':
      return 'warning';
    case 'rejected':
      return 'danger';
    case 'processed':
      return 'info';
    default:
      return 'info';
  }
};
</script>
