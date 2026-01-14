<template>
  <Loading v-if="isLoading" />
  <div
    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 container max-w-full w-full">

    <div class="space-y-8">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">By PNR Number</h1>
      </div>

      <div v-if="errorMessage" class="bg-red-200 text-red-700 p-3 mb-4 rounded-md">
        {{ errorMessage }}
      </div>

      <!-- Form Mode -->
      <div v-show="!showPreview">
        <div>
          <h3 class="text-xl font-semibold text-gray-800">&nbsp; </h3>
        </div>

        <!-- User Search Section -->
        <div class="mb-6 dark:bg-black-800 dark:text-slate-300" v-if="!selectedMember">
          <div class="relative">
            <input
              type="text"
              v-model="searchQuery"
              @input="searchMembers"
              placeholder="Search by Name or IC number..."
              class="w-full p-3 border border-gray-300 rounded-lg dark:bg-black-800 dark:text-slate-300 dark:border-gray-600"
            />
            <div
              v-if="searchResults.length > 0"
              class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto dark:bg-black-800 dark:text-slate-300"
            >
              <div
                v-for="member in searchResults"
                :key="member.id"
                @click="selectMember(member)"
                class="p-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700 border-b border-gray-200 last:border-b-0"
              >
                <div class="font-medium">{{ member.full_name }}</div>
                <div class="text-sm text-gray-500">IC Number: #{{ member.core_user_id }}</div>
              </div>
            </div>
          </div>
        </div>

        <div v-show="selectedMember" class="mb-6">
          <Card class="mb-6">
            <div class="flex justify-center pb-4">
              <div class="rounded-xl shadow-sm w-full p-0">
                <div class="bg-gray-100 rounded-lg flex items-center justify-between mt-2 p-3">
                  <!-- Left Section -->
                  <div class="flex items-center space-x-3">
                    <!-- Avatar -->
                    <img
                      src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png"
                      alt="User Avatar"
                      class="w-10 h-10 rounded-full object-cover"
                    />
                    <!-- User Info -->
                    <div>
                      <h3 class="text-base font-semibold text-gray-900 leading-tight">
                        {{ selectedMember?.full_name }}
                      </h3>
                      <p class="text-sm text-gray-600 leading-tight">
                        IC Number :
                        <span class="text-gray-700 font-medium">#{{ selectedMember?.core_user_id }}</span>
                      </p>
                    </div>
                  </div>

                  <!-- Right Section (Check Icon) -->
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 text-green-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
              </div>
            </div>
          </Card>

          <!-- Form - Only show when no ticket data exists -->
          <div v-if="!ticketData && !isSubmitted">
           <div class="grid grid-cols-1 md:grid-cols-4 xl:grid-cols-4 gap-5">
  <!-- PNR No -->
  <div>
    <TextInput
      v-model="form.pnrNo"
      v-model:error="errors.pnrNo"
      label="PNR No"
      placeholder="Enter PNR No"
      name="pnrNo"
      :isRequired="true"
      :disableBuiltinValidation="true"
      @blur="handleFieldBlur('pnrNo')"
      @input="handleFieldInput('pnrNo')"
      class="mt-2"
    />
  </div>

  <!-- Ticket Number (unchanged) -->
  <div>
    <TextInput
      v-model="form.ticketNumber"
      v-model:error="errors.ticketNumber"
      label="Ticket Number"
      placeholder="Enter ticket number"
      name="ticketNumber"
      :isRequired="true"
      :disableBuiltinValidation="true"
      @blur="handleFieldBlur('ticketNumber')"
      @input="handleFieldInput('ticketNumber')"
      class="mt-2"
    />
  </div>

  <!-- Airline Type (select via TextInput) -->
  <div>
    <TextInput
      type="select"
      v-model="form.airlineCode"
      v-model:error="errors.airlineCode"
      :options="airlineOptions"
      label="Airline Type"
      name="airlineCode"
      :isRequired="true"
      :disableBuiltinValidation="true"
      placeholder="Select Airline"
      option-label="label"
      option-value="value"
      @blur="handleFieldBlur('airlineCode')"
      @change="handleFieldInput('airlineCode')"
      class="mt-2"
    />
  </div>

  <!-- Last Name -->
  <div>
    <TextInput
      v-model="form.lastName"
      v-model:error="errors.lastName"
      label="Last Name"
      placeholder="Enter last name"
      name="lastName"
      :isRequired="true"
      :disableBuiltinValidation="true"
      @blur="handleFieldBlur('lastName')"
      @input="handleFieldInput('lastName')"
      class="mt-2"
    />
  </div>
</div>


            <!-- Submit Button -->
            <div class="md:col-span-2 xl:col-span-3 flex justify-end mt-4">
              <Button
                @click="handleSubmit"
                label="Search Flights"
                :disabled="!isFormValid || isLoading"
                type="button"
                class="bg-blue-900 hover:bg-brand-600 disabled:bg-gray-600 disabled:cursor-not-allowed flex items-center gap-2 rounded-lg p-3 text-sm font-medium text-white transition-colors"
              >
                <span v-if="isLoading">Processing...</span>
                <span v-else>Search Flights</span>
              </Button>
            </div>
          </div>

          <!-- Loading State (visible after submit) -->
          <div v-if="isSubmitted && isLoading" class="text-center">
            <img src="/assets/images/all-img/data-fetch.gif" width="350" alt="Fetching Data" class="mt-5 mx-auto" />
            <p class="font-bold mt-4">Fetching data, please wait...</p>
          </div>

          <!-- Ticket Details Component -->
          <TicketDetails
            v-if="ticketData"
            :ticketData="ticketData"
            :coreUserId="selectedMember?.core_user_id"
            @reset="handleReset"
          />
        </div>
      </div>

      <!-- Links (shown when form is visible or after successful fetch) -->
      <div v-if="!isLoading && !ticketData" class="w-full px-2.5 mt-6">
        <div class="flex flex-col justify-between gap-5 xl:flex-row xl:items-center">
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from "vue";
import { TextInput, Button } from '@sds/oneui-common-ui';
import { getTicketDetails } from "@/services/rss/SubmitTadaClaims";
import { useRouter } from 'vue-router';
import swalWithBootstrapButtons from '@/utils/swal';
import { useValidation, required, minLength, maxLength, pattern } from '@sds/oneui-validation';
import { isSwal } from "@/utils/isSwal";
import TicketDetails from './TicketDetails.vue';
import {
  getMembers,
  getAccountDetails,
  addNewClaim
} from "@/services/rss/SubmitItEquipementClaim";

const router = useRouter();
const showPreview = ref(false);

// Refs
const searchQuery = ref('');
const searchResults = ref([]);
const selectedMember = ref(null);

// Airline options map
const airlineOptions = [
  { label: 'Air India', value: 'AI' },
  { label: 'IndiGo', value: '6E' },
  { label: 'SpiceJet', value: 'SG' },
  { label: 'Vistara', value: 'UK' },
  { label: 'Go Air', value: 'G8' }
];

// Form state
const form = reactive({
  pnrNo: '',
  lastName: '',
    ticketNumber: '',
  airlineCode: '' // holds code like 'AI'
});

// Validation schema
const validationSchema = {
  pnrNo: [required(), minLength(6), maxLength(10), pattern(/^[A-Z0-9]+$/)],
  airlineCode: [required(), minLength(2), maxLength(3), pattern(/^[A-Z0-9]+$/)],
   ticketNumber: [required(), minLength(10), maxLength(20), pattern(/^[0-9]+$/)],
  lastName: [required(), minLength(2), maxLength(50), pattern(/^[a-zA-Z\s]+$/)]
};

const { errors, isValid, validateField, validateAll, clearFieldError } = useValidation(form, validationSchema);

// Component state
const isLoading = ref(false);
const isSubmitted = ref(false);
const showPopup = ref(false);
const errorMessage = ref("");
const ticketData = ref(null);

// Computed
const isFormValid = computed(() => {
  return (
    isValid.value &&
    form.pnrNo.trim() &&
    form.lastName.trim() &&
     form.ticketNumber.trim() &&
    form.airlineCode.trim()
  );
});

// Field handlers
const handleFieldInput = (fieldName) => {
  if (form[fieldName] && form[fieldName].trim()) {
    clearFieldError(fieldName);
  }
  validateField(fieldName);
};

const handleFieldBlur = (fieldName) => {
  validateField(fieldName);
  if (form[fieldName] && form[fieldName].trim() && !errors[fieldName]) {
    clearFieldError(fieldName);
  }
};

// Submit handler
let isProcessing = false;

const handleSubmit = async () => {
  if (isProcessing) return;
  isProcessing = true;

  try {
    errorMessage.value = "";
    ticketData.value = null;

    const ok = await validateAll();
    if (!ok) {
      errorMessage.value = "Please fix all validation errors before submitting.";
      isProcessing = false;
      return;
    }

    isLoading.value = true;
    isSubmitted.value = true;

    const payload = {
      pnr: form.pnrNo.trim().toUpperCase(),
      lastName: form.lastName.trim().toUpperCase(),
        ticketNumber: form.ticketNumber.trim(),
      airline_code: form.airlineCode.trim() // from select, e.g. 'AI'
    };

    console.log('Submitting payload:', payload);

    const response = await getTicketDetails(payload);
    console.log('API response:', response);

    if (response && (response.success_code === 200 || response.success_code === 201)) {
      const transformedData = transformApiResponse(response.data);
      console.log('Transformed Ticket Details:', transformedData);
      ticketData.value = transformedData;
      isSwal('Ticket details fetched successfully!', 'success');
    } else {
      isSubmitted.value = false;

      if (response?.response?.data?.description || response?.response?.data?.error) {
        const errorList = response.response.data.description || response.response.data.error;

        const fieldMapping = {
          pnr: 'pnrNo',
          lastName: 'lastName',
           ticketNumber: 'ticketNumber',
          airline_code: 'airlineCode'
        };

        for (const key in errorList) {
          const formField = fieldMapping[key] || key;
          if (form.hasOwnProperty(formField)) {
            errors[formField] = Array.isArray(errorList[key]) ? errorList[key][0] : errorList[key];
          }
        }
      }

      errorMessage.value = response?.message || "Failed to fetch ticket details. Please try again.";
    }
  } catch (error) {
    console.error('Submit error:', error);
    isSubmitted.value = false;

    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || "Failed to fetch ticket details. Please try again.",
    });

    errorMessage.value = "Network error occurred. Please check your connection and try again.";
  } finally {
    isLoading.value = false;
    isProcessing = false;
  }
};

// Transform API response
const transformApiResponse = (apiData) => {
  const journeyWiseData = apiData.journeyWiseData || [];
  const paymentInformation = apiData.paymentInformation || [];

  const firstJourney = journeyWiseData[0] || {};
  const firstSegment = firstJourney.segments?.[0] || {};

  let baseFare = 0;
  let acceptableTaxAmount = 0;
  let nonAcceptableTaxs = [];
  let serviceResults = [];

  paymentInformation.forEach(payment => {
    payment.fees?.forEach(fee => {
      if (fee.feeName === "Ticket Base Fare") {
        baseFare = fee.amount;
        acceptableTaxAmount = fee.includeTax;
      } else if (fee.feeName === "PENALTY FEE") {
        serviceResults.push({
          service_name: fee.feeName,
          baseFare: fee.amount + fee.includeTax
        });
      }
    });
  });

  const passengerList = firstSegment.passengerList || [];
  const firstPassenger = passengerList[0] || {};

  const nameParts = firstPassenger.name?.trim().split(' ') || [];
  const lastName = nameParts[0] || '';
  const firstName = nameParts.slice(1).join(' ') || '';

  return {
    flight_number: `AI${Math.floor(Math.random() * 1000)}`,
    from: firstJourney.origin || 'N/A',
    to: firstJourney.destination || 'N/A',
    depart_time: firstJourney.departure?.substring(11) || '00:00:00',
    depart_date: firstJourney.departure?.substring(0, 10) || '',
    arrival_time: firstJourney.arrival?.substring(11) || '00:00:00',
    arrival_date: firstJourney.arrival?.substring(0, 10) || '',
    pnr: form.pnrNo.trim().toUpperCase(),
    complete_journey_certification: firstPassenger.liftStatus === 1 ? 'Yes' : 'No',
    passengers: [{
      firstName: firstName,
      lastName: lastName,
      ticket_no: form.ticketNumber.trim(),
      emailAddresses: 'NA',
      phoneNumbers: 'NA'
    }],
    payment_details: {
      baseFare: baseFare,
      acceptableTaxAmount: acceptableTaxAmount,
      acceptableTaxs: [
        {
          name: 'Service',
          charges: acceptableTaxAmount
        }
      ],
      nonAcceptableTaxs: [],
      refundedBaseFare: 0
    },
    service_result: serviceResults
  };
};

// Reset handler
const handleReset = () => {
  ticketData.value = null;
  isSubmitted.value = false;
  resetForm();
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

// Clear messages after timeout
watch([errorMessage], () => {
  if (errorMessage.value) {
    setTimeout(() => {
      errorMessage.value = "";
    }, 5000);
  }
});

// Reset form
const resetForm = () => {
  Object.assign(form, {
    pnrNo: '',
    lastName: '',
      ticketNumber: '',
    airlineCode: ''
  });

  Object.keys(errors).forEach(key => {
    clearFieldError(key);
  });

  isSubmitted.value = false;
  errorMessage.value = "";
};

defineExpose({
  resetForm,
  handleSubmit
});
</script>

<style scoped>
.overlay {
  background: #3333339c;
}

.transition-colors {
  transition: all 0.2s ease-in-out;
}

:deep(.text-input-container) {
  margin-bottom: 1rem;
}

:deep(.error-text) {
  color: #dc2626;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
</style>
