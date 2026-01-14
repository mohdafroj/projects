<template>
  <Loading v-if="isLoading" />
  <div class="p-6 min-h-screen">
    <!-- Preview Mode -->
    <div v-show="showPreview">
      <TADAPreview v-if="Object.keys(previewData).length" :claimData="previewData" @edit="showPreview = false"
        @submit="finalSubmit" />
      <Invoice ref="pdfGenerator" :claimData="pdfClaimData" @pdf-generated="handlePdfGenerated" class="mt-4" />
      <FileUploads ref="pdfUploadRef" :onFileUpload="postMethod" :multiple="false" class="hidden"
        @update:files="handlePdfUpload" />
    </div>

    <!-- Form Mode -->
    <div v-show="!showPreview">
      <!-- User Search Section -->
      <div class="mb-6 dark:bg-black-800 dark:text-slate-300" v-if="!selectedMember">
        <div class="relative">
          <input type="text" v-model="searchQuery" @input="searchMembers" placeholder="Search by Name or IC number..."
            class="w-full p-3 border border-gray-300 rounded-lg dark:bg-black-800 dark:text-slate-300 dark:border-gray-600" />
          <div v-if="searchResults.length > 0"
            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto dark:bg-black-800 dark:text-slate-300">
            <div v-for="member in searchResults" :key="member.id" @click="selectMember(member)"
              class="p-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700 border-b border-gray-200 last:border-b-0">
              <div class="font-medium">{{ member.full_name }}</div>
              <div class="text-sm text-gray-500">IC Number: #{{ member.core_user_id }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Selected Member and Finance Details -->
      <div v-show="selectedMember" class="mb-6">
        <Card class="mb-4">
          <div class="flex justify-center p-1">
            <div class="rounded-xl shadow-sm w-full p-0">
              <h2 class="text-xl font-semibold text-gray-800 pb-6">TA/ DA Claim Form</h2>

              <div class="bg-gray-100 rounded-lg flex items-center justify-between mt-2 p-3">
                <!-- Left Section -->
                <div class="flex items-center space-x-3">
                  <!-- Avatar -->
                  <img src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png"
                    alt="User Avatar" class="w-10 h-10 rounded-full object-cover" />
                  <!-- User Info -->
                  <div>
                    <h3 class="text-base font-semibold text-gray-900 leading-tight">
                      {{ selectedMember?.full_name }}
                    </h3>
                    <p class="text-sm text-gray-600 leading-tight">
                      IC Number : <span class="text-gray-700 font-medium">#{{ selectedMember?.core_user_id }}</span>
                    </p>
                  </div>
                </div>

                <!-- Right Section (Check Icon) -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>
          </div>
        </Card>

        <div class="mx-auto">
          <!-- Content Start -->
          <div class="space-y-6">
            <!-- TA/DA Claim Form -->
            <div>
              <div class="space-y-8">
                <!-- Header -->
                <div v-if="errorMessage" class="bg-red-200 text-red-700 p-3 mb-4 rounded-md">
                  {{ errorMessage }}
                </div>

                <Card>
                  <!-- Purpose of Journey -->
                  <div class="border border-gray-200 rounded-xl p-4 mb-4 dark:border-gray-700">
                    <div>
                      <label
                        class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300">
                        Select Purpose of Visit
                      </label>
                      <Multiselect v-model="selectedVisitPurposes" :options="purposeOfVisitOptions" :multiple="true"
                        :searchable="true" placeholder="Select Purpose of Visit" track-by="id" label="name"
                        @select="handlePurposeSelect" @remove="handlePurposeRemove" />
                      <div v-if="errors['dailyAllowance.visitPurpose']" class="text-red-500 text-sm mt-1">
                        {{ errors['dailyAllowance.visitPurpose'] }}
                      </div>
                    </div>
                  </div>
                </Card>

                <Card>
                  <div class="space-y-6">
                    <!-- Repeater -->
                    <div v-for="(ticket, index) in tickets" :key="index"
                      class="border border-gray-200 rounded-xl p-6 space-y-6 dark:border-gray-700">
                      <div class="flex items-center justify-between">
                        <div class="border-b border-gray-200 pb-1 dark:border-gray-800 mb-4 w-full">
                          <h4 class="text-lg font-medium text-gray-800 dark:text-white">
                            Personal Information (Passenger {{ index + 1 }})
                          </h4>
                        </div>

                        <!-- Remove Button (hidden for first ticket) -->
                        <button v-if="index > 0" @click="removeTicket(index)"
                          class="text-red-500 hover:text-red-700 px-3 py-1 rounded-full text-sm border border-red-300 hover:border-red-500">
                          Remove
                        </button>
                      </div>

                      <!-- Passenger Name -->
                      <TextInput v-model="ticket.passengerName" name="passengerName" label="Name of Passenger"
                        placeholder="Enter passenger name" v-model:error="errors[`tickets[${index}].passengerName`]"
                        :isRequired="true" :disableBuiltinValidation="true"
                        @blur="validateTicketField(index, 'passengerName')"
                        @input="clearError(`tickets[${index}].passengerName`)" />

                      <!-- Journey Type & Date -->
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <TextInput v-model="ticket.date" name="journeyDate" type="date" label="Date of Journey"
                          v-model:error="errors[`tickets[${index}].date`]" :isRequired="true"
                          :disableBuiltinValidation="true" @blur="validateTicketField(index, 'date')"
                          @input="clearError(`tickets[${index}].date`)" />
                        <div>
                          <!-- Journey Type Dropdown -->
                          <TextInput v-model="ticket.mode" name="mode" type="select" label="Select Journey Type"
                            :options="[
                              { label: 'Select Journey Type', value: '' },
                              { label: 'By Air Journey', value: 'air' },
                              { label: 'By Road Journey', value: 'road' },
                              { label: 'By River Journey', value: 'river' }
                            ]" v-model:error="errors[`tickets[${index}].mode`]" :isRequired="true"
                            :disableBuiltinValidation="true" @blur="validateTicketField(index, 'mode')"
                            @input="clearError(`tickets[${index}].mode`)"
                            class="w-full px-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                      </div>

                      <!-- Air Journey Details -->
                      <div v-show="ticket.mode === 'air'" class="mt-6">
                        <div class="border-b border-gray-200 pb-1 dark:border-gray-800 mb-4">
                          <h4 class="text-lg font-medium text-gray-800 dark:text-white">
                            Journey as Air
                          </h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <TextInput v-model="ticket.air.from" name="airFrom" label="Place From (Location)"
                            placeholder="Enter source location" v-model:error="errors[`tickets[${index}].air.from`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.from')"
                            @input="clearError(`tickets[${index}].air.from`)" />
                          <TextInput v-model="ticket.air.to" name="airTo" label="Arrival At (Location)"
                            placeholder="Enter destination location" v-model:error="errors[`tickets[${index}].air.to`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.to')"
                            @input="clearError(`tickets[${index}].air.to`)" />
                          <TextInput v-model="ticket.air.airline" name="airline" label="Airline Name"
                            placeholder="Enter airline name" v-model:error="errors[`tickets[${index}].air.airline`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.airline')"
                            @input="clearError(`tickets[${index}].air.airline`)" />
                          <TextInput v-model="ticket.air.ticketNo" name="ticketNo" label="Ticket Number"
                            placeholder="Enter ticket number" v-model:error="errors[`tickets[${index}].air.ticketNo`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.ticketNo')"
                            @input="clearError(`tickets[${index}].air.ticketNo`)" />
                          <TextInput v-model="ticket.air.pnr" name="pnr" label="PNR Number"
                            placeholder="Enter PNR number" v-model:error="errors[`tickets[${index}].air.pnr`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.pnr')"
                            @input="clearError(`tickets[${index}].air.pnr`)" />
                          <TextInput v-model="ticket.air.baseFare" name="baseFare" type="number" label="Base Fare"
                            placeholder="Enter base fare" v-model:error="errors[`tickets[${index}].air.baseFare`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.baseFare')"
                            @input="clearError(`tickets[${index}].air.baseFare`)" />
                          <TextInput v-model="ticket.air.taxes" name="taxes" type="number" label="Taxes & Fees"
                            placeholder="Enter taxes and fees" v-model:error="errors[`tickets[${index}].air.taxes`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.taxes')"
                            @input="clearError(`tickets[${index}].air.taxes`)" />
                          <TextInput v-model="ticket.air.serviceFee" name="serviceFee" type="number" label="Service Fee"
                            placeholder="Enter service fee" v-model:error="errors[`tickets[${index}].air.serviceFee`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.serviceFee')"
                            @input="clearError(`tickets[${index}].air.serviceFee`)" />
                          <!-- Add these four fields after service fee -->
                          <TextInput v-model="ticket.air.baggageFee" name="baggageFee" type="number" label="Baggage Fee"
                            placeholder="Enter baggage fee" v-model:error="errors[`tickets[${index}].air.baggageFee`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'air.baggageFee')"
                            @input="clearError(`tickets[${index}].air.baggageFee`)" />

                          <TextInput v-model="ticket.air.seatSelectionFee" name="seatSelectionFee" type="number"
                            label="Seat Selection Fee" placeholder="Enter seat selection fee"
                            v-model:error="errors[`tickets[${index}].air.seatSelectionFee`]" :isRequired="true"
                            :disableBuiltinValidation="true" @blur="validateTicketField(index, 'air.seatSelectionFee')"
                            @input="clearError(`tickets[${index}].air.seatSelectionFee`)" />

                          <TextInput v-model="ticket.air.inFlightFee" name="inFlightFee" type="number"
                            label="In-Flight Fee" placeholder="Enter in-flight fee"
                            v-model:error="errors[`tickets[${index}].air.inFlightFee`]" :isRequired="true"
                            :disableBuiltinValidation="true" @blur="validateTicketField(index, 'air.inFlightFee')"
                            @input="clearError(`tickets[${index}].air.inFlightFee`)" />

                          <TextInput v-model="ticket.air.miscFee" name="miscFee" type="number" label="Miscellaneous Fee"
                            placeholder="Enter miscellaneous fee"
                            v-model:error="errors[`tickets[${index}].air.miscFee`]" :isRequired="true"
                            :disableBuiltinValidation="true" @blur="validateTicketField(index, 'air.miscFee')"
                            @input="clearError(`tickets[${index}].air.miscFee`)" />


                          <!-- File Uploads for Air Journey -->
                          <div class="md:col-span-2">
                            <Card>
                              <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold text-gray-800">Upload Ticket Bill</h2>
                              </div>
                              <FileUploads :isRequired="true" ref="uploadRef1" :onFileUpload="postMethod"
                                :multiple="true" @update:files="(files) => handleFiles(files, index, 'ticketBill')" />



                            </Card>
                          </div>


                          <div class="md:col-span-2">
                            <Card>
                              <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold text-gray-800">Upload Boarding Pass</h2>
                              </div>
                              <FileUploads :isRequired="true" ref="uploadRef" :onFileUpload="postMethod"
                                :multiple="true" @update:files="(files) => handleFiles(files, index, 'boardingPass')" />



                            </Card>
                          </div>
                        </div>
                      </div>

                      <!-- Road Journey Details -->
                      <div v-if="ticket.mode === 'road'" class="mt-6">
                        <div class="border-b border-gray-200 pb-1 dark:border-gray-800 mb-4">
                          <h4 class="text-lg font-medium text-gray-800 dark:text-white">
                            Journey by Road
                          </h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                          <TextInput v-model="ticket.road.from" name="roadFrom" label="From Location (Source)"
                            placeholder="Enter source location" v-model:error="errors[`tickets[${index}].road.from`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'road.from')"
                            @input="clearError(`tickets[${index}].road.from`)" />
                          <TextInput v-model="ticket.road.to" name="roadTo" label="To Location (Destination)"
                            placeholder="Enter destination location" v-model:error="errors[`tickets[${index}].road.to`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'road.to')"
                            @input="clearError(`tickets[${index}].road.to`)" />
                          <TextInput v-model="ticket.road.distance" name="distance" type="number" label="Distance (km)"
                            placeholder="Enter distance" v-model:error="errors[`tickets[${index}].road.distance`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'road.distance')"
                            @input="clearError(`tickets[${index}].road.distance`)" />
                          <TextInput v-model="ticket.road.amount" name="roadAmount" type="number" label="Claim Amount"
                            placeholder="Enter claim amount" v-model:error="errors[`tickets[${index}].road.amount`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'road.amount')"
                            @input="clearError(`tickets[${index}].road.amount`)" />
                        </div>
                      </div>

                      <!-- River Journey Details -->
                      <div v-if="ticket.mode === 'river'" class="mt-1">
                        <div class="border-b border-gray-200 pb-1 dark:border-gray-800 mb-4">
                          <h4 class="text-lg font-medium text-gray-800 dark:text-white">
                            Journey by River
                          </h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <TextInput v-model="ticket.river.from" name="riverFrom" label="From Location (Source)"
                            placeholder="Enter source location" v-model:error="errors[`tickets[${index}].river.from`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'river.from')"
                            @input="clearError(`tickets[${index}].river.from`)" />
                          <TextInput v-model="ticket.river.to" name="riverTo" label="To Location (Destination)"
                            placeholder="Enter destination location"
                            v-model:error="errors[`tickets[${index}].river.to`]" :isRequired="true"
                            :disableBuiltinValidation="true" @blur="validateTicketField(index, 'river.to')"
                            @input="clearError(`tickets[${index}].river.to`)" />
                          <TextInput v-model="ticket.river.distance" name="riverDistance" type="number"
                            label="Distance (km)" placeholder="Enter distance"
                            v-model:error="errors[`tickets[${index}].river.distance`]" :isRequired="true"
                            :disableBuiltinValidation="true" @blur="validateTicketField(index, 'river.distance')"
                            @input="clearError(`tickets[${index}].river.distance`)" />
                          <TextInput v-model="ticket.river.amount" name="riverAmount" type="number" label="Claim Amount"
                            placeholder="Enter claim amount" v-model:error="errors[`tickets[${index}].river.amount`]"
                            :isRequired="true" :disableBuiltinValidation="true"
                            @blur="validateTicketField(index, 'river.amount')"
                            @input="clearError(`tickets[${index}].river.amount`)" />
                        </div>
                      </div>
                    </div>

                    <!-- Add More -->
                    <div class="flex justify-end">
                      <button @click="addTicket"
                        class="px-4 py-2 border border-gray-300 text-sm rounded-full hover:bg-gray-100 dark:hover:text-gray-700 dark:text-slate-300">
                        + Add More Ticket
                      </button>
                    </div>

                    <!-- Daily Allowance Section -->
                    <div class="border border-gray-200 rounded-xl p-6 space-y-4 relative">
                      <div class="border-b border-gray-200 pb-1 dark:border-gray-800 mb-4">
                        <h4 class="text-lg font-medium text-gray-800 dark:text-white">
                          Daily Allowance Claim
                        </h4>
                      </div>
                      <!-- Passenger 1 -->
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label
                            class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300">
                            Select Traveller
                          </label>
                          <select v-model="dailyAllowance.traveller" name="dailyAllowance.traveller"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            @blur="validateDailyAllowanceField('traveller')"
                            @change="clearError('dailyAllowance.traveller')">
                            <option value="">Select Traveller</option>
                            <option value="Member">Member</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Companion">Companion</option>
                            <option value="Member & Spouse">Member & Spouse</option>
                            <option value="Member & Companion">Member & Companion</option>
                          </select>
                          <div v-if="errors['dailyAllowance.traveller']" class="text-red-500 text-sm mt-1">
                            {{ errors['dailyAllowance.traveller'] }}
                          </div>
                        </div>

                        <!-- <div>
                          <label
                            class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300">
                            Select Purpose of Visit
                          </label>
                          <select v-model="dailyAllowance.visitPurpose" name="visitPurpose"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            @blur="validateDailyAllowanceField('visitPurpose')"
                            @change="clearError('dailyAllowance.visitPurpose')">
                            <option value="">Select Purpose of Visit</option>
                            <option value="Session">Session</option>
                            <option value="Committee Meeting">Committee Meeting</option>
                            <option value="Other Business connected with duties">Other Business connected with duties
                            </option>
                            <option value="Oath">Oath</option>
                          </select>
                          <div v-if="errors['dailyAllowance.visitPurpose']" class="text-red-500 text-sm mt-1">
                            {{ errors['dailyAllowance.visitPurpose'] }}
                          </div>
                        </div> -->

                        <TextInput v-model="dailyAllowance.fromDate" type="date" name="fromDate" label="From Date"
                          v-model:error="errors['dailyAllowance.fromDate']" :isRequired="true"
                          :disableBuiltinValidation="true" @blur="validateDailyAllowanceField('fromDate')"
                          @input="clearError('dailyAllowance.fromDate')" />
                        <TextInput v-model="dailyAllowance.toDate" type="date" name="toDate" label="To Date"
                          v-model:error="errors['dailyAllowance.toDate']" :isRequired="true"
                          :disableBuiltinValidation="true" @blur="validateDailyAllowanceField('toDate')"
                          @input="clearError('dailyAllowance.toDate')" />
                      </div>
                    </div>
                  </div>
                </Card>

                <!-- Buttons -->
                <div class="flex justify-center items-center">
                  <div class="flex gap-3">
                    <Button @click="handlePreview" label="Preview">
                      <span v-if="isSubmitting">Processing...</span>
                      <span v-else>Preview & Submit</span>
                    </Button>
                    <Button label="Reset" @click="resetForm" class="btn bg-info-800">
                      Reset
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- File Preview Modal -->
    <div v-if="showPreviewModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-screen overflow-auto">
        <div class="flex justify-between items-center p-4 border-b">
          <h3 class="text-lg font-medium">File Preview</h3>
          <button @click="showPreviewModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="p-4">
          <iframe v-if="previewFileUrl" :src="previewFileUrl" class="w-full h-96" frameborder="0"></iframe>
          <div v-else class="flex items-center justify-center h-96">
            <p class="text-gray-500">Preview not available for this file type</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive, onUnmounted, watch } from "vue";
import { Loading, Card, Modal, TextInput, Button, FileUploads } from "@sds/oneui-common-ui";
import { useValidation, required, minLength, maxLength, pattern } from '@sds/oneui-validation';
import TADAPreview from './TADAPreview.vue';
import Swal from 'sweetalert2';
import { cookieService } from '@sds/oneui-layout';
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
console.log('RBAC App Data:', rbacAppData);
import {
  getMembers,
  getAccountDetails,
  addNewClaim,
  getClaimTrackId
} from "@/services/rss/SubmitItEquipementClaim";
import { getTadaPurposeVisit } from '@/services/rss/TadaServices';
import { useRouter } from 'vue-router';
import Invoice from './invoice.vue';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import swalWithBootstrapButtons from '@/utils/swal';
import { isSwal } from "@/utils/isSwal";
import { postMethod } from "@/composables/useApi";

// Refs
const searchQuery = ref('');
const searchResults = ref([]);
const selectedMember = ref(null);
const accountDetails = ref({});
const uploadedFiles = ref([]);
const uploadRef = ref(null);
const uploadRef1 = ref(null);
const pdfUploadRef = ref(null);
const declarationAccepted = ref(false);
const showPreviewModal = ref(false);
const previewFileUrl = ref('');
const showPreview = ref(false);
const previewData = ref({});
const claimId = ref('');
// Purpose of Visit
const purposeOfVisitOptions = ref([]);
const selectedVisitPurposes = ref([]);

// Component state
const isLoading = ref(false);
const errorMessage = ref("");
const isSubmitting = ref(false);
const pdfGenerator = ref(null);
const isPdfGenerated = ref(false);
const generatedPdfPath = ref('');

// Form state
const journeyPurpose = ref("");

// Daily Allowance form
const dailyAllowance = reactive({
  traveller: '',
  visitPurpose: '',
  fromDate: '',
  toDate: '',
  days: '',
  amount: ''
});

// Tickets state
const tickets = ref([
  {
    passengerName: "",
    date: "",
    mode: "",
    air: {
      from: "",
      to: "",
      airline: "",
      ticketNo: "",
      pnr: "",
      baseFare: "",
      taxes: "",
      serviceFee: "",
      baggageFee: "",
      seatSelectionFee: "",
      inFlightFee: "",
      miscFee: "",
      status: "Confirm"
    },
    road: {
      from: "",
      to: "",
      distance: "",
      amount: ""
    },
    river: {
      from: "",
      to: "",
      distance: "",
      amount: ""
    },
    boardingPass: null,
    ticketBill: null
  },
]);

// Validation
const validationSchema = {
  journeyPurpose: [required()],
  'dailyAllowance.traveller': [required()],
  'dailyAllowance.visitPurpose': [required()],
  'dailyAllowance.fromDate': [required()],
  'dailyAllowance.toDate': [required()],
  'dailyAllowance.days': [required(), pattern(/^[0-9]+$/)],
  'dailyAllowance.amount': [required(), pattern(/^[0-9.]+$/)],

  // Ticket fields validation
  'tickets.*.passengerName': [required(), minLength(2)],
  'tickets.*.date': [required()],
  'tickets.*.mode': [required()],

  // Air journey fields
  'tickets.*.air.from': [required()],
  'tickets.*.air.to': [required()],
  'tickets.*.air.airline': [required()],
  'tickets.*.air.ticketNo': [required()],
  'tickets.*.air.pnr': [required()],
  'tickets.*.air.baseFare': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.air.taxes': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.air.serviceFee': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.air.baggageFee': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.air.seatSelectionFee': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.air.inFlightFee': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.air.miscFee': [required(), pattern(/^[0-9.]+$/)],

  // Road journey fields
  'tickets.*.road.from': [required()],
  'tickets.*.road.to': [required()],
  'tickets.*.road.distance': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.road.amount': [required(), pattern(/^[0-9.]+$/)],

  // River journey fields
  'tickets.*.river.from': [required()],
  'tickets.*.river.to': [required()],
  'tickets.*.river.distance': [required(), pattern(/^[0-9.]+$/)],
  'tickets.*.river.amount': [required(), pattern(/^[0-9.]+$/)],
};

const { errors, validateField, validateAll } = useValidation({ journeyPurpose, dailyAllowance, tickets }, validationSchema);

// Computed properties
const totalAmountsAir = computed(() => {
  return tickets.value
    .filter(ticket => ticket.mode === 'air')
    .reduce((sum, ticket) => {
      const baseFare = parseFloat(ticket.air.baseFare) || 0;
      const taxes = parseFloat(ticket.air.taxes) || 0;
      const serviceFee = parseFloat(ticket.air.serviceFee) || 0;
      const baggageFee = parseFloat(ticket.air.baggageFee) || 0;
      const seatSelectionFee = parseFloat(ticket.air.seatSelectionFee) || 0;
      const inFlightFee = parseFloat(ticket.air.inFlightFee) || 0;
      const miscFee = parseFloat(ticket.air.miscFee) || 0;
      return sum + baseFare + taxes + serviceFee + baggageFee + seatSelectionFee + inFlightFee + miscFee;
    }, 0);
});


const shouldShowDailyAllowance = computed(() => {
  const hasOtherBusiness = selectedVisitPurposes.value.some(purpose =>
    purpose.name === "Other Business connected with duties"
  );
  return !hasOtherBusiness;
});
const totalAmountsRoad = computed(() => {
  return tickets.value
    .filter(ticket => ticket.mode === 'road')
    .reduce((sum, ticket) => sum + (parseFloat(ticket.road.amount) || 0), 0);
});

const totalAmountsWater = computed(() => {
  return tickets.value
    .filter(ticket => ticket.mode === 'river')
    .reduce((sum, ticket) => sum + (parseFloat(ticket.river.amount) || 0), 0);
});

const totalAmountsDa = computed(() => {
  return journeyPurpose.value === 'official' ? (parseFloat(dailyAllowance.amount) || 0) : 0;
});

const totalAmount = computed(() => {
  return totalAmountsAir.value + totalAmountsRoad.value + totalAmountsWater.value + totalAmountsDa.value;
});

// PDF Claim Data
const pdfClaimData = computed(() => {
  const airItems = tickets.value
    .filter(ticket => ticket.mode === 'air')
    .map((ticket, index) => ({
      sno: index + 1,
      itemName: `Air Journey - ${ticket.air.airline}`,
      description: [
        `Passenger Name: ${ticket.passengerName}`,
        `Airline: ${ticket.air.airline}`,
        `From: ${ticket.air.from}`,
        `To: ${ticket.air.to}`,
        `Ticket No: ${ticket.air.ticketNo}`,
        `PNR: ${ticket.air.pnr}`,
        `Date: ${ticket.date}`,
        `Base Fare: ₹${ticket.air.baseFare}`,
        `Taxes: ₹${ticket.air.taxes}`,
        `Service Fee: ₹${ticket.air.serviceFee}`
      ],
      qty: 1,
      unitPrice: (parseFloat(ticket.air.baseFare) || 0) + (parseFloat(ticket.air.taxes) || 0) + (parseFloat(ticket.air.serviceFee) || 0),
      total: (parseFloat(ticket.air.baseFare) || 0) + (parseFloat(ticket.air.taxes) || 0) + (parseFloat(ticket.air.serviceFee) || 0)
    }));

  // Calculate days difference for daily allowance
  if (dailyAllowance.fromDate && dailyAllowance.toDate) {
    const fromDate = new Date(dailyAllowance.fromDate);
    const toDate = new Date(dailyAllowance.toDate);
    const diffTime = toDate - fromDate;
    dailyAllowance.days = diffTime >= 0 ? Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 : 1;
  } else {
    dailyAllowance.days = 1;
  }

  const roadItems = tickets.value
    .filter(ticket => ticket.mode === 'road')
    .map((ticket, index) => ({
      sno: airItems.length + index + 1,
      itemName: 'Road Journey',
      description: [
        `Passenger Name: ${ticket.passengerName}`,
        `From: ${ticket.road.from}`,
        `To: ${ticket.road.to}`,
        `Date: ${ticket.date}`,
        `Distance: ${ticket.road.distance} Km`,
        `Amount: ₹${ticket.road.amount}`
      ],
      qty: 1,
      unitPrice: parseFloat(ticket.road.amount) || 0,
      total: parseFloat(ticket.road.amount) || 0
    }));

  const riverItems = tickets.value
    .filter(ticket => ticket.mode === 'river')
    .map((ticket, index) => ({
      sno: airItems.length + roadItems.length + index + 1,
      itemName: 'River Journey',
      description: [
        `Passenger Name: ${ticket.passengerName}`,
        `From: ${ticket.river.from}`,
        `To: ${ticket.river.to}`,
        `Date: ${ticket.date}`,
        `Distance: ${ticket.river.distance} Km`,
        `Amount: ₹${ticket.river.amount}`
      ],
      qty: 1,
      unitPrice: parseFloat(ticket.river.amount) || 0,
      total: parseFloat(ticket.river.amount) || 0
    }));

  const daItem = journeyPurpose.value === 'official' ? [{
    sno: airItems.length + roadItems.length + riverItems.length + 1,
    itemName: 'Daily Allowance',
    description: [
      `Traveller: ${dailyAllowance.traveller}`,
      `Purpose: ${dailyAllowance.visitPurpose}`,
      `From: ${dailyAllowance.fromDate}`,
      `To: ${dailyAllowance.toDate}`,
      `Days: ${dailyAllowance.days}`,
      `Amount: ₹${dailyAllowance.amount}`
    ],
    qty: parseInt(dailyAllowance.days) || 1,
    unitPrice: parseFloat(dailyAllowance.amount) || 0,
    total: totalAmountsDa.value
  }] : [];

  const allItems = [...airItems, ...roadItems, ...riverItems, ...daItem];

  return {
    // claimId: `TADA${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}${String(new Date().getDate()).padStart(2, '0')}${String(tickets.value.length).padStart(2, '0')}`,
    claimId: claimId.value || 'Generating...',
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
    claimReceivedBy: tickets.value[0]?.passengerName || ''
  };
});


const router = useRouter();

// Validation methods
const validateDailyAllowanceField = (field) => {
  if (dailyAllowance[field] == '') {
    validateField(`dailyAllowance.${field}`);
  } else {
    const fieldPath = `${field}`;
    validateField(fieldPath);
  }
};

const validateTicketField = (index, field) => {
  const ticket = tickets.value[index];
  const fieldPath = `tickets[${index}].${field}`;

  let value;
  if (field.startsWith('air.')) {
    const airField = field.split('.')[1];
    value = ticket.air[airField];
  } else if (field.startsWith('road.')) {
    const roadField = field.split('.')[1];
    value = ticket.road[roadField];
  } else if (field.startsWith('river.')) {
    const riverField = field.split('.')[1];
    value = ticket.river[riverField];
  } else {
    value = ticket[field];
  }

  if (field.startsWith('air.') && ticket.mode !== 'air') return;
  if (field.startsWith('road.') && ticket.mode !== 'road') return;
  if (field.startsWith('river.') && ticket.mode !== 'river') return;

  if (!value || value === '') {
    errors[fieldPath] = 'This field is required';
  } else {
    errors[fieldPath] = '';
  }

  validateField(fieldPath);
};

const clearError = (fieldPath) => {
  if (errors[fieldPath]) {
    errors[fieldPath] = '';
  }
};

const validateAllFields = () => {
  let isValid = true;

  validateField('journeyPurpose');
  if (errors.journeyPurpose) isValid = false;

  if (journeyPurpose.value === 'official') {
    validateDailyAllowanceField('traveller');
    validateDailyAllowanceField('visitPurpose');
    validateDailyAllowanceField('fromDate');
    validateDailyAllowanceField('toDate');

    if (errors['dailyAllowance.traveller'] ||
      errors['dailyAllowance.visitPurpose'] ||
      errors['dailyAllowance.fromDate'] ||
      errors['dailyAllowance.toDate']) {
      isValid = false;
    }
  }

  tickets.value.forEach((ticket, index) => {
    validateTicketField(index, 'passengerName');
    validateTicketField(index, 'date');
    validateTicketField(index, 'mode');

    if (errors[`tickets[${index}].passengerName`] ||
      errors[`tickets[${index}].date`] ||
      errors[`tickets[${index}].mode`]) {
      isValid = false;
    }

    if (ticket.mode === 'air') {
      validateTicketField(index, 'air.from');
      validateTicketField(index, 'air.to');
      validateTicketField(index, 'air.airline');
      validateTicketField(index, 'air.ticketNo');
      validateTicketField(index, 'air.pnr');
      validateTicketField(index, 'air.baseFare');
      validateTicketField(index, 'air.taxes');
      validateTicketField(index, 'air.serviceFee');

      const airFields = ['from', 'to', 'airline', 'ticketNo', 'pnr', 'baseFare', 'taxes', 'serviceFee'];
      if (airFields.some(field => errors[`tickets[${index}].air.${field}`])) {
        isValid = false;
      }
    }
    else if (ticket.mode === 'road') {
      validateTicketField(index, 'road.from');
      validateTicketField(index, 'road.to');
      validateTicketField(index, 'road.distance');
      validateTicketField(index, 'road.amount');

      const roadFields = ['from', 'to', 'distance', 'amount'];
      if (roadFields.some(field => errors[`tickets[${index}].road.${field}`])) {
        isValid = false;
      }
    }
    else if (ticket.mode === 'river') {
      validateTicketField(index, 'river.from');
      validateTicketField(index, 'river.to');
      validateTicketField(index, 'river.distance');
      validateTicketField(index, 'river.amount');

      const riverFields = ['from', 'to', 'distance', 'amount'];
      if (riverFields.some(field => errors[`tickets[${index}].river.${field}`])) {
        isValid = false;
      }
    }
  });

  return isValid;
};

// Ticket management
const addTicket = () => {
  const newIndex = tickets.value.length;
  tickets.value.push({
    passengerName: "",
    date: "",
    mode: "",
    air: {
      from: "",
      to: "",
      airline: "",
      ticketNo: "",
      pnr: "",
      baseFare: "",
      taxes: "",
      serviceFee: "",
      status: "Confirm"
    },
    road: {
      from: "",
      to: "",
      distance: "",
      amount: ""
    },
    river: {
      from: "",
      to: "",
      distance: "",
      amount: ""
    },
    boardingPass: null,
    ticketBill: null
  });

  // Initialize error keys for the new ticket
  errors[`tickets[${newIndex}].passengerName`] = '';
  errors[`tickets[${newIndex}].date`] = '';
  errors[`tickets[${newIndex}].mode`] = '';
  errors[`tickets[${newIndex}].air.from`] = '';
  errors[`tickets[${newIndex}].air.to`] = '';
  errors[`tickets[${newIndex}].air.airline`] = '';
  errors[`tickets[${newIndex}].air.ticketNo`] = '';
  errors[`tickets[${newIndex}].air.pnr`] = '';
  errors[`tickets[${newIndex}].air.baseFare`] = '';
  errors[`tickets[${newIndex}].air.taxes`] = '';
  errors[`tickets[${newIndex}].air.serviceFee`] = '';
  errors[`tickets[${newIndex}].air.baggageFee`] = '';
  errors[`tickets[${newIndex}].air.seatSelectionFee`] = '';
  errors[`tickets[${newIndex}].air.inFlightFee`] = '';
  errors[`tickets[${newIndex}].air.miscFee`] = '';
  errors[`tickets[${newIndex}].road.from`] = '';
  errors[`tickets[${newIndex}].road.to`] = '';
  errors[`tickets[${newIndex}].road.distance`] = '';
  errors[`tickets[${newIndex}].road.amount`] = '';
  errors[`tickets[${newIndex}].river.from`] = '';
  errors[`tickets[${newIndex}].river.to`] = '';
  errors[`tickets[${newIndex}].river.distance`] = '';
  errors[`tickets[${newIndex}].river.amount`] = '';
};

const removeTicket = (index) => {
  if (index > 0 && tickets.value.length > 1) {
    tickets.value.splice(index, 1);

    const allErrorKeys = Object.keys(errors);
    allErrorKeys.forEach(key => {
      if (key.startsWith(`tickets[${index}]`)) {
        delete errors[key];
      }
    });

    for (let i = index + 1; i < tickets.value.length + 1; i++) {
      allErrorKeys.forEach(key => {
        if (key.startsWith(`tickets[${i}]`)) {
          const newKey = key.replace(`tickets[${i}]`, `tickets[${i - 1}]`);
          errors[newKey] = errors[key];
          delete errors[key];
        }
      });
    }
  }
};

const handleFiles = async (files, ticketIndex, fileType) => {
  if (files && files.length > 0) {
    const validFiles = files.filter(file => file.errors.length === 0);
    if (validFiles.length > 0) {
      if (fileType === 'boardingPass') {
        tickets.value[ticketIndex].boardingPass = validFiles;
        console.log('Boarding Pass Files:', validFiles);
        validFiles.forEach(file => {
          console.log('Boarding Pass File Path:', file.path || file.name);
        });
      } else if (fileType === 'ticketBill') {
        tickets.value[ticketIndex].ticketBill = validFiles;
        console.log('Ticket Bill Files:', validFiles);
        validFiles.forEach(file => {
          console.log('Ticket  Bill File Path:', file.path || file.name);
        });
      }
    }
  } else {
    console.log("Error during uploading file", errors);
  }
};


// Update handlePdfGenerated
const handlePdfGenerated = async (blob) => {
  try {
    const fileName = `Claim_Receipt_${pdfClaimData.value.claimId}.pdf`;
    const file = new File([blob], fileName, { type: 'application/pdf' });

    pdfUploadRef.value.customUpload([file]);


  } catch (error) {
    console.error('Error uploading generated PDF to DMS:', error);
  }
};

// Add this handler
const handlePdfUpload = (files) => {
  if (files && files.length > 0 && files[0].errors.length === 0) {
    generatedPdfPath.value = files[0].path;
    isPdfGenerated.value = true;
  }
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

setTimeout(() => {
  if (pdfGenerator.value) {
    pdfGenerator.value.generatePDFBlob();
  }
}, 300);
// Preview functionality
const preparePreviewData = () => {
  previewData.value = {
    member: selectedMember.value,
    journeyPurpose: journeyPurpose.value,
    tickets: tickets.value,
    dailyAllowance: { ...dailyAllowance },
    totalAmountsAir: totalAmountsAir.value,
    totalAmountsRoad: totalAmountsRoad.value,
    totalAmountsWater: totalAmountsWater.value,
    totalAmountsDa: totalAmountsDa.value,
    totalAmount: totalAmount.value
  };
};

const handlePreview = async () => {
  try {
    if (!validateAllFields()) {
      await swalWithBootstrapButtons.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'Please fix all validation errors before submitting.',
      });
      return;
    }

    preparePreviewData();
    showPreview.value = true;

    // Generate PDF for preview
    setTimeout(() => {
      if (pdfGenerator.value) {
        pdfGenerator.value.generatePDFBlob();
      }
    }, 100);

  } catch (error) {
    console.error('Preview error:', error);
    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to generate preview. Please try again.',
    });
  }
};


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

// Prepare payload for API
const preparePayload = () => {
  const payload = new FormData();

  // Basic fields
  payload.append('claim_type', 'TADA');
  payload.append('traveller_type', '24132');
  payload.append('claim_code', claimId.value || '0');
  payload.append('core_user_id', `${selectedMember.value?.core_user_id}`);

  // Purpose IDs
  const purposeIds = selectedVisitPurposes.value.map(p => p.id).join(',');
  payload.append('purpose_of_visit[]', purposeIds);

  // Daily Allowance (match first form format)
  payload.append('da_from_date', dailyAllowance.fromDate || '');
  payload.append('da_to_date', dailyAllowance.toDate || '');
  payload.append('da_days', dailyAllowance.days || '0');
  payload.append('da_amount', dailyAllowance.amount || '0');

  payload.append('total_amount', totalAmount.value.toString());

  //
  // =========================
  //    AIR SECTION
  // =========================
  //
  const airTickets = tickets.value.filter(t => t.mode === 'air');

  if (airTickets.length > 0) {
    payload.append('pnr_data', '1');
  } else {
    payload.append('pnr_data', '0');
  }

  airTickets.forEach((t, i) => {

    payload.append(`pnr_data_airline_name[${i}]`, t.air.airline || '');
    payload.append(`pnr_data_departure[${i}]`, t.air.from || '');
    payload.append(`pnr_data_arrival[${i}]`, t.air.to || '');
    payload.append(`pnr_data_departure_date[${i}]`, t.date || '');
    payload.append(`pnr_data_arrival_date[${i}]`, t.date || '');
    payload.append(`pnr_data_passenger_name[${i}]`, t.passengerName || '');
    payload.append(`pnr_data_ticket_no[${i}]`, t.air.ticketNo || '');
    payload.append(`pnr_data_pnr_no[${i}]`, t.air.pnr || '');

    payload.append(`pnr_data_pnr_status[${i}]`,
      t.air.status === 'Confirm' ? 'Confirmed' : 'Pending'
    );

    payload.append(`pnr_data_base_fare[${i}]`, t.air.baseFare || '0');
    payload.append(`pnr_data_taxes_fee[${i}]`, t.air.taxes || '0');
    payload.append(`pnr_data_baggage_fee[${i}]`, t.air.baggageFee || '0');
    payload.append(`pnr_data_seat_selection_fee[${i}]`, t.air.seatSelectionFee || '0');
    payload.append(`pnr_data_service_fee[${i}]`, t.air.serviceFee || '0');
    payload.append(`pnr_data_in_flight_fee[${i}]`, t.air.inFlightFee || '0');
    payload.append(`pnr_data_misc_fee[${i}]`, t.air.miscFee || '0');

    const admissible =
      (parseFloat(t.air.baseFare) || 0) +
      (parseFloat(t.air.taxes) || 0) +
      (parseFloat(t.air.serviceFee) || 0) +
      (parseFloat(t.air.baggageFee) || 0) +
      (parseFloat(t.air.seatSelectionFee) || 0) +
      (parseFloat(t.air.inFlightFee) || 0) +
      (parseFloat(t.air.miscFee) || 0);

    payload.append(`pnr_data_admissible_amount[${i}]`, admissible.toString());
  });

  //
  // =========================
  //    ROAD SECTION
  // =========================
  //
  const roadTickets = tickets.value.filter(t => t.mode === 'road');

  if (roadTickets.length > 0) {
    payload.append('road_data', '1');
    roadTickets.forEach(t => {
      payload.append('road_data_date_of_journey[]', t.date);
      payload.append('road_data_from_location[]', t.road.from);
      payload.append('road_data_to_location[]', t.road.to);
      payload.append('road_data_distance[]', t.road.distance);
      payload.append('road_data_amount[]', t.road.amount);
    });
  } else {
    payload.append('road_data', '0');
    payload.append('road_data_date_of_journey[]', '');
    payload.append('road_data_from_location[]', '');
    payload.append('road_data_to_location[]', '');
    payload.append('road_data_distance[]', '');
    payload.append('road_data_amount[]', '');
  }

  //
  // =========================
  //    WATER SECTION
  // =========================
  //
  const waterTickets = tickets.value.filter(t => t.mode === 'river');

  if (waterTickets.length > 0) {
    payload.append('water_data', '1');
    waterTickets.forEach((t,i) => {
      payload.append(`water_data_date_of_journey[${i}]`, t.date);
      payload.append(`water_data_from_location[${i}]`, t.river.from);
      payload.append(`water_data_to_location[${i}]`, t.river.to);
      payload.append(`water_data_distance[${i}]`, t.river.distance);
      payload.append(`water_data_amount[${i}]`, t.river.amount);
    });
  } else {
    payload.append('water_data', '0');
  }

  //
  // =========================
  //     TOTAL SECTIONS
  // =========================
  //
  payload.append('total_amounts_road', totalAmountsRoad.value.toString());
  payload.append('total_amounts_water', totalAmountsWater.value.toString());
  payload.append('total_amounts_da', totalAmountsDa.value.toString());

  //
  // =========================
  //    DEVICE + PDF
  // =========================
  //
  payload.append('device_name', 'samsung SM-X706B');
  payload.append('device_ip', '10.174.98.219');

  if (generatedPdfPath.value) {
    payload.append('submited_claim_file', generatedPdfPath.value);
  }

  return payload;
};


// Final submission
const finalSubmit = async () => {
  try {
    isSubmitting.value = true;
    errorMessage.value = "";

    // Ensure PDF is generated
    if (!isPdfGenerated.value) {
      await pdfGenerator.value.generatePDFBlob();

      await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
          reject(new Error('PDF generation timeout'));
        }, 10000);

        const checkInterval = setInterval(() => {
          if (isPdfGenerated.value) {
            clearInterval(checkInterval);
            clearTimeout(timeout);
            resolve();
          }
        }, 100);
      });
    }
    const files = [];
    let pdfPath = '';
    uploadedFiles.value.map(item => {
      if (item.custom == 0 && item.errors.length == 0) {
        files.push(item.path);
      }
      if (item.custom == 1) {
        if (item.errors.length == 0) {
          pdfPath = item.path;
        }
      }
    })

    const formData = preparePayload();

    // Add PDF path to payload if generated
    if (generatedPdfPath.value) {
      formData.append('submited_claim_file', generatedPdfPath.value);
    }
    formData.append('claim_file', files);

    const cleanPayload = formDataToPlainObject(formData);

    const response = await addNewClaim(cleanPayload);
    console.log('Submission payload:', cleanPayload);
    // const response = await addNewClaim(payload);

    if (response.isError === false && (response.success_code === 200 || response.success_code === 201)) {
      isSwal('TA/DA Claim submitted successfully', 'success');

      const claimId = response.data?.id;
      const requestId = response.data?.requestId;

      if (claimId && requestId) {
        router.replace({
          name: 'SubmitEsign',
          state: {
            claimId,
            requestId,
            status: "success",
          },
        });
      }

      resetForm();
    } else {
      throw new Error(response?.message || 'Failed to submit form');
    }

  } catch (error) {
    console.error('Submit error:', error);
    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to submit form. Please try again.',
    });
    errorMessage.value = "Network error occurred. Please check your connection and try again.";
  } finally {
    isSubmitting.value = false;
  }
};


// Reset form
const resetForm = () => {
  journeyPurpose.value = "";
  tickets.value = [{
    passengerName: "",
    date: "",
    mode: "",
    air: {
      from: "",
      to: "",
      airline: "",
      ticketNo: "",
      pnr: "",
      baseFare: "",
      taxes: "",
      serviceFee: "",
      status: "Confirm"
    },
    road: {
      from: "",
      to: "",
      distance: "",
      amount: ""
    },
    river: {
      from: "",
      to: "",
      distance: "",
      amount: ""
    },
    boardingPass: null,
    ticketBill: null
  }];

  Object.keys(dailyAllowance).forEach(key => {
    dailyAllowance[key] = '';
  });

  Object.keys(errors).forEach(key => {
    delete errors[key];
  });

  errorMessage.value = "";
  showPreview.value = false;
  previewData.value = {};
};

// Search members
const searchMembers = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  try {
    isLoading.value = true;
    const response = await getMembers();
    if (response.isError === false && response.success_code === 200) {
      searchResults.value = response.data.filter(member =>
        member.full_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        member.core_user_id.toString().includes(searchQuery.value)
      );
    }
  } catch (error) {
    console.error('Error searching members:', error);
  } finally {
    isLoading.value = false;
  }
};

// Select a member
const selectMember = async (member) => {
  selectedMember.value = member;
  searchResults.value = [];
  searchQuery.value = '';

  try {
    isLoading.value = true;
    const accountResponse = await getAccountDetails(member.core_user_id);

    if (!accountResponse.isError && accountResponse.success_code === 200) {
      accountDetails.value = accountResponse.data;
    }
  } catch (error) {
    console.error('Error fetching member details:', error);
  } finally {
    isLoading.value = false;
  }
};


// Watch for error messages to auto-clear
watch([errorMessage], () => {
  if (errorMessage.value) {
    setTimeout(() => {
      errorMessage.value = "";
    }, 5000);
  }
});

// Close suggestions when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.relative')) {
    // Handle click outside logic if needed
  }
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
  document.addEventListener('click', handleClickOutside);
  fetchPurposeOfVisitOptions();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.border-dashed {
  border-style: dashed;
}

td {
  border: none !important;
}

.multiselect {
  background-color: transparent;
}

input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
}
</style>