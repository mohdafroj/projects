<template><Loading v-if="isLoading" />
  <div class="table-container">
    <!-- Card Claim Details -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <DashboardCard
      subTitle="Total Claims"
      :amount="loadingT ? 'Loading...' :TotalClaim"      
      iconName="mdi:account-search-outline"
      iconColor="text-blue-500"
      iconBgColor="bg-blue-100" 
      />

      <DashboardCard
      subTitle="Completed Claims"
      :amount="loadingC ? 'Loading...' :CompletedClaim"      
      iconName="mdi:checkbox-marked-outline"
      iconColor="text-green-500"
      iconBgColor="bg-green-100" 
      />

      <DashboardCard
      subTitle="Pending Claims"
      :amount="loadingP ? 'Loading...' :PendingClaim"      
      iconName="mdi:alert-octagon-outline"
      iconColor="text-orange-500"
      iconBgColor="bg-orange-100" 
      />

      <DashboardCard
      subTitle="Overdue Claims"
      :amount="loadingO ? 'Loading...' :OverDueClaim"      
      iconName="mdi:alert-outline"
      iconColor="text-red-500"
      iconBgColor="bg-red-100" 
      /> 
    </div> 

    <!-- Table List -->
    <TableOptions 
    :tableTitle="tableHeading" 
    :columns="columnsList" 
    :data="users"     
    :excludeSearch="['documents','slno','submission','claimed_amount']" 
    :excludeSort="['documents','slno','submission','claimed_amount']"
    :excludeFilter="['documents', 'slno', 'claim_id', 'claim_code','submission','claimed_amount']" 
    :isDownload="true" 
    @search="handleSearch" 
    @sort="onSort" 
    @filter="onFilter"
    @clearSearch="handleClearSearch" 
    @clearSort="onClearSort"     
    @clearFilter="onClearFilter" />
    <!-- Main Table -->
    <div>

      <DataTable
      :data="userList"
      :customColumnOrder="columnsList"
      @filtersChanged="handleFilterChange" 
      :showOpen="isShowOpen"
      :show-actions="false"
      @rowOpened="handleRowOpened"
      >
        <!-- Custom cell for S.No -->
        <template #cell-slno="{ cell }" class="bg-neutral-100">
          <div class="text-center">{{ cell.value }}</div>
        </template>  

        <template #cell-claim_id="{ cell }">
          <div class="text-center">{{ cell?.value??'-' }}</div>
        </template>

        <template #cell-member_name="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Designation -->
        <template #cell-claim_track_id="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template> 

        <template #cell-submission="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <template #cell-claimed_amount="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Full Name -->
        <template #cell-term_start_date="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Official Email ID -->
        <template #cell-term_end_date="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Division/Branch -->
        <template #cell-relation="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>

        <!-- Custom cell for Mobile Number -->
        <template #cell-valid_upto="{ cell }">
          <div class="text-center">{{ cell.value }}</div>
        </template>
         <template #cell-status="{ cell }">  
            <Badge :text="cell.value" :type="badgeClass(cell.value)"/> 
        </template> 
      </DataTable> 

      <PaginationSelect 
        :currentPage="currentPage" 
        :totalPages="totalPages" 
        :pageSize="perPage"
        @update:currentPage="handlePageChange" 
        @update:pageSize="handlePageSizeChange" />     
    </div>
  </div>
</template>
<script setup>
import { DashboardCard,Loading,Badge} from '@sds/oneui-common-ui';
import { ref, reactive, watch, computed, onMounted } from "vue";
// import StatCard from "@/ui-components/StatCard.vue";
import DataTable from "@/components/Datatable/DataTable.vue"; 
import TableOptions from '@/components/Datatable/TableOptions.vue';
import PaginationSelect from "@/components/Datatable/PaginationSelect.vue"; 
import { useI18n } from "vue-i18n"; 
import { useRouter } from 'vue-router';
import {getCountClaimByStatus,getMedicalClaimList} from '@/services/rss/medicalClaims';
 

const router = useRouter();
const { t, locale } = useI18n();
const tableHeading = computed( () => t("menu.it_equipment"));
const userList = ref([]);


const pagiShowLabel = computed( () => t("pagi_show_label"));
const pagiPrevLabel = computed( () => t("pagi_prev_label"));
const pagiNextLabel = computed( () => t("pagi_next_label"));
const pagiTotalLabel = computed(() => t("pagi_total_label", { current: 1, total: 100 }));
const isLoading = ref(true);

const TotalClaim = ref(0);
const CompletedClaim = ref(0);
const PendingClaim = ref(0);
const OverDueClaim = ref(0);

const loadingT = ref(true)
const loadingC = ref(true)
const loadingP = ref(true)
const loadingO = ref(true) 

const pagedData = ref([]);
const pagedDataLoader = ref(true);
const pagedDataMessage = ref('');
const claimListOptions = reactive({}); 
const currentSortPage = ref('asc');
const currentSortCol = ref('created_at');
const currentSearchCol = ref('') ; 
const perPage = ref(10);
// Sorting and filtering state 
const currentSearch = ref('');
const currentPage = ref(1);
const pageSize = ref(10); // Default to 50 records per page
const totalPages = ref(0);
const totalEntries = ref(0);
const isShowOpen = ref(true);  // or false to hide the button initially

  // Track hovered row ID (or slno)
 const handleClearSearch = () => { 
    console.log("onClearSearch ========>")
      currentSearch.value = '';
    currentSearchCol.value = '';
    currentPage.value = 1;
    getAllClaimList();
} 

// Define columns with localized labels
const columnsList = computed(() => [
  { key: "slno", label: "Sl.No." },
  { key: "member_name", label: "Full Name" },
  { key: "claim_id", label: "Claim ID." },
  { key: "claim_track_id", label: "Claim Track Id." },
  { key: "submission", label: "Submission" },
  { key: "claimed_amount", label: "Claim Amount" },
  { key: "status", label: "Status" },
]);
  
const onSort = (data) => { 
    currentSortPage.value = data.order;
    currentSortCol.value = data.key;
    getAllCardsList(); 
}

const onFilter = filters => { 
};

const onClearFilter = () => {
      currentFilter.value = '';
    currentPage.value = 1;
    getAllCardsList();
}

 
const onClearSort = () => {
    currentSortPage.value = '';
    currentSortCol.value = '';
    currentPage.value = 1;
    getAllCardsList();
}

const handleFilterChange = (res)=>{
console.log('On Filter=====',res);
}

//Opem button for detail page//
const handleRowOpened = (data) => {
  router.push({ 
    name: 'medical-claim-detail',         // use your route's name
    params: { id: data.claim_id }    // pass id as a route parameter
  });
}
//Opem button for detail page//
//================ code start counts of data ==============//
//fetch counts
const requestsCount = ref([]);
const FetchCountClaimByStatus = async ()=>{
  try {
   const res = await getCountClaimByStatus();
   requestsCount.value = res.data;
   console.log('Claims count=====', requestsCount.value);

    TotalClaim.value      = requestsCount.value['total_claim_count'];
    CompletedClaim.value  = requestsCount.value['completed_claim_count'];
    PendingClaim.value    = requestsCount.value['pending_claim_count'];
    OverDueClaim.value  = requestsCount.value['overdue_claim'];

  }catch(err){
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('Error=====', err.value);
  }finally {
    // ✅ FIX: Mark loading as false
    loadingT.value = false;
    loadingC.value = false;
    loadingP.value = false;
    loadingO.value = false;
  }
}
//=================== code end counts of data===================//




// Compute paginated data for the current page
 
const handleSearch = (data) => {
    console.log("onSearch ========>", data)
    currentSearch.value = data.value;
    currentSearchCol.value = data.key;
    data.key = data.value;
    // currentSearchCol.value = data.key;
    getAllClaimList(); 
}
const handlePageChange=(page)=>{ 
  currentPage.value= page;
  getAllClaimList();
  console.log("handlePageChange ========>", page)
}


const handlePageSizeChange=(pagesize)=>{
    perPage.value = size;
    currentPage.value = 1;
    getAllClaimList();
    console.log("handlePageChange ========>", size)
} 
const getAllClaimList = async () =>{  
  try {   
    const options = {
            sort_order: currentSortPage.value,
            sort_by: currentSortCol.value,
            [currentSearchCol.value]:currentSearch.value, 
            page: parseInt(currentPage.value),
            per_page: parseInt(perPage.value)
        }; 
    const response = await getMedicalClaimList(options); 
    console.log('Claim List Responce Data==>>',response.data.data)
    if (!response.isError && Array.isArray(response.data.data)) {
      userList.value = response.data.data.map((item, index) => ({
        slno: item.sr_no || "-",
        claim_id: item.claim_id || "N/A",
        member_name: item.member_name || "N/A",
        claim_track_id: item.claim_track_id || "N/A",
        submission: item.submission || "N/A",
        claimed_amount: item.claimed_amount,
        status: item.status || "N/A",
      }));
      currentPage.value = response.data.pagination.current_page;
      perPage.value = response.data.pagination.per_page;
      totalEntries.value = response.data.pagination.total;
      totalPages.value = response.data.pagination.last_page;
      //console.log('all dataItem====>>>>>>',users.value );  
    } else {
      console.error("Failed to fetch users:", response.message || response.statusText);
    }
  } catch (error) {
    console.error("Error fetching claim list:", error);
  } finally {
    isLoading.value = false; 
  }
}
 
onMounted(async () => { 
await FetchCountClaimByStatus(); 
await getAllClaimList();  
  
  
});

const badgeClass = (status) => {
  //console.log('sssssaa',status)
  switch (status?.toLowerCase()) {
    case "draft":
      return 'success';
    case 'pending':
      return 'warning';
    case 'rejected':
      return 'danger';
    case 'processed':
      return 'info';
    default:
      return 'warning';
  }
};
</script>
