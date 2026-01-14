<template>
  <div v-show="visible" class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-gray-900/30">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-5xl h-[75vh] overflow-y-auto p-6 relative">
      <button class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-semibold"
        @click="$emit('close')">✕</button>
      <h2 class="text-lg font-bold mb-4">{{ title }}</h2>

      <div ref="pdfContent" id="pdfContent" class="text-sm space-y-4 relative">
        <!-- Page number container (visible only in PDF) -->
        <!-- <div class="pdf-header">
          <div class="page-number" style="position: absolute; top: -10px; right: 0; font-size: 12px;">
            Page <span class="pageNumber"></span> of <span class="totalPages"></span>
          </div>
        </div> -->

        <!-- Editor Content -->
        <div v-html="editorHtml" class="ql-editor"></div>

        <!-- Page Break -->
        <div style="page-break-before: always;"></div>
      </div>

      <!-- Button -->
      <div class="mt-4 text-right">
        <button @click="generatePdfDownload" v-if="props.isDownloadPdf"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Download PDF
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import html2pdf from 'html2pdf.js';
import { Icon } from '@iconify/vue';
import { defineEmits } from 'vue';
// import 'quill/dist/quill.snow.css';
const props = defineProps({
   title: {
    type: String,
    default: ''
  },
  visible: Boolean,
  editorHtml: String,
  filename: String,
  isDownloadPdf: Boolean,
  triggerPdfGenerate: Boolean
});

const emit = defineEmits(['close', 'blobData']);
const pdfContent = ref(null);

watch(
  () => props.triggerPdfGenerate,
  async (newVal) => {
    if (newVal) {
      await nextTick(); // Wait for DOM update
      if (pdfContent.value) {
        await generatePdfBlob();
      } else {
        console.warn("pdfContent is null");
      }
    }
  }
);

const generatePdfBlob = async () => {
  const opt = {
    margin: [0.5, 0.5],
    filename: props.filename,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: {
      unit: 'in',
      format: 'a4',
      orientation: 'portrait'
    },
    pagebreak: {
      mode: ['avoid-all', 'css', 'legacy']
    }
  };

  // Add page numbers manually after rendering
  await html2pdf().set(opt).from(pdfContent.value).toPdf().get('pdf').then(pdf => {
    const totalPages = pdf.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);
      pdf.setFontSize(10);
      pdf.text(`Page ${i} of ${totalPages}`, 7.5, 0.3, { align: 'right' });
    }
  }).output('blob')
    .then(async blob => {
      emit('blobData', blob)
      emit('close'); // Close modal on success
    });
};
const generatePdfDownload = async () => {
  const opt = {
    margin: [0.5, 0.5],
    filename: props.filename,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: {
      unit: 'in',
      format: 'a4',
      orientation: 'portrait'
    },
    pagebreak: {
      mode: ['avoid-all', 'css', 'legacy']
    }
  };

  // Add page numbers manually after rendering
  await html2pdf().set(opt).from(pdfContent.value).toPdf().get('pdf').then(pdf => {
    const totalPages = pdf.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);
      pdf.setFontSize(10);
      pdf.text(`Page ${i} of ${totalPages}`, 7.5, 0.3, { align: 'right' });
    }
  }).save()
};

</script>

<style scoped>
.budget-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.budget-table th,
.budget-table td {
  border: 1px solid #ccc;
  padding: 6px;
  text-align: left;
  vertical-align: top;
}

/* Repeat header on each page */
.budget-table thead {
  display: table-header-group;
}

.budget-table thead tr {
  page-break-inside: avoid !important;
}

/* Prevent row break */
.budget-table tr {
  page-break-inside: avoid;
}
</style>
