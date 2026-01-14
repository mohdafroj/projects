<template>
  <Loading v-if="isLoading" />
    <div class=" min-h-screen p-2 sm:p-4 md:p-0">
      <div class="space-y-6" v-if="requests.no_cards!=0">
       <div class="search">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by Request Id."
          class="mb-2 p-1 border border-blue-300 rounded"
        />
        </div>
        <div v-if="filteredRequests.length>0" v-for="(request, index) in filteredRequests" :key="index"  class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-4 relative"> 
        <!-- <div 
          v-for="(request, index) in requests"
          :key="index"
          class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-4 relative"
        > -->
        <!-- =={{ request }} -->
         <div class="hidden lg:flex justify-between relative"> 
          <!-- Desktop Layout (lg and above) -->
          <div class="">
          <div class=""> 
            <h3 class="text-lg font-semibold text-gray-900">{{request?.applicant_name??'-' }}</h3>

           &nbsp; <span v-if="request?.card_type" class="bg-gray-200 border-gray-400 text-gray-500 text-xs px-4 py-1 rounded-full capitalize">
                {{ request?.card_type??'' }}
              </span>
             
              <p class="text-sm text-gray-700 mt-2" v-if="request?.card_type && request?.card_type!='Member'">
                  Member: <span class="font-semibold">{{ request?.member_name??'' }}</span>
              </p>


            <p class="text-sm text-gray-500">
               {{request?.msa_cghs_card_request_no??'-'}}

                <span v-if="request?.state_name" class="text-sm text-gray-500">
                  - ({{ request?.state_name }})
                </span>                
            </p>

            
            <p class="text-sm text-gray-700 mt-2">
              <span class="font-semibold">Submitted:</span> {{ (request?.submitted) }}
            </p>
            <p class="text-sm text-gray-700">
              <span class="font-semibold">Type:</span> {{request?.request_type??'-'}}
            </p>
          </div>
          <div>
         
        </div>
        
        
          </div>

           <!----Comment section start-->
           <div class=" space-x-3 ml-4 mr-6">
            <div class="flex items-center justify-end text-right mb-4">
            <div class="border border-gray-400 text-gray-600 text-xs px-4 py-1 rounded-full capitalize mr-4">
              {{request?.card_type??'New Card Request' }}
            </div>
            <div class="bg-gray-200 border-gray-400 text-gray-500 text-xs px-4 py-1 rounded-full capitalize">
              {{request?.status??'Initiated'}}
            </div>
          </div>
          <div class="">
          <div class=" rounded-lg">
          <div class="flex justify-end items-start space-x-2">
            <div class="flex items-center bg-blue-100 p-2 rounded">
              <Icon icon="material-symbols:comment" class="w-4 h-4 text-blue-600" />
              <button type="button" @click="OpenRemarks(request?.msa_cghs_card_request_no)" 
                class="text-sm text-blue-600 hover:text-blue-800 ml-2">View All
                  Remark</button>  
            </div>
          </div>
          <div> 
          </div>
          <transition @before-enter="beforeEnter" @enter="enter" @leave="leave"
            >
            <div v-show='show[request?.msa_cghs_card_request_no?.replace("Member of Parliament, Request Id - ", "")]' ref="content" class="slide-content">
              <!-- Your remarks or comment content here -->
              <RemarkSlider :remarks='remk[request?.msa_cghs_card_request_no?.replace("Member of Parliament, Request Id - ", "")] || []'/>
            </div>
          </transition> 
          </div>
          </div>
          
          </div>
          <!--- Comment Section end-->
       
           <!-- Right: Top-aligned Badges -->
          
          <!-- Mobile/Tablet Layout (below lg) -->
          <div class="lg:hidden">
            <!-- Top Row: Name and Badges -->
            <div class="flex justify-between items-start mb-3">
              <h3 class="text-lg font-semibold text-gray-900 flex-1 pr-4">{{ request.name }}</h3>
              <div class="flex space-x-2 flex-shrink-0">
                <span class="border border-gray-300 text-gray-600 text-xs px-3 py-1 rounded-full whitespace-nowrap">
                  {{ request.requestType }}
                </span>
                <span class="bg-blue-50 text-blue-600 text-xs px-3 py-1 rounded-full whitespace-nowrap">
                  {{ request?.status??'Pending' }}
                </span>
              </div>
            </div>

            <!-- Member Info -->
            <div class="mb-4">
              <p class="text-sm text-gray-500 mb-2">
                Member of Parliament, {{ request.constituency }} to {{ request.startDate }}
              </p>
              <p class="text-sm text-gray-700 mb-1">
                <span class="font-semibold">Submitted:</span> {{ request.processedDate }}, {{ request.processedTime }}
              </p>
              <p class="text-sm text-gray-700">
                <span class="font-semibold">Type:</span> {{ request.type }}
              </p>
            </div>

            <!-- Comment Section - Full Width on Mobile -->
            <div class="bg-blue-50 rounded-lg p-3">
              <div class="flex items-start space-x-2">
                <div class="bg-blue-100 p-1 rounded flex-shrink-0">
                  <Icon icon="material-symbols:comment" class="w-4 h-4 text-blue-600" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700">Initiator Remark:</span>
                    <button class="text-xs text-blue-600 hover:text-blue-800 flex-shrink-0">View All Remark</button>
                  </div>
                  <p class="text-sm text-gray-600 break-words">{{ request.comment }}</p>
                </div>
              </div>
            </div>
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
   
    </div>
</template>

<script setup>
import { Icon } from '@iconify/vue';
import { Button } from '@sds/oneui-common-ui';
import { ref,onMounted,computed } from 'vue';
import VerifyRequestModal from './VerifyRequestModal.vue';
import VerifyAndForwardModal from './VerifyAndForwardModal.vue'; 
import {getAllCardsList,getProcessedCardsList,getAllRemarks} from '../../../src/services/rss/cghsServices.js';
import { dateFormated,timeFormated } from "@/utils/dateFormat";
import RemarkSlider from '@/views/ma-card-status-approve/RemarkSlider.vue';
const requests = ref([{}]);
const processedRequests = ref([{}]);
const showModal = ref(false);
const selectedRequest = ref(null);
const showVerificationModal = ref(false);
const selectedVerificationRequest = ref(null);
import { Loading } from '@sds/oneui-common-ui';
const isLoading = ref(true); 
//to get auth logs user name and permission//
import { useApiStore } from '@/store/apiData'; 
const apiData = useApiStore();
const rbac = computed(() => apiData.user?.rbac);
//console.log('RBAC Data KKK:', rbac.value);
//to get auth logs user name and permission//

//-----------search functionality-------------//
const searchQuery = ref('');
const filteredRequests = computed(() => {
  if (!searchQuery.value.trim()) {
    return requests.value;
  }
  const query = searchQuery.value.toLowerCase().trim();
  return requests.value.filter(request => {
    return (
      (request.member_name?.toLowerCase().includes(query)) ||
      (request.msa_cghs_card_request_no?.toLowerCase().includes(query))
    );
  });
});

//-----------search functionality-------------//


// Method to open the detail modal
const openModal = (request) => {
  selectedRequest.value = request;
  showModal.value = true;
};

// Method to open the verification modal
const openVerificationModal = (request) => {
  selectedVerificationRequest.value = request;
  showVerificationModal.value = true;
};

// Method to handle forward action from detail modal
const handleForward = (request) => {
  console.log('Forwarded to approver:', request);
  showModal.value = false;
};

// Method to handle verify and forward action
const handleVerifyAndForward = (data) => {
  console.log('Verified and forwarded:', data);
  showVerificationModal.value = false;
  // Update request status or handle the verification logic here
};

// Method to handle reject action
const handleReject = (data) => {
  console.log('Request rejected:', data);
  showVerificationModal.value = false;
  // Handle rejection logic here
};

const fetAllCards = async () =>{ 
 requests.value = ([]); // reset previous data
  try{
    const response = await getProcessedCardsList('I');
    requests.value = response.data||[]; 
   // console.log('DATA PROCCESSED=====', requests.value );
  }catch(err){
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('Error in listing', err.value)
  }finally {
    isLoading.value = false; 
  }
}

onMounted ( () => {
  fetAllCards();
}) 


const show = ref({})

const beforeEnter = (el) => {
  el.style.height = '0'
}
const enter = (el) => {
  el.style.height = el.scrollHeight + 'px'
  el.style.transition = 'height 0.3s ease'
  el.addEventListener(
    'transitionend',
    () => {
      el.style.height = 'auto'
    },
    { once: true }
  )
}
const leave = (el) => {
  el.style.height = el.scrollHeight + 'px'
  void el.offsetHeight // force repaint
  el.style.transition = 'height 0.3s ease'
  el.style.height = '0'
}


//remarks fetching
const OpenRemarks = async (reqid,rbac) =>{
  if (!reqid) return;

  try {
    // Parse the id part safely
    const parsedId = reqid.replace("Member of Parliament, Request Id - ", "")?.trim();
 
    if (!parsedId) {
      console.warn('Invalid reqid format:', reqid);
      return;
    }

    // Toggle visibility
    show.value[parsedId] = !show.value[parsedId];
    // show.value = !show.value;

    if (show.value[parsedId] && !remk.value[parsedId]) {
      // Fetch remarks only if opening
      await fetchRemarks(parsedId,rbac);
    } else {
      // Clear remarks if closing
      remk.value = [];
    }
  } catch (err) {
    console.error('Error in OpenRemarks:', err);
  }
}
const remk = ref({});
const fetchRemarks = async(reqid,rbac)=>{
  try{  
    const res = await getAllRemarks(reqid);
    remk.value[reqid] = res.data; 
    remk.value['permission'] = rbac.value;
   console.log('REMARKS getttt with auth-->>',remk.value);
  }catch(er){
    er.value = er.res?.data?.message || er.message||'Unknown';
    console.log('error', er.value);
  }  
}
</script>

<style scoped>
.slide-content {
  overflow: hidden;
} 
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