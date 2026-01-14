<template>
  <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
    <!-- Content Start -->
    <div class="space-y-6">
      <!-- User Info Card -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
          <h2 class="text-lg font-medium text-gray-800 dark:text-white">
            Add New CGHS Card
          </h2>
        </div>
        <div class="p-4 sm:p-6 dark:border-gray-800">
          <div class="flex items-center gap-3 p-3 border rounded-xl">
            <!-- <img src="https://i.pravatar.cc/100?img=1" class="w-12 h-12 rounded-full" /> -->
            <div>
              <p class="font-semibold"> {{(setFormVal.srchName.username)?setFormVal.srchName.username:''}}</p>
              <!-- <p class="text-sm text-gray-600">UI/UX Designer</p> -->
            </div>
          </div>
        </div>
      </div>

      <!-- Application Form -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
          <h2 class="text-lg font-medium text-gray-800 dark:text-white">
            CGHS Card Application Form
          </h2>
        </div>

        <div class="p-4 sm:p-6 dark:border-gray-800">
          <div class="mx-auto">
            <!-- Title -->
            <div class="mb-3">
              <h2 class="text-lg font-semibold text-gray-800">Residential Address</h2>
              <p class="text-sm text-gray-500">3 of 4 steps completed</p>
            </div>

            <!-- Progress bar -->
            <div class="flex gap-2 mb-5">
              <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
              <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
              <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
              <div class="h-3 flex-1 rounded-full bg-gray-200"></div>
            </div>
          </div>

          <!-- FORM -->
          <form @submit.prevent="handleSubmit">
            <div class="grid grid-cols-1 gap-5 mb-5">
              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Address 1 <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.address1"
                label=""
                name="address1"
                placeholder="Enter address line 1"
                :isRequired="true"
                :error="errors.address1"
                @update:error="errors.address1 = $event"
              />
              </div>

              <div>  
              <Textinput
                v-model="form.address2"
                label="Address 2"
                name="address2"
                placeholder="Enter address line 2"
                :error="errors.address2"
                @update:error="errors.address2 = $event"
              />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 mb-5">

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Place <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.place"
                label=""
                name="place"
                placeholder="Enter place"
                :isRequired="true"
                :error="errors.place"
                @update:error="errors.place = $event"
              />
              </div>

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               City <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.city"
                label=""
                name="city"
                placeholder="Enter city"
                :isRequired="true"
                :error="errors.city"
                @update:error="errors.city = $event"
              />
            </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-5">
              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Pincode <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.pincode"
                label=""
                name="pincode"
                placeholder="Enter pincode"
                type="number"
                :isRequired="true"
                :error="errors.pincode"
                @input="form.pincode = $event.target.value.slice(0, 6)"
                @update:error="errors.pincode = $event"
              />
              </div>

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Phone Number  <span  class="text-red-500">*</span> 
              </label> 

              <Textinput
                v-model="form.phone"
                label=""
                name="phone"
                placeholder="Enter phone number"
                type="number"
                :error="errors.phone"
                maxlength="10"
                @update:error="errors.phone = $event"
               
                @input="onPhoneInput"
              />
              </div>

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Mobile Number <span  class="text-red-500">*</span>
              </label> 

              <Textinput
                v-model="form.mobile"
                label=""
                name="mobile"
                placeholder="Enter mobile number"
                type="number"
                :isRequired="true"
                :error="errors.mobile"
                @update:error="errors.mobile = $event"
                @input="form.mobile = $event.target.value.slice(0, 10)"
              />
              </div>
            </div>

            <!-- Buttons -->
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                @click="goToCghsStep2"
                class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-400 rounded-lg px-6 py-3 text-sm font-medium transition"
              >
                Back
              </button>

              <button
                type="submit"
                class="bg-success-700 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition"
              >
                Save & Next
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Content End -->
  </div>
</template>

<script setup>
import { ref ,onMounted,watch} from "vue";
import { useRouter } from "vue-router";
import Textinput from "@/ui-components/Textinput.vue";
import { storeCardDetails } from "@/store/cghsCardAdd";
const setFormVal = storeCardDetails();

const router = useRouter();
const selected = ref("");

function isDigitValidated(val, no) {
  if (!val) return false;
  const digits = val.replace(/\D/g, ''); // Remove non-digit characters
  if (digits.length !== no) return false; // Check if length matches
  const regex = new RegExp(`^\\d{${no}}$`); // Create a dynamic regex
  return regex.test(digits); // Test if the string matches the regex
}

// Form state
const form = ref({
  address1: "",
  address2: "",
  place: "",
  city: "",
  pincode: "",
  phone: "",
  mobile: "",
});

// Error state
const errors = ref({
  address1: "",
  address2: "",
  place: "",
  city: "",
  pincode: "",
  phone: "",
  mobile: "",
});


function onPhoneInput(event) {
  let val = event.target.value;

  // Remove non-digit characters
  val = val.replace(/\D+/g, '');

  // Limit length
  if (val.length > 10) {
    val = val.slice(0, 10);
  }

  form.value.phone = val;
}

// Navigation
function goToCghsStep2() {
  selected.value = "cghsStep2";
 // router.push("add-cghs-card-office-dt");
  router.push({ name: 'AddNewCardRequestOfficeDt' });

}

function goToCghsStep4() {
  selected.value = "cghsStep4";
  //router.push("add-cghs-card-family-info");
  router.push({ name: 'AddNewCardRequestFamilyInfo' });
}

function hasNoSpaces(str) {
  return !/\s/.test(str); // \s matches any whitespace (space, tab, newline)
}

// Submit handler
function handleSubmit() {
  const regex = /^[a-zA-Z0-9\s.,&#\/\\-]+$/;
  let valid = true;

  if (!form.value.address1) {
    errors.value.address1 = "Address 1 is required";
    valid = false;
  }else if(!regex.test(form.value.address1)){
    errors.value.address1 = "Invalid characters should not be used in Address 1";
    valid = false;
  }

  if(form.value.address2 && !regex.test(form.value.address2)){
    errors.value.address2 = "Invalid characters should not be used in Address 2";
    valid = false;
  }else{
    errors.value.address2 ='';
  }

  if (!form.value.place) {
    errors.value.place = "Place is required";
    valid = false;
  }
  if (!form.value.city) {
    errors.value.city = "City is required";
    valid = false;
  }else if(hasNoSpaces(form.value.city)===false){
    errors.value.city = "City name should not have any space";
    valid = false;
  }
  if (!form.value.pincode) {
    if (!isDigitValidated(form.value.pincode,6)) {//PhoneNo VALIDATION
    errors.value.pincode = 'Pincode No. must be exactly 6 digits (numbers only)';
    valid = false;
    } else {
      errors.value.pincode = '';
    } 
  }
  const mo = form.value.mobile;
  if (!mo) {
    errors.value.mobile = "Mobile No. is required";
    valid = false;
  }
  if (mo) {
    if (mo.length < 10) {
      errors.value.mobile = "Mobile no must be 10 digits";
      valid = false;
    }
    // (optionally) ensure exactly numeric. But since onAadharInput strips non-digits, mostly numeric.
    else if (!/^\d{10}$/.test(mo)) {
      errors.value.mobile = "Mobile no. must contain only digits";
      valid = false;
    }
  }

  const ph = form.value.phone;
  if (ph) {
    if (ph.length < 10) {
      errors.value.phone = "Secondary phone must be 10 digits";
      valid = false;
    }
    // (optionally) ensure exactly numeric. But since onAadharInput strips non-digits, mostly numeric.
    else if (!/^\d{10}$/.test(ph)) {
      errors.value.phone = "Phone must contain only digits";
      valid = false;
    }
  }

  if (valid) {
     //setALlData to Pinia  
    console.log('addddd',form.value);
    setFormVal.setHomeAddress(form.value); 
    //setALlData to Pinia 
    goToCghsStep4();
  }
}

onMounted(async () => {  
  const piniaStoredData = setFormVal.apiDataStored; 
  console.log('dataaaa==',piniaStoredData?.address[0])
  const editedData = setFormVal.homeAddress_1;
  //getALlData from Pinia 
  //if(piniaStoredData?.address[0]){

// pinia stored DATA==home_address home_address2 home_city home_geo_location home_mobile home_phone  home_pincode  
    
  //    address1: "",
  // address2: "",
  // place: "",
  // city: "",
  // pincode: "",
  // phone: "",
  // mobile: "",
//console.log('edited data===',editedData);
    form.value.address1 = (editedData?.address1)?(editedData?.address1):(piniaStoredData?.address[0]?.home_address);
    form.value.address2 = (editedData?.address2)?(editedData?.address2):(piniaStoredData?.address[0]?.home_address2);
    form.value.place = (editedData?.place)?(editedData?.place):(piniaStoredData?.address[0]?.place);
    form.value.city = (editedData?.city)?(editedData?.city):(piniaStoredData?.address[0]?.home_city);
    form.value.pincode = (editedData?.pincode)?(editedData?.pincode):(piniaStoredData?.address[0]?.home_pincode);
    form.value.phone = (editedData?.phone)?(editedData?.phone):(piniaStoredData?.address[0]?.home_phone);
    form.value.mobile = (editedData?.mobile)?(editedData?.mobile):(piniaStoredData?.address[0]?.home_mobile);
       
 // }        
});


watch(() => form.value.address1, (val) => {
  if (val) errors.value.address1 = '';
});

watch(() => form.value.place, (val) => {
  if (val) errors.value.place = '';
});

watch(() => form.value.city, (val) => {
  if (val) errors.value.city = '';
});

watch(() => form.value.pincode, (val) => {
  if (isDigitValidated(val, 6)) errors.value.pincode = '';
});

watch(() => form.value.phone, (val) => {
  if (isDigitValidated(val, 10)) errors.value.phone = '';
});

watch(() => form.value.mobile, (val) => {
  if (isDigitValidated(val, 10)) errors.value.mobile = '';
});
</script>
