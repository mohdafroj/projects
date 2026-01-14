<template>
  <div ref="scrollToSection">
    <Loading v-if="isLoading" />
    <div v-if="approveData.media_id > 0" class="p-6 bg-white min-h-screen text-center">
      <!-- Header Section -->
      <div class="flex items-center justify-center h-full">
        <img :src="esignLoader" alt="" class="watch-sign w-50 h-36" />
      </div>
      <p class="text-align-center font-bold mb-4 mt-4">A notification has been sent to your linked phone
        number.
        <br />Please open your authenticator app and enter the
        <br />
        PIN to digitally sign the document.
      </p>

      <p class="text-sm text-gray-500 text-center mb-4">
        <span v-if="countdown > 0">Resend</span>
        <span v-else @click="() => handleSendNotification()"
          class="text-blue-500 cursor-pointer hover:underline">Resend</span>
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
      <StatusTrack v-else :title="progressbarTitle" :successLabel="'Success'" :stages="stages"
        :refreshLabel="t('refresh')" :historyLabel="t('status_history')" @refresh="handleRefresh"
        @viewHistoryDetails="handleHistoryDetails" />
      <ResizablePanels :module-id="moduleId" />

    </div>
  </div>

</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Loading, Button, Card } from "@sds/oneui-common-ui";
import StatusTrack from "./StatusTrack.vue";
import ResizablePanels from './ResizablePanels.vue';
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
  getRulesByClaim,
  resendEsignNotification
} from "@/services/rss/TadaServices";
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from "vue-i18n";
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
const storedata_tada = ref(null);
const moduleId = ref('0');
const countdown = ref(0);
const approveData = ref({ esign_request_id: '', media_id: 0 });
const esignStatusIntervalId = ref(null);
const finalPayload = ref({});
const isSubmitted = ref(false);
const historicalData = ref(false);

const userType = computed(() => {
  let user = '';
  if (hasPermission([PERMISSIONS.TADACLAIM.APPROVE])) {
    user = 'approver';
  } else if (hasPermission([PERMISSIONS.TADACLAIM.REVIEW])) {
    user = 'reviewer';
  } else if (hasPermission([PERMISSIONS.TADACLAIM.INITIATE])) {
    user = 'initiator';
  }
  return user;
});

const progressbarTitle = computed(() => {
  const baseTitle = `${t("menu.tada_claims")}`;
  const userPrefixMap = {
    initiator: t("initiator"),
    reviewer: t("reviewer"),
    approver: t("approver"),
  };
  const prefix = userPrefixMap[userType.value] || "";
  return prefix ? `${prefix} ${baseTitle}` : baseTitle;
});

const currentUserId = computed(() => apiStore.user.id || 0);
const claimStatus = ref('');
const claimId = ref(0);
const sendToUserId = ref(0);
const pending = ref('pending');
const stages = ref([
  { key: "submitted", status: "in-process", delay: 0, date: null, isRejected: false, isReturned: false },
  { key: "initiate", status: pending.value, delay: 0, date: null, isRejected: false, isReturned: false },
  { key: "review", status: pending.value, delay: 0, date: null, isRejected: false, isReturned: false },
  { key: "approve", status: pending.value, delay: 0, date: null, isRejected: false, isReturned: false }
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

// Fix: Define payload properly


const fetchClaimDetail = async () => {
  isLoading.value = true;
  const response = await claimDetailsById(claimId.value);
  isLoading.value = false;

  if (response.isError == false && response.success_code == 200) {
    if (Array.isArray(response.data) && response.data.length == 0) {
      detailNotFound();
      return true;
    } else if (typeof response.data == "object" && Object.keys(response.data).length == 0) {
      detailNotFound();
      return true;
    }
    apiStore.setTadaClaim({ ...apiStore.tada_claim, detail: response.data });
    sendToUserId.value = response.data.assigned_to || 0;
    claimStatus.value = response.data.status.toLowerCase() || '';
    if (response.data.source == 'bulk') {
      stages.value = [{ ...stages.value[0], status: 'Success' }, { ...stages.value[3], key: 'Paid', status: 'Success' }];
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
    name: 'TADAClaims'
  });
}

const fetchClaimDivisionBudget = async () => {
  let division_budget = {};
  const response = await claimDivisionBudget(claimId.value);
  if (response.isError == false && response.success_code == 200) division_budget = response.data;
  apiStore.setTadaClaim({ ...apiStore.tada_claim, detail: { ...apiStore.tada_claim.detail, division_budget } });
  return true;
};

const fetchTemplates = async () => {
  let templates = [];
  const response = await getTemplates();
  if (response.isError == false && response.success_code == 200) templates = response.data;
  apiStore.setTadaClaim({ ...apiStore.tada_claim, detail: { ...apiStore.tada_claim.detail, templates } });
  return true;
};

const fetchUsers = async () => {
  sendToUsers.value = [];
  const response = await getUsers();
  if (response.isError == false && response.success_code == 200) sendToUsers.value = response.data;
  apiStore.setTadaClaim({ ...apiStore.tada_claim, detail: { ...apiStore.tada_claim.detail, users: sendToUsers.value } });
  return true;
};


let countdownInterval = null;
const startCountdown = () => {
  if (countdown.value == 0) {
    countdown.value = 60;
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
  if (response.isError == false && response.success_code == 200) {
    approveData.value.esign_request_id = response.data;
    findSignStatus();
  }
};

const handleBack = () => {
  approveData.value = { esign_request_id: '', media_id: 0 };
  countdown.value = 0;
  isSubmitted.value = false;
  clearInterval(countdownInterval);
  clearInterval(esignStatusIntervalId.value);
};


const findSignStatus = async () => {
  startCountdown();
  esignStatusIntervalId.value = setInterval(async () => {
    if (approveData.value.esign_request_id == '') return;
    const response = await checkeSignStatus(approveData.value.esign_request_id);
    if (response.isError == false && response.success_code == 200) {
      clearInterval(esignStatusIntervalId.value);
      processClaimData();
    }
  }, 3000);
};

const processClaimData = async () => {
  const response = await processClaim(claimId.value, finalPayload.value);
  isSubmitted.value = false;
  approveData.value = { esign_request_id: '', media_id: 0 };
  if (response.isError) {
    detailSwalPopup({ isError: response.isError, message: response.message || response.error || notFoundMessage.value });
  } else {
    if (response.success_code == 200) {
      switch (finalPayload.value.action) {
        case 'submit':
          detailSwalPopup({ isError: false, message: 'The claim has been forwarded successfully' });
          router.push({
            name: 'TADAClaimDetailForwardedSummary'
          });
          break;
        case 'back':
          detailSwalPopup({ isError: false, message: 'The claim has been updated successfully' });
          router.push({
            name: 'TADAClaims'
          });
          break;
        case 'reject':
          detailSwalPopup({ isError: false, message: 'The claim has been updated successfully' });
          router.push({
            name: 'TADAClaims'
          });
          break;
        case 'approve':
          detailSwalPopup({ isError: false, message: 'The claim has been approved successfully' });
          router.push({
            name: 'TADAClaimDetailForwardedSummary'
          });
          break;
        default: detailSwalPopup({ isError: true });
      }
    } else {
      const message = response.message || 'Something went wrong!';
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

watch(
  () => apiStore.tada_claim?.detail,
  (newDetail) => {
    if (!newDetail) {
      storedata_tada.value = null;
      moduleId.value = '0';
      return;
    }

    storedata_tada.value = newDetail;
    moduleId.value = newDetail.module_id || '0';
  },
  { immediate: true, deep: true }
);


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
    name: 'TADAClaimStatusHistory',
    params: { id: claimId.value }
  });
};
</script>