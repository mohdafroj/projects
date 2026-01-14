<template>
  <Modal
    v-model="internalVisible"
    :title="modalTitle"
    :subtitle="modalSubtitle"
    size="xl"
  >
    <!-- <div class="flex justify-end mb-4">
      <Icon
        icon="material-symbols:download-rounded"
        @click="downloadPDF"
        width="24"
        height="24"
        class="cursor-pointer"
      />
    </div> -->

    <!-- MODIFIED: Added conditional rendering for no data state -->
    <div v-if="members && members.length > 0" class="overflow-x-auto">
      <table class="min-w-full table-auto text-sm">
        <thead>
          <tr class="bg-gray-100 dark:bg-slate-700 text-left">
            <th class="px-4 py-2">Sr No.</th>
            <th class="px-4 py-2">Division No.</th>
            <th class="px-4 py-2">Member's Name</th>
            <th class="px-4 py-2">State</th>
            <!-- MODIFIED: Added Party Name column -->
            <th class="px-4 py-2">Party Name</th>
            <th class="px-4 py-2">Attendance Time</th>
            <!-- MODIFIED: Added status column for better data display -->
            <th class="px-4 py-2">Attendance Status</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(member, index) in members"
            :key="index"
            class="border-b"
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
            <!-- MODIFIED: Added Party Name cell -->
            <td class="px-4 py-2">
              <span class="text-sm font-medium">{{ member.partyName || 'N/A' }}</span>
            </td>
            <td class="px-4 py-2">{{ member.submitted }}</td>
            <!-- MODIFIED: Added status column with proper styling -->
            <td class="px-4 py-2">
              <span
                class="inline-flex items-center border border-gray-200 rounded-full px-3 py-0.5 text-xs font-medium text-gray-700 bg-gray-50"
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

    <!-- MODIFIED: Added no data state -->
    <div v-else class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="text-gray-400 mb-2">
          <Icon icon="solar:file-text-broken" width="48" height="48" class="mx-auto" />
        </div>
        <p class="text-gray-500 text-lg font-medium">No Record Found</p>
        <p class="text-gray-400 text-sm mt-1">No members found for the selected criteria</p>
      </div>
    </div>

    <div id="pdfContent" style="display: none;">
      <!-- Hidden div for potential future use -->
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Modal } from '@sds/oneui-common-ui';
import { Icon } from '@iconify/vue';
import useLocalDate from '@/composables/useLocalDate';
import { fetchDailyAttendance } from '@/services/attendanceService';

// Reactive flag to track image loading status
const imagesLoaded = ref(false);

// pdfMake instance
let pdfMake = null;

onMounted(() => {
  fetchPreviousDayAttendance();
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
  sessionNumber: {
    type: Number,
    default: 269
  },
  totalsSummary: {},
  selectedDate: ''
});

const previousDay = ref({
    all: 0,
    notSigned:0,
    signed:0,
    notRequired: 0,
    date: ''
  });
const fetchPreviousDayAttendance = async () => {
  previousDay.value.date = new Date(
    new Date(props.selectedDate).setDate(
      new Date(props.selectedDate).getDate() - 1
    )
  ).toISOString().split('T')[0];
  const response = await fetchDailyAttendance({date: previousDay.value.date});
  if (response.success_code === 200 && response.data) {
    previousDay.value.all = response.data.attendanceTotals?.totalAttendance || 0;
    previousDay.value.signed = response.data.attendanceTotals?.totalSigned || 0;
    previousDay.value.notSigned = response.data.attendanceTotals?.totalNotSigned || 0;
    previousDay.value.notRequired = response.data.attendanceTotals?.totalNotRequired || 0;
  }
};
watch(() => props.selectedDate, fetchPreviousDayAttendance, {immediate:true})

const emit = defineEmits(['update:modelValue']);

const internalVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

//Handle to convert number into sup and text
const ordinalSup = (n) => {
  const v = n % 100;
  let suffix = 'TH';

  if (v < 11 || v > 13) {
    if (n % 10 === 1) suffix = 'ST';
    else if (n % 10 === 2) suffix = 'ND';
    else if (n % 10 === 3) suffix = 'RD';
  }

  return [
    { text: String(n) },
    { text: suffix, sup: true }
  ];
}

const numberToOrdinalWords = (num) => {
  const ones = [
    '', 'One', 'Two', 'Three', 'Four', 'Five',
    'Six', 'Seven', 'Eight', 'Nine'
  ];

  const teens = [
    'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen',
    'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'
  ];

  const tens = [
    '', '', 'Twenty', 'Thirty', 'Forty',
    'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
  ];

  const ordinals = {
    One: 'First',
    Two: 'Second',
    Three: 'Third',
    Four: 'Fourth',
    Five: 'Fifth',
    Six: 'Sixth',
    Seven: 'Seventh',
    Eight: 'Eighth',
    Nine: 'Ninth',
    Ten: 'Tenth',
    Eleven: 'Eleventh',
    Twelve: 'Twelfth',
    Thirteen: 'Thirteenth',
    Fourteen: 'Fourteenth',
    Fifteen: 'Fifteenth',
    Sixteen: 'Sixteenth',
    Seventeen: 'Seventeenth',
    Eighteen: 'Eighteenth',
    Nineteen: 'Nineteenth',
    Twenty: 'Twentieth',
    Thirty: 'Thirtieth',
    Forty: 'Fortieth',
    Fifty: 'Fiftieth',
    Sixty: 'Sixtieth',
    Seventy: 'Seventieth',
    Eighty: 'Eightieth',
    Ninety: 'Ninetieth',
    Hundred: 'Hundredth',
    Thousand: 'Thousandth'
  };

  const toWords = (n) => {
    if (n < 10) return ones[n];
    if (n < 20) return teens[n - 10];
    if (n < 100)
      return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '');
    if (n < 1000)
      return (
        ones[Math.floor(n / 100)] +
        ' Hundred' +
        (n % 100 ? ' And ' + toWords(n % 100) : '')
      );
    return '';
  }

  const words = toWords(num).split(' ');
  const lastWord = words.pop();

  words.push(ordinals[lastWord] || lastWord + 'th');

  return words.join(' ');
}



const downloadPDF = async () => {
  if (!pdfMake) {
    alert('PDF library is not loaded yet. Please try again.');
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

    // Create table data
    const tableBody = [
      [
        { text: 'Sr No.', style: 'tableHeader' },
        { text: 'Division No.', style: 'tableHeader' },
        { text: "Member's Name", style: 'tableHeader' },
        { text: 'State', style: 'tableHeader' },
        // MODIFIED: Added Party Name column to PDF
        { text: 'Party Name', style: 'tableHeader' },
        { text: 'Attendance Time', style: 'tableHeader' },
        // MODIFIED: Added status column to PDF table
        { text: 'Attendance Status', style: 'tableHeader' },
      ],
    ];

    props.members.forEach((member, index) => {
      tableBody.push([
        { text: (index + 1).toString(), style: 'tableCell' },
        { text: member.divisionNo || '', style: 'tableCell' },
        { text: member.name || '', style: 'tableCell' },
        { text: member.state || '', style: 'tableCell' },
        // MODIFIED: Added Party Name to PDF data
        { text: member.partyName || 'N/A', style: 'tableCell' },
        { text: member.submitted || '', style: 'tableCell' },
        // MODIFIED: Added status to PDF with color coding
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
        {
          stack: [
            { text: 'Rajya Sabha Secretariat'.toLocaleUpperCase(), alignment: 'center', decoration: 'underline' },
            { text: '[Lobby Office]'.toLocaleUpperCase(), alignment: 'center' },
            { text: [...ordinalSup(props.sessionNumber),' SESSION OF RAJYA SABHA'], alignment: 'center', margin:[0,30,0,0] },
            { text: 'Attendance Report of Honble members of'.toLocaleUpperCase(), alignment: 'center', margin:[0,30,0,0] },
            { text: ['RAJYA SABHA FOR ', {text: useLocalDate(props.selectedDate, 'dd-mm-yyyy')}], alignment: 'center' },
            { text: '(At Adjournment of the house)'.toLocaleUpperCase(), alignment: 'center', margin:[0,0,0,30]},

            {
              table: {
                widths: ['70%', '30%'],
                body: [
                  [
                    { text: 'Total Members of Rajya Sabha', alignment: 'left' },
                    { text: props.totalsSummary.all, alignment: 'center' },
                  ],
                  [
                    {
                      text: [
                        'Members not required to sign\n',
                        { text: '(HDC, Ministers, LOP)', fontSize: 9 }
                      ],
                      alignment: 'left',
                    },
                    { text: props.totalsSummary.notRequired, alignment: 'center' },
                  ],
                  [
                    { text: 'Members who signed', alignment: 'left' },
                    { text: props.totalsSummary.signed, alignment: 'center' },
                  ],
                  [
                    { text: 'Members who did not signed', alignment: 'left' },
                    { text: props.totalsSummary.notSigned, alignment: 'center' },
                  ],
                  [
                    { text: 'Absentee percentage', alignment: 'left' },
                    { text: [ props.totalsSummary.all > 0 ? Number(props.totalsSummary.notSigned * 100 / props.totalsSummary.all).toFixed(2) : 0.00, {text:'%'}], alignment: 'center' },
                  ],
                ],
              },
              layout: {
                paddingLeft: () => 10,
                paddingRight: () => 10,
                paddingTop: () => 10,
                paddingBottom: () => 10,
                hLineColor: () => '#9ca3af',
                vLineColor: () => '#9ca3af',
              },
            },

            { text: 'Summary of Attendance'.toLocaleUpperCase(), alignment: 'center', margin:[0,40,0,0]},
            { text: `on ${useLocalDate(previousDay.value.date, 'dd-mm-yyyy')} (Previous Day)`.toLocaleUpperCase(), alignment: 'center', margin:[0,0,0,20]},

            {
              table: {
                widths: ['70%', '30%'],
                body: [
                  [
                    { text: 'Total Members of Rajya Sabha', alignment: 'left' },
                    { text: previousDay.value.all, alignment: 'center' },
                  ],
                  [
                    {
                      text: [
                        'Members not required to sign\n',
                        { text: '(HDC, Ministers, LOP)', fontSize: 9 }
                      ],
                      alignment: 'left',
                    },
                    { text: previousDay.value.notRequired, alignment: 'center' },
                  ],
                  [
                    { text: 'Members who signed', alignment: 'left' },
                    { text: previousDay.value.signed, alignment: 'center' },
                  ],
                  [
                    { text: 'Members who did not signed', alignment: 'left' },
                    { text: previousDay.value.notSigned, alignment: 'center' },
                  ],
                  [
                    { text: 'Absentee percentage', alignment: 'left' },
                    { text: [ previousDay.value.all > 0 ? Number(previousDay.value.notSigned * 100 / previousDay.value.all).toFixed(2) : 0.00, {text:'%'}], alignment: 'center' },
                  ],
                ],
              },
              layout: {
                paddingLeft: () => 10,
                paddingRight: () => 10,
                paddingTop: () => 10,
                paddingBottom: () => 10,
                hLineColor: () => '#9ca3af',
                vLineColor: () => '#9ca3af',
              },
            },
            
          ],
          margin: [0, 0, 0, 20],
          pageBreak: 'after'
        },
        
        // Header with centered emblem and text, circular logo on right
        {
          stack: [
              {
              columns: [
                // CENTER: Emblem + Text
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
                        { text: `(${numberToOrdinalWords(props.sessionNumber)} Session)`, style: 'sessionInfo', alignment: 'center' },
                        {
                          text: `Parliament House, New Delhi, Dated ${useLocalDate(props.selectedDate, 'dd-mm-yyyy')}`,
                          style: 'dateInfo',
                          alignment: 'center',
                        },
                      ],
                      alignment: 'center',
                    },
                  ],
                  alignment: 'center',
                },

                // RIGHT: Circular Logo
                {
                  width: 'auto',
                  stack: [
                    pdfMake.vfs && pdfMake.vfs['rs-logo-c.png']
                      ? {
                          image: 'rs-logo-c.png',
                          width: 60,
                          height: 60,
                        }
                      : {
                          text: 'Circular Logo Not Available',
                          style: 'placeholderText',
                        },
                  ],
                  alignment: 'right',
                },
              ],
              columnGap: 10,
            },           
          ],
          margin: [0, 0, 0, 20],
        },
        //Start of create box        
        

{
  style: 'boxesContainer',
  table: {
    widths: ['*', '*', '*', '*', '*'],
    padding:[10,10,10,10],
    body: [
      [
        // Row with 4 boxes
        {
          stack: [
            { text: 'All Members', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + props.totalsSummary.all, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Signed', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + props.totalsSummary.signed, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Not Required', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + props.totalsSummary.notRequired, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Not Signed', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + props.totalsSummary.notSigned, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Manual Signed', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + props.totalsSummary.manual, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        }
      ]
    ]
  }
},

        //End of boxes



        // Data Table
        {
          table: {
            headerRows: 1,
            // MODIFIED: Updated widths to include Party Name column (7 columns total)
            widths: ['auto', 'auto', '*', 'auto', '*', 'auto', 'auto'],
            body: tableBody,
          },
          layout: {
            fillColor: function (rowIndex) {
              return rowIndex === 0 ? '#f1f5f9' : null;
            },
            hLineWidth: function () {
              return 0.5;
            },
            vLineWidth: function () {
              return 0.5;
            },
            hLineColor: function () {
              return '#d1d5db';
            },
            vLineColor: function () {
              return '#d1d5db';
            },
          },
        },
        // Footer
        // {
        //   margin: [0, 30, 0, 0],
        //   table: {
        //     widths: ['*'],
        //     body: [
        //       [
        //         {
        //           border: [false, true, false, false],
        //           text: [
        //             { text: `Generated on: ${formatDateTime(new Date())}\n`, style: 'footer' },
        //             { text: `Total Records: ${props.members.length}`, style: 'footer' },
        //           ],
        //           alignment: 'center',
        //         },
        //       ],
        //     ],
        //   },
        //   layout: 'noBorders',
        // },
      ],
      footer: function (currentPage, pageCount) {
        return {
          columns: [
            { text: `Generated on: ${formatDateTime(new Date())}`, style: 'footer', alignment: 'left', },
            {
              text: [`Total Records: ${props.members.length},`, '    ' ,`Page ${currentPage} of ${pageCount}`],
              alignment: 'right',
              style: 'footer',
            },
          ],
          margin: [40, 10, 40, 0],
        };
      },

      styles: {
        placeholderText: {
          fontSize: 8,
          bold: true,
          color: '#9CA3AF',
          margin: [0, 0, 0, 0],
        },
        parliamentText: {
          fontSize: 12,
          bold: true,
          color: '#374151',
          margin: [0, 0, 0, 5],
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
          fontSize: 14,
          bold: true,
          color: '#1f2937',
        },
        reportSubtitle: {
          fontSize: 12,
          color: '#6b7280',
        },
        boxesContainer: {
          margin: [0, 10, 0, 10],
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

    pdfMake.createPdf(docDefinition).download(fileName);
  } catch (error) {
    console.error('Error generating PDF:', error.message);
    alert('Failed to generate PDF. Please try again.');
  }
};

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

// MODIFIED: Added helper function for status color coding
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