<template>
  <div ref="scrollToSection">
  <Loading v-if="isLoading" />
  <div v-if="approveData.media_id > 0" class="p-6 bg-white min-h-screen text-center">
    <!-- Header Section -->
    <div class="flex items-center justify-center h-full">
        <img :src="esignLoader" alt="" class="watch-sign w-50 h-36" />
    </div>
    <p v-if="approveData.txn" class="font-bold text-xl">Claim {{ approveData.label }} Reference Transaction No</p>
    <p class="font-bold text-xl text-red-500">{{ approveData.txn ? lastFourChar(approveData.txn) : '' }}</p>
    <p class="text-align-center font-bold mb-4 mt-4">A notification has been sent to your linked phone number.
        <br />Please open your authenticator app and authenticate using 
        <br />your biometric to digitally sign the document.
    </p>
    
    <p class="text-sm text-gray-500 text-center mb-4">
      <span v-if="countdown > 0">Resend</span>
      <span v-else @click="() => handleSendNotification()" class="text-blue-500 cursor-pointer hover:underline">Resend</span>
      {{ String(Math.floor(countdown / 60)).padStart(2, '0') }}:{{ String(countdown %
        60).padStart(2, '0') }} Min
    </p>

    <div class="flex items-center justify-center h-full">
        <Button @click="() => handleBack()" label="Back" color="gray-outline" size="sm" />
    </div>
  </div>

  <div v-else>
    <div v-if="historicalData" class="py-3 mb-3">
      <Card class="text-center text-2xl font-semibold text-cyan-800">Historical Data</Card>
    </div>  
    <StatusTrack v-else 
      :title="progressbarTitle"
      :successLabel="'Success'"
      :stages="stages" 
      :refreshLabel="t('refresh')"
      :historyLabel="t('status_history')" 
      @refresh="handleRefresh" 
      @viewHistoryDetails="handleHistoryDetails" />
    <ResizablePanels />
    <!-- split panel for letter add section -->
    <!-- <div class="mt-4">
      <SplitPanel ref="letterPanelRef" :initialLeftWidth="'100%'" :minRightWidth=minRightWidth
        v-model:showPanel="letterSplitPanel">
        <template #left="{ close }">
          <LetterTemplateEditor />
        </template>
      </SplitPanel>
      <TemplateHistory />
    </div> -->
    <div class="hidden bg-blue-800"></div>
    <div class="flex justify-end gap-4 py-3 rounded-b">
      <div v-if="userType == 'approver'">
        <div v-if="sendToUserId == currentUserId" class="flex gap-2">
          <Button v-if="['initiated','in progress'].includes(claimStatus)" :label="'initiated' == claimStatus ? 'Back To Initiator' : 'Back To Reviewer'" size="sm" color="gray" @click="() => handleSubmit('back')" />
          <Button label="Reject & Send Back To Reviewer" size="sm" color="red" @click="() => handleSubmit('reject', true)" />
          <Button label="E-Sign & Approve" size="sm" color="green" @click="() => handleSubmit('approve', true)" />
          <select 
            class="rounded-full px-4 py-2 rounded text-sm mr-2 cursor-pointer" v-model="selectedUser"
            name="selectedUser">
            <option class="bg-white hover:bg-white" value="" key="ukey0">Send To My Channel</option>
            <option class="bg-white hover:bg-white" v-for="item in sendToUsers" :value="item.id" :key="'ukey' + item.id">{{
              item.name }}</option>
          </select>      
          <Button label="Forward" size="sm" color="green" @click="() => handleSubmit('submit')" />
        </div>
        <div v-if="sendToUserId == currentUserId && ['initiated','in progress'].includes(claimStatus)" class="w-40 text-sm text-center truncate" :title="apiStore.it_equipment?.detail.sent_to_user">
          {{ apiStore.it_equipment?.detail.sent_to_user }}
        </div>
      </div>
      <div v-else-if="userType == 'reviewer'">
        <div v-if="(sendToUserId == currentUserId || 'rejected' == claimStatus)" class="flex gap-2">
          <Button v-if="['initiated','in progress'].includes(claimStatus)" label="Back To Initiator" size="sm" color="gray" @click="() => handleSubmit('back')" />
          <Button v-if="(apiStore.it_equipment?.detail?.member_notified == 0)" label="Back To Member" size="sm" color="red" @click="() => handleBackToMember()" />
          <select v-if="['initiated','in progress'].includes(claimStatus)" 
            class="rounded-full px-4 py-2 rounded text-sm mr-2 cursor-pointer" v-model="selectedUser"
            name="selectedUser">
            <option class="bg-white hover:bg-white" value="" key="ukey0">Send To My Channel</option>
            <option class="bg-white hover:bg-white" v-for="item in sendToUsers" :value="item.id" :key="'ukey' + item.id">{{
              item.name }}</option>
          </select>      

          <Button v-if="['initiated','in progress'].includes(claimStatus)" label="Forward" size="sm" color="green" @click="() => handleSubmit('submit')" />

        </div>
        <div v-if="(sendToUserId == currentUserId && ['initiated','in progress'].includes(claimStatus))" class="w-40 text-sm text-center truncate" :title="apiStore.it_equipment?.detail.sent_to_user">
          {{ apiStore.it_equipment?.detail.sent_to_user }}
        </div>
      </div>
      <div v-else-if="userType == 'initiator'">
        <div v-if="claimStatus == 'submitted'" class="flex gap-2">
          <select class="rounded-full px-4 py-2 rounded text-sm mr-2 cursor-pointer" v-model="selectedUser"
            name="selectedUser">
            <option class="bg-white hover:bg-white" value="" key="ukey0">Send To My Channel</option>
            <option class="bg-white hover:bg-white" v-for="item in sendToUsers" :value="item.id" :key="'ukey' + item.id">{{
              item.name }}</option>
          </select>
          <Button label="Forward To Reviewer" size="sm" color="green" @click="() => handleSubmit('submit')" />
        </div>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Loading, Button, Card } from "@sds/oneui-common-ui";
import StatusTrack from "./StatusTrack.vue";
import LetterTemplateEditor from './LetterTemplateEditor.vue';
import ResizablePanels from './ResizablePanels.vue';
import SplitPanel from './SplitPanel.vue';
import Swal from 'sweetalert2';
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { useApiStore } from "@/store/apiData";
import esignLoader from '@/assets/images/loading-esign.gif';
import { 
  claimProgressById, 
  claimDetailsById, 
  claimDivisionBudget, 
  getTemplates, 
  getUsers, 
  processClaim,
  processEsign,
  checkeSignStatus,
  resendEsignNotification,
  backToMember
} from "@/services/rss/itEquipmentsService";
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from "vue-i18n";
import TemplateHistory from "./TemplateHistory.vue";
import { lastFourChar } from "@/utils/global";

const router = useRouter();
const route = useRoute();
const apiStore = useApiStore();
const { t, locale } = useI18n();
const isLoading = ref(true);
const scrollToSection = ref(null);
const selectedUser = ref('');
const sendToUsers = ref([]);
const oklabel = computed(() => t('btn_ok'));
const notFoundMessage = computed(() => t('something_wrong'));
const letterPanelRef = ref();
const letterSplitPanel = ref(true);
const minRightWidth = ref(0);

const countdown = ref(0);
const approveData = ref({esign_request_id:'', media_id:0, txn:'', label:''}); //B6tzLd_GstApnn8BYhbKCw - 7748
const approvedBy = ref('');
const esignStatusIntervalId = ref(null);
const finalPayload = ref({});
const isSubmitted = ref(false);
const historicalData = ref(false);

const userType = computed(() => {
  let user = '';
  if ( hasPermission([PERMISSIONS.ITCLAIM.APPROVE]) ) {
    user = 'approver';
  } else if ( hasPermission([PERMISSIONS.ITCLAIM.REVIEW]) ) {
    user = 'reviewer';
  } else if ( hasPermission([PERMISSIONS.ITCLAIM.INITIATE]) ) {
    user = 'initiator';    
  }
  //user = 'approver';
  return user;
});

const progressbarTitle = computed(() => {
  const baseTitle = `${t("menu.it_claim")} ${t("menu.claims")}`;
  const userPrefixMap = {
    initiator: t("initiator"),
    reviewer: t("reviewer"),
    approver: t("approver"),
  };
  const prefix = userPrefixMap[userType.value] || "";
  return prefix ? `${prefix} ${baseTitle}` : baseTitle;
});

const currentUserId = computed(() =>apiStore.user.id || 0);
const claimStatus = ref('');
const claimId = ref(0);
const sendToUserId = ref(0);
const pending = ref('pending');
const stages = ref([
  { key: "submitted", status: "in-process", delay: 0, date: null, isRejected: false , isReturned: false },
  { key: "initiate", status: pending.value, delay: 0, date: null, isRejected: false , isReturned: false },
  { key: "review", status: pending.value, delay: 0, date: null, isRejected: false , isReturned: false },
  { key: "approve", status: pending.value, delay: 0, date: null, isRejected: false , isReturned: false }
]);
const updateStageNames = () => {
  stages.value.forEach((stage) => {
    stage.name = t(stage.key);
  });
};

const fetchClaimProgress = async () => {
  const response = await claimProgressById(claimId.value);
  if (response.isError || response.success_code !== 200) return;
  const { Submitted, Initiated, Review, Approve, Rejected } = response.data;
  
  const updateStage = (index, data, translatedKey) => {
    if (Object.keys(data).length) {
      if ((translatedKey == 'approved') && (Rejected.status == 'Success')) {
        stages.value[index].key = 'rejected';
        stages.value[index].status = Rejected.status.toLowerCase();
        stages.value[index].date = Rejected.datetime;
        stages.value[index].delay = Rejected.delay;
        stages.value[index].isRejected = Rejected.returned;
        stages.value[index].isReturned = Rejected.returned;
      } else {
        stages.value[index].key = translatedKey;
        stages.value[index].status = data.status ? data.status.toLowerCase() : data.status;
        stages.value[index].date = data.datetime;
        stages.value[index].delay = data.delay;
        stages.value[index].isRejected = false;
        stages.value[index].isReturned = Boolean(data.returned);
      }
    }
  };

  updateStage(0, Submitted, "submitted");
  updateStage(1, Initiated, "initiated");
  updateStage(2, Review, "reviewed");
  updateStage(3, Approve, "approved");
  updateStageNames();
}

const fetchClaimDetail = async () => {
  isLoading.value = true;
  const response = await claimDetailsById(claimId.value);
  isLoading.value = false;
  //console.log(response)
  if (response.isError == false && response.success_code == 200 ) {
    if ( Array.isArray(response.data) && response.data.length == 0 ) {
      detailNotFound();
      return true;
    } else if ( typeof response.data == "object" && Object.keys(response.data).length == 0 ) {
      detailNotFound();
      return true;
    }
    apiStore.setItClaimFinalData(response.data.approval_data);
    apiStore.setItEquipment({ ...apiStore.it_equipment, detail: response.data });
    sendToUserId.value = response.data.assigned_to || 0;
    claimStatus.value = response.data.status.toLowerCase() || '';
    if ( response.data.source == 'bulk' ) {
      stages.value = [{...stages.value[0], status:'Success'},{...stages.value[3], key:'Paid', status:'Success'}];
      historicalData.value = true;
    } else {
      fetchClaimProgress();
    }
  } else {
    detailNotFound();
  }
  return true;
};

const detailNotFound = () => {
    Swal.fire({
      icon: 'error',
      title: progressbarTitle.value,
      text: notFoundMessage.value,
      confirmButtonText: oklabel.value,
      customClass: {
        confirmButton: 'bg-green-600 hover:bg-green-700 rounded-full text-white px-6 py-2 rounded text-sm mr-4'
      }
    });
    router.push({
      name: 'ITClaimList'
    });
}

const fetchClaimDivisionBudget = async () => {
  let division_budget = {};
  const response = await claimDivisionBudget(claimId.value);
  if (response.isError == false && response.success_code == 200) division_budget = response.data;
  apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, division_budget } });
  return true;
};

const fetchTemplates = async () => {
  let templates = [];
  const response = await getTemplates();
  if (response.isError == false && response.success_code == 200) templates = response.data;
  apiStore.setITClaimTemplates(templates);
  return true;
};

const fetchUsers = async () => {
  sendToUsers.value = [];
  const response = await getUsers();
  if (response.isError == false && response.success_code == 200) sendToUsers.value = response.data;
  apiStore.setItEquipment({ ...apiStore.it_equipment, detail: { ...apiStore.it_equipment.detail, users: sendToUsers.value } });
  return true;
};

let countdownInterval = null;
const startCountdown = () => {
  if (countdown.value == 0) {
    countdown.value = 300; //In Second
  }
  countdownInterval = setInterval(() => {
    if (countdown.value > 0) {
      countdown.value -= 1;
    } else {
      clearInterval(countdownInterval);
      clearInterval(esignStatusIntervalId.value); 
    }
  }, 1000);
};
const handleSendNotification = async () => {
  const response = await resendEsignNotification(approveData.value.media_id);
  if ( response.isError == false && response.success_code == 200 ) {
    approveData.value.esign_request_id = response.data;
    findSignStatus();
  }
};

const handleBack = () => {
  approveData.value = {esign_request_id:'', media_id: 0, txn:'', label:''};
  countdown.value = 0;
  isSubmitted.value = false;
  clearInterval(countdownInterval);
  clearInterval(esignStatusIntervalId.value); 
};

const handleSubmit = async (actionType = '', eSign = false) => {
  if ( isSubmitted.value ) return false;
  const fullData = apiStore.it_equipment?.detail || {};
  const ictAmount = Number(fullData.ict_amount);
  const digitalAmount = Number(fullData.digital_amount);
  let payableAmount = 0;
  const sendTo = Number(selectedUser.value);
  const templateId = Number(fullData.template_id);
  const templateContent = fullData.template_content;
  const touchedItems = fullData.touched_items || [];
  const notes = fullData.notes || [];
  let noteActorId = notes.length ? notes[0]['actor_id'] : 0;

  let amount = Math.ceil(fullData.claim_items.filter(item => item.is_accpted == 'Admissible').reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0));
  const totalBySystem = digitalAmount + ictAmount;
  if (totalBySystem > 0 && totalBySystem <= amount) {
    payableAmount = totalBySystem;
  } else {
    payableAmount = amount;
  }

  const divisionBudget = apiStore.it_equipment.detail?.division_budget || {digital:0, ict: 0};
  //console.log(divisionBudget);
  let errorMessage = '';
  if ( (currentUserId.value != noteActorId) && (eSign == false) ) {
    switch (userType.value) {
      case 'approver':
        if ( actionType == 'submit' ) {
          errorMessage = 'Please add remark/note to forward the claim.';
        }
        break;
      case 'reviewer':
        errorMessage = 'Please add remark/note to the claim.';
        break;
      case 'initiator':
        errorMessage = 'Please add remark/note to the claim.';
        break;  
      default:
    }
  }
  if ( Array.isArray(touchedItems) && touchedItems.length ) {
    errorMessage = 'Please add remark/note for claim item.';
  }

  if ( actionType == 'submit' && (sendTo <= 0 || sendTo == '') ) {
    errorMessage = 'Please select Send To';
  }
  const editorContent = templateContent.replace(/<\/?[^>]+(>|$)/g, "").trim();
  if (editorContent == '') {
    errorMessage = 'Letter to template cannot be empty';
  }

  if ( (amount > 0) && (payableAmount !== totalBySystem || payableAmount === 0) ) {
    errorMessage = payableAmount == 0 ? 'In claim detail, The Payable Amount should not be empty.' : 'The combined total of the Digital Amount and the ICT Amount must equal the Payable Amount.';
  }

  if ( ( divisionBudget?.digital == 0 && divisionBudget?.ict == 0) ||  (digitalAmount > divisionBudget.digital) || (ictAmount > divisionBudget.ict) ) {
    errorMessage = 'Payable amount should be less then or equal to available Division Budget';
  }
  
  if ( errorMessage != '' ) {
    detailSwalPopup({ isError: true, message: errorMessage })
    return false;
  }

  finalPayload.value = {
    action: actionType,
    ict_amount: ictAmount,
    digital_amount: digitalAmount,
    payble_amount: payableAmount,
    template_id: templateId,
    template_content: templateContent,
    send_to: sendTo
  };

  const swalRes = await Swal.fire({
    title: 'Are you sure?',
    text: "Are you want to proceed this action!",
    icon: 'warning',
    showCancelButton: true,
    buttonsStyling: false, // disable default SweetAlert styles
    confirmButtonText: 'Yes, confirm it!',
    cancelButtonText: 'Cancel',
    customClass: {
      confirmButton: 'bg-green-600 hover:bg-green-700 rounded-full text-white px-6 py-2 rounded text-sm mr-4',
      cancelButton: 'bg-red-600 hover:bg-red-700 rounded-full text-white px-6 py-2 rounded text-sm'
    }
  });
  if ( swalRes.isConfirmed == false ) {
    return false;
  }

  isSubmitted.value = true;
  if ( eSign ) {
    if ( actionType == 'approve' ) {
      approveData.value.label = 'Approve';
    } else if ( actionType == 'reject' ) {
      approveData.value.label = 'Drop';
    } else {
      approveData.value.label = '';
    }

    const response = await processEsign(claimId.value, finalPayload.value);
    if (response.isError) {
      detailSwalPopup({ isError: response.isError, message: response.message || response.error || notFoundMessage.value });
      isSubmitted.value = false;
    } else {
      if (response.success_code == 200) {
        approveData.value = {...approveData.value, ...response.data};
        const top = scrollToSection.value.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ behavior: "smooth", top: top - 200 });
        findSignStatus();
      } else {
        detailSwalPopup({ isError: response.isError, message: response.description || response.error || notFoundMessage.value });
        isSubmitted.value = false;
      }
    }
  } else {
    processClaimData();
  }
}

const findSignStatus = async () => {
  startCountdown();
  esignStatusIntervalId.value = setInterval(async () => {
    if ( approveData.value.esign_request_id == '' ) return;
    const response = await checkeSignStatus(approveData.value.esign_request_id);
    if ( response.isError == false && response.success_code == 200 ) {
      clearInterval(esignStatusIntervalId.value);
      processClaimData();
    }
  }, 3000);

};

const processClaimData = async () => {
  const response = await processClaim(claimId.value, finalPayload.value);
  isSubmitted.value = false;
  approveData.value = {esign_request_id:'', media_id: 0, txn:'', label:''};
  if (response.isError) {
    detailSwalPopup({ isError: response.isError, message: response.message || response.error || notFoundMessage.value });
  } else {
    if (response.success_code == 200) {
      switch (finalPayload.value.action) {
        case 'submit':
          detailSwalPopup({ isError: false, message: 'The claim has been forwarded successfully' });
          router.push({ 
            name: 'ITClaimDetailForwardedSummary'
          });
          break;
        case 'back':
          detailSwalPopup({ isError: false, message: 'The claim has been updated successfully' });
          router.push({ 
            name: 'ITClaimList'
          });
          break;
        case 'reject':
          detailSwalPopup({ isError: false, message: 'The claim has been updated successfully' });
          router.push({ 
            name: 'ITClaimList'
          });
          break;
        case 'approve':
          detailSwalPopup({ isError: false, message: 'The claim has been approved successfully' });
          router.push({ 
            name: 'ITClaimDetailForwardedSummary'
          });
          break;
        default: detailSwalPopup({ isError: true });
      }
    } else {
      message = response.message || 'Something went wrong!';
      detailSwalPopup({ isError: false, message: message });
    }
  }
}

const detailSwalPopup = (item) => {
  Swal.fire({
    icon: item.isError ? 'error' : 'success',
    title: "IT Claim: Detail",
    text: item.message || notFoundMessage.value,
    confirmButtonText: "OK",
    customClass: {
      confirmButton: 'bg-green-600 hover:bg-green-700 rounded-full text-white px-6 py-2 rounded text-sm mr-4'
    }
  });
  return true;
}

const handleBackToMember = async () => {
  const swalRes = await Swal.fire({
    title: 'Are you sure?',
    text: "Are you want to proceed this action!",
    icon: 'warning',
    showCancelButton: true,
    buttonsStyling: false,
    confirmButtonText: 'Yes, confirm it!',
    cancelButtonText: 'Cancel',
    customClass: {
      confirmButton: 'bg-green-600 hover:bg-green-700 rounded-full text-white px-6 py-2 rounded text-sm mr-4',
      cancelButton: 'bg-red-600 hover:bg-red-700 rounded-full text-white px-6 py-2 rounded text-sm'
    }
  });
  if ( swalRes.isConfirmed == false ) {
    return false;
  }
  const response = await backToMember(claimId.value);
  detailSwalPopup({ isError: response.isError, message: response.message || response.error || notFoundMessage.value });
  if ( response.isError == false && response.success_code == 200 ) {
    router.push({name: 'ITClaimList'});
  }
}

watch(locale, () => {
  updateStageNames();
});

onMounted(async () => {
  claimId.value = route.params.id;
  await fetchClaimDetail();
  await fetchClaimDivisionBudget();
  fetchTemplates();
  fetchUsers();
  updateStageNames();
})

const handleRefresh = event => {
  fetchClaimDetail();
  updateStageNames();
};
const handleHistoryDetails = event => {
  router.push({ 
    name: 'ITClaimStatusHistory',
    params: {id: claimId.value}
  });
};
</script>
