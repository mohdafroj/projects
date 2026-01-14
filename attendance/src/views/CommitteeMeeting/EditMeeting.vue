<template>
     <h3 class="text-lg font-semibold mb-4">{{ formTitle }}</h3>
  <div class="p-6 max-w-6xl mx-auto">
    <Card class="p-6 mt-4">
     <h3 class="text-lg font-semibold mb-4">{{ formTitle }}</h3>


      <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- House Selection -->
         <!-- House Selection -->
        <div class="form-group col-span-2">
          <label class="block text-sm font-semibold mb-2">Select House</label>
          <div class="flex space-x-4">
            <label
              class="flex items-center justify-between border rounded px-4 py-2 cursor-pointer w-40"
              :class="{
                'bg-blue-100 text-blue-800 border-blue-500': houseType === 'Lok Sabha',
                'bg-white text-gray-800 border-gray-300': houseType !== 'Lok Sabha'
              }"
            >
              <span>Lok Sabha</span>
              <input
                type="radio"
                value="Lok Sabha"
                v-model="houseType"
                class="hidden"
              />
              <svg
                v-if="houseType === 'Lok Sabha'"
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
                'bg-blue-100 text-blue-800 border-blue-500': houseType === 'Rajya Sabha',
                'bg-white text-gray-800 border-gray-300': houseType !== 'Rajya Sabha'
              }"
            >
              <span>Rajya Sabha</span>
              <input
                type="radio"
                value="Rajya Sabha"
                v-model="houseType"
                class="hidden"
              />
              <svg
                v-if="houseType === 'Rajya Sabha'"
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

        <!-- <div class="form-group col-span-2">
          <label class="block text-sm font-semibold mb-2">Select House</label>
          <CustomRadio
            v-model="houseType"
            :options="[
              { label: 'Lok Sabha', value: '2' },
              { label: 'Rajya Sabha', value: '1' }
            ]"
          />
        </div> -->
         <!-- <div class="form-group col-span-2">
            <label class="block text-sm font-semibold mb-2">Select House</label>
            <label class="mr-4">
              <input type="radio" value="Lok Sabha" v-model="houseType" />
                  Lok Sabha
            </label>
               <label>
                <input type="radio" value="Rajya Sabha" v-model="houseType" />
               Rajya Sabha
                </label>
          </div> -->
        <!-- Select Category -->
        <Select
          label="Select Category"
          name="category"
          v-model="form.category"
          :options="categories"
          placeholder="Select Category"
          :error="errors.category"
        />

        <!-- Select Committee -->
        <Select
          label="Select Committee"
          name="committee"
          v-model="form.committee"
          :options="committees"
          placeholder="Select Committee"
          :error="errors.committee"
        />

        <!-- Meeting No. -->
        <Textinput
          label="Meeting No."
          type="text"
          name="meeting_no"
          v-model="form.meetingNo"
          placeholder="Enter Meeting No."
          :error="errors.meetingNo"
        />

        <!-- Date -->
        <Textinput
          label="Date"
          type="date"
          name="date"
          v-model="form.date"
          :error="errors.date"
        />

        <!-- Time -->
        <Textinput
          label="Time"
          type="time"
          name="time"
          v-model="form.time"
          :error="errors.time"
        />

        <!-- Venue -->
       <Select
        label="Meeting Venue"
        name="venue"
        v-model="form.venue"
             :options="venues"
         placeholder="Select Venue"
           :error="errors.venue"
                />

        <!-- Agenda -->
        <div class="col-span-2">
          <label class="block text-sm font-semibold mb-2">Agenda</label>
          <textarea
            v-model="form.agenda"
            name="agenda"
            rows="4"
            class="w-full p-3 border border-gray-300 rounded text-sm"
            placeholder="Enter agenda here"
          ></textarea>
        </div>

          <div class="flex justify-center mt-6 gap-4">
        <button type="submit"
          class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600"
          
        >
           {{ isEditing ? 'Update' : 'Submit' }}
        </button>
        <button
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
import { ref, onMounted,computed ,watch  } from 'vue'
import { fetchCategoryList, fetchCommitteeOption, fetchVenueList,createMeeting,updateMeeting, editMeeting  } from '@/services/committeeServices'
import Card from "@/ui-components/Card.vue";
import Select from "@/ui-components/Select.vue";
import Textinput from "@/ui-components/Textinput.vue";
import Swal from 'sweetalert2'
// import RadioGroup from "@/ui-components/RadioGroup.vue";
import { FileUpload, TextInput, Button,RadioGroup, CustomRadio, Modal,  Switch, Pagination, Badge } from '@sds/oneui-common-ui';
import {useRoute, useRouter } from 'vue-router'
const route = useRoute()
const router = useRouter()
const isEditing = ref(false)
const houseType = ref('Lok Sabha') // default selected value
const meetingId = ref(null);
//const passedFormData = router.options.history.state?.formData;
const form = ref({
  category: '',
  committee: '',
  meetingNo: '',
  date: '',
  time: '',
  venue: '',
  agenda: ''
})

const errors = ref({
  category: '',
  committee: '',
  meetingNo: '',
  date: '',
  time: '',
  venue: ''
})
const categories = ref([])
const committees = ref([])
const venues = ref([])

const resetForm = () => {
  form.value = {
    category: '',
    committee: '',
    meetingNo: '',
    date: '',
    time: '',
    venue: '',
    agenda: ''
  }
  houseType.value = 'Lok sabha'
  isEditing.value = false
}
const formTitle = computed(() =>
  isEditing.value ? 'Update Meeting Details' : 'Create New Meeting'
);
const fetchDropdownData = async () => {
  try {
    const params = { current_page: 1, per_page: 1000 } // get large number of items for dropdown
    const [catRes, comRes, venRes] = await Promise.all([
      fetchCategoryList(params),
      fetchCommitteeOption(params),
      fetchVenueList(params)
      
    ])

    // categories
    categories.value = (catRes?.data || []).map(item => ({
      label: item.name,
      value: item.id || item.slug
    }))

    // committees
    committees.value = (comRes?.data || []).map(item => ({
      label: item.name,
      value: item.id || item.slug
    }))
    // venues
  venues.value = venRes.data.map(item => ({
  label: item.venue_name, 
  value: item.id
}))

  } 
  catch (error) {
    console.error('Error fetching dropdown data:', error)
  }
}
const formatDateTime = (value) => {
  if (!value) return 'N/A';

  // Expected format: "MM/DD/YYYY HH:MM AM/PM"
  const [datePart, timePart, ampm] = value.split(' ');

  if (!datePart || !timePart || !ampm) return value; // fallback to raw value if something breaks

  return `${datePart}\n${timePart} ${ampm}`;
};

// const formatDate = (inputDate) => {
//   if (!inputDate) return null;
//   const date = new Date(inputDate);
//   if (isNaN(date)) return null;
//   return date.toISOString().split('T')[0]; // => '2025-06-24'
// };

// const formatTime = (inputTime) => {
//   if (!inputTime) return null;
//   return `${inputTime}:00`; // e.g., '12:40' → '12:40:00'
// };
//edit form
const populateForm = async (id) => {
  console.log("Edit ID", id);
  try {
    const response = await editMeeting(id); // GET request to fetch meeting
    const data = response.data;

    meetingId.value = id; // Store for later update
    isEditing.value = true;

    form.value = {
      category: data.category_id || data.category || '',
      committee: data.committee_id || '',
      meetingNo: data.meeting_no || '',
      date: formatDateTime(data.date || data.date_time),
      time: data.time?.slice(0, 5) || '', // format to 'HH:MM'
      venue: data.venue_id || '',
      agenda: data.agenda || ''
    };

   // houseType.value = String(data.house) === '2' ? 'Lok Sabha' : 'Rajya Sabha';
  } catch (error) {
    console.error("Error fetching meeting:", error);
    Swal.fire({
      icon: "error",
      title: "Failed to load meeting data",
      text: error.message || "Something went wrong"
    });
  }
};

const cancelForm = () => {
  form.value = {
    category: '',
    committee: '',
    meetingNo: '',
    date: '',
    time: '',
    venue: '',
    agenda: ''
  }
  houseType.value = 'Lok sabha'
}
const houseValueMap = {
  'Lok Sabha': 2,
  'Rajya Sabha': 1
}
const houseLabelMap = {
  2: 'Lok Sabha',
  1: 'Rajya Sabha'
}


const submitForm = async () => {
  errors.value = {}

  // Validate required fields
  if (!form.value.category) errors.value.category = 'Category is required.'
  if (!form.value.committee) errors.value.committee = 'Committee is required.'
  if (!form.value.meetingNo) errors.value.meetingNo = 'Meeting No. is required.'
  if (!form.value.date) errors.value.date = 'Date is required.'
  if (!form.value.time) errors.value.time = 'Time is required.'
  if (!form.value.venue) errors.value.venue = 'Venue is required.'
  if (!form.value.agenda) errors.value.agenda = 'Agenda is required.'

  if (Object.keys(errors.value).length > 0) return
  console.log('Selected houseType:', houseType.value)
  const payload = {
    house: houseValueMap[houseType.value],
    category: form.value.category,
    committee_id: form.value.committee,
    meeting_no: form.value.meetingNo,
    date: formatDateTime(form.value.date),
    time: form.value.time,
    venue_id: form.value.venue,
    agenda: form.value.agenda
  }

  try {
    let response
    if (isEditing.value) {
       //const meetingId = passedFormData?.id
       response = await updateMeeting(meetingId.value, payload)
    } else {
      response = await createMeeting(payload)
    }

    const successCode = isEditing.value ? 200 : 201
    const successMessage = isEditing.value
      ? 'Meeting updated successfully!'
      : 'Meeting created successfully!'

    if (response?.success_code === successCode || response?.success) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: successMessage,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
     
      cancelForm()
      isEditing.value = false
    } else {
      throw new Error(response?.message || "Failed")
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



const loadDropdowns = async () => {
  try {
    const [catRes, comRes, venRes] = await Promise.all([
      fetchCategoryList(),
      fetchCommitteeOption(),
      fetchVenueList()
    ])

    categories.value = catRes.data.map(item => ({
      label: item.name,
      value: item.id || item.slug
    }))

    committees.value = comRes.data.map(item => ({
      label: item.name,
      value: item.id || item.slug
    }))

    venues.value = venRes.data.map(item => ({
       label: item.venue_name,
       value: item.id || item.slug
    }))
    console.log("VENUE OPTIONS:", venues.value);
  } catch (error) {
    console.error('Error loading dropdown data:', error)
  }
}

// onMounted( () => {
//   loadDropdowns();

// })
onMounted(async () => {
  await loadDropdowns()
  //await fetchCategoryByHouse(houseType.value)
  const id = route.params.id;
  if(id) {
    populateForm(id)
  }

})

</script>
