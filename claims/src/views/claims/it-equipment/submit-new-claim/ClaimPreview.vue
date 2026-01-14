<template>
  <div class="p-6 bg-white min-h-screen">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Claim Preview</h1>
      <p class="text-gray-600">Review your claim before submission</p>
    </div>



    <!-- Processed Info -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Processed By</label>
          <p class="font-medium text-gray-800">Member Name / PA Name</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Processed on Date & Time</label>
          <p class="font-medium text-gray-800">{{ currentDateTime }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Member</label>
          <p class="font-medium text-gray-800">{{ claimData.member.full_name }}</p>
        </div>
      </div>
    </div>

    <!-- Invoice Details -->
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-4">Invoice Detail</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div v-for="(item, index) in claimData.uploadedFiles" :key="index" class="bg-blue-50 rounded-lg p-3 text-center">
   
          <p class="text-sm text-gray-600 mt-1">{{ item.file.name }}</p>
        </div>
      </div>
    </div>

    <!-- Bill Details -->
    <div class="mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-4">Bill Details</h2>
      <h3 class="text-lg font-semibold text-gray-700 mb-3">Product Information</h3>
      
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sub Product Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice No</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(item, index) in claimData.claimItems" :key="index">
      
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ item.checkField || 'Category ' + (index + 1) }}
              </td>
           <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
  <template v-if="item.checkField === '20)Accessories & Ancillary Items'">
    {{ item.key_value_h?.key_value || '' }}
  </template>

  <template v-else-if="item.checkField === '21)OTHER'">
    {{ item.product_name || '' }}
  </template>

  <template v-else>
    {{  '' }}
  </template>
</td>

              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ item.description }} 
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ item.invoice_no }} 
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ useLocalDate(item.invoice_date, 'dd-mm-yyyy')  }} 
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ item.quantity }} 
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ useLocalCurrency(item.price * item.quantity) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">✅</span>
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50">
            <tr>
              <td colspan="3" class="px-2 py-4 text-right text-lg font-bold text-gray-900">Total Amount:</td>
              <td class="px-6 py-4 font-bold text-lg  text-gray-900">{{ useLocalCurrency(claimData.totalAmount) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex justify-center gap-4 mt-8">
      <button @click="$emit('edit')" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
        Edit Claim
      </button>
      <button @click="$emit('submit')" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
        Proceed to e-Sign
      </button>
    </div>
  </div>
</template>

<script setup>
import useLocalCurrency from '@/composables/useLocalCurrency';
import useLocalDate from '@/composables/useLocalDate';
import { computed } from 'vue';

const props = defineProps({
  claimData: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['edit', 'submit']);

const currentDateTime = computed(() => {
  const now = new Date();
  return now.toLocaleDateString('en-GB', { 
    day: '2-digit', 
    month: 'short', 
    year: 'numeric' 
  }) + ' ' + now.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  }).toUpperCase();
});
</script>

<style scoped>
/* Add any custom styles here */
</style>