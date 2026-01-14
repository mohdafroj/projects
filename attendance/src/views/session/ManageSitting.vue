<template>
  <Loading v-if="isLoading" />
  <Card v-if="previewSession">
    <div class="flex items-center mb-4">
      <div class="bg-blue-100 rounded-full w-6 h-6 mr-2 flex justify-center items-center p-1 cursor-pointer" title="Back to Session List">
        <Icon icon="heroicons:arrow-left-20-solid" width="24" height="24" @click="backToList"></Icon>
      </div>
      <div class="text-blue-800 mr-4 font-semibold text-base">Session No. {{ sessionData?.session_number || 0 }}</div>
      <div class="mr-4">
        <span class="inline-block text-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-semibold">
          {{ sessionData?.session_name || '' }}
        </span>
      </div>
      <div class="px-4">|</div>
      <div class="text-sm text-neutral-600 text-right font-semibold">

        Duration : {{ formatSlashToDash(sessionData.start_date) || '' }} to {{ formatSlashToDash(sessionData.end_date)
          || '' }}
      </div>
    </div>
    <div class="p-6 bg-gray-100 min-h-screen mt-3">
      <!-- Session Info Grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white p-4 rounded shadow mb-6">
        <div>
          <p class="text-sm text-gray-500 font-semibold">Session Type</p>
          <p class="mt-1 text-gray-800">{{ sessionData.session_name }} {{ sessionData.session_number }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 font-semibold">Session Mode</p>
          <p class="mt-1 text-gray-800">{{ sessionData.session_part.length == 0 ? 'Continue' :
            sessionData.session_part.length + ' Parts' }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 font-semibold">Session Start Date</p>
          <div v-if="sessionData.session_part.length > 0">
            <div v-for="(row, index) in sessionData.session_part" :key="index">
              <p class="mt-1 text-gray-800">{{ row.start_date }}</p>
            </div>
          </div>
          <p v-else class="mt-1 text-gray-800">{{ sessionData.start_date }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 font-semibold">Session End Date</p>
          <div v-if="sessionData.session_part.length > 0">
            <div v-for="(row, index) in sessionData.session_part" :key="index">
              <p class="mt-1 text-gray-800">{{ row.end_date }}</p>
            </div>
          </div>
          <p v-else class="mt-1 text-gray-800">{{ sessionData.end_date }}</p>
        </div>
      </div>

      <!-- Sitting Calendar -->
      <h2 class="text-gray-700 mb-3 mr-4 font-semibold text-base">Sitting Calendar</h2>

      <div class="bg-white rounded-lg shadow mt-3">
        <!-- Header -->
        <div class="font-semibold px-6 py-4 border-b text-gray-700">
          Session Period: {{ formatSlashToDash(sessionData.start_date) }} to {{ formatSlashToDash(sessionData.end_date)
          }}
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full table-auto text-left">
            <thead class="bg-blue-100 text-sm text-gray-600">
              <tr>
                <th class="px-6 py-3">Day & Date</th>
                <th class="px-6 py-3">Schedule</th>
              </tr>
            </thead>
            <tbody class="text-gray-800 text-sm divide-y">
              <tr v-for="(row, index) in sessionData.sitting_dates" :key="index">
                <td class="px-6 py-3">{{ formatToFullDate(row.sitting_date) }}</td>
                <td class="px-6 py-3">Sitting</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-6 flex space-x-4">
        <!-- <Button class="bg-custom-navy hover:bg-blue-600 text-white px-1 py-2" label="Finish sitting Scheduling" @click="backToSessionList">
                    </Button> -->
        <Button class="bg-custom-navy hover:bg-blue-600 text-white px-1 py-2" label="Keep Editing"
          :color="bg - gray - 300" @click="previewSession = false">
        </Button>
      </div>
    </div>
  </Card>

  <Card class="mt-4" v-else>
    <div class="">
      <div class="flex items-center mb-4">
        <div class="bg-blue-100 rounded-full w-6 h-6 mr-2 flex justify-center items-center p-1 cursor-pointer" title="Back to Session List">
          <Icon icon="heroicons:arrow-left-20-solid" width="24" height="24" @click="backToList"></Icon>
        </div>
        <div class="text-blue-800 mr-4 font-semibold text-base">Session No. {{ sessionData?.session_number || 0 }}</div>
        <div class="mr-4">
          <span class="inline-block text-center bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-semibold">
            {{ sessionData?.session_name || '' }}
          </span>
        </div>
        <div class="px-4">|</div>
        <div class="text-sm text-neutral-600 text-right font-semibold">

          Duration : {{ formatSlashToDash(sessionData.start_date) || '' }} to {{ formatSlashToDash(sessionData.end_date)
          || '' }}
        </div>

      </div>

    </div>
    <div>
      <FullCalendar v-if="calendarOptions.plugins" :options="calendarOptions" class="fc-calendar" />
    </div>
  </Card>
  <Modal v-model="showFormModal" title="" size="md" disable-backdrop="true" @close="handleModalClose">
    <!-- Your form or content here -->
    <form @submit.prevent="saveSittingDates">
      <div class="mt-3">
        <input type="hidden" name="seasonDate" :value="seasonDate" />
        <TextInput :modelValue="formattedSittingDate" label="Day & Date" name="day" type="text" :disabled="true" />
      </div>
      <div class="mt-3">
        <TextInput v-model="sitting.scheduleType" label="Schedule" type="select" :options="[
          { value: '', label: 'Select Schedule' },
          { value: 1, label: 'Sitting' },
          { value: 0, label: 'Not Sitting' }
        ]" name="Schedule" :isRequired="true" />
      </div>
      <div class="mt-3" v-if="showNote == true">
        <div class="statusbox flex justify-content-space items-center">
          <div>
            <p><strong>
                {{ sitting.scheduleType == 0 ? 'Not Sitting' : 'Sitting' }}
              </strong>
            </p>
            <p>{{ formatToFullDate(sittingEvent) }}</p>
          </div>
          <div class="close-btn">
            <Icon icon="heroicons:x-mark" @click="closeNote"></Icon>
          </div>
        </div>
      </div>
      <Button v-if="showNote == false" class="bg-custom-navy mt-5" label="Add Schedule" type="button"
        @click="showNote = true" :disabled="sitting.scheduleType === null || sitting.scheduleType === ''" />
      <Button v-if="showNote == true" class="green mt-5" label="Save" type="submit" />
      <br />
    </form>
  </Modal>
</template>

<script setup>
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction';
import { onMounted, ref, watch, computed } from 'vue'
import { Button, Card, Modal, TextInput } from '@sds/oneui-common-ui'
import Icon from '@/ui-components/Icon.vue';
import { getSessionData, postSaveSessionData } from "@/services/rss/sessionService";
import { useRoute, useRouter } from 'vue-router';
import Swal from "sweetalert2";
import { hasPermission, PERMISSIONS } from "@/utils/rbac";
import Loading from "@/components/Loding.vue";



const route = useRoute();
const router = useRouter();
const sessionId = route.query.id;

const showFormModal = ref(false);
const showNote = ref(false);

const validRange = ref({
  start: '',
  end: ''
})

const sittingEvent = ref(''); // Store date as string

const calendarOptions = ref({})

const sessionData = ref({
  session_number: '',
  start_date: '',
  end_date: '',
  session_name: '',
  sitting_dates: []
});

const previewSession = ref(false);
const isLoading = ref(true);

const sitting = ref({
  date: '',
  scheduleType: ''
})

function formatToFullDate(dateStr) {
  const [day, month, year] = dateStr.split('/');
  const date = new Date(`${year}-${month}-${day}`); // Create a proper Date object

  return new Intl.DateTimeFormat('en-US', {
    weekday: 'long',      // Monday
    year: 'numeric',      // 2025
    month: 'long',        // July
    day: 'numeric'        // 1
  }).format(date);        // => "Monday, July 1, 2025"
}

const formattedSittingDate = computed(() => {
  if (!sittingEvent.value) return '';
  const date = new Date(sittingEvent.value);
  const weekday = date.toLocaleDateString('en-US', { weekday: 'long' });
  const day = date.getDate();
  const month = date.toLocaleDateString('en-US', { month: 'long' });
  const year = date.getFullYear();
  return `${weekday}, ${day} ${month}, ${year}`;
});

function formatSlashToDash(dateStr) {
  return dateStr?.replace(/\//g, '-') || '';
}

const closeNote = () => {
  showNote.value = false;
  sitting.value.scheduleType = '';
}



const saveSittingDates = async () => {
  try {
    isLoading.value = true;
    const payload = {
      sitting_date: sittingEvent.value,
      sitting_status: sitting.value.scheduleType
    }
    const response = await postSaveSessionData(sessionData.value.session_number, payload);
    if (response.success_code == 200) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: `Update done successfully!`,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
    else {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Something went wrong!",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
    showFormModal.value = false;
    showNote.value = false;
    getSessionDetails();
  }
  catch (error) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: error.message,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }

}


function parseToISO(dateStr) {
  if (!dateStr) return null; // 🛑 prevent crashing on empty input
  const [day, month, year] = dateStr.split('/');
  return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
}


const getSessionDetails = async () => {
  try {
    const response = await getSessionData(sessionId);
    if (response.success_code == 200) {
      sessionData.value = response.data;
      validRange.value.start = parseToISO(sessionData.value.start_date);
      validRange.value.end = parseToISO(sessionData.value.end_date);

      if (validRange.value.start && validRange.value.end) {
        validRange.value.start = validRange.value.start;
        validRange.value.end = validRange.value.end;
      } else {
        console.warn('Invalid start or end date from API')
      }


      const sessionDates = response.data.sitting_dates;

      const allDatesInRange = [];
      let currentDate = validRange.value.start;

      // Generate all dates between start and end
      while (currentDate <= validRange.value.end) {
        allDatesInRange.push(new Date(currentDate)); // Store each date in the range
        currentDate = addDays(currentDate, 1); // Increment by 1 day
      }

      // Check if the date is present in the sessionDates
      const events = allDatesInRange.map(date => {
        const isSittingDate = sessionDates.some(session => {
          const sessionDate = new Date(parseToISO(session.sitting_date));
          return sessionDate.toDateString() === date.toDateString();
        });

        if (isSittingDate) {
          return {
            title: 'Sitting',
            start: date,
            end: date,
            allDay: true,
            color: '#d1e2fa',
            textColor: '#0b3d91'
          };
        } else {
          return {
            title: 'Not Sitting',
            start: date,
            end: date,
            allDay: true,
            color: '#ecd5d5', // color for "Not Sitting"
            textColor: '#660000'
          };
        }
      });


      if (response.data.session_part.length != 0) {
        const sessionParts = response.data.session_part.map(part => ({
          id: part.id,
          start: parseToISO(part.start_date),
          end: parseToISO(part.end_date),
        }));

        const allDatesInRanges = [];

        // Loop over each session part to generate all dates within those ranges
        response.data.session_part.forEach(range => {
          const rangeStart = parseToISO(range.start_date);
          const rangeEnd = parseToISO(range.end_date);

          let currentDate = rangeStart;

          // Generate all dates within the range
          while (currentDate <= rangeEnd) {
            allDatesInRanges.push(new Date(currentDate)); // Store each date in the range
            currentDate = addDays(currentDate, 1); // Increment by 1 day
          }
        });

        // Check each date against sessionDates
        const events = allDatesInRanges.map(date => {
          const isSittingDate = sessionDates.some(session => {
            const sessionDate = new Date(parseToISO(session.sitting_date));
            return sessionDate.toDateString() === date.toDateString();
          });

          if (isSittingDate) {
            return {
              title: 'Sitting',
              start: date,
              end: date,
              allDay: true,
              color: '#d1e2fa',
              textColor: '#0b3d91'
            };
          } else {
            return {
              title: 'Not Sitting',
              start: date,
              end: date,
              allDay: true,
              color: '#ecd5d5', // color for "Not Sitting"
              textColor: '#660000'
            };
          }
        });
        const allEvents = [...events];

        // parted session calendar view
        calendarOptions.value = {
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'customPreviewButton'
          },
          customButtons: {
            customPreviewButton: {
              text: 'Preview',
              click() {
                previewSession.value = true;
              }
            }
          },
          plugins: [dayGridPlugin, interactionPlugin],
          initialView: 'dayGridMonth',
          selectable: true,
          // Set overall range to allow calendar navigation
          // validRange: {
          //   start: sessionParts[0].start,
          //   end: addDays(sessionParts[sessionParts.length - 1].end, 1),
          // },
          events: allEvents,

          dateClick: info => {
            const clicked = info.date;
            const d = new Date(clicked.getFullYear(), clicked.getMonth(), clicked.getDate());

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (d < today) {
              console.warn('❌ Cannot select past dates');
              return;
            }

            const isAllowed = sessionParts.some(range => {
              const start = new Date(range.start);
              const end = new Date(range.end);

              const s = new Date(start.getFullYear(), start.getMonth(), start.getDate());
              const e = new Date(end.getFullYear(), end.getMonth(), end.getDate());

              return d >= s && d <= e;
            });
            if (isAllowed) {
              sittingEvent.value = info.dateStr;
              showFormModal.value = true;
            } else {
              console.warn('❌ Clicked date is outside allowed range');
            }
          },
          eventClick: info => {
            const clicked = info.event.start;
            const d = new Date(clicked.getFullYear(), clicked.getMonth(), clicked.getDate());
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (d < today) {
              console.warn('❌ Cannot select past dates');
              return;
            }

            const isAllowed = sessionParts.some(range => {
              const start = new Date(range.start);
              const end = new Date(range.end);

              const s = new Date(start.getFullYear(), start.getMonth(), start.getDate());
              const e = new Date(end.getFullYear(), end.getMonth(), end.getDate());

              return d >= s && d <= e;
            });
            if (isAllowed) {
              const eventDate = info.event.start.toLocaleDateString('en-CA');
              sittingEvent.value = eventDate;
              
              showFormModal.value = true;
            } else {
              console.warn('❌ Clicked date is outside allowed range');
            }
          },
          dayCellContent: info => {
            return { html: info.dayNumberText };
          },

          dayCellDidMount: info => {
            const cellDate = info.date;

            const isAllowed = sessionParts.some(range => {
              const start = new Date(range.start);
              const end = new Date(range.end);

              // normalize all to local midnight
              const d = new Date(cellDate.getFullYear(), cellDate.getMonth(), cellDate.getDate());
              const s = new Date(start.getFullYear(), start.getMonth(), start.getDate());
              const e = new Date(end.getFullYear(), end.getMonth(), end.getDate());

              return d >= s && d <= e;
            });

            if (!isAllowed) {
              info.el.style.pointerEvents = 'none';
              info.el.style.backgroundColor = '#f0f0f0';
              const dayNumEl = info.el.querySelector('.fc-daygrid-day-number');
              if (dayNumEl) {
                dayNumEl.style.opacity = '0.7';
                dayNumEl.style.color = '#999';
              }
            }
          }
        };
      }
      else {
        // continuous session calendar view
        calendarOptions.value = {
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'customPreviewButton'
          },
          customButtons: {
            customPreviewButton: {
              text: 'Preview',
              click() {
                previewSession.value = true;
              }
            }
          },
          plugins: [dayGridPlugin, interactionPlugin],
          initialView: 'dayGridMonth',
          selectable: true,
          // validRange: { start: validRange.value.start, end: validRange.value.end },
          events: [
            {
              start: validRange.value.start,
              end: validRange.value.end,
              display: 'background',
              color: 'transparent'
            },
            ...events,
          ],

          dateClick: (info) => {
            const clickedDate = new Date(info.dateStr);
            const start = new Date(validRange.value.start);
            const end = new Date(validRange.value.end);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (clickedDate < today) {
              console.warn('❌ Cannot select past dates');
              return; // Don't proceed
            }
            if (clickedDate < start || clickedDate > end) {
              console.warn('❌ Clicked date is outside valid range');
              return;
            }

            showFormModal.value = true;
            sittingEvent.value = info.dateStr;
          },
          eventClick: (info) => {
            const clickedDate = new Date(info.event.start);
            const start = new Date(validRange.value.start);
            const end = new Date(validRange.value.end);

            // Strip time (normalize to midnight)
            clickedDate.setHours(0, 0, 0, 0);
            start.setHours(0, 0, 0, 0);
            end.setHours(0, 0, 0, 0);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (clickedDate < today) {
              console.warn('❌ Cannot select past dates');
              return; // Don't proceed
            }

            if (clickedDate < start || clickedDate > end) {
              console.warn('❌ Clicked date is outside valid range');
              return;
            }

            // ✅ Passed the check
            sittingEvent.value = info.event.startStr;
            showFormModal.value = true;
          },

          // Only allow selection within the range
          selectAllow: selection => {
            const start = new Date(validRange.value.start);
            const end = new Date(validRange.value.end);

            const selStart = new Date(selection.start);
            const selEnd = new Date(selection.end);

            return selStart >= start && selEnd <= end;
          },

          dayCellDidMount: info => {
            // Normalize all dates to local midnight
            const cellDate = new Date(info.date.getFullYear(), info.date.getMonth(), info.date.getDate());
            const start = new Date(validRange.value.start);
            const end = new Date(validRange.value.end);
            const rangeStart = new Date(start.getFullYear(), start.getMonth(), start.getDate());
            const rangeEnd = new Date(end.getFullYear(), end.getMonth(), end.getDate());

            const isAllowed = cellDate >= rangeStart && cellDate <= rangeEnd;

            if (!isAllowed) {
              info.el.style.pointerEvents = 'none';
              info.el.style.backgroundColor = '#f0f0f0';
              const dayNum = info.el.querySelector('.fc-daygrid-day-number');
              if (dayNum) {
                dayNum.style.color = '#bbb';
                dayNum.style.opacity = '0.9';
                dayNum.title = 'Unavailable';
              }
            } else {
              // ✅ Add a distinct style for allowed (active) dates
              const dayNum = info.el.querySelector('.fc-daygrid-day-number');
              if (dayNum) {
                dayNum.style.color = '#222'; // active dates get darker color
                dayNum.style.opacity = '1';
              }
            }
          }
        }
      }
      isLoading.value = false;
    }
    if (response.error_code == 1422) {
      Swal.fire({
        title: "Error!",
        text: "Session not found",
        icon: "error",
      });
      router.push({ name: 'session' });
    }
  } catch (error) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: error.message,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }

}

function addDays(dateStr, days = 1) {
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) {
    console.error('Invalid date passed to addDays:', dateStr)
    return null
  }
  date.setDate(date.getDate() + days)
  return date.toISOString().split('T')[0]
}


const backToList = () => {
  router.push({ name: 'session' });
}

onMounted(async () => {
  isLoading.value = true;
  await getSessionDetails();
})

</script>

<style scoped>
@import '@/views/session/session.css';
</style>
