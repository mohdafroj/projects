<template>
    <!-- <Card title="Modal" class="mt-4"> -->
    <Button @click="showModal = true" label="View Status History" color="green" size="sm" class="" />
    <Modal v-model="showModal" :title="`Claim History ( Claim No. ${claimId})`" size="lg" disable-backdrop="true">
        <!-- Your form or content here -->
        <ClaimHistory :claimHistoryData="claimHistoryData" />
    </Modal>
    <!-- </Card> -->

    <div class="table-container">
        <!-- Main Table -->
        <CustomOptions :tableTitle="`Claim Details (Claim No. ${claimId})`" :columns="columns" :isDownload="true"
            @exportDownloadFile="handleDownloadTable" :data="claimDetailsData" :excludeSearch="['documents']"
            :excludeSort="['documents']" :excludeFilter="['documents']" @addItems="onAddItems" @search="onSearch"
            @sort="onSort" @filter="onFilter" @clearSearch="onClearSearch" @clearSort="onClearSort" />
        <CustomTable :data="claimDetailsData" :customColumnOrder="columns" @filtersChanged="handleFilterChange"
            :show-open="false" @rowOpened="handleRowOpened">
            <!-- Custom cell: note number -->
            <template #cell-item_id="{ cell }">
                <a class="text-indigo-600 font-medium hover:underline" href="#">
                    #{{ cell.index + 1 }}
                </a>
            </template>

            <!-- member name -->
            <template #cell-category_name="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- member_state -->
            <template #cell-item_name="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!--amount_claimed -->
            <template #cell-amount="{ cell }">
                {{ cell.value }}
            </template>

            <!--approved_amout -->
            <template #cell-attachment="{ cell }">
                <a class="flex justify-center" target="_blank" :href="`${cell.value}`">
                    <Icon icon="material-icon-theme:pdf" class="w-6 h-6" />
                </a>
            </template>
            <!-- payment_status on -->
            <template #cell-item_status="{ cell }">
                <span class="px-2 py-1 rounded-full text-sm font-semibold" :class="{
                    'bg-blue-100 text-blue-800': cell.value === 'Submited',
                    'bg-orange-100 text-red-800': cell.value === 'Rejaect' || cell.value === 'DRAFT',
                    'bg-green-100 text-green-800': cell.value === 'Approved',
                }">
                    {{ cell.value }}
                </span>
            </template>
        </CustomTable>

        <!-- Error or Loading -->
        <div v-if="claimDetailsData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
            <span v-if="pagedDataLoader">{{ $t('loading') }}</span>
            <span v-else>{{ pagedDataMessage }}</span>
        </div>
        <!-- Pagination -->
        <div class="bg-white">
            <PaginationSelect :currentPage="currentPage" :totalPages="totalPages" :pageSize="pageSize"
                @update:currentPage="handlePageChange" @update:pageSize="handlePageSizeChange" />
        </div>
    </div>
</template>
<script setup>
import StatCard from "@/ui-components/StatCard.vue";
import { ref, computed, onMounted } from "vue";
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import CustomTable from "./CustomTable.vue";
import CustomOptions from "./CustomOptions.vue";
import { useRoute } from 'vue-router'
import { claimDetailsList } from "@/services/rss/reportService";
import { Button, Card, Modal } from '@sds/oneui-common-ui';
import ClaimHistory from './ClaimHistory.vue'
import { Icon } from "@iconify/vue";

const route = useRoute()
const claimDetailsData = ref([]);
const claimHistoryData = ref([]);
const pagedDataLoader = ref(true);
const pagedDataMessage = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const totalItems = ref(0); // This could come from an API
const totalPages = ref(0);
const perPage = ref(10);
const showModal = ref(false);
const onCardClick = type => {
    console.log(`Card clicked: ${type}`);
    // navigate, filter, emit, etc.
};
const claimId = ref("");

const historyData = () => {
    showModal.value = true;
}

//NEW DATA TABLE
const columns = [
    { key: "item_id", label: "S.No", type: "Number" },
    { key: "category_name", label: "Category Name", type: "Number" },
    { key: "item_name", label: "Category Item", type: "String" },
    { key: "amount", label: "Amount", type: "String" },
    { key: "attachment", label: "Attach Document", type: "Amount" },
    { key: "item_status", label: "Item Status", type: "Amount" },
];
const searchQuery = ref("");
const sortConfig = ref(null);
const filterConfig = ref({});

const onSearch = query => {
    searchQuery.value = query;
    console.log("onsearch", searchQuery.value);
};

const onSort = config => {
    sortConfig.value = config;
    console.log("on sort", sortConfig.value);
};

const onFilter = filters => {
    filterConfig.value = filters;
    console.log("on filter", filterConfig.value);
};

const onClearSearch = () => {
    console.log("clear");
};

const onClearSort = () => {
    console.log("sort");
};

const onAddItems = items => {
    console.log("Add Response : ", items);
};
//END NEW TABLE OPTIONS
//column order
const columnOrder = ref([
    "item_id",
    "category_name",
    "item_name",
    "amount",
    "attachment",
    "item_status",
]);

//count page
const handlePageChange = newPage => {
    currentPage.value = newPage;
    //fetchData()
};

const handleDownloadTable = () => {
    console.log("Download");

}

const handlePageSizeChange = newSize => {
    pageSize.value = newSize;
    currentPage.value = 1; // Reset to first page
    // fetchData()
};

const handleFilterChange = filterData => {
    console.log("filet data : ", filterData);
};

const handleRowOpened = (data) => {
    console.log("data", data);

}

const fetchClaimReportList = async () => {
    const options = {
        //
    }
    const id = claimId.value;
    pagedDataLoader.value = true;
    const response = await claimDetailsList(options, id);
    console.log(response);
    pagedDataLoader.value = false;
    if (response.isError == false) {
        if (response.success_code == 200) {
            claimDetailsData.value = response.data.claim_data;
            claimHistoryData.value = response.data.history.comm_history;
            if (response.pagination) {
                pageSize.value = response.pagination.per_page
                currentPage.value = response.pagination.current_page
                totalEntries.value = response.pagination.total
                totalPages.value = response.pagination.last_page
            }
        } else {
            claimDetailsData.value = [];
            pagedDataMessage.value = computed(() => t("no_record"));
        }
    } else {
        pagedDataMessage.value = computed(() => t("something_wrong"));
    }
}

onMounted(() => {
    claimId.value = route.params.id;
    fetchClaimReportList();
})
</script>
