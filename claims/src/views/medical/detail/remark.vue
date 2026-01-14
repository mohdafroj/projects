<template>
  <div class="statusdiv mb-3">
    <div class="flex justify-between">
      <div class="text-xl font-medium text-neutral-900">Initiator Medical Claim</div>
      <div class="flex items-center">
        <button class="rounded-2xl border border-blue-800 text-sm text-blue-800 px-3 py-1 bg-blue-100 mr-3">View Status History</button>
        <button class="flex items-center rounded-2xl border border-blue-800 text-sm text-blue-800 px-3 py-1 bg-blue-100">
          <Icon icon="heroicons:arrow-path" width="24" height="24" class="mr-2" /> Refresh</button>
      </div>
    </div>

    <div class="flex justify-between mt-5 max-w-4xl mx-auto">
    
    <!-- Step 1: Submitted -->
    <div class="flex flex-col items-center">
      <div class="text-lg font-semibold">Submitted</div>
      <div class="bg-green-100 text-green-700 text-sm px-3 py-1 rounded-full mt-1 text-nowrap">Success</div>
      <div class="w-14 h-14 border-4 border-green-500 rounded-full flex items-center justify-center mt-2">
        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <div class="text-sm text-gray-600 mt-2 text-nowrap">22 Nov, 2024 9:15</div>
    </div>

    <!-- Line with delay text -->
    <div class="flex flex-col justify-center items-center w-1/4 mt-6">
      <div class="text-xs text-gray-400 mb-1 font-semibold">Delayed by 1 day</div>
      <div class="border-t-2 border-gray-300 w-full h-0"></div>
    </div>

    <!-- Step 2: Initiate -->
    <div class="flex flex-col items-center">
      <div class="text-lg font-semibold">Initiate</div>
      <div class="bg-red-100 text-red-600 text-sm px-3 py-1 rounded-full mt-1 text-nowrap">In-Process</div>
      <div class="relative mt-2">
        <div class="w-14 h-14 border-4 border-red-400 rounded-full flex items-center justify-center text-2xl font-semibold text-red-600 bg-white shadow-md">
          2
        </div>
        <div class="absolute -inset-1 rounded-full bg-red-200 opacity-30 animate-ping"></div>
      </div>
    </div>

    <!-- Dashed line -->
    <div class="flex flex-col justify-center items-center w-1/4 mt-10">
      <div class="border-t-2 border-dashed border-gray-300 w-full h-0"></div>
    </div>

    <!-- Step 3: Review -->
    <div class="flex flex-col items-center">
      <div class="text-lg font-semibold">Reviessssw</div>
      <div class="bg-gray-200 text-gray-600 text-sm px-3 py-1 rounded-full mt-1 text-nowrap">Pending</div>
      <div class="w-14 h-14 border-4 border-white bg-neutral-100 rounded-full flex items-center justify-center text-2xl font-semibold text-gray-500 mt-2">
        3
      </div>
    </div>

    <!-- Dashed line -->
    <div class="flex flex-col justify-center items-center w-1/4 mt-10">
      <div class="border-t-2 border-dashed border-gray-300 w-full h-0"></div>
    </div>

    <!-- Step 4: Approve -->
    <div class="flex flex-col items-center">
      <div class="text-lg font-semibold">Approve</div>
      <div class="bg-gray-200 text-gray-600 text-sm px-3 py-1 rounded-full mt-1 text-nowrap">Pending</div>
      <div class="w-14 h-14 border-4 border-white bg-neutral-100 rounded-full flex items-center justify-center text-2xl font-semibold text-gray-500 mt-2">
        4
      </div>
    </div>

  </div>
  </div>
  <div class="grid grid-cols-2 gap-4">
      <div class="">
        <NoteSection/>
        <div class="mt-4">
    <SplitPanel ref="letterPanelRef" :initialLeftWidth="'100%'" :minRightWidth=minRightWidth v-model:showPanel="letterSplitPanel">
      <template #left="{ close }">
        <LetterTemplateEditor />
      </template>
    </SplitPanel>
  </div>
      </div>
      <div class="">
        
         <div>
    
    <div class="grid grid-cols-2 gap-4">
      <card>
        <div class="card-body flex items-center justify-between">
          <div class="">
              <div class="font-medium text-6xl text-stone-950 mb-2">4</div>
              <div class="text-md font-medium">Total Claims</div>
          </div>
          <div class="">
            <button class="flex items-center rounded-2xl border border-blue-800 text-sm text-blue-800 px-3 py-1 bg-blue-100 mb-3 font-medium">
          <Icon icon="line-md:chat-round-dots" width="30" height="30" class="mr-2" />Add Remark</button>
          <div class="bg-blue-700 text-white p-5 rounded-full mx-auto w-[80px] h-[80px]">
          <Icon icon="ep:first-aid-kit" width="40" height="40"  class="medical-icon" />
          </div>
          </div>
        </div>
      </card>
      <card>
        <div class="card-body">
          <div class="relative ">
              <div data-v-1590e322="" class="absolute -top-2 -right-8 bg-sky-500 text-white py-2 px-3 flex items-center text-xs"><span data-v-1590e322="">System Division</span><div data-v-1590e322="" class="w-0 h-0 border-t-8 border-r-8 border-blue-500 border-r-transparent absolute -bottom-2 right-0"></div></div>
          </div>
          <div class="">
              <div class="font-semibold text-sm text-blue-800 mb-2">Division Budget</div>
              <div class="flex items-center mt-2">
                <div class="mr-2 bg-blue-100 p-2 rounded-2xl"><Icon icon="ep:first-aid-kit" width="30" height="30" class="card-icon" /></div>
                <div class="text-sm">Medical Treatment</div>
              </div>
              <div class="font-semibold text-xl mt-2 mb-1 text-neutral-900">₹10,00,000</div>
              <div class="text-xs text-neutral-400">Total Medical Claim Amount</div>
          </div>
          
        </div>
      </card>
    </div>
  </div>
  <div class="mt-4 claimdetail">
  <card>
    <div class="flex justify-between items-center">
      <div class="font-semibold text-neutral-800">Claim Detail : <span class=" ">#095423     |  Rahul Sharma, MP</span></div>
      <div><Icon icon="mdi:keyboard-arrow-down" width="50" height="50" class="mdi-icon-svg" /></div>
    </div>
    <table class="w-full table-auto mt-3">
  <thead>
    <tr class="bg-blue-100">
      <th class="text-left px-5 py-2 text-neutral-700 font-semibold">Title</th>
      <th class="text-left px-5 py-2 text-neutral-700 font-semibold">Response</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">Test data1</td>
      <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">Test data 2</td>
    </tr>
     <tr>
      <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">Test Data3</td>
      <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">Test data 4</td>
    </tr>
     <tr>
      <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">Test Data 3</td>
      <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">Test Data 6</td>
    </tr>
  </tbody>
</table>
  <div class="text-md font-semibold text-neutral-800 mt-6 uppercase">Claim Amount Details</div>
  <div class="claimdetdiv">
    <div class="">
      <div class="grid grid-cols-2 gap-4 border border-neutral-300 rounded-sm px-3 py-2 mt-4">
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">Blood Test</div>
          <div class="font-medium text-neutral-600 text-xs">Medical procedure/ test/ medicine name</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">Type</div>
          <div class="font-medium text-neutral-600 text-xs">Usage Type</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">22/02/2025</div>
          <div class="font-medium text-neutral-600 text-xs">Date of Use/ Test/ Procedure</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">Document-name.pdf</div>
          <div class="font-medium text-neutral-600 text-xs">Upload Signed Copy of Itemized Bill</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">2</div>
          <div class="font-medium text-neutral-600 text-xs">Units Purchased</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">10,000</div>
          <div class="font-medium text-neutral-600 text-xs">Price per Unit</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm">20,000</div>
          <div class="font-medium text-neutral-600 text-xs">Amount Charged</div>
        </div>
        <div class="">
          <div class="font-semibold text-neutral-800 text-sm"><input type="checkbox" class="w-4 h-4"></div>
          <div class="font-medium text-neutral-600 text-xs">If Admissible Amount exists</div>
        </div>
      </div>
      <div class="flex justify-end">
      <table class="table-auto mt-3">
        <tr>
          <td class="w-[15rem]">Total Amount</td>
          <td class="w-[10rem] text-right">22,000</td>
        </tr>
        <tr>
          <td class="w-[15rem] font-semibold text-neutral-800 text-md">Total Admissible Amount</td>
          <td class="w-[10rem] font-semibold text-neutral-800 text-right text-md">20,000</td>
        </tr>
        
      </table>
      </div>
      <div class="text-md font-semibold text-neutral-800 mt-6 uppercase">Declaration</div>
      <div class="border-t border-b py-1 mt-2 border-neutral-300 flex justify-between items-center">
        <div class="text-sm">Upload duplicate claim papers/bills affidavit</div>
        <div class=""><button class="flex items-center rounded-2xl border border-sky-700 text-sm text-sky-700 px-3 py-1 bg-sky-100">
          <Icon icon="mdi:file-document-outline" width="24" height="24" class="mr-2" /> View Invoice Bill</button></div>
      </div>
    </div>
  </div>
  </card>
</div>
      </div>

  </div>
 

  <!-- <ResizablePanels /> -->
  <!-- split panel for letter add section -->
  
  <div class="flex items-center mt-4">
    <SelectInput v-model="selectedDepartment" class="mr-4">
    <option :value="mem.id" v-for="mem in members" :key="mem.id">
      {{ mem.name }}
    </option>

  </SelectInput>
  <button class="bg-blue-800 hover:bg-blue-700 rounded-md text-white px-6 py-2 text-sm">
        Submit
  </button>
  </div>

  
</template>

<script setup>
import { ref, computed,onMounted, watch } from "vue";
import Card from "@/ui-components/Card.vue";
import { StatusTrack } from "@sds/oneui-common-ui";
import StatusTimeLine from "@/components/StatusTimeLine.vue";
import LetterTemplateEditor from './LetterTemplateEditor.vue';
import ResizablePanels from './ResizablePanels.vue';
import SplitPanel from './SplitPanel.vue';
import Swal from 'sweetalert2';
import Icon from "@/ui-components/Icon.vue";
import { useApiStore } from "@/store/apiData";
import { SelectInput } from "@sds/oneui-common-ui";

// import { claimProgressById, claimDetailsById, claimDivisionBudget} from "@/services/rss/itEquipmentsService";
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from "vue-i18n";
import NoteSection from "./NoteSection.vue";

const router = useRouter();
const route = useRoute();
const apiStore = useApiStore();
const { t, locale } = useI18n();
const oklabel = computed(() => t('btn_ok'));
const notFoundMessage = computed(() => t('something_wrong'));
const letterPanelRef = ref();
 const letterSplitPanel = ref(true);
const minRightWidth = ref(0);
const progressbarTitle = ref('');
// switch ( userType ) {
//   case 'initiator': progressbarTitle.value = computed( () => t("initiator") + " " + t("menu.tab_2_1") + " " + t("menu.claims")); break;
//   case 'reviewer': progressbarTitle.value = computed( () => t("reviewer") + " " + t("menu.tab_2_1") + " " + t("menu.claims")); break;
//   case 'approver': progressbarTitle.value = computed( () => t("approver") + " " + t("menu.tab_2_1") + " " + t("menu.claims")); break;
//   default: progressbarTitle.value = computed( () => t("menu.tab_2_1") + " " + t("menu.claims"));
// }
// const refresh = computed(() => t('refresh'));
// const history = computed(() => t('status_history'));
// const claimId = ref(0);
// const pending = ref('pending');
// const stages = ref([
//   { key: "submitted", status: "in-process", delay: null, date: null },
//   { key: "initiate", status: pending.value, delay: null, date: null  },
//   { key: "review", status: pending.value, delay: null, date: null },
//   { key: "approve", status: pending.value, delay: null, date: null,}
// ]);

// const claimdata = ref([
//   { title: 'Whether the hospital/diagnostic/imaging center is empanelled under CGHS?', respnse: 'Yes' },
//   { title: 'Whether treatment was taken in emergency?', respnse: 'Yes' },
//   { title: 'Whether prior permission was taken for the treatment?', respnse: 'Yes' },
//   { title: 'Upload permission letter ', respnse: 'Document-name.pdf' },
//   { title: 'Whether subscribing to any health/medical insurance scheme?', respnse: 'Yes' },
//   { title: 'Enter the amount claimed/received', respnse: '10,000' },
//   { title: 'Details of Medical Advance taken (if any)', respnse: 'Medical Detail' },
//   { title: 'Name & address of the hospital / diagnostic center / imaging center', respnse: 'Hospital Name' },
//   { title: 'State(s)/UT name in which hospital/diagnostic/imaging center is established?', respnse: 'Delhi' },
// ]);

// const members = [
//   { id: 1, name: 'HR' },
//   { id: 2, name: 'Finance' },
//   { id: 3, name: 'Engineering' }
// ]

// const delayDays = ref(0);
// const delayMessage = ref(t('delay_status_message', {days: delayDays.value}));
// const updateStageNames = () => {
//   stages.value.forEach((stage) => {
//     stage.name = t(stage.key);
//   });
// };
// const fetchClaimProgress = async () => {
//     const response = await claimProgressById(claimId.value);
//     if ( response.isError || response.success_code !== 200) return;
//     const { Submitted, Initiated, Review, Approve, Extra } = response.data;
//     delayDays.value = Extra.delay || 0;
//     delayMessage.value = computed(() => t('delay_status_message', {days: delayDays.value}));
//     let delayMsg = delayMessage.value;
//     const updateStage = (index, data, translatedKey) => { console.log(stages.value[index].key);
//       if (Object.keys(data).length) {
//         stages.value[index].key = translatedKey;
//         stages.value[index].status = data.status.toLowerCase();
//         stages.value[index].date = data.datetime;
//       } else {
//         stages.value[index].status = delayMsg != '' ? 'in-process' : pending.value;
//         stages.value[index].delay = (index == 0) ? '' : delayMsg;
//         delayMsg = '';
//       }
//     };

//     updateStage(0, Submitted, "submitted");
//     updateStage(1, Initiated, "initiated");
//     updateStage(2, Review, "reviewed");
//     updateStage(3, Approve, "approved");
//     updateStageNames();
// }

// const fetchClaimDetail = async () => {
//     const response = await claimDetailsById(claimId.value);
//     if ( response.isError == false && response.success_code == 200) {
//       apiStore.setItEquipment({...apiStore.it_equipment, detail: response.data});
//     } else {
//       Swal.fire({
//         icon: 'error',
//         title: progressbarTitle.value,
//         text: notFoundMessage.value,
//         confirmButtonText: oklabel.value,
//         confirmButtonColor: '#4bc66d'
//       });
//       router.push({ 
//         name: 'i t equipment'
//       });
//     }
//     return true;
// };

</script>
