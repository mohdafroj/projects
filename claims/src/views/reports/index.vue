<template>
  <div class="p-0">
    <!-- Header Section -->
    <div class="p-1 mb-2">
      <h5 class="font-semibold text-lg">Medical Claims</h5>
      <p class="text-sm text-gray-500">Manage Medical Claims</p>
    </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 sm:gap-4 mb-6">
        <CardOne 
          title="Total Claims" 
          :amount="95"
       
          iconSrc="/assets/images/icon/total_claims.png" 
        />

        <CardOne 
          title="Completed Claims" 
          :amount="60"
       
          iconSrc="/assets/images/icon/completed_claims.svg" 
        />

        <CardOne 
          title="Pending Claims" 
          :amount="15"
       
          iconSrc="/assets/images/icon/pending_claims.svg" 
        />

        <CardOne 
          title="Overdue Claims" 
          :amount="20"

          iconSrc="/assets/images/icon/overdue_claims.svg" 
        />
      </div>
    <Card noborder>
      <!-- Messages -->
      <div v-if="successMessage" class="bg-green-200 text-green-700 p-2 mb-4 rounded-md">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="bg-red-200 text-red-700 p-2 mb-4 rounded-md">
        {{ errorMessage }}
      </div>

      <!-- Filters -->
      <div class="filters-container bg-white rounded-md">
        <TableOptions 
          :columns="columns" 
  
          :data="filteredClaims" 
          :excludeSearch="excludeSearchFields" 
          :excludeSort="excludeSortFields"
          :excludeFilter="excludeFilterFields" 
          @addItems="showAddModal" 
          @search="onSearch" 
          @sort="onSort"
          @filter="onFilter" 
          @clearSearch="onClearSearch" 
          @clearSort="onClearSort"
          @clearAllFilters="handleClearAllFilters" 
          class="table_filterred" 
        />
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="flex justify-center items-center p-1">
        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>

      <!-- Data Table -->
      <div v-else class="table-container">
        <TaDaTable 
          :data="filteredPagedData" 
          :customColumnOrder="columnOrder" 
          @filtersChanged="handleFilterChange"
          :show-open="false" 
          @rowOpened="handleRowOpened" 
          class="table_data"
        >
          <template #cell-sNo="{ cell }">{{ cell.row?.sNo }}</template>
          <template #cell-memberName="{ cell }">{{ cell.value }}</template>
          <template #cell-icNumber="{ cell }">{{ cell.value }}</template>
          <template #cell-constituency="{ cell }">{{ cell.value }}</template>
          <template #cell-beneficiaryName="{ cell }">{{ cell.value }}</template>
          <template #cell-relationship="{ cell }">{{ cell.value }}</template>
          <template #cell-treatmentName="{ cell }">{{ cell.value }}</template>
          <template #cell-claimReferenceNumber="{ cell }">{{ cell.value }}</template>
          <template #cell-claimSubmissionDate="{ cell }">{{ formatDate(cell.value) }}</template>
          <template #cell-amountClaimed="{ cell }">{{ formatCurrency(cell.value) }}</template>
          <template #cell-approvedAmount="{ cell }">{{ formatCurrency(cell.value) }}</template>
          <template #cell-paymentStatus="{ cell }">
            <span :class="getStatusClass(cell.value)">{{ cell.value }}</span>
          </template>
          <template #cell-paymentDate="{ cell }">{{ formatDate(cell.value) }}</template>
          <template #cell-budgetHead="{ cell }">{{ cell.value }}</template>
          <template #cell-action="{ cell }">
            <div class="text-left">
              <button class="p-1 mx-1 text-black-500 hover:text-blue-700" @click="editClaim(cell.row.original || cell.row)">
                <img src="/assets/images/icon/edit-btn.svg" alt="Edit" class="w-5 h-5" />
              </button>
              <button class="p-1 mx-1 text-black-500 hover:text-red-700" @click="deleteClaim(cell.row.original || cell.row)">
                <img src="/assets/images/icon/delete-btn.svg" alt="Delete" class="w-5 h-5" />
              </button>
            </div>
          </template>
        </TaDaTable>

        <!-- Pagination -->
        <div class="bg-white p-4 rounded-lg shadow-md mt-4">
          <PaginationSelect 
              :currentPage="currentPage" 
              :totalPages="totalPages" 
              :pageSize="pageSize"
              @update:currentPage="handlePageChange" 
              @update:pageSize="handlePageSizeChange" 
          />
          <p class="mt-4 text-sm text-gray-600">
            Showing 
            {{ (currentPage - 1) * pageSize + 1 }} to
            {{ Math.min(currentPage * pageSize, totalItems) }} of 
            {{ totalItems }} entries
          </p>
        </div>
      </div>
    </Card>


  </div>
</template>
 
<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from "vue";
import Card from "@/ui-components/Card.vue";
import PaginationSelect from "./PaginationSelect.vue";
import TaDaTable from "./TaDaTable.vue";
import TableOptions from "./TableOptions.vue";
import swalWithBootstrapButtons from '@/utils/swal';
import { useValidation, required, minLength, maxLength } from '@sds/oneui-validation';
import claimsData from '@/views/reports/Data.json';
import CardOne from '@/components/CardOne.vue';

// Form and validation setup
const form = reactive({
  id: null,
  memberName: '',
  icNumber: '',
  constituency: '',
  beneficiaryName: '',
  relationship: '',
  treatmentName: '',
  claimReferenceNumber: '',
  claimSubmissionDate: '',
  amountClaimed: '',
  approvedAmount: '',
  paymentStatus: '',
  paymentDate: '',
  budgetHead: ''
});

const validationSchema = {
  memberName: [required(), minLength(2), maxLength(100)],
  icNumber: [required(), minLength(2), maxLength(20)],
  constituency: [required(), minLength(2), maxLength(100)],
  beneficiaryName: [required(), minLength(2), maxLength(100)],
  relationship: [required(), minLength(2), maxLength(50)],
  treatmentName: [required(), minLength(2), maxLength(200)],
  claimReferenceNumber: [required(), minLength(2), maxLength(50)],
  claimSubmissionDate: [required(), minLength(8), maxLength(10)],
  amountClaimed: [required(), minLength(1), maxLength(20)],
  approvedAmount: [minLength(1), maxLength(20)],
  paymentStatus: [required(), minLength(2), maxLength(50)],
  paymentDate: [minLength(8), maxLength(10)],
  budgetHead: [required(), minLength(2), maxLength(100)]
};

const { errors, isValid, validateField, validateAll } = useValidation(form, validationSchema);

// Refs
const claims = ref([]);
const searchQuery = ref("");
const showModal = ref(false);
const editMode = ref(false);
const successMessage = ref("");
const errorMessage = ref("");
const isLoading = ref(false);
const currentPage = ref(1);
const pageSize = ref(10);
const totalItems = ref(0);
const currentFilters = ref({});
const currentSort = ref({ by: "", order: "asc" });

// Constants
const columns = [
  { key: "sNo", label: "S.No.", type: "Number" },
  { key: "memberName", label: "Member Name", type: "String" },
  { key: "icNumber", label: "IC Number", type: "String" },
  { key: "constituency", label: "State/Constituency", type: "String" },
  { key: "beneficiaryName", label: "Beneficiary Name", type: "String" },
  { key: "relationship", label: "Relationship", type: "String" },
  { key: "treatmentName", label: "Treatment Name", type: "String" },
  { key: "claimReferenceNumber", label: "Claim Ref No.", type: "String" },
  { key: "claimSubmissionDate", label: "Submission Date", type: "Date" },
  { key: "amountClaimed", label: "Amount Claimed", type: "Number" },
  { key: "approvedAmount", label: "Approved Amount", type: "Number" },
  { key: "paymentStatus", label: "Payment Status", type: "String" },
  { key: "paymentDate", label: "Payment Date", type: "Date" },
  { key: "budgetHead", label: "Budget Head", type: "String" },
  { key: "action", label: "Action", type: "String", sortable: false, searchable: false }
];
const columnOrder = columns.map(col => col.key);
const excludeSearchFields = ["sNo", "action"];
const excludeSortFields = excludeSearchFields;
const excludeFilterFields = excludeSearchFields;

// Computed
const totalPages = computed(() => Math.ceil(totalItems.value / pageSize.value));

const filteredClaims = computed(() => {
  let data = [...claims.value];

  // Search
  if (searchQuery.value && searchQuery.value.value && searchQuery.value.key) {
    const field = searchQuery.value.key;
    const value = searchQuery.value.value.toLowerCase();
    data = data.filter(claim =>
      claim[field]?.toString().toLowerCase().includes(value)
    );
  }

  // Filters
  if (currentFilters.value && Object.keys(currentFilters.value).length) {
    Object.entries(currentFilters.value).forEach(([key, val]) => {
      const isRangeEmpty = Array.isArray(val) && val.every(v => !v);
      const isEmptyString = typeof val === 'string' && val.trim() === '';

      if (val && !isRangeEmpty && !isEmptyString) {
        data = data.filter(claim =>
          claim[key]?.toString().toLowerCase().includes(val.toString().toLowerCase())
        );
      }
    });
  }

  // Sorting
  if (currentSort.value) {
    const { key, order } = currentSort.value;
    data.sort((a, b) => {
      const aVal = a[key]?.toString().toLowerCase() || '';
      const bVal = b[key]?.toString().toLowerCase() || '';
      return order === "asc"
        ? aVal.localeCompare(bVal)
        : bVal.localeCompare(aVal);
    });
  }
  totalItems.value = data.length;
  return data;
});

const filteredPagedData = computed(() => {
  return filteredClaims.value.slice((currentPage.value - 1) * pageSize.value, currentPage.value * pageSize.value);
});

// Helper functions
const formatDate = (date) => {
  if (!date) return 'NA';
  return date;
};

const formatCurrency = (amount) => {
  if (amount === null || amount === undefined) return 'NA';
  return new Intl.NumberFormat('en-IN', { style: 'decimal' }).format(amount);
};

const getStatusClass = (status) => {
  switch (status) {
    case 'Paid': return 'text-green-600';
    case 'Restricted Payment': return 'text-yellow-600';
    case 'Rejected': return 'text-red-600';
    case 'In Process': return 'text-blue-600';
    default: return '';
  }
};

// Methods
const fetchClaims = () => {
  isLoading.value = true;
  setTimeout(() => {
    claims.value = claimsData.map((claim, index) => ({
      sNo: index + 1,
      ...claim
    }));
    totalItems.value = claims.value.length;
    isLoading.value = false;
  }, 500);
};

const handleSubmit = async () => {
  if (await validateAll()) {
    try {
      // Simulate API call delay
      isLoading.value = true;
      setTimeout(() => {
        if (editMode.value) {
          // Update existing claim
          const index = claims.value.findIndex(c => c.id === form.id);
          if (index !== -1) {
            claims.value[index] = { ...form };
          }
          successMessage.value = "Claim updated successfully!";
        } else {
          // Add new claim
          const newId = Math.max(...claims.value.map(c => c.id), 0) + 1;
          const newClaim = {
            id: newId,
            ...form
          };
          claims.value.unshift(newClaim);
          successMessage.value = "Claim added successfully!";
        }
        
        closeModal();
        isLoading.value = false;
      }, 1000);
    } catch (error) {
      errorMessage.value = "Failed to save claim";
      isLoading.value = false;
    }
  }
};

const handleClearAllFilters = async () => {
  searchQuery.value = "";
  currentSort.value = { by: "", order: "asc" };
  currentFilters.value = {};
  currentPage.value = 1;

  await nextTick();
  fetchClaims();
};

const showAddModal = () => {
  Object.assign(form, { 
    id: null,
    memberName: '',
    icNumber: '',
    constituency: '',
    beneficiaryName: '',
    relationship: '',
    treatmentName: '',
    claimReferenceNumber: '',
    claimSubmissionDate: '',
    amountClaimed: '',
    approvedAmount: '',
    paymentStatus: '',
    paymentDate: '',
    budgetHead: ''
  });
  editMode.value = false;
  showModal.value = true;
  // Reset errors when showing modal
  Object.keys(errors).forEach(key => errors[key] = '');
};

const editClaim = (claim) => {
  Object.assign(form, claim);
  editMode.value = true;
  showModal.value = true;
  // Reset errors when showing modal
  Object.keys(errors).forEach(key => errors[key] = '');
};

const deleteClaim = (claim) => {
  swalWithBootstrapButtons.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'No, cancel!',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      claims.value = claims.value.filter(c => c.id !== claim.id);
      totalItems.value = claims.value.length;
      swalWithBootstrapButtons.fire(
        'Deleted!',
        'The claim has been deleted.',
        'success'
      );
    }
  });
};

const closeModal = () => {
  showModal.value = false;
  Object.assign(form, { 
    id: null,
    memberName: '',
    icNumber: '',
    constituency: '',
    beneficiaryName: '',
    relationship: '',
    treatmentName: '',
    claimReferenceNumber: '',
    claimSubmissionDate: '',
    amountClaimed: '',
    approvedAmount: '',
    paymentStatus: '',
    paymentDate: '',
    budgetHead: ''
  });
  // Reset errors when closing modal
  Object.keys(errors).forEach(key => errors[key] = '');
};

// Handlers
const onSearch = (query) => { searchQuery.value = query; currentPage.value = 1; };
const onSort = (config) => { currentSort.value = config; };
const onFilter = (filters) => { currentFilters.value = filters; currentPage.value = 1; };
const onClearSearch = () => { searchQuery.value = ""; };
const onClearSort = () => { currentSort.value = { by: "", order: "asc" }; };
const handlePageChange = (newPage) => { currentPage.value = newPage; };
const handlePageSizeChange = (newSize) => { pageSize.value = newSize; currentPage.value = 1; };
const handleFilterChange = (filterData) => { console.log("filter data:", filterData) };
const handleRowOpened = (data) => { console.log("data", data); };

// Watchers
watch([successMessage, errorMessage], () => {
  if (successMessage.value || errorMessage.value) {
    setTimeout(() => {
      successMessage.value = "";
      errorMessage.value = "";
    }, 7000);
  }
});

// Lifecycle
onMounted(() => {
  fetchClaims();
});
</script>

<style scoped>
:deep(.table-cell) {
  padding: 0.5rem;
}

:deep(.action-button) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
  border-radius: 0.25rem;
}

.p-6 {
  padding: 0px !important;
}

.table_filterred,
.table_data {
  margin-top: 0px !important;
}

::v-deep(.p-6) {
  padding: 0px !important;
}

.text-green-600 {
  color: #16a34a;
}
.text-yellow-600 {
  color: #ca8a04;
}
.text-red-600 {
  color: #dc2626;
}
.text-blue-600 {
  color: #2563eb;
}
</style>