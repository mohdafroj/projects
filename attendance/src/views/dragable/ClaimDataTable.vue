<template>
  <div>
    <table class="w-full text-sm text-center border">
      <thead class="bg-gray-50">
        <tr>
          <th class="p-2 border">✔</th>
          <th class="p-2 border">Items</th>
          <th class="p-2 border">Price</th>
          <th class="p-2 border">Qty</th>
          <th class="p-2 border">Total</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="item in items" :key="item.id">
          <tr class="border-b">
            <td class="p-2 text-center"><input type="checkbox" v-model="item.checked" /></td>
            <td class="p-2">{{ item.name }}</td>
            <td class="p-2">₹{{ item.price.toFixed(2) }}</td>
            <td class="p-2">{{ item.qty }}</td>
            <td class="p-2">₹{{ (item.price * item.qty).toFixed(2) }}</td>
          </tr>
          <tr v-if="!item.checked">
            <td colspan="5" class="p-2 m-1 rounded-md">
              <div
                class="bg-orange-50 border-x rounded-sm border-orange-200 p-2 text-xs text-left text-orange-700"
              >
                Since you have removed {{ item.name }}, you need to mention the
                reason -
                <a href="#" class="text-orange-600 font-medium">Add Reason</a>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <!-- Totals -->
    <!-- Totals + View Button -->
    <div
      class="mt-4 flex justify-between items-center flex-wrap gap-2 ml-6 mr-6"
    >
      <!-- View Invoice Button -->
      <button
        class="flex items-center border border-blue-600 text-blue-600 px-3 m-4 py-1 rounded-full hover:bg-blue-100 transition"
      >
        <Icon
          icon="heroicons:arrow-path-solid"
          width="16"
          height="16"
          class="mr-2"
          style="color: bg-blue-500"
        />
        <span class="text-sm">View Invoice Bills</span>
      </button>

      <!-- Totals -->
      <div class="text-sm text-gray-800 space-y-1 max-w-xs ml-auto">
        <div class="flex justify-between">
          <span class="font-medium">Total Amount :</span>
          <span class="font-medium">₹40,000</span>
        </div>
        <div class="flex justify-between">
          <span class="font-medium">ICT :</span>
          <span>₹10,000</span>
        </div>
        <div class="flex justify-between">
          <span class="font-medium">Digital :</span>
          <span>₹30,000</span>
        </div>
        <div class="flex justify-between mt-2 text-lg font-bold text-gray-900">
          <span>Payable Amount :</span>
          <span>₹40,000</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive } from "vue";
import { Icon } from "@iconify/vue";
const props = defineProps({
  items: Array,
});

const totalAmount = computed(() =>
  props.items.reduce((sum, item) => sum + item.price * item.qty, 0),
);

const headTotals = computed(() => {
  return props.items.reduce((acc, item) => {
    const total = item.price * item.qty;
    acc[item.head] = (acc[item.head] || 0) + total;
    return acc;
  }, {});
});
</script>
