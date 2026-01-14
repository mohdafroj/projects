<template>

    <Modal
    :modelValue="apiStore.it_equipment.action?.showItemUpdate"
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
        <div>
<Listbox 
:modelValue="item.is_accpted" 
@update:modelValue="obj => item.is_accpted = obj.value"
by="value">
  <div class="relative w-full">
    <!-- Button -->
    <ListboxButton
      class="w-full p-1 text-sm rounded-md border bg-white dark:bg-slate-900 focus:ring-2 cursor-pointer flex items-center justify-between"
    >
      <span
        :class="[
          'block truncate',
          item.is_accpted === 'Admissible' && 'text-green-600',
          item.is_accpted === 'Incomplete' && 'text-yellow-600',
          item.is_accpted === 'Non-Admissible' && 'text-red-600'
        ]"
      >
        {{ item.is_accpted }}
      </span>

      <Icon
        icon="ph:caret-up-down-bold"
        width="16"
        height="16"
        class="ml-2 text-gray-500"
      />
    </ListboxButton>

    <!-- Options -->
    <transition
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <ListboxOptions
        class="absolute z-10 mt-1 max-h-60 w-full overflow-auto
               rounded-md bg-white dark:bg-slate-900
               py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
      >
        <ListboxOption
          v-for="opt in admissibleOptions"
          :key="opt.value"
          :value="{...opt, item_id:item.id}"
          v-slot="{ active, selected }"
        >
          <li
            :class="[
              'cursor-pointer select-none px-3 py-1',
              active && 'bg-gray-200 dark:bg-slate-700',
              selected && 'font-semibold',
              opt.value === 'Admissible' && 'text-green-600',
              opt.value === 'Incomplete' && 'text-yellow-600',
              opt.value === 'Non-Admissible' && 'text-red-600'
            ]"
          >
            {{ opt.value }}
          </li>
        </ListboxOption>
      </ListboxOptions>
    </transition>

  </div>
</Listbox>

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
import { Modal, Button, Card } from '@sds/oneui-common-ui';
import { Icon } from "@iconify/vue";
import {
    Listbox,
    ListboxButton,
    ListboxOptions,
    ListboxOption,
  } from '@headlessui/vue'

import { updateClaimItems } from "@/services/rss/itEquipmentsService";
import { useApiStore } from "@/store/apiData";
const apiStore = useApiStore();
const admissibleOptions = ref([]);
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
  //console.log(validationErrors.value, Object.keys(validationErrors.value[0]))
  if ( validationErrors.value.length && Object.keys(validationErrors.value[0]).length ) return false;
  const payload = itemDetails.value.map(item => ({id: item.id, invoice_no: item.invoice_no, invoice_date: item.invoice_date, is_accpted: item.is_accpted, description: item.description}));
  const response = await updateClaimItems(apiStore.it_equipment.detail.claim_id, {claim_items:payload});
  if ( response.isError == false && response.success_code == 200 ) {
    apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, claim_items: itemDetails.value } });
    handleItemClose();
  } else {
    itemError.value = "Somethig went wrong."
  }
}

const handleItemClose = () => {
  itemError.value = "";
  itemDetails.value = [];
  apiStore.setItEquipmentAct({ ...apiStore.it_equipment.action, showItemUpdate: false });
}

watch([
  () => apiStore.it_equipment.action.showItemUpdate
],
  ([newVale], [oldValue]) => {
    if (newVale) {
        itemDetails.value = JSON.parse(
            JSON.stringify(apiStore.it_equipment?.detail.claim_items || [])
        );
        admissibleOptions.value = apiStore.it_equipment?.detail?.is_accpted_li.map(item => ({label: item, value: item}) )
    } else {
        itemDetails.value = [];
        admissibleOptions.value = [];
    }
  },
);

</script>
<style lang="css" scoped>
input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
}

</style>