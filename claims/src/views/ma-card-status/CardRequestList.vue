<template> 
  <Loading v-if="isLoading" />
  <!-- ====>>>>{{requests}} -->
  <div v-if="!isLoading" class="min-h-screen p-2 sm:p-4 md:p-0">
   
    <div class=" min-h-screen p-2 sm:p-4 md:p-0">
      
      <div class="space-y-4" v-if="requests&&requests.length > 0">
        <div class="search">
          <button @click="goToCGHSForm" class="mb-2 p-1 border-grey-300 rounded"><span class="text-gray-800 dark:text-slate-300 text-sm">+ &nbsp;Add New</span></button>
        &nbsp;&nbsp;&nbsp;
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="t('cghsCard.SrchReq')"
          class="mb-2 p-1 border border-blue-300 rounded"
        />
        </div>
  
        <div v-if="filteredRequests.length>0" v-for="(request, index) in filteredRequests" :key="index"
          class="bg-white rounded-xl shadow border p-3 sm:p-4 lg:p-6 relative">
          <!-- Desktop Layout (lg and above) -->
          <div class="hidden lg:flex justify-between relative">
            <!-- Left: MP Info -->
            <div class="flex-1 self-start">
              <h3 class="text-lg font-semibold text-gray-900">{{ request?.applicant_name??'N/A' }}
                REQ:#({{ request?.cghs_card_number ?? '-' }})</h3>
               &nbsp; 
               <span v-if="request?.card_details?.card_type" class="bg-gray-200 border-gray-400 text-gray-500 text-xs px-4 py-1 rounded-full capitalize">
                {{ request?.card_details?.card_type??'' }}
              </span>
             
              <p class="text-sm text-gray-700 mt-2" v-if="request?.card_details?.card_type && request?.card_details?.card_type!='Member'">
                  Member: <span class="font-semibold">{{ request?.member_name??'' }}</span>
              </p> 
              <p class="text-sm text-gray-500">
                {{ request?.member_term_detail?.member_terms_date ?? '' }}
              </p> 

              <p class="text-sm text-gray-700 mt-2">
                <span class="font-semibold">Submitted:</span> {{ (request?.created_at) ?? '' }}
              </p>
              <p class="text-sm text-gray-700">
                <span class="font-semibold">Type:</span> {{ request?.card_type }}
              </p>
            </div>

            <!-- Center: Buttons (VERTICALLY CENTERED) -->
            <div class="flex items-center space-x-3 ml-4 mr-6">
              <Button :label="t('cghsCard.ViewDetail')" icon="ic:outline-remove-red-eye" size="sm" color="green-outline"
                @click="() => { openModal(request.cghs_card_number); }" />
              <Button label="Verify and Forward" icon="ri:send-plane-fill" size="sm" color="green"
                @click="() => { openVerificationModal(request?.cghs_card_number??'',request?.created_at??''); }" />
            </div>

            <!-- Right: Top-aligned Badges -->
            <div class="flex items-start space-x-1 self-start">
              <span class="border border-gray-400 text-gray-600 text-xs px-4 py-1 rounded-full capitalize">
                {{ request?.card_type ?? 'New Card Request' }}
              </span>
              <span class="bg-gray-200 border-gray-400 text-gray-500 text-xs px-4 py-1 rounded-full capitalize">
                {{ request?.status ?? 'Pending' }}
              </span>
            </div>
          </div>

          <!-- Mobile/Tablet Layout (below lg) -->
          <div class="lg:hidden">
            <!-- Top Row: Name and Badges -->
            <div class="flex justify-between items-start mb-3">
              <h3 class="text-lg font-semibold text-gray-900 flex-1 pr-4">{{ request.name }}</h3>
              <div class="flex space-x-2 flex-shrink-0">
                <span
                  class="border border-gray-400 text-gray-600 text-xs px-3 py-1 rounded-full capitalize whitespace-nowrap">
                  {{ request.requestType }}
                </span>
                <span
                  class="bg-orange-100 border-orange-400 text-orange-500 text-xs px-3 py-1 rounded-full capitalize whitespace-nowrap">
                  {{ request.status }}
                </span>
              </div>
            </div>

            <!-- Member Info -->
            <div class="mb-4">
              <p class="text-sm text-gray-500 mb-2">
                Member of Parliament, {{ request.constituency }} to {{ request.startDate }}
              </p>
              <p class="text-sm text-gray-700 mb-1">
                <span class="font-semibold">Submitted:</span> {{ request.submittedDate }}, {{ request.submittedTime }}
              </p>
              <p class="text-sm text-gray-700">
                <span class="font-semibold">Type:</span> {{ request.type }}
              </p>
            </div>

            <!-- Action Buttons - Full Width -->
            <div class="space-y-2">
              <Button label="View Detail" icon="ic:outline-remove-red-eye" size="sm" color="green-outline"
                class="w-full justify-center" @click="openModal(request?.member_id)" />
              <Button label="Verify and Forward" icon="ri:send-plane-fill" size="sm" color="green"
                class="w-full justify-center" @click="openVerificationModal(request?.member_id??'',request?.created_at??'')" />
            </div>
          </div>
        </div>
         <div v-else  class="text-red-900 bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-6 relative dark:bg-gray-800 dark:border-gray-700"
      >No Data Available</div>
      </div>
      <div class="space-y-4" v-else>
        <div class="search">
        <button @click="goToCGHSForm" class="mb-2 p-1 border-grey-300 rounded"><span class="text-gray-800 dark:text-slate-300 text-sm">+ &nbsp;Add New</span></button>&nbsp;&nbsp;</div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-4 relative">
          <div class="hidden lg:flex items-center justify-between">
            No data available.
          </div>
        </div>
      </div>
      <!-- Verify Request Modal -->
      <VerifyRequestModal v-model="showModal" :requests="requestsDt" @close="showModal = false"
        @forward="handleForward" />

      <!-- Verification Comments Modal -->
      <VerifyAndForwardModal v-model="showVerificationModal" @close="showVerificationModal = false"
        @verify="handleVerifyAndForward" @reject="handleReject" :requests="requestsFrwd" />

    </div>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue';
import { Button } from '@sds/oneui-common-ui';
import { ref, onMounted, nextTick,defineEmits,computed } from 'vue';
import VerifyRequestModal from './VerifyRequestModal.vue';
import VerifyAndForwardModal from './VerifyAndForwardModal.vue'; 
import { dateFormated, timeFormated, getMemberCghsCardRequest } from "@/utils/dateFormat";
import { format } from 'date-fns';
import { Loading } from '@sds/oneui-common-ui';
import i18n from "@/i18n";
const t = i18n.global.t;
import Swal from 'sweetalert2'  
import { getMemberCardRequest, getFamilyCardRequest, verifyAndForwardToApprover } from '@/services/rss/cghsServices';

const isLoading = ref(true);
const requests = ref([]);
const memberId = ref(30);
const showModal = ref(false);
const selectedRequest = ref(null);
const showVerificationModal = ref(false);
const selectedVerificationRequest = ref(null);
const isModalOpen = ref(false)
const new_card_request = 'New Card Request';
import { useRouter } from 'vue-router';
const router = useRouter();

// Method to open the detail modal
const openModal = async (id) => { 
  isLoading.value=false;  
   if(!id){
        Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Cghs Card No. is missging, To open the data card No. must be provided!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
   }else{
    isLoading.value=true;
    await fetchFamilyCardRequest(id);
    showModal.value = true;
   }  
};
const requestsDt = ref([])
//fetch member and family details
const fetchFamilyCardRequest = async (memberid) => {
  // reset previous data
  try {
    const response = await getFamilyCardRequest(memberid);
    
    if(response.success_code===200){
      requestsDt.value = response.data || [];
    }else{ 
       Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: `Error! ${response?.description}`,
      showConfirmButton: false,
      timer: 6000,
      timerProgressBar: true,
    });
    }
   
  } catch (err) {
    err.value = err.response?.data?.message || err.message || 'Unknown error';
  }finally {
    isLoading.value = false;
  }
}//fetch member and family details

// Method to open the verification modal
const openVerificationModal = async (id,submitDate) => {
  isLoading.value=true
  memberId.value = id;
  console.log('verifycation model open');
  await verifyAndForwardCard(id,submitDate);
  showVerificationModal.value = true;
};
const requestsFrwd = ref([]);
//member card verify and forward
const verifyAndForwardCard = async (memberid,submitDt) => {
  // reset previous data
  try {
    const response = await verifyAndForwardToApprover(memberid);
    requestsFrwd.value = response.data || '[]';
    requestsFrwd.value.submit_date = submitDt
    console.log('verifyData=====', requestsFrwd.value);
  } catch (err) {
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('Error=====', err.value);
  }finally {
    isLoading.value = false;
  }
}//member card verify and forward

// Method to handle forward action from detail modal
const handleForward = (request) => {
  console.log('Forwarded to approver:', request);
  showModal.value = false;
};

// Method to handle verify and forward action
const handleVerifyAndForward = async (data) => {
  console.log('Verified and forwarded:', data);
  showVerificationModal.value = false;
  // Update request status or handle the verification logic here
 
  // Wait 1 second then refresh the request list
  setTimeout(() => {
    fetchMemberCardRequest();
  }, 1000); // 1000ms = 1 second
};

// Method to handle reject action
const handleReject = (data) => {
  console.log('Request rejected:', data);
  showVerificationModal.value = false;
  // Handle rejection logic here
};
const emit = defineEmits(['new_cards_count']);
const cards_count = ref(0);
const fetchMemberCardRequest = async () => {
  try {
    const response = await getMemberCardRequest();
    requests.value = response.data;
    cards_count.value = requests.value.length;
    emit('new_cards_count', cards_count.value);
    //console.log('cards==',cards_count.value);
  } catch (err) {
    // Handle errors like network failure, invalid response, etc.
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('error', err.value)
  }finally {
    isLoading.value = false; 
  }
};

function goToCGHSForm() {
  window.location.href = '/rssms/add-cghs-card'; // replace with your actual internal route path
}
//-----------search functionality-------------//
const searchQuery = ref('');
const filteredRequests = computed(() => {
  if (!searchQuery.value.trim()) {
    return requests.value;
  }
  //console.log('ssssssss',requests.value);
  const query = searchQuery.value.toLowerCase().trim();
  return requests.value.filter(request => {
    return (
      (request.member_name?.toLowerCase().includes(query)) ||
      (request.cghs_card_number?.toLowerCase().includes(query))
    );
  });
});

//-----------search functionality-------------//
onMounted(() => {
  fetchMemberCardRequest(); 
})  
</script>

<style setup>
.search {
    float: right;
    margin-top: -50px;
}
.search input {
    background: #f1f5f9;
    border: none;
    border-bottom: 1px solid #afaeae;
    border-radius: 0px;
}
</style>