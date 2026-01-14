<template>
  <div class="p-6 min-h-screen">
    <!-- Search Member Section -->
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

    <!-- Selected Member Display -->
    <div v-if="selectedMember" class="mb-6">
      <div class="bg-white rounded-lg flex items-center justify-between mt-2 p-3">
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
              IC Number : <span class="text-gray-700 font-medium">#{{ selectedMember?.core_user_id }}</span>
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

    <!-- Purpose of Journey -->
    <div v-if="selectedMember" class="border border-gray-200 bg-white rounded-xl p-4 mb-4 dark:border-gray-700">
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
        <div v-if="errors['dailyAllowance.visitPurpose']" class="text-red-500 text-sm mt-1">
          {{ errors['dailyAllowance.visitPurpose'] }}
        </div>
      </div>
    </div>

    <!-- Journey Forms Section -->
    <div v-if="selectedMember" class="mt-6 space-y-6">
      <!-- Dynamic Journey Forms -->
      <div
        v-for="(journey, index) in journeyForms"
        :key="index"
        class="p-6 bg-gray-50 rounded-lg border border-gray-200 bg-white"
      >
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">
            Journey Details {{ index + 1 }}
          </h3>
          <button
            v-if="index > 0"
            @click="removeForm(index)"
            class="text-red-600 hover:text-red-800 w-6 h-6 flex items-center justify-center"
          >
            ✕
          </button>
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <TextInput
              type="date"
              label="Date of Journey"
              name="dateOfJourney"
              v-model="journey.dateOfJourney"
              v-model:error="journey.errors.dateOfJourney"
              :isRequired="true"
              :disableBuiltinValidation="true"
              placeholder="Select date"
              @blur="validateJourneyField(index, 'dateOfJourney')"
              @input="clearJourneyFieldError(index, 'dateOfJourney')"
            />

            <TextInput
              type="text"
              label="From Location"
              name="fromLocation"
              v-model="journey.fromLocation"
              v-model:error="journey.errors.fromLocation"
              :isRequired="true"
              :disableBuiltinValidation="true"
              placeholder="Enter starting location"
              @blur="validateJourneyField(index, 'fromLocation')"
              @input="clearJourneyFieldError(index, 'fromLocation')"
            />
     
            <TextInput
              type="text"
              label="To Location"
              name="toLocation"
              v-model="journey.toLocation"
              v-model:error="journey.errors.toLocation"
              :isRequired="true"
              :disableBuiltinValidation="true"
              placeholder="Enter destination"
              @blur="validateJourneyField(index, 'toLocation')"
              @input="clearJourneyFieldError(index, 'toLocation')"
            />

            <TextInput
              type="number"
              label="Distance (Km)"
              name="distance"
              v-model="journey.distance"
              v-model:error="journey.errors.distance"
              :isRequired="true"
              :disableBuiltinValidation="true"
              placeholder="Enter distance"
              @blur="validateJourneyField(index, 'distance')"
              @input="clearJourneyFieldError(index, 'distance')"
            />

            <TextInput
              type="number"
              label="Claim Amount (₹)"
              name="claimAmount"
              v-model="journey.claimAmount"
              v-model:error="journey.errors.claimAmount"
              :isRequired="true"
              :disableBuiltinValidation="true"
              placeholder="Enter claim amount"
              @blur="validateJourneyField(index, 'claimAmount')"
              @input="clearJourneyFieldError(index, 'claimAmount')"
            />
          </div>
        </div>
      </div>

      <!-- Add More Journey Button -->
      <div class="flex justify-end">
        <button
          type="button"
          @click="addMoreJourney"
          class="flex items-center gap-2 text-blue-700 hover:underline font-medium"
        >
          + Add More Journey
        </button>
      </div>
    </div>

    <!-- Daily Allowance Section -->
    <div v-if="selectedMember && shouldShowDailyAllowance" class="border border-gray-200 bg-white rounded-xl p-6 space-y-4 relative mt-6">
      <div class="border-b border-gray-200 pb-1 dark:border-gray-800 mb-4">
        <h4 class="text-lg font-medium text-gray-800 dark:text-white">
          Daily Allowance Claim
        </h4>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300">
            Select Traveller
          </label>
          <select 
            v-model="dailyAllowance.traveller" 
            name="dailyAllowance.traveller" 
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            @blur="validateDailyAllowanceField('traveller')"
            @change="clearError('dailyAllowance.traveller')"
          >
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

        <TextInput
          v-model="dailyAllowance.fromDate"
          type="date"
          name="fromDate"
          label="From Date"
          v-model:error="errors['dailyAllowance.fromDate']"
          :isRequired="true"
          :disableBuiltinValidation="true"
          @blur="validateDailyAllowanceField('fromDate')"
          @input="clearError('dailyAllowance.fromDate')"
        />
        <TextInput
          v-model="dailyAllowance.toDate"
          type="date"
          name="toDate"
          label="To Date"
          v-model:error="errors['dailyAllowance.toDate']"
          :isRequired="true"
          :disableBuiltinValidation="true"
          @blur="validateDailyAllowanceField('toDate')"
          @input="clearError('dailyAllowance.toDate')"
        />
      </div>
    </div>

    <!-- Action Buttons -->
    <div v-if="selectedMember" class="-mx-2.5 flex flex-wrap gap-y-5 justify-end mt-6">
      <div class="px-2.5">
        <button
          @click="handleSubmitForm"
          :disabled="isSubmitting || !isFormValid"
          class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 flex w-full items-center justify-end gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition-colors"
        >
          <span v-if="isSubmitting">Submitting...</span>
          <span v-else>Submit</span>
        </button>
      </div>
      <div class="px-2.5">
        <button
          @click="handleReset"
          class="bg-gray-500 hover:bg-gray-600 flex w-full items-center justify-end gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition-colors"
        >
          Reset Form
        </button>
      </div>
    </div>
    
    <Invoice 
      ref="pdfGenerator"
      :claimData="pdfClaimData" 
      @pdf-generated="handlePdfGenerated"
      class="hidden"
    />
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { TextInput } from '@sds/oneui-common-ui';
import {getClaimTrackId } from "@/services/rss/SubmitItEquipementClaim";
import { addNewClaim, getMembers, getAccountDetails } from '@/services/rss/SubmitTadaClaims';
import { getTadaPurposeVisit, uploadFileChunk } from '@/services/rss/TadaServices';
import { useValidation, required, minLength, maxLength, pattern } from '@sds/oneui-validation';
import swalWithBootstrapButtons from '@/utils/swal';
import Invoice from './invoice.vue';
import { isSwal } from '@/utils/isSwal';
import { cookieService } from '@sds/oneui-layout';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.min.css";
import { sha256 } from 'js-sha256';
const router = useRouter();
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
const userId = rbacAppData?.id;
const designation_name = rbacAppData.post_data[0].designation.name;
const formattedUserId = String(userId).padStart(4, "0");

const getPassengerFullName = rbacAppData.name;

const props = defineProps({
  ticketData: { type: Object, required: true }
});

const pdfGenerator = ref(null);
const isPdfGenerated = ref(false);
const generatedPdfPath = ref('');
const isSubmitting = ref(false);

// Search functionality
const searchQuery = ref('');
const searchResults = ref([]);
const selectedMember = ref(null);
const accountDetails = ref({});
const isLoading = ref(false);
const claimId = ref('');
// Purpose of Visit
const purposeOfVisitOptions = ref([]);
const selectedVisitPurposes = ref([]);
const additionalJourneys = ref([]);
// Daily Allowance form
const dailyAllowance = reactive({
  traveller: '',
  visitPurpose: '',
  fromDate: '',
  toDate: '',
  days: '',
  amount: ''
});

// Journey forms array - starts with one default form
const journeyForms = ref([
  {
    type: 'road',
    dateOfJourney: '',
    fromLocation: '',
    toLocation: '',
    distance: '',
    claimAmount: '',
    errors: {
      dateOfJourney: '',
      fromLocation: '',
      toLocation: '',
      distance: '',
      claimAmount: ''
    }
  }
]);

// Validation schema
const validationSchema = {
  dateOfJourney: [required()],
  fromLocation: [required(), minLength(2), maxLength(100)],
  toLocation: [required(), minLength(2), maxLength(100)],
  distance: [required(), pattern(/^[0-9]+$/)],
  claimAmount: [required(), pattern(/^[0-9]+$/)],
  'dailyAllowance.traveller': [required()],
  'dailyAllowance.fromDate': [required()],
  'dailyAllowance.toDate': [required()]
};

const { errors, validateField } = useValidation({ dailyAllowance, journeyForms }, validationSchema);

// Computed properties
const shouldShowDailyAllowance = computed(() => {
  const hasOtherBusiness = selectedVisitPurposes.value.some(purpose => 
    purpose.name === "Other Business connected with duties"
  );
  return !hasOtherBusiness;
});

const totalAmountsRoad = computed(() => 
  journeyForms.value
    .filter(j => j.type === 'road')
    .reduce((total, j) => total + parseFloat(j.claimAmount || 0), 0)
);

const totalAmountsWater = computed(() => 
  journeyForms.value
    .filter(j => j.type === 'river')
    .reduce((total, j) => total + parseFloat(j.claimAmount || 0), 0)
);

const totalAmountsDa = computed(() => {
  if (!shouldShowDailyAllowance.value) {
    return parseFloat(dailyAllowance.amount) || 0;
  }
  return 0;
});

const totalAmount = computed(() => {
  const airAmount = props.ticketData ? getAdmissibleTotal() : 0;
  return airAmount + totalAmountsRoad.value + totalAmountsWater.value + totalAmountsDa.value;
});

const isFormValid = computed(() => {
  const hasJourneys = journeyForms.value.length > 0 && journeyForms.value.every(j => j.type);
  const hasSelectedMember = !!selectedMember.value;
  
  if (shouldShowDailyAllowance.value) {
    const hasDailyAllowanceFields = dailyAllowance.traveller && dailyAllowance.fromDate && dailyAllowance.toDate;
    return hasJourneys && hasSelectedMember && hasDailyAllowanceFields;
  }
  
  return hasJourneys && hasSelectedMember;
});

// Search members function
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

// Validation helper functions
const validateJourneyField = (index, fieldName) => {
  const journey = journeyForms.value[index];
  const rules = validationSchema[fieldName];
  
  if (!rules) return;
  
  // for (const rule of rules) {
  //   const error = rule(journey[fieldName]);
  //   if (error) {
  //     journey.errors[fieldName] = error;
  //     return;
  //   }
  // }
  journey.errors[fieldName] = '';
};

const clearJourneyFieldError = (index, fieldName) => {
  journeyForms.value[index].errors[fieldName] = '';
};

const validateDailyAllowanceField = (field) => {
  if (dailyAllowance[field] == '') {
    validateField(`dailyAllowance.${field}`);
  } else {
    validateField(field);
  }
};

const clearError = (fieldPath) => {
  if (errors[fieldPath]) {
    errors[fieldPath] = '';
  }
};

const validateAllJourneys = () => {
  let isValid = true;

  // Validate journey forms
  journeyForms.value.forEach((journey, index) => {
    Object.keys(validationSchema).forEach(fieldName => {
      if (!fieldName.startsWith('dailyAllowance')) {
        validateJourneyField(index, fieldName);
        if (journey.errors[fieldName]) {
          isValid = false;
        }
      }
    });
  });

  // Validate daily allowance if shown
  if (shouldShowDailyAllowance.value) {
    validateDailyAllowanceField('traveller');
    validateDailyAllowanceField('fromDate');
    validateDailyAllowanceField('toDate');
    
    if (errors['dailyAllowance.traveller'] || 
        errors['dailyAllowance.fromDate'] || 
        errors['dailyAllowance.toDate']) {
      isValid = false;
    }
  }
  
  return isValid;
};

const addMoreJourney = () => {
  journeyForms.value.push({
    type: 'road',
    dateOfJourney: '',
    fromLocation: '',
    toLocation: '',
    distance: '',
    claimAmount: '',
    errors: {
      dateOfJourney: '',
      fromLocation: '',
      toLocation: '',
      distance: '',
      claimAmount: ''
    }
  });
};

const removeForm = (index) => {
  journeyForms.value.splice(index, 1);
};

const formatTime = (timeString) => timeString ? timeString.substring(0, 5) : 'N/A';

const getAirlineName = () => {
  const airlineCodes = {
    'AI': 'Air India', '6E': 'IndiGo', 'SG': 'SpiceJet', 
    'UK': 'Vistara', 'G8': 'Go Air'
  };
  const flightNumber = props.ticketData.flight_number;
  const airlineCode = flightNumber.replace(/[0-9]/g, '');
  return airlineCodes[airlineCode] || 'Airline';
};

const getAdmissibleTotal = () => {
  return props.ticketData?.payment_details?.baseFare || 0;
};


const calculateFileHash = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const hash = sha256.create();
        hash.update(new Uint8Array(e.target.result));
        resolve(hash.hex());
      } catch (error) {
        reject(error);
      }
    };
    reader.onerror = reject;
    reader.readAsArrayBuffer(file);
  });
};

const uploadFileInChunks = async (fileModel, onProgress) => {
  const file = fileModel.file;
  const chunkSize = 2 * 1024 * 1024 - 128;
  const totalChunks = Math.ceil(file.size / chunkSize);
  const identifier = `${fileModel.name}-${file.size}-${Date.now()}`;
  const fileHash = await calculateFileHash(file);
  let lastResponseData = null;

  for (let i = 0; i < totalChunks; i++) {
    const start = i * chunkSize;
    const end = Math.min(start + chunkSize, file.size);
    const chunk = file.slice(start, end);

    const formData = new FormData();
    formData.append('file', chunk, fileModel.name);
    formData.append('resumableFilename', fileModel.name);
    formData.append('resumableIdentifier', identifier);
    formData.append('resumableChunkNumber', (i + 1).toString());
    formData.append('resumableTotalChunks', totalChunks.toString());
    formData.append('file_hash', fileHash);

    const response = await uploadFileChunk(formData, (progressEvent) => {
      const chunkProgress = progressEvent.loaded / progressEvent.total;
      const overallProgress = (i + chunkProgress) / totalChunks;
      if (onProgress) onProgress(overallProgress);
    });

    if (response?.done === true) {
      lastResponseData = response;
      if (onProgress) onProgress((i + 1) / totalChunks);
    } else {
      throw new Error(`Upload failed at chunk ${i + 1}`);
    }
  }

  return lastResponseData;
};

const pdfClaimData = computed(() => {
  const flightItems = props.ticketData ? [{
    sno: 1,
    itemName: `Flight ${props.ticketData.flight_number}`,
    description: [
      `Passenger Name: ${getPassengerFullName}`,
      `Airline Name: ${getAirlineName()}`,
      `Flight Number: ${props.ticketData.flight_number}`,
      `Departure: ${props.ticketData.from}`,
      `Arrival: ${props.ticketData.to}`,
      `PNR Number: ${props.ticketData.pnr}`,
      `Ticket Number: ${props.ticketData.passengers?.[0]?.ticket_no || 'N/A'}`,
      `Date Time: ${props.ticketData.depart_date} ${formatTime(props.ticketData.depart_time)}`,
    ],
    qty: 1,
    unitPrice: getAdmissibleTotal(),
    total: getAdmissibleTotal()
  }] : [];

  const roadItems = journeyForms.value
    .filter(j => j.type === 'road')
    .map((j, i) => ({
      sno: flightItems.length + i + 1,
      itemName: 'Road Journey',
      description: [
        `From Location: ${j.fromLocation}`,
        `To Location: ${j.toLocation}`,
        `Date Time: ${j.dateOfJourney}`,
        `Distance: ${j.distance} Km`,
        `Fare: ₹${j.claimAmount}`
      ],
      qty: 1,
      unitPrice: parseFloat(j.claimAmount),
      total: parseFloat(j.claimAmount)
    }));

  const waterItems = journeyForms.value
    .filter(j => j.type === 'river')
    .map((j, i) => ({
      sno: flightItems.length + roadItems.length + i + 1,
      itemName: 'River Journey',
      description: [
        `From Location: ${j.fromLocation}`,
        `To Location: ${j.toLocation}`,
        `Date Time: ${j.dateOfJourney}`,
        `Distance: ${j.distance} Km`,
        `Fare: ₹${j.claimAmount}`
      ],
      qty: 1,
      unitPrice: parseFloat(j.claimAmount),
      total: parseFloat(j.claimAmount)
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

  const daItem = dailyAllowance.visitPurpose && dailyAllowance.visitPurpose !== "Other Business connected with duties" ? [{
    sno: flightItems.length + roadItems.length + waterItems.length + 1,
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

  const allItems = [...flightItems, ...roadItems, ...waterItems, ...daItem];

  return {
    claimId: claimId.value || 'Generating',
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
    claimReceivedBy: getPassengerFullName || ''
  };
});

const handlePdfGenerated = async (blob) => {
  try {
    const fileName = `tada_Claim_${pdfClaimData.value.claimId}.pdf`;
    const file = new File([blob], fileName, { type: 'application/pdf' });
    
    const fileModel = {
      file: file,
      name: fileName,
      size: blob.size,
      progress: 0,
      isUploaded: false,
      uploadResponse: null
    };
    
    const response = await uploadFileInChunks(fileModel, (progress) => {
      fileModel.progress = progress;
    });
    
    generatedPdfPath.value = response?.path || '';
    isPdfGenerated.value = true;
  } catch (error) {
    console.error('Error handling generated PDF:', error);
  }
};

// API methods
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

const handlePurposeSelect = (selectedOption) => {
  clearError('dailyAllowance.visitPurpose');
};

const handlePurposeRemove = (removedOption) => {
  // Handled by computed property
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


const preparePayload = () => {
  const payload = new FormData();

  /* =========================
     BASIC FIELDS
  ========================== */
  payload.append('claim_type', 'TADA');
  payload.append('traveller_type', '24132');
  payload.append('claim_code', claimId.value || '0');
   payload.append('core_user_id', `${selectedMember.value?.core_user_id}`);

  // Purpose of visit - join IDs with comma
  const purposeIds = selectedVisitPurposes.value.map(purpose => purpose.id).join(',');
  payload.append('purpose_of_visit[]', purposeIds);

  payload.append('da_from_date', '2025-04-28');
  payload.append('da_to_date', '2025-10-03');
  payload.append('da_days', '2');
  payload.append('da_amount', '4000');
  payload.append('total_amount', totalAmount.value.toString());

  /* =========================
     PNR DATA (INDEXED ARRAYS)
  ========================== */
  if (props.ticketData) {
    const passenger = props.ticketData.passengers?.[0];

    payload.append('pnr_data', '1');

    payload.append('pnr_data_airline_name[0]', getAirlineName());
    payload.append('pnr_data_departure[0]', props.ticketData.from);
    payload.append('pnr_data_arrival[0]', props.ticketData.to);
    payload.append('pnr_data_departure_date[0]', props.ticketData.depart_date);
    payload.append('pnr_data_arrival_date[0]', props.ticketData.arrival_date);
    payload.append('pnr_data_passenger_name[0]', getPassengerFullName());
    payload.append('pnr_data_ticket_no[0]', passenger?.ticket_no || '');
    payload.append('pnr_data_pnr_no[0]', props.ticketData.pnr);

    payload.append(
      'pnr_data_pnr_status[0]',
      props.ticketData.complete_journey_certification === 'Yes'
        ? 'Confirmed'
        : 'Pending'
    );

    payload.append(
      'pnr_data_base_fare[0]',
      props.ticketData.payment_details.baseFare.toString()
    );
    payload.append(
      'pnr_data_taxes_fee[0]',
      props.ticketData.payment_details.acceptableTaxAmount.toString()
    );

    payload.append('pnr_data_baggage_fee[0]', '0');
    payload.append('pnr_data_seat_selection_fee[0]', '0');

    payload.append(
      'pnr_data_service_fee[0]',
      (props.ticketData.service_result?.reduce((s, i) => s + i.baseFare, 0) || 0).toString()
    );

    payload.append('pnr_data_in_flight_fee[0]', '0');
    payload.append('pnr_data_misc_fee[0]', '0');

    payload.append(
      'pnr_data_admissible_amount[0]',
      getAdmissibleTotal().toString()
    );
  } else {
    payload.append('pnr_data', '0');
  }

  /* =========================
     ROAD DATA (INDEXED ARRAYS)
  ========================== */
  const roadJourneys = journeyForms.value.filter(j => j.type === 'road');

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

  /* =========================
     WATER DATA (INDEXED ARRAYS)
  ========================== */
  const waterJourneys = journeyForms.value.filter(j => j.type === 'river');

  if (waterJourneys.length > 0) {
    payload.append('water_data', '1');

    waterJourneys.forEach((j, i) => {
      payload.append(`water_data_date_of_journey[${i}]`, j.dateOfJourney);
      payload.append(`water_data_from_location[${i}]`, j.fromLocation);
      payload.append(`water_data_to_location[${i}]`, j.toLocation);
      payload.append(`water_data_distance[${i}]`, j.distance);
      payload.append(`water_data_amount[${i}]`, j.claimAmount);
    });
  } else {
    payload.append('water_data', '0');
  }

  /* =========================
     TOTALS
  ========================== */
  // payload.append('total_amounts_air', totalAmountsAir.value.toString());
  payload.append('total_amounts_road', totalAmountsRoad.value.toString());
  payload.append('total_amounts_water', totalAmountsWater.value.toString());
  payload.append('total_amounts_da', '4000');



  /* =========================
     DEVICE INFO (OPTIONAL)
  ========================== */
  payload.append('device_name', 'samsung SM-X706B');
  payload.append('device_ip', '10.174.98.219');

  return payload;
};


const handleSubmitForm = async () => {
  if (!validateAllJourneys()) {
    await swalWithBootstrapButtons.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please fix all validation errors before submitting.',
    });
    return;
  }

  try {
    isSubmitting.value = true;

    if (!selectedMember.value) {
      await swalWithBootstrapButtons.fire({
        icon: 'error',
        title: 'Error',
        text: 'Please select a member first.',
      });
      return;
    }

    if (journeyForms.value.length === 0) {
      await swalWithBootstrapButtons.fire({
        icon: 'error',
        title: 'Error',
        text: 'Please add at least one journey before submitting.',
      });
      return;
    }

    // Generate PDF first
    if (pdfGenerator.value) {
      await pdfGenerator.value.generatePDFBlob();
      
      await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('PDF generation timeout')), 10000);
        const checkInterval = setInterval(() => {
          if (isPdfGenerated.value) {
            clearInterval(checkInterval);
            clearTimeout(timeout);
            resolve();
          }
        }, 100);
      });
    }
   const formData = preparePayload();
    

// ⏳ Wait until PDF upload path is available
if (!generatedPdfPath.value) {
  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      reject(new Error('PDF upload timeout'));
    }, 10000);

    const interval = setInterval(() => {
      if (generatedPdfPath.value) {
        clearInterval(interval);
        clearTimeout(timeout);
        resolve();
      }
    }, 100);
  });
}

// ✅ Now append safely
formData.append('submited_claim_file', generatedPdfPath.value);
const cleanPayload = formDataToPlainObject(formData);

// DEBUG (optional)
console.log('Final Payload Object:', cleanPayload);
    const response = await addNewClaim(cleanPayload);

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

      // resetForm();
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
  } finally {
    isSubmitting.value = false;
  }
};

const handleReset = () => {
  journeyForms.value = [{
    type: 'road',
    dateOfJourney: '',
    fromLocation: '',
    toLocation: '',
    distance: '',
    claimAmount: '',
    errors: {
      dateOfJourney: '',
      fromLocation: '',
      toLocation: '',
      distance: '',
      claimAmount: ''
    }
  }];
  selectedVisitPurposes.value = [];
  Object.keys(dailyAllowance).forEach(key => {
    dailyAllowance[key] = '';
  });
  Object.keys(errors).forEach(key => {
    clearError(key);
  });
  selectedMember.value = null;
  searchQuery.value = '';
  searchResults.value = [];
};

// Close suggestions when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.relative')) {
    searchResults.value = [];
  }
};

onMounted(async () => {
    try {
    const response = await getClaimTrackId();

    // Adjust this based on your actual API response structure
    claimId.value =
      response.data?.claim_track_id || response.claim_track_id || response.data?.claim_track_id || '';

    // console.log('Fetched Claim ID:', claimId.value);
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
.transition-colors {
  transition: all 0.2s ease-in-out;
}
</style>