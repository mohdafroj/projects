<template>
  <div>
    <table class="w-full text-sm text-center border">
      <thead class="bg-gray-50">
        <tr>
          <!--th class="p-2 border">✔</th-->
          <th class="p-2 border text-left">Items</th>
          <th class="p-2 border">Admissible</th>
          <th class="p-2 border">Price</th>
          <th class="p-2 border w-[10%]">Qty</th>
          <th class="p-2 border">Total</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="item in claimDetails.claim_items" :key="'claim_item_' + item.id">
          <tr class="border-b">
            <!--td class="p-1 text-center">
              <input v-show="hasPermission(PERMISSIONS.ITCLAIM.INITIATE)" type="checkbox" :value="item.id" @input="handleCheckBox"
                :v-model="Boolean(item.checked)" :checked="Boolean(item.checked)" /></td-->
            <td class="p-1 text-left">
              {{ item.item_name }}
              <span v-show="item.description"><br>{{ item.description }}</span>
              <span v-show="item.invoice_no"><br>Invoice No: {{ item.invoice_no }}</span>
              <span v-show="item.invoice_date"><br>Invoice Date: {{ useLocalDate(item.invoice_date, 'dd-mm-yyyy') }}</span> 
            </td>
            <td class="p-1">
<Listbox 
:modelValue="item.is_accpted" 
@update:modelValue="value => handleAdmissible(value)"
by="value"
:disabled="disableUpdate">
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

            </td>
            <td class="p-1">{{ useLocalCurrency(item.price) }}</td>
            <td class="p-1">{{ item.qty }}</td>
            <td class="p-1">{{ useLocalCurrency(item.price * item.qty) }}</td>
          </tr>
          <!--tr v-if="touchedItems.includes(Number(item.id))">
            <td colspan="5" class="p-2 m-1 rounded-md">
              <div class="bg-orange-50 border-x rounded-sm border-orange-200 p-2 text-xs text-left text-orange-700">
                Since you have {{ Boolean(item.checked) ? 'added' : 'removed' }} {{ item.name }}, you need to mention
                the
                reason -
                <button @click="addNote" class="text-black-900 font-medium">Add Reason</button>
              </div>
            </td>
          </tr-->
          
        </template>
        <tr>
            <td colspan="2" rowspan="4" class="text-center">
              <div v-show="hasPermission(PERMISSIONS.ITCLAIM.INITIATE)" class="ml-8 mb-10">
                <button :disabled="disableUpdate" @click="handleShowItemUpdate" class="text-xs flex items-center border border-gray-600 text-gray-600 px-3 py-1 rounded-full hover:bg-gray-100">
                  <Icon icon="heroicons-solid:pencil" width="16" height="16" class="mr-2 text-xs" />
                  <span>Update Item Detail</span>
                </button>
              </div>
              <div class="ml-8 mt-4" v-if="Object.keys(seletedItem).length">
                <button
                  class="flex items-center border border-blue-600 text-blue-600 px-3 py-1 rounded-full hover:bg-blue-100 transition">
                  <Icon icon="carbon:document-view" width="16" height="16" class="mr-2" />
                  <span class="text-sm" @click="() => handleShowInvoice(1)">E-Signed Document</span>
                </button>
              </div>
              <div class="ml-8 mt-4">
                <button
                  class="flex items-center border border-blue-600 text-blue-600 px-3 py-1 rounded-full hover:bg-blue-100 transition">
                  <Icon icon="heroicons:arrow-path-solid" width="16" height="16" class="mr-2" />
                  <span class="text-sm" @click="() => handleShowInvoice(0)">View Invoice Bills</span>
                </button>
              </div>
            </td>
            <td colspan="2" class="text-right">
              <span class="font-medium">Total Amount :</span>
            </td>
            <td>
                <span class="font-medium">{{ useLocalCurrency(totalAmount) }}</span>
            </td>
          </tr>
          <tr>
            <td colspan="2" class="text-right">
              <span >Payable Amount :</span>
            </td>
            <td>
              <span class="font-medium">{{ useLocalCurrency(payableAmount) }}</span>
            </td>
          </tr>
          <tr>
            <td colspan="2" class="text-right">
              <span >ICT :</span>
            </td>
            <td class="pt-2">
           <div v-show="isTouched">
            <input type="number" min="0" @input="handleUpdate" @keypress="blockInvalid" name="ict_amount"
              placeholder="Enter ICT Amount"
              ref="ictAmountRef"
              class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 h-[30px] border-gray-300 focus:outline-none focus:ring-1 focus:ring-gray-500"
              id="ict_amount" aria-required="false" :value="claimDetails.ict_amount"
              @blur="() => { isTouched = false; handlePayableAmount(); }" />
        </div>
        <div v-show="!isTouched" @click="isTouched = !disableUpdate">
          <Badge :text="useLocalCurrency(claimDetails.ict_amount)" type="info"
            customClass="w-[150px] h-[30px] bg-white text-gray-800 border border-gray-300 rounded-[3px]" />
        </div>
            </td>
          </tr>
          <tr>
            <td colspan="2" class="text-right pb-6">
              <span >Digital :</span>
            </td>
            <td class="pt-2">
           <div v-show="isTouched">
            <input type="number" min="0" @input="handleUpdate" @blur="() => { isTouched = false; handlePayableAmount(); }"
              @keypress="blockInvalid" ref="digitalAmountRef" name="digital_amount" placeholder="Enter Digital Amount"
              class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 h-[30px] border-gray-300 focus:outline-none focus:ring-1 focus:ring-gray-500"
              id="digital_amount" aria-required="false" :value="claimDetails.digital_amount" />
          </div>
        <div v-show="!isTouched" @click="isTouched = !disableUpdate">
          <Badge :text="useLocalCurrency(claimDetails.digital_amount)" type="info"
            customClass="w-[150px] h-[30px] bg-white text-gray-800 border border-gray-300 rounded-[3px]" />
        </div>
            </td>
          </tr>
          <tr v-if="claimDetails?.duplicate_invoicess">
            <td colspan="5" class="border-red-700 bg-red-100 rounded-full text-red-500 px-2 py-1">
              <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap scrollbar-thin">
                <span class="font-semibold whitespace-nowrap">Duplicate Invoices:</span>
                <router-link
                  v-for="item in claimDetails?.duplicate_invoices"
                  :key="'duplicate_invoices' + item.invoice_no"
                  :to="{ name: 'ITClaimDetail', params: { id: item.claim_id } }"
                  target="_blank"
                  class="underline hover:text-red-800 shrink-0"
                >
                  {{ item.invoice_no }}
                </router-link>

              </div>
            </td>
          </tr>
      </tbody>
    </table>
  </div>
  <ClaimItemUpdate />
  <Modal
      :modelValue="showModal"
      :title="' '"
      size="xl"
      @close="() => showModal = false"
    >
    <div class="flex items-center justify-left mb-1">
      <div @click="() => downloadFile(seletedItem.url, seletedItem.name)" class="z-10 px-4 py-2 mr-5">
        <Icon icon="hugeicons:download-04" class="text-2xl cursor-pointer" />
      </div> 
      <div v-if="isImage(seletedItem.url)" @click="() => printFile(seletedItem.url)" class="z-10 px-4 py-2 ml-5">
        <Icon icon="fluent:print-32-regular" class="text-2xl cursor-pointer" />
      </div>       
    </div>
    <div class="w-full h-[95vh] flex items-center justify-center">

        <img
          v-if="isImage(seletedItem.url)"
          :src="seletedItem.url"
          class="w-full h-full object-contain"
        />
        <object
          v-else
          :data="seletedItem.url"
          type="application/pdf"
          class="w-full h-full object-contain"
        />
    </div>
  </Modal>

</template>

<script setup>
import { computed, ref, watch, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import Swal from 'sweetalert2';
import { Badge, SelectInput } from '@sds/oneui-common-ui'
import { useApiStore } from "@/store/apiData";
import useLocalCurrency from "@/composables/useLocalCurrency";
import ClaimItemUpdate from "./ClaimItemUpdate.vue";
import useLocalDate from "@/composables/useLocalDate";
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { Modal } from "@sds/oneui-common-ui";
import {
    Listbox,
    ListboxButton,
    ListboxOptions,
    ListboxOption,
  } from '@headlessui/vue'
import { updateClaimItems } from "@/services/rss/itEquipmentsService";

const apiStore = useApiStore();

const props = defineProps({
  addNote: Function
});
const currentUserId = computed(() =>apiStore.user.id || 0);
const sendToUserId = ref(0);
const disableUpdate = ref(false);

const ictAmountRef = ref(null);
const digitalAmountRef = ref(null);
const isTouched = ref(false);
const admissibleOptions = ref([]);
const claimDetails = ref({ ...apiStore.it_equipment?.detail });
const touchedItems = ref([]);
const payableAmount = ref(0);

const esignBy = ref(false)

const seletedItem = ref({});
const showModal = ref(false);
watch(
  () => apiStore.it_equipment.detail.documents, 
  (newData) => {
    Object.keys(newData).map(key => {
      if ( key.toLocaleLowerCase() == 'e-sign' ) {
        newData[key].map(item => {
          seletedItem.value = {name:key, url: item};
        })
      }
    });
  },
  { immediate: true }
);

function isImage(name) {
  return /\.(jpe?g|png|gif|bmp|webp)$/i.test(name)
}

const downloadFile = async (url, name = "Invoice") => {
  try {
    const response = await fetch(url)
    if (!response.ok) throw new Error("Failed to fetch file")

    const blob = await response.blob()
    const blobUrl = URL.createObjectURL(blob)

    const link = document.createElement("a")
    link.href = blobUrl
    link.download = name
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    URL.revokeObjectURL(blobUrl) // cleanup
  } catch (err) {
    console.error("Download failed:", err)
  }
}

const printFile = async (url) => {
  if (isImage(url)) {
    // For images: print the modal preview
    const imgHtml = `<img src="${url}" style="width:100%;height:auto;" />`
    const w = window.open('', '', 'width=800,height=600')
    w.document.write(imgHtml)
    w.document.close()
    w.focus()
    w.print()
    w.close()
  } else {
    try {
      const response = await fetch(url)
      const blob = await response.blob()
      const blobUrl = URL.createObjectURL(blob);

      const w = window.open(blobUrl, '_blank')
      w.focus()
    } catch (err) {
      console.error('Failed to print PDF:', err)
    }
  }
}


watch([
  () => apiStore.it_equipment.detail
],
  ([newDetail]) => {
    if (!newDetail) return;
    claimDetails.value = { ...newDetail };
    admissibleOptions.value = newDetail.is_accpted_li.map(item => ({label: item, value: item}) )
    const esign_by = newDetail?.esign_by || 0;
    if ( esign_by > 0 ) {
      esignBy.value = ( currentUserId == esign_by ) ? true : false;
    } else {
      esignBy.value = true;
    }
    sendToUserId.value = newDetail?.assigned_to || 0;
    const claimStatus = newDetail?.status.toLowerCase() || '';
    disableUpdate.value = (hasPermission(PERMISSIONS.ITCLAIM.INITIATE) || ['submitted','initiated','in progress'].includes(claimStatus)) ? false : true;
  },
  { deep: true }
);


const handleShowItemUpdate = () => {
  apiStore.setItEquipmentAct({ ...apiStore.it_equipment.action, showItemUpdate: true });
}

const handleShowInvoice = (param=0) => {
  if ( param == 1 ) {
    showModal.value = true;
    return false;
  } else {
    apiStore.setItEquipmentAct({ ...apiStore.it_equipment.action, showInvoice: true });
  }
}

const handleCheckBox = async (e) => {
  const id = Number(e.target.value);
  const checked = Number(e.target.checked);
  touchedItems.value = touchedItems.value.includes(id) ? touchedItems.value.filter(itemId => itemId !== id) : [...touchedItems.value, id];
  let claim_items = claimDetails.value.claim_items.map(item => item.id == id ? { ...item, checked } : item);
  const payable_amount = await findPayableAmount(claim_items);
  apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, touched_items: touchedItems.value, claim_items, payable_amount } });
  payableAmount.value = payable_amount;
}

const handleAdmissible = async (obj) => {
  const id = Number(obj.item_id);
  const checked = obj.value == 'Admissible' ? 1 : 0;
  const claim_items = claimDetails.value.claim_items.map(item => item.id == id ? { ...item, is_accpted: obj.value, checked } : item);
  const response = await updateClaimItems(claimDetails.value.claim_id, {claim_items});
  if ( response.isError == false && response.success_code == 200 ) {
    const payable_amount = await findPayableAmount(claim_items);
    apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, touched_items: touchedItems.value, claim_items, payable_amount } });
    touchedItems.value = touchedItems.value.includes(id) ? touchedItems.value.filter(itemId => itemId !== id) : [...touchedItems.value, id];
    payableAmount.value = payable_amount;
  } else {
    console.log("Items update error: ", response.customMessage);
  }
}

const handleUpdate = async (e) => {
  await apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, [e.target.name]: e.target.value } });
  const payable_amount = await findPayableAmount(); //Check amount after update input box
  apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, [e.target.name]: e.target.value, payable_amount } });
  payableAmount.value = payable_amount;  
}

const blockInvalid = (e) => {
  if (["e", "E", "+", "-"].includes(e.key)) {
    e.preventDefault();
  }
  const parts = e.target.value.split('.');
  if ( parts[1] && parts[1].length > 1 ) {
    e.preventDefault();
  }
  if( e.which == 13 ) {
    if ( e.target.name == 'ict_amount' ) {
      digitalAmountRef.value.focus();
      isTouched.value = true;
    } else if ( e.target.name == 'digital_amount' ) {
      isTouched.value = false;
    }
  }
}

const totalAmount = computed(() =>{
  let claimItems = claimDetails.value?.claim_items || [];
  return claimItems.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0)
});

const handlePayableAmount = () => {
  let totalByItem = claimDetails.value.claim_items.filter(item => item.is_accpted == 'Admissible').reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
  const totalBySystem = Number(claimDetails.value.digital_amount) + Number(claimDetails.value.ict_amount);
  if (totalBySystem > Math.ceil(totalByItem) ) {
    payableAmount.value = totalByItem;
    Swal.fire({
      icon: 'error',
      title: "IT Claim",
      text: "Sum of ICT and Digital amount should be equal to or less than Payable Amount!!",
      confirmButtonText: "OK",
      confirmButtonColor: '#4bc66d'
    });
  }
  return payableAmount.value;
}

const findPayableAmount = async (items = []) => {
  let claimItems = items.length ? items : (claimDetails.value?.claim_items || []);
  const totalByItem = claimItems.filter(item => item.is_accpted == 'Admissible').reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
  const totalBySystem = Number(claimDetails.value?.digital_amount || 0) + Number(claimDetails.value?.ict_amount || 0);
  return (totalBySystem > 0 && totalBySystem < totalByItem) ? totalBySystem : totalByItem;
}
onMounted(async () => {
  payableAmount.value = await findPayableAmount();
})
</script>
