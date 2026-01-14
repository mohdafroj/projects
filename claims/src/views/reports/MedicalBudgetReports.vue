<template>
    <div class="table-container">
        <!-- Main Table -->
        <CustomOptions :tableTitle="'Medical Budget Report'" :columns="columns" :isDownload="true"
            @exportDownloadFile="handleDownloadTable" :data="pagedData" :excludeSearch="['documents']"
            :excludeSort="['documents']" :excludeFilter="['documents']" @addItems="onAddItems" @search="onSearch"
            @sort="onSort" @filter="onFilter" @clearSearch="onClearSearch" @clearSort="onClearSort" />
        <CustomTable :data="pagedData" :customColumnOrder="columns" @filtersChanged="handleFilterChange"
            :show-open="false" @rowOpened="handleRowOpened">
            <!-- Custom cell: note number -->
            <template #cell-id="{ cell }">
                <a class="text-indigo-600 font-medium hover:underline" href="#">
                    #{{ cell.value }}
                </a>
            </template>

            <!-- member name -->
            <template #cell-budget_head_name="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- member_state -->
            <template #cell-total_allocated_budget="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- claim_reference_no -->
            <template #cell-amount_claimed="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>
            <!--claim_submission -->
            <template #cell-remaining_amount="{ cell }">
                {{ cell.value }}
            </template>
            <!-- item_category -->
            <template #cell-total_expense="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

        </CustomTable>

        <!-- Pagination -->
        <div class="bg-white">
            <PaginationSelect :currentPage="currentPage" :totalPages="totalPages" :pageSize="pageSize"
                @update:currentPage="handlePageChange" @update:pageSize="handlePageSizeChange" />
        </div>
    </div>
</template>
<script setup>
import StatCard from "@/ui-components/StatCard.vue";
import { ref, computed } from "vue";
import PaginationSelect from "@/ui-components/PaginationSelect.vue";
import CustomTable from "./CustomTable.vue";
import CustomOptions from "./CustomOptions.vue";

const tadaClaimData = ref([
    {
        id: 1,
        budget_head_name: "TEST",
        total_allocated_budget: "200000",
        amount_claimed: "50000",
        remaining_amount: "50000",
        total_expense: "100000",
    },
    {
        id: 2,
        budget_head_name: "TEST",
        total_allocated_budget: "200000",
        amount_claimed: "50000",
        remaining_amount: "50000",
        total_expense: "100000",
    },
    {
        id: 3,
        budget_head_name: "TEST",
        total_allocated_budget: "200000",
        amount_claimed: "50000",
        remaining_amount: "50000",
        total_expense: "100000",
    },
    {
        id: 4,
        budget_head_name: "TEST",
        total_allocated_budget: "200000",
        amount_claimed: "50000",
        remaining_amount: "50000",
        total_expense: "100000",
    },
    {
        id: 5,
        budget_head_name: "TEST",
        total_allocated_budget: "200000",
        amount_claimed: "50000",
        remaining_amount: "50000",
        total_expense: "100000",
    },
]);

const currentPage = ref(1);
const pageSize = ref(10);
const totalItems = ref(tadaClaimData.value.length); // This could come from an API
const totalPages = computed(() => Math.ceil(totalItems.value / pageSize.value));
const onCardClick = type => {
    console.log(`Card clicked: ${type}`);
    // navigate, filter, emit, etc.
};
//NEW DATA TABLE
const columns = [
    { key: "id", label: "ID", type: "Number" },
    { key: "budget_head_name", label: "MEMBER NAME", type: "String" },
    { key: "total_allocated_budget", label: "MEMBER STATE", type: "Amount" },
    { key: "amount_claimed", label: "Locked/ Inprocess Claim Amount", type: "Amount" },
    { key: "remaining_amount", label: "Remaining Amount", type: "Amount" },
    { key: "total_expense", label: "Total Expense", type: "Amount" },
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
    "id",
    "budget_head_name",
    "total_allocated_budget",
    "amount_claimed",
    "remaining_amount",
    "total_expense",
]);

//paginated Data send to table
const pagedData = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return tadaClaimData.value.slice(start, start + pageSize.value);
});

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
</script>
