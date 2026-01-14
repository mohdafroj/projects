<template>
     <!-- =kkkkk=<pre>{{remarks }}</pre>    -->
  <div class="space-y-2 bg-gray-100 p-3 mt-2 rounded-lg">
        <div class="max-w-md mx-auto mt-6 space-y-6 text-sm text-gray-700 font-medium">
          <div class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div :class="[Cls,'absolute left-0 top-0 w-4 h-4 rounded-full border-2'] "></div>
            <div>
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-800">Initiator</div>
                <div class="text-xs text-gray-400 mt-1">{{ remarks?.initiator_human_diff ?? '-' }}</div>
              </div>
              <p class="mt-1">{{ remarks?.initiator_remarks ?? 'No Remark' }}</p>
              <!-- <div style="cursor: pointer;"
                class="mt-2 inline-flex items-center bg-gray-100 px-3 py-1 rounded-md text-sm" @click="openDocument()">
                <img src="/assets/images/logo/pdf.svg" alt="pdf" class="w-4 h-4 mr-2" />
                <button @click="generatePdf">Attached Letter to Member.pdf </button>

                <div>
                </div>

              </div> -->
            </div>
          </div>
 
          <div v-if="role=='approver'" class="relative pl-6">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div class="absolute left-0 top-0 w-4 h-4 rounded-full border-2 border-green-200 bg-green-500" ></div>

            <div>
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-800">Approver</div>
                <div class="text-xs text-gray-400 mt-1">{{ remarks?.approver_human_diff ?? '-' }}</div>
              </div>
              <p class="mt-1">{{ remarks?.approver_remarks ?? 'No Remark By Approver' }}</p>
            </div>
          </div>
        </div>
      </div> 
</template>
<script setup>
import { defineEmits, computed, ref, watch } from 'vue'
import { Button } from '@sds/oneui-common-ui';
import Modal from './Modal.vue';
import { hasAnyPermission, hasPermission, PERMISSIONS } from "@/utils/rbac";

const emit = defineEmits(['close'])
const closeModal = () => {
  emit('update:modelValue', false);
  emit('close');
};

const Cls = ref('');
const Cls_gr = ref('');
const role = ref('');

 if (hasPermission(PERMISSIONS.CGHS.MANAGE_CARD)) { 
   role.value='review';
   Cls.value = 'border-green-200 bg-green-500'
 }
 
 
 if (hasPermission([PERMISSIONS.CGHSAPPROVE.MANAGE_CARD,PERMISSIONS.CGHSAPPROVE.APPROVE])) {
  role.value='approver';
   Cls.value = 'border-indigo-200 bg-white'
 }
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
</style>
