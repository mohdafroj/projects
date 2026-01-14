<template>
    <CustomTable :data="historyData" :customColumnOrder="columns" @filtersChanged="handleFilterChange"
        :show-open="false" @rowOpened="handleRowOpened">
        <!-- Custom cell: note number -->
        <template #cell-id="{ cell }">
            {{ cell.index + 1 }}
        </template>

        <!-- member name -->
        <template #cell-title="{ cell }">
            <div>
                {{ cell.value }}
            </div>
        </template>

        <!-- member_state -->
        <template #cell-action="{ cell }">
            <div>
                {{ cell.value }}
            </div>
        </template>

        <!--amount_claimed -->
        <template #cell-actor_id="{ cell }">
            {{ cell.value }}
        </template>

        <!--approved_amout -->
        <template #cell-created_at="{ cell }">
            {{ cell.value }}
        </template>
        <!-- payment_status on -->
        <template #cell-status="{ cell }">
            <span class="px-2 py-1 rounded-full text-sm font-semibold" :class="{
                'bg-blue-100 text-blue-800': cell.value === '0' || cell.value === 'DRAFT',
                'bg-orange-100 text-red-800': cell.value === 'Rejaect',
                'bg-green-100 text-green-800': cell.value === 'Approved',
            }">
                {{ cell.value }}
            </span>
        </template>
        <!-- remark on -->
        <template #cell-description="{ cell }">
            {{ cell.value }}
        </template>
    </CustomTable>

    <!-- Error or Loading1 -->
    <div v-if="historyData.length == 0" class="bg-white mb-3 p-2 text-center font-bold">
        <span v-if="pagedDataLoader">{{ $t('loading') }}</span>
        <span v-else>{{ pagedDataMessage }}</span>
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
import { Button, Card, Modal } from '@sds/oneui-common-ui'

const route = useRoute()
const pagedDataLoader = ref(true);
const pagedDataMessage = ref('');
const currentPage = ref(1);
const pageSize = ref(10);
const totalItems = ref(0); // This could come from an API
const totalPages = ref(0);
const perPage = ref(10);
const showModal = ref(false);

const columns = [
    { key: "id", label: "S.No", type: "Number" },
    { key: "item_type", label: "Category Name", type: "String" },
    { key: "action", label: "Action Type", type: "String" },
    { key: "actor_name", label: "Action Name", type: "String" },
    { key: "created_at", label: "Date", type: "Date" },
    { key: "status", label: "Status", type: "String" },
    { key: "description", label: "Remarks", type: "String" },
];

const onCardClick = type => {
    console.log(`Card clicked: ${type}`);
    // navigate, filter, emit, etc.
};
const claimId = ref("");

const props = defineProps({
    claimHistoryData: Array,
})

const historyData = computed(() => {
    return props.claimHistoryData;
});

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

</script>
