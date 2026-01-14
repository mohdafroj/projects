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
              <!-- <button
                class="flex items-center border border-green-500 text-green-600 px-3 py-1 rounded-full hover:bg-green-50 transition whitespace-nowrap self-start md:self-center"
                @click="addNote">
                <Icon icon="heroicons:arrow-path-solid" width="16" height="16" class="mr-2" style="color: #8bea60" />
                <span class="text-sm">Add Remarks</span>
              </button> -->
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
                Utilized Amount : <span class="font-semibold">{{ useLocalCurrency(financeBudget.utilized_amount) }}</span>
              </div>
              <div v-if="financeBudget.hold_amount > 0" class="text-gray-600 text-sm mt-1">
                Hold Amount : <span class="font-semibold">{{ useLocalCurrency(financeBudget.hold_amount) }}</span>
              </div>

              <div class="mt-4 flex flex-col items-center">
                <div
                  class="mt-2 px-2 border rounded-md text-center"
                  :class="{
                    'bg-red-50 border-red-300': isAdmissibility == false,
                    'bg-green-50 border-green-300': isAdmissibility == true,
                  }"
                >
                {{ isAdmissibility ? 'This admissible amount is valid for approval.' :'The current bill amount exceeds the remaining amount.' }}
                </div>
              </div>
            </div>
          </div>

          <!-- Card 2: Division Budget -->
          <div class="bg-white rounded-lg shadow-sm p-6 relative">
            <div class="text-gray-700 text-base font-medium mt-8">
              Division Budget
            </div>

            <div class="flex mt-4 flex-wrap">
              <div class="flex-1 w-full border-r pr-6 mb-4">
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

                <div class="text-gray-800 text-xl font-semibold">{{ useLocalCurrency(divisionBudget.ict_allocated_amount) }}</div>

                <div class="text-gray-500 text-sm mt-1">
                  Total ICT Claim Amount
                </div>
                <div class="text-gray-500 text-sm">{{ useLocalCurrency(divisionBudget.ict) }}</div>
              </div>

              <div class="flex-1 w-full">
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

                <div class="text-gray-800 text-xl font-semibold">{{ useLocalCurrency(divisionBudget.digital_allocated_amount) }}</div>

                <div class="text-gray-500 text-sm mt-1">
                  Total Digital Claim Amount
                </div>
                <div class="text-gray-500 text-sm">{{ useLocalCurrency(divisionBudget.digital) }}</div>
              </div>
            </div>

            <!-- System Division label -->
            <div
              class="absolute top-0 right-0 bg-blue-500 text-white py-3 px-4 rounded-tr-lg rounded-bl-xl flex items-center">
              <span>Systems Division</span>
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
      <ClaimAccordion v-for="claim in claims" :key="claim.id" :claim="claim" :addNote="addNote" />
    </Card>
    <InvoicePreview :files="documents" :showInvoice="showInvoice" @close="handleCloseInvoice" />
    <!-- Member Information Card -->

  <div class="bg-white shadow-md rounded-xl p-5 overflow-hidden">    
      <h2 class="text-gray-800 font-semibold text-lg">Member Information</h2>
      <p class="mb-4 text-xs text-gray-500 flex items-center mt-1">
        <Icon icon="circum:calendar" class="w-4 h-4 text-gray-500 mr-2" />
        {{ claimDetails?.term_start_date }} To {{ claimDetails?.term_end_date }} 
        <Badge v-if="claimDetails?.term_title == 'Phase 1'" :text="claimDetails?.term_title" type="info" class="ml-2" />
        <Badge v-else-if="claimDetails?.term_title == 'Phase 2'" :text="claimDetails?.term_title" type="danger" class="ml-2" />
        <Badge v-else :text="claimDetails?.term_title" type="primary" class="ml-2" />
      </p>

    <div
      @click="memberInformationOpen = !memberInformationOpen" 
      class="flex justify-between w-full text-left border-md cursor-pointer"
    >
      <!-- Profile -->
      <div class="flex items-center mb-4">
        <img :src="claimDetails?.member_profile_phpto" alt="Profile" class="w-20 rounded-full border mr-3">
        <div>
          <h6 class="font-semibold text-gray-800">{{ claimDetails?.title }} {{ claimDetails?.full_name }}</h6><br>
          <span class="bg-gray-100 text-gray-600 text-xs font-medium px-3 py-1 rounded-full">{{ claimDetails?.elected_nominated_status }}</span>
        </div>
      </div>

      <Icon
        icon="mdi:chevron-down"
        class="transition-transform duration-300"
        :class="{ 'rotate-180': memberInformationOpen }"
        width="24"
        height="24"
      />
    </div>

      <!-- Info -->
      <div v-if="memberInformationOpen" class="space-y-2 text-sm">
        <div class="flex items-center bg-gray-100 py-1">
          <Icon icon="fluent-mdl2:contact-info" class="w-4 h-4 text-gray-500 mr-2" />
          <span>Member IC NO. <strong># {{ claimDetails.member_id }}</strong></span>
        </div>

        <div class="flex items-center bg-gray-100 py-1">
          <Icon icon="fluent-mdl2:group" class="w-4 h-4 text-gray-500 mr-2" />
          <span>Party Name: <strong>{{ claimDetails?.party_name }}</strong></span>
        </div>

        <div class="flex items-center bg-gray-100 py-1">
          <Icon icon="gis:location-poi" class="w-4 h-4 text-gray-500 mr-2" />
          <span>State: <strong>{{ claimDetails?.state_shortname }}</strong></span>
        </div>
        <table class="w-full text-sm text-center border">
          <caption class="text-left text-sm font-semibold text-gray-600 mb-0">Previous Claims</caption>
          <thead class="bg-gray-50">
            <tr>
              <th class="p-2 border text-left">Claim Code</th>
              <th class="p-2 border">Claim Date</th>
              <th class="p-2 border">Member Docs</th>
              <th class="p-2 border">Issued Documents</th>
              <th class="p-2 border">Issued Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in previousClaims">
              <td class="p-2 border text-left">{{ item.claim_code || '-----' }}</td>
              <td class="p-2 border">{{ item.claim_date || '-----' }}</td>
              <td class="p-2 border items-center">
                <div class="flex items-center gap-2">
                  <Icon v-for="inv in item.documents.invoice" @click="() => { selectedItem = {url: inv, name:'invoice.pdf'}; previousCliamModalShow = true }" icon="carbon:document-view" class="text-green-600 cursor-pointer" width="16" height="16" />

                </div>
              </td>
              <td class="p-2 border">
                <div class="flex items-center gap-2">
                  <Icon v-for="inv in item.documents['it-claim-e-sign']" @click="() => { selectedItem = {url: inv, name:'invoice.pdf'}; previousCliamModalShow = true }" icon="carbon:document-view" class="text-green-600 cursor-pointer" width="16" height="16" />
                </div>                
              </td>
              <td class="p-2 border">{{ item.issue_date || '-----' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
  </div>

  </div>

<Modal
    :modelValue="previousCliamModalShow"
    :title="' '"
    size="xl"
    @close="() => previousCliamModalShow = false"
  >
  <div class="flex items-center justify-left mb-1">
    <div @click="() => downloadFileByUrl(selectedItem?.url, selectedItem?.name)" class="z-10 px-4 py-2 mr-5">
      <Icon icon="hugeicons:download-04" class="text-2xl cursor-pointer" />
    </div> 
    <div v-if="isImage(selectedItem?.url)" @click="() => printFileByUrl(selectedItem?.url)" class="z-10 px-4 py-2 ml-5">
      <Icon icon="fluent:print-32-regular" class="text-2xl cursor-pointer" />
    </div>       
  </div>
  <div class="w-full h-[95vh] flex items-center justify-center">

      <img
        v-if="isImage(selectedItem?.url)"
        :src="selectedItem?.url"
        class="w-full h-full object-contain"
      />
      <object
        v-else
        :data="selectedItem?.url"
        type="application/pdf"
        class="w-full h-full object-contain"
      />
  </div>
</Modal>

</template>

<script setup>
import { ref, defineEmits, onMounted, watch } from "vue";
import { Card, Badge, Modal } from "@sds/oneui-common-ui";
import { Icon } from "@iconify/vue";
import ClaimAccordion from "./ClaimAccordion.vue";
import InvoicePreview from "./InvoicePreview.vue";
import useLocalCurrency from "@/composables/useLocalCurrency";
import { useApiStore } from "@/store/apiData";
import { memberClaims } from "@/services/rss/itEquipmentsService";
import { isImage, downloadFileByUrl, printFileByUrl } from "@/utils/downloads";

const apiStore = useApiStore();
const isAdmissibility = ref(false);
const memberInformationOpen = ref(false);
const divisionBudget = ref({ict:0, digital:0, ict_allocated_amount: 0, digital_allocated_amount: 0});
const financeBudget = ref({financial_amount:0, utilized_amount:0, hold_amount:0, remaining_amount:0});
const financeBudgetBar = ref(0);
const claimDetails = ref({
  amount:0, claim_code:0, claim_date:0, claim_id:0, claim_items: [], documents: [], notes:[],
  first_name:'',last_name:'', middle_name:'', member_code:'', member_id:0,
});
const claims = ref([{id:'', name:''}]);
const previousClaims = ref([]);
const previousCliamModalShow = ref(false);
const selectedItem = ref({});

const documents = ref([]);

//invoice file
const showInvoice = ref(apiStore.it_equipment.action.showInvoice || false);
const handleCloseInvoice = async () => {
  apiStore.setItEquipmentAct({...apiStore.it_equipment.action, showInvoice:false});
  showInvoice.value = apiStore.it_equipment.action.showInvoice;
};

const fetchMemberClaims = async () => {
  if ( !claimDetails.value.claim_id ) return false;
  const response = await memberClaims(claimDetails.value.claim_id);
  previousClaims.value = response.data || [];
}

watch([
    () => apiStore.it_equipment.detail,
    () => apiStore.it_equipment.action?.showInvoice
  ],
  ([newDetail, showInvoiceFlag]) => {
    if (!newDetail) return;

    if (newDetail?.division_budget) divisionBudget.value = newDetail?.division_budget;

    if (newDetail?.finance_details) {
      financeBudget.value = newDetail?.finance_details;
      const { remaining_amount = 0, financial_amount = 0 } = financeBudget.value;
      financeBudgetBar.value = financial_amount ? Number((remaining_amount * 100) / financial_amount).toFixed(2) : '0.00';
      isAdmissibility.value = remaining_amount > 0;
    }

    claimDetails.value = { ...newDetail };
    let fullName = newDetail.title ? `${newDetail.title} ${newDetail.full_name}` : newDetail.full_name;
    claims.value = [{id: newDetail.claim_code, name: fullName, items: newDetail.claim_items}];

    const invoiceDocs = newDetail.documents?.invoice;
    documents.value = Array.isArray(invoiceDocs) ? invoiceDocs.map(url => ({ name: '', url })) : [];

    showInvoice.value = showInvoiceFlag?.showInvoice ?? false;
    fetchMemberClaims();    
  },
  { immediate: true }
);

// Define emits
const emit = defineEmits(["add-note"]);

const notesSectionOpen = ref(false);

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