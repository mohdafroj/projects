<template>
  <div class="p-6 rounded-xl shadow-md bg-white w-full
   mx-auto">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold text-gray-800">Invoice Details</h2>
      <Icon icon="mdi:close" class="text-2xl cursor-pointer" @click="$emit('close')" />
    </div>

    <div class="flex flex-wrap gap-6">
      <div
        v-for="(file, index) in files"
        :key="index"
        class="w-40 rounded-xl shadow-lg p-3 bg-white relative group"
      >
        <!-- Thumbnail -->
        <img
          v-if="isImage(file.name)"
          :src="file.url"
          class="w-full h-48 object-contain rounded"
        />
        <embed
          v-else
          :src="file.url"
          type="application/pdf"
          class="w-full h-48 object-contain rounded"
        />

        <!-- Zoom icon -->
        <div
          class="absolute inset-0 flex items-center justify-center bg-black/30 text-white opacity-0 group-hover:opacity-100 transition"
        >
          <Icon icon="mdi:magnify-plus" class="text-3xl cursor-pointer" @click="handleView(file)" />
        </div>

        <!-- File Name + Delete -->
        <div class="mt-2 bg-gray-50 rounded-xl py-1 px-2 flex justify-between items-center">
          <span class="text-sm text-gray-700 truncate">{{ file.name }}</span>
          <Icon icon="mdi:close-circle-outline" class="text-red-500 cursor-pointer text-lg" @click="$emit('delete', index)" />
        </div>
      </div>
    </div>

    <!-- Image Popup Modal -->
    <div
      v-if="popupImage"
      class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center"
      @click.self="popupImage = null"
    >
      <img :src="popupImage" class="max-h-[90vh] max-w-[90vw] rounded shadow-xl" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  files: Array // { name: string, url: string }[]
})

const emit = defineEmits(['delete', 'close'])

const popupImage = ref(null)

function isImage(name) {
  return /\.(jpe?g|png|gif|bmp)$/i.test(name)
}

function handleView(file) {
  if (isImage(file.name)) {
    popupImage.value = file.url
  } else {
    window.open(file.url, '_blank')
  }
}
</script>
