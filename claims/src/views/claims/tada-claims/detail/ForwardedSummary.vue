<template>
  <Loading v-if="isLoading" />
  <div v-else class="table-container">

    <!-- Main Table -->
    <div>
      <table class="min-w-full text-sm text-left text-gray-700">
        <thead class="bg-blue-100 text-md text-gray-600">
          <tr>
            <th class="px-4 py-5">SR NO.</th>
            <th class="px-4 py-5">CLAIM ID</th>
            <th class="px-4 py-5">SENT TO</th>
            <th class="px-4 py-5">REMARK</th>
            <th class="px-4 py-5">SENT ON</th>
            <th class="px-4 py-5">READ ON</th>
            <th class="px-4 py-5">ACTION</th>
          </tr>
        </thead>
        <tbody v-if="pagedData.length">
            <tr v-for="(row, index) in pagedData" :key="index">
              <td class="px-4 py-4">{{ index + Number(claimPagination.from) }}</td>
              <td class="px-4 py-4">{{ row.claim_code }}</td>
              <td class="px-4 py-4">{{ row.send_to }}</td>
              <td class="px-4 py-4" v-html="row.remark"></td>
              <td class="px-4 py-4">{{ row.send_at }}</td>
              <td class="px-4 py-4">{{ row.opened_at }}</td>
              <td class="px-4 py-4" v-if="row.can_pull_back == 1">
                <Button @click="() => handlePullback(row.claim_id)" label="Pull Back" color="gray-outline" size="sm" />
              </td>
              <td class="px-4 py-4" v-else></td>
            </tr>
        </tbody>
      </table>

      <div v-if="pagedData.length == 0" class="bg-white mb-3 p-2 text-center">
        <span>{{pagedDataMessage}}</span>
      </div>
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
import { ref, reactive, watch, computed, onMounted } from "vue";
import { Loading, Button } from '@sds/oneui-common-ui';
import PaginationSelect from "./../PaginationSelect.vue";
import { useI18n } from "vue-i18n";
import { forwardedClaim, pullbackClaim } from "@/services/rss/itEquipmentsService";
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

const router = useRouter();
const { t, locale } = useI18n();
const isLoading = ref(true);
const tableHeading = computed( () => t("menu.it_claim") + " " + t("forwared_claims"));
const pagiShowLabel = computed( () => t("pagi_show_label"));
const pagiPrevLabel = computed( () => t("pagi_prev_label"));
const pagiNextLabel = computed( () => t("pagi_next_label"));
const pagiTotalLabel = computed(() => t("pagi_total_label", { current: claimPagination.value.current_page, total: claimPagination.value.last_page }));

const pagedData = ref([]);
const pagedDataMessage = ref('');
const claimListOptions = reactive({params:{pagelimit:10}});
const claimPagination = ref({from:0, current_page:0, last_page:0});

const fetchClaimList = async () => {
    claimListOptions.params = {...claimListOptions.params}; 
    pagedData.value = [];
    claimPagination.value = {from:0, current_page:0, last_page:0};
    isLoading.value = true;
    const response = await forwardedClaim(claimListOptions);
    isLoading.value = false;
    if ( response.isError == false ) {
      if ( response.success_code == 200 ) {
        if (response.pagination) {
          claimPagination.value = response.pagination;
        }
        pagedData.value = response.data;
      } else {
        pagedData.value = [];
      }
      if ( pagedData.value.length == 0 ) {
        pagedDataMessage.value = computed( () =>t("no_record"));
      }
    } else {
      pagedDataMessage.value = computed( () =>t("something_wrong"));
    }
}

const handlePullback = async (id) => {
  const response = await pullbackClaim(id);
  if ( response.isError || response?.success_code != 200 ) {
    Swal.fire({
      icon: 'error',
      title: tableHeading.value,
      text: response.description || response.error || t("something_wrong"),
      confirmButtonText: 'OK',
      confirmButtonColor: '#4bc66d'
    });
  } else {
    router.push({ 
      name: 'ITClaimDetail',
      params: {id}
    });
  }
}

onMounted( async () => {
  await fetchClaimList();
})

const onSearch = query => {
  let searchColumnName = '';
  switch(query.key) {
    case 'submission': searchColumnName = 'claim_date'; break;
    case 'bill_amount': searchColumnName = 'amount'; break;
    case 'claim_status': searchColumnName = 'status'; break;
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
    case 'submission': sortColumnName = 'claim_date'; break;
    case 'bill_amount': sortColumnName = 'amount'; break;
    case 'claim_status': sortColumnName = 'status'; break;
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
    let filterColumnName = '';
    switch(key) {
      case 'submission': filterColumnName = 'claim_date'; break;
      case 'bill_amount': filterColumnName = 'amount'; break;
      case 'claim_status': filterColumnName = 'status'; break;
      default: filterColumnName = key;
    }
    if ( Array.isArray(filters[key]) && filters[key].length ) {
      filterData[filterColumnName] = filters[key].join("|");
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

const onAddItems = items => {
  console.log("Add Response : ", items);
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
    page: 1
  };
  fetchClaimList();  
};

const handleFilterChange = filterData => {
  //console.log("filet data : ", filterData);
};

const handleRowOpened = (data) => {
  router.push({ 
    name: 'ITClaimDetail',         // use your route's name
    params: { id: data.claim_id }    // pass id as a route parameter
  });
}
</script>
