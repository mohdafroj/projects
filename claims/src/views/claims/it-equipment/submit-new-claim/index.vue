<template>
  <Loading v-if="isLoading" />
  <div class="p-6 min-h-screen">
    <!-- Preview Mode -->
    <div v-show="showPreview">
      <ClaimPreview v-if="Object.keys(previewData).length" :claimData="previewData" @edit="showPreview = false"
        @submit="finalSubmit" />
       <Invoice 
        ref="pdfGenerator"
        :claimData="pdfClaimData" 
        @pdf-generated="handlePdfGenerated"
        class="mt-4"
      />
    </div>
    

    <!-- Form Mode -->
    <div v-show="!showPreview">
      <!-- User Search Section -->
      <div class="mb-6 dark:bg-black-800 dark:text-slate-300" v-if="!selectedMember" >
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
              <p class="text-lg font-bold dark:text-slate-300">{{ useLocalCurrency(financeDetails.financial_amount || 0) }}</p>
            </div>
            <div class="bg-gray-50 p-3 dark:bg-slate-700 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Utilized Amount</h3>
              <p class="text-lg font-bold dark:text-slate-300">{{ useLocalCurrency(financeDetails.utlized_amount || 0) }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700  p-3 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Under Process Claims Amount</h3>
              <p class="text-lg font-bold dark:text-slate-300">{{ useLocalCurrency(financeDetails.hold_amount || 0) }}</p>
            </div>
            <div class="bg-gray-50  dark:bg-slate-700 p-3 rounded-lg shadow">
              <h3 class="text-sm font-medium text-gray-500">Remaining Amount</h3>
              <p class="text-lg font-bold dark:text-slate-300">{{ useLocalCurrency(financeDetails.remaining_amount || 0) }}</p>
            </div>
          </div>
        </Card>

        <!-- Header Section -->
     <div class="mb-6 p-2 flex justify-between items-center">
  <div>
    <h1 class="text-xl font-bold text-gray-800">Add a New Claim</h1>
    <p class="text-gray-600">Please fill the Claim detail</p>
  </div>

  <button
    @click="showAccountDetails = !showAccountDetails"
    class="text-sm text-blue-600 hover:text-blue-800 font-medium border-b border-blue-600"
  >
    {{ showAccountDetails ? 'Hide' : 'View' }} Account Information
  </button>
</div>


        <!-- Account Details Section -->
        <div class="mb-6 p-0 rounded-md ">
          <!--  <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">&nbsp;</h2>
            <div class="flex space-x-2">
         
             <button @click="showAccountDetails = !showAccountDetails"
                class="bg-green-500 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-full">
            Previous Claims
              </button> 
            </div>
          </div> -->

          <div v-if="showAccountDetails" class="p-4 shadow-base2 rounded-lg bg-white">
            <h2 class="text-xl font-bold text-gray-800">Account Detail</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name:</label>
                <p class="font-medium">{{ accountDetails.bank_name || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Type:</label>
                <p class="font-medium">{{ accountDetails.account_type || 'N/A' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account No.</label>
                <p class="font-medium">{{ accountDetails.account_number || 'N/A' }}</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Branch Name:</label>
              <p class="font-medium">{{ accountDetails.branch_name || 'N/A' }}</p>
            </div>
            <!-- <p class="mt-3 text-sm text-blue-600 cursor-pointer hover:underline">
              To update your bank details, please click here
            </p> -->
          </div>
        </div>

        <!-- Invoice Upload Section -->
        <Card>
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Upload  Claim Invoice/Files</h2>
          </div>
          <FileUploads ref="uploadRef" :isRequired="true" :onFileUpload="postMethod" :multiple="true" @update:files="handleFiles" />
        </Card>

     <!-- Claim Details Section -->
<Card class="mt-4">
  <h2 class="text-xl font-bold text-gray-800 dark:text-slate-200 mb-4">
    Claim Detail
  </h2>

  <div class="space-y-6">
    <!-- Loop items -->
<div v-for="(item, index) in claimItems" :key="index">
  <div
    :class="[
      'grid gap-4 p-1 rounded-lg dark:border-slate-700 dark:bg-slate-800',
      item.checkField === '20)Accessories & Ancillary Items' || item.checkField === '21)OTHER'
        ? 'md:grid-cols-[2.5fr_1.5fr_1fr_1fr_auto]'
        : 'md:grid-cols-[2.5fr_1fr_1fr_auto]'
    ]"
  >

    <!-- Product Name -->
    <div>
      <label class="block text-sm font-medium text-gray-800 dark:text-slate-300  mb-1">
        Select Category <span class="text-red-600">*</span>
      </label>
      <Multiselect
        v-model="item.key_value"
        @focus="showProductSuggestions(index)"
        @update:modelValue="onProductSelected(item, index)"
        placeholder="Enter Category name"
        :options="productSuggestions"
        :searchable="true"
        label="key_value"
        track-by="id"
        class="w-full"
        :class="{ 'border-red-500': validationErrors[index]?.category }"
      />
      <span v-if="validationErrors[index]?.category" class="text-red-500 text-xs mt-1">
        {{ validationErrors[index].category }}
      </span>
    </div>

    <!-- Sub Product -->
    <template v-if="item.checkField === '20)Accessories & Ancillary Items'">
      <div>
        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1">
          Select Product
        </label>
        <Multiselect
          v-model="item.key_value_h"
          @focus="SubSelectData(index)"
          @update:modelValue="validateClaimItem(index, 'subProduct')"
          placeholder="Enter sub-product name"
          :options="productSubSelections"
          :searchable="true"
          :close-on-select="true"
          :clear-on-select="false"
          :allow-empty="true"
          label="key_value"
          track-by="id"
          class="w-full"
          :class="{ 'border-red-500': validationErrors[index]?.subProduct }"
        />
        <span v-if="validationErrors[index]?.subProduct" class="text-red-500 text-xs mt-1">
          {{ validationErrors[index].subProduct }}
        </span>
      </div>
    </template>

    <template v-else-if="item.checkField === '21)OTHER'">
      <div>
        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1">
          Product Name <span class="text-red-600">*</span>
        </label>
        <input
          type="text"
          v-model="item.product_name"
          @blur="validateClaimItem(index, 'productName')"
          @input="validateClaimItem(index, 'productName')"
          class="w-full p-2 border rounded dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
          :class="{ 'border-red-500': validationErrors[index]?.productName }"
          placeholder="Enter product name"
        />
        <span v-if="validationErrors[index]?.productName" class="text-red-500 text-xs mt-1">
          {{ validationErrors[index].productName }}
        </span>
      </div>
    </template>

    <!-- Qty -->
    <div>
      <label class="block text-sm font-medium text-gray-800 dark:text-slate-300 mt-1">
        Quantity <span class="text-red-600">*</span>
      </label>
      <input
        type="number"
        v-model="item.quantity"
        @blur="validateClaimItem(index, 'quantity')"
        @input="validateClaimItem(index, 'quantity')"
        min="1"
        step="1"
        placeholder="Quantity"
        class="w-full p-2 border rounded dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
        :class="{ 'border-red-500': validationErrors[index]?.quantity }"
      />
      <span v-if="validationErrors[index]?.quantity" class="text-red-500 text-xs mt-1">
        {{ validationErrors[index].quantity }}
      </span>
    </div>

    <!-- Price -->
    <div>
      <label class="block text-sm font-medium text-gray-800 dark:text-slate-300 mb-1">
        Price Per Unit <span class="text-red-600">*</span>
      </label>
      <input
        type="number"
        v-model="item.price"
        @blur="validateClaimItem(index, 'price')"
        @input="validateClaimItem(index, 'price')"
        min="0"
        step="0.01"
        placeholder="Price"
        class="w-full p-2 border rounded dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
        :class="{ 'border-red-500': validationErrors[index]?.price }"
      />
      <span v-if="validationErrors[index]?.price" class="text-red-500 text-xs mt-1">
        {{ validationErrors[index].price }}
      </span>
    </div>

    <!-- ❌ Button -->
    <div class="items-end justify-center remove-button mt-8">
      <button
        @click="() => removeItem(index)"
        class="text-red-600 hover:text-red-800"
        title="Remove"
      >
        <!-- Inline SVG Cross Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" 
            class="w-5 h-5 mt-2" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor" 
            stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Description -->
    <div>
      <label class="block text-sm font-medium text-gray-800 dark:text-slate-300 mb-1">
        Description
      </label>
      <textarea
        type="text"
        v-model="item.description"
        @blur="validateClaimItem(index, 'description')"
        @input="validateClaimItem(index, 'description')"
        placeholder="Enter description"
        class="w-full p-2 border rounded dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
        :class="{ 'border-red-500': validationErrors[index]?.description }"
      >
      </textarea>
      <span v-if="validationErrors[index]?.description" class="text-red-500 text-xs mt-1">
        {{ validationErrors[index].description }}
      </span>
    </div>

    <!-- Invoice number -->
    <div>
      <label class="block text-sm font-medium text-gray-800 dark:text-slate-300 mb-1">
        Invoice Number
      </label>
      <input
        type="text"
        v-model="item.invoice_no"
        @blur="() => validateClaimItem(index, 'invoice_no', 'blur')"
        @input="() => validateClaimItem(index, 'invoice_no')"
        placeholder="Enter invoice number"
        class="p-2 border rounded dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
        :class="{ 'border-red-500': validationErrors[index]?.invoice_no }"
      />
      <span v-if="validationErrors[index]?.invoice_no" class="text-red-500 text-xs mt-1">
        {{ validationErrors[index].invoice_no }}
      </span>
    </div>

    <!-- Invoice Date -->
    <div>
      <label class="block text-sm font-medium text-gray-800 dark:text-slate-300 mb-1">
        Invoice Date
      </label>
      <input
        type="date"
        v-model="item.invoice_date"
        :min="minDate"
        :max="maxDate"
        class="w-full p-2 border rounded dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
      />
    </div>
    
  </div>

  <!-- Divider (only between items) -->
  <hr v-if="index < claimItems.length - 1" class="my-4 border-gray-300 dark:border-slate-600" />
</div>

<hr />

    <!-- Add More Button -->
    <div class="flex justify-start">
      <button
        @click="addItem"
        :disabled="claimItems.length >= maxItemLimit"
        class="flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
        :class="claimItems.length >= maxItemLimit ? 'text-gray-500 hover:text-gray-700': 'text-blue-600 hover:text-blue-800'"
        :title="'You can add up to ' + maxItemLimit + ' items'"
      >
        <svg
          class="w-5 h-5 mr-1"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
          ></path>
        </svg>
        Add More
      </button>
    </div>

    <!-- Total -->
    <div class="flex justify-end text-xl font-bold dark:text-slate-200">
      <span class="mr-4">Total Amount:</span>
      <span>{{ useLocalCurrency(totalAmount) }}</span>
    </div>
  </div>
</Card>

        <!-- Declaration Section -->
        <div class="mt-4 mb-4">
          <div class="p-4 rounded-lg">
            <div class="mt-4 flex items-center">
              <input id="declaration" type="checkbox" v-model="declarationAccepted"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" />
              <label for="declaration" class="ml-2 block text-sm text-gray-900 dark:text-slate-300">
                I confirm that I have reviewed the shared content.
              </label>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-center gap-4">
          <button @click="resetForm" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
          <button @click="submitForm" :disabled="!isFormValid"
            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Preview
          </button>
        </div>
      </div>
    </div>

    <!-- File Preview Modal -->
    <div v-if="showPreviewModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-screen overflow-auto">
        <div class="flex justify-between items-center p-4 border-b">
          <h3 class="text-lg font-medium">File Preview</h3>
          <button @click="showPreviewModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="p-4">
          <iframe v-if="previewFileUrl" :src="previewFileUrl" class="w-full h-96" frameborder="0"></iframe>
          <div v-else class="flex items-center justify-center h-96">
            <p class="text-gray-500">Preview not available for this file type</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- <Modal
    :modelValue="true"
    :disableBackdrop="true"
    title="Update item details"
    size="full"
  >
  <iframe :src="url" style="width:100%; height:100%; border:0" title="PDF preview"></iframe>
  </Modal> -->
  </template>

  <script setup>
  import { ref, computed, onMounted, onUnmounted } from "vue";
  import { Loading, Card, FileUploads, Modal } from "@sds/oneui-common-ui";
import Swal from 'sweetalert2';

import {
  getMembers,
  getFinanceDetails,
  getAccountDetails,
  getInvoiceDetail,
  searchProducts,
  submitClaim,
  getClaimTrackId,
  getChildCategories
} from "@/services/rss/SubmitItEquipementClaim";
import Multiselect from "vue-multiselect";
import { useRouter } from 'vue-router';
import { cookieService } from '@sds/oneui-layout';
import ClaimPreview from './ClaimPreview.vue';
import Invoice from './invoice.vue'; 
import { isSwal } from "@/utils/isSwal";
import { postMethod } from "@/composables/useApi";
import useLocalCurrency from "@/composables/useLocalCurrency";
import useLocalDate from "@/composables/useLocalDate";

const router = useRouter();
const isLoading = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
//const selectedMember = ref({core_user_id:''});
const selectedMember = ref(null);
const financeDetails = ref({ total_amount: 0, milled_amount: 0, claims_amount: 0, remaining_amount: 0, terms_detail: '' });
const accountDetails = ref({});
const showAccountDetails = ref(false);
const uploadedFiles = ref([]);
const uploadRef = ref(null);
const claimItems = ref([{ product_name: '', quantity: 1, price: 0, invoice_no:'', invoice_date:'', description:'', showSuggestions: false }]);
const productSuggestions = ref([]);
const productSubSelections = ref([]);
const declarationAccepted = ref(false);
const showPreviewModal = ref(false);
const previewFileUrl = ref('');
const showPreview = ref(false);
const previewData = ref({});
const maxItemLimit = ref(20);
const claimId = ref('');
const maxDate = computed(() => {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0"); // Months are 0-based
  const dd = String(d.getDate()).padStart(2, "0");
  return `${dd}-${mm}-${yyyy}`;
});

const minDate = computed(() => {
  const d = new Date();
  d.setFullYear(d.getFullYear() - 6); // Subtract 6 years
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0"); // Months are 0-based
  const dd = String(d.getDate()).padStart(2, "0");
  return `${dd}-${mm}-${yyyy}`;
});

// Validation errors
const validationErrors = ref([{}]);

// generating pdf 
const pdfGenerator = ref(null);
const isPdfGenerated = ref(false);
const url = ref('');

//const claimIDCode = computed(() => `${selectedMember.value?.core_user_id}IT${Date.now().toString().slice(-4)}`)
// Get user data
const userAppData = cookieService.getData({
  name: "userAppData",
  non_primitive: 1,
  decode: 1,
});

// Validation functions
const validateRequired = (value, fieldName) => {
  if (!value || (typeof value === 'string' && value.trim() === '')) {
    return `${fieldName} is required`;
  }
  return '';
};

const validateNumber = (value, fieldName, min = null, max = null) => {
  const error = validateRequired(value, fieldName);
  if (error) return error;
  
  const num = Number(value);
  if (isNaN(num)) {
    return `${fieldName} must be a valid number`;
  }
  
  if (min !== null && num < min) {
    return `${fieldName} must be at least ${min}`;
  }
  
  if (max !== null && num > max) {
    return `${fieldName} must be at most ${max}`;
  }
  
  return '';
};

const validateString = (value, fieldName, minLength = 1, maxLength = 100) => {
  const error = validateRequired(value, fieldName);
  if (error) return error;
  
  const str = String(value).trim();
  if (str.length < minLength) {
    return `${fieldName} must be at least ${minLength} characters`;
  }
  
  if (str.length > maxLength) {
    return `${fieldName} must be at most ${maxLength} characters`;
  }
  
  return '';
};

// Validate individual claim item field
const validateClaimItem = async (index, field, typeOfEvent) => {
  if (!validationErrors.value[index]) {
    validationErrors.value[index] = {};
  }
  
  const item = claimItems.value[index];
  let error = '';
  
  switch (field) {
    case 'category':
      if (!item.key_value || !item.key_value.key_value) {
        error = 'Category selection is required';
      }
      break;
      
    case 'subProduct':
      if (item.checkField === '20)Accessories & Ancillary Items' && (!item.key_value_h || !item.key_value_h.key_value)) {
        error = 'Sub-product selection is required';
      }
      break;
      
    case 'productName':
      if (item.checkField === '21)OTHER') {
        error = validateString(item.product_name, 'Product name', 2, 100);
      }
      break;
      
    case 'quantity':
      error = validateNumber(item.quantity, 'Quantity', 1, 999);
      if (!error && !Number.isInteger(Number(item.quantity))) {
        error = 'Quantity must be a whole number';
      }
      break;
      
    case 'description':
      const description = item.description.trim();
      if ( description.length > 200 ) {
        error = 'Description should not exceed 200 characters.';
      }
      break;
      
    case 'invoice_no':
      const regex = /^[A-Za-z0-9]+$/;
      if ( item.invoice_no.trim() != '' && regex.test(item.invoice_no) == false ) {
        error = 'Invoice number must be alphanumeric.';
      } else {
        if ( typeOfEvent == 'blur' ) {
          const response = await getInvoiceDetail({invoice_no: item.invoice_no.trim(), core_user_id: selectedMember.value.core_user_id });
          if ( response.success_code == 200 ) {
            let itemNames = '';
            const totalInvoices = response.data.length;
            if ( totalInvoices == 1 ) {
              const route = router.resolve({ name: 'ITClaimDetail', params: { id: response.data[0]['claim-id'] } });
                itemNames = `<a href="${route.href}" style="color:#3085d6;text-decoration:underline;" target="_blank">
                  ${response.data[0]['claim-code']}
                </a>`;
            } else {
              response.data.map((item, index) => {
                const route = router.resolve({ name: 'ITClaimDetail', params: { id: item['claim-id'] } });
                if (totalInvoices === index + 1) {
                  itemNames = `${itemNames.slice(0, -2)} and <a href="${route.href}" style="color:#3085d6;text-decoration:underline;" target="_blank">
                    ${item['claim-code']}
                  </a>`;
                } else {
                  itemNames += `<a href="${route.href}" style="color:#3085d6;text-decoration:underline;" target="_blank">
                    ${item['claim-code']}
                  </a>, `;
                }
              });
            }
            if ( itemNames != '' ) {
              Swal.fire({
                icon: "warning",
                title: "Duplicate Invoice Alert",
                html: "The invoice <strong>'"+ item.invoice_no.trim() +"'</strong> is used in following claim - <br>" + itemNames,
                confirmButtonText: "OK",
                confirmButtonColor: "#4bc66d",
              });
            }

          }
        }
      }
      break;
      
    case 'price':
      error = validateNumber(item.price, 'Price', 0.01, 999999);
      break;
  }
  
  validationErrors.value[index][field] = error;
};

// Validate all claim items
const validateAllClaimItems = () => {
  let isValid = true;
  
  claimItems.value.forEach((item, index) => {
    // Ensure validation errors object exists for this index
    if (!validationErrors.value[index]) {
      validationErrors.value[index] = {};
    }
    
    // Validate category
    validateClaimItem(index, 'category');
    if (validationErrors.value[index].category) isValid = false;
    
    // Validate sub-product if needed
    if (item.checkField === '20)Accessories & Ancillary Items') {
      validateClaimItem(index, 'subProduct');
      if (validationErrors.value[index].subProduct) isValid = false;
    }
    
    // Validate product name if needed
    if (item.checkField === '21)OTHER') {
      validateClaimItem(index, 'productName');
      if (validationErrors.value[index].productName) isValid = false;
    }
    
    // Validate quantity
    validateClaimItem(index, 'quantity');
    if (validationErrors.value[index].quantity) isValid = false;
    
    // Validate price
    validateClaimItem(index, 'price');
    if (validationErrors.value[index].price) isValid = false;
  });

  let fileError = false;
  if ( uploadedFiles.value.length == 0 ) {
    fileError = true;
  } else {
    uploadedFiles.value.map( item => {
      if ( item.errors.length > 0 ) {
        fileError = true;
      }
    });  
  }
  if ( fileError ) {
    isValid = false;

  }
  return isValid;
};

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

// when a product is selected in dropdown
const onProductSelected = (item, index) => {
  if (item?.key_value?.id) {
    getChildsDetails(item.key_value.id);
    // Also set the product_name for validation
    item.product_name = ( item.key_value.key_value == "21)OTHER" ) ? '' : item.key_value.key_value;
  }
  if (item?.key_value?.key_value) {
    item.checkField = item.key_value.key_value;
  }
  
  // Validate after selection
  validateClaimItem(index, 'category');
};

const getChildsDetails = async (id) => {
  try {
    const params = {
      value_id: id,
      type: "IT-EQUIPMENT",
      recursive: true,
    };
    const response = await getChildCategories(params);
    if (response.isError === false && response.success_code === 200) {
      productSubSelections.value = response.data.children.map((prod) => ({
        id: prod.id,
        key_value: prod.key_value_h,
      }));
    }
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Failed to fetch child details.",
      confirmButtonText: "OK",
      confirmButtonColor: "#4bc66d",
    });
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

// Process selected files
const handleFiles = async (files) => {
  files.map( item => {
    if ( item.custom == 1 ) {
      if ( item.errors.length == 0 ) {
        isPdfGenerated.value = true;
      }
    }
  })
  uploadedFiles.value = files;
};

// Show product suggestions
const showProductSuggestions = (index) => {
  claimItems.value.forEach((item, i) => {
    item.showSuggestions = i === index;
  });
};

const SubSelectData = (index) => {
  return productSubSelections.value.filter(item => item.id === claimItems.value[index].key_value);
};

// Add new item
const addItem = () => {  
  const newItem = { 
    product_name: '', 
    quantity: 1, 
    price: 0, 
    invoice_no: '', 
    invoice_date: '', 
    description: '', 
    showSuggestions: false,
    errors: {}
  };
  
  claimItems.value.push(newItem);
};
// Remove item
const removeItem = (index) => {
  if (claimItems.value.length > 1) {
    claimItems.value.splice(index, 1);
  } else {
    let product_name = ( claimItems.value[0].product_name == "21)OTHER" ) ? '' : claimItems.value[0].product_name;
    claimItems.value = [{ product_name: product_name, quantity: 1, price: 0, invoice_no:'', invoice_date:'', description:'', showSuggestions: false }];
  }
};

// Calculate total amount
const totalAmount = computed(() => {
  return claimItems.value.reduce((total, item) => {
    return total + (Number(item.price) * Number(item.quantity));
  }, 0);
});

// Form validation
// Form validation - FIXED
const isFormValid = computed(() => {
  // Check if member is selected
  if (!selectedMember.value) return false;

  // Check if at least one file is uploaded and completed
  if (uploadedFiles.value.length === 0 || !uploadedFiles.value.some(item => item.custom == 0 && item.errors.length > 0)) {
    //return false;
  }

  // Check if all claim items are valid
  const allItemsValid = claimItems.value.every(item => {
    // Check if product is selected (either from dropdown or manual input)
    const hasProduct = item.key_value?.key_value || item.product_name;

    // Check if quantity and price are valid
    const hasValidQuantity = Number(item.quantity) > 0;
    const hasValidPrice = Number(item.price) > 0;

    return hasProduct && hasValidQuantity && hasValidPrice;
  });

  // Check declaration
  if (!declarationAccepted.value) return false;

  return allItemsValid;
});

// Prepare data for PDF generation
const pdfClaimData = computed(() => {
  return {
    claimId: claimId.value,
    originalForReceipt: 'Original for receipt',
    organization: 'Rajya Sabha',
    memberDS:'',
    address: '', //'Address line, Street Address, City Name, State, Country — Pin Code',
    email: selectedMember.value?.email || '',
    phone: selectedMember.value?.phone || '',
    mobile:'',
    state:'',
    pincode:'',
    processedBy: userAppData?.full_name || 'P A Name',
    processedOn: useLocalDate(new Date(), 'dd-mm-yyyy'),
    approvedBy: selectedMember.value?.full_name || 'Member Name',
    systemIP: '--',
    deviceName: '--',
    items: claimItems.value.map((item, index) => ({
      sno: index + 1,
      itemName: item.key_value?.key_value || item.product_name,
      qty: Number(item.quantity),
      unitPrice: Number(item.price),
      total: Number(item.price) * Number(item.quantity)
    })),
    totalAmount: totalAmount.value,
    claimReceivedBy: selectedMember.value?.full_name || ''
  };
});


// Handle PDF generation and upload to DMS
const handlePdfGenerated = async (blob) => {
  try {
    // Convert blob to file
    const fileName = `Claim_Receipt_${pdfClaimData.value.claimId}.pdf`;
    const file = new File([blob], fileName, { type: 'application/pdf' });
    //url.value = await URL.createObjectURL(blob);
    uploadRef.value.customUpload([file]);
  } catch (error) {
    console.error('Error uploading generated PDF to DMS:', error);
  }
};
setTimeout(() => {
    if (pdfGenerator.value) {
      pdfGenerator.value.generatePDFBlob();
    }
  }, 100);
// Submit form (go to preview)
const submitForm = async () => {
    // Validate all claim items first
  if (!validateAllClaimItems()) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please fix all validation errors before submitting.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#4bc66d'
    });
    return;
  }
  if (!isFormValid.value) return;
  if ( financeDetails.value.remaining_amount < 0 || totalAmount.value > financeDetails.value.remaining_amount ) {
    Swal.fire({
      icon: 'warning',
      title: 'Claim Amount',
      text: 'Claim amount is greater than the remaining amount.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#4bc66d'
    });
  }
  // Prepare preview data
  previewData.value = {
    member: selectedMember.value,
    financeDetails: financeDetails.value,
    accountDetails: accountDetails.value,
    claimItems: claimItems.value,
    totalAmount: totalAmount.value,
    uploadedFiles: uploadedFiles.value
  };
  //localStorage.setItem('previewData',JSON.stringify(previewData.value));
  showPreview.value = true;
  
  // Auto-generate PDF when preview is shown
  setTimeout(() => {
    if (pdfGenerator.value) {
      pdfGenerator.value.generatePDFBlob();
    }
  }, 100);
};


// Final submit after preview
const finalSubmit = async () => {
  try {
    isLoading.value = true;

    // Ensure PDF is generated and uploaded
    if (!isPdfGenerated.value) {
      // Generate PDF if not already done
      await pdfGenerator.value.generatePDFBlob();
      
      // Wait for PDF generation and upload to complete with timeout
      await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
          reject(new Error('PDF generation and upload timeout'));
        }, 15000); // 15 second timeout
        
        const checkInterval = setInterval(() => {
          if (isPdfGenerated.value) {
            clearInterval(checkInterval);
            clearTimeout(timeout);
            resolve();
          }
        }, 100);
      });
    }

    const files = [];
    let pdfPath = '';
    uploadedFiles.value.map(item => {
      if ( item.custom == 0 && item.errors.length == 0 ) {
        files.push(item.path);
      }
      if ( item.custom == 1 ) {
        if ( item.errors.length == 0 ) {
          pdfPath = item.path;
        }
      }
    })
    
    const claimData = {
      core_user_id: selectedMember.value.core_user_id,
      member_id: selectedMember.value.core_user_id,
      item_name: claimItems.value.map((item) => item.key_value?.key_value || item.key_value_h || item.product_name),
      description: claimItems.value.map((item) => item.description),
      invoice_no: claimItems.value.map((item) => item.invoice_no),
      invoice_date: claimItems.value.map((item) => item.invoice_date),
      quantity: claimItems.value.map((item) => Number(item.quantity)),
      amount_pr_item: claimItems.value.map((item) => Number(item.price)),
      amount: claimItems.value.map((item) => Number(item.price) * Number(item.quantity)),
      total_amount: totalAmount.value,
      claim_file: files, // Array of user uploaded file paths
      is_cdac: "0",
      bypass_esign: "0",
      entitlement_type: 'IT-EQUIPMENT',
      claim_code: claimId.value,
      submited_claim: pdfPath // Single string path for generated PDF
    };

    const response = await submitClaim(claimData);
    
  if (response.isError === false && (response.success_code === 200 || response.success_code === 201)) {
    isSwal('Request for self-sign sent successfully', 'success');

    const claimId = response.data?.id;
    const requestId = response.data?.requestId;    
    const requestTxn = response.data?.txn_id;
    
    if (claimId && requestId) {
      router.replace({
        name: 'ITClaimSubmitEsign',
         state: {   // 👈 hidden state (not visible in URL)
      claimId,
      requestId,
      requestTxn,
      status: "success",
    },
      });
    }

    // ✅ reset form here (after redirect trigger)
    resetForm();
  } else {
    let errorMessage = '';
    if ( Array.isArray(response.description) && response.description.length > 0 ) {
      errorMessage = response.description[0] || '';
    } else if ( typeof response.description == "object" && Object.keys(response.description).length > 0 ) {
      Object.keys(response.description).map(key => {
        errorMessage = response.description[key][0] || '';
      });
    } else if ( typeof response.description == "string" ) {
      errorMessage = response.description || '';
    }    
    if ( errorMessage != '' ) {
      throw new Error(errorMessage);
    }
  }
  isLoading.value = false;
  isPdfGenerated.value = false;
} catch (error) {
  isLoading.value = false;
  isPdfGenerated.value = false;
  Swal.fire({
    icon: 'error',
    title: 'Submission Failed',
    text: error.message || 'There was an error submitting your claim. Please try again.',
    confirmButtonText: 'OK',
    confirmButtonColor: '#4bc66d'
  });
}

};


// Reset form
const resetForm = () => {
  selectedMember.value = null;
  uploadedFiles.value = [];
  claimItems.value = [{ product_name: '', quantity: 1, price: 0, invoice_no:'', invoice_date:'', description:'', showSuggestions: false }];
  declarationAccepted.value = false;
  showPreview.value = false;
  isPdfGenerated.value = false;
};
// Close suggestions when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.relative')) {
    claimItems.value.forEach(item => {
      item.showSuggestions = false;
    });
  }
};

onMounted(async () => {
   try {
    const response = await getClaimTrackId();

    // Adjust this based on your actual API response structure
    claimId.value =
      response.data?.claim_track_id || response.claim_track_id || response.data?.claim_track_id || '';

    console.log('Fetched Claim ID:', claimId.value);
  } catch (error) {
    console.error('Error fetching claim ID:', error);
  }
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

input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
}
</style>
<style src="vue-multiselect/dist/vue-multiselect.css"></style>