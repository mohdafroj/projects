<template>
    <div class="table-container">
        <!-- Main Table -->
        <CustomOptions :tableTitle="'IT CLaim Report'" :columns="columns" :isDownload="true"
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
            <template #cell-financial_entitlement="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!-- member_state -->
            <template #cell-entitlement_date="{ cell }">
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
            <template #cell-total_disbursed_amount="{ cell }">
                <div>
                    {{ cell.value }}
                </div>
            </template>

            <!--amount_claimed -->
            <template #cell-start_date="{ cell }">
                {{ cell.value }}
            </template>

            <!--approved_amout -->
            <template #cell-end_date="{ cell }">
                {{ cell.value }}
            </template>
            <!-- payment_status on -->
            <template #cell-payment_status="{ cell }">
                <span class="px-2 py-1 rounded-full text-sm font-semibold" :class="{
                    'bg-blue-100 text-blue-800': cell.value === 'Submitted',
                    'bg-green-100 text-green-800': cell.value === 'Approved',
                    'bg-red-100 text-red-800': cell.value === 'Canceled',
                    'bg-orange-100 text-orange-800': cell.value === 'Initiated',
                }">
                    {{ cell.value }}
                </span>
            </template>

            <!--payment_date -->
            <template #cell-payment_date="{ cell }">
                {{ cell.value }}
            </template>

            <!--budget_head -->
            <template #cell-budget_head="{ cell }">
                {{ cell.value }}
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
        financial_entitlement: "400000",
        entitlement_date: "2025-04-01",
        amount_claimed: "200000",
        remaining_amount: "200000",
        total_disbursed_amount: "200000",
        start_date: "2025-04-01",
        end_date: "2025-10-20",
    },
    {
        id: 1,
        financial_entitlement: "400000",
        entitlement_date: "2025-04-01",
        amount_claimed: "200000",
        remaining_amount: "200000",
        total_disbursed_amount: "200000",
        start_date: "2025-04-01",
        end_date: "2025-10-20",
    },
    {
        id: 1,
        financial_entitlement: "400000",
        entitlement_date: "2025-04-01",
        amount_claimed: "200000",
        remaining_amount: "200000",
        total_disbursed_amount: "200000",
        start_date: "2025-04-01",
        end_date: "2025-10-20",
    },
    {
        id: 1,
        financial_entitlement: "400000",
        entitlement_date: "2025-04-01",
        amount_claimed: "200000",
        remaining_amount: "200000",
        total_disbursed_amount: "200000",
        start_date: "2025-04-01",
        end_date: "2025-10-20",
    },
    {
        id: 1,
        financial_entitlement: "400000",
        entitlement_date: "2025-04-01",
        amount_claimed: "200000",
        remaining_amount: "200000",
        total_disbursed_amount: "200000",
        start_date: "2025-04-01",
        end_date: "2025-10-20",
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
    { key: "financial_entitlement", label: "Total Financial Entitlement", type: "Date" },
    { key: "entitlement_date", label: "Entitlement Date", type: "Date" },
    { key: "amount_claimed", label: "Locked/ Inprocess Claim Amount", type: "Amount" },
    { key: "remaining_amount", label: "Remaining Amount", type: "Amount" },
    { key: "total_disbursed_amount", label: "Total Disbursed Claim Amount", type: "Amount" },
    { key: "start_date", label: "Term Start Date", type: "date" },
    { key: "end_daye", label: "Term End Date", type: "date" },
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
    "financial_entitlement",
    "entitlement_date",
    "amount_claimed",
    "remaining_amount",
    "total_disbursed_amount",
    "start_date",
    "end_date",
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
