<template>
  <div>
    <label v-if="label" class="block mb-[6px] text-sm font-semibold text-gray-500 dark:text-slate-300">
      {{ label }}
      <span v-if="isRequired" class="text-red-500 dark:text-red-300">*</span>
    </label>
    
    <div 
      :class="[
        'grid gap-2 border-2 border-dashed rounded-md p-1 transition-colors duration-300 min-h-[150px]',
        'bg-white dark:bg-slate-900',
        'border-blue-300 dark:border-slate-600',
        {
          'grid-cols-1 items-center justify-center': files.length == 0,
          'md:grid-cols-7 xl:grid-cols-10 border-solid border-blue-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800': files.length > 0,
          'bg-blue-100 dark:bg-slate-700 border-blue-600 dark:border-blue-400': isDragging
        }
      ]"
    >
      <div v-for="(item, index) in files" :key="'fileIndex' + index">
        <div
        v-if="item.custom == 0"
        :class="[
          'bg-white rounded-md overflow-hidden relative',
          {
            'border border-gray-200': item.errors.length == 0,
            'border border-red-500 dark:border-red-400': item.errors.length > 0
          }
          ]"
        >
          <div v-if="isImage(item.file)" class="relative flex items-center justify-center h-[150px] aspect-square">
            <img :src="item.url" class="object-cover w-full h-full" alt="File preview" />
          </div>
          
          <div v-else-if="item.file.type == 'application/pdf'" class="relative flex items-center justify-center h-[150px] aspect-square">
            <embed
              :src="item.url"
              type="application/pdf"
              class="w-full h-full object-cover"
            />
          </div>

          <div v-else class="p-3 flex items-center h-[165px] gap-2 relative">
            <div class="w-full h-[100px]">
              <Icon icon="heroicons-outline:document" class="text-gray-500 text-2xl flex-shrink-0" />
            </div>
          </div>
          <button 
            v-if="isPreview"
            @click.stop="() => handlePreviewFile(item)" 
            class="absolute top-2 left-2 bg-gray-800 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm shadow-md opacity-90 hover:opacity-100"
            type="button"
            aria-label="Preview file"
          >
            <Icon icon="heroicons-solid:eye" />
          </button>

          <button 
            @click.stop="removeFile(index)" 
            class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm shadow-md opacity-90 hover:opacity-100"
            type="button"
            aria-label="Remove file"
          >
            <Icon icon="heroicons-solid:x" />
          </button>
          <div class="text-xs px-1 truncate" :title="item.file.name">{{ item.file.name }}</div>
          <div class="px-1" v-if="item.errors.length == 0">
            <div v-if="item.percentage >= 100" class="text-xs text-green-500">✓ Uploaded</div>
            <div v-else class="text-xs text-blue-500">Uploading... {{ item.percentage }}%</div>
          </div>
        </div>
      </div>
      
      <div
        class="cursor-pointer flex items-center justify-center transition-colors duration-300 min-h-[150px]"
        :class="[{'w-full': files.length == 0}]"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
        @click="triggerFileInput"
        @blur="handleBlur"
      >
        <input
          type="file"
          ref="fileInput"
          :accept="accept"
          :multiple="multiple"
          class="hidden"
          @change="handleFileChange"
          :aria-required="isRequired"
        />

        <div v-if="files.length === 0" class="flex flex-col items-center justify-center">
          <Icon icon="iconamoon:cloud-upload-thin" class="w-16 h-16 text-gray-400 mb-3" />
          <p class="text-gray-500 text-center">
            Click here or drag and drop your files.
          </p>
          <p v-if="accept" class="text-xs text-gray-600 mt-2">
            Allowed file types: {{ accept }}
          </p>
        </div>

        <div v-else class="w-[130px] bg-white flexflex-col items-center justify-center">
          <Icon icon="icon-park-outline:add-one" width="48" height="48" />
          <p v-if="accept" class="text-xs text-gray-600 mt-2">
            Allowed file types: {{ accept }}
          </p>
        </div>

      </div>

    </div>

    <div class="mt-2 ml-4 text-sm text-red-500">
      <ol :style="{listStyleType: 'decimal'}">
        <li v-for="(item, index) in allErrors" :key="'error:' + index">
          {{ item }}
        </li>
      </ol>
    </div>
    

    <Modal
      :modelValue="isShowModel"
      :title="' '"
      size="xl"
      @close="handleClosePreviewFile"

    >
      <div v-if="Object.keys(previewFile).length" class="w-full h-[95vh] flex items-center justify-center">

          <img v-if="isImage(previewFile.file)" :src="previewFile.url" class="object-cover w-full h-full" alt="File preview" />          
          <embed
            v-else-if="previewFile.file.type == 'application/pdf'"
            :src="previewFile.url"
            type="application/pdf"
            class="w-full h-full object-cover"
          />

          <div v-else class="p-3 flex items-center gap-2 relative">
            <div class="w-full">
              <Icon icon="heroicons-outline:document" class="text-gray-500 text-2xl flex-shrink-0" />
            </div>
          </div>

      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Icon } from '@iconify/vue';
import Swal from 'sweetalert2';
import { Modal } from "@sds/oneui-common-ui";

const props = defineProps({
  onFileUpload: {
    type: Function,
    required: true
  },
  label: {
    type: String,
    default: 'File Upload'
  },
  multiple: {
    type: Boolean,
    default: false
  },
  accept: {
    type: String,
    default: ''
  },
  maxSize: {
    type: Number,
    default: 100 // MB
  },
  maxFiles: {
    type: Number,
    default: 10
  },
  isRequired: {
    type: Boolean,
    default: false
  },
  isPreview: {
    type: Boolean,
    default: true
  },
  data: {
    type: Array,
    default: []
  }
});
const emit = defineEmits(['update:files']);

const fileInput = ref(null);
const files = ref(props.data || []);
const fileErrors = ref([]);
const isDragging = ref(false);
const wasTouched = ref(false);
const isShowModel = ref(false);
const previewFile = ref({});

const allErrors = computed(() => {
  let errors = [...fileErrors.value];
  files.value.map(item => {
    if ( item.custom == 0 ) {
      errors = [...item.errors, ...errors];
    }
  })
  return errors;
});

const triggerFileInput = () => {
  fileInput.value.click();
};

const handleFileChange = (event) => {
  const selectedFiles = Array.from(event.target.files);
  event.target.value = null;
  if (selectedFiles.length > 0) {
    addFiles(selectedFiles);
  }
};

const onDrop = (event) => {
  isDragging.value = false;
  
  const droppedFiles = Array.from(event.dataTransfer.files);
  if (droppedFiles.length > 0) {
    addFiles(droppedFiles);
  }
};

const customUpload = (data) => {
  const customFiles = Array.from(data);
  if ( customFiles.length > 0 ) {
    addFiles(customFiles, 1);
  }
}

defineExpose({
  customUpload
});

const addFiles = (newFiles, custom=0) => {
  let findTotal = 0; //Ignore custom upload files.
  if ( custom == 0 ) {
    findTotal++;
  }
  if ( files.value.length ) {
    files.value.map(item => { 
      if ( item.custom == 0 ) {
        findTotal++;
      }
    });
  }

  if ( findTotal > props.maxFiles ) {
    Swal.fire({
      toast: true,          // make it a toast
      position: 'top-end',  // top-right corner
      icon: 'error',      // icon type: success, error, warning, info, question
      title: 'You can upload up to ' + props.maxFiles + ' Files.',
      showConfirmButton: false, // hide the "OK" button
      timer: 3000,          // auto-close after 3 seconds
      timerProgressBar: true,
    });    
    return true;
  }
  wasTouched.value = true;
  const validFiles = newFiles.map(file => {
    let response = {custom: custom, view_path: '', path: '', percentage:0, url: URL.createObjectURL(file), file:file, errors: []};
    const fileSizeMB = file.size / (1024 * 1024);
    if (fileSizeMB > props.maxSize) {
      response.errors = [...response.errors, `File ${file.name} is too large. Maximum size is ${props.maxSize}MB.`];
    }
    
    if (props.accept && !props.accept.split(',').some(type => {
      type = type.trim();
      if (type.startsWith('.')) {
        return file.name.toLowerCase().endsWith(type.toLowerCase());
      } else {
        return file.type.match(type.replace('*', '.*'));
      }
    })) {
      response.errors = [...response.errors, `File ${file.name} has an invalid type.`];
    }
    return response;
  });
  
  if (props.multiple) {
    files.value = [...files.value, ...validFiles];
  } else {
    files.value = validFiles.slice(0, 1);
  }
  selectedFileUpload();
};

const handlePreviewFile = (item) => {  
  previewFile.value = item;
  isShowModel.value = true;
};

const handleClosePreviewFile = () => {  
  previewFile.value = {};
  isShowModel.value = false;
};

const removeFile = (index) => {  
  files.value.splice(index, 1);
  handleBlur();
};

const isImage = (file) => {
  return file.type.startsWith('image/');
};

// Validate on blur if the field is required
const handleBlur = () => {
  wasTouched.value = true;
  if ( props.isRequired && files.value.length === 0 && wasTouched.value) {
    const fieldName = props.label || 'File upload field';
    Swal.fire({
      toast: true,          // make it a toast
      position: 'top-end',  // top-right corner
      icon: 'error',      // icon type: success, error, warning, info, question
      title: fieldName + ' is required.',
      showConfirmButton: false, // hide the "OK" button
      timer: 3000,          // auto-close after 3 seconds
      timerProgressBar: true,
    }); 
  } else {
    fileErrors.value = [];
  }
  emit('update:files', files.value);  
};

//Start to upload file or files to server.
const chunkSize = (2 * 1024 * 1024) - 128 // ~2MB
const maxRequestsPerMinute = 100
const rateLimitDelay = Math.floor(60000 / maxRequestsPerMinute)
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

const retryUploadChunk = async (formData, retries = 1) => {
  let response = {done:false};
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const options = {headers:{'Accept-Language': 'en-US', 'Content-Type': 'multipart/form-data'}};
      response = await props.onFileUpload({url:'', options, payload: formData, client:'fdms'});
      return response;
    } catch (err) {
      response = err;
      if (attempt < retries) {
        await delay(1000)
      } else {
        console.log("File error: ", err);
      }
    }
  }
  return response;
}

const computeFileHash = async (file) => {
  const arrayBuffer = await file.arrayBuffer()
  const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer)
  const hashArray = Array.from(new Uint8Array(hashBuffer))
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
}

const selectedFileUpload = async () => {
  if (files.value.length == 0) return
  for (const item of files.value) {
    if ( item.percentage > 0 || item.errors.length ) continue
    const totalChunks = Math.ceil(item.file.size / chunkSize)
    const uploadId = `${item.file.name}-${item.file.size}-${Date.now()}`
    
    const fileHash = await computeFileHash(item.file)
    
    for (let i = 0; i < totalChunks; i++) {
      const start = i * chunkSize
      const end = Math.min(item.file.size, start + chunkSize)
      const chunk = item.file.slice(start, end)

      const formData = new FormData()
      formData.append('file', chunk, item.file.name)
      formData.append('resumableFilename', item.file.name)
      formData.append('resumableIdentifier', uploadId)
      formData.append('resumableChunkNumber', i + 1)
      formData.append('resumableTotalChunks', totalChunks)
      formData.append('file_hash', fileHash) // ✅ Add hash in every chunk

      const response = await retryUploadChunk(formData);
      //console.log(response);
      if ( response?.isError ) {
        let customMessage = '';
        if ( response.message ) {
          customMessage = response.message
        }

        if ( response.description ) {
          customMessage = response.description
        }

        if ( response.error ) {
          customMessage = (customMessage == '') ? response.error : customMessage + ' OR ' + response.error;
        }
        item.errors = [...item.errors, item.file.name + ' - ' + customMessage];
        break
      } else {
        if ( i + 1 == totalChunks ) {
          item.view_path = response.done ? response.view_path : '';
          item.path = response.done ? response.path : '';
        } 
        item.percentage = Math.round(((i + 1) / totalChunks) * 100);
      }
      await delay(rateLimitDelay)
    }
  }
  emit('update:files', files.value);  
}


</script>