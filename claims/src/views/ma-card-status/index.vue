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
      :amount="loadingP ? 'Loading...': pendingReviewCount"     
      iconName="material-symbols-light:pending-actions-sharp"
      iconColor="text-orange-500"
      iconBgColor="bg-orange-100" 
      
    />

    <DashboardCard
      :subTitle="Rejected"
      :amount="loadingV ? 'Loading...' : ProcessedCardCount"
      iconName="material-symbols-light:verified-outline"
      iconColor="text-green-600"
      iconBgColor="bg-green-100"
    />
    
    <DashboardCard 
      :subTitle = "DeletionRequest"
      :amount="loadingD ? 'Loading...' : DeleteCardCount"
      
      iconName="material-symbols-light:contract-delete-outline-rounded"
      iconColor="text-red-500"
      iconBgColor="bg-red-100" 
    />
  </div>

  <div class="py-1">
    <TabGroup :tabs="tabList" backgroundColor="bg-slate-200" maxWidth="max-w-lg">
      <!-- Override content for tab 1 -->
      <template #tab-content-0>
        <CardRequestList  @new_cards_count ="handleNewCardsCount"/>
      </template>
      <template #tab-content-1>
        <CardProcessedList />
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
  import { ref, onMounted,computed,watch   } from 'vue';
  import { useRouter } from 'vue-router'; 
  import {Card, DashboardCard, TabGroup} from '@sds/oneui-common-ui';
  import CardRequestList from './CardRequestList.vue';
  import CardProcessedList from './CardProcessedList.vue';
  import AllUserList from './AllUserList.vue';
  import i18n from "@/i18n";
  const t = i18n.global.t;
  import { getMemberCardRequest,getCountCardByStatus } from '@/services/rss/cghsServices'; 
//import { getAllocations } from "@/services/rss/dashboardService";
  const pendingReviewCount = ref(0);
  const ActiveCardCount = ref(0);
  const ProcessedCardCount = ref(0);
  const DeleteCardCount = ref(0);
  const loadingP = ref(true)
  const loadingA = ref(true)
  const loadingD = ref(true)
  const loadingV = ref(true)
  const router = useRouter(); 
  const initiatorCnt = ref(0);  
  const requestCount = ref(0);

  const ActiveCards = ref();
  const PendingReview = ref();
  const Rejected = ref();
  const DeletionRequest = ref();

const handleNewCardsCount = async (count) => { 
    console.log('Updated request count:', count);
    requestCount.value = count;
    // Refresh all counts again
    await fetchCardCountByStatus();
};

  // const fetchMemberData=async()=>{
  // // const options = {};  
  //   await getMemberCardRequest();
  //   return true;
  // }

  const fetchCardCountByStatus= async ()=>{ 
    const requests = ref([])
    try{
      const  response = await getCountCardByStatus();
      requests.value= response.data 
      //console.log('COUNTSSSS==',requests.value)
      if(requests.value){
        pendingReviewCount.value = requests.value?.pending_cards??0; 
        ActiveCardCount.value = requests.value?.active_cards??0; 
        ProcessedCardCount.value = requests.value?.rejected??0; 
        DeleteCardCount.value = requests.value?.expired_cards??0; 
        initiatorCnt.value = requests.value?.processed_cards_by_initiator??0; 
      } 
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
  const NewRequest =ref();
  const Processed =ref();
  const AllRequest =ref();
  // tab group
  const tabList =computed(()=> [
    { title: `${NewRequest.value}  (${requestCount.value})` },
    { title: `${Processed.value} (${initiatorCnt.value})` },
    { title: AllRequest, content: 'Settings content.' }
  ]);  
watch(
  [() => t('cghsCard.ActiveCards'),() => t('cghsCard.PendingReview'),  () => t('cghsCard.Rejected'),() => t('cghsCard.DeletionRequest'),() => t('cghsCard.NewRequest'),() => t('cghsCard.Processed'),() => t('cghsCard.AllRequest')],
  ([newActiveCards, newPendingReview,newVerified,newDeletionRequest,newNewRequest,newProcessed,newAllRequest]) => {
    ActiveCards.value = newActiveCards;
    PendingReview.value = newPendingReview;
    Rejected.value = newVerified;
    DeletionRequest.value = newDeletionRequest;


    NewRequest.value = newNewRequest;
    Processed.value = newProcessed;
    AllRequest.value = newAllRequest;
  },
  { immediate: true }
);
</script>

