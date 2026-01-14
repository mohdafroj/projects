<template>
 <Loading v-if="isLoading" />
 <div ref="fullscreenElement">
        <div class="flex justify-between gap-2">
          <h2 class="w-[20%] text-lg font-bold text-gray-800 pb-2">Daily Report</h2>
          <div class="w-[80%] flex justify-end gap-2">
            <Tabs />
          </div>
        </div> 
        <div class="flex gap-2 items-center justify-between px-4 py-1 bg-white w-full rounded-sm mt-2">
          <div class="flex gap-1">
            <div class="flex">
                <Multiselect 
                v-model="selectedToSession" 
                selectLabel="" 
                deselectLabel="" 
                placeholder="Select To Session" 
                class="text-sm text-blue-500 rounded h-fit custom-multiselect" 
                openDirection="below" 
                track-by="id" 
                label="name" 
                :showNoResults="false" 
                :showNoOptions="false" 
                :options="filterToSessions" 
                :multiple="false" 
                :searchable="true" 
                :close-on-select="true" 
                :clear-on-select="false" 
                />
            </div>
            <div class="p-2">
                <input 
                  ref="inputDateRef"
                  type="date" 
                  v-model="selectedDate"
                  :max="maxDate"
                  :min="minDate"
                  @click="() => handleInputDateClick()"
                  class="cursor-pointer text-sm w-40 px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  aria-label="attendance-date"
                />
            </div>
          </div>
          <div>
            <Icon icon="heroicons-outline:arrow-down-tray" class="w-6 h-6 cursor-pointer" @click="downloadPDF" />
          </div>
        </div>
    <!-- Table -->
    <div class="overflow-x-auto bg-white shadow rounded-md mt-2">
    <div v-if="members && members.length > 0" class="overflow-x-auto">
      <table class="min-w-full table-auto text-sm">
        <thead>
          <tr class="bg-gray-100 dark:bg-slate-700 text-left">
            <th class="px-4 py-2">Sr No.</th>
            <th class="px-4 py-2">Division No.</th>
            <th class="px-4 py-2">Member's Name</th>
            <th class="px-4 py-2">State</th>
            <th class="px-4 py-2">Party Name</th>
            <th class="px-4 py-2">Attendance Time</th>
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
            <td class="px-4 py-2">
              <span class="text-sm font-medium">{{ member.partyName || 'N/A' }}</span>
            </td>
            <td class="px-4 py-2">{{ member.submitted }}</td>
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
      
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed,onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Loading, Button } from '@sds/oneui-common-ui';
import Swal from 'sweetalert2';
import { PERMISSIONS, hasPermission } from "@/utils/rbac";
import { fetchDailyAttendance, fetchSessions, } from "@/services/attendanceService";
import { useI18n } from "vue-i18n";
import "vue-multiselect/dist/vue-multiselect.min.css";
import useLocalDate from "@/composables/useLocalDate";
import Tabs from "./Tabs.vue";
import Multiselect from "vue-multiselect";

const { t, locale } = useI18n();
const isLoading = ref(true);
const inputDateRef = ref(null)
const sessionDetails = ref({});
const selectedToSession = ref([]);
const filterToSessions = ref([]);

const members = ref([]);
const today = new Date().toISOString().split('T')[0];
const selectedDate = ref(today);

const maxDate = computed(() => {
  let d = new Date(sessionDetails.value?.session_end_date);
  if (isNaN(d)) { // handle invalid date
    d = new Date();
  } 
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const minDate = computed(() => {
  let d = new Date(sessionDetails.value?.session_start_date);
  if (isNaN(d)) { // handle invalid date
    d = new Date();
    d.setFullYear(d.getFullYear() - 6); // Subtract 6 years
  } 
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
});

const previousDay = ref({
    all: 0,
    notSigned:0,
    signed:0,
    notRequired: 0,
    date: ''
  });

const handleInputDateClick = () => {
  inputDateRef.value.showPicker();
}

const getSessions = async () => {
  const response = await fetchSessions();
  if ( response.length ) {
    filterToSessions.value = response;
    selectedToSession.value = sessionDetails.value = response[0];
  } else {
    console.log("Session is not loaded");
  }
  //fetchAttendanceData();
};

const fetchPreviousDayAttendance = async () => {
  previousDay.value.date = new Date(
    new Date(selectedDate.value).setDate(
      new Date(selectedDate.value).getDate() - 1
    )
  ).toISOString().split('T')[0];
  const response = await fetchDailyAttendance({date: previousDay.value.date, session_id: sessionDetails.value.id});
  if (response.success_code === 200 && response.data) {
    previousDay.value.all = response.data.attendanceTotals?.totalAttendance || 0;
    previousDay.value.signed = response.data.attendanceTotals?.totalSigned || 0;
    previousDay.value.notSigned = response.data.attendanceTotals?.totalNotSigned || 0;
    previousDay.value.notRequired = response.data.attendanceTotals?.totalNotRequired || 0;
  }
};

const totalAttendance = ref(0);
const totalSigned = ref(0);
const totalNotSigned = ref(0);
const totalNotRequired = ref(0);
const totalPaperSigned = ref(0);
const lastUpdatedTime = ref(0);
const isPublished = ref(false);

const fetchAttendanceData = async () => {
  try {
    isLoading.value = true;
    const response = await fetchDailyAttendance({date: selectedDate.value, session_id: sessionDetails.value.id});    
    isLoading.value = false;
    if (response.success_code === 200 && response.data) {
      const apiData = response.data;
      
      // Update all the reactive data
      totalAttendance.value = apiData.attendanceTotals?.totalAttendance || 0;
      totalSigned.value = apiData.attendanceTotals?.totalSigned || 0;
      totalNotSigned.value = apiData.attendanceTotals?.totalNotSigned || 0;
      totalNotRequired.value = apiData.attendanceTotals?.totalNotRequired || 0;
      totalPaperSigned.value = apiData.attendanceTotals?.papersigncount || 0;
      lastUpdatedTime.value = apiData.attendanceTotals?.last_updated_at_time || "";      
      isPublished.value = apiData.is_published || 0;

      members.value = (apiData.members || []).map((member, index) => ({
        srNo: index + 1,
        divisionNo: member.division_no,
        name: member.name,
        state: member.state,
        submitted: member.attendance_time,
        status: member.is_signed,
        partyName: member.party_name,
        imageUrl: member.profile_photo || "https://via.placeholder.com/40",
      }));
      fetchPreviousDayAttendance();
    }
    
  } catch (error) {
    console.error('Error refreshing attendance data:', error);
  }
};

//watch(() => selectedDate.value, fetchPreviousDayAttendance, {immediate:true})
watch(
  [selectedToSession, selectedDate], 
  ([toSession, newDate]) => {
  if (toSession) {
    sessionDetails.value = toSession;
    fetchAttendanceData();
  }
  if ( newDate ) {
    fetchAttendanceData();
  }
});

const imagesLoaded = ref(false);
let pdfMake = null;
onMounted( async () => {
  await getSessions();
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
    const fileName = `All_Members_${formatDateForFileName(new Date())}.pdf`;

    // Create table data
    const tableBody = [
      [
        { text: 'Sr No.', style: 'tableHeader' },
        { text: 'Division No.', style: 'tableHeader' },
        { text: "Member's Name", style: 'tableHeader' },
        { text: 'State', style: 'tableHeader' },
        { text: 'Party Name', style: 'tableHeader' },
        { text: 'Attendance Time', style: 'tableHeader' },
        { text: 'Attendance Status', style: 'tableHeader' },
      ],
    ];

    members.value.forEach((member, index) => {
      tableBody.push([
        { text: (index + 1).toString(), style: 'tableCell' },
        { text: member.divisionNo || '', style: 'tableCell' },
        { text: member.name || '', style: 'tableCell' },
        { text: member.state || '', style: 'tableCell' },
        { text: member.partyName || 'N/A', style: 'tableCell' },
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
        {
          stack: [
            { text: 'Rajya Sabha Secretariat'.toLocaleUpperCase(), alignment: 'center', decoration: 'underline' },
            { text: '[Lobby Office]'.toLocaleUpperCase(), alignment: 'center' },
            { text: [...ordinalSup(sessionDetails.value.session_number),' SESSION OF RAJYA SABHA'], alignment: 'center', margin:[0,30,0,0] },
            { text: 'Attendance Report of Honble members of'.toLocaleUpperCase(), alignment: 'center', margin:[0,30,0,0] },
            { text: ['RAJYA SABHA FOR ', {text: useLocalDate(selectedDate.value, 'dd-mm-yyyy')}], alignment: 'center' },
            { text: '(At Adjournment of the house)'.toLocaleUpperCase(), alignment: 'center', margin:[0,0,0,30]},

            {
              table: {
                widths: ['70%', '30%'],
                body: [
                  [
                    { text: 'Total Members of Rajya Sabha', alignment: 'left' },
                    { text: totalAttendance.value, alignment: 'center' },
                  ],
                  [
                    {
                      text: [
                        'Members not required to sign\n',
                        { text: '(HDC, Ministers, LOP)', fontSize: 9 }
                      ],
                      alignment: 'left',
                    },
                    { text: totalNotRequired.value, alignment: 'center' },
                  ],
                  [
                    { text: 'Members who signed', alignment: 'left' },
                    { text: totalSigned.value, alignment: 'center' },
                  ],
                  [
                    { text: 'Members who did not signed', alignment: 'left' },
                    { text: totalNotSigned.value, alignment: 'center' },
                  ],
                  [
                    { text: 'Absentee percentage', alignment: 'left' },
                    { text: [ totalAttendance.value > 0 ? Number(totalNotSigned.value * 100 / totalAttendance.value).toFixed(2) : 0.00, {text:'%'}], alignment: 'center' },
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
                        { text: `(${numberToOrdinalWords(sessionDetails.value.session_number)} Session)`, style: 'sessionInfo', alignment: 'center' },
                        {
                          text: `Parliament House, New Delhi, Dated ${useLocalDate(selectedDate.value, 'dd-mm-yyyy')}`,
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
            { text: 'Total: ' + totalAttendance.value, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Signed', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + totalSigned.value, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Not Required', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + totalNotRequired.value, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Not Signed', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + totalNotSigned.value, style: 'reportSubtitle' }
          ],
          border: [true, true, true, true],
          borderColor: ['#b5d4cd', '#b5d4cd', '#b5d4cd', '#b5d4cd'],
          alignment: 'center',
          margin: [0, 15, 0, 15]
        },
        {
          stack: [
            { text: 'Manual Signed', style: 'reportTitle', margin: [0, 0, 0, 8] },
            { text: 'Total: ' + totalPaperSigned.value, style: 'reportSubtitle' }
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
      ],
      footer: function (currentPage, pageCount) {
        return {
          columns: [
            { text: `Generated on: ${formatDateTime(new Date())}`, style: 'footer', alignment: 'left', },
            {
              text: [`Total Records: ${members.value.length},`, '    ' ,`Page ${currentPage} of ${pageCount}`],
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

</script>
