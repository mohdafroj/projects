<template>
  <div class="p-4">
    <div class="flex justify-between items-center mb-4">
      <button @click="prevMonth">&#8592;</button>
      <h2 class="text-lg font-bold">{{ currentMonthYear }}</h2>
      <button @click="nextMonth">&#8594;</button>
    </div>

    <div class="grid grid-cols-7 text-center font-semibold mb-2">
      <div v-for="d in weekDays" :key="d">{{ d }}</div>
    </div>

    <div class="grid grid-cols-7 gap-1">
      <div
        v-for="day in calendarDays"
        :key="day.key"
        class="relative border flex flex-col p-1 h-28 overflow-hidden cursor-pointer"
        :class="{
          'bg-red-100 text-red-700': day.isSunday && day.isCurrentMonth,
          'bg-yellow-100': day.isSaturday && day.isCurrentMonth,
          'text-gray-400': !day.isCurrentMonth,
        }"
        @click="openModal(day)"
      >
        <!-- <div class="absolute top-1 left-1 text-xs">{{ day.date }}</div> -->
        <div class="absolute top-1 left-[50%] font-semibold text-xs">{{ day.date }}</div>
        <div
          v-if="notes[day.key]"
          class="mt-6 text-xs bg-green-200 rounded p-1 text-center truncate"
        >
          {{ notes[day.key] }}
        </div>
        <!-- <div
          v-for="(meeting, index) in meetings[day.key] || []"
          :key="index"
          class="mt-1 bg-blue-100 text-xs p-1 rounded cursor-pointer truncate"
          @click.stop="editMeeting(day.key, index)"
        >
          {{ meeting.time }} - {{ meeting.title }}
        </div> -->
        <div
          v-for="(meeting, index) in meetings[day.key] || []"
          :key="index"
          class="mt-1 bg-blue-100 text-xs p-1 rounded flex justify-between items-center gap-1"
        >
          <span class="truncate" @click.stop="editMeeting(day.key, index)">
            {{ meeting.time }} - {{ meeting.title }}
          </span>
          <button
            class="text-red-500"
            @click.stop="deleteMeetingByKey(day.key, index)"
            title="Delete meeting"
          >
            <Icon icon="mdi:trash-can-outline" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div
      v-if="modalDay"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10"
    >
      <div class="bg-white p-4 rounded w-80">
        <h3 class="text-lg font-bold mb-2">{{ modalTitle }}</h3>

        <div class="mb-2">
          <label class="text-sm">Note:</label>
          <textarea
            v-model="tempNote"
            class="w-full border rounded p-1 mt-1"
          ></textarea>
        </div>

        <div class="mb-2">
          <label class="text-sm">Meeting Time:</label>
          <input
            v-model="tempMeetingTime"
            type="time"
            class="w-full border rounded p-1 mt-1"
          />
        </div>

        <div class="mb-2">
          <label class="text-sm">Meeting Title:</label>
          <input
            v-model="tempMeetingTitle"
            type="text"
            class="w-full border rounded p-1 mt-1"
          />
        </div>

        <div class="flex justify-end gap-2 mt-4">
          <button @click="cancelModal" class="px-3 py-1 border rounded">
            Cancel
          </button>
          <button
            @click="saveModal"
            class="px-3 py-1 bg-blue-500 text-white rounded"
          >
            Save
          </button>
        </div>

        <div v-if="editingMeetingIndex !== null" class="text-right mt-2">
          <button @click="deleteMeeting" class="text-xs text-red-500">
            Delete Meeting
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import dayjs from "dayjs";
import { Icon } from "@iconify/vue";

// Keys for localStorage
const NOTES_KEY = "calendar-notes";
const MEETINGS_KEY = "calendar-meetings";

// Reactive state
const today = dayjs();
const current = ref(today.startOf("month"));
const notes = ref({});
const meetings = ref({});
const modalDay = ref(null);
const tempNote = ref("");
const tempMeetingTime = ref("");
const tempMeetingTitle = ref("");
const editingMeetingIndex = ref(null);

const weekDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const currentMonthYear = computed(() => current.value.format("MMMM YYYY"));

// Show only days from the current month
const calendarDays = computed(() => {
  const start = current.value.startOf("month").startOf("week");
  const end = current.value.endOf("month").endOf("week");
  const days = [];

  for (
    let date = start;
    date.isBefore(end) || date.isSame(end);
    date = date.add(1, "day")
  ) {
    days.push({
      date: date.date(),
      key: date.format("YYYY-MM-DD"),
      isSunday: date.day() === 0,
      isSaturday: date.day() === 6,
      isCurrentMonth: date.month() === current.value.month(),
    });
  }

  return days;
});

onMounted(() => {
  loadData();
});
// Load data from localStorage on mount
function loadData() {
  const storedNotes = localStorage.getItem(NOTES_KEY);
  const storedMeetings = localStorage.getItem(MEETINGS_KEY);

  if (storedNotes) notes.value = JSON.parse(storedNotes);
  if (storedMeetings) meetings.value = JSON.parse(storedMeetings);
}

// Save data to localStorage
function saveData() {
  localStorage.setItem(NOTES_KEY, JSON.stringify(notes.value));
  localStorage.setItem(MEETINGS_KEY, JSON.stringify(meetings.value));
}

// Auto-save when notes or meetings change
watch([notes, meetings], saveData, { deep: true });

// Navigation
function prevMonth() {
  current.value = current.value.subtract(1, "month");
}
function nextMonth() {
  current.value = current.value.add(1, "month");
}

// Modal handling
function openModal(day) {
  modalDay.value = day;
  tempNote.value = notes.value[day.key] || "";
  tempMeetingTime.value = "";
  tempMeetingTitle.value = "";
  editingMeetingIndex.value = null;
}

function closeModal() {
  modalDay.value = null;
  tempNote.value = "";
  tempMeetingTime.value = "";
  tempMeetingTitle.value = "";
  editingMeetingIndex.value = null;
}

function cancelModal() {
  closeModal();
}

function saveModal() {
  if (tempNote.value) notes.value[modalDay.value.key] = tempNote.value;

  if (tempMeetingTime.value && tempMeetingTitle.value) {
    if (!meetings.value[modalDay.value.key])
      meetings.value[modalDay.value.key] = [];

    if (editingMeetingIndex.value !== null) {
      meetings.value[modalDay.value.key][editingMeetingIndex.value] = {
        time: tempMeetingTime.value,
        title: tempMeetingTitle.value,
      };
    } else {
      meetings.value[modalDay.value.key].push({
        time: tempMeetingTime.value,
        title: tempMeetingTitle.value,
      });
    }
  }

  closeModal();
}

function editMeeting(dayKey, index) {
  modalDay.value = calendarDays.value.find(d => d.key === dayKey);
  tempNote.value = notes.value[dayKey] || "";
  const meeting = meetings.value[dayKey][index];
  tempMeetingTime.value = meeting.time;
  tempMeetingTitle.value = meeting.title;
  editingMeetingIndex.value = index;
}

function deleteMeeting() {
  if (modalDay.value && editingMeetingIndex.value !== null) {
    meetings.value[modalDay.value.key].splice(editingMeetingIndex.value, 1);
    if (meetings.value[modalDay.value.key].length === 0) {
      delete meetings.value[modalDay.value.key];
    }
    closeModal();
  }
}

function deleteMeetingByKey(dayKey, index) {
  if (meetings.value[dayKey]) {
    meetings.value[dayKey].splice(index, 1);

    // Optional: remove note on delete
    if (notes.value[dayKey]) {
      delete notes.value[dayKey];
    }

    if (meetings.value[dayKey].length === 0) {
      delete meetings.value[dayKey];
    }
  }
}
</script>

<style scoped>
textarea,
input[type="text"],
input[type="time"] {
  outline: none;
}
button:hover .iconify {
  transform: scale(1.1);
  transition: transform 0.2s ease;
}
</style>
