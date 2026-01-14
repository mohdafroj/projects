<template>

    <Modal
    :modelValue="apiStore.tada_claim.action?.showItemUpdate"
    :disableBackdrop="true"
    title="Update item details"
    size="lg"
    @close="handleItemClose"
    >

      <div
        v-for="(item, index) in itemDetails"
        :key="'item_detail: ' + index"
      >
      <Card>
        <!-- Item Name -->
        <div>
          <label class="font-semibold">Item Name: </label>
          <label>{{ item.item_name }}</label>          
        </div>
        <div class="grid grid-cols-2 gap-2">
          <!-- Invoice No -->
          <div class="flex items-center">
            <label class="font-semibold">Invoice No: </label>
            <input
              v-model="item.invoice_no"
              type="text"
              class="w-32 flex-1 focus:outline-none border rounded ml-1 px-1 focus:ring focus:ring-blue-50"
              placeholder="Enter invoice number"
            />
          </div>

          <!-- Invoice Date -->
          <div class="flex items-center">
            <label class="font-semibold">Invoice Date: </label>
            <input
              v-model="item.invoice_date"
              :min="minDate"
              :max="maxDate"
              type="date"
              class="w-28 mt-1 flex-1 focus:outline-none border rounded ml-1 px-2 focus:ring focus:ring-blue-50"
            />
          </div>

        </div>
        <!-- Item Description -->
        <div>
          <label class="block font-semibold">Description: </label>
          <textarea
            v-model="item.description"
            type="text"
            @blur="validateClaimItem(index, 'description')"
            @input="validateClaimItem(index, 'description')"
            class="mt-1 p-1 block w-full border rounded focus:outline-none focus:ring focus:ring-blue-50"
            placeholder="Enter description"
          ></textarea>
          <span v-if="validationErrors[index]?.description" class="text-red-500 text-xs mt-1">
            {{ validationErrors[index].description }}
          </span>
        </div>
      </Card>
      <div class="mt-1 text-center text-red-500">{{ itemError }}</div>
      </div>

      <!-- Named slot "footer" -->
      <template #footer>
        <Button
          type="submit"
          class="px-4 py-0 rounded"
          size="sm"
          color="green-outline"
          label="Submit"
          @click="() => handleItemUpdate()"
        >
        </Button>
        <Button 
          class="px-4 py-0 rounded"
          size="sm"
          color="red-outline"
          label="Close"
          @click="handleItemClose"
        ></Button>
      </template>
    </Modal>

</template>

<script setup>
import { ref, computed, watch } from "vue";
import { Modal, Button, Card } from '@sds/oneui-common-ui'
import { updateClaimItems } from "@/services/rss/itEquipmentsService";
import { useApiStore } from "@/store/apiData";
const apiStore = useApiStore();

const itemDetails = ref([]);
const itemError = ref('')
const validationErrors = ref([{}]);

const maxDate = computed(() => {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0"); // Months are 0-based
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const minDate = computed(() => {
  const d = new Date();
  d.setFullYear(d.getFullYear() - 6); // Subtract 6 years
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0"); // Months are 0-based
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});


const validateClaimItem = (index, field) => {
  if (!validationErrors.value[index]) {
    validationErrors.value[index] = {};
  }
  const item = itemDetails.value[index];
  let error = '';
  switch (field) {
    case 'description':
      const description = item.description.trim();
      if ( description.length > 200 ) {
        error = 'Description should not exceed 200 characters.';
      }
      break;      
  }
  validationErrors.value[index][field] = error;
};


const handleItemUpdate = async () => {
  if ( validationErrors.value.length ) return false;
  const payload = itemDetails.value.map(item => ({id: item.id, invoice_no: item.invoice_no, invoice_date: item.invoice_date, description: item.description}));
  const response = await updateClaimItems(apiStore.tada_claim.detail.claim_id, {claim_items:payload});
  if ( response.isError == false && response.success_code == 200 ) {
    apiStore.setItEquipment({ ...apiStore.tada_claim, detail: { ...apiStore.tada_claim.detail, claim_items: itemDetails.value } });
    handleItemClose();
  } else {
    itemError.value = "Somethig went wrong."
  }
}

const handleItemClose = () => {
  itemError.value = "";
  itemDetails.value = [];
  apiStore.setItEquipmentAct({ ...apiStore.tada_claim.action, showItemUpdate: false });
}

watch([
  () => apiStore.tada_claim.action.showItemUpdate
],
  ([newVale], [oldValue]) => {
    if (newVale) {
        itemDetails.value = JSON.parse(
            JSON.stringify(apiStore.tada_claim?.detail.claim_items || [])
        );
    } else {
        itemDetails.value = [];
    }
  },
);

</script>
<style lang="css" scoped>
input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
}

</style>