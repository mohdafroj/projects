<template>
  <Modal
    v-model="isVisible"
    :title="modalTitle"  
    size="xl"
    @close="closeModal"
  >
    <form>
      <!-- Modal Body Content -->
      <div class="space-y-6">
        <!-- Request Summary -->
        <div>
          <h4 class="text-lg font-semibold text-gray-900 mb-4">
            Request Summary
          </h4>
          <div class="space-y-1 bg-gray-100 p-3 mt-2 rounded-lg">
            <p class="text-sm text-gray-800">
              <span class="font-medium">MP:</span> {{ request?.member_name??'' }}
            </p>
            <p class="text-sm text-gray-800">
              <span class="font-medium text-gray-800">Type:</span> {{ request?.type??'New Request' }}
            </p>
            <p class="text-sm text-gray-800">
              <span class="font-medium text-gray-800">Constituency:</span>{{ request?.election_nomination_state??'-' }}
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
                <button type="button" @click="OpenRemarks(request?.msa_cghs_card_request_no)" class="text-xs text-blue-600 hover:text-blue-800">View All Remark</button>
              </div>

              <transition @before-enter="beforeEnter" @enter="enter" @leave="leave">
                <div v-show='show[request?.msa_cghs_card_request_no?.replace("Member of Parliament, Request Id - ", "")]' ref="content" class="slide-content">
                  <RemarkSlider :remarks='remk[request?.msa_cghs_card_request_no?.replace("Member of Parliament, Request Id - ", "")] || []'/>
                </div>
              </transition>

              <p class="text-sm text-gray-600 overflow-hidden text-ellipsis whitespace-nowrap w-[400px] block">{{ request.comment }}</p>
            </div>
          </div>
          
          <!----- Editor section start ------>
          <RichTextEditor
            ref="childRef" :requests="props.requests"
            v-model="props.request.member_card_template"
            title=""
            @save="handleSave"
            @sendTemplateData="handleTemplateHtml"
            :fileParams=props.requests  
          /> 
          <!-------- Editor section end ----->
        </div>

        <PdfPreviewModal 
          :visible="showPreview" 
          :title="'CGHS Member Cards'"
          :tableTitle="'Family Member'" 
          :editor-html="editorContainer" 
          :isDownloadPdf="false"
          :filename="CGHSFILE" 
          @close="showPreview = false" 
          @blobData="handleBlobData"
          :trigger-pdf-generate="isPdfSend" 
        />

        <FileUploads 
          ref="uploadRef" 
          :isRequired="true" 
          :onFileUpload="postMethod" 
          :multiple="false" 
          class="hidden" 
          @update:files="handleFileUpload" 
        />

        <div>
          <h4 class="text-lg font-semibold text-gray-900 mb-4">
            Remarks
          </h4>
          <div class="mt-2">
            <textarea
              v-model="rejectionComments"
              class="w-full p-3 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
              rows="4"
              placeholder="Add your remark"
            ></textarea>
          </div>
        </div>
      </div>
    </form>

    <!-- Modal Footer -->
    <template #footer>
      <Button 
        label="Send Back To Member"  
        :disabled="isLoading"
        :loading="isLoading" 
        color="red" 
        size="sm" 
        @click="startApprovalProcess" 
      /> 
    </template>
  </Modal>
  
  <!---------waiting page----------->
  <EsignWaitingPage  v-if="isLoading" @resend-notification="resendNotification" />
  <!---------waiting page----------->
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Button, Modal, FileUploads } from '@sds/oneui-common-ui';
import EsignWaitingPage from '@/components/EsignWaitingPage.vue';
import { Icon } from '@iconify/vue';
import { Approver_action, getAllRemarks, eSign_approval } from '@/services/rss/cghsServices';
import RemarkSlider from '@/views/ma-card-status-approve/RemarkSlider.vue';
import Swal from 'sweetalert2'
import PdfPreviewModal from '@/components/PdfPreviewModal.vue';
import RichTextEditor from './RichTextEditor.vue'
import { postMethod } from "@/composables/useApi";

const showPreview = ref(false);
const CGHSFILE = ref('member_cghs_pdf_file');
const editorContainer = ref('');
const isLoading = ref(false);
const isPdfSend = ref(false);
const rejectionComments = ref('');
const showThankYouModalReject = ref(false);
const eSignResponse = ref({});
const remk = ref({});
const show = ref({});
const showModal_remark = ref(false); 
const lastBlob = ref(null);
const lastFormData = ref(null);
const hasShownEsignError = ref(false); 
const pollingIntervalId = ref(null);  
const approvalComments = ref('');
const uploadRef = ref(null);
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
});

const closeModal = async() => {
  console.log('closed called')
  rejectionComments.value = '';
  
  await nextTick(() => {
    emit('update:modelValue', false);
  });
  emit('close');

  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
  }

  isLoading.value = false;
  hasShownEsignError.value = false;
  uploadedFilePath.value = null; // Reset file path
};

const startApprovalProcess = () => {
  isLoading.value = true; 
  if (!rejectionComments.value || rejectionComments.value.trim() === '') { 
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Please provide rejection comment!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    }) 
    isLoading.value = false; 
    return false;
  }
 
  // ✅ Trigger PDF generation
  isPdfSend.value = true;
};

const handleFileUpload = (files) => {
  if (files && files.length > 0 && files[0].errors.length === 0) {
    uploadedFilePath.value = files[0].path; // This gets the uploaded file path
    console.log('PDF file uploaded successfully, path:', uploadedFilePath.value);
  } else {
    console.error('File upload failed:', files?.[0]?.errors);
  }
};

const handleBlobData = async(blob) => { 
  lastBlob.value = blob;
  isLoading.value = true;
  
  if (blob?.size > (64 * 1024 * 1000)) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: 'File Size is too large',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    isLoading.value = false;
    return;
  } 
  
  try {
    const data = {
      request: props.request,
      comments: rejectionComments.value,
      action: 'rejected'
    };

    const formData = new FormData();
    const requestId = props.request.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", "");     
    formData.append('msa_cghs_card_request_no', requestId);
    formData.append('type', 'R');
    formData.append('remarks', data.comments);   
    
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
    
    const remarksSend = data.comments;
    const res = await postApprover_action(formData, remarksSend);
    console.log('success REJECTEDDDDDD');
    
  } catch(er) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Error in saving data!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    er.value = er.res?.data?.message || er.message || 'Unknown';
    console.log('error', er.value);
    isLoading.value = false;
  } 
};

const postApprover_action = async(payload, remarksSend) => { 
  try { 
    const response = await Approver_action(payload); 
    if (response.success_code === 200) { 
      //==========esign api status check start==========//  
      const Edata = {
        "requestId": response.data?.requestId ?? '',
        "claim_id": response.data?.id ?? '',
        "mode": "R",
        "remarks": remarksSend
      };

      startESignPolling(Edata);
      //==========esign api status check start==========//
    } else {
      isLoading.value = false;
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Something went wrong in saving data!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      }) 
      console.log('error in saving data');
    }    
  } catch(err) {
    isLoading.value = false; 
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Error in saving data!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    }) 
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('Error!!', err.value);
  }
}

const startESignPolling = (edata) => {
  const pollingInterval = 10000; // every 10 seconds
  const timeoutLimit = 60000;   // 1 minute

  let attempt = 0;
  hasShownEsignError.value = false;

  // Clean up previous polling if any
  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
  }

  isLoading.value = true;

  const pollingFn = async () => {
    attempt++;

    try {
      console.log(`[Polling #${attempt}] Checking eSign status...`); 
      const res = await eSign_approval(edata);
       
      if (res.success_code === 200 && res.data.success && res.data.message === 'File signed successfully.') {
        clearInterval(pollingIntervalId.value);
        clearTimeout(timeoutId);
        pollingIntervalId.value = null;
        eSignResponse.value = res.data.file.data;
        
        // Close modal via method
        closeModal();  // this emits update:modelValue=false etc.

        // Ensure parent react to verify
        emit('verify');

        // Wait a tick to let the modal close
        await nextTick();
        
        Swal.fire({
          toast: true,
          position: "top-end",
          icon: "success",
          title: "Card Rejected Successfully!",
          showConfirmButton: false,
          timer: 3000,
        });

        showThankYouModalReject.value = true;
      } 
    } catch (err) {
      console.error("Polling error:", err);

      if (!hasShownEsignError.value) {
        hasShownEsignError.value = true; 
        Swal.fire({
          toast: true,
          position: "top-end",
          icon: "error",
          title: "Failed during eSign. Please try again.",
          showConfirmButton: false,
          timer: 3000,
        });
      }

      clearInterval(pollingIntervalId.value);
      clearTimeout(timeoutId);
      pollingIntervalId.value = null;
      isLoading.value = false;
    }
  };

  // Start polling
  pollingIntervalId.value = setInterval(pollingFn, pollingInterval);

  // Stop polling after timeout (1 min)
  const timeoutId = setTimeout(() => {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;

    if (!hasShownEsignError.value) {
      hasShownEsignError.value = true;
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "eSign timeout. Please try again.",
        showConfirmButton: false,
        timer: 3000,
      });
    }

    isLoading.value = false;
  }, timeoutLimit);
};

const resendNotification = () => {
  console.log('Resending notification...');
  isLoading.value = true;
  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
  }

  if (lastFormData.value) {
    postApprover_action(lastFormData.value, rejectionComments.value); // This will internally start polling again
  } else {
    console.warn('Form data missing, regenerating...');
    isPdfSend.value = false;
    nextTick(() => {
      isPdfSend.value = true;
    });
  }
};

const emit = defineEmits(['update:modelValue', 'close', 'verify']);
editorContainer.value = props.request?.member_card_template ?? '';

const modalTitle = ref('Loading...');

watch(() => props.request, (newRequests) => {
  if (newRequests?.msa_cghs_card_request_no) {
    const requestId = newRequests.msa_cghs_card_request_no.replace("Member of Parliament, Request Id - ", "");
    modalTitle.value = `Request ID: ${requestId}`;
  }
}, { immediate: true });

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

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
const OpenRemarks = async (reqid) => {
  if (!reqid) return;

  try {
    // Parse the id part safely
    const parsedId = reqid.replace("Member of Parliament, Request Id - ", "");
 
    if (!parsedId) {
      console.warn('Invalid reqid format:', reqid);
      return;
    }

    // Toggle visibility
    show.value[parsedId] = !show.value[parsedId];

    if (show.value[parsedId] && !remk.value[parsedId]) {
      await fetchRemarks(parsedId);
    } else {
      delete remk.value[parsedId];
    }
  } catch (err) {
    console.error('Error in OpenRemarks:', err);
  }
}

const fetchRemarks = async(reqid) => {
  try {  
    const res = await getAllRemarks(reqid);
    remk.value[reqid] = res.data; 
  } catch(er) {
    er.value = er.res?.data?.message || er.message || 'Unknown';
    console.log('error', er.value);
  }  
}

// These methods might be needed for RichTextEditor
const handleSave = () => {
  // Implementation for handleSave
}

const handleTemplateHtml = () => {
  // Implementation for handleTemplateHtml
}
</script>

<style scoped>
.slide-content {
  overflow: hidden;
} 
</style>