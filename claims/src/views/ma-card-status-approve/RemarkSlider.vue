<template>
       <!-- sads =kkkkk=<pre>{{props. remarks?.member_card_template  }}</pre>   -->
      
  <PdfPreviewModal :visible="showPreview" :editor-html="editorContainer"
        :isDownloadPdf="false" :filename="CGHSFILE" @close="showPreview = false" @blobData="handleBlobData"
        :trigger-pdf-generate="isPdfSend" />
  <div class="space-y-2 bg-gray-100 p-3 mt-2 rounded-lg">
        <div class="max-w-md mx-auto mt-6 space-y-6 text-sm text-gray-700 font-medium">
          <div class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div :class="[Cls,Cls_gr,'absolute left-0 top-0 w-4 h-4 rounded-full border-2'] "></div>
            <div>
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-800">Initiator</div>
                <div class="text-xs text-gray-400 mt-1">{{ remarks?.initiator_human_diff ?? '-' }}</div>
              </div>
              <p class="mt-1">{{ remarks?.initiator_remarks ?? 'No Remark' }}</p>
             
            </div>
          </div> 
          
          <div v-if="role=='approver'" class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div :class="[Cls_a,Cls_gr_a,'absolute left-0 top-0 w-4 h-4 rounded-full border-2 border-green-200 bg-green-500']"
            
            ></div>

            <div>
              <!-- ===<pre>{{ remarks }}</pre> -->
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-800">Approver</div>
                <div class="text-xs text-gray-400 mt-1">{{remarks?.approver_human_diff ?? '-' }}</div>
              </div>
              <p class="mt-1">{{remarks?.approver_remarks ?? 'No Remark By Approver' }}</p>
               <div v-if="role=='approver'" style="cursor: pointer;"
                class="mt-2 inline-flex items-center bg-gray-100 px-3 py-1 rounded-md text-sm" @click="openDocument()">
              
                <div class="d-flex " style="display: flex;" v-if="remarks?.cghs_signed_file">
                  <img src="/assets/images/logo/pdf.svg" alt="pdf" class="w-4 h-4 mr-2" />
                  <a :href="remarks?.cghs_signed_file" target="_blank" class=" h-4 mr-2">Attached Letter to Member.pdf
                </a></div> 
                <div>
                </div> 
              </div>
            </div>
          </div>
        </div>
      </div> 
   
</template>
<script setup>
import { defineEmits, computed, ref, watch,nextTick  } from 'vue'
import { Button } from '@sds/oneui-common-ui';
import Modal from './Modal.vue';
import { hasAnyPermission, hasPermission, PERMISSIONS } from "@/utils/rbac";
import PdfPreviewModal from '@/components/PdfPreviewModal.vue';
const emit = defineEmits(['close'])
const closeModal = () => {
  emit('update:modelValue', false);
  emit('close');
};


const isPdfSend = ref(false);

const showPreview = ref(false);
const CGHSFILE = ref('member_cghs_pdf_file');
const editorContainer = ref('');
const Cls = ref('');
const Cls_gr = ref('');
let role = ref('');
const Cls_a = ref();
const Cls_gr_a = ref('');

const props = defineProps({
  request: {
    type: Object,
    default: null,
  },
  requests: {
    type: Object,
    default: () => ({})
  },
  remarks: {
    type: Array,
    default: () => [],
  }
})


const handleBlobData = async (blob) => { 
  isPdfSend.value = false;
  const url = URL.createObjectURL(blob)
  window.open(url);
  showPreview.value = false; 
}


//function for open pdf as document
const openDocument = () => {
  editorContainer.value =props.remarks?.member_card_template ?? '';
  isPdfSend.value = true;
 
}

 if (hasPermission(PERMISSIONS.CGHS.MANAGE_CARD)) { 
   role.value='review';
   Cls.value = 'border-green-200 bg-green-500'
 }
 
 if (hasPermission([PERMISSIONS.CGHSAPPROVE.APPROVE])) {
  role.value='approver';
//  Cls_gr.value= 'bg-green-500'
    Cls.value = 'border-green-200 bg-green-500'
 }

 import { useRoute } from 'vue-router';

const route = useRoute();
const checkInitiator = ref(route.path);
//console.log('Full URL path:', route.fullPath);      // e.g., /dashboard?tab=1


 
const modalTitle = ref('Loading...');


watch(() => props.requests, (newVal) => {
  if (newVal?.req_id) {
    modalTitle.value = `#REQ No.  ${newVal.req_id}`;
  }
}, { immediate: true });



 
</script>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.textcls {
  width: 400;
  height: 48px;
  top: 207.42px;
  font-weight: 700;
  size: 20px;
  line-height: 27px;
  letter-spacing: 0px;
  padding-left: 17px;
  margin-top: 18px;
  text-align: center;

} 


a {
  color: #4e4e6a;
  font-size: 13px;
  font-weight: bold;
  font-family: sans-serif;
  text-decoration: none; /* optional: removes underline */
}

a:hover {
  color: #ff6600; /* change this to whatever hover color you want */
}
</style>
