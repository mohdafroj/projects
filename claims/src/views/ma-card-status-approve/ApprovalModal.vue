<template>
  <Modal v-model="isVisible" :title="modalTitle" size="xl" @close="closeModal">
    <form>
      <!-- Modal Body Content -->
      <div class="space-y-6">
        <!-- Request Summary -->
        <!-- ===<pre>{{ props.remarks }}</pre>   -->
        <div>
          <h4 class="text-lg font-semibold text-gray-900 mb-4">
          </h4>
          <div class="space-y-1 bg-gray-100 p-3 mt-2 rounded-lg">
            <p class="text-sm text-gray-800">
              <span class="font-medium">MP:</span> {{ request?.member_name ?? '' }}
            </p>
            <p class="text-sm text-gray-800">
              <span class="font-medium text-gray-800">Type:</span> {{ request?.request_type ?? 'New Request' }}
            </p>
            <p class="text-sm text-gray-800">
              <span class="font-medium text-gray-800">Constituency:</span> {{ request?.state_name ?? '-' }}
            </p>
          </div>
        </div>

        <!-- Verification Comments -->
        <div class="bg-blue-50 rounded-lg p-3 md:min-w-[450px]">
          <div class="flex items-start space-x-2">
            <div class="bg-blue-100 p-1 rounded">
              <Icon icon="material-symbols:comment" class="w-4 h-4 text-blue-600" />
            </div>

            <div class="flex-1">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-gray-700">Initiator Comment:</span>
                <!-- <button type="button" @click="show = !show" 
                
                class="text-xs text-blue-600 hover:text-blue-800">View All
                  Remark</button> -->

                <button type="button"
                  @click='OpenRemarks(props.request.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", ""))'
                  class="text-xs text-blue-600 hover:text-blue-800">View All Remark</button>
              </div>
              <p class="text-sm text-gray-600 overflow-hidden text-ellipsis whitespace-nowrap w-[400px] block">{{
                request.comment }}</p>
            </div>
          </div>
          <div>
          </div>
        </div>

        <transition @before-enter="beforeEnter" @enter="enter" @leave="leave">
          <div v-show='show[request?.msa_cghs_card_request_no?.replace("Member of Parliament, Request Id - ", "")]'
            ref="content" class="slide-content">
            <!-- Your remarks or comment content here -->
            <RemarkSlider
              :remarks='remk[request?.msa_cghs_card_request_no?.replace("Member of Parliament, Request Id - ", "")] || []' />
          </div>
        </transition>
        <!----- Editor section start ------>
        <RichTextEditor ref="childRef" :requests="props.requests" v-model="editorContainer" title="" @save="handleSave"
          @sendTemplateData="handleTemplateHtml" :fileParams=props.requests />
        <!-------- Editor section end ----->


        <div>
          <h4 class="text-lg font-semibold text-gray-900 mb-4">
            Approval Comments
          </h4>

          <div class="mt-2">
            <textarea v-model="approvalComments"
              class="w-full p-3 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
              rows="4" placeholder="Add your approval comments"></textarea>
          </div>
        </div>
      </div>
      <PdfPreviewModal :title="'CGHS Member Cards'" :visible="showPreview" :editor-html="editorContainer"
        :isDownloadPdf="false" :filename="CGHSFILE" @close="showPreview = false" @blobData="handleBlobData"
        :trigger-pdf-generate="isPdfSend" />
      <FileUploads ref="uploadRef" :isRequired="true" :onFileUpload="postMethod" :multiple="false" class="hidden" 
@update:files="handleFileUpload" />
    </form>
    <!-- Modal Footer -->
    <template #footer>
      <!-- <Button label="Approve" color="green" size="sm" @click="handleApproval(props.request)" /> -->
      <Button :disabled="isLoading" :loading="isLoading" label="Approve" color="green" size="sm"
        @click="startApprovalProcess" />

    </template>

  </Modal>
  <!---------waiting page----------->
  <EsignWaitingPage v-if="isLoading" :key="isLoading" @resend-notification="resendNotification" />
  <!---------waiting page----------->
  <!-- <ThankyouTemplate v-if="showThankYouModal" @close="showThankYouModal = false" /> -->



</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import EsignWaitingPage from '@/components/EsignWaitingPage.vue';
import { Button, Modal, FileUploads } from '@sds/oneui-common-ui';
// import Modal from './Modal.vue';
import { Icon } from '@iconify/vue';
import { Approver_action, eSign_approval, getAllRemarks } from '@/services/rss/cghsServices';
// import ThankyouTemplate from '@/views/ma-card-status/ThankyouTemplate.vue';
import RemarkSlider from './RemarkSlider.vue';
import Swal from 'sweetalert2'
import PdfPreviewModal from '@/components/PdfPreviewModal.vue';
import RichTextEditor from './RichTextEditor.vue'
const isLoading = ref(false);
const isPdfSend = ref(false);
const uploadRef = ref(null);
const showPreview = ref(false);
const CGHSFILE = ref('member_cghs_pdf_file');
import { postMethod } from "@/composables/useApi";
const showModal_remark = ref(false);
const editorContainer = ref('');
const lastBlob = ref(null);
const lastFormData = ref(null);
const hasShownEsignError = ref(false);
const showThankYouModal = ref(false);
const eSignResponse = ref({});
const pollingIntervalId = ref(null);
const show = ref({});
const approvalComments = ref('');
const uploadedFilePath = ref(null);
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  request: {
    type: Object,
    default: null,
  },
  remarks: {
    type: Array,
    default: () => [],
  }
});

editorContainer.value = props.request?.member_card_template ?? '';
const toggleRemarks = (request) => {
  showModal_remark.value = true;
}
const emit = defineEmits(['update:modelValue', 'close', 'verify']);
const modalTitle = ref('Loading...');
//modalTitle.value =`Request ID`;//MemberData?.msa_cghs_card_request_no;

const reqIDCore = ref('');
watch(() => props.request, (newRequests) => {
  if (newRequests?.msa_cghs_card_request_no) {
    //const requestId = newRequests.msa_cghs_card_request_no.split('-')[1].trim();

    let requestId = props.request.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", "");
    // console.log('ddddddddddddddddddddddddd',requestId );
    modalTitle.value = `Request ID: ${requestId}`;
  }
}, { immediate: true });


const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const closeModal = () => {
  //  approvalComments.value = '';
  emit('update:modelValue', false);
  emit('close');
  uploadedFilePath.value = null; // Reset file path

  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
  }

  isLoading.value = false;
  hasShownEsignError.value = false;
};

//pdf creation and sending //

const startApprovalProcess = () => {

  const requestId = props.request.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", "");
  console.log('ssss', requestId)
  if (!approvalComments.value || approvalComments.value.trim() === '') {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Comment is required for Approval/Rejection!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    })
  } else if (!requestId || requestId == '') {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: `Cghs Card no is required to proceed the request! 
      Currently request has no card no.!`,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  } else {
    isLoading.value = true;
    isPdfSend.value = true;

  }
  uploadedFilePath.value = null;
};

//pdf creation and sending//
// const handleBlobData = async (blob = '') => {
//   lastBlob.value = '';
//   isLoading.value = true;
//   try {
//     const data = {
//       request: props.request,
//       comments: approvalComments.value,
//       action: 'verified'
//     };
//     // approvalComments.value = '';  
//     const formData = new FormData();
//     let requestId = '';
//     requestId = props.request.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", "");

//     formData.append('msa_cghs_card_request_no', requestId);
//     formData.append('remarks', data.comments);
//     formData.append('type', 'A');
//     formData.append('card_template_file', blob);
//     isPdfSend.value = false;
//     //==================form data================// 
//     lastFormData.value = formData;
//     //==================form data================//
//     const res = await postApprover_action(formData);


//   } catch (er) {
//     Swal.fire({
//       toast: true,
//       position: "top-end",
//       icon: "error",
//       title: `Error in saving data! ${er}`,
//       showConfirmButton: false,
//       timer: 3000,
//       timerProgressBar: true,
//     })
//     er.value = er.res?.data?.message || er.message || 'Unknown';
//     console.log('error', er.value);
//     isLoading.value = false;
//   }
// };


const handleFileUpload = (files) => {
  if (files && files.length > 0 && files[0].errors.length === 0) {
    uploadedFilePath.value = files[0].path; // This gets the uploaded file path
    console.log('PDF file uploaded successfully, path:', uploadedFilePath.value);
  } else {
    console.error('File upload failed:', files?.[0]?.errors);
  }
};


const handleBlobData = async (blob = '') => { 
  lastBlob.value = '';
  isLoading.value = true;
  try {
    const data = {
      request: props.request,
      comments: approvalComments.value,
      action: 'verified'
    }; 
    
    const formData = new FormData();
    let requestId = '';
    requestId = props.request.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", "");
      
    formData.append('msa_cghs_card_request_no', requestId);
    formData.append('remarks', data.comments);
    formData.append('type', 'A');
    
    // ✅ Convert blob to file and upload to get path
    if (blob) {
      const fileName = `CGHS_Card_${requestId}_${Date.now()}.pdf`;
      const pdfFile = new File([blob], fileName, { type: 'application/pdf' });
      
      // Upload the file using FileUploads component to get the path
      uploadRef.value.customUpload([pdfFile]);
      
      // Wait for file upload to complete and get the path
      await new Promise((resolve) => {
        const checkUpload = setInterval(() => {
          if (uploadedFilePath.value) {
            clearInterval(checkUpload);
            formData.append('card_template_file', uploadedFilePath.value); // ✅ Send path instead of blob
            resolve();
          }
        }, 100);
      });
    }
    
    isPdfSend.value = false;
    lastFormData.value = formData; 
    
    const res = await postApprover_action(formData); 

  } catch (er) {  
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: `Error in saving data! ${er}`,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    })
    er.value = er.res?.data?.message || er.message || 'Unknown';
    console.log('error', er.value);
    isLoading.value = false;
  }
};

const postApprover_action = async (payload) => {
  try {
    const response = await Approver_action(payload);
    if (response.success_code === 200) {
      closeModal();
      emit('verify');
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: "Request has been sent to CGHS!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
      showThankYouModal.value = true;
      const Edata = {
        claim_id: response.data?.id ?? '',
        requestId: response.data?.requestId ?? '',
        mode: 'A',
      };
      startESignPolling(Edata); // Clean polling starts here
    }
    else if (response.isError === false && response.error_code) {
      isLoading.value = false;

      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: response.description,
        showConfirmButton: false,
        timer: 8000,
        timerProgressBar: true,
      });
    } else {
      isLoading.value = false;
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: `Error in saving data! ${response.error}`,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
  } catch (err) {
    isLoading.value = false;
    console.error("Error posting approval:", err.message || err);

    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: `Failed to forward. error: ${err.message}`,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }
};

const startESignPolling = (edata) => {
  const pollingInterval = 10000; // 10 seconds
  const timeoutDuration = 60000; // 1 minute (60 seconds)
  let pollingAttempts = 0;

  // Reset flags
  hasShownEsignError.value = false;
  isLoading.value = true;

  // Clear any existing polling
  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
  }

  // Start timeout countdown (like Paytm OTP timeout)
  const timeoutId = setTimeout(() => {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
    isLoading.value = false;

    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "eSign request timed out. Please try again.",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }, timeoutDuration);

  // Start polling every 10s
  pollingIntervalId.value = setInterval(async () => {
    pollingAttempts++;
    console.log(`Polling attempt ${pollingAttempts}`);

    try {
      const pollingPayload = {
        ...edata,
        remarks: approvalComments.value.trim() || '',
      };

      const esignRes = await eSign_approval(pollingPayload);

      if (esignRes.success_code === 200) {
        const { success, message, file } = esignRes.data;

        if (success && message === 'File signed successfully.') {
          eSignResponse.value = file.data;
          clearInterval(pollingIntervalId.value);
          clearTimeout(timeoutId);
          pollingIntervalId.value = null;
          isLoading.value = false;

          closeModal();
          emit('verify');
          Swal.fire({
            toast: true,
            position: "top-end",
            icon: "success",
            title: "Request has been sent to CGHS!",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
          });

          showThankYouModal.value = true;
        }
      }
    } catch (err) {
      console.error("Polling error:", err.message || err);

      clearInterval(pollingIntervalId.value);
      clearTimeout(timeoutId);
      pollingIntervalId.value = null;
      isLoading.value = false;

      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Error during eSign polling",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
  }, pollingInterval);
};


const resendNotification = () => {
  console.log('Resending notification...');
  isLoading.value = true;

  if (lastFormData.value) {
    postApprover_action(lastFormData.value); // This will internally start polling again
  } else {
    console.warn('form data missing, regenerating...');
    isPdfSend.value = false;
    nextTick(() => {
      isPdfSend.value = true;
    });
  }
};



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

///remarks
const OpenRemarks = async (reqid) => {
  if (!reqid) return;

  try {
    // Parse the id part safely
    const parsedId = reqid;
    // console.log('kam',parsedId);
    if (!parsedId) {
      console.warn('Invalid reqid format:', reqid);
      return;
    }
    console.log('asasasas', show.value[parsedId]);
    // Toggle visibility
    show.value[parsedId] = !show.value[parsedId];
    // show.value = !show.value;

    if (show.value[parsedId] && !remk.value[parsedId]) {
      // Fetch remarks only if opening
      await fetchRemarks(parsedId);
    } else {
      // Clear remarks if closing
      remk.value = [];
    }
  } catch (err) {
    console.error('Error in OpenRemarks:', err);
  }
}
const remk = ref({});
const fetchRemarks = async (reqid) => {
  try {
    const res = await getAllRemarks(reqid);
    remk.value[reqid] = res.data;
    console.log('REMARKS getttt-->>', remk.value);
  } catch (er) {
    er.value = er.res?.data?.message || er.message || 'Unknown';
    console.log('error', er.value);
  }
}
</script>

<style scoped>
.slide-content {
  overflow: hidden;
}
</style>
