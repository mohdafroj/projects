<!-- CalendarPopupDatepickerOptions.vue -->
<template>
  <div class="relative inline-block" ref="root">
    <!-- Input -->
    <input
      type="text"
      :value="displayValue"
      readonly
      @click="toggle"
      @keydown.enter.prevent="toggle"
      @keydown.esc.prevent="close"
      class="w-56 cursor-pointer bg-white border border-gray-300 rounded-lg p-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
      :placeholder="placeholder"
      aria-haspopup="dialog"
      aria-expanded="open"
    />

    <!-- Popup -->
    <transition name="fade">
      <div
        v-if="open"
        class="absolute z-50 mt-2 w-72 bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden"
        :style="popupStyle"
        role="dialog"
        aria-modal="false"
      >
        <FullCalendar :options="calendarOptions" class="fc-theme-standard" />
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-100">
          <button class="text-sm text-gray-500 hover:underline" @click="clear">Clear</button>
          <div class="flex gap-2">
            <button class="text-sm text-gray-600 px-2 py-1 rounded hover:bg-gray-100" @click="close">Cancel</button>
            <button class="text-sm text-blue-600 font-medium px-2 py-1 rounded hover:bg-blue-50" @click="done">Done</button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
/* FullCalendar popup datepicker using :options="calendarOptions" */
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'

/* Props & emits */
const props = defineProps({
  modelValue: { type: String, default: '' }, // 'YYYY-MM-DD'
  placeholder: { type: String, default: 'Select date' },
  highlightWeekends: { type: Boolean, default: true },
  position: { type: String, default: 'bottom-left' }, // simple positioning
  allowRange: { type: Boolean, default: false }, // allow select range vs single date
})
const emit = defineEmits(['update:modelValue', 'change'])

/* state */
const root = ref(null)
const open = ref(false)
const selected = ref(props.modelValue || '')
const events = ref([]) // background events for visual highlighting

/* popup style (simple) */
const popupStyle = computed(() => {
  if (props.position === 'bottom-right') return { right: '0' }
  return { left: '0' }
})

/* display text in input */
const displayValue = computed(() => {
  if (!selected.value) return ''
  // if range: selected may be "start|end"
  if (props.allowRange && selected.value.includes('|')) {
    const [s, e] = selected.value.split('|')
    const ds = new Date(s + 'T00:00:00').toLocaleDateString()
    const de = new Date(e + 'T00:00:00').toLocaleDateString()
    return `${ds} — ${de}`
  }
  const d = new Date(selected.value + 'T00:00:00')
  return d.toLocaleDateString()
})

/* helpers */
function toISO(d) {
  const dd = new Date(d)
  return dd.toISOString().slice(0, 10)
}

/* dayCellDidMount: per-cell classes */
function dayCellDidMount(arg) {
  const iso = toISO(arg.date)
  const todayISO = toISO(new Date())
  if (iso === todayISO) arg.el.classList.add('fc-today-custom')
  const day = arg.date.getDay()
  if (props.highlightWeekends && (day === 0 || day === 6)) arg.el.classList.add('fc-weekend-custom')
}

/* dateClick handler for single-date selection */
function dateClick(info) {
  if (!props.allowRange) {
    selected.value = info.dateStr
    emit('update:modelValue', info.dateStr)
    emit('change', info.dateStr)
    // highlight selected
    setSelectedBg(info.dateStr)
    // close shortly after so user sees highlight
    setTimeout(() => close(), 120)
  }
}

/* select handler for ranges */
function handleSelect(selectionInfo) {
  if (!props.allowRange) return
  // selectionInfo.startStr, selectionInfo.endStr (end exclusive in FullCalendar)
  // convert end to inclusive by subtracting one day
  const start = selectionInfo.startStr
  // FullCalendar end is exclusive; compute last included date
  const endDate = new Date(selectionInfo.end)
  endDate.setDate(endDate.getDate() - 1)
  const end = endDate.toISOString().slice(0, 10)
  selected.value = `${start}|${end}`
  emit('update:modelValue', selected.value)
  emit('change', selected.value)
  // highlight range
  events.value = [
    ...events.value.filter(e => e.extendedProps?.marker !== 'range-selected'),
    {
      start,
      end: new Date(selectionInfo.end).toISOString().slice(0, 10), // fullCalendar expects exclusive end for background ranges
      display: 'background',
      backgroundColor: 'rgba(99,102,241,0.12)',
      extendedProps: { marker: 'range-selected' }
    }
  ]
}

/* set single-date background highlight */
function setSelectedBg(dateStr) {
  events.value = [
    ...events.value.filter(e => e.extendedProps?.marker !== 'background-selected'),
    {
      start: dateStr,
      display: 'background',
      backgroundColor: '#bfdbfe',
      extendedProps: { marker: 'background-selected' }
    }
  ]
}

/* clear selection */
function clear() {
  selected.value = ''
  events.value = events.value.filter(e => !(e.extendedProps && (e.extendedProps.marker === 'background-selected' || e.extendedProps.marker === 'range-selected')))
  emit('update:modelValue', '')
  emit('change', '')
  close()
}

/* done button */
function done() {
  // just close - value already emitted on selection
  close()
}

/* open/close */
function toggle() { open.value = !open.value }
function close() { open.value = false }

/* outside click */
function onDocClick(e) {
  if (!root.value) return
  if (!root.value.contains(e.target)) open.value = false
}

/* keyboard support for accessibility */
function onKeydown(e) {
  if (e.key === 'Escape') close()
  if (e.key === 'Enter') toggle()
}

/* Build reactive calendarOptions to pass to FullCalendar */
const calendarOptions = reactive({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
  selectable: props.allowRange,
  selectMirror: true,
  dateClick,            // single click
  select: handleSelect, // drag select for ranges
  dayCellDidMount,
  // events as fetcher to keep reactive
  events: (fetchInfo, successCallback) => {
    successCallback(events.value)
  },
  fixedWeekCount: false,
  dayMaxEventRows: false,
  validRange: {
    start: new Date().toISOString().split('T')[0] // disallow past dates by default
  }
})

/* sync modelValue -> internal selected */
watch(() => props.modelValue, (v) => {
  if (!v) {
    selected.value = ''
    events.value = events.value.filter(e => !(e.extendedProps && (e.extendedProps.marker === 'background-selected' || e.extendedProps.marker === 'range-selected')))
    return
  }
  selected.value = v
  // reflect highlight when parent sets value externally
  if (props.allowRange && v.includes('|')) {
    const [s, e] = v.split('|')
    events.value = [
      ...events.value.filter(e => e.extendedProps?.marker !== 'range-selected'),
      {
        start: s,
        end: new Date(new Date(e).getTime() + 24*60*60*1000).toISOString().slice(0,10), // make exclusive end
        display: 'background',
        backgroundColor: 'rgba(99,102,241,0.12)',
        extendedProps: { marker: 'range-selected' }
      }
    ]
  } else if (!props.allowRange) {
    setSelectedBg(v)
  }
})

/* lifecycle */
onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>

/* Transition */
.fade-enter-active, .fade-leave-active { transition: opacity .12s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* FullCalendar small tweaks */
.fc { font-family: inherit; font-size: 0.9rem; }
.fc .fc-toolbar-title { font-weight: 600; font-size: 0.95rem; }
.fc .fc-daygrid-day-number { font-size: 0.85rem; padding: 0.25rem 0.35rem; }

/* custom day styling */
.fc-daygrid-day.fc-today-custom {
  box-shadow: inset 0 0 0 1px rgba(14,165,233,0.12);
  background-color: rgba(59,130,246,0.06);
}
.fc-daygrid-day.fc-weekend-custom { background-color: rgba(239,68,68,0.04); }

/* smooth cell hover */
.fc .fc-daygrid-day:hover { background-color: #eff6ff; }
</style>
