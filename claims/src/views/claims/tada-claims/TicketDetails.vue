<template>
  <div>
    <!-- Ticket Details -->
    <div class="rounded-2xl border border-gray-200 bg-white w-full dark:border-gray-800">
      <div class="px-6 py-5">
        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
          Ticket Detail
        </h3>
      </div>
      <div
        class="grid grid-cols-2 gap-6 border-t border-gray-200 p-4 sm:p-6 lg:grid-cols-2 xl:grid-cols-2 dark:border-gray-800">
        <!-- Flight Information -->
        <div>
          <div
            class="divide-y divide-gray-100 rounded-t-xl border border-gray-200 p-5 dark:divide-gray-800 dark:border-gray-800">
            <!-- Plane Info -->
            <div class="text-center mb-2">
              <h2 class="text-lg font-semibold text-gray-800 pb-2">
                Flight {{ ticketData.flight_number }}
              </h2>
            </div>
            <FileUploads ref="pdfUploadRef" :onFileUpload="postMethod" :multiple="false" class="hidden"
              @update:files="handlePdfUpload" />
            <!-- Route -->
            <div class="flex items-center justify-between py-5">
              <div class="text-left">
                <p class="text-2xl font-bold text-blue-600">{{ ticketData.from }}</p>
                <p class="text-sm text-gray-600">{{ formatTime(ticketData.depart_time) }}</p>
                <p class="text-xs text-gray-500">{{ formatDate(ticketData.depart_date) }}</p>
              </div>

              <div class="flex flex-col items-center">
                <div class="flex items-center space-x-2">
                  <svg width="140" height="21" viewBox="0 0 140 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle opacity="0.2" cx="4.11328" cy="10.6562" r="3" stroke="#1A171B" stroke-width="2" />
                    <rect opacity="0.2" x="10.7764" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="18.4385" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="26.1016" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="33.7646" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="41.4277" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <path
                      d="M73.3771 12.3229L67.7969 18.9896L65.5883 18.9896L68.3778 12.3229L62.4586 12.3229L60.6188 14.8229L58.9623 14.8229L60.0666 11.0729L58.9623 7.32292L60.6188 7.32292L62.4597 9.82292L68.3789 9.82292L65.5883 3.15625L67.7969 3.15625L73.3771 9.82292L79.3923 9.82292C79.8317 9.82292 80.253 9.95461 80.5637 10.189C80.8743 10.4235 81.0488 10.7414 81.0488 11.0729C81.0488 11.4044 80.8743 11.7224 80.5637 11.9568C80.253 12.1912 79.8317 12.3229 79.3923 12.3229L73.3771 12.3229Z"
                      fill="#1877F2" />
                    <rect opacity="0.2" x="93.582" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="101.245" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="108.908" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="116.57" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <rect opacity="0.2" x="124.233" y="10.1562" width="5" height="1" rx="0.5" fill="#1A171B" />
                    <circle cx="135.896" cy="10.6562" r="3" stroke="#3F8AEF" stroke-width="2" />
                  </svg>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ getAirlineName() }}</p>
              </div>

              <div class="text-right">
                <p class="text-2xl font-bold text-blue-600">{{ ticketData.to }}</p>
                <p class="text-sm text-gray-600">{{ formatTime(ticketData.arrival_time) }}</p>
                <p class="text-xs text-gray-500">{{ formatDate(ticketData.arrival_date) }}</p>
              </div>
            </div>

            <!-- Passenger Info -->
            <div class="grid grid-cols-2 gap-4 text-sm pt-5"
              v-if="ticketData.passengers && ticketData.passengers.length > 0">
              <div>
                <p class="text-gray-500">Passenger Name</p>
                <p class="font-medium text-gray-800">{{ getPassengerFullName() }}</p>
              </div>
              <div class="text-right">
                <p class="text-gray-500">Ticket Number</p>
                <p class="font-semibold text-gray-900">{{ ticketData.passengers[0].ticket_no }}</p>
              </div>
              <div>
                <p class="text-gray-500">PNR No.</p>
                <p class="font-medium text-gray-800">{{ ticketData.pnr }}</p>
              </div>
              <div class="text-right">
                <p class="text-gray-500">Journey Status</p>
                <p :class="ticketData.complete_journey_certification === 'Yes' ? 'text-green-600' : 'text-orange-600'"
                  class="font-semibold">
                  {{ ticketData.complete_journey_certification === 'Yes' ? 'Complete' : 'Incomplete' }}
                </p>
              </div>
              <div v-if="ticketData.passengers[0].emailAddresses !== 'NA'">
                <p class="text-gray-500">Email</p>
                <p class="font-medium text-gray-800 text-xs">{{ ticketData.passengers[0].emailAddresses }}</p>
              </div>
              <div v-if="ticketData.passengers[0].phoneNumbers !== 'NA'" class="text-right">
                <p class="text-gray-500">Phone</p>
                <p class="font-medium text-gray-800">{{ ticketData.passengers[0].phoneNumbers }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-b-xl border border-t-0 border-gray-200 p-5 dark:border-gray-800">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Flight Number</span>
              <span class="text-sm text-gray-700 dark:text-gray-400">#{{ ticketData.flight_number }}</span>
            </div>
          </div>
        </div>

        <!-- Payment Breakup -->
        <div class=" divide-gray-100 rounded-t-xl border border-gray-200 p-5 dark:divide-gray-800 dark:border-gray-800">
          <h3 class="text-base font-bold text-gray-800 dark:text-white/90">
            Payment Breakup
          </h3>
          <div class="border-t border-gray-200 mt-4 sm:p-1 dark:border-gray-800">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
              <!-- Base Fare -->
              <li class="flex items-center gap-5 py-2">
                <span class="w-1/2 text-sm font-bold text-gray-700 sm:w-2/3 dark:text-gray-400">Base Fare</span>
                <span class="w-1/2 text-sm font-bold text-gray-700 sm:w-2/3 dark:text-gray-400 text-right">
                  INR {{ formatAmount(ticketData.payment_details.baseFare) }}
                </span>
              </li>

              <!-- Acceptable Taxes -->
              <li v-for="(tax, index) in ticketData.payment_details.acceptableTaxs" :key="'acceptable-' + index"
                class="flex items-center gap-5 py-2">
                <span class="w-1/2 text-sm font-bold text-gray-700 sm:w-2/3 dark:text-gray-400">{{ tax.name }}
                  Tax</span>
                <span class="w-1/2 text-sm font-bold text-gray-700 sm:w-2/3 dark:text-gray-400 text-right">
                  INR {{ formatAmount(tax.charges) }}
                </span>
              </li>

              <!-- Non-Acceptable Taxes (strikethrough) -->
              <li v-for="(tax, index) in ticketData.payment_details.nonAcceptableTaxs" :key="'non-acceptable-' + index"
                class="flex items-center gap-5 py-2">
                <span class="w-1/2 text-sm text-gray-500 sm:w-2/3 dark:text-gray-400 line-through">{{ tax.name }}
                  Tax</span>
                <span class="w-1/2 text-sm text-gray-500 sm:w-2/3 dark:text-gray-400 line-through text-right">
                  INR {{ formatAmount(tax.charges) }}
                </span>
              </li>

              <!-- Service Results (like penalty fees) - strikethrough as non-admissible -->
              <li v-for="(service, index) in ticketData.service_result" :key="'service-' + index"
                class="flex items-center gap-5 py-2">
                <span class="w-1/2 text-sm text-gray-500 sm:w-2/3 dark:text-gray-400 line-through">{{
                  service.service_name }}</span>
                <span class="w-1/2 text-sm text-gray-500 sm:w-2/3 dark:text-gray-400 line-through text-right">
                  INR {{ formatAmount(service.baseFare) }}
                </span>
              </li>

              <!-- Refunded Base Fare (if any) -->
              <li v-if="ticketData.payment_details.refundedBaseFare > 0" class="flex items-center gap-5 py-2">
                <span class="w-1/2 text-sm text-red-500 sm:w-2/3 dark:text-gray-400">Refunded Base Fare</span>
                <span class="w-1/2 text-sm text-red-500 sm:w-2/3 dark:text-gray-400 text-right">
                  -INR {{ formatAmount(ticketData.payment_details.refundedBaseFare) }}
                </span>
              </li>
            </ul>

            <p class="text-sm text-gray-500 text-center mt-2">Strikethrough means non-admissible</p>

            <ul class="divide-y divide-gray-100 dark:divide-gray-800 mt-4 pt-4 border-t">
              <li class="flex items-center gap-5 py-2">
                <span class="w-1/2 text-md font-bold text-gray-700 sm:w-2/3 dark:text-gray-400">Total Admissible
                  Amount</span>
                <span class="w-1/2 text-md font-bold text-green-600 sm:w-2/3 dark:text-gray-400 text-right">
                  INR {{ formatAmount(getAdmissibleTotal()) }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- File Uploads for Air Journey -->
    <div class="mt-6">
      <!-- Upload Ticket Bill -->
      <div class="mb-6">
        <Card>
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Upload Ticket Bill</h2>
          </div>
         <FileUploads :isRequired="true" ref="uploadRef1" :onFileUpload="postMethod" :multiple="true"
  @update:files="(files) => handleFiles(files, 0, 'ticketBill')" />


        </Card>
      </div>

      <!-- Upload Boarding Pass -->
      <div class="mb-6">
        <Card>
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Upload Boarding Pass</h2>
          </div>
         <FileUploads :isRequired="true" ref="uploadRef" :onFileUpload="postMethod" :multiple="true"
  @update:files="(files) => handleFiles(files, 0, 'boardingPass')" />

        </Card>
      </div>
    </div>



            <!-- End Select Visit Purpose -->

    <!-- Additional Journey Types Section -->
    <div v-if="additionalJourneys.length > 0"
      class="mt-6 rounded-2xl border border-gray-200 bg-white w-full dark:border-gray-800">
      <div class="px-6 py-5">
        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
          Additional Journey Types
        </h3>
      </div>
      <div class="border-t border-gray-200 p-6 dark:border-gray-800">
        <div v-for="(journey, index) in additionalJourneys" :key="index"
          class="mb-6 p-4 border border-gray-200 rounded-lg">
          <div class="flex justify-between items-center mb-4">
         <h4 class="text-lg font-semibold text-gray-800">
  {{ journey.type === 'road' ? 'Road Journey' : 'Water Journey' }}
</h4>

            <button @click="removeJourney(index)" class="text-red-600 hover:text-red-800 font-medium">
              Remove
            </button>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
            <p><strong>Purpose of Visit:</strong> {{ journey.selectedVisitPurposes }}</p>
              <p><strong>Date of Journey:</strong> {{ journey.dateOfJourney }}</p>
              <p><strong>From:</strong> {{ journey.fromLocation }}</p>
              <p><strong>To:</strong> {{ journey.toLocation }}</p>
            </div>
            <div>
              <p><strong>Distance:</strong> {{ journey.distance }} Km</p>
              <p><strong>Claim Amount:</strong> ₹{{ journey.claimAmount }}</p>
              <p><strong>Type:</strong> {{ journey.type === 'road' ? 'By Road' : 'By Water' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add this before the Action Buttons section -->
    <Invoice ref="pdfGenerator" :claimData="pdfClaimData" @pdf-generated="handlePdfGenerated" class="mt-4" />
    <!-- Action Buttons -->
    <div class="-mx-2.5 flex flex-wrap gap-y-5 justify-end mt-[-121px]">
      <div class="px-2.5">
        <button @click="handleSubmitForm" :disabled="isSubmitting || !isFormValid"
          class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 flex w-full items-center justify-end gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition-colors">
          <span v-if="isSubmitting">Submitting...</span>
          <span v-else>Submit</span>
        </button>
      </div>
      <!-- <div class="px-2.5">
        <button
          @click="proceedToESign"
          class="bg-blue-900 hover:bg-blue-800 flex w-full items-center justify-end gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition-colors"
        >
          Proceed To e-Sign
        </button>
      </div> -->
      <div class="px-2.5">
        <button @click="handleReset"
          class="bg-gray-500 hover:bg-gray-600 flex w-full items-center justify-end gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition-colors">
          Reset
        </button>
      </div>
    </div>

    <!-- Links -->
    <div class="flex  px-2.5 mt-6">
      <div class="flex flex-col justify-between gap-5 xl:flex-row xl:items-center">
        <!-- <a href="javascript:void(0)" class="text-blue-700 hover:underline">+ Add More Traveller</a> -->
        <button type="button" @click="showJourneyTypePopup = true"
          class="flex w-full items-right justify-end gap-2 px-4 py-3 font-medium xl:w-auto text-blue-700 underline hover:no-underline">
          + Add Other Journey Type
        </button>
      </div>
    </div>

    <!-- Journey Type Selection Popup -->
    <div v-if="showJourneyTypePopup" class="fixed inset-0 z-50 flex items-center justify-center overlay">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 relative">
        <button @click="showJourneyTypePopup = false"
          class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 w-6 h-6 flex items-center justify-center">
          ✕
        </button>

        <h2 class="w-full text-xl font-semibold text-gray-800">
          Add Other Journey Type
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-5 text-center">
          <div @click="showRoadForm = true; showJourneyTypePopup = false"
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] cursor-pointer hover:border-blue-900 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors">
            <h4 class="text-xl font-semibold text-gray-800">By Road</h4>
            <img src="/assets/images/all-img/car.png" width="60" alt="By Road" class="mt-5 mx-auto" />
          </div>
          <div @click="showWaterForm = true; showJourneyTypePopup = false"
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] cursor-pointer hover:border-blue-900 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors">
            <h4 class="text-xl font-semibold text-gray-800">By Water</h4>
            <img src="/assets/images/all-img/cruise.png" width="60" alt="By Water" class="mt-5 mx-auto" />
          </div>
        </div>
      </div>
    </div>

    <!-- Road Journey Form Popup -->
    <div v-if="showRoadForm" class="fixed inset-0 z-50 flex items-center justify-center overlay">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 space-y-4 relative max-h-[90vh] overflow-y-auto">
        <button @click="showRoadForm = false"
          class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 w-6 h-6 flex items-center justify-center">
          ✕
        </button>

        <h2 class="w-full text-xl font-semibold text-gray-800">
          Add Road Journey Details
        </h2>

        <div class="space-y-4">
                  <!-- For Select Visit Purpose -->
 <div>
        <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300">
          Select Purpose of Visit
        </label>
        <Multiselect
          v-model="selectedVisitPurposes"
          :options="purposeOfVisitOptions"
          :multiple="true"
          :searchable="true"
          placeholder="Select Purpose of Visit"
          track-by="id"
          label="name"
          @blur="validateDailyAllowanceField('visitPurpose')"
          @select="handlePurposeSelect"
          @remove="handlePurposeRemove"
        />

      </div>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <TextInput type="date" label="Date of Journey" name="dateOfJourney" v-model="roadForm.dateOfJourney"
              v-model:error="roadFormErrors.dateOfJourney" :isRequired="true" :disableBuiltinValidation="true"
              placeholder="Select date" @blur="handleRoadFieldBlur('dateOfJourney')" />

            <TextInput type="text" label="From Location" name="fromLocation" v-model="roadForm.fromLocation"
              v-model:error="roadFormErrors.fromLocation" :isRequired="true" :disableBuiltinValidation="true"
              placeholder="Enter starting location" @blur="handleRoadFieldBlur('fromLocation')" />

            <TextInput type="text" label="To Location" name="toLocation" v-model="roadForm.toLocation"
              v-model:error="roadFormErrors.toLocation" :isRequired="true" :disableBuiltinValidation="true"
              placeholder="Enter destination" @blur="handleRoadFieldBlur('toLocation')" />
          </div>

          <!-- Distance -->
          <TextInput type="number" label="Distance (Km)" name="distance" v-model="roadForm.distance"
            v-model:error="roadFormErrors.distance" :isRequired="true" :disableBuiltinValidation="true"
            placeholder="Enter distance" @blur="handleRoadFieldBlur('distance')" />

          <!-- Claim Amount -->
          <TextInput type="number" label="Claim Amount (₹)" name="claimAmount" v-model="roadForm.claimAmount"
            v-model:error="roadFormErrors.claimAmount" :isRequired="true" :disableBuiltinValidation="true"
            placeholder="Enter claim amount" @blur="handleRoadFieldBlur('claimAmount')" />
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <button @click="showRoadForm = false" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
            Cancel
          </button>
          <button @click="addRoadJourney" :disabled="!isRoadFormValid"
            class="bg-blue-900 hover:bg-blue-800 disabled:bg-gray-400 px-4 py-2 text-white font-medium rounded-lg transition-colors">
            Add Journey
          </button>
        </div>
      </div>
    </div>

    <!-- Water Journey Form Popup -->
    <div v-if="showWaterForm" class="fixed inset-0 z-50 flex items-center justify-center overlay">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 space-y-4 relative max-h-[90vh] overflow-y-auto">
        <button @click="showWaterForm = false"
          class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 w-6 h-6 flex items-center justify-center">
          ✕
        </button>

        <h2 class="w-full text-xl font-semibold text-gray-800">
          Add Water Journey Details
        </h2>

        <div class="space-y-4">
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <TextInput type="date" label="Date of Journey" name="dateOfJourney" v-model="waterForm.dateOfJourney"
              v-model:error="waterFormErrors.dateOfJourney" :isRequired="true" :disableBuiltinValidation="true"
              placeholder="Select date" @blur="handleWaterFieldBlur('dateOfJourney')"
              @input="handleWaterFieldInput('dateOfJourney')" />

            <TextInput type="text" label="From Location" name="fromLocation" v-model="waterForm.fromLocation"
              v-model:error="waterFormErrors.fromLocation" :isRequired="true" :disableBuiltinValidation="true"
              placeholder="Enter starting location" @blur="handleWaterFieldBlur('fromLocation')"
              @input="handleWaterFieldInput('fromLocation')" />

            <TextInput type="text" label="To Location" name="toLocation" v-model="waterForm.toLocation"
              v-model:error="waterFormErrors.toLocation" :isRequired="true" :disableBuiltinValidation="true"
              placeholder="Enter destination" @blur="handleWaterFieldBlur('toLocation')"
              @input="handleWaterFieldInput('toLocation')" />
          </div>

          <!-- Distance -->
          <TextInput type="number" label="Distance (Km)" name="distance" v-model="waterForm.distance"
            v-model:error="waterFormErrors.distance" :isRequired="true" :disableBuiltinValidation="true"
            placeholder="Enter distance" @blur="handleWaterFieldBlur('distance')"
            @input="handleWaterFieldInput('distance')" />

          <!-- Claim Amount -->
          <TextInput type="number" label="Claim Amount (₹)" name="claimAmount" v-model="waterForm.claimAmount"
            v-model:error="waterFormErrors.claimAmount" :isRequired="true" :disableBuiltinValidation="true"
            placeholder="Enter claim amount" @blur="handleWaterFieldBlur('claimAmount')"
            @input="handleWaterFieldInput('claimAmount')" />
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <button @click="showWaterForm = false" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
            Cancel
          </button>
          <button @click="addWaterJourney" :disabled="!isWaterFormValid"
            class="bg-blue-900 hover:bg-blue-800 disabled:bg-gray-400 px-4 py-2 text-white font-medium rounded-lg transition-colors">
            Add Journey
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { TextInput, FileUploads } from '@sds/oneui-common-ui';
import { addNewClaim, getDaAllowance } from '@/services/rss/SubmitTadaClaims';
import { useValidation, required, minLength, maxLength, pattern } from '@sds/oneui-validation';
import swalWithBootstrapButtons from '@/utils/swal';
import Invoice from './invoice.vue'; // Adjust path as needed
import { getTadaPurposeVisit } from '@/services/rss/TadaServices';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
const pdfGenerator = ref(null);
const isPdfGenerated = ref(false);
const generatedPdfPath = ref('');
import { isSwal } from '@/utils/isSwal';
import { postMethod } from "@/composables/useApi";
const router = useRouter();
const claimId = ref('');
import { cookieService } from '@sds/oneui-layout';
import {  
  getClaimTrackId
} from "@/services/rss/SubmitItEquipementClaim";
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
const userId = rbacAppData?.id;
// console.log("Formatted User ID:", formattedUserId);
// Props
const props = defineProps({
  ticketData: {
    type: Object,
    required: true
  },
   coreUserId: [String, Number]
});

// Emits
const emit = defineEmits(['reset']);

// Component state
const showJourneyTypePopup = ref(false);
const pdfUploadRef = ref(null);
const showRoadForm = ref(false);
const uploadedFiles = ref([]);
const showWaterForm = ref(false);
const isSubmitting = ref(false);
const additionalJourneys = ref([]);

// for select visit purpose
const purposeOfVisitOptions = ref([]);
const selectedVisitPurposes = ref([]);

const MUST_HAVE_PURPOSES = ['Committee Meeting', 'Session'];

const hasRequiredPurposes = computed(() => {
  const labels = selectedVisitPurposes.value.map(p => p.name);
  return MUST_HAVE_PURPOSES.every(req => labels.includes(req));
}); // 

const totalAmountsAir = computed(() =>
  getAdmissibleTotal()
); // you already have this in file, keep as-is 

const totalAmountsRoad = computed(() =>
  additionalJourneys.value
    .filter(journey => journey.type === 'road')
    .reduce((total, journey) => total + (parseFloat(journey.claimAmount) || 0), 0)
); 

const totalAmountsWater = computed(() =>
  additionalJourneys.value
    .filter(journey => journey.type === 'water')
    .reduce((total, journey) => total + (parseFloat(journey.claimAmount) || 0), 0)
);  

const tickets = ref([{
  boardingPass: [],
  ticketBill: []
}]);
// Road journey form
const roadForm = reactive({
  selectedVisitPurposes: '',
  dateOfJourney: '',
  fromLocation: '',
  toLocation: '',
  distance: '',
  claimAmount: ''
});
// Water journey form
const waterForm = reactive({
  selectedVisitPurposes: '',
  dateOfJourney: '',
  fromLocation: '',
  toLocation: '',
  distance: '',
  claimAmount: ''
});

// Validation schemas
const roadValidationSchema = {
  dateOfJourney: [required()],
  fromLocation: [required(), minLength(2), maxLength(100)],
  toLocation: [required(), minLength(2), maxLength(100)],
  distance: [required(), pattern(/^[0-9]+$/)],
  claimAmount: [required(), pattern(/^[0-9]+$/)]
};

const waterValidationSchema = {
  dateOfJourney: [required()],
  fromLocation: [required(), minLength(2), maxLength(100)],
  toLocation: [required(), minLength(2), maxLength(100)],
  distance: [required(), pattern(/^[0-9]+$/)],
  claimAmount: [required(), pattern(/^[0-9]+$/)]
};

// Validation instances
const {
  errors: roadFormErrors,
  isValid: isRoadFormValid,
  validateField: validateRoadField,
  validateAll: validateRoadAll,
  clearFieldError: clearRoadFieldError
} = useValidation(roadForm, roadValidationSchema);

const {
  errors: waterFormErrors,
  isValid: isWaterFormValid,
  validateField: validateWaterField,
  validateAll: validateWaterAll,
  clearFieldError: clearWaterFieldError
} = useValidation(waterForm, waterValidationSchema);

// Helper methods
const formatTime = (timeString) => {
  if (!timeString) return 'N/A';
  return timeString.substring(0, 5);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatAmount = (amount) => {
  return Math.abs(amount).toLocaleString('en-IN');
};

// Computed properties
const shouldShowDailyAllowance = computed(() => {
  const hasOtherBusiness = selectedVisitPurposes.value.some(purpose => 
    purpose.name === "Other Business connected with duties"
  );
  return !hasOtherBusiness;
});

// API methods

const fetchDaAllowance = async () => {
  const roadJourneys = additionalJourneys.value.filter(j => j.type === 'road');

  // must have both purposes and at least one road journey
  if (!hasRequiredPurposes.value || roadJourneys.length === 0) {
    return null;
  }

  // Build payload exactly like Postman
  const purpose_of_visit = selectedVisitPurposes.value.map(p => p.name); // ["Committee Meeting","Session"]

  const ta_data = roadJourneys.map(j => ({
    date_of_journey: j.dateOfJourney,     // "16-12-2025"
    from_location: j.fromLocation,
    to_location: j.toLocation,
    distance: Number(j.distance),
    amount: Number(j.claimAmount)
  }));

  const body = { purpose_of_visit, ta_data };

  const response = await getDaAllowance(body);

  if (response?.success_code === 200 && response.data) {
    // response.data = { total_da_days, total_da_amount, attendance_dates, eligible_da_dates, travel_valid }
    return response.data;
  }

  return null;
};

const fetchPurposeOfVisitOptions = async () => {
  try {
    const response = await getTadaPurposeVisit();
    if (response.success_code === 200) {
      purposeOfVisitOptions.value = response.data.values.map(item => ({
        id: item.id,
        name: item.key_value
      }));
    }
  } catch (error) {
    console.error('Error fetching purpose of visit options:', error);
  }
};

const getAirlineName = () => {
  const airlineCodes = {
    'AI': 'Air India',
    '6E': 'IndiGo',
    'SG': 'SpiceJet',
    'UK': 'Vistara',
    'G8': 'Go Air'
  };

  const flightNumber = props.ticketData.flight_number;
  const airlineCode = flightNumber.replace(/[0-9]/g, '');
  return airlineCodes[airlineCode] || 'Airline';
};

const getPassengerFullName = () => {
  if (!props.ticketData.passengers || props.ticketData.passengers.length === 0) return 'N/A';
  const passenger = props.ticketData.passengers[0];
  return `${passenger.firstName} ${passenger.lastName}`;
};

const getAdmissibleTotal = () => {
  const payment = props.ticketData.payment_details;
  let total = payment.baseFare + payment.acceptableTaxAmount;

  if (payment.refundedBaseFare > 0) {
    total -= payment.refundedBaseFare;
  }

  return Math.abs(total);
};

const handleFiles = async (files, ticketIndex, fileType) => {
  // console.log('handleFiles called:', { files, ticketIndex, fileType });
  
  if (files && files.length > 0) {
    const validFiles = files.filter(file => file.errors.length === 0);
    // console.log('Valid files:', validFiles);
    
    if (validFiles.length > 0) {
      // Ensure the ticket exists at this index
      if (!tickets.value[ticketIndex]) {
        tickets.value[ticketIndex] = {
          boardingPass: [],
          ticketBill: []
        };
      }
      
      if (fileType === 'boardingPass') {
        tickets.value[ticketIndex].boardingPass = validFiles;
        // console.log('Updated boardingPass:', tickets.value[ticketIndex].boardingPass);
      } else if (fileType === 'ticketBill') {
        tickets.value[ticketIndex].ticketBill = validFiles;
        // console.log('Updated ticketBill:', tickets.value[ticketIndex].ticketBill);
      }
    }
  } else {
    console.log("No files or error during uploading");
  }
};


const handleRoadFieldBlur = (fieldName) => {
  validateRoadField(fieldName);
  if (roadForm[fieldName] && roadForm[fieldName].trim() && !roadFormErrors[fieldName]) {
    clearRoadFieldError(fieldName);
  }
};

// Field handlers for water form
const handleWaterFieldInput = (fieldName) => {
  if (waterForm[fieldName] && waterForm[fieldName].trim()) {
    clearWaterFieldError(fieldName);
  }
  validateWaterField(fieldName);
};

const handleWaterFieldBlur = (fieldName) => {
  validateWaterField(fieldName);
  if (waterForm[fieldName] && waterForm[fieldName].trim() && !waterFormErrors[fieldName]) {
    clearWaterFieldError(fieldName);
  }
};

// Add journey functions
const addRoadJourney = async () => {
  const isFormValid = await validateRoadAll();

  if (!isFormValid) {
    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please fix all validation errors before adding the journey.',
    });
    return;
  }

  additionalJourneys.value.push({
    ...roadForm,
    type: 'road'
  });

  // Reset form
  Object.keys(roadForm).forEach(key => {
    roadForm[key] = '';
  });
  Object.keys(roadFormErrors).forEach(key => {
    clearRoadFieldError(key);
  });

  showRoadForm.value = false;

  await swalWithBootstrapButtons.fire({
    icon: 'success',
    title: 'Success',
    text: 'Road journey added successfully!',
  });
};

const addWaterJourney = async () => {
  const isFormValid = await validateWaterAll();

  if (!isFormValid) {
    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please fix all validation errors before adding the journey.',
    });
    return;
  }

  additionalJourneys.value.push({
    ...waterForm,
    type: 'water'
  });

  // Reset form
  Object.keys(waterForm).forEach(key => {
    waterForm[key] = '';
  });
  Object.keys(waterFormErrors).forEach(key => {
    clearWaterFieldError(key);
  });

  showWaterForm.value = false;

  await swalWithBootstrapButtons.fire({
    icon: 'success',
    title: 'Success',
    text: 'Water journey added successfully!',
  });
};

// Remove journey
const removeJourney = (index) => {
  additionalJourneys.value.splice(index, 1);
};


const totalAmount = computed(() => {
  return totalAmountsAir.value + totalAmountsRoad.value + totalAmountsWater.value;
});

const isFormValid = computed(() => {
  return additionalJourneys.value.length > 0 || props.ticketData;
});


// Add this computed property
const pdfClaimData = computed(() => {
  const flightItems = props.ticketData ? [{
    sno: 1,
    itemName: `Flight ${props.ticketData.flight_number}`,
    description: [
      `Passenger Name: ${getPassengerFullName()}`,
      `Airline Name: ${getAirlineName()}`,
      `Flight Number: ${props.ticketData.flight_number}`,
      `Departure: ${props.ticketData.from}`,
      `Arrival: ${props.ticketData.to}`,
      `PNR Number: ${props.ticketData.pnr}`,
      `Ticket Number: ${props.ticketData.passengers?.[0]?.ticket_no || 'N/A'}`,
      `Date Time: ${props.ticketData.depart_date} ${formatTime(props.ticketData.depart_time)}`,
      `Fare: ₹${getAdmissibleTotal()}`
    ],
    qty: 1,
    unitPrice: getAdmissibleTotal(),
    total: getAdmissibleTotal()
  }] : [];

  const roadItems = additionalJourneys.value
    .filter(journey => journey.type === 'road')
    .map((journey, index) => ({
      sno: flightItems.length + index + 1,
      itemName: 'Road Journey',
      description: [
        `Purpose of Visit: ${journey.selectedVisitPurposes}`,
        `From Location: ${journey.fromLocation}`,
        `To Location: ${journey.toLocation}`,
        `Date Time: ${journey.dateOfJourney}`,
        `Distance: ${journey.distance} Km`,
        `Fare: ₹${journey.claimAmount}`
      ],
      qty: 1,
      unitPrice: parseFloat(journey.claimAmount),
      total: parseFloat(journey.claimAmount)
    }));

  const waterItems = additionalJourneys.value
    .filter(journey => journey.type === 'water')
    .map((journey, index) => ({
      sno: flightItems.length + roadItems.length + index + 1,
      itemName: 'Water Journey',
      description: [
        `Purpose of Visit: ${journey.selectedVisitPurposes}`,
        `From Location: ${journey.fromLocation}`,
        `To Location: ${journey.toLocation}`,
        `Date Time: ${journey.dateOfJourney}`,
        `Distance: ${journey.distance} Km`,
        `Fare: ₹${journey.claimAmount}`
      ],
      qty: 1,
      unitPrice: parseFloat(journey.claimAmount),
      total: parseFloat(journey.claimAmount)
    }));

  const allItems = [...flightItems, ...roadItems, ...waterItems];

  return {
    claimId: `TADA${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}0001`,
    originalForReceipt: 'Original for receipt',
    organization: 'Rajya Sabha',
    address: 'Address line, Street Address, City Name, State, Country — Pin Code',
    email: 'username@email.com',
    phone: '+91 0000000000',
    processedBy: 'P.A Name',
    processedOn: new Date().toLocaleDateString('en-GB').replace(/\//g, ''),
    approvedBy: 'Member Name',
    systemIP: '10.110.100.21',
    deviceName: 'Acer laptop',
    items: allItems,
    totalAmount: totalAmount.value,
    claimReceivedBy: getPassengerFullName() || ''
  };
});

// Update handlePdfGenerated
const handlePdfGenerated = async (blob) => {
  try {
    const fileName = `Claim_Receipt_${pdfClaimData.value.claimId}.pdf`;
    const file = new File([blob], fileName, { type: 'application/pdf' });
    // Set the flag immediately when PDF is generated
    isPdfGenerated.value = true;
    pdfUploadRef.value.customUpload([file]);


  } catch (error) {
    console.error('Error uploading generated PDF to DMS:', error);
  }
};

const handlePdfUpload = (files) => {
  if (files && files.length > 0 && files[0].errors.length === 0) {
    generatedPdfPath.value = files[0].path;
    isPdfGenerated.value = true;
  }
};


setTimeout(() => {
  if (pdfGenerator.value) {
    pdfGenerator.value.generatePDFBlob();
  }
}, 300);

const formDataToPlainObject = (formData) => {
  const obj = {};

  for (const [key, value] of formData.entries()) {
    if (key.endsWith('[]')) {
      const cleanKey = key.replace('[]', '');
      if (!obj[cleanKey]) obj[cleanKey] = [];
      if (value !== '' && value !== null) {
        obj[cleanKey].push(value);
      }
    } else {
      obj[key] = value;
    }
  }

  return obj;
};

const preparePayload = (daData = null) => {
  const payload = new FormData();

  /* BASIC FIELDS */
  payload.append('claim_type', 'TADA');
  payload.append('traveller_type', '24132');
  payload.append('claim_code', claimId.value || '');
  payload.append('core_user_id', props.coreUserId || ''); 

  // Purpose of visit ids, joined by comma
  const purposeIds = selectedVisitPurposes.value.map(p => p.id).join(',');
  payload.append('purpose_of_visit[]', purposeIds); 

  // DA fields (only from API if available)
  const daAmount = daData ? Number(daData.da_amount || 0) : 0;
  payload.append('da_from_date', props.ticketData?.depart_date || '');
  payload.append('da_to_date',   props.ticketData?.arrival_date || '');
  // payload.append('da_from_date', daData?.da_from_date || '');
  // payload.append('da_to_date', daData?.da_to_date || '');
  payload.append('da_days', daData?.da_days != null ? String(daData.da_days) : '0');
  payload.append('da_amount', String(daAmount));

  // Total = air + road + water + da
  const total =
    Number(totalAmountsAir.value || 0) +
    Number(totalAmountsRoad.value || 0) +
    Number(totalAmountsWater.value || 0) +
    daAmount; 

  payload.append('total_amount', String(total));

  /* PNR DATA */
  if (props.ticketData) {
    const passenger = props.ticketData.passengers?.[0];

    payload.append('pnr_data', '1');
    payload.append('pnr_data_airline_name[]', getAirlineName());
    payload.append('pnr_data_departure[]', props.ticketData.from);
    payload.append('pnr_data_arrival[]', props.ticketData.to);
    payload.append('pnr_data_departure_date[]', props.ticketData.depart_date);
    payload.append('pnr_data_arrival_date[]', props.ticketData.arrival_date);
    payload.append('pnr_data_passenger_name[]', getPassengerFullName());
    payload.append('pnr_data_ticket_no[]', passenger?.ticket_no || '');
    payload.append('pnr_data_pnr_no[]', props.ticketData.pnr);

    payload.append(
      'pnr_data_pnr_status[]',
      props.ticketData.complete_journey_certification === 'Yes'
        ? 'Confirmed'
        : 'Pending'
    );

    payload.append(
      'pnr_data_base_fare[]',
      props.ticketData.payment_details.baseFare
    );
    payload.append(
      'pnr_data_taxes_fee[]',
      props.ticketData.payment_details.acceptableTaxAmount
    );

    payload.append('pnr_data_baggage_fee[]', '0');
    payload.append('pnr_data_seat_selection_fee[]', '0');
    payload.append(
      'pnr_data_service_fee[]',
      props.ticketData.service_result?.reduce((s, i) => s + i.baseFare, 0) || 0
    );
    payload.append('pnr_data_in_flight_fee[]', '0');
    payload.append('pnr_data_misc_fee[]', '0');
    payload.append(
      'pnr_data_admissible_amount[]',
      getAdmissibleTotal()
    ); 
  } else {
    payload.append('pnr_data', '0');
  }

  /* ROAD DATA */
  const roadJourneys = additionalJourneys.value.filter(j => j.type === 'road'); 

  if (roadJourneys.length > 0) {
    payload.append('road_data', '1');

    roadJourneys.forEach(j => {
      payload.append('road_data_date_of_journey[]', j.dateOfJourney);
      payload.append('road_data_from_location[]', j.fromLocation);
      payload.append('road_data_to_location[]', j.toLocation);
      payload.append('road_data_distance[]', j.distance);
      payload.append('road_data_amount[]', j.claimAmount);
    });
  } else {
    payload.append('road_data', '0');
    payload.append('road_data_date_of_journey[]', '');
    payload.append('road_data_from_location[]', '');
    payload.append('road_data_to_location[]', '');
    payload.append('road_data_distance[]', '');
    payload.append('road_data_amount[]', '');
  }

  /* WATER DATA (always 0 for now) */
  payload.append('water_data', '0');
  payload.append('water_data_date_of_journey[]', '');
  payload.append('water_data_from_location[]', '');
  payload.append('water_data_to_location[]', '');
  payload.append('water_data_distance[]', '');
  payload.append('water_data_amount[]', '');

  /* TOTALS (per mode) */
  payload.append('total_amounts_air', String(totalAmountsAir.value || 0));
  payload.append('total_amounts_road', String(totalAmountsRoad.value || 0));
  payload.append('total_amounts_water', String(totalAmountsWater.value || 0));
  payload.append('total_amounts_da', String(daAmount)); 

  /* FILE PATHS */
  tickets.value.forEach(ticket => {
    ticket.boardingPass?.forEach(p =>
      payload.append('pnr_bording_pass[]', p.path)
    );
    ticket.ticketBill?.forEach(p =>
      payload.append('pnr_supporting_file[]', p.path)
    );
  }); 

  return payload;
};

const handleSubmitForm = async () => {
  try {
    isSubmitting.value = true;

    // 1) Ensure PDF is generated (your existing logic)
    if (!isPdfGenerated.value && pdfGenerator.value) {
      await pdfGenerator.value.generatePDFBlob();
    }

    // 2) Wait for PDF upload path if needed (as in your original)
    if (!generatedPdfPath.value) {
      await new Promise((resolve, reject) => {
        const timeout = setTimeout(
          () => reject(new Error('PDF upload timeout')),
          10000
        );

        const interval = setInterval(() => {
          if (generatedPdfPath.value) {
            clearInterval(interval);
            clearTimeout(timeout);
            resolve();
          }
        }, 100);
      });
    }

    // 3) Call DA allowance only if conditions met
    const daData = await fetchDaAllowance(); // may be null 

    // 4) Build form-data payload using DA data
    const formData = preparePayload(daData);

    // 5) Attach submitted claim file path
    formData.append('submited_claim_file', generatedPdfPath.value || '');

    // Convert to plain object if your `addNewClaim` expects JSON
    const cleanPayload = formDataToPlainObject(formData); 

    const response = await addNewClaim(cleanPayload);

    if (!response.isError && (response.successcode === 200 || response.successcode === 201)) {
      isSwal('TADA Claim submitted successfully', 'success');
      const claimIdResp = response.data?.id;
      const requestId = response.data?.requestId;

      if (claimIdResp && requestId) {
        router.replace({
          name: 'SubmitEsign',
          state: {
            claimId: claimIdResp,
            requestId,
            status: 'success'
          }
        });
      }
    } else {
      throw new Error(response?.message || 'Failed to submit form');
    }
  } catch (error) {
    console.error('Submit error:', error);
    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to submit form. Please try again.'
    });
  } finally {
    isSubmitting.value = false;
  }
};


// Event handlers
const proceedToESign = () => {
  console.log('Proceeding to e-sign with data:', {
    ticketData: props.ticketData,
    additionalJourneys: additionalJourneys.value
  });
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
  fetchPurposeOfVisitOptions();
});

const handleReset = () => {
  emit('reset');
};
</script>

<style scoped>
.overlay {
  background: rgba(51, 51, 51, 0.61);
}

.transition-colors {
  transition: all 0.2s ease-in-out;
}
</style>