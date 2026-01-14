<template>
  <div class="statusdiv mb-3">
    <Card class="mt-2"> 
      <Loading v-if="isLoading" />    
      <StatusTrack       
        title="Initiator Medical Claim"
        :stages="stages"
        @refresh="fetchStages"
        @viewHistoryDetails="handleHistoryDetails"        
      />
    </Card> 
  </div> 
  <form name="medilcaim_initiator_frm" @submit.prevent="handleSubmit">
  <div  :class="['grid', fullWidthCls, 'gap-4']">     
    <div :class="['leftbox', 'toggleDisplay',toggleDisplayCls]">
      <div>
        <NoteSection 
        @closeEditor="hadleLeftRightShowHide" 
        v-model:content="editorContent"
        :requests="notesListingData"
        :claimid="route.params.id"
        @refreshNotes="fetchNotes"
         />
      </div> 
    </div>
    <claim_listing_right 
    :requests="claimDetailsRes"
    :request="showHideSubmitBtn"
    @button-clicked="handleButtonClick" 
    v-model="form"
    @updateFldValue="handleFormValue" 
    />
  
  </div>
  <div :class="['mt-4', 'leftbox',toggleDisplayCls]">  
    <SplitPanel ref="letterPanelRef" 
  
    :initialLeftWidth="'70%'"
  :minRightWidth="100"
          v-model:showPanel="letterSplitPanel">
          <template #left="{ close }">
            <LetterTemplateEditor v-model="parentContent" />
          </template>
        </SplitPanel>
  </div>
  </form>
  <!-- <ResizablePanels /> -->
  <!-- split panel for letter add section -->

</template>

<script setup>
import { ref, computed, onMounted, watch,reactive  } from "vue";
import NoteSection from "./NoteSection.vue";
import { Card } from "@sds/oneui-common-ui";
import { StatusTrack } from "@sds/oneui-common-ui";
//import StatusTimeLine from "@/components/StatusTimeLine.vue";
import LetterTemplateEditor from './LetterTemplateEditor.vue';
import ResizablePanels from './ResizablePanels.vue';
import SplitPanel from './SplitPanel.vue';
import Swal from 'sweetalert2';
import Icon from "@/ui-components/Icon.vue";
import { useDetailStore } from "@/store/mediclaimDetail";
import { SelectInput } from "@sds/oneui-common-ui";
import { useRoute, useRouter } from 'vue-router';
// import { useI18n } from "vue-i18n";
//import { dateFormated } from '@/utils/dateFormat.js';
import claim_listing_right from "@/components/claim_listing_right.vue";
import { getMedicalClaimDetails,getNotesListing,initiatorSaveData } from '@/services/rss/medicalClaims';
import stagesData from "@/assets/stages.json";
import { Loading } from '@sds/oneui-common-ui';

//console.log("stagesData________________", stagesData);
const setAmt = useDetailStore();
const route = useRoute();
const selectedDepartment = ref(null);  // declare reactive ref
const letterSplitPanel = ref(true);
const minRightWidth = ref(0);
const stages = ref([]);
const isLoading = ref(false);
const error = ref(null);
const parentContent = ref('');

let fullWidthCls = ref("grid-cols-1");
let toggleDisplayCls = ref('hidden'); 
const showHideSubmitBtn = ref('');
function handleButtonClick() {
   fullWidthCls.value = 'grid-cols-2';
   toggleDisplayCls.value = 'block';
   showHideSubmitBtn.value = 'hidden'
//  console.log('Button clicked in child component!');
  // Your parent logic here
}

const form = reactive({
  enteredAmounts: {},
  selectedDepartment: ''
});



function hadleLeftRightShowHide() { 
  console.log('handle cross')
   fullWidthCls.value = 'grid-cols-1';
   toggleDisplayCls.value = 'hidden';
   showHideSubmitBtn.value = 'block'
  //console.log('Button clicked in note component!');
  // Your parent logic here
}


//----------------form submit function start ----------------//
const editorContent = ref("");
const getAdmissibleAmt=ref(0);
const claimDetailsRes = ref(null);
let postedClaimId = ref(0);
let notesListingData = ref({});


function handleFormValue(val){
getAdmissibleAmt.value = val;
console.log('hhhhhh', getAdmissibleAmt.value);
}
function validateForm() {
  //console.log('formdata==',editorContent.value);
  if (!editorContent.value) {
     
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Note must be filled!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
    return false
  }
  
  return true
}

const handleSubmit = async()=> {
  isLoading.value=true; 
  //set values from pinia//
  let admissibleAmt = setAmt.TotalAdmissibleAmt;
  let totalAmt = setAmt.TotalAmt;
  let ipd_amount = setAmt.ipd_amount;
  let opd_amount = setAmt.opd_amount;
  let test_investigation_amount = setAmt.test_investigation_amount;
  //set from pinia//

  //Creating post Data json
  const postData = {};
  const initiator1_dataObj = {}
  try{ 
    if (validateForm()) {    
      postData.claim_master_id = route.params.id;
      postData.remarks = editorContent.value;
      postData.pdf_editor = parentContent.value;
      postData.total_amount = totalAmt;
      postData.total_admissible_amount = admissibleAmt;
      postData.ipd_amount = ipd_amount;
      postData.opd_amount = opd_amount;
      postData.test_investigation_amount = test_investigation_amount; 


      // claimDetailsRes.value  admissible amount values
      postData.ipd_amount_adm = claimDetailsRes.value?.treatement_type_amount?.ipd_amount;
      postData.opd_amount_adm = claimDetailsRes.value?.treatement_type_amount?.opd_amount;
      postData.test_investigation_amount_adm = claimDetailsRes.value?.treatement_type_amount?.test_investigation_amount; 
      
      
      console.log('form data submitted==', postData);

       initiator1_dataObj.claim_master_id=route.params.id;
       initiator1_dataObj.initiator1_data=postData; 
      await verAndSaveDataInitiator(initiator1_dataObj); 
    } else {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Form validation failed",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
      console.log('Form validation failed.');
      isLoading.value=false; 
    }
  }catch(err){
    Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Failed to Save. Please try again",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
      isLoading.value=false; 
  }  
}

const verAndSaveDataInitiator = async (payload) => {
  try {
    const response = await initiatorSaveData(payload);
    //console.log('tryyy block', response)
    if (response.success_code === 200) {
     // showThankYouModal.value = true
      Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: "Claim submitted successfully!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    } else {      
      Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Error in!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
     isLoading.value=false;
     console.log('error in saving data',response);
    }
  } catch (err) {
    Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Failed to save data!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('Error!!', err.value)
  }finally {
    isLoading.value = false;
  } 
}
//-----------------form submit function end ---------------------//

const fetchStages = () => {
  isLoading.value = true;
  error.value = null;
  try {
    // Transform JSON object into array for StatusTrack components
    stages.value = Object.entries(stagesData).map(([name, details], index) => ({
      id: index + 1, // Add unique ID
      name,
      status: Array.isArray(details.status)
        ? "pending"
        : typeof details.status === "string"
        ? details.status.toLowerCase()
        : "pending", // Fallback to "pending" if undefined
      date: details.datetime,
      delay: details.delay,
    }));
  } catch (err) {
    error.value = err.message || "An error occurred while isLoading stages";
    stages.value = [];
  } finally {
    isLoading.value = false;
  }
};

const handleHistoryDetails = (event) => {
  console.log("handleHistoryDetails", event);
};


const notesList = ref([]);

const fetchNotes = async () => {
  // Fetch notes from your API or service
    ///for notes listing
    postedClaimId = route.params.id;
  const notesRes = await getNotesListing(postedClaimId);
  if (notesRes.success_code == 200) {
    notesListingData.value = notesRes.data;
    console.log('refresh DATA==', notesListingData.value);
  }
};

onMounted(async () => {
  postedClaimId = route.params.id;
  const response = await getMedicalClaimDetails(postedClaimId);
  if (response.isError == false && response.success_code == 200) {
    claimDetailsRes.value = response.data;
    console.log('details DATA==', claimDetailsRes.value);
  } 

  ///for notes listing
  const notesRes = await getNotesListing(postedClaimId);
  if (notesRes.success_code == 200) {
    notesListingData.value = notesRes.data;
    console.log('NOtes DATA==', notesListingData.value);
  } 
  ///for notes listing


})

// Fetch stages on mount
onMounted(() => {
  fetchStages();
});
</script>
 
