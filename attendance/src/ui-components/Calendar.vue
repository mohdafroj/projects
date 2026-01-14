<template>
  <div class="p-4 max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-4">
      <button @click="prevMonth" class="px-3 py-1 bg-gray-200 rounded">
        &lt;
      </button>
      <div class="text-xl font-semibold">{{ monthName }} {{ currentYear }}</div>
      <button @click="nextMonth" class="px-3 py-1 bg-gray-200 rounded">
        &gt;
      </button>
    </div>

    <div class="grid grid-cols-7 text-center font-bold mb-2">
      <div v-for="day in weekDays" :key="day">{{ day }}</div>
    </div>

    <div class="grid grid-cols-7 gap-2">
      <div
        v-for="(day, index) in calendarDays"
        :key="index"
        class="h-24 border rounded relative p-1 text-sm cursor-pointer flex flex-col items-center text-center"
        :class="[
          isSunday(day) && 'bg-red-200 text-red-700',
          isSaturday(day) && 'bg-yellow-100',
        ]"
        @click="editNote(day)"
      >
        <div
          class="text-xs font-semibold p-2"
          :class="[isToday(day) && 'bg-blue-600 font-bold  rounded-full']"
        >
          {{ day?.date }}
        </div>

        <div
          v-if="notes[day?.key]"
          class="mt-1 px-2 py-1 text-xs bg-green-600 text-white rounded-md w-full truncate"
        >
          {{ notes[day?.key] }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";

const today = new Date();
const currentMonth = ref(today.getMonth());
const currentYear = ref(today.getFullYear());
const notes = ref({});

// Load from localStorage
onMounted(() => {
  const stored = localStorage.getItem("calendar-notes");
  if (stored) notes.value = JSON.parse(stored);
});

// Watch and persist
watch(
  notes,
  () => {
    localStorage.setItem("calendar-notes", JSON.stringify(notes.value));
  },
  { deep: true },
);

const weekDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const monthName = computed(() =>
  new Date(currentYear.value, currentMonth.value).toLocaleString("default", {
    month: "long",
  }),
);

const daysInMonth = computed(() =>
  new Date(currentYear.value, currentMonth.value + 1, 0).getDate(),
);

const startDay = computed(() =>
  new Date(currentYear.value, currentMonth.value, 1).getDay(),
);

const calendarDays = computed(() => {
  const days = [];
  console.log("start days", startDay.value);

  for (let i = 0; i < startDay.value; i++) days.push(null);
  for (let d = 1; d <= daysInMonth.value; d++) {
    const key = `${currentYear.value}-${String(currentMonth.value + 1).padStart(
      2,
      "0",
    )}-${String(d).padStart(2, "0")}`;
    days.push({ date: d, key });
  }

  return days;
});

const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11;
    currentYear.value--;
  } else {
    currentMonth.value--;
  }
};

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0;
    currentYear.value++;
  } else {
    currentMonth.value++;
  }
};

const isToday = day => {
  if (!day) return false;
  const now = new Date();
  return (
    now.getDate() === day.date &&
    now.getMonth() === currentMonth.value &&
    now.getFullYear() === currentYear.value
  );
};

const isSunday = day => {
  if (!day) return false;
  const date = new Date(day.key);
  return date.getDay() === 0;
};

const isSaturday = day => {
  if (!day) return false;
  const date = new Date(day.key);
  return date.getDay() === 6;
};

const editNote = day => {
  if (!day) return;

  const current = notes.value[day.key] || "";
  const text = prompt(`Enter note/meeting for ${day.key}:`, current);
  if (text !== null) {
    notes.value[day.key] = text.trim();
  }
};
</script>

<style scoped>
.grid-cols-7 {
  grid-template-columns: repeat(7, minmax(0, 1fr));
}
</style>
