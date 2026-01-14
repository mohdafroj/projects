<template>
    <div class="table-container">
        <!-- Main Table -->
        <CustomOptions :tableTitle="'Medical Claim Report'" :columns="columns" :isDownload="true"
            @exportDownloadFile="handleDownloadTable" :data="claimReportData" :excludeSearch="['claim_id','ic_number','state','claim_code','pay_date','budget_head']"
            :excludeSort="['claim_id','member_name','ic_number','state','pay_date','budget_head']" :excludeFilter="['claim_id','ic_number','state','amount','claim_code','pay_date','budget_head']" @addItems="onAddItems" @search="onSearch"
            @sort="onSort" @filter="onFilter" @clearSearch="onClearSearch" @clearSort="onClearSort" />
        <CustomTable :data="pagedData" :customColumnOrder="columns" @filtersChanged="handleFilterChange"
            :show-open="false" @rowOpened="handleRowOpened">
            <!-- Custom cell: note number -->
            <template #cell-claim_id="{ cell }">
                <a class="text-indigo-600 font-medium hover:underline" href="#">
                    #{{ cell.index + 1 }}
                </a>
            </template>

            <!-- member name -->
            <template #cell-member_name="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- member_state -->
            <template #cell-ic_number="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- member_state -->
            <template #cell-state="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- claim_reference_no -->
            <template #cell-claim_track_id="{ cell }">
                <div>
                    <!-- <a class="text-indigo-600 font-medium hover:underline"
                        :href="`claim-details/${cell.row.claim_id}`"> -->
                    <a class="text-indigo-600 font-medium hover:underline" :href="`claim-details/971`">
                        {{ cell.value }}
                    </a>
                </div>
            </template>

            <!-- claim_reference_no -->
            <template #cell-claim_type="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>
            <!--claim_submission -->
            <template #cell-created_at="{ cell }">
                {{ cell.value }}
            </template>

            <!--amount_claimed -->
            <template #cell-claim_amount="{ cell }">
                {{ cell.value }}
            </template>

            <!--approved_amout -->
            <template #cell-amount="{ cell }">
                {{ cell.value }}
            </template>
            <!-- payment_status on -->
            <template #cell-status="{ cell }">
                <span class="px-2 py-1 rounded-full text-sm font-semibold" :class="{
                    'bg-blue-100 text-blue-800': cell.value === 'Submited',
                    'bg-orange-100 text-orange-800': cell.value === 'Draft' || cell.value === 'DRAFT',
                    'bg-green-100 text-green-800': cell.value === 'Paid',
                }">
                    {{ cell.value }}
                </span>
            </template>

            <!--payment_date -->
            <template #cell-pay_date="{ cell }">
                {{ cell.value }}
            </template>

            <!--budget_head -->
            <template #cell-budget_head="{ cell }">
                {{ cell.value }}
            </template>
        </CustomTable>

        <!-- Error or Loading -->
        <div v-if="claimReportData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
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
import { cghsReportList } from "@/services/rss/reportService";

const claimReportData = ref([]);
const pagedDataLoader = ref(true);
const pagedDataMessage = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const totalItems = ref(0); // This could come from an API
const totalPages = ref(0);
const perPage = ref(10);
const onCardClick = type => {
    console.log(`Card clicked: ${type}`);
    // navigate, filter, emit, etc.
};
//NEW DATA TABLE
const columns = [
    { key: "claim_id", label: "S.No", type: "Number" },
    { key: "member_name", label: "Member Name", type: "String" },
    { key: "ic_number", label: "IC Number", type: "String" },
    { key: "state", label: "Member's State / Constituency Name", type: "String" },
    { key: "claim_track_id", label: "Claim Reference No.", type: "String" },
    { key: "created_at", label: "Claim Submission", type: "Date" },
    { key: "claim_amount", label: "Amount Claimed", type: "String" },
    { key: "amount", label: "Approve Amount", type: "String" },
    { key: "status", label: "Payment Status", type: "String" },
    { key: "pay_date", label: "Payment Date", type: "Date" },
    { key: "budget_head", label: "Budget Head", type: "String" },
];
const searchQuery = ref("");
const searchColumn = ref("");
const sortQuery = ref(null);
const sortColumn = ref("");
const filterConfig = ref({});

const onSearch = query => {
    searchQuery.value = query.value;
    searchColumn.value = query.key;
    fetchCghsReportList();
};

const onSort = config => {
    sortQuery.value = config.order;
    sortColumn.value = config.key;
    fetchCghsReportList();
};

const onFilter = filters => {
    filterConfig.value = filters;
    if(filterConfig.value.created_at[0]) {
        filterConfig.value.key = "date"
        filterConfig.value.from_date = filters.created_at[0];
    }
    if(filters.created_at[1]) {
        filterConfig.value.to_date = filters.created_at[1];
    }
    //delete filterConfig.value.created_at
    fetchCghsReportList();
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
//column order"claim_id",
const columnOrder = ref([
    "claim_id",
    "member_name",
    "ic_number",
    "state",
    "claim_track_id",
    "claim_type",
    "created_at",
    "claim_amount",
    "amount",
    "status",
    "pay_date",
    "budget_head",
]);

//count page
const handlePageChange = newPage => {
    currentPage.value = newPage;
    fetchCghsReportList();
};

const handleDownloadTable = () => {
    console.log("Download");

}

const handlePageSizeChange = newSize => {
    pageSize.value = newSize;
    currentPage.value = 1; // Reset to first page
    fetchCghsReportList();
};

const handleFilterChange = filterData => {
    console.log("filet data : ", filterData);
};

const handleRowOpened = (data) => {
    console.log("data", data);

}

const pagedData = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    const totalPages = Math.ceil(totalItems.value / pageSize.value);
    console.log(claimReportData.value.slice(start, start + pageSize.value),totalPages);
    return claimReportData.value.slice(start, start + pageSize.value);
});

const fetchCghsReportList = async () => {
    const options = {
        page: parseInt(currentPage.value),
    }
    if(searchColumn.value) {
        options.key = searchColumn.value
        options.value = searchQuery.value
        searchColumn.value = '';
        searchQuery.value = '';
    } 
    if(sortColumn.value) {
        options.key = sortColumn.value
        options.order = sortQuery.value
        sortColumn.value = '';
        sortQuery.value = ''
    } 
    if(filterConfig) {
        options.key = filterConfig.value.key
        options.filter = filterConfig.value
    }
    console.log(options);
    pagedDataLoader.value = true;
    const response = await cghsReportList({params: options});
    pagedDataLoader.value = false;
    if (response.isError == false) {
        if (response.success_code == 200) {
            claimReportData.value = response.data
            totalItems.value = claimReportData.value.length;
            totalPages.value = Math.ceil(totalItems.value / pageSize.value);
            if (response.data.total) {
                // pageSize.value = response.data.per_page
                currentPage.value = response.data.current_page
                totalItems.value = response.data.total
                totalPages.value = response.data.last_page
            }
        } else {
            claimReportData.value = [];
            pagedDataMessage.value = computed(() => t("no_record"));
        }
    } else {
        pagedDataMessage.value = computed(() => t("something_wrong"));
    }
}

onMounted(() => {
    fetchCghsReportList();
    const totalPages = computed(() => Math.ceil(totalItems.value / pageSize.value));
    console.log("total",totalPages.value);
})
</script>
