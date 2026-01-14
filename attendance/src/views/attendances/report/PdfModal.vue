<template>
    <Modal 
      v-model="showModal"
      title="Signed Document"
      :subtitle="subtitle"
      size="xl"
      :isLoading="isLoading"
      @close="handleClose"
    >
     
      <div >
       
        <div v-if="isLoading" class="flex items-center justify-center h-96">
          <div class="flex flex-col items-center gap-3">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="text-gray-600">Loading PDF...</p>
          </div>
        </div>
        
        
        <div v-else-if="error" class="flex items-center justify-center h-96">
          <div class="text-center">
            <Icon icon="material-symbols:error-outline" class="w-12 h-12 text-red-500 mx-auto mb-3" />
            <p class="text-red-600 font-medium">Failed to load PDF</p>
            <p class="text-gray-500 text-sm mt-1">{{ error }}</p>
            <Button
                label="Retry" 
                size="sm"
                color="red-outline"
                @click="$emit('retry')"
                />
           
          </div>
        </div>
  

        <div v-else class="space-y-4">

          <div class="flex justify-end">
           <Button
  label="Download PDF"
  icon="material-symbols:download"
  size="sm"
  color="green-outline"
  @click="downloadPdf"
/>
            
          </div>
  

          <iframe
            :src="pdfUrl"
            class="w-full h-[600px] border rounded"
            frameborder="0"
            title="PDF Viewer"
          />
          
          <!-- Fallback message -->
          <div class="p-3 bg-yellow-50 border border-yellow-200 rounded">
            <p class="text-yellow-800 text-sm">
              <Icon icon="material-symbols:info-outline" class="w-4 h-4 inline mr-1" />
              If the PDF doesn't display properly, please use the download button to view it in your default PDF viewer.
            </p>
          </div>
        </div>
      </div>
    </Modal>
  </template>
  
  <script setup>
  import { computed } from 'vue'
  import { Icon } from '@iconify/vue'
  import { Modal, Button } from '@sds/oneui-common-ui';
  
  // Props
  const props = defineProps({
    modelValue: {
      type: Boolean,
      default: false
    },
    pdfUrl: {
      type: String,
      default: ''
    },
    isLoading: {
      type: Boolean,
      default: false
    },
    error: {
      type: String,
      default: ''
    },
    mediaId: {
      type: [String, Number],
      default: ''
    }
  })
  
  // Emits
  const emit = defineEmits(['update:modelValue', 'close', 'retry'])
  
  // Computed
  const showModal = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })
  
  const subtitle = computed(() => {
    return props.mediaId ? `Media ID: ${props.mediaId}` : ''
  })
  

  const handleClose = () => {
    emit('close')
  }
  
  const downloadPdf = () => {
    if (props.pdfUrl) {
      const link = document.createElement('a')
      link.href = props.pdfUrl
      link.target = '_blank'
      link.setAttribute('download', 'signed-document.pdf')
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }
  }
  </script>