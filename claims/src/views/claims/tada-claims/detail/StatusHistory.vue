<template>
  <Loading v-if="isLoading" />
  <div v-else class="table-container">

    <!-- Main Table -->
    <div>
      <table class="min-w-full text-sm text-left text-gray-700">
        <thead class="bg-blue-100 text-md text-gray-600">
          <tr>
            <th class="px-4 py-5">#NOTE NO.</th>
            <th class="px-4 py-5">SENDER</th>
            <th class="px-4 py-5">SENT TO</th>
            <th class="px-4 py-5">SENT ON</th>
            <th class="px-4 py-5">ACTION</th>
          </tr>
        </thead>
        <tbody v-if="pagedData.length">
            <tr v-for="(row, index) in pagedData" :key="index">
              <td class="px-4 py-4">{{ row.note_no }}</td>
              <td class="px-4 py-4">{{ row.sender }}</td>
              <td class="px-4 py-4">{{ row.send_to }}</td>
              <td class="px-4 py-4">{{ row.send_on }}</td>
              <td class="px-4 py-4" v-html="row.action"></td>
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
import { ref, reactive, computed, onMounted } from "vue";
import { Loading } from '@sds/oneui-common-ui';
import PaginationSelect from "./../PaginationSelect.vue";
import { useI18n } from "vue-i18n";
import { getStatusHistory } from "@/services/rss/TadaServices";
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';

const route = useRoute();
const { t, locale } = useI18n();
const isLoading = ref(false);
const claimId = ref(0);
const pagedData = ref([]);
const pagedDataMessage = ref('');
const claimListOptions = reactive({params:{pagelimit:10}});
const claimPagination = ref({from:0, current_page:0, last_page:0});

const pagiShowLabel = computed( () => t("pagi_show_label"));
const pagiPrevLabel = computed( () => t("pagi_prev_label"));
const pagiNextLabel = computed( () => t("pagi_next_label"));
const pagiTotalLabel = computed(() => t("pagi_total_label", { current: claimPagination.value.current_page, total: claimPagination.value.last_page }));

const fetchHistory = async () => {
    claimListOptions.params = {...claimListOptions.params}; 
    pagedData.value = [];
    claimPagination.value = {from:0, current_page:0, last_page:0};
    isLoading.value = true;
    const response = await getStatusHistory(claimId.value, claimListOptions);
    //console.log(response);
    isLoading.value = false;
    if ( response.isError == false ) {
      if ( response.success_code == 200 ) {
        if ( response.pagination ) {
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

onMounted( async () => {
  claimId.value = route.params.id;
  await fetchHistory();
})

//count page
const handlePageChange = newPage => {
  claimListOptions.params = {
    ...claimListOptions.params, 
    page: newPage
  };
  fetchHistory();  
};

const handlePageSizeChange = newSize => {
  claimListOptions.params = {
    ...claimListOptions.params, 
    pagelimit: newSize,
    page: 1
  };
  fetchHistory();  
};

</script>
