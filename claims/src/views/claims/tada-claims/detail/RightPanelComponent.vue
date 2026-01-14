<template>
  <div class="flex flex-col py-0 gap-6 ">
    <div class="h-full w-full">
      <div class="flex flex-col gap-6">
        <div :class="[
          'grid gap-4 responsive-grid',
          isNarrow ? 'grid-cols-1' : 'lg:grid-cols-2'
        ]">
          <!-- Card 1: Remaining Amount -->
 
          <Card class="border-[1px] rounded-md">
            <div class="rounded-lg ">
              <div class="flex flex-col md:flex-row md:justify-between sm:flex-row gap-2 mb-1">
                <!-- Title -->
                <div class="dark:text-slate-300 text-md  mt-2 font-medium">
                  Travelling Allowances Claim
                </div>
              </div>


              <div class="flex justify-between text-gray-500 text-sm mb-3">
                <span>Total Available Journey</span>
                <span>{{ travellingData?.total_available_journey }}</span>
              </div>

              <div class="flex justify-between text-gray-500 text-sm mb-3">
                <span>Total Used Journey</span>
                <span>{{ travellingData?.total_used_journey }}</span>
              </div>

              <div class="flex justify-between text-gray-500 text-sm mb-3">
                <span>Spouse Journey</span>
                <span>{{ travellingData?.spouse_journey }}</span>
              </div>

              <div class="flex justify-between text-gray-500 text-sm mb-3">
                <span>Used Spouse Journey</span>
                <span>{{ travellingData?.spouse_used_journey }}</span>
              </div>

              <div class="flex justify-between text-gray-500 text-sm ">
                <span>Companion Journey</span>
                <span>{{ travellingData?.companion_journey }}</span>
              </div>
              <div class="flex justify-between text-gray-500 text-sm mt-3">
                <span>Used Companion Journey</span>
                <span>{{ travellingData?.companion_used_journey }}</span>
              </div>


            </div>
          </Card>

          <!-- Card 2: Division Budget -->
          <Card class="border-[1px] rounded-md">
            <div class="rounded-lg relative">
              <div class="dark:text-slate-300 text-md font-medium mt-2">
                Division Budget

              </div>
              <div class="rounded-lg mt-2">
                <p class="text-center mt-2">
                  Total Amount: {{ useLocalCurrency(
                    (claimDetails?.domestic_travel_amount || 0) + (claimDetails?.allowances_amount || 0)
                  ) }}
                </p>

              </div>

              <div class="flex mt-4 flex-wrap">
                <div class="flex-1 w-full border-r pr-1 mb-4">
                  <div class="flex items-center mb-2">
                    <div class="bg-blue-100 p-1 rounded-md mr-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-2 text-blue-500" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path
                          d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.88a2 2 0 01-1.414-.586l-.708-.708A2 2 0 0010.586 2H6a2 2 0 00-2 2z" />
                      </svg>

                    </div>
                    <span class="text-xs">Domestic Travel</span>

                  </div>

                  <div class="text-md font-semibold">{{
                    useLocalCurrency(claimDetails?.domestic_travel_amount) }}</div>

                  <div class="text-gray-500 text-xs mt-1">
                    Total Domestic Travel Claim

                  </div>
                </div>

                <div class="flex-1 w-full">
                  <div class="flex items-center mb-2">
                    <span class="text-gray-600 text-xs  ml-2">Allowances &nbsp;</span>
                    <div class="bg-green-100 p-1 rounded-md">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-2 mr-2 text-green-500"
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                          d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586l3.293-3.293A1 1 0 0112 7z"
                          clip-rule="evenodd" />
                      </svg>
                    </div>
                  </div>

                  <div class="text-xl font-semibold ml-1">{{
                    useLocalCurrency(claimDetails?.allowances_amount) }}</div>

                  <div class="text-gray-500 text-xs mt-1 ml-1">
                    Total Allowances
                  </div>
                  <!-- <div class="text-gray-500 text-sm ml-1">{{ useLocalCurrency(claimDetails.digital) }}</div> -->
                </div>
              </div>

              <!-- System Division label -->
              <div
                class="absolute mt-[-35px] mr-[-20px] top-0  right-0 bg-blue-500 text-white py-2 px-3 rounded-tr-lg rounded-bl-xl flex items-center">
                <span>Systems Division</span>
                <div
                  class="w-0 h-0 border-t-8 border-r-8 border-blue-500 border-r-transparent absolute -bottom-2 right-0">
                </div>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>

    <!-- ClaimTable -->
    <Card>
      <ClaimAccordion v-for="claim in claims" :key="claim.id" :claim="claim" :addNote="addNote" />
    </Card>
    <InvoicePreview :files="documents" :showInvoice="showInvoice" @close="handleCloseInvoice" />
    <!-- Member Information Card -->

    <Card class="rounded-xl">
      <!-- Header -->
      <div class="mb-3">
        <h2 class="text-gray-700 font-semibold text-sm">Member Information</h2>
        <p class="text-xs  flex items-center mt-1 dark:text-slate-300">
          <Icon icon="circum:calendar" class="w-4 h-4 mr-2" />
          {{ claimDetails?.term_start_date }} to {{ claimDetails?.term_end_date }} ({{ claimDetails?.term_title }})
        </p>
      </div>

      <!-- Profile -->
      <div class="flex items-center mb-4">
        <img :src="claimDetails?.member_profile_phpto" alt="Profile" class="w-20 rounded-full border mr-3">
        <div>
          <h6 class="font-semibold text-gray-800">{{ claimDetails?.title }} {{ claimDetails?.full_name }}</h6><br>
          <span
            class="bg-gray-100 dark:bg-slate-700 px-2 rounded-md  dark:text-slate-300 text-gray-600 text-xs font-medium px-3 py-1 rounded-full">{{
              claimDetails?.elected_nominated_status }}</span>
        </div>
      </div>

      <!-- Info -->
      <div class="space-y-2 text-sm">
        <div class="flex items-center bg-gray-100 dark:bg-slate-700 px-2 rounded-md  dark:text-slate-300 py-1">
          <Icon icon="fluent-mdl2:contact-info" class="w-4 h-4 text-gray-500 mr-2" />
          <span>Member IC NO. <strong># {{ claimDetails.member_id }}</strong></span>
        </div>

        <div class="flex items-center bg-gray-100 dark:bg-slate-700 px-2 rounded-md  dark:text-slate-300 py-1">
          <Icon icon="fluent-mdl2:group" class="w-4 h-4 text-gray-500 mr-2" />
          <span>Party Name: <strong>{{ claimDetails?.party_name }}</strong></span>
        </div>

        <div class="flex items-center bg-gray-100 dark:bg-slate-700 px-2 rounded-md  dark:text-slate-300 py-1">
          <Icon icon="gis:location-poi" class="w-4 h-4 text-gray-500 mr-2" />
          <span>State: <strong>{{ claimDetails?.state_shortname }}</strong></span>
        </div>
      </div>
    </Card>

  </div>
</template>

<script setup>
import { ref, defineEmits, onMounted, watch } from "vue";
import { Card } from "@sds/oneui-common-ui";
import { Icon } from "@iconify/vue";
import ClaimAccordion from "./ClaimAccordion.vue";
import InvoicePreview from "./InvoicePreview.vue";
import useLocalCurrency from "@/composables/useLocalCurrency";
import { useApiStore } from "@/store/apiData";

const apiStore = useApiStore();

// const divisionBudget = ref({ domestic_travel_amount: 0, allowances_amount: 0, total_allowances_amount: 0 });
//const financeBudget = ref({ financial_amount: 0, utilized_amount: 0, hold_amount: 0, remaining_amount: 0 });
const travellingData = ref({
  total_available_journey: 0, total_used_journey: 0, spouse_journey: 0, spouse_used_journey: 0,
  companion_journey: 0, companion_used_journey: 0
});
const financeBudgetBar = ref(0);
const claimDetails = ref({
  amount: 0, claim_code: 0, claim_date: 0, claim_id: 0, claim_items: [], documents: [], notes: [],
  first_name: '', last_name: '', middle_name: '', member_code: '', member_id: 0,
});
const claims = ref([{ id: '', name: '' }]);
const documents = ref([]);

//invoice file
const showInvoice = ref(apiStore.tada_claim.action.showInvoice || false);
const handleCloseInvoice = async () => {
  apiStore.setTadaClaim({ ...apiStore.tada_claim.action, showInvoice: false });
  showInvoice.value = apiStore.tada_claim.action.showInvoice;
};

watch(
  () => apiStore.tada_claim.detail?.travelling_available,
  (newData, oldData) => {
    if (newData !== oldData) {
      // console.log("New Data", newData);

      travellingData.value = newData;
    }
  },
  { immediate: true }
);



// watch(
//   () => apiStore.tada_claim.detail?.finance_details,
//   (newData) => {
//     if (newData) {
//       financeBudget.value = newData;
//       financeBudgetBar.value = Number((newData.remaining_amount * 100) / newData.financial_amount).toFixed(2);
//     }
//   },
//   { immediate: true }
// );

watch(
  () => apiStore.tada_claim.detail,
  (newData) => {
    if (newData) {
      claimDetails.value = { ...newData };
      let fullName = claimDetails.value.title ? claimDetails.value.title + ' ' + claimDetails.value.full_name : claimDetails.value.full_name;
      claims.value = [{ id: claimDetails.value.claim_code, name: fullName, items: claimDetails.value.claim_items }]
    }
  },
  { immediate: true }
);

watch([
  () => apiStore.tada_claim.detail,
  () => apiStore.tada_claim.action?.showInvoice
],
  ([newDetail, newAction], [oldDetail, oldAction]) => {
    if (newDetail != oldDetail) {
      claimDetails.value = { ...newDetail };
      if (claimDetails.value?.documents?.invoice && Array.isArray(claimDetails.value?.documents?.invoice)) {
        documents.value = claimDetails.value.documents?.invoice.map(item => {
          return { name: '', url: item }
        })
      } else {
        documents.value = [];
      }
      //Calculate Admissible amout status
      if (claimDetails.value.finance_details?.remaining_amount > 0) {
        alertMessage.value.show = 0;
      } else {
        alertMessage.value.show = 1;
      }
    }

    if (newAction != oldAction) {
      showInvoice.value = newAction.showInvoice;
    }
  },
  { deep: true }
);

// Define emits
const emit = defineEmits(["add-note"]);

const alertMessage = ref({
  show: 0,
  message0: 'This admissible amount is valid for approval.',
  message1: 'The current bill amount exceeds the remaining amount.'
});
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
onMounted(async () => {
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