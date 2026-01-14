<template>
  <Modal :title="modalTitle" subtitle="Add your verification comments and forward to approver" size="lg"
    @close="closeModal">
    <div class="space-y-6">
      <h4 class="text-lg font-semibold text-gray-900 mb-4"></h4>
       <!-- ===<pre>{{ props.requests }}</pre> -->
      <div class="space-y-2 bg-gray-100 p-3 mt-2 rounded-lg">
        <div class="max-w-md mx-auto mt-6 space-y-6 text-sm text-gray-700 font-medium">
          <div class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div class="absolute left-0 top-0 w-4 h-4 rounded-full border-2 border-indigo-200 bg-white"></div>
            <div>
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-800">Initiator</div>
                <div class="text-xs text-gray-400 mt-1">{{ props?.requests?.initiator_human_diff ?? '-' }}</div>
              </div>
              <p class="mt-1">{{ props.requests?.initiator_remarks ?? 'No Remark' }}</p>
              <!-- <div style="cursor: pointer;"
                class="mt-2 inline-flex items-center bg-gray-100 px-3 py-1 rounded-md text-sm" @click="openDocument()">
                <img src="/assets/images/logo/pdf.svg" alt="pdf" class="w-4 h-4 mr-2" />
                <button @click="generatePdf">Attached Letter to Member.pdf </button>

                <div>
                </div>

              </div> -->
            </div>
          </div>
          <div class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div class="absolute left-0 top-0 w-4 h-4 rounded-full bg-green-500 border-2 border-green-200"></div>

            <div>
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-800">Approver</div>
                <div class="text-xs text-gray-400 mt-1">{{ props?.requests?.approver_human_diff ?? '-' }}</div>
              </div>
              <p class="mt-1">{{ props?.requests?.approver_remarks ?? 'No Remark By Approver' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Modal>
</template>
<script setup>
import { defineEmits, computed, ref, watch } from 'vue'
import { Button } from '@sds/oneui-common-ui';
import Modal from './Modal.vue';


const emit = defineEmits(['close'])
const closeModal = () => {
  emit('update:modelValue', false);
  emit('close');
};

const props = defineProps({
  request: {
    type: Object,
    default: null,
  },
  requests: {
    type: Object,
    default: () => ({})
  }
})

const modalTitle = ref('Loading...');


watch(() => props.requests, (newVal) => {
  if (newVal?.req_id) {
    modalTitle.value = `#REQ No.  ${newVal.req_id}`;
  }
}, { immediate: true });



//function for open pdf as document
const openDocument = () => {
  //alert('kam');
}
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

.modal-box {
  min-height: 300px;
  /* set your desired minimum height */
  max-width: 500px;
  /* optional: max width */
  padding: 20px;
  /* padding inside modal */
  background: white;
  /* make sure background is visible */
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}
</style>
