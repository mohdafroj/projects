<template>
  <Loading v-if="isLoading" />
  <form>
    <Modal v-model="isVisible" :title="modalTitle" subtitle="Add your verification comments and forward to approver"
      size="xl" @close="closeModal" :isLoading="isLoading">
      <!-- Modal Body Content -->
      <!-- form start-->

      <div class="space-y-6">
        <!-- Request Summary -->
        <!-- <pre>=={{ props.requests }}</pre>    -->
        <div v-if="MemberTempplateData">
          <h4 class="text-lg font-semibold text-gray-900 mb-4">
            Request Summary
          </h4>
          <div class="space-y-2 bg-gray-100 p-3 mt-2 rounded-lg">
            <p class="text-sm text-gray-800">
              <span class="font-medium ">MP:</span> {{ MemberTempplateData?.member_name_en ?? '-' }}
            </p>
            <p class="text-sm text-gray-800">
              <span class="font-medium text-gray-800">Type:</span> {{ MemberTempplateData?.icNo ?? 'New Request' }}
            </p>
            <p class="text-sm text-gray-800">
              <span class="font-medium text-gray-800">Constituency:</span> {{
                MemberTempplateData?.election_nomination_state ?? '-' }}
            </p>
          </div>
        </div>
        <div v-else> No data available. </div>
        <RichTextEditor v-if="isShowTemplate" ref="childRef" :requests="props.requests" v-model="documentContent"
          title="" @save="handleSave" @sendTemplateData="handleTemplateHtml" :fileParams=props.requests />
        <!-- <pre>=={{ props.requests }}</pre> -->

        <!-- Verification Comments -->
        <div>
          <h4 class="text-lg font-semibold text-gray-900 mb-4">
            Remarks
          </h4>
          <div>
            <textarea v-model="verificationComments"
              class="w-full p-3 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
              rows="4"
              placeholder="Add your verification comments, document check status, and recommendations..."></textarea>
          </div>
        </div>
      </div>


      <!-- Modal Footer -->
      <template #footer>
        <Button :isLoading="isLoading" label="Forward to Approver" color="green" size="sm"
          @click="handleVerifyAndForward(props.requests)" />
        <!-- </div> -->
      </template>
      <!-- form end-->
    </Modal>
  </form>
  <!-- <ThankyouTemplate v-if="showThankYouModal" @close="showThankYouModal = false" />        -->
</template>

<script setup>
import { ref, computed, onMounted, watch, toRaw, nextTick, defineProps } from 'vue';
import { saveDetailsForwardByApprover } from '@/services/rss/cghsServices';
import { Button, Modal } from '@sds/oneui-common-ui';
import { dbDateFormat } from '@/utils/dateFormat'
import PdfPreviewModal from '@/components/PdfPreviewModal.vue'
import LetterTemplate from './LetterTemplate';
import { Loading } from '@sds/oneui-common-ui';
import html2pdf from 'html2pdf.js';
import RichTextEditor from './RichTextEditor.vue'
// import { jsPDF } from "jspdf";
import Swal from 'sweetalert2'
const isLoading = ref(false);
const documentContent = ref('')
const lastSaved = ref('');

const clearContent = () => {
  documentContent.value = ''
  lastSaved.value = ''
}

const handleSave = (data) => {
  console.log('Document saved:', data)
  lastSaved.value = new Date().toLocaleString()
}
//Rich editor

const handleTemplateHtml = (html) => {
  //console.log('Received from child:', html);
  //documentContent.value = props.requests;
  //console.log('sssssds',html)
  if (!html || html === 'blank') {
    documentContent.value = '';
  } else {
    documentContent.value = MemberTempplateData.value?.template_data ?? ''; // set to template html content
  }// or do whatever you want with it
};


//================function to convert html to pdf end ======//
const generatePdfBlob = (pdfContent) => {
  const container = document.createElement('div');
  container.innerHTML = pdfContent;


  // Apply styles to avoid cutoff
  container.style.width = '210mm'; // A4 width
  container.style.minHeight = '200mm'; // A4 height
  container.style.padding = '10mm';
  container.style.boxSizing = 'border-box';
  container.style.fontSize = '12pt';
  container.style.fontFamily = 'Arial, sans-serif';
  container.style.lineHeight = '1.6';

  return html2pdf()
    .from(container)
    .outputPdf('blob');  // This returns a Promise resolving to a Blob
};

function blobToBase64(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result.split(',')[1]); // just base64 without prefix
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}
//================function to convert html to pdf end ======//

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  request: {
    type: Object,
    default: null,
  },
  requests: {
    type: Object,
    default: () => ({})
  },
  visible: Boolean,
  title: String,
  tableTitle: String,
  editorHtml: String,
  tableData: {
    type: Array,
    default: () => []
  },
  columns: {
    type: Array,
    default: () => []
  },
  filename: String,
  isDownloadPdf: Boolean,
  triggerPdfGenerate: Boolean
});


const modalTitle = ref('Loading...');

watch(() => props.requests, (newRequests) => {
  if (newRequests?.msa_cghs_card_request_no) {
    modalTitle.value = `Request ID: ${newRequests.msa_cghs_card_request_no}`;
  }
}, { immediate: true });
const childRef = ref(null)

const MemberTempplateData = computed(() => props.requests?.member_details || '');
const isShowTemplate = ref(true)
const emit = defineEmits(['update:modelValue', 'close', 'verify', 'reject']);
const verificationComments = ref('');
const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const closeModal = () => {
  verificationComments.value = '';
  emit('update:modelValue', false);
  emit('close');
};

const handleVerifyAndForward = async (request) => {
  isLoading.value = true;
  const postData = {};
  const data = {
    request: props.request,
    comments: verificationComments.value,
    action: 'verified'
  };

  if (!data.comments.trim()) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Approval Comments are required!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    isLoading.value = false;
    return;
  }

  if (!documentContent.value.trim()) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Template document must be filled!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    isLoading.value = false;
    return;
  }

  if (!request?.msa_cghs_card_request_no.trim() || request?.msa_cghs_card_request_no.trim() == '') {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Cghs card no is required, here card no is empty!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    isLoading.value = false;
    return;
  }
  try {
    postData.member_card_api_data = '';
    postData.member_card_template = documentContent.value
    postData.msa_cghs_card_request_no = request?.msa_cghs_card_request_no ?? '';
    postData.blood_group = request?.member_details?.member_details?.blood_grp ?? '';
    postData.remarks = data.comments;
    postData.member_name = request?.member_details?.member_name_en ?? '';
    postData.ic_no = request?.member_details?.icNo ?? '';
    postData.election_date = dbDateFormat(request?.member_details?.terms_start_date) ?? '';
    postData.election_expire_date = dbDateFormat(request?.member_details?.terms_end_date) ?? '';
    postData.election_nomination_state = request?.member_details?.election_nomination_state ?? '';
    postData.dob = dbDateFormat(request?.member_details?.member_details?.dob) ?? '';
    postData.mobile = request?.member_details?.member_details?.mobile ?? '';
    postData.email = request?.member_details?.member_details?.email ?? '';
    postData.parent_wellness_center = request?.member_details?.member_details?.wellness_center ?? '';
    postData.member_card_deta = JSON.stringify(request?.member_details) ?? '';
    postData.relation = request?.member_details?.member_details?.relation ?? '';
    postData.member_id = request?.member_id ?? ''; request['member_id'] ?? '';
    // console.log('post data==', postData); 
    await verAndFrwddata(postData);
    emit('verify', data);
    verificationComments.value = '';
    closeModal();
  } catch (err) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Failed to forward. Please try again",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    })
    console.error('Error forwarding request:', err);
    //alert('Failed to forward. Please try again.');
  } finally {
    isLoading.value = false;
  }
  // emit('verify', data);
  // verificationComments.value = '';
  // const templateDt = ''; 
};
const showThankYouModal = ref(false);

const verAndFrwddata = async (payload) => {
  try {
    const response = await saveDetailsForwardByApprover(payload);
    //console.log('tryyy block', response)
    if (response.success_code === 200) {
      showThankYouModal.value = true
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: "Card request submitted successfully!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    } else if (response.error_code === 1907) {
      Swal.fire({
        icon: 'warning',
        // title: 'Please confirm your action',
        text: response.description,
        showCancelButton: false,
        //confirmButtonText: false,
        cancelButtonText: 'Ok',
        showCloseButton: true,
        closeButtonHtml: '<i class="fa fa-times"></i>',
        focusConfirm: false,
        focusCancel: false,
        customClass: {
          popup: 'my-popup',
          icon: 'my-icon',
          closeButton: 'my-close-btn'
        }
      });
      isLoading.value = false;
      console.log('error in saving data', response);
    } else {

      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Error in your card, please check!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
      isLoading.value = false;

      console.log('error in saving data', response);
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
  }
}

const handleReject = () => {
  const data = {
    request: props.request,
    comments: verificationComments.value,
    action: 'rejected'
  };
  emit('reject', data);
  verificationComments.value = '';
};
</script>