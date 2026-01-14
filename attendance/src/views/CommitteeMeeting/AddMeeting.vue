<template>
    <Loading v-if="isLoading" />

  <h2 class="text-lg font-bold text-gray-800 pb-2">{{ formTitle }}</h2>
  <div class=''>
    <Card class="mt-2">
      <form @submit.prevent="submitForm"  class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- House Selection -->
         <!-- House Selection -->
        <div class="form-group col-span-3">
          <label class="block text-sm font-semibold mb-2">Select House</label>
          <div class="flex space-x-4">
            
            <label
              class="flex items-center justify-between border rounded px-4 py-2 cursor-pointer w-40"
              :class="{
                'bg-blue-100 text-blue-800 border-blue-500': houseType == 1,
                'bg-white text-gray-800 border-gray-300': houseType != 1
              }"
            >
              <span>Rajya Sabha</span>
              <input :disabled="isEditing"
                type="radio"
                value="1"
                v-model="houseType"
                class="hidden"
                @change="loadDropdowns(1)"
              />
              <svg
                v-if="houseType == 1"
                class="w-5 h-5 text-blue-600"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 10-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z"
                  clip-rule="evenodd"
                />
              </svg>
            </label>
          
            
            <label
              class="flex items-center justify-between border rounded px-4 py-2 cursor-pointer w-40"
              :class="{
                'bg-blue-100 text-blue-800 border-blue-500': houseType == 2,
                'bg-white text-gray-800 border-gray-300': houseType != 2
              }"
            >
              <span>Lok Sabha</span>
              <input
                type="radio"
                value="2" :disabled="isEditing"
                v-model="houseType"
                class="hidden"
                @change="loadDropdowns(2)"
              />
              <svg
                v-if="houseType == 2"
                class="w-5 h-5 text-blue-600"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 10-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z"
                  clip-rule="evenodd"
                />
              </svg>
            </label>

          </div>
        </div>
          <!-- Select Category -->
        <SelectInput
          label="Select Category"
          name="category"
          @change="loadcommitteeList"
           :isRequired="true"
           :disableBuiltinValidation="true"
          v-model="form.category"
          :options="categories"
          placeholder="Select Category"
          v-model:error="errors.category"
          :disabled="isEditing"
           @blur="validateField('category')"
           @input="validateField('category')"
        />

        <!-- Select Committee -->
        <SelectInput
          label="Select Committee"
          name="committee"
          v-model="form.committee"
          v-model:error="errors.committee"
          :isRequired="true"
           :disableBuiltinValidation="true"
          :options="committees"
          placeholder="Select Committee"
          :error="errors.committee"
          :disabled="isEditing"
           @blur="validateField('committee')"
           @input="validateField('committee')"
          @change="UpdateMeetingNo"
        />

        <!-- Meeting No. -->
         <div class="formGroup realtive">
          <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">Meeting No</label>
          <div class="relative w-full">
        <input
          label="Meeting No."
          type="number"
          step="1"
           :isRequired="true"
          @input="blockDecimal; validateField('meetingNo');"
          @keydown="preventInvalidKeys"
       
          :error="errors.meetingNo"
          :disabled="isEditing"
           @blur="validateField('meetingNo')"
          min=0
          name="meeting_no"
          v-model="form.meetingNo"
          placeholder="Enter Meeting No."
          class="no-arrows border border-gray-300 rounded px-2 py-1 custom-input w-full"
          :class="{ 'border-red-500': errors.meetingNo }"
        >
        
        </div>
        <p v-if="errors.meetingNo" class=" text-red-500 text-sm">{{ errors.meetingNo }}</p>
       </div>
       
        <!-- Date -->
        <Textinput
          label="Date"
          type="date"
          name="date"
          v-model="form.date"
          :error="errors.date"
           :isRequired="true"
           @blur="validateField('date')"
           @input="validateField('date')"
           :disableBuiltinValidation="true"
        />

        <!-- Time -->
        <Textinput
        ref="timeRef"
          label="Time"
          type="time"
          name="time"
           :isRequired="true"
          v-model="form.time"
          :error="errors.time"
           @focus="openTimePicker"
           @click="openTimePicker"
            @blur="validateField('time')"
           @input="validateField('time')"
           :disableBuiltinValidation="true"
        />
        
        <!-- Venue -->
       <SelectInput
        label="Meeting Venue"
        name="venue"
        v-model="form.venue"
        :options="venues"
         :isRequired="true"
         placeholder="Select Venue"
           v-model:error="errors.venue"
           :disabled="isEditing"
            @blur="validateField('venue')"
           @input="validateField('venue')"
           :disableBuiltinValidation="true"
                />

        <!-- Agenda -->
        <div class="col-span-3">
          <label class="block text-sm font-semibold mb-2">Agenda</label>
          <textarea
            v-model="form.agenda"
            name="agenda" maxlength="1000"
            @dragover.prevent
            @drop.prevent
            :isRequired="true"
            @dragenter.prevent
            @dragleave.prevent
            :error="errors.agenda"
           @blur="validateField('agenda')"
           @input="validateField('agenda')"
           :disableBuiltinValidation="true"
            rows="4"
            class="w-full p-3 border border-gray-300 rounded text-sm"
            :class="{ 'border-red-500': errors.agenda }"
            placeholder="Enter agenda here"
          ></textarea>
          <p v-if="errors.agenda" class=" text-red-500 text-sm">{{ errors.agenda }}</p>
        </div>

        <div class="w-full flex justify-end mt-1 gap-4 col-span-3">
        <button type="submit"
          class="bg-blue-800 text-white px-6 py-2 rounded hover:bg-blue-600"
          
        >
           {{ isEditing ? 'Update' : 'Submit' }}
        </button>
        <button type="button"
          class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300"
          @click="cancelForm"
        >
          Cancel
        </button>
      </div>
      </form>

      <!-- Submit Buttons -->
    
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted,computed ,watch,nextTick,reactive  } from 'vue'
import { fetchCategoryList, fetchCommitteeOption, fetchVenueList,createMeeting,updateMeeting, editMeeting,fetchMeetingNOupdate } from '@/services/committeeServices'
import Card from "@/ui-components/Card.vue";
import Select from "@/ui-components/Select.vue";
import Textinput from "@/ui-components/Textinput.vue";
import Swal from 'sweetalert2'
import { useValidation, required, minLength, maxLength,pattern } from '@sds/oneui-validation';
// import RadioGroup from "@/ui-components/RadioGroup.vue";
import { FileUpload, TextInput, Button,RadioGroup, CustomRadio, Modal,  Switch, Pagination, Badge, SelectInput } from '@sds/oneui-common-ui';
import {useRoute, useRouter } from 'vue-router'
import Loading from "@/components/Loding.vue";
import { dateNotBeforeToday } from "@/rules/previousDate";



const route = useRoute()
const router = useRouter()
const isEditing = ref(false);
const houseType = ref(1) // default selected value
const meetingId = ref(null);
const timeRef = ref(null);

const house_id = ref(null);
const form = reactive({
  house: '',
  category: '',
  committee: '',
  committee_id:'',
  meetingNo: '',
  date: '',
  time: '',
  venue: '',
  agenda: '',
  house_id:''
})


const initialForm = () => ({
  house: '',
  category: '',
  committee: '',
  committee_id: '',
  meetingNo: '',
  date: '',
  time: '',
  venue: '',
  agenda: '',
  house_id: ''
})

// const form = reactive(initialForm())

// Convert "YYYY-MM-DD" -> "DD/MM/YYYY" for API if needed
const toApiDate = (ymd) => {
  if (!ymd) return ''
  const [y, m, d] = ymd.split('-')
  return `${d}/${m}/${y}`
}



// const errors = ref({
//   category: '',
//   committee: '',
//   meetingNo: '',
//   date: '',
//   time: '',
//   venue: '',
//   agenda:''
  
// })

const categories = ref([])
const committees = ref([])
const venues = ref([])

// const resetForm = () => {
//   form = {
//     category: '',
//     committee: '',
//     meetingNo: '',
//     date: '',
//     time: '',
//     venue: '',
//     agenda: ''
//   }
//   houseType.value = ''
//   isEditing.value = false
// }

// const resetForm = () => {
//   Object.assign(form, initialForm())
//   houseType.value = 1        // or keep current selection; set as you need
//   isEditing.value = false
// }

const isLoading = ref(true);
const validationSchema = {
  category: [required()],
  committee: [required()],
  meetingNo: [required(),
  maxLength(4)],
  date: [required(),dateNotBeforeToday('Start date cannot be before today')],
  time: [required()],
  venue: [required()],
  agenda: [required()],

};
const { errors, isValid, validateField, validateAll } = useValidation(form, validationSchema);

const formTitle = computed(() =>
  isEditing.value ? 'Update Meeting Details' : 'Create New Meeting'
);

// const numberValue = ref('')
const blockDecimal = (event) => {
  const value = event.target.value
  // Only allow integers (optional "-" at start, digits only, no dot)
  const integerOnly = /^-?\d*$/
  if (!integerOnly.test(value)) {
    // Remove invalid characters (e.g., dots or letters)
    event.target.value = value.replace(/[^\d-]/g, '')
  }
  form.meetingNo = event.target.value
}
const preventInvalidKeys = (event) => {
  // Disallow 'e', '.', '+', etc.
  if (['e', 'E', '+', '-', '.'].includes(event.key)) {
    event.preventDefault();
  }
}

const openTimePicker = () => {
  const el = timeRef.value?.$el?.querySelector('input');
  if (el && typeof el.showPicker === 'function') {
    el.showPicker();
  }
};

const formatDateTime = (value) => {
  if (!value) return 'N/A';

  const parts = value.split(' ');
  if (parts.length !== 3) return value; // fallback if unexpected format

  const [datePart, timePart, ampm] = parts;
  const [month, day, year] = datePart.split('/');

  if (!month || !day || !year) return value;

  // Return date on first line, time + AM/PM on second line
  return `${day}/${month}/${year}\n${timePart} ${ampm}`;
};
const toDateInputFormat = (value) => {
  if (!value) return '';

  const [day, month, year] = value.split('/');
  return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
};



const populateForm = async (id) => {
  
  isEditing.value = true;
 // console.log("Edit ID", id);
    isLoading.value = true;

  try {
    
    const response = await editMeeting(id); // GET request to fetch meeting
    isLoading.value = false;

    const data = response.data;
    meetingId.value = id; // Store for later update
    house_id.value = data.house_id;
    houseType.value=data.house_id;
    // editable field
    
      form.category= data.category_id|| '',
      form.committee= data.committee_id || '',
      form.meetingNo= data.meeting_no || '',
     // date= formatDateTime(data.date || data.date_time),
      form.date= toDateInputFormat(data.date) || '',
      form.time= data.time?.slice(0, 5) || '', // format to 'HH=MM'
      form.venue= data.venue_id || '',
      form.agenda= data.agenda || ''
    loadcommitteeList(form.category);
   //console.log("form.value,(data.house)",form.value);
     
  } catch (error) {
    console.error("Error fetching meeting:", error);
    Swal.fire({
      icon: "error",
      title: "Failed to load meeting data",
      text: error.message || "Something went wrong"
    });
  }
};

// const cancelForm = () => {
//   form.value = {
//     category: '',
//     committee: '',
//     meetingNo: '',
//     date: '',
//     time: '',
//     venue: '',
//     agenda: ''
//   }
 
 
//   isEditing.value = false
//   router.push('/committee-meeting')
// }
// --- fix cancelForm (stop reassigning form / using form.value) ---
const cancelForm = () => {
  Object.assign(form, initialForm())
  isEditing.value = false
  router.push('/committee-meeting')
}

const houseValueMap = {
  'Lok Sabha': 2,
  'Rajya Sabha': 1
}
const houseLabelMap = {
  2: 'Lok Sabha',
  1: 'Rajya Sabha'
}



//meeting no end here
const submitForm = async () => {
    if (await validateAll()) {
   // console.log('Form Submitted', form);
  

    isLoading.value = true;
    const payload = {
  house: houseType.value,
  category: form.category,
  category_id: form.category,
  committee_id: form.committee,
  meeting_no: form.meetingNo,
  date: toApiDate(form.date),  
  time: form.time,
  venue_id: form.venue,
  agenda: form.agenda,
}

  try {
    let response
    if (isEditing.value) {
       //const meetingId = passedFormData?.id
       response = await updateMeeting(meetingId.value, payload)
    } else {
      response = await createMeeting(payload)
    }
    isLoading.value = false;

    //const successCode = isEditing.value ? 200 : 201
    const successCode = 200
    const successMessage = isEditing.value
      ? 'Meeting updated successfully!'
      : 'Meeting created successfully!'
      console.log("API Response:", response)
       console.log("Success Code Expected:", successCode)

       
   if (Number(response?.success_code) === successCode && response?.isError === false) {
  Swal.fire({
    toast: true,
    position: "top-end",
    icon: "success",
    title: "Meeting saved successfully",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
  })

  cancelForm()
  isEditing.value = false
  router.push({ name: 'CommitteeMeeting' })
// } else {
//   throw new Error(response?.message || "Failed")
// }

  }
  else if ( response.error_code == 401) {
    const errorMessages = Object.values(response.error).flat().join(", ");

    Swal.fire({
      icon: "error",
      title: "Error",
      text: errorMessages || response.error || "An error occurred",
    });

  } else {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: response?.error || "Failed",
    });
  }
  } catch (error) {
    console.error('Submit error:', error)
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: error?.message || "Error saving meeting",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    })
  }
}
}
const UpdateMeetingNo = async () => {
  console.log("iddddddddddddddddddddddd",form.committee);
 const res = await fetchMeetingNOupdate(form.committee);
 form.meetingNo = res.data;
}

const loadDropdowns = async (house_id) => {
  try {
    isLoading.value = true;
     
     
    // Fetch each API call one after another
    const catRes = await fetchCategoryList(house_id);
    categories.value = catRes.data.map(item => ({
      label: item.name,
      value: item.id || item.slug
    }));
    const venRes = await fetchVenueList();
    venues.value = venRes.data.map(item => ({
      label: item.venue_name,
      value: item.id || item.slug
    }));

  } catch (error) {
    console.error('Error loading dropdown data:', error);
  } finally {
    isLoading.value = false;
  }
};

const loadcommitteeList = async (category_id) => {
  try {
  
    
    isLoading.value = true;

    const payload = {
      house_id : houseType.value,
      category_id:category_id
    }
       
    const comRes = await fetchCommitteeOption(houseType.value,category_id);

    committees.value = comRes.data.map(item => ({
      label: item.name,
      value: item.id || item.slug
    }));
    
  }
   catch (error) {
    console.error('Error loading dropdown data:', error);
  } finally {
    isLoading.value = false;
  };
}

onMounted(async () => {
  await loadDropdowns(1);
  //await UpdateMeetingNo();
  const id = route.params.id;
  if(id) {
    populateForm(id)
  }

})

</script>
<style>
.no-arrows::-webkit-outer-spin-button,
.no-arrows::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.custom-input {
  height: 40px; /* or whatever height you want */
  padding: 5px 10px;
  font-size: 16px;
  box-sizing: border-box;
}

/* input[type="number"] {
  border: 1px solid #ccc;
  border-radius: 4px;
} */

</style>
