<template>
  <Loading v-if="isLoading" />
  <div class="p-6 min-h-screen">    
    <!-- Form Mode -->
    <div>
      <!-- User Search Section -->
      <div class="mb-6 dark:bg-black-800 dark:text-slate-300">
        <div class="relative">
          <input type="text" v-model="searchQuery" @input="searchMembers" placeholder="Search by Name or IC number..."
            class="w-full p-3 border border-gray-300 rounded-lg dark:bg-black-800 dark:text-slate-300 dark:border-gray-600" />
          <div v-if="searchResults.length > 0"
            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto dark:bg-black-800 dark:text-slate-300">
            <div v-for="member in searchResults" :key="member.id" @click="selectMember(member)"
              class="p-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700 border-b border-gray-200 last:border-b-0">
              <div class="font-medium">{{ member.full_name }}</div>
              <div class="text-sm text-gray-500">IC Number: #{{ member.core_user_id }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Selected Member and Finance Details -->
      <div v-if="selectedMember" class="mb-6">
        <Card class="mb-4">
          <h2 class="text-xl font-bold text-gray-800">{{ selectedMember.full_name }}</h2>
          <p class="text-gray-600">{{ financeDetails?.terms_detail }} ({{ financeDetails?.terms }})</p>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-gray-50 p-3  dark:bg-slate-700 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Financial Entitlement</h3>
              <p class="text-lg font-bold dark:text-slate-300">₹{{ financeDetails.financial_amount || 0 }}</p>
            </div>
            <div class="bg-gray-50 p-3 dark:bg-slate-700 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Utilized Amount</h3>
              <p class="text-lg font-bold dark:text-slate-300">₹{{ financeDetails.utlized_amount || 0 }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700  p-3 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Under Process Claims Amount</h3>
              <p class="text-lg font-bold dark:text-slate-300">₹{{ financeDetails.hold_amount || 0 }}</p>
            </div>
            <div class="bg-gray-50  dark:bg-slate-700 p-3 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Remaining Amount</h3>
              <p class="text-lg font-bold dark:text-slate-300">₹{{ financeDetails.remaining_amount || 0 }}</p>
            </div>
          </div>
        </Card>
      </div>
    </div>    
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { Loading, Card } from "@sds/oneui-common-ui";

import {
  getMembers,
  getFinanceDetails,
  getAccountDetails,
  searchProducts,
} from "@/services/rss/SubmitItEquipementClaim";

const isLoading = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const selectedMember = ref(null);
const financeDetails = ref({ total_amount: 0, milled_amount: 0, claims_amount: 0, remaining_amount: 0, terms_detail: '' });
const accountDetails = ref({});
const claimItems = ref([{ product_name: '', quantity: 1, price: 0, showSuggestions: false }]);
const productSuggestions = ref([]);

// Search members
const searchMembers = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  try {
    isLoading.value = true;
    const response = await getMembers();
    if (response.isError === false && response.success_code === 200) {
      searchResults.value = response.data.filter(member =>
        member.full_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        member.core_user_id.toString().includes(searchQuery.value)
      );
    }
  } catch (error) {
    console.error('Error searching members:', error);
  } finally {
    isLoading.value = false;
  }
};

// Select a member
const selectMember = async (member) => {
  selectedMember.value = member;
  searchResults.value = [];
  searchQuery.value = '';

  try {
    isLoading.value = true;
    const [financeResponse, accountResponse, productResponse] = await Promise.all([
      getFinanceDetails(member.core_user_id),
      getAccountDetails(member.core_user_id),
      searchProducts(),
    ]);

    if (!financeResponse.isError && financeResponse.success_code === 200) {
      financeDetails.value = financeResponse.data;
    }
    if (!accountResponse.isError && accountResponse.success_code === 200) {
      accountDetails.value = accountResponse.data;
    }
    if (!productResponse.isError && productResponse.success_code === 200) {
      productSuggestions.value = productResponse.data.values.map(prod => ({
        id: prod.id,
        key_value: prod.key_value
      }));
    }
  } catch (error) {
    console.error('Error fetching member details:', error);
  } finally {
    isLoading.value = false;
  }
};


// Close suggestions when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.relative')) {
    claimItems.value.forEach(item => {
      item.showSuggestions = false;
    });
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.border-dashed {
  border-style: dashed;
}


td{border:nonne !important;}
.multiselect{
  background-color: transparent;
}

</style>
<style src="vue-multiselect/dist/vue-multiselect.css"></style>