<template>
  <div class="p-6 rounded-xl shadow-md bg-white w-full mx-auto" v-show="showInvoice">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold text-gray-800">Invoice Details</h2>
      <span v-if="filesData.length == 0">No invoice attached</span>
      <Icon icon="mdi:close" class="text-2xl cursor-pointer" @click="$emit('close')" />
    </div>

    <div class="flex flex-wrap gap-6">
      <div
        v-for="(file, index) in filesData"
        :key="index"
        class="w-40 rounded-xl shadow-lg p-3 bg-white relative group"
      >
        <div
        @click="() => handleView(file)"
        class="cursor-pointer hover:shadow-lg"
        >
          <!-- Thumbnail -->
          <img
            v-if="isImage(file.url)"
            :src="file.url"
            class="w-full h-48 object-contain rounded pointer-events-none"
          />
          <embed
            v-else
            :src="file.url"
            type="application/pdf"
            class="w-full h-48 object-contain rounded pointer-events-none"
          />
          <div class="mt-2 bg-gray-50 rounded-xl py-1 px-2 flex justify-between items-center">
            <span class="text-sm text-gray-700 truncate">{{ file.name }}</span>
          </div>

        </div>
      </div>
    </div>

    <!-- Image Popup Modal -->
    <Modal
      :modelValue="modelValue"
      :title="' '"
      size="xl"
      @close="handleClose"
    >
    <div class="flex items-center justify-left mb-1">
      <div @click="() => downloadFile(seletedItem.url, seletedItem.name)" class="z-10 px-4 py-2 mr-5">
        <Icon icon="hugeicons:download-04" class="text-2xl cursor-pointer" />
      </div> 
      <div v-if="isImage(seletedItem.url)" @click="() => printFile(seletedItem.url)" class="z-10 px-4 py-2 ml-5">
        <Icon icon="fluent:print-32-regular" class="text-2xl cursor-pointer" />
      </div>       
    </div>
    <div class="w-full h-[95vh] flex items-center justify-center">

        <img
          v-if="isImage(seletedItem.url)"
          :src="seletedItem.url"
          class="w-full h-full object-contain"
        />
        <object
          v-else
          :data="seletedItem.url"
          type="application/pdf"
          class="w-full h-full object-contain"
        />
    </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { Modal } from "@sds/oneui-common-ui";
import { useApiStore } from "@/store/apiData";

const props = defineProps({
  //files: Array, // { name: string, url: string }[]
  showInvoice: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['delete', 'close'])
const modelValue = ref(false);
const seletedItem = ref({name:'', url:''});
const filesData = ref([]);
const apiStore = useApiStore();

const listFiles = (data) => {
  if ( data ) {
    filesData.value = [];
    Object.keys(data).map(key => {
      data[key].map(item => {
        filesData.value = [...filesData.value, {name:key, url: item}];
      })
    });
  }
}

watch(
  () => apiStore.tada_claim.detail.documents, 
  (newData, oldData) => {
    listFiles(newData);
  },
  { immediate: true }
);

function isImage(name) {
  return /\.(jpe?g|png|gif|bmp|webp)$/i.test(name)
}

const handleView = (file) => {
  seletedItem.value = file;
  modelValue.value = true;
}

const handleClose = () => {
  modelValue.value = false;
}

const downloadFile = async (url, name = "Invoice") => {
  try {
    const response = await fetch(url)
    if (!response.ok) throw new Error("Failed to fetch file")

    const blob = await response.blob()
    const blobUrl = URL.createObjectURL(blob)

    const link = document.createElement("a")
    link.href = blobUrl
    link.download = name
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    URL.revokeObjectURL(blobUrl) // cleanup
  } catch (err) {
    console.error("Download failed:", err)
  }
}


const printFile = async (url) => {
  if (isImage(url)) {
    // For images: print the modal preview
    const imgHtml = `<img src="${url}" style="width:100%;height:auto;" />`
    const w = window.open('', '', 'width=800,height=600')
    w.document.write(imgHtml)
    w.document.close()
    w.focus()
    w.print()
    w.close()
  } else {
    try {
      const response = await fetch(url)
      const blob = await response.blob()
      const blobUrl = URL.createObjectURL(blob);

      const w = window.open(blobUrl, '_blank')
      w.focus()
    } catch (err) {
      console.error('Failed to print PDF:', err)
    }
  }
}

</script>
