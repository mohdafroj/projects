<template>
  <div class="flex flex-col h-full py-0 gap-6 overflow-auto">
    <div class="h-full w-full overflow-x-auto">
      <div class="flex flex-col gap-6">
        <div :class="[
          'grid gap-4 overflow-x-auto responsive-grid',
          isNarrow ? 'grid-cols-1' : 'lg:grid-cols-2'
        ]">
          <!-- Card 1: Remaining Amount -->
          <div class=" bg-white rounded-lg shadow-sm p-6 border border-blue-100 border-dashed">
            <div class="flex flex-col md:flex-row md:justify-between sm:flex-row gap-2 mb-2">
              <!-- Title -->
              <div class="text-gray-700 text-lg font-medium">
                Member Remaining Amount
              </div>

              <!-- Remarks Button -->
              <button
                class="flex items-center border border-green-500 text-green-600 px-3 py-1 rounded-full hover:bg-green-50 transition whitespace-nowrap self-start md:self-center"
                @click="addNote">
                <Icon icon="heroicons:arrow-path-solid" width="16" height="16" class="mr-2" style="color: #8bea60" />
                <span class="text-sm">Remarks</span>
              </button>
            </div>

            <div class="text-gray-800 text-2xl font-semibold mt-2">{{ useLocalCurrency(financeBudget.remaining_amount) }}</div>

            <div class="text-gray-500 text-sm">
              Financial Entitlement : {{ useLocalCurrency(financeBudget.financial_amount) }}
            </div>

            <div class="mt-4">
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-green-500 h-2.5 rounded-full" :style="{width: financeBudgetBar + '%'}"></div>
              </div>
            </div>

            <div class="flex flex-col items-center justify-center w-full">
              <div class="text-gray-600 text-sm mt-1">
                Utilized Amount : -{{ useLocalCurrency(financeBudget.utilized_amount) }}
              </div>
              <div v-if="financeBudget.hold_amount > 0" class="text-gray-600 text-sm mt-1">
                Hold Amount : -{{ useLocalCurrency(financeBudget.hold_amount) }}
              </div>

              <div class="mt-4 flex flex-col items-center">
                <button v-if="!checked" @click="checkAdmissibility"
                  class="bg-green-600 rounded-full hover:bg-green-700 text-white px-4 py-1.5 flex items-center text-sm">
                  <Icon icon="material-symbols-light:select-check-box-sharp" width="24" height="24" />
                  Check Admissibility
                </button>
                <div v-else>
                  <!-- Alert -->
                  <div v-if="showAlert"
                    class="mt-2 px-2 bg-red-50 border border-red-300 rounded-md text-center">
                    The current bill amount exceeds the remaining amount.
                  </div>
                  <div v-else="showAlert"
                    class="mt-2 px-2 bg-green-50 border border-green-300 rounded-md text-center">
                    This admissible amount is valid for approval.
                  </div>
                </div>

                <button v-if="checked" @click="addNote" :disabled="notesSectionOpen" :class="[
                  'mt-4 text-white px-6 py-2.5 rounded-full flex items-center',
                  notesSectionOpen
                    ? 'bg-gray-400 cursor-not-allowed opacity-70'
                    : 'bg-green-500 hover:bg-green-600',
                ]">
                  Add Note
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1.5" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                      clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Card 2: Division Budget -->
          <div class="bg-white rounded-lg shadow-sm p-6 relative">
            <div class="text-gray-700 text-base font-medium">
              Division Budget
            </div>

            <div class="flex mt-4 flex-wrap lg:flex-nowrap">
              <div class="flex-1 w-full border-r pr-6">
                <div class="flex items-center mb-2">
                  <div class="bg-blue-100 p-1 rounded-md mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20"
                      fill="currentColor">
                      <path
                        d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.88a2 2 0 01-1.414-.586l-.708-.708A2 2 0 0010.586 2H6a2 2 0 00-2 2z" />
                    </svg>
                  </div>
                  <span class="text-blue-500 font-medium">ICT</span>
                </div>

                <div class="text-gray-800 text-xl font-semibold">{{ useLocalCurrency(divisionBudget.ict) }}</div>

                <div class="text-gray-500 text-sm mt-1">
                  Total ICT Claim Amount
                </div>
              </div>

              <div class="flex-1 w-full pl-6">
                <div class="flex items-center mb-2">
                  <span class="text-gray-600 font-medium mr-2">Digital</span>
                  <div class="bg-green-100 p-1 rounded-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20"
                      fill="currentColor">
                      <path fill-rule="evenodd"
                        d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586l3.293-3.293A1 1 0 0112 7z"
                        clip-rule="evenodd" />
                    </svg>
                  </div>
                </div>

                <div class="text-gray-800 text-xl font-semibold">{{ useLocalCurrency(divisionBudget.digital) }}</div>

                <div class="text-gray-500 text-sm mt-1">
                  Total Digital Claim Amount
                </div>
              </div>
            </div>

            <!-- System Division label -->
            <div
              class="absolute top-0 right-0 bg-blue-500 text-white py-3 px-4 rounded-tr-lg rounded-bl-xl flex items-center">
              <span>System Division</span>
              <div
                class="w-0 h-0 border-t-8 border-r-8 border-blue-500 border-r-transparent absolute -bottom-2 right-0">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ClaimTable -->
    <Card>

      <ClaimAccordion v-for="claim in claims" :key="claim.id" :claim="claim" />
    </Card>
    <InvoicePreview :files="uploadedFiles" @delete="uploadedFiles.splice($event, 1)" @close="showPreview = false" />
    <!-- Member Information Card -->
    <div class="bg-white rounded-lg shadow-sm p-4">
      <div class="text-gray-800 font-medium">Member Information</div>
      <div class="text-xs text-gray-500 mt-1">
        1st March 2021 to 31st March 2024
      </div>

      <!-- Member Details -->
      <div class="flex items-center mt-4">
        <div class="flex-shrink-0">
          <div class="h-10 w-10 rounded-full bg-purple-600 flex items-center justify-center text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
        </div>
        <div class="ml-3">
          <div class="text-sm font-medium">Member Name: {{ claimDetails?.first_name + ' ' + claimDetails.middle_name + ' ' + claimDetails.last_name}}</div>
          <div class="text-xs text-gray-500">Member ID: #{{ claimDetails.member_id }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineEmits, onMounted, watch } from "vue";
import Card from "@/ui-components/Card.vue";
//import ClaimTable from './ClaimTable.vue';
import { Icon } from "@iconify/vue";
import ClaimAccordion from "./ClaimAccordion.vue";
import InvoicePreview from "./InvoicePreview.vue";
import useLocalCurrency from "@/composables/useLocalCurrency";
import { useApiStore } from "@/store/apiData";

const apiStore = useApiStore();

const divisionBudget = ref({ict:0, digital:0});
const financeBudget = ref({financial_amount:0, utilized_amount:0, hold_amount:0, remaining_amount:0});
const financeBudgetBar = ref(0);
const claimDetails = ref({
  amount:0, claim_code:0, claim_date:0, claim_id:0, claim_items: [], documents: [], notes:[],
  first_name:'',last_name:'', middle_name:'', member_code:'', member_id:0,
});
watch(
  () => apiStore.it_equipment.detail?.division_budget,
  (newData) => {
    if (newData) {
      divisionBudget.value = newData;
    }
  },
  { immediate: true }
);

watch(
  () => apiStore.it_equipment.detail?.finance_details,
  (newData) => {
    if (newData) {
      financeBudget.value = newData;
      financeBudgetBar.value = Number((newData.remaining_amount * 100) / newData.financial_amount).toFixed(2);
    }
  },
  { immediate: true }
);

watch(
  () => apiStore.it_equipment.detail,
  (newData) => {
    if (newData) {
      claimDetails.value = { ...newData };
    }
  },
  { immediate: true }
);

watch(
  () => apiStore.it_equipment.detail,
  (newData) => {
    if (newData) {
      claimDetails.value = { ...newData };
      //Calculate Admissible amout status
      let financeBudget = claimDetails.value.finance_details?.remaining_amount - claimDetails.value.amount;
      if ( financeBudget >= 0 ) {
        showAlert.value = true;
      } else {
        showAlert.value = false;
      }
    }
  },
  { deep: true }
);


// Define emits
const emit = defineEmits(["add-note"]);

// State to control alert visibility
const showAlert = ref(false);
const checked = ref(false);
const notesSectionOpen = ref(false);

// First step: Check admissibility and show results
const checkAdmissibility = () => {

  showAlert.value = true;
  checked.value = true;
};

const claims = [
  {
    id: "081451",
    name: "Deepak Kumar Gupta, MP",
    items: [
      {
        name: "OnePlus 7Pro",
        head: "ICT",
        price: 10000,
        qty: 1,
        checked: false,
      },
      {
        name: "Magic Mouse",
        head: "Digital",
        price: 10000,
        qty: 1,
        checked: true,
      },
      {
        name: "Wooden Chair",
        head: "ICT",
        price: 10000,
        qty: 1,
        checked: true,
      },
      {
        name: "Mobile Charger",
        head: "Digital",
        price: 10000,
        qty: 1,
        checked: false,
      },
    ],
  },
];

//invoice file
const uploadedFiles = ref([
  { name: 'Invoice.pdf', url: '/docs/invoice.pdf' },
  { name: 'Invoice.jpg', url: '/images/invoice.jpg' },
])

// Second step: Add note and trigger panel change
const addNote = () => {
  // Set the notes section as open to disable the button
  notesSectionOpen.value = true;
  // Emit event to parent component to show the split panel
  emit("add-note", notesSectionOpen.value);
};
const isNarrow = ref(false)
let observer
onMounted( async () => {
  const container = document.querySelector('.responsive-grid')
  observer = new ResizeObserver(([entry]) => {
    const widthPercent = (entry.contentRect.width / window.innerWidth) * 100
    isNarrow.value = widthPercent < 40
  })

  observer.observe(container)
})
</script>
<style lang="css" scoped>
.responsive-grid {
  container-type: inline-size;
}

@container (max-width: 40vw) {
  .responsive-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>