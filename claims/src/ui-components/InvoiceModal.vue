<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50"
  >
    <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-5xl relative">
      <button
        @click="$emit('close')"
        class="absolute top-4 right-4 text-2xl font-bold text-gray-800 hover:text-black"
      >
        &times;
      </button>

      <h2 class="text-2xl font-semibold mb-6 text-gray-800">Invoice Details</h2>

      <div class="flex gap-6 flex-wrap">
        <div
          v-for="(file, index) in invoices"
          :key="index"
          class="w-48 bg-white rounded-xl shadow-md p-3 hover:shadow-lg transition"
        >
          <PdfThumbnail :src="file.url" />

          <div
            class="mt-3 flex justify-between items-center px-2 py-1 bg-gray-100 rounded-lg shadow-sm"
          >
            <span class="text-gray-700 text-sm truncate">Invoice.Pdf</span>
            <button
              @click="removeInvoice(index)"
              class="text-red-500 hover:text-red-600 text-lg font-semibold"
            >
              &times;
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PdfThumbnail from "./PdfThumbnail.vue";
const props = defineProps({
  invoices: Array,
});
const emit = defineEmits(["remove", "close"]);

function removeInvoice(index) {
  emit("remove", index);
}
</script>
