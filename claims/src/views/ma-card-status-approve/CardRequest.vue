<template>
  <!-- ===={{requests}} -->
  <Loading v-if="isLoading" />
  <div v-if="!isLoading" class="bg-gray-50 min-h-screen p-2 sm:p-4 lg:p-6 dark:bg-gray-900"> 
   
    <div class="space-y-4" v-if="requests!=0">
      <div class="search">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by Request Id."
          class="mb-2 p-1 border border-blue-300 rounded"
        />
        </div> 
       
      <div v-if="filteredRequests.length>0"
        v-for="(request, index) in filteredRequests"
        :key="index"
        class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-6 relative dark:bg-gray-800 dark:border-gray-700"
      >
        <!-- Desktop Layout (lg and above) -->
        <div class="hidden md:block">
          <div class="flex justify-between items-start mb-4">
            <!-- Left: Member Info -->
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ request?.applicant_name??'N/A' }}</h3>
              <p class="text-sm text-gray-500 mb-2 dark:text-gray-400">
                <p class="text-sm text-gray-700 mt-2" v-if="request?.card_type && request?.card_type!='Member'">
                  Member: <span class="font-semibold">{{ request?.member_name??'' }}</span>
              </p> 
                {{request.msa_cghs_card_request_no}} 
                 &nbsp; <span v-if="request?.card_type" class="bg-gray-200 border-gray-400 text-gray-500 text-xs px-4 py-1 rounded-full capitalize">
                {{ request?.card_type??'' }}
              </span>
              </p>
              <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                <p><span class="font-semibold">Submitted: </span> {{ (request?.submitted)??'' }}</p>
                <p><span class="font-semibold">Verified: </span>{{ (request?.submitted)??'' }}</p>
                <p><span class="font-semibold">Type:</span> {{ request?.request_type??'New Request' }}</p>
              </div>
            </div>

            <!-- Right: Badges and Comment Section -->
            <div class="ml-6 flex-shrink-0 min-w-[400px]">
              <!-- Top: Badges -->
              <div class="flex items-center justify-end space-x-2 mb-3">
                <span class="border border-gray-400 text-gray-700 text-xs px-3 py-1 rounded-full dark:border-gray-600 dark:text-gray-300">
                  {{ request?.request_type??'New Card Request' }}
                </span>
                <span class="bg-orange-100 border border-orange-200 text-orange-500 text-xs px-3 py-1 rounded-full dark:bg-orange-100 dark:border-orange-400 dark:text-orange-100">
                  {{ request?.status??'Initiated' }}
                </span>
              </div>
              
              <!-- Bottom: Comment Section -->
              <div class="rounded-lg">
                <div class="flex justify-end items-start space-x-2">
                  <div class="flex items-center bg-blue-100 p-2 rounded">
                    <Icon icon="material-symbols:comment" class="w-4 h-4 text-blue-600 dark:text-blue-300" />
                    <button type="button" @click="openModal_remark(request?.msa_cghs_card_request_no??'')" class="text-xs text-blue-600 hover:text-blue-800 ml-2">View All
                  Remark</button>  
                  </div>
                  
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-600 overflow-hidden text-ellipsis whitespace-nowrap w-[400px] block dark:text-gray-300">{{ request.comment }}</p>
                  </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center space-x-3">
            <Button
              label="Approve"
              icon="ic:baseline-check"
              size="sm"
              color="green"
              @click="openApprovalModal(request)"
            />
            <Button
              label="Send Back"
              icon="material-symbols-light:cancel-outline-rounded"
              size="sm"
              color="red"
              @click="openRejectModal(request)"
            /> 
            <Button
              label="View Full Details"
              icon="ic:outline-remove-red-eye"
              size="sm"
              color="gray-outline"
              @click="openModal(request?.msa_cghs_card_request_no??'' )"

            />
          </div>
        </div>

        <!-- Mobile/Tablet Layout (below lg) -->
        <div class="md:hidden">
          <!-- Top Row: Name and Badges -->
          <div class="flex justify-between items-start mb-3">
            <div class="flex-1 pr-4">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ request.name }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ request.constituency }} • Request #{{ index + 1 }}</p>
            </div>
          </div>

          <!-- Member Info -->
          <div class="mb-4 space-y-1 text-sm text-gray-700 dark:text-gray-300">
            <p><span class="font-semibold">Submitted: </span> {{ request.processedDate }}</p>
            <p><span class="font-semibold">Verified: </span> {{ request.processedDate }}</p>
            <p><span class="font-semibold">Type:</span> {{ request.type }}</p>
          </div>

          <!-- Comment Section -->
          <div class="bg-blue-50 rounded-lg p-3 mb-4 dark:bg-blue-900">
            <div class="flex items-start space-x-2">
              <div class="bg-blue-100 p-1 rounded flex-shrink-0 dark:bg-blue-800">
                <Icon icon="material-symbols:comment" class="w-4 h-4 text-blue-600 dark:text-blue-300" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-sm font-medium text-blue-700 dark:text-blue-200">Initiator Comment:</span>
                  <button class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex-shrink-0">View All Remark</button>
                </div>
                <p class="text-sm text-gray-600 break-words dark:text-gray-300">{{ request.comment }}</p>
              </div>
            </div>
          </div>

          <!-- Action Buttons - Stacked on Mobile -->
          <div class="space-y-2">
            <Button
              label="Approve"
              icon="material-symbols:check-circle"
              size="sm"
              color="green"
              class="w-full justify-center"
              @click="openApprovalModal(request)"
            />
            <Button
              label="Reject"
              icon="material-symbols:cancel"
              size="sm"
              color="red"
              class="w-full justify-center"              
              @click="openRejectModal(request)"
            />
            <Button
              label="View Full Details"
              icon="ic:outline-remove-red-eye"
              size="sm"
              color="gray-outline"
              class="w-full justify-center"
              @click="openModal(memid)"
            />
          </div>
        </div>
      </div> 
      <div v-else  class="text-red-900 bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-6 relative dark:bg-gray-800 dark:border-gray-700"
      >No Data Available</div>
    </div>
    <div class="space-y-4" v-else> 
         <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-4 relative"
        >
          <div class="hidden lg:flex items-center justify-between">
            No data available. 
          </div>
        </div>
      </div>

    <!-- Verify Request Modal -->
    <RequestModal
      v-model="showModal"
      :request="req"
      @close="showModal = false"
      @forward="handleForward"
      @listingUpdated="fetchApproverMemberCardRequest"
    />

    <RequestModalRemark 
      v-model="showModal_remark"
      :requests="remk"  
      @close="showModal_remark = false"
      @forward="handleRemark"
    />

    <!-- Verification Comments Modal -->
    <ApprovalModal
      v-model="showVerificationModal"
      :request="selectedVerificationRequest"
      @close="showVerificationModal = false"
      @verify="fetchApproverMemberCardRequest"
      :remarks="remk"
    />


    <RejectModal
      v-model="showVerificationModalReject"
      :request="selectedVerificationRequest"
      @close="showVerificationModalReject = false"
      @verify="handleVerifyAndReject"
    />
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue';
import { Button } from '@sds/oneui-common-ui';
import { ref,onMounted,defineEmits,computed } from 'vue';
import RequestModal from './RequestModal.vue';
import ApprovalModal from './ApprovalModal.vue';
import RejectModal from './RejectModal.vue';
import RequestModalRemark from './RequestModalRemark.vue';
import Swal from 'sweetalert2';
import { dateFormated,timeFormated,getMemberCghsCardRequest } from "@/utils/dateFormat";
// import ThankyouTemplate from './ThankyouTemplate.vue';
import { format } from 'date-fns';
import {getProcessedCardsList,getFamilyCardRequest,getAllRemarks,Approver_action } from '@/services/rss/cghsServices';
import { Loading } from '@sds/oneui-common-ui'; 
const isLoading = ref(true);
const requests  = ref([]);
const details = ref([{}]);
const showModal = ref(false);
const showModal_remark = ref(false);
const selectedRequest = ref(null);
const showVerificationModal = ref(false);
const showVerificationModalReject = ref(false);
const selectedVerificationRequest = ref(null); 
const emit = defineEmits(['new_cards_count']);

// Method to open the detail modal
const openModal = async(memid) => {
  isLoading.value = true
  const requestId =  memid.replace("Member of Parliament, Request Id - ", "")?.trim();
 // console.log('hhhhhdd', requestId); 
  await fetchFamilyDetails(requestId); 
  showModal.value = true;
};
const openModal_remark = async(reqid) => {
  isLoading.value=true;  
  await fetchRemarks(reqid);  
  showModal_remark.value = true;
};

const remk = ref([]);
const fetchRemarks = async(reqid)=>{
  try{ 
    const requestId =  reqid.replace("Member of Parliament, Request Id - ", "")?.trim(); 
   
     
    const res = await getAllRemarks(requestId);
    remk.value = res.data;
    remk.value['req_id'] = requestId
    //console.log('REMARKS-->>',remk.value);
  }catch(er){
    er.value = er.res?.data?.message || er.message||'Unknown';
    console.log('error', er.value);
  }finally{
    isLoading.value=false;
  }  
}


function closeModal_ramark() {
  showModal.value = false
}

const processedRequests = ref([]); 

// Method to open the approval modal
const openApprovalModal = (request) => {
  isLoading.value=true;
  selectedVerificationRequest.value = request;
  //console.log('xxxxx',selectedVerificationRequest.value);
  showVerificationModal.value = true;
  setTimeout(() => {
      isLoading.value=false;
    //setupSocket();
  }, 300);
};

// Method to handle approve action
const handleApprove = (request) => {
  openApprovalModal(request);
};

const openRejectModal = (request) => {
  isLoading.value=true;
  selectedVerificationRequest.value = request;
  showVerificationModalReject.value = true;
  setTimeout(() => {
      isLoading.value=false;
    //setupSocket();
  }, 300);
};
 
// Method to handle forward action from detail modal
const handleForward = (request) => {
  console.log('Forwarded to approver:', request);
  showModal.value = false;
};

const handleRemark = (request) => {
  console.log('remark:', request);
  showModal_remark.value = false;
};

// Method to handle verify and forward action
const handleVerifyAndForward = (data) => {
//  console.log('Verified and forwarded:', data);
  // Update request status to 'Approved'
  const requestIndex = processedRequests.value.findIndex(r => r === data.request);
  if (requestIndex !== -1) {
    processedRequests.value[requestIndex].status = 'Approved';
    processedRequests.value[requestIndex].comment = data.comments || processedRequests.value[requestIndex].comment;
  }
  showVerificationModal.value = true;
 fetchApproverMemberCardRequest();
};

const handleVerifyAndReject = (data) => { 
//  console.log('Verified and Reject:', data);
  // Update request status to 'Approved'
  const requestIndex = processedRequests.value.findIndex(r => r === data.request);
  if (requestIndex !== -1) {
    processedRequests.value[requestIndex].status = 'Approved';
    processedRequests.value[requestIndex].comment = data.comments || processedRequests.value[requestIndex].comment;
  }
  showVerificationModalReject.value = true;
  //console.log('ssssssssssskam') 
  fetchApproverMemberCardRequest();
};

import { useThemeSettingsStore } from '@/store/themeSettings';

// Get the theme store
const themeStore = useThemeSettingsStore();

// Simplified confirmReject method using Pinia store
const confirmReject = async (request) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: 'Are you sure to reject the card request?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626', // red-600
    cancelButtonColor: '#3b82f6',  // blue-500
    confirmButtonText: 'Yes, reject it!',
    cancelButtonText: 'Cancel',
    theme: themeStore.theme // Direct access to theme from store
  });

  if (result.isConfirmed) {
    // Update request status to 'Rejected'
    const requestIndex = processedRequests.value.findIndex(r => r === request);
    if (requestIndex !== -1) {
      processedRequests.value[requestIndex].status = 'Rejected';
    }
    
    await Swal.fire({
      title: 'Need Attention!',
      text: 'The card request has been send back.',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false,
      theme: themeStore.theme // Apply theme to success dialog too
    });
  }
};



//fetch approver listing data as new request pass I for new request
const fetchApproverMemberCardRequest = async () => {
  try {
      const response = await getProcessedCardsList('I');
      requests.value = response.data;     
      emit('new_cards_count', requests.value.length);
      
     // console.log('New Req approver',requests.value);
  } catch (err) {
      // Handle errors like network failure, invalid response, etc.
      err.value = err.response?.data?.message || err.message || 'Unknown error';
      console.log('error',err.value)
  }finally {
    isLoading.value = false; 
  }
}; //fetch approver listing data as new request


//get view details popup
const req = ref([{}]);
const fetchFamilyDetails = async(memid)=>{
  try{ 
    const res = await getFamilyCardRequest(memid);
    req.value = res.data
    //console.log('GGGGGGGGGGGGGGG',req.value);
  }catch(er){
    er.value = er.res?.data?.message || er.message||'Unknown';
    console.log('error', er.value);
  } 
  finally{
    isLoading.value=false;
  } 
}



// view details popup
onMounted ( () => { 
  
   fetchApproverMemberCardRequest(); 


  
})  

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
      (request.msa_cghs_card_request_no?.toLowerCase().includes(query))
    );
  });
});

//-----------search functionality-------------//
</script>

<style>
.swal2-backdrop-show
{
  @apply bg-gray-900/70;
  @apply backdrop-filter backdrop-blur-sm
}
/* .swal2-dark {
  background-color: #1f2937 !important;
  color: #f9fafb !important;
}

.swal2-dark .swal2-title {
  color: #f9fafb !important;
}

.swal2-dark .swal2-content {
  color: #d1d5db !important;
} */

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
