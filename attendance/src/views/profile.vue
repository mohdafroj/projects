<template>
  <div class="space-y-5">
    <!-- Profile Header Section -->
    <div class="profiel-wrap px-[35px] pb-10 md:pt-[84px] pt-10 rounded-lg bg-white dark:bg-slate-800 lg:flex lg:space-y-0 space-y-6 justify-between items-end relative z-[1]">
      <!-- <div class="bg-gray-900 dark:bg-slate-700 absolute left-0 top-0 md:h-1/2 h-[150px] w-full z-[-1] rounded-t-lg"></div>
       -->
       <div class="absolute left-0 top-0 md:h-1/2 h-[150px] w-full z-[-1] rounded-t-lg overflow-hidden">
    <img src="/assets/images/all-img/bg1.jpg" alt="Background Image" class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-black-800 opacity-80"></div>
  </div>
      <!-- Profile Info -->
      <div class="profile-box flex-none md:text-start text-center">
        <div class="md:flex items-end md:space-x-6">
          <div class="flex-none">
            <div class="md:h-[186px] md:w-[186px] h-[140px] w-[140px] md:ml-0 md:mr-0 ml-auto mr-auto md:mb-0 mb-4 rounded-full ring-4 ring-slate-100 relative">
              <img  :src="profileImage" alt="Profile Picture" class="w-full h-full object-cover rounded-full" />
             <!-- Hidden File Input -->
             <input
                type="file"
                ref="fileInput"
                accept="image/*"
                class="hidden"
                @change="handleImageChange"
              />

              <button @click="triggerFileInput" class="absolute right-2 h-8 w-8 bg-fuchsia-200 text-slate-600 rounded-full shadow-sm flex flex-col items-center justify-center md:top-[140px] top-[100px]">
                <Icon icon="heroicons:pencil-square" />
              </button>
            </div>
          </div>
          <div class="flex-1">
            <div class="text-2xl font-medium text-slate-900 dark:text-slate-200 mb-[3px]">
              Pushpendra Chauhan
            </div>
            <div class="text-sm font-light text-slate-600 dark:text-slate-400">
              System Division
            </div>
          </div>
        </div>
      </div>

      <!-- Employee Details -->
      <div class="profile-info-500 md:flex md:text-start text-center flex-1 max-w-[516px] md:space-y-0 space-y-4">
        <div class="flex-1">
          <div class="text-base font-semibold text-gray-600 dark:text-slate-300 mb-1">4000</div>
          <div class="text-sm text-slate-600 font-light dark:text-slate-300">Employee Code</div>
        </div>
        
        <div class="flex-1">
          <div class="text-base font-semibold text-gray-600 dark:text-slate-300 mb-1">Asdf</div>
          <div class="text-sm text-slate-600 font-light dark:text-slate-300">Designation</div>
        </div>
        
        <div class="flex-1">
          <div class="text-base font-semibold text-gray-600 dark:text-slate-300 mb-1">System Division</div>
          <div class="text-sm text-slate-600 font-light dark:text-slate-300">Division/Section</div>
        </div> 
      </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-12 gap-6">
      <div class="lg:col-span-5 col-span-12">
        <Card>
          <div class="flex justify-between">
            <div class="text-2xl ">My Info</div>
            <button @click="openBackdropModal" class=" h-10 w-10 bg-gray-700 text-gray-200 rounded-full shadow-sm flex flex-col items-center justify-center md:top-[140px] top-[100px]">
                <Icon icon="material-symbols:person-edit-outline-sharp" class="text-2xl" />
                
              </button>
          </div>
          <hr class="my-4">
          <ul class="list space-y-8">
            <li class="flex space-x-3">
              <div class="flex-none text-2xl text-slate-600 dark:text-slate-300 h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center">
                <Icon icon="heroicons:envelope" />
              </div>
              <div class="flex-1">
                <div class="uppercase text-xs text-slate-500 dark:text-slate-300 mb-1">EMAIL</div>
                <span class="text-base text-slate-600 dark:text-slate-50">pushpendra.chauhan@rajyasabha...</span>
              </div>
            </li>
            
            <li class="flex space-x-3">
              <div class="flex-none text-2xl text-slate-600 dark:text-slate-300 h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center">
                <Icon icon="heroicons:phone-arrow-up-right" />
              </div>
              <div class="flex-1">
                <div class="uppercase text-xs text-slate-500 dark:text-slate-300 mb-1">PHONE</div>
                <span class="text-base text-slate-600 dark:text-slate-50">+91-9811707353</span>
              </div>
            </li>

            <li class="flex space-x-3">
              <div class="flex-none text-2xl text-slate-600 dark:text-slate-300 h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center">
                <Icon icon="heroicons:map" />
              </div>
              <div class="flex-1">
                <div class="uppercase text-xs text-slate-500 dark:text-slate-300 mb-1">LOCATION</div>
                <div class="text-base text-slate-600 dark:text-slate-50">L-12, Parliament House, New Delhi 110001</div>
              </div>
            </li>
          </ul>
        </Card>
      </div>
      
      <div class="lg:col-span-7 col-span-12">
        <Card>
          <div class="flex ">
            <div class="text-2xl mb-2">Change Password</div>
            
          </div>
          <hr class="my-4">
          <form @submit.prevent="onSubmit" class="space-y-4">
              <Textinput
      label="Password"
      type="password"
      placeholder="************** "
      name="password"
      v-model="password"
      :error="passwordError"
      hasicon
      classInput="h-[48px]"
    />
    <Textinput
      label="New Password"
      type="New password"
      placeholder="  "
      name="password"
      v-model="password"
      :error="passwordError"
      hasicon
      classInput="h-[48px]"
    />
    <Button text="Change Password" type="submit" class="btn-primary float-right "/>
              </form>
        </Card>
      </div>
    </div>
    
  </div>
  <div class="p-6">
    <!-- Modal -->
  <Modal
  :isOpen="isBackdropModalOpen"
      title="Disabled Backdrop Modal"
      headerColor="bg-purple-700"
      size="md"
       :disableBackdropClose="true"
      @update:isOpen="isBackdropModalOpen = $event"
  >
    <template #body>
      <div class="overflow-y-auto w-[450px] px-4">
    <form @submit.prevent="onSubmit" class="space-y-4 ">
      <Textinput
        label="Email"
        type="email"
        placeholder="Type your email"
        name="email"
        v-model="email"
        :error="emailError"
        classInput="h-[48px]"
      />
      <Textinput
        label="Phone Number"
        type="number"
        placeholder=""
        name="phone"
        v-model="phone"
        :error="phoneError"
       
        classInput="h-[48px]"
      />
      
        <!-- <textarea name="a" class=" h-[100px] border-2 border-gray-300 rounded-md p-2" placeholder="Type your message here"></textarea> -->
      <Textarea
      label="Address"
      name="asd"
      placeholder="address"
      rows="3"
      
    />
  
    </form>
  </div>
    </template>

    <template #footer>
      <button
        class="px-4 py-2 bg-white border-[2px] border-purple-700 text-purple-600 rounded-md hover:bg-purple-700 hover:text-white"
        @click="closeBackdropModal"
      >
        Cancel
      </button>
      <button
        class="px-4 py-2 bg-purple-700 text-white rounded-md hover:bg-purple-600"
        @click="performAction"
      >
        Confirm
      </button>
    </template>
  </Modal>
  </div>


</template>

<script setup>
import { ref } from "vue";
import Card from "@/ui-components/Card.vue";
import Icon from "@/ui-components/Icon.vue";
import Modal from "@/ui-components/Modal.vue";
import Textinput from "@/ui-components/Textinput.vue";
import Button from "@/ui-components/Button.vue";
import Textarea from "@/ui-components/Textarea.vue";


// Modal 
const isBackdropModalOpen = ref(false);

// Open Modal
const openBackdropModal = () => {
  isBackdropModalOpen.value = true;
};

// Close Modal
const closeBackdropModal = () => {
  isBackdropModalOpen.value = false;
};

// Perform an action on confirmation
const performAction = () => {
  console.log("Profile Updated");
  closeBackdropModal();
};

const profileImage = ref("/assets/images/users/pushpendra.jpg"); 
const fileInput = ref(null);

// Trigger file input when the button is clicked
const triggerFileInput = () => {
  fileInput.value.click();
};

// Handle file input change to update the profile image
const handleImageChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();

    reader.onload = (e) => {
      profileImage.value = e.target.result; // Update the profile image
    };

    reader.readAsDataURL(file); // Read the file as a Data URL
  }
};
</script>
