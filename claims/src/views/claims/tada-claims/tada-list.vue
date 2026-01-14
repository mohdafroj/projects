<template>
  <Loading v-if="isLoading" />

  <div v-else class="table-container">
    <!-- Card Claim Details -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCard @click="() => handleSelectedCard('total')" :title="claimCountData.lables?.total" :value="claimCountData.data?.total" icon="mdi:account-search-outline" color="default"
        />
      <StatCard @click="() => handleSelectedCard('completed')" :title="claimCountData.lables?.completed" :value="claimCountData.data?.completed" icon="mdi:checkbox-marked-outline" color="green"
        />
      <StatCard @click="() => handleSelectedCard('pending')" :title="claimCountData.lables?.pending" :value="claimCountData.data?.pending" icon="mdi:alert-octagon-outline" color="orange"
        />
      <StatCard @click="() => handleSelectedCard('overdue')" :title="claimCountData.lables?.overdue" :value="claimCountData.data?.overdue" icon="mdi:alert-outline" color="red"
        />
    </div>
    
    <!-- Table List -->
    <div
      ref="scrollToSection"
    >
    <CustomOptions 
      :tableTitle="tableHeading"
      :addButtons="buttonMenu" 
      :columns="columns" 
      :isDownload="true" 
      :data="pagedData"
      :excludeFilter="['id', 'claim_id', 'claim_code', 'claim_amount', 'payment_status']"
      :excludeSearch="['id', 'submission', 'claim_status', 'claim_amount', 'payment_status']" 
      :excludeSort="['id','payment_status']" 
      @exportDownloadFile="handleDownloadTable" 
      @addItems="onAddItems" 
      @search="onSearch" 
      @sort="onSort" 
      @filter="onFilter"
      @clearFilter="onClearFilter" 
      @clearSearch="onClearSearch" 
      @clearSort="onClearSort" />
    </div>
    <!-- Main Table -->
    <div
    >
      <div class="flex border-b mt-2 bg-slate-300 p-2">
    
        <button @click="() => selectTab('assigned')" :class="selectedTab == 'assigned' ? 'bg-white' : 'hover:text-slate-900'" class="rounded-[4px] px-2 font-medium text-slate-500" type="button">Assigned to Me</button>
        <button @click="() => selectTab('all')" :class="selectedTab == 'all' ? 'bg-white' : 'hover:text-slate-900'" class="rounded-[4px] px-2 font-medium ml-1 text-slate-500" type="button">All TADA Claims</button>
      </div>     
      <CustomTable 
        :data="pagedData" 
        :customColumnOrder="columns" 
        :show-open="true" 
        @filtersChanged="handleFilterChange"
        @rowOpened="handleRowOpened">
        <!-- Custom cell: note number -->
        <template #cell-id="{ cell }">
          <span :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </span>
        </template>

        <!-- client id -->
        <template #cell-claim_code="{ cell }">
          <div :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </div>
        </template>

        <!-- member name -->
        <template #cell-member_name="{ cell }">
          <div :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </div>
        </template>

        <!-- pnr number -->
        <template #cell-pnr_number="{ cell }">
          <div :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </div>
        </template>
        <!-- party name -->
        <template #cell-party_name="{ cell }">
          <div :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </div>
        </template>

        <!-- state -->
        <template #cell-state="{ cell }">
          <div :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </div>
        </template>
        <!-- submission on -->
        <template #cell-submission="{ cell }">
          <div :class="{'font-bold': cell.row.is_bold}">
            {{ cell.value }}
          </div>
        </template>
        <!--bill amount -->
        <template #cell-bill_amount="{ cell }">
          <span :class="{'font-bold': cell.row.is_bold}">{{ cell.value == '₹0.00' ? '--' : cell.value }}</span>
        </template>
        <template #cell-claim_amount="{ cell }">
          <span :class="{'font-bold': cell.row.is_bold}">{{ cell.value == '₹0.00' ? '--' : cell.value }}</span>
        </template>
        <!-- claim_status on -->
        <template #cell-claim_status="{ cell }">
          <span class="px-2 py-1 rounded-full text-sm font-semibold" :class="{
            'bg-blue-100 text-blue-800': cell.value === 'Submitted',
            'bg-green-100 text-green-800': cell.value === 'Approved',
            'bg-red-100 text-red-800': cell.value === 'Canceled',
            'bg-orange-100 text-orange-800': cell.value === 'Initiated',
            'bg-green-100 text-green-700': cell.value === 'Reviewed',
          }">
            {{ cell.value }}
          </span>
        </template>
        <!--payment_status -->
        <template #cell-documents="{ cell }">
          <span class="px-2 py-1 rounded-full text-sm font-semibold">{{ cell.value }}</span>
        </template>
        <template #cell-payment_status="{ cell }">
          <span class="px-2 py-1 rounded-full text-sm font-semibold">{{ cell.value }}</span>
        </template>
      </CustomTable>
      <div v-if="pagedData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span v-if="pagedDataLoader"><Loading /></span> 
        <span v-else>{{pagedDataMessage}}</span>
      </div>
      <!-- Pagination -->
      <div class="bg-white">
        <PaginationSelect 
        :pagiShowLabel="pagiShowLabel"
        :pagiPrevLabel="pagiPrevLabel"
        :pagiNextLabel="pagiNextLabel"
        :pagiTotalLabel="pagiTotalLabel"
        :currentPage="claimPagination.current_page" 
        :totalPages="claimPagination.last_page" 
        :pageSize="claimPagination.per_page"
        @update:currentPage="handlePageChange" 
        @update:pageSize="handlePageSizeChange" />
      </div>
    </div>
  </div>

</template>
<script setup>
import StatCard from "./../it-equipment/list/StatCard.vue";
import { ref, reactive, computed, onMounted } from "vue";
import { Loading } from '@sds/oneui-common-ui';
import PaginationSelect from "./../it-equipment/PaginationSelect.vue";
import CustomTable from "./../it-equipment/list/CustomTable.vue";
import CustomOptions from "./../it-equipment/list/CustomOptions.vue";
import { useI18n } from "vue-i18n";
import { claimCount, memberList, claimStatusList, claimList } from "@/services/rss/itEquipmentsService";
import { TadaClaimList } from "@/services/rss/TadaServices";
import { useRouter } from 'vue-router';
import useLocalCurrency from "@/composables/useLocalCurrency";
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { useApiStore } from "@/store/apiData";

const router = useRouter();
const { t, locale } = useI18n();
const apiStore = useApiStore();
const isLoading = ref(true);
const selectedTab = ref('assigned');
const selectedCard = ref('total');
const scrollToSection = ref(null);

const tableHeading = computed( () => t("menu.tada_claims"));
const pagiShowLabel = computed( () => t("pagi_show_label"));
const pagiPrevLabel = computed( () => t("pagi_prev_label"));
const pagiNextLabel = computed( () => t("pagi_next_label"));
const pagiTotalLabel = computed(() => t("pagi_total_label", { current: claimPagination.value.current_page, total: claimPagination.value.last_page }));
const rawData = ref({
  total: 0,
  completed: 0,
  pending: 0,
  overdue: 0,
});
const claimCountData = computed(() => ({
  lables: {
      total: t("claims.total_count"), 
      completed: t("claims.completed_count"), 
      pending: t("claims.pending_count"), 
      overdue: t("claims.overdue_count")
    }, 
  data: rawData.value
}));
const filterDataList = ref({member_name: [], claim_status: []});

const fetchClaimCount = async () => {
    const response = await claimCount();
    if ( response.isError == false && response.success_code == 200 ) {
      rawData.value = response.data;
    }
}

const columns = ref([
  { key: "id", label: t("id"), type: "Number" },
  { key: "claim_code", label: t("claims.claim_code"), type: "String" },
  { key: "member_name", label: t("claims.member_name"), type: "String"  },
  { key: "pnr_number", label: t("claims.pnr_number"), type: "String"  },
  { key: "party_name", label: t("claims.party_name"), type: "String"  },
  { key: "state", label: t("claims.state"), type: "String"  },
  { key: "submission", label: t("claims.claim_date"), type: "Date" },
  { key: "claim_amount", label: t("claims.claim_amount"), type: "Amount" },
  { key: "bill_amount", label: t("claims.bill_amount"), type: "Amount" },
  { key: "claim_status", label: t("claims.claim_status"), type: "String" },
  { key: "documents", label: t("claims.documents"), type: "String" },
  { key: "payment_status", label: t("claims.payment_status"), type: "String" },
]);
const pagedData = ref([]);
const pagedDataLoader = ref(true);
const pagedDataMessage = ref('');
const claimListOptions = reactive({params:{pagelimit:10}});
const claimPagination = ref({current_page:0, last_page:0});
const currentUserId = computed(() =>apiStore.user.id || 0);

const buttonMenu = computed(() => {
  let result = [];
  if(hasPermission([PERMISSIONS.TADACLAIM.INITIATE])) {
    result = [
      {key:'add', text:'Add New TA/DA Claim', textColor:'underline', bgColor: '', hoverColor: '', icon:'mdi:plus'}
    ];
  }
  // result = [...result, {key:'itfe', text:'Financial Entitlement', textColor:'underline', bgColor: '', hoverColor: ''}];
  return result;
})

const makeBold = (isOpened, assignedTo) => {
  let status = false;
  if ( isOpened == 0  && ((assignedTo == null ) || (assignedTo == currentUserId)) ) {
    status = true;
  }
  return status;
}

const fetchClaimList = async () => {
    //isLoading.value = true;
    if ( selectedTab.value == 'assigned' ) {
      claimListOptions.params = {...claimListOptions.params, type:'TADA', card_type: selectedCard.value, tab: selectedTab.value };
    } else {
      claimListOptions.params = {...claimListOptions.params, type:'TADA', card_type: selectedCard.value};
    }
    pagedDataLoader.value = true;
    pagedData.value = [];
    claimPagination.value = {current_page:0, last_page:0};
    const response = await TadaClaimList(claimListOptions);
    pagedDataLoader.value = false;
    isLoading.value = false;
    if ( response.isError == false ) {
      if ( response.success_code == 200 && response.data.length ) {
        claimPagination.value = response.pagination;
        pagedData.value = response.data.map((item, i) => ({
          id: i + Number(claimPagination.value.from),
          claim_id: item['claim_id'],
          claim_code: item['claim_code'],
          member_name: item['member_name'],
          pnr_number: item['pnr_number'] || '--',
          party_name: item['party_name'] || '--',
          state: item['state'] || '--',
          submission: item['claim_date'],
          bill_amount: useLocalCurrency(item.claim_amount),
          claim_amount: useLocalCurrency(item.amount),
          claim_status: item.status,
          documents: item.required_documents || '--',
          payment_status: item.payment_status,
          assigned_to: item.assigned_to,
          is_opened: item.is_opened,
          is_bold: makeBold(item.is_opened, item.assigned_to)
        }));
      } else {
        pagedData.value = [];
        pagedDataMessage.value = computed( () =>t("no_record"));
      }
    } else {
      pagedDataMessage.value = computed( () =>t("something_wrong"));
    }
}

const handleSelectedCard = (value) => {
  selectedTab.value = 'all';
  selectedCard.value = value;
  claimListOptions.params = {pagelimit:10};
  fetchClaimList();
  scrollToSection.value.scrollIntoView({ behavior: "smooth", top: 0 });
}

const selectTab = (value) => {
  selectedTab.value = value;
  claimListOptions.params = {pagelimit:10};
  fetchClaimList();
}

const fetchMemberList = async () => {
    filterDataList.value.member_name = [];
    const response = await memberList();
    if ( response.isError == false && response.success_code == 200 ) {
      filterDataList.value.member_name = response.data;
      let values = filterDataList.value.member_name.map((item) => item.full_name);
      columns.value = columns.value.map(item => item.key == 'member_name' ? {...item, values} : item );
    }
}

const fetchStatusList = async () => {
    filterDataList.value.claim_status = [];
    const response = await claimStatusList();
    if ( response.isError == false && response.success_code == 200 ) {
      filterDataList.value.claim_status = response.data;
      let values = filterDataList.value.claim_status.map((item) => item.status_name);
      columns.value = columns.value.map(item => item.key == 'claim_status' ? {...item, values} : item );
    }
}

onMounted( async () => {
  await fetchClaimCount();
  await fetchClaimList();
  fetchMemberList();
  fetchStatusList();
})

const onSearch = query => {
  let searchColumnName = '';
  switch(query.key) {
    case 'claim_code': searchColumnName = 'claim_code'; break;
    case 'bill_amount': searchColumnName = 'claimed_amount'; break;
    case 'member_name': searchColumnName = 'member_name'; break;
    default: searchColumnName = query.key;
  }
  if ( query.value == '' ) {
    const { search_by, search, ...remain } = claimListOptions.params;
    claimListOptions.params = {...remain};
  } else {
    claimListOptions.params = {
      ...claimListOptions.params, 
      search_by: searchColumnName, 
      search: query.value
    };
  }
  fetchClaimList();
};

const onSort = config => {
  let sortColumnName = '';
  switch(config.key) {
    case 'claim_code': sortColumnName = 'claim_code'; break;
    case 'member_name': sortColumnName = 'member_name'; break;
    case 'submission': sortColumnName = 'claim_date'; break;
    case 'bill_amount': sortColumnName = 'claimed_amount'; break;
    case 'claim_status': sortColumnName = 'claim_status_id'; break;
    default: sortColumnName = config.key;
  }
  claimListOptions.params = {
    ...claimListOptions.params, 
    order_by: sortColumnName, 
    order: config.order
  };
  fetchClaimList();  
};

const onFilter = filters => {
  let filterData = {};
  const { search_by, search, order_by, order, ...remain } = claimListOptions.params;
  if ( search_by ) {
    filterData = {...filterData, search_by };
  }
  if ( search ) {
    filterData = {...filterData, search };
  }
  if ( order_by ) {
    filterData = {...filterData, order_by };
  }
  if ( order ) {
    filterData = {...filterData, order };
  }
  const keys = Object.keys(filters);
  keys.map(key => {
    let keyName = '';
    switch(key) {
      case 'member_name': 
        keyName = 'member_id';
        if ( Array.isArray(filters[key]) && filters[key].length ) {
          let findMemberIds = filters[key].flatMap(item =>
            filterDataList.value.member_name
              .filter(member => member.full_name === item)
              .map(member => member.id)
          );
          filters[key] = findMemberIds;
        }
        break;
      case 'submission': keyName = 'claim_date'; break;
      case 'bill_amount': keyName = 'claimed_amount'; break;
      case 'claim_status': 
        keyName = 'claim_status_id';
        if ( Array.isArray(filters[key]) && filters[key].length ) {
          let findStatusIds = filters[key].flatMap(item =>
            filterDataList.value.claim_status
              .filter(status => status.status_name === item)
              .map(status => status.id)
          );
          filters[key] = findStatusIds;
        }
        break;
      default: keyName = key;
    }
    if ( Array.isArray(filters[key]) && filters[key].length ) {
      filterData[keyName] = filters[key].join("|");
    }
  });
  claimListOptions.params = {...filterData};
  fetchClaimList();
};

const onClearFilter = () => {
  const { order_by, order, search_by, search, ...remain } = claimListOptions.params;
  let params = {};
  if ( order_by ) { params = {...params, order_by}; }
  if ( order ) { params = {...params, order}; }
  if ( search_by ) { params = {...params, search_by}; }
  if ( search ) { params = {...params, search}; }
  if ( Object.keys(params).length ) { 
    claimListOptions.params = {...params};
    fetchClaimList();
  }
};

const onClearSearch = () => {
  const { search_by, search, ...remain } = claimListOptions.params;
  claimListOptions.params = {...remain};
  fetchClaimList();
};

const onClearSort = () => {
  const { order_by, order, ...remain } = claimListOptions.params;
  claimListOptions.params = {...remain};
  fetchClaimList();
};

const onAddItems = (item) => {
  if ( item.key == 'add' ) {
    router.push({ 
      name: 'TADASelectClaims'
    });
  } else if (item.key == 'itfe') {
    router.push({ 
      name: 'ITFinancialEntitlement'
    });
  }

};

//count page
const handlePageChange = newPage => {
  claimListOptions.params = {
    ...claimListOptions.params, 
    page: newPage
  };
  fetchClaimList();  
};

const handleDownloadTable=()=>{
console.log("Download");

}

const handlePageSizeChange = newSize => {
  claimListOptions.params = {
    ...claimListOptions.params, 
    pagelimit: newSize,
    page:1
  };
  fetchClaimList();  
};

const handleFilterChange = filterData => {
  //console.log("filet data : ", filterData);
};

const handleRowOpened = (data) => {
  router.push({ 
    name: 'TADAClaimDetail',         // use your route's name
    params: { id: data.claim_id }    // pass id as a route parameter
  });
}
</script>
