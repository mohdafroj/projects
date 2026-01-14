<template>
  <Loading v-if="isLoading" />
  <div v-if="memberData?.cghs_number != ''" class="w-[525px]">
    <div class="bg-white rounded-lg border-4 border-gray-300 shadow-2xl overflow-hidden ">
      <!-- Header -->
      <div class="relative h-[85px]">
        <div class="header-bg-dark" :style="{ background: isActiveCard ? '#6B1A1A' : '#585858' }"></div>
        <div class="header-banner flex items-center"
         :style="{ background: isActiveCard ? '#D32E2E' : '#848484' }">
          <div
            class="ml-6 w-14 h-14 rounded-md border-2 border-white bg-gray-100 shadow-md flex items-center justify-center text-white text-[10px] font-bold">
            <img src="/assets/images/logo/cghs_scheme.png" alt="Logo" class="w-14 h-14 object-contain" />
          </div>
          <div class="ml-4 flex-1 mr-4">
            <h1 class="text-white text-lg font-bold leading-tight">
              CENTRAL GOVERNMENT HEALTH SCHEMEd
            </h1>
          </div>
        </div>
      </div>
      
      <div class="p-4 flex gap-4">
        <!-- Photo -->
        <div class="flex-shrink-0">
          <div
            class="w-24 h-28 bg-gray-100 border-2 border-gray-300 rounded-md flex items-center justify-center text-gray-400 text-sm">
            <img src="/assets/images/users/user-m.jpg" alt="Profile" class="w-full h-full object-cover" />
          </div>
        </div>

        <div class="flex-1">
          <div class="mb-.5">
            <span class="text-teal-800 font-bold text-sm">NAME: </span>
            <span class="text-teal-800 font-semibold text-sm"> {{ memberData?.first_name }}{{ memberData?.middle_name }}
              {{ memberData?.last_name }}</span>

            <p><span class="text-teal-800 font-bold text-sm">DOB/GENDER: </span>
              <span class="text-teal-800 font-semibold text-sm"> {{ memberData?.dob }} / {{ memberData?.gender }}</span>
            </p>
            <p>
              <span class="text-teal-800 font-bold text-sm">CATEGORY: </span>
              <span class="text-teal-800 font-semibold text-sm"> {{ memberData?.sub_title }}</span>
            </p>
            <p>
              <span class="text-teal-800 font-bold text-sm">RELATION: </span>
              <span class="text-teal-800 font-semibold text-sm"> {{ memberData?.relation ?? 'Self' }}</span>
            </p>


          </div>


        </div>
        <!-- QR + Signature -->
        <div class="flex-shrink-0">
          <div v-if="memberData?.cghs_number != '' && memberData?.cghs_number != '9999999'">
            <div class="flex flex-col items-center justify-between ml-2">
              <img src="/assets/images/logo/active_card.png" alt="Active-Inactive" class="w-20 h-20 object-contain" />
              <div class="mt-2 text-center">
                <Icon icon="material-symbols-light:download-sharp" width="24" height="24"
                  class="w-20 h-10 object-contain cursor-pointer" @click="downloadCard(memberData?.cghs_number)" />
                <!-- <p class="text-xs">/Director</p> -->
              </div>
            </div>
          </div>
          <div v-else><img src="/assets/images/logo/pending_card.png" alt="Active-Inactive"
              class="w-20 h-20 object-contain" /></div>

        </div> 
        <!-- QR + Signature -->
      </div>
      <!-- Footer -->
      <div class="relative h-14 mt-4">
          <div class="footer-bg-dark" :style="{ background: isActiveCard ? '#6B1A1A' : '#585858' }"></div>
          <div
            class="footer-banner flex items-center justify-between px-4" :style="{ background: isActiveCard ? '#D32E2E' : '#848484' }"
          >
           <div class="flex items-center gap-8 text-white ">
              <span class="text-white text-lg font-bold italic" 
             ></span>
              <span v-if="memberData?.cghs_number != 9999999" >BEN ID : {{ memberData?.cghs_number ?? 'N/A' }} &nbsp;</span>

     
              <div class="flex gap-1.5">
                <div class="stripe-small"></div>
                <div class="stripe-small"></div>
                <div class="stripe-small"></div>
              </div>
            </div>
            <div class="text-white text-base font-medium"></div>
        </div> 
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, defineProps } from 'vue';
import { getDownloadCard } from '@/services/rss/cghsServices.js';
import { Icon } from "@iconify/vue";
import Swal from 'sweetalert2';
import { Loading } from '@sds/oneui-common-ui';
const isLoading = ref(true);
const props = defineProps({
  requests: {
    type: Object,
    default: () => ({})
  },
})

const memberData = computed(() => {
  isLoading.value = false;
  return props.requests.member;
});

const res = ref();

const downloadCard = async (memberId) => {
  isLoading.value = true;
  // memberId=20036750;
  try {
    const response = await getDownloadCard(memberId);
    //console.log('tttttttt',response)
    if (response.success_code == 200) {
      const base64String = response?.data
      console.log(base64String);
      // console.log('Type of blob:', typeof base64String);
      // console.log('Blob instance:', base64String instanceof Blob);
      const blob = base64ToBlob(base64String, 'application/pdf');
      downloadCardNow(blob, 'member-card.pdf');
      //console.log('iiiiiii', blob);
    } else {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: `Error in download card! ${response.error}`,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
  } catch (err) {
    Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: `Error in download card! ${err}`,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    //console.log('error in download==>', err)
  } finally {
    isLoading.value = false;
  }
}

function base64ToBlob(base64, mime) {
  // Remove data prefix if it exists (e.g., "data:application/pdf;base64,")
  const base64Data = base64.split(',').pop();
  const byteCharacters = atob(base64Data);
  const byteNumbers = new Array(byteCharacters.length);
  for (let i = 0; i < byteCharacters.length; i++) {
    byteNumbers[i] = byteCharacters.charCodeAt(i);
  }
  const byteArray = new Uint8Array(byteNumbers);
  return new Blob([byteArray], { type: mime });
}

const downloadCardNow = (blob) => {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = "cghs_card.pdf";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
}

const isActiveCard = computed(() => {
  const num = memberData.value?.cghs_number;
  return num && num !== '' && num !== '9999999';
});
</script>
<style scoped>
.header-bg-dark {
  position: absolute;
  inset: 0;
  background: #6b1a1a;
  clip-path: polygon(0 0, 60% 0, 50% 100%, 0 100%);
}

.header-banner {
  position: absolute;
  top: 12px;
  left: 0;
  right: 0;
  height: 64px;
  background: #d32e2e;
  clip-path: polygon(0 0, 98% 0, 90% 100%, 0 100%);
}

.qr-box-small {
  width: 96px;
  height: 96px;
  background: white;
  border: 4px solid black;
  display: flex;

}

.footer-bg-dark {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 140px;
  height: 57px;
  background: #6b1a1a;
  clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%);
}

.footer-banner {
  position: absolute;
  bottom: 0;
  left: 10px;
  right: 0;
  height: 50px;
  background: #d32e2e;
  clip-path: polygon(0 0, 100% 0, 100% 100%, 3% 100%);
}

.stripe-small {
  width: 8px;
  height: 50px;
  background: white;
  transform: skewX(-20deg);
}
</style>