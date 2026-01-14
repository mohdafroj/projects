<template>
  <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
    <!-- Content Start -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                  <h2 class="text-lg font-medium text-gray-800 dark:text-white">
                   Add New CGHS Card
                  </h2>
                </div>
                <div class="p-4 sm:p-6 dark:border-gray-800">
                  <form>
                    <div class="grid grid-cols-1   sm:grid-cols-1">
                                         
                      <!-- Selected User -->
                      <div class=" flex items-center gap-3 p-3 border rounded-xl">
                        <!-- <img src="https://i.pravatar.cc/100?img=1" class="w-12 h-12 rounded-full" /> -->
                        <div>
                          <p class="font-semibold"> 
                             {{(setFormVal.srchName.username)?setFormVal.srchName.username:''}}
                          </p>
                          <!-- <p class="text-sm text-gray-600">UI/UX Designer</p> -->
                        </div>
                      </div>
                                            
                                          </div>
                  </form>
                </div>
              </div>
      <!-- Card Header -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
          <h2 class="text-lg font-medium text-gray-800 dark:text-white">
            CGHS Card Application Form
          </h2>
        </div>

        <div class="p-4 sm:p-6 dark:border-gray-800">
          <!-- Progress -->
          <div class="mx-auto mb-5">
            <div class="mb-3">
              <h2 class="text-lg font-semibold text-gray-800">Official Address</h2>
              <p class="text-sm text-gray-500">2 of 4 steps completed</p>
            </div>
            <div class="flex gap-2">
              <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
              <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
              <div class="h-3 flex-1 rounded-full bg-gray-200"></div>
              <div class="h-3 flex-1 rounded-full bg-gray-200"></div>
            </div>
          </div>

          <!-- Form -->
          <form @submit.prevent="handleSubmit">
            <div class="grid grid-cols-1 gap-5 mb-5">
              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Address Line 1 <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.address1"
                label=""
                placeholder="Enter Address 1"
                name="address1"
                :isRequired="true"
                :error="errors.address1"
                @update:error="errors.address1 = $event"
              />
              </div>

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Address Line 2 <span  class="text-red-500">*</span>
              </label> 
              
              <Textinput
                label=""
                placeholder="Enter Address 2"
                v-model="form.address2" 
                :isRequired="true"
                :error="errors.address2"
                @update:error="errors.address2 = $event"
              />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-5">

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Phone Number <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.phone"
                label=""
                placeholder="Enter phone number"
                name="phone" 
                isRequired
                type="number"
                maxlength=10             
                :error="errors.phone"
                @input="form.phone = $event.target.value.slice(0, 10)"
                @update:error="errors.phone = $event"
              />
              </div>

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               E-mail Address <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.email"
                label=""
                placeholder="Enter email"
                name="Email" 
                :isRequired="true"
                :error="errors.email"
                @update:error="errors.email = $event"
              />
              </div>

              <div>
                <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Pincode <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                v-model="form.pincode"
                label=""
                placeholder="Enter pincode"
                name="pincode"
                maxlength=6                
                :error="errors.pincode" 
                isRequired  
                @input="form.pincode = $event.target.value.slice(0, 6)"
                @update:error="errors.pincode = $event"
              />
              </div>
            </div>

            <!-- Buttons -->
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                @click="goToCghsStep1"
                class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-400 rounded-lg px-6 py-3 text-sm font-medium transition"
              >
                Back
              </button>

              <button
                type="submit"
                class="bg-success-700 hover:bg-brand-600 shadow-theme-xs inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition"
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
import { ref,onMounted ,watch} from "vue";
import { useRouter } from "vue-router";
import Textinput from "@/ui-components/Textinput.vue"; // adjust path if needed
import { storeCardDetails } from "@/store/cghsCardAdd";
const setFormVal = storeCardDetails();

const router = useRouter();

const form = ref({
  address1: "",
  address2: "",
  phone: "",
  email: "",
  pincode: "",
});

const errors = ref({
  address1: "",
  address2: "",
  phone: "",
  email: "",
  pincode: "",
});



function isDigitValidated(val, no) {
  if (!val) return false;
  const digits = val.replace(/\D/g, ''); // Remove non-digit characters
  if (digits.length !== no) return false; // Check if length matches
  const regex = new RegExp(`^\\d{${no}}$`); // Create a dynamic regex
  return regex.test(digits); // Test if the string matches the regex
}

function goToCghsStep1() {
  //router.push("add-cghs-card");
  router.push({ name: 'AddNewCardRequest' });
}

function goToCghsStep3() {
  //router.push("add-cghs-card-residence-add");
  router.push({ name: 'AddNewCardRequestResidenceAdd' });

}


function handleSubmit() {
  let valid = true;
  const regex = /^[a-zA-Z0-9\s.,&#\/\\-]+$/;

  // Simple required field validation
  Object.keys(form.value).forEach((key) => {
    if (!form.value[key]) {
      errors.value[key] = `${key} is required`;
      valid = false;
    }
  });
 

  if(form.value.address1 && !regex.test(form.value.address1)){
    errors.value.address1 = "Invalid special characters should not be used in Address 1";
    valid = false;
  }

  if(form.value.address2 && !regex.test(form.value.address2)){
    errors.value.address2 = "Invalid special characters should not be used in Address 2";
    valid = false;
  }
  
  if (!isDigitValidated(form.value.phone,10)) {//PhoneNo VALIDATION
    errors.value.phone = 'Phone No. must not be exeeds 10 digits (numbers only)';
    valid = false;
  } else {
    errors.value.phone = ''; 
  }

  if (!form.value.email) {
    errors.value.email = 'Email ID is required';
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    errors.value.email = 'Please enter a valid email address';
    valid = false;
  }else{
    errors.value.email = '';
  }

  if (!isDigitValidated(form.value.pincode,6)) {//PhoneNo VALIDATION
    errors.value.pincode = 'Pincode No. must be exactly 6 digits (numbers only)';
    valid = false;
  } else {
    errors.value.pincode = '';
  }


  if (valid) { 
    //setALlData to Pinia  
    //console.log('addddd',form.value);
     setFormVal.setOfficeAddress(form.value); 
    //setALlData to Pinia 
    goToCghsStep3();
  }
}
function removeSpecialChars(str) {
  if(str){
    return str.replace(/[^a-zA-Z0-9 ]/g, "");
  }
}
onMounted(async () => {  
  const piniaStoredData = setFormVal.apiDataStored; 

  const editedData = setFormVal.officeAddress_1;
  //getALlData from Pinia   
  //console.log('kamal======',piniaStoredData);
  //if(piniaStoredData?.address[0]){
    form.value.address1 = editedData?.address1?editedData.address1:(piniaStoredData?.address[0]?.present_address);
    form.value.address2 = editedData?.address2?editedData.address2:(piniaStoredData?.address[0]?.present_address2);
    form.value.phone = editedData?.phone?editedData.phone:(removeSpecialChars(piniaStoredData?.address[0]?.present_phone));
    form.value.email = editedData?.email?editedData.email:(piniaStoredData?.address[0]?.email);
    form.value.pincode = editedData?.pincode?editedData.pincode:(piniaStoredData?.address[0]?.present_pincode);     
  //}        
});


//================for vremoving valodation after filling the fiels============//
 

Object.keys(form.value).forEach((key) => {
  watch(() => form.value[key], (newVal) => {
    if (newVal) {
      // Special validation for phone and pincode
      if (key === "phone" && !isDigitValidated(newVal, 10)) return;
      if (key === "pincode" && !isDigitValidated(newVal, 6)) return;

      errors.value[key] = "";
    }
  });
});

//================for vremoving valodation after filling the fiels============//
</script>
