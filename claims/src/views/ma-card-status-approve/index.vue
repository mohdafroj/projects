<template>
    <div class="py-6 grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-4">    
    <DashboardCard
      :subTitle="ActiveCards"
      :amount="loadingA ? 'Loading...' :ActiveCardCount"      
      iconName="material-symbols-light:interactive-space-outline"
      iconColor="text-blue-500"
      iconBgColor="bg-blue-100"
       
    />      
    <DashboardCard
      :subTitle="PendingReview" 
      :amount="loadingP ? 'Loading...' : pendingReviewCount"     
      iconName="material-symbols-light:pending-actions-sharp"
      iconColor="text-orange-500"
      iconBgColor="bg-orange-100"       
    />
    <DashboardCard
      :subTitle="Verified"
      :amount="loadingV ? 'Loading...' :NeedAttention"
      iconName="material-symbols-light:verified-outline"
      iconColor="text-green-600"
      iconBgColor="bg-green-100"
    />    
    <DashboardCard 
      :subTitle="DeletionRequest"
      :amount="loadingD ? 'Loading...' :DeleteCardCount"      
      iconName="material-symbols-light:contract-delete-outline-rounded"
      iconColor="text-red-500"
      iconBgColor="bg-red-100"      
    />
  </div>

  <div class="py-1">
    <TabGroup :tabs="tabList" backgroundColor="bg-slate-200" maxWidth="max-w-lg">
      <!-- Override content for tab 1 -->
      <template #tab-content-0>
        <CardRequest v-if="showCardRequest"  @new_cards_count ="handleNewCardsCount"/>
         


         <div class="space-y-4" v-else> 
         <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 lg:p-4 relative"
        >
          <div class="hidden lg:flex items-center justify-between">
            No data available. 
          </div>
        </div>
      </div>


      </template>
      <template #tab-content-1>
        <CardProcessed />
      </template>
      <template #tab-content-2>
        <div class="p-4 bg-white rounded shadow">
          <AllUserList/>
        </div>
      </template>     
    </TabGroup>
  </div>
  <!-- <Card title="file upload"></Card> -->
<!-- <div class="top-4 hidden rounded-2xl"></div> -->
  </template>
  

<script setup>
import { ref,onMounted,computed,watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  Card, DashboardCard, TabGroup
} from '@sds/oneui-common-ui';
import CardRequest from './CardRequest.vue';
import CardProcessed from './CardProcessed.vue';
import AllUserList from '@/views/ma-card-status/AllUserList.vue';
import i18n from "@/i18n";
const t = i18n.global.t;

const NewRequest =ref();
const Processed =ref();
const AllRequest =ref();
// import CardRequestList from '../CardRequestList.vue';
// import CardProcessedList from './CardProcessedList.vue';
import { getMemberCardRequest,getCountCardByStatus } from '@/services/rss/cghsServices'; 
//import { getAllocations } from "@/services/rss/dashboardService";
const pendingReviewCount = ref(0);
const NeedAttention = ref(0);
const ActiveCardCount = ref(0);
const ProcessedCardCount = ref(0);
const DeleteCardCount = ref(0);
const processed_cards_by_approver=ref(0);

const loadingP = ref(true)
const loadingA = ref(true)
const loadingD = ref(true)
const loadingV = ref(true)

 const ActiveCards = ref();
  const PendingReview = ref();
  const Verified = ref();
  const DeletionRequest = ref();

const showCardRequest = computed(() => pendingReviewCount.value > 0);

const router = useRouter();

function goToRevenue() {
  router.push('/dashboard/revenue');
}

function goToUsers() {
  router.push('/dashboard/users');
}

const requestCount = ref(0);
const handleNewCardsCount = async(count) => { 
    console.log('Updated request count:', count);
    requestCount.value = count;
    await fetchCardCountByStatus();
};

const fetchMemberData=async()=>{ 
    const response = await getMemberCardRequest();
    return true;
  }
 
  const fetchCardCountByStatus= async ()=>{ 
    const requests = ref([])
  try{
    const  response = await getCountCardByStatus();
      requests.value= response.data  
    
    if(requests.value){
      //console.log('aaaa',requests.value);
      NeedAttention.value = requests.value?.rejected??0;
      pendingReviewCount.value = requests.value?.pending_cards??0;
      ActiveCardCount.value = requests.value?.active_cards??0; 
      ProcessedCardCount.value = requests.value?.processed_cards_by_initiator??0; 
      DeleteCardCount.value = requests.value?.expired_cards??0; 
      processed_cards_by_approver.value = requests.value?.processed_cards_by_approver??0; 
    }
    //console.log(requests.value['D'])
  }catch(err){
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('error in count',  err.value);
  }
  finally{
    loadingP.value = false;
    loadingA.value = false;
    loadingV.value = false;
    loadingD.value = false;
  } 
   
  }
  onMounted( async () =>{  
    const response = await fetchCardCountByStatus(); 
  })

// tab group
// const tabList =computed ( ()=> [
//   { title: 'New Request'+` (${pendingReviewCount.value})` },
//   { title: 'Processed'+` (${ProcessedCardCount.value})` },
//   { title: 'All Request', content: 'Settings content.' }
// ]);

 const tabList =computed(()=> [
    { title: `${NewRequest.value}  (${ProcessedCardCount.value})` },
    { title: `${Processed.value} (${processed_cards_by_approver.value})` },
    { title: AllRequest, content: 'Settings content.' }
  ]); 
// tab content 1


watch(
  [() => t('cghsCard.ActiveCards'),() => t('cghsCard.PendingReview'),  () => t('cghsCard.Rejected'),() => t('cghsCard.DeletionRequest'),() => t('cghsCard.NewRequest'),() => t('cghsCard.Processed'),() => t('cghsCard.AllRequest')],
  ([newActiveCards, newPendingReview,newVerified,newDeletionRequest,newNewRequest,newProcessed,newAllRequest]) => {
    ActiveCards.value = newActiveCards;
    PendingReview.value = newPendingReview;
    Verified.value = newVerified;
    DeletionRequest.value = newDeletionRequest;


    NewRequest.value = newNewRequest;
    Processed.value = newProcessed;
    AllRequest.value = newAllRequest;
  },
  { immediate: true }
);

</script>

