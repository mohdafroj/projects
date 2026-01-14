<template>
  <Modal
    v-model="internalVisible"
    :title="modalTitle"
    :subtitle="modalSubtitle"
    size="xl"
    class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
  >
    <div class="flex justify-end mb-4">
      <Icon
        icon="material-symbols:download-rounded"
        @click="downloadPDF"
        width="24"
        height="24"
        class="cursor-pointer text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
      />
    </div>

    <!-- MODIFIED: Added conditional rendering for no data state with dark mode support -->
    <div v-if="members && members.length > 0" class="overflow-x-auto">
      <table class="min-w-full table-auto text-sm">
        <thead>
          <tr class="bg-gray-100 dark:bg-slate-700 text-left">
            <th class="px-4 py-2">Sr No.</th>
            <th class="px-4 py-2">Division No.</th>
            <th class="px-4 py-2">Member's Name</th>
            <th class="px-4 py-2">State</th>
            <th class="px-4 py-2">Attendance Time</th>
            <th class="px-4 py-2">Attendance Status</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(member, index) in members"
            :key="index"
            class="border-b border-gray-200 dark:border-gray-600"
          >
            <td class="px-4 py-2">{{ index + 1 }}</td>
            <td class="px-4 py-2">{{ member.divisionNo }}</td>
            <td class="px-4 py-2 flex items-center gap-2">
              <img
                :src="member.imageUrl"
                alt="Member"
                class="w-8 h-8 rounded-full object-cover"
              />
              <span class="font-medium">{{ member.name }}</span>
            </td>
            <td class="px-4 py-2">{{ member.state }}</td>
            <td class="px-4 py-2">{{ member.submitted }}</td>
            <td class="px-4 py-2">
              <span
                class="inline-flex items-center border border-gray-200 dark:border-gray-600 rounded-full px-3 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700"
              >
                <span
                  class="w-2 h-2 rounded-full mr-1"
                  :class="{
                    'bg-green-500': member.status === 'Signed',
                    'bg-red-500': member.status === 'Not Signed',
                    'bg-blue-500': member.status === 'Not Required',
                  }"
                ></span>
                <span
                  :class="{
                    'text-green-500': member.status === 'Signed',
                    'text-red-500': member.status === 'Not Signed',
                    'text-blue-500': member.status === 'Not Required',
                  }"
                >
                  {{ member.status }}
                </span>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODIFIED: Updated no data state for dark mode -->
    <div v-else class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="text-gray-400 dark:text-gray-500 mb-2">
          <Icon icon="solar:file-text-broken" width="48" height="48" class="mx-auto" />
        </div>
        <p class="text-gray-500 dark:text-gray-300 text-lg font-medium">No Record Found</p>
        <p class="text-gray-400 dark:text-gray-400 text-sm mt-1">No members found for the selected criteria</p>
      </div>
    </div>

    <div id="pdfContent" style="display: none;">
      <!-- Hidden div for potential future use -->
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Modal } from '@sds/oneui-common-ui';
import { Icon } from '@iconify/vue';

// Reactive flag to track image loading status
const imagesLoaded = ref(false);

// pdfMake instance
let pdfMake = null;

onMounted(() => {
  // Load pdfMake via CDN or dynamic import
  if (window.pdfMake) {
    pdfMake = window.pdfMake;
    if (!pdfMake.vfs) {
      pdfMake.vfs = {};
    }
    loadImagesFromPublic();
  } else {
    loadPdfMake();
  }
});

const loadPdfMake = async () => {
  try {
    const pdfMakeModule = await import('pdfmake/build/pdfmake');
    const pdfFonts = await import('pdfmake/build/vfs_fonts');
    pdfMake = pdfMakeModule.default || pdfMakeModule;
    pdfMake.vfs = pdfFonts.default || pdfFonts.vfs || pdfFonts || {};
    await loadImagesFromPublic();
  } catch (error) {
    console.error('Failed to load pdfMake:', error.message);
    pdfMake.vfs = {};
    await loadImagesFromPublic();
  }
};

const loadImagesFromPublic = async () => {
  try {
    if (!pdfMake.vfs) {
      pdfMake.vfs = {};
    }

    // Define image paths (adjust these based on your public folder structure)
    const parliamentPaths = ['/assets/images/logo/emblem.png'];
    const circularPaths = ['/assets/images/logo/rs-logo-c.png'];

    // Load Parliament logo
    let parliamentLoaded = false;
    for (const path of parliamentPaths) {
      try {
        const fullUrl = `${window.location.origin}${path}`;
        const response = await fetch(fullUrl);
        if (response.ok) {
          const blob = await response.blob();
          const base64 = await blobToBase64(blob);
          pdfMake.vfs['emblem.png'] = base64.split(',')[1];
          parliamentLoaded = true;
          break;
        }
      } catch (error) {
        console.error(`Error loading parliament logo from ${path}:`, error.message);
      }
    }

    // Load Circular logo
    let circularLoaded = false;
    for (const path of circularPaths) {
      try {
        const fullUrl = `${window.location.origin}${path}`;
        const response = await fetch(fullUrl);
        if (response.ok) {
          const blob = await response.blob();
          const base64 = await blobToBase64(blob);
          pdfMake.vfs['rs-logo-c.png'] = base64.split(',')[1];
          circularLoaded = true;
          break;
        }
      } catch (error) {
        console.error(`Error loading circular logo from ${path}:`, error.message);
      }
    }

    imagesLoaded.value = parliamentLoaded && circularLoaded;
  } catch (error) {
    console.error('Error loading images:', error.message);
    imagesLoaded.value = false;
  }
};

const blobToBase64 = (blob) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
};

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  members: {
    type: Array,
    default: () => [],
  },
  modalTitle: {
    type: String,
    default: 'Attendance Report',
  },
  modalSubtitle: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const internalVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

// PDF download functionality using pdfMake
const downloadPDF = async () => {
  if (!pdfMake) {
    alert('PDF library is not loaded yet. Please try again in a moment.');
    return;
  }

  // Ensure images are loaded before generating PDF
  if (!imagesLoaded.value) {
    await loadImagesFromPublic();
    if (!imagesLoaded.value) {
      alert('Failed to load images for the PDF. Generating without images.');
    }
  }

  try {
    const fileName = `${props.modalTitle.replace(/\s+/g, '_')}_${formatDateForFileName(new Date())}.pdf`;

    // Create table data for pdfMake
    const tableBody = [
      // Header row
      [
        { text: 'Sr No.', style: 'tableHeader' },
        { text: 'Division No.', style: 'tableHeader' },
        { text: "Member's Name", style: 'tableHeader' },
        { text: 'State', style: 'tableHeader' },
        { text: 'Attendance Time', style: 'tableHeader' },
        { text: 'Attendance Status', style: 'tableHeader' },
      ],
    ];

    // Add data rows
    props.members.forEach((member, index) => {
      tableBody.push([
        { text: (index + 1).toString(), style: 'tableCell' },
        { text: member.divisionNo || '', style: 'tableCell' },
        { text: member.name || '', style: 'tableCell' },
        { text: member.state || '', style: 'tableCell' },
        { text: member.submitted || '', style: 'tableCell' },
        {
          text: member.status || '',
          style: 'tableCell',
          color: getStatusColor(member.status),
        },
      ]);
    });

    // PDF document definition
    const docDefinition = {
      pageSize: 'A4',
      pageMargins: [40, 60, 40, 60],
      content: [
        // Parliament Header
        {
          stack: [
            {
              columns: [
                {
                  width: '*',
                  stack: [
                    pdfMake.vfs && pdfMake.vfs['emblem.png']
                      ? {
                          image: 'emblem.png',
                          width: 30,
                          height: 40,
                          alignment: 'center',
                          margin: [0, 0, 0, 10],
                        }
                      : {
                          text: 'Parliament Logo Not Available',
                          style: 'placeholderText',
                          alignment: 'center',
                          margin: [0, 30, 0, 20],
                        },
                    {
                      stack: [
                        { text: 'Rajya Sabha', style: 'headerEnglish', alignment: 'center' },
                        { text: 'Attendance Register', style: 'subHeader', alignment: 'center' },
                        { text: '( Two Hundred And Sixty Eight Session)', style: 'sessionInfo', alignment: 'center' },
                        { text: `Parliament House, New Delhi, Dated ${formatDate(new Date())}`, style: 'dateInfo', alignment: 'center' },
                      ],
                      alignment: 'center',
                    },
                  ],
                },
              ],
            },
            {
              absolutePosition: { x: 450, y: 100 }, // Position circular logo on the right
              stack: [
                pdfMake.vfs && pdfMake.vfs['rs-logo-c.png']
                  ? {
                      image: 'rs-logo-c.png',
                      width: 60,
                      height: 60,
                      alignment: 'right',
                    }
                  : {
                      text: 'Circular Logo Not Available',
                      style: 'placeholderText',
                      alignment: 'right',
                    },
              ],
            },
          ],
          margin: [0, 0, 0, 20],
        },

        // Report Title
        { text: props.modalTitle, style: 'reportTitle', margin: [0, 20, 0, 5] },
        { text: props.modalSubtitle, style: 'reportSubtitle', margin: [0, 0, 0, 20] },

        // Data Table
        {
          table: {
            headerRows: 1,
            widths: ['auto', 'auto', '*', 'auto', 'auto', 'auto'],
            body: tableBody,
          },
          layout: {
            fillColor: function (rowIndex, node, columnIndex) {
              return rowIndex === 0 ? '#f1f5f9' : null;
            },
            hLineWidth: function (i, node) {
              return 0.5;
            },
            vLineWidth: function (i, node) {
              return 0.5;
            },
            hLineColor: function (i, node) {
              return '#d1d5db';
            },
            vLineColor: function (i, node) {
              return '#d1d5db';
            },
          },
        },

        // Footer
        {
          margin: [0, 30, 0, 0],
          table: {
            widths: ['*'],
            body: [
              [
                {
                  border: [false, true, false, false],
                  text: [
                    { text: `Generated on: ${formatDateTime(new Date())}\n`, style: 'footer' },
                    { text: `Total Records: ${props.members.length}`, style: 'footer' },
                  ],
                  alignment: 'center',
                },
              ],
            ],
          },
          layout: 'noBorders',
        },
      ],

      styles: {
        placeholderText: {
          fontSize: 8,
          bold: true,
          color: '#9CA3AF',
          margin: [0, 0, 0, 0],
        },
        headerEnglish: {
          fontSize: 18,
          bold: true,
          color: '#4b5563',
          margin: [0, 0, 0, 8],
        },
        subHeader: {
          fontSize: 14,
          bold: true,
          color: '#4b5563',
          margin: [0, 0, 0, 8],
        },
        sessionInfo: {
          fontSize: 12,
          bold: true,
          color: '#4b5563',
          margin: [0, 0, 0, 4],
        },
        dateInfo: {
          fontSize: 12,
          bold: true,
          color: '#4b5563',
          margin: [0, 0, 0, 2],
        },
        reportTitle: {
          fontSize: 16,
          bold: true,
          color: '#1f2937',
        },
        reportSubtitle: {
          fontSize: 12,
          color: '#6b7280',
        },
        tableHeader: {
          fontSize: 11,
          bold: true,
          color: '#374151',
          margin: [4, 8, 4, 8],
        },
        tableCell: {
          fontSize: 10,
          color: '#374151',
          margin: [4, 6, 4, 6],
        },
        footer: {
          fontSize: 10,
          color: '#6b7280',
          italics: true,
        },
      },
    };

    // Generate and download PDF
    pdfMake.createPdf(docDefinition).download(fileName);
  } catch (error) {
    console.error('Error generating PDF:', error.message);
    alert('Failed to generate PDF. Please try again.');
  }
};

// Helper functions
const formatDate = (date) => {
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
};

const formatDateTime = (date) => {
  return date.toLocaleString('en-IN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};

const formatDateForFileName = (date) => {
  return date.toISOString().split('T')[0];
};

const getStatusColor = (status) => {
  const colors = {
    'Signed': '#059669',      // green
    'Not Signed': '#DC2626',  // red
    'Not Required': '#2563EB', // blue
  };
  return colors[status] || '#374151';
};
</script>

<style scoped>
/* PDF-specific styles */
#pdfContent {
  font-family: Arial, sans-serif;
  line-height: 1.4;
}

#pdfContent table {
  width: 100%;
  border-collapse: collapse;
}

#pdfContent th,
#pdfContent td {
  text-align: left;
  vertical-align: top;
  word-wrap: break-word;
}

/* Ensure proper spacing in PDF */
#pdfContent .mb-6 {
  margin-bottom: 1.5rem;
}

#pdfContent .mt-8 {
  margin-top: 2rem;
}
</style>