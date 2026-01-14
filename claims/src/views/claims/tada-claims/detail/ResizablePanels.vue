<template>
  <div ref="scrollToSection">
    <Loading v-if="isLoading" />
    <!-- E-Sign Section -->
   <!-- hello {{ claimStatus }} -->
    <div v-if="approveData.media_id > 0" class="p-6 bg-white min-h-screen text-center">
      <div class="flex items-center justify-center h-full">
        <img :src="esignLoader" alt="" class="watch-sign w-50 h-36" />
      </div>

      <p v-if="approveData.txn" class="font-bold text-xl">
        TXN: <span class="text-red-500">{{ maskedTxn }}</span>
      </p>

      <p class="text-center font-bold mb-4 mt-4">
        A notification has been sent to your linked phone number.
        <br />Please open your authenticator app and authenticate using
        <br />your biometric to digitally sign the document.
      </p>

      <p class="text-sm text-gray-500 text-center mb-4">
        <span v-if="countdown > 0">Resend</span>
        <span v-else @click="handleSendNotification" class="text-blue-500 cursor-pointer hover:underline">
          Resend
        </span>
        {{ formatTime(countdown) }}
      </p>

      <div class="flex items-center justify-center">
        <Button @click="handleBack" label="Back" color="gray-outline" size="sm" />
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="flex flex-col">
      <SplitPanel ref="splitPanelRef" v-model:showPanel="showSplitPanel" :initialLeftWidth="'40%'" class="flex-1">
        <template #left="{ close }">
          <ApproveReject />
          <ApprovalNoteSection @closeEditor="close" />
        </template>

        <template #right>
          <div class="h-full flex flex-col bg-white rounded-md p-1 dark:bg-gray-900">
            <!-- Tabs -->
            <div class="tab-header">
              <button @click="activeTab = 'details'" :class="tabClass('details')">
                Claim Details
              </button>
              <button @click="activeTab = 'version'" :class="tabClass('version')">
                Template History
              </button>
            </div>

            <!-- Content -->
            <div class="flex-1 p-4">
              <RightPanelComponent v-if="activeTab === 'details'" @add-note="enableSplitPanel" />
              <VersionApprovalSection v-if="activeTab === 'version'" />
            </div>
          </div>
        </template>
      </SplitPanel>
    </div>

    <!-- Action Buttons -->
    <div v-if="claimStatus !== 'Approved'"
      class="grid grid-cols-2 gap-4 p-2 bg-white rounded-md shadow-sm dark:bg-gray-900 mb-3">
      <div class="flex justify-start gap-2">
        <!-- Pull Back Button -->
        <Button v-if="canPullBack" label="Pull Back" color="gray-outline" class="action-btn warning"
          @click="pullToBack" />

        <!-- Send Back Button -->
        <Button v-if="canSendBack" label="Send Back" color="gray-outline" class="action-btn"
          @click="openTimelineModal" />

        <!-- Forward Button -->
        <Button v-if="canForward" label="Forward" color="blue-outline" class="action-btn" @click="openReviewModal" />

        <!-- Approve Button -->
        <Button v-if="canApprove" label="Approve" color="green" class="action-btn" @click="openApproveModal" />

        <!-- Reject Button -->
        <Button v-if="canReject" label="Reject" color="red" class="action-btn" @click="openRejectModal" />
      </div>
    </div>

    <!-- Modals -->
    <ReviewModal v-model="showReviewModal" v-model:draftId="approvalForm.draft_id" :content="approvalForm.content"
      v-model:reviewTo="form.review_to" :users="reviewUsers" :loading-user="loading.reviewUser"
      :loading-send="loading.send" :drafts="draftDropdown" @search="searchSendTo" @send="sendToReviewUser" />

    <TimeLineModal v-model="showTimelineModal" :stakeholders="module.stackholder" :drafts="draftDropdown"
      :draft-id="approvalForm.draft_id" :content="approvalForm.content" :allow-action="module?.action"
      @send-back="sendToBack" />

    <ApprovalModal v-model="showApproveModal" v-model:draftId="approvalForm.draft_id" :drafts="draftDropdown"
      :content="approvalForm.content" :errors="errorsApproval" :loading-approve="loading.approve"
      @draft-change="selectApprovalDraft" @approve="approvedSubmit('approve', true)" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import ApproveReject from './ApproveReject.vue';
import SplitPanel from './SplitPanel.vue';
import ApprovalNoteSection from './ApprovalNoteSection.vue';
import VersionApprovalSection from './VersionApprovalSection.vue';
import RightPanelComponent from './RightPanelComponent.vue';
import ReviewModal from '@/components/ReviewModal.vue';
import TimeLineModal from '@/components/TimeLineModal.vue';
import ApprovalModal from '@/components/ApprovalModal.vue';
import { Button, Loading } from '@sds/oneui-common-ui';
import { PERMISSIONS, hasPermission } from '@/utils/rbac';
import { debounce } from '@/utils/debounce';
import { fireToast } from '@/mixins/toast';
import { useApiStore } from "@/store/apiData";
import { cookieService } from '@sds/oneui-layout';
import {
  processEsign,
  checkeSignStatus,
  resendEsignNotification,
} from "@/services/rss/itEquipmentsService";
import {
  fetchSendTo,
  getDraftAll,
  fetchApprovalView,
  PullBackApproval
} from '@/services/approval';
import { processClaim } from '@/services/rss/TadaServices';
import { useValidation, approvalNoticeValSchema } from '@/constant/validation';
import { formFieldValidator } from '@/utils/formFieldValidator';

const route = useRoute();
const router = useRouter();
const apiStore = useApiStore();

// Computed
const claimStatus = computed(() => apiStore.tada_claim?.detail?.status || '');
const moduleId = computed(() => apiStore.tada_claim?.detail?.module_id);
const maskedTxn = computed(() => {
  if (!approveData.value?.txn) return '';
  return String(approveData.value.txn).slice(-4);
});

// RBAC
const rbacAppData = cookieService.getLocalStorageData({
  name: "rbacAppData",
  non_primitive: 1,
  decode: 1
});
const user_ids = rbacAppData.core_user_id;
//const assigned_to_user = computed(() => apiStore.tada_claim?.detail?.assigned_to);
const assignedTo = computed(() => apiStore.tada_claim?.detail?.assigned_to);
// State
const isLoading = ref(false);
const claimId = ref(route.params.id);
const finalPayload = ref({});
const isSubmitted = ref(false);
const approveData = ref({ esign_request_id: '', media_id: 0, txn: '' });
const scrollToSection = ref(null);
const countdown = ref(0);
const esignStatusIntervalId = ref(null);
let countdownInterval = null;

const showSplitPanel = ref(true);
const showReviewModal = ref(false);
const showTimelineModal = ref(false);
const showApproveModal = ref(false);
const activeTab = ref('details');

const module = ref({});
const reviewUsers = ref([]);
const draftDropdown = ref([]);

const loading = reactive({
  page: false,
  reviewUser: false,
  send: false,
  approve: false,
});

const form = reactive({
  review_to: '',
});

const approvalForm = reactive({
  draft_id: '',
  template_id: '',
  content: '',
});

// Permissions

// Permissions Helper
const isAssignedToUser = computed(() =>
  Number(assignedTo.value) === Number(user_ids) || assignedTo.value === null
);

const hasInitiatePermission = computed(() => hasPermission(PERMISSIONS.TADACLAIM.INITIATE));
const hasReviewPermission = computed(() => hasPermission(PERMISSIONS.TADACLAIM.REVIEW));
const hasApprovePermission = computed(() => hasPermission(PERMISSIONS.TADACLAIM.APPROVE));

// Hide all buttons if status is Approved or Rejected
const showActionButtons = computed(() =>
  !['Approved', 'Rejected'].includes(claimStatus.value)
);

const htmlTemplateString = `<!DOCTYPE html>\r\n<html>\r\n\r\n<head>\r\n    <title>Rajya Sabha Secretariat<\/title>\r\n<\/head>\r\n<body>\r\n\r\n<div style=\"font-family: arial;padding: 25px 25px;\">\r\n    <div style=\"text-align: center;\">\r\n        <div style=\"font-size: 16px;text-decoration: underline;\"><strong>SYSTEMS DIVISION<\/strong><\/div>\r\n    <\/div>\r\n    <div>\r\n        <div style=\"font-size: 15px;line-height: 24px;color:#000;padding-top:20px;\">\r\n            <div style=\"font-size: 15px;color: #000;line-height: 24px;\">\r\n                <p><strong>Sub:  Submission of Tax Invoice by Dummy Member2, Member, Rajya Sabha for reimbursement under the Scheme of Financial Entitlement of Members of Rajya Sabha for Computer Equipment.<\/strong><\/p>\r\n            <\/div>\r\n        <\/div>\r\n        <ol style=\"line-height: 24px;\">\r\n\r\n            <li style=\"font-size: 15px;padding-bottom: 20px;\">PUC is a letter received on <strong>{{CLAIM_SUBMISSION_DATE}}<\/strong> from <strong>{{MEMBER_NAME}}<\/strong>, Rajya Sabha, whereby the Member has submitted the following invoices from M\/s <strong>{{VENDOR_NAME}}<\/strong>, <strong>{{VENDOR_ADDRESS}}<\/strong>, for reimbursement towards the procurement of computer equipment under the Scheme of Financial Entitlement of Members of Rajya Sabha for Computer Equipment:\r\n            <ul style=\"line-height: 24px;margin-top:10px;\">\r\n            {{ITEM_DETAILS_LI}}\r\n            <\/ul>\r\n            <p>The total claimed amount is Rs. <strong>{{TOTAL_CLAIMED_AMOUNT}}<\/strong>.<\/p>\r\n<\/li>\r\n            <\/li>\r\n            <li style=\"padding-bottom: 20px;\">\r\n                The Member has procured following items of computer equipment  :-\r\n                <table border=\"1\" cellpadding=\"10\" cellspacing=\"0\"\r\n                style=\"width:100%; border-collapse: collapse; margin: 1em 0; line-height: 24px;\">\r\n            <tr>\r\n                <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Sl. No.<\/th>\r\n                <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Item<\/th>\r\n                <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Quantity<\/th>\r\n                <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Amount in INR <br\/>(including of GST)<\/th>\r\n            <\/tr>\r\n            {{ITEM_DETAILS_TH}}\r\n            <tr>\r\n                <td style=\"border: 1px solid #aaa; padding: 8px;\"><\/td>\r\n                <td style=\"border: 1px solid #aaa; padding: 8px;\"><strong>Total including all taxes<\/strong><\/td>\r\n                <td style=\"border: 1px solid #aaa; padding: 8px;\">-<\/td>\r\n                <td style=\"border: 1px solid #aaa; padding: 8px;\"><strong>{{TOTAL_CLAIM_AMOUNT}}<\/strong><\/td>\r\n            <\/tr>\r\n           \r\n\r\n                <\/table>\r\n\r\n            <\/li>\r\n            <li style=\"padding-bottom: 20px;\">\r\n                It may be stated that the above mentioned equipment is as per the computer items allowed under the Rules.  A copy each of the amended Rules and approved Procedure <strong><i>is placed at reference #1<\/i><\/strong>.\r\n            <\/li>\r\n            <li style=\"padding-bottom: 20px;\">\r\n                The Member has been elected on <strong>{{MEMBER_ELECTED_DATE}}<\/strong> and the tenure of the Member is up to  <strong>{{MEMBER_TERM_END_DATE}}<\/strong> <strong><i>(placed at reference #2)<\/strong><\/i>.  A Member of Rajya Sabha is eligible for financial entitlement for purchasing computer equipment and software under the Scheme of \u201cFinancial Entitlement of Members of Rajya Sabha for Computer Equipment\u201d as amended <i>w.e.f.<\/i> 9th September, 2021 to the tune of Rs. <strong>{{FINANCIAL_ENTITLEMENT_AMOUNT}}<\/strong> for the first three years.  This Member has not made any prior claim for reimbursement, therefore, the Member is presently eligible for amount of Rs.<strong>{{FINANCIAL_ENTITLEMENT_AMOUNT}}<\/strong>.  An Entitlement Sheet with regard to the Member is <strong><i>placed at local reference #3<\/strong><\/i>.\r\n            <\/li>\r\n            <li style=\"padding-bottom: 20px;\">\r\n                The admissible expenditure incurred item-wise can be categorized in Revenue and Capital Expenditure Heads respectively as given in the table below as per decision <strong><i>(placed at local reference #4)<\/strong><\/i> taken by the competent authority to categorize the expenditure of items under two different said heads based on criteria of the cost of individual item to the threshold limit of Rupees one lakh or three years of useful life, either of the two, in view of Budget and Finance Section\u2019s Circular dated <strong>{{BUDGET_FINANCE_CIRCULAR_DATE}}<\/strong> adopting Ministry of Finance, Government of India OM dated <strong>{{GOI_OM_DATE}}<\/strong> on operationalization of revised\/new object heads :\r\n\r\n                <table border=\"1\" cellpadding=\"10\" cellspacing=\"0\"\r\n                style=\"width:100%; border-collapse: collapse; margin: 1em 0; line-height: 24px;\">\r\n                    <tr>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Sl. No.<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Item<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Quantity<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Capital Expenditure Head (in rupees)<br\/>(inclusive of all taxes)<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Revenue Expenditure Head (in rupees)<br\/>(inclusive of all taxes)<\/th>\r\n                    <\/tr>\r\n                    {{ITEM_DETAILS_TH1}}                    \r\n                    <tr>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><strong>Total including all taxes<\/strong><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">-<\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><strong>{{TOTAL_CLAIM_AMOUNT}}<\/strong><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">-<\/td>\r\n                    <\/tr>\r\n                    <tr>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><strong>Total amount to be booked\/paid to the Member<\/strong><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\" colspan=\"3\"><strong>{{TOTAL_CLAIM_AMOUNT}}<\/strong><\/td>\r\n                    <\/tr>\r\n                <\/table>\r\n            <\/li>\r\n            <li style=\"padding-bottom: 20px;\">\r\n               The expenditure involved is debitable to the sanctioned budget grant for the year <strong>{{FINANCIAL_YEAR}}<\/strong> in the respective heads, details of which along with other budget details are as under:\r\n\r\n                <table border=\"1\" cellpadding=\"10\" cellspacing=\"0\"\r\n                style=\"width:100%; border-collapse: collapse; margin: 1em 0; line-height: 24px;\">\r\n                    <tr>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Sl. No.<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Booking Head<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">BE Allocation {{FINANCIAL_YEAR}} (in rupees)<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">BE {{FINANCIAL_YEAR}} Balance as on date (in rupees)<\/th>\r\n                        <th style=\"border: 1px solid #aaa; padding: 8px; background-color: #f5f5f5; font-weight: bold;\">Instant expenditure to be booked (in rupees)<\/th>\r\n                    <\/tr>\r\n                    <tr>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">1<\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">Capital Section Major Head \u201c4075 \u2013 Capital Outlay on Miscellaneous General Services\/Detailed Head, 001 - Direction and Administration,  01 - Secretariat (General\/Social\/Economic Services),  01.29- Rajya Sabha, 01.29.71 - Information Computer, Telecommunications (ICT) Equipment\" (Member)<\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">{{BE_TOTAL_ALLOCATED_AMOUNT}}<\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">{{BE_BALANCED_AMOUNT}}<\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\">{{TOTAL_CLAIM_AMOUNT}}<\/td>\r\n                    <\/tr>\r\n                    <tr>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\"><strong>Total amount to be booked\/paid to the Member<\/strong><\/td>\r\n                        <td style=\"border: 1px solid #aaa; padding: 8px;\" colspan=\"3\"><strong>{{TOTAL_CLAIM_AMOUNT}}<\/strong><\/td>\r\n                    <\/tr>\r\n                <\/table>\r\n            <\/li>\r\n            <li style=\"padding-bottom: 20px;\">\r\n                In view of the above, the Competent Authority i.e. Joint Secretary (Systems & CBD) & CISO, may be requested to accord approval for reimbursement of <strong>Rs. {{TOTAL_CLAIM_AMOUNT}} ({{TOTAL_CLAIM_AMOUNT_IN_WORDS}}), to {{MEMBER_NAME}}, Member, Rajya Sabha<\/strong> towards the purchase of computer equipment under the Scheme of Financial Entitlement for purchase of computer equipment.  Once approved, the claim will be processed through e-bill on PFMS before onwards transmission to MS&A Branch for further processing and release of payment.\r\n            <\/li>\r\n        <\/ol>\r\n    <\/div>\r\n\r\n<\/div>\r\n<\/body>\r\n\r\n<\/html>"
        `;
// Button Visibility Logic Based on Requirements
const canPullBack = computed(() => {
  const status = claimStatus.value;


  // Case 3: Initiate permission + Initiated status
  if (hasInitiatePermission.value && status === 'Initiated') {
    console.log(status);
    console.log(hasInitiatePermission);
    return true;
  }

  // Case 4: Review permission + In Progress status
  if (hasReviewPermission.value && status === 'In Progress') {
    return true;
  }

  return false;
});

const canSendBack = computed(() => {
  const status = claimStatus.value;

  // Case 2: Review permission + Initiated status + Assigned to user
  if (hasReviewPermission.value && status === 'Initiated' && isAssignedToUser.value) {
    return true;
  }

  // Case 5: Approve permission + In Progress status + Assigned to user
  if (hasApprovePermission.value && status === 'In Progress' && isAssignedToUser.value) {
    return true;
  }

  return false;
});

const canForward = computed(() => {
  const status = claimStatus.value;

  // Case 1: Initiate permission + Submitted status
  if (hasInitiatePermission.value && status === 'Submitted') {
    return true;
  }

  // Case 2: Review permission + Initiated status + Assigned to user
  if (hasReviewPermission.value && status === 'Initiated' && isAssignedToUser.value) {
    return true;
  }

  return false;
});

const canApprove = computed(() => {
  const status = claimStatus.value;

  // Case 5: Approve permission + In Progress status + Assigned to user
  if (hasApprovePermission.value && status === 'In Progress' && isAssignedToUser.value) {
    return true;
  }

  return false;
});

const canReject = computed(() => {
  const status = claimStatus.value;

  // Case 6: (Approver OR Review) permission + (In Progress OR Initiated) status + Assigned to user
  const hasPermission = hasApprovePermission.value || hasReviewPermission.value;
  const validStatus = ['In Progress', 'Initiated'].includes(status);

  return hasPermission && validStatus && isAssignedToUser.value;
});

console.table({
  INITIATE: hasPermission(PERMISSIONS.TADACLAIM.INITIATE),
  REVIEW: hasPermission(PERMISSIONS.TADACLAIM.REVIEW),
  assigned: assignedTo.value,
  user: user_ids,
  status: claimStatus.value,
  canForward: canForward.value,
});

// Validation
const { errors: errorsApproval, validateAll } = useValidation(
  approvalForm,
  { draft_id: approvalNoticeValSchema.approve.draft_id }
);
formFieldValidator(approvalForm, validateAll);

// Methods
const tabClass = tab => activeTab.value === tab ? 'tab active' : 'tab';

const enableSplitPanel = () => {
  showSplitPanel.value = true;
};

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')} Min`;
};

// Load last active draft
const loadLastDraft = () => {
  const lastDraft = draftDropdown.value.find(d => d.status === 1);
  if (lastDraft) {
    approvalForm.draft_id = lastDraft.value;
    approvalForm.template_id = lastDraft.value || '0';
    approvalForm.content = htmlTemplateString;
  }
};

// Ensure draft is loaded before modal opens
const ensureLastDraft = () => {
  if (!approvalForm.template_id || !approvalForm.content) {
    loadLastDraft();
    console.log('Draft loaded before modal open', approvalForm);
  }
};

const openReviewModal = () => {
  ensureLastDraft();
  showReviewModal.value = true;
};

const openTimelineModal = () => {
  ensureLastDraft();
  showTimelineModal.value = true;
};

const openApproveModal = () => {
  ensureLastDraft();
  showApproveModal.value = true;
};

const loadApprovalView = async () => {
  loading.page = true;
  try {
    const res = await fetchApprovalView({ params: { module_id: moduleId.value } });
    module.value = res?.data ?? {};
  } finally {
    loading.page = false;
  }
};

const searchSendTo = debounce(async (search = '') => {
  loading.reviewUser = true;
  try {
    const res = await fetchSendTo({ params: { search } });
    reviewUsers.value = (res?.data?.data || []).map(u => ({
      label: u.full_name,
      value: u.core_user_id,
    }));
  } finally {
    loading.reviewUser = false;
  }
}, 500);

const validateBeforeSubmit = () => {
  const fullData = apiStore.tada_claim?.detail || {};
  const daAmount = Number(fullData.da_details?.da_amount) || 0;
  const digitalAmount = Number(fullData.digital_amount) || 0;
  const touchedItems = fullData.touched_items || [];

  const amount = Math.ceil(
    fullData.claim_items
      ?.filter(item => item.checked)
      .reduce((sum, item) => sum + Number(item.price) * Number(item.qty), 0) || 0
  );

  const payableAmount = daAmount + digitalAmount;

  if (!approvalForm.template_id || !approvalForm.content) {
    console.log('Template ID or content missing', approvalForm.template_id, approvalForm.content);
    return 'Something Went Wrong';
  }

  if (touchedItems.length) {
    return 'Please add remark/note for claim item.';
  }

  if (amount > 0 && (payableAmount !== daAmount + digitalAmount || payableAmount === 0)) {
    return payableAmount === 0
      ? 'In claim detail, The Payable Amount should not be empty.'
      : 'The combined total of the Digital Amount and the ICT Amount must equal the Payable Amount.';
  }

  return null;
};

const submitClaimCore = async ({ action, eSign = false, successMessage = '' }) => {
  ensureLastDraft();

  if (isSubmitted.value) return false;

  // Validate
  const errorMessage = validateBeforeSubmit();
  if (action === 'submit' && !form.review_to) {
    showAlert({ isError: true, message: 'Please select user' });
    return false;
  }

  if (errorMessage) {
    showAlert({ isError: true, message: errorMessage });
    return false;
  }

  // Confirm
  const confirm = await Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to proceed with this action?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, confirm it!',
    cancelButtonText: 'Cancel',
  });

  if (!confirm.isConfirmed) return false;

  isSubmitted.value = true;

  const fullData = apiStore.tada_claim?.detail || {};
  const daAmount = Number(fullData.da_details?.da_amount) || 0;
  const digitalAmount = Number(fullData.digital_amount) || 0;
  const ictAmount = Number(fullData.ict_amount || 0);

  finalPayload.value = {
    action,
    module_id: moduleId.value,
    draft_id: approvalForm.draft_id,
    template_content: approvalForm.content || 'Testing Content',
    template_id: approvalForm.template_id,
    tada_amount: daAmount,
    digital_amount: digitalAmount,
    ict_amount: ictAmount,
    payble_amount: daAmount + digitalAmount,
    send_to: form.review_to,
  };

  if (eSign) {
    const res = await processEsign(claimId.value, finalPayload.value);
    if (res.isError) {
      showAlert({ isError: true, message: res.message });
      isSubmitted.value = false;
      return;
    }
    showApproveModal.value = false;
    approveData.value = res.data;
    scrollToSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    findSignStatus();
  } else {
    processClaimData(successMessage);
  }
};

const sendToReviewUser = async () => {
  await submitClaimCore({
    action: 'submit',
    successMessage: 'Claim forwarded successfully',
  });
};

const sendToBack = async () => {
  await submitClaimCore({
    action: 'sendback',
    successMessage: 'Claim sent back successfully',
  });
};

const approvedSubmit = async (eSign = false) => {
  await submitClaimCore({
    action: 'approve',
    eSign,
    successMessage: 'The claim has been approved successfully',
  });
};

// E-Sign
const startCountdown = () => {
  if (countdown.value === 0) countdown.value = 300;
  countdownInterval = setInterval(() => {
    if (countdown.value > 0) countdown.value--;
    else {
      clearInterval(countdownInterval);
      clearInterval(esignStatusIntervalId.value);
    }
  }, 1000);
};

const handleSendNotification = async () => {
  const res = await resendEsignNotification(approveData.value.media_id);
  if (!res.isError) {
    approveData.value.esign_request_id = res.data;
    findSignStatus();
  }
};

const findSignStatus = () => {
  startCountdown();
  esignStatusIntervalId.value = setInterval(async () => {
    if (!approveData.value.esign_request_id) return;
    const res = await checkeSignStatus(approveData.value.esign_request_id);
    if (!res.isError && res.success_code === 200) {
      clearInterval(esignStatusIntervalId.value);
      processClaimData('The claim has been approved successfully');
    }
  }, 3000);
};

const handleBack = () => {
  clearInterval(countdownInterval);
  clearInterval(esignStatusIntervalId.value);
  approveData.value = { esign_request_id: '', media_id: 0, txn: '' };
};

// Final Process
const processClaimData = async (successMsg = '') => {
  const res = await processClaim(claimId.value, finalPayload.value);
  isSubmitted.value = false;
  approveData.value = { esign_request_id: '', media_id: 0, txn: '' };

  if (res.isError) {
    showAlert({ isError: true, message: res.message });
    return;
  }

  showAlert({ isError: false, message: successMsg });
  router.push({ name: 'TADAClaims' });
};

const showAlert = ({ isError, message }) => {
  Swal.fire({
    icon: isError ? 'error' : 'success',
    title: "TADA Claim: Detail",
    text: message,
    confirmButtonText: "OK",
  });
};

// Drafts
const loadDrafts = async () => {
  const res = await getDraftAll(moduleId.value);
  if (!res?.success) return;

  draftDropdown.value = res.data.map(d => ({
    value: d.id,
    label: `Version ${d.version} (Draft ${d.draft_no})`,
    content: d.content,
    status: d.status,
  }));

  loadLastDraft();
};

const selectApprovalDraft = (draftId) => {
  const draft = draftDropdown.value.find(d => d.value === draftId);
  if (draft) {
    approvalForm.draft_id = draft.value;
    approvalForm.content = draft.content;
    approvalForm.template_id = draft.value;
  }
};

const pullToBack = async () => {
  loading.page = true;
  try {
    const response = await PullBackApproval(moduleId.value);
    if (!response?.success) {
      fireToast({ type: 'error', message: response?.message });
      return;
    }
    fireToast({ type: 'success', message: response.message });
    await loadApprovalView();
  } catch (error) {
    fireToast({
      type: 'error',
      message: error?.response?.data?.message || error?.message || 'Server error occurred.',
    });
  } finally {
    loading.page = false;
  }
};

// Lifecycle
onMounted(async () => {
  await loadApprovalView();
  await loadDrafts();
  searchSendTo();
});
</script>

<style scoped>
.action-btn {
  padding: 2px 10px;
  border-radius: 5px;
}

.action-btn.warning {
  color: #979701;
  border: 1px solid #979701;
  background: transparent;
}

.tab-header {
  display: flex;
  gap: 8px;
  padding: 6px;
  background: #f9fafb;
  border-radius: 8px;
}

.tab {
  padding: 8px 20px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.tab.active {
  background: #e5e7eb;
  border-radius: 6px;
  color: #111827;
}
</style>