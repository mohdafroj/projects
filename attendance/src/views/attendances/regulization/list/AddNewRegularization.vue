<template>
  <form @submit.prevent="handleSubmit">
    <div class="pb-8 px-2 h-[400px] overflow-y-auto">
      <div class="mt-4">
        <div class="relative">
          <input
            type="text"
            v-model="searchQuery"
            @input="searchMembers"
            placeholder="Search by name or division number..."
            class="w-full p-3 border border-gray-300 rounded-lg dark:bg-black-800 dark:text-slate-300 dark:border-gray-600"
          />
          <span v-if="formErrors.member_id" class="text-sm text-red-500">{{ formErrors.member_id }}</span>

          <div v-if="searchResults.length > 0"
            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto dark:bg-black-800 dark:text-slate-300">
            <div
              v-for="member in searchResults"
              :key="member.id"
              @click="selectMember(member)"
              class="p-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700 border-b border-gray-200 last:border-b-0"
            >
              <div class="font-medium">{{ member.name }}</div>
              <div class="text-sm text-gray-500">Division Number: #{{ member.division_no }}</div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(memberDetail).length" class="mt-4 p-4 bg-blue-50 rounded dark:bg-slate-700">
        <div class="grid grid-cols-2 gap-4">
          <div class="flex items-center">
            <img :src="memberDetail.profile_photo" width="35" height="35" class="mr-2" />
            <div>
              <div class="text-md font-semibold text-stone-800 dark:text-white">{{ memberDetail.name }}</div>
              <div class="text-sm text-stone-600 dark:text-white">Division Number: {{ memberDetail.division_no }}</div>
            </div>
          </div>

          <div>
            <div class="text-md font-semibold text-stone-800 dark:text-white">{{ memberDetail.term_start_date }} - {{ memberDetail.term_end_date }}</div>
            <div class="text-sm text-stone-600 dark:text-white">Tenure Status</div>
          </div>

          <div class="flex items-center">
            <div>
              <div class="text-md font-semibold text-stone-800 dark:text-white">{{ memberDetail.party_name }}</div>
              <div class="text-sm text-stone-600 dark:text-white">Political Party</div>
            </div>
          </div>

          <div>
            <div class="text-md font-semibold text-stone-800 dark:text-white">{{ memberDetail.state }}</div>
            <div class="text-sm text-stone-600 dark:text-white">State</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 mt-4">
        <!-- <div>
          <TextInput
            :isRequired="true"
            type="date"
            label="Attendance Date"
            @update:modelValue="(data) => handleFormData(data, 'fromDate')"
          />
          <span v-if="formErrors.attendance_date" class="text-sm text-red-500">{{ formErrors.attendance_date }}</span>
        </div> -->
        <div>
          <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold dark:text-slate-300">Attendance Date *</label>
          <input 
          :type="showDate ? 'date' : 'text'"
          placeholder="dd-mm-yyyy"
          :min="minDate" 
          :max="maxDate" 
          v-model="formData.attendance_date"
          ref="inputMinDateRef"
          @focus="showDate = true"
          @input="(data) => handleFormData(data, 'fromDate')"
          @click="() => handleInputDateClick('minDate')" 
          class="cursor-pointer border rounded-lg px-3 py-2 w-full" />
          <span v-if="formErrors.attendance_date" class="text-sm text-red-500">{{ formErrors.attendance_date }}</span>
        </div>
        <div class="hidden">
          <SelectInput type="select" label="Select Reason *" v-model="select_reason" :options="reasons" @change="(data) => handleFormData(data, 'selectreason')" />
        </div>            
      </div>

      <div class="grid grid-cols-2 gap-4 mt-4">
        <div>
          <FileUploads ref="uploadRef" :accept="acceptFiles" :onFileUpload="postMethod" @update:files="(data) => handleFormData(data, 'file')" />
          <span v-if="formErrors.file" class="text-sm text-red-500">{{ formErrors.file }}</span>
        </div>

        <div>
          <TextArea
            v-show="select_reason == 'Other'"
            v-model="formData.reason"
            label="Add Remark"
            placeholder="Enter remark"
            class="tarea"
            @update:modelValue="(data) => handleFormData(data, 'reason')"
          ></TextArea>
          <span v-if="formErrors.reason" class="text-sm text-red-500">{{ formErrors.reason }}</span>
        </div>
      </div>
    </div>

    <div class="flex justify-center items-center space-x-2 mt-4">
      <Button type="submit" label="Submit" size="sm" color="green-outline" />
      <Button type="reset" label="Reset" size="sm" color="red-outline" @click="resetForm" />
    </div>
  </form>
</template>

<script setup>
import { ref, computed } from "vue";
import { Button, SelectInput, FileUploads, TextArea } from "@sds/oneui-common-ui";
import { fetchActiveMemberList, addLeaveRegularizationRequest } from "@/services/attendanceService";
import { postMethod } from "@/composables/useApi";
import Swal from 'sweetalert2';

const props = defineProps({
  sessionDetails: {
    type: Object,
    required: true
  },
  reasons: {
    type: Array,
    default: []
  },
  handleCloseNewModal: {
    type: Function,
    required: true
  }
})
const today = '';//new Date().toISOString().split('T')[0]
const formInstance = { session_id: props.sessionDetails.id, member_id: '', attendance_date: today, file: '', reason: '' };
const showDate = ref(false);
const select_reason = ref('Other');
const memberDetail = ref({});
const searchQuery = ref('');
const searchResults = ref([]);
const acceptFiles = ref('pdf,png,jpg,jpeg');
const formData = ref({ ...formInstance });
const formErrors = ref({});
const uploadRef = ref(null);
const inputMinDateRef = ref(null);

const maxDate = computed(() => {
  let d = new Date();
  d.setDate(d.getDate() - 1); // Subtract 1 day
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const minDate = computed(() => {
  let d = null;
  if ( props.sessionDetails.session_start_date ) {
    d = new Date(props.sessionDetails.session_start_date)
  } else {
    d = new Date();
    d.setFullYear(d.getFullYear() - 6); // Subtract 6 years
  }
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0"); // Months are 0-based
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const handleInputDateClick = (param) => {
  if ( param == 'minDate' ) {
    inputMinDateRef.value.showPicker();
  }
}

// Search members - simple implementation (you can debounce this if needed)
const searchMembers = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  const response = await fetchActiveMemberList();
  if (response && response.isError == false && response.success_code == 200) {
    searchResults.value = response.data.filter(member => 
      member.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      member.division_no == searchQuery.value
    );
  } else {
    console.error("member list error", response);
  }
};

const selectMember = (member) => {
  memberDetail.value = member;
  formData.value.member_id = member.core_user_id ?? member.id ?? '';
  formErrors.value.member_id = '';
  searchResults.value = [];
  searchQuery.value = '';
};

const handleFormData = (data, fieldName) => {
  if (fieldName === 'fromDate') {
    formData.value.attendance_date = data.target.value;
    formErrors.value.attendance_date = '';
  } else if (fieldName === 'file') {
    if (Array.isArray(data) && data.length && data[0]['errors'] && data[0]['errors'].length === 0) {
      formData.value.file = data[0]['path'];
      formErrors.value.file = '';
    }
  } else if (fieldName == 'selectreason') {
    formData.value.reason = data;
    if ( formData.value.reason == 'Other' ) {
      formData.value.reason = '';
    }
    formErrors.value.reason = '';
  } else if (fieldName === 'reason') {
    formData.value.reason = data;
    formErrors.value.reason = '';
  }
};

const validate = () => {
  const errors = {};
  if (!formData.value.member_id) errors.member_id = 'The field member search is required.';
  if (!formData.value.attendance_date) errors.attendance_date = 'The field attendance date is required.';
  if (!formData.value.file) errors.file = 'The field upload file is required.';
  if (!formData.value.reason) {
    errors.reason = ( select_reason.value == 'Other' ) ? 'The field remark is required.' : 'The field leave reason is required.';
  }

  // Example: check date order
  if (formData.value.attendance_date ) {
    const attendanceDate = new Date(formData.value.attendance_date);
    const checkMaxDate = new Date(maxDate.value);
    if ( !isNaN(checkMaxDate) && !isNaN(attendanceDate) && attendanceDate > checkMaxDate ) {
      const yyyy = checkMaxDate.getFullYear();
      const mm = String(checkMaxDate.getMonth() + 1).padStart(2, "0");
      const dd = String(checkMaxDate.getDate()).padStart(2, "0");
      errors.attendance_date = `The date must be less than or equal to ${yyyy}-${mm}-${dd}`;
    }
  }

  formErrors.value = errors;
  return Object.keys(errors).length === 0;
};

const handleSubmit = async () => {  
  if (!validate()) return;

  // Prepare payload
  const payload = {
    session_id: formData.value.session_id,
    member_id: formData.value.member_id,
    attendance_date: formData.value.attendance_date,
    file_path: formData.value.file,
    reason: formData.value.reason
  };

  const response = await addLeaveRegularizationRequest(payload);
  if (response.isError == false && response.success_code == 200) {
    resetForm();
    props.handleCloseNewModal('added');
  }
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: (response.isError == false && response.success_code == 200) ? 'success' : 'error',
    title: (response.isError == false && response.success_code == 200) ? 'Attendance Regularization added successfully' : response.customMessage,
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true
  });
};

const resetForm = () => {
  formData.value = { ...formInstance };
  memberDetail.value = {};
  searchQuery.value = '';
  searchResults.value = [];
  formErrors.value = {};
  if (uploadRef.value && uploadRef.value.removeFile) {
    uploadRef.value.removeFile();
  }
};
</script>

<style scoped>
.reportRadio :deep(label) {
  margin-right: 2rem;
}
.tarea :deep(textarea) {
  height: 172px;
}
</style>
